<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\DeliveryMan;
use App\Services\OtpService;
use App\Services\OtpNotificationService;

class CompanyDeliveryManController extends Controller
{
    use LogsCompanyActivity;

    private OtpService $otpService;
    private OtpNotificationService $otpNotifier;

    public function __construct(OtpService $otpService, OtpNotificationService $otpNotifier)
    {
        $this->otpService = $otpService;
        $this->otpNotifier = $otpNotifier;
    }

    // List all delivery men for the authenticated company
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'sort_by' => 'nullable|string|in:id,name,created_at,last_login_at,activated_at,invited_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $query = $company->deliveryMen();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('delivery_men.name', 'like', "%{$search}%")
                    ->orWhere('delivery_men.email', 'like', "%{$search}%")
                    ->orWhere('delivery_men.mobile_no', 'like', "%{$search}%")
                    ->orWhere('delivery_men.identification_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('delivery_men.status', '=', $request->status );
        }
         if ($request->filled('identification_number')) {
            $query->where('delivery_men.identification_number', '=', $request->identification_number );
        }

        if ($request->filled('from_date')) {
            $query->whereDate('delivery_men.created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('delivery_men.created_at', '<=', $request->to_date);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy("delivery_men.{$sortBy}", $sortOrder);

        $perPage = $request->get('per_page', 15);
        $deliveryMen = $query->paginate($perPage);

        return $this->success([
            'delivery_men' => $deliveryMen->items(),
            'pagination' => [
                'current_page' => $deliveryMen->currentPage(),
                'per_page' => $deliveryMen->perPage(),
                'total' => $deliveryMen->total(),
                'last_page' => $deliveryMen->lastPage(),
                'from' => $deliveryMen->firstItem(),
                'to' => $deliveryMen->lastItem(),
                'has_more_pages' => $deliveryMen->hasMorePages(),
            ],
            'filters_applied' => [
                'search' => $request->search,
                'status' => $request->status,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ], 'Delivery men fetched.');
    }

    // Add (link or create) a delivery man to the company (by email or phone)
    public function store(Request $request)
    {
        return $this->invite($request);
    }

    // Invite a delivery man with OTP activation (SMS + Email)
    public function invite(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'mobile_no' => 'required|string',
            'send_otp' => 'nullable|boolean',
        ]);

        $company = Auth::guard('company_user')->user()->company;

        $deliveryManByMobile = DeliveryMan::where('mobile_no', $request->mobile_no)->first();
        $deliveryManByEmail = $request->email
            ? DeliveryMan::where('email', $request->email)->first()
            : null;

        if ($deliveryManByMobile && $deliveryManByEmail && $deliveryManByMobile->id !== $deliveryManByEmail->id) {
            return $this->error('Email or mobile number already exists for another delivery man.', [], 422);
        }

        $deliveryMan = $deliveryManByMobile ?? $deliveryManByEmail;

        if (! $deliveryMan) {
            $deliveryMan = DeliveryMan::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile_no' => $request->mobile_no,
                'identification_number' => $this->generateUniqueIdentificationNumber(),
                'status' => 'pending',
                'invited_at' => now(),
            ]);
        } else {
            $deliveryMan->name = $request->name ?? $deliveryMan->name;
            if ($request->email && ! $deliveryMan->email) {
                $deliveryMan->email = $request->email;
            }
            if (! $deliveryMan->identification_number) {
                $deliveryMan->identification_number = $this->generateUniqueIdentificationNumber();
            }
            if ($deliveryMan->status !== 'active') {
                $deliveryMan->status = 'pending';
                $deliveryMan->invited_at = now();
            }
            $deliveryMan->save();
        }

        $company->deliveryMen()->syncWithoutDetaching([$deliveryMan->id]);

        $otpSent = null;
        if ($deliveryMan->status !== 'active' && $request->boolean('send_otp', true)) {
            $otp = $this->otpService->create(
                'delivery_man_activation',
                $deliveryMan->mobile_no,
                $deliveryMan->email,
                'sms_email',
                $request->ip(),
                $request->userAgent()
            );
            $otpSent = $this->otpNotifier->sendToBoth($deliveryMan->mobile_no, $deliveryMan->email, $otp);
        }

        $this->logDeliveryManActivity('delivery_man_linked', $deliveryMan, "Delivery man {$deliveryMan->name} linked to company");

        return $this->success([
            'delivery_man' => $deliveryMan,
            'otp_sent' => $otpSent,
            'requires_activation' => $deliveryMan->status !== 'active',
        ], 'Delivery man invited and linked to company.');
    }

    // Remove (unlink) a delivery man from the company
    public function destroy(Request $request, $id)
    {
        $request->merge(['id' => $id]);
        $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $company = Auth::guard('company_user')->user()->company;
        
        // Get the delivery man before unlinking for logging
        $deliveryMan = $company->deliveryMen()->find($id);
        if (! $deliveryMan) {
            return $this->error('Delivery man not found in your company.', [], 404);
        }
        
        $company->deliveryMen()->detach($id);
        
        // Log activity
        $this->logDeliveryManActivity('delivery_man_unlinked', $deliveryMan, "Delivery man {$deliveryMan->name} unlinked from company");
        
        return $this->success(null, 'Delivery man unlinked from company.');
    }

    private function generateUniqueIdentificationNumber(): string
    {
        do {
            $identificationNumber = Str::upper(Str::random(20));
        } while (DeliveryMan::where('identification_number', $identificationNumber)->exists());

        return $identificationNumber;
    }
}
