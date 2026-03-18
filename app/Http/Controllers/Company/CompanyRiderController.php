<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Rider;
use App\Services\OtpService;
use App\Services\OtpNotificationService;

class CompanyRiderController extends Controller
{
    use LogsCompanyActivity;

    private OtpService $otpService;
    private OtpNotificationService $otpNotifier;

    public function __construct(OtpService $otpService, OtpNotificationService $otpNotifier)
    {
        $this->otpService = $otpService;
        $this->otpNotifier = $otpNotifier;
    }

    // List all riders for the authenticated company
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

        $query = $company->riders();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('riders.name', 'like', "%{$search}%")
                    ->orWhere('riders.email', 'like', "%{$search}%")
                    ->orWhere('riders.mobile_no', 'like', "%{$search}%")
                    ->orWhere('riders.identification_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('riders.status', '=', $request->status );
        }
         if ($request->filled('identification_number')) {
            $query->where('riders.identification_number', '=', $request->identification_number );
        }

        if ($request->filled('from_date')) {
            $query->whereDate('riders.created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('riders.created_at', '<=', $request->to_date);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy("riders.{$sortBy}", $sortOrder);

        $perPage = $request->get('per_page', 15);
        $riders = $query->paginate($perPage);

        return $this->success([
            'riders' => $riders->items(),
            'pagination' => [
                'current_page' => $riders->currentPage(),
                'per_page' => $riders->perPage(),
                'total' => $riders->total(),
                'last_page' => $riders->lastPage(),
                'from' => $riders->firstItem(),
                'to' => $riders->lastItem(),
                'has_more_pages' => $riders->hasMorePages(),
            ],
            'filters_applied' => [
                'search' => $request->search,
                'status' => $request->status,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ], 'Riders fetched.');
    }

    // Add (link or create) a rider to the company (by email or phone)
    public function store(Request $request)
    {
        return $this->invite($request);
    }

    public function show(Request $request, int $id)
    {
        $company = Auth::guard('company_user')->user()->company;

        $rider = $company->riders()
            ->where('riders.id', $id)
            ->first();

        if (! $rider) {
            return $this->error('Rider not found in your company.', [], 404);
        }

        $averageRating = ((int) ($rider->total_rating_count ?? 0)) > 0
            ? round(((float) $rider->total_rating_points / (int) $rider->total_rating_count), 2)
            : null;

        return $this->success([
            'rider' => [
                'id' => $rider->id,
                'name' => $rider->name,
                'email' => $rider->email,
                'mobile_no' => $rider->mobile_no,
                'identification_number' => $rider->identification_number,
                'photo_url' => $rider->photo_url,
                'status' => $rider->status,
                'invited_at' => $rider->invited_at,
                'activated_at' => $rider->activated_at,
                'last_login_at' => $rider->last_login_at,
                'company_link' => [
                    'status' => $rider->pivot?->status,
                    'joined_at' => $rider->pivot?->joined_at,
                ],
                'delivery_stats' => [
                    'total_deliveries' => (int) ($rider->total_deliveries ?? 0),
                    'successful_deliveries' => (int) ($rider->successful_deliveries ?? 0),
                    'cancelled_deliveries' => (int) ($rider->cancelled_deliveries ?? 0),
                    'total_rating_count' => (int) ($rider->total_rating_count ?? 0),
                    'average_rating' => $averageRating,
                ],
            ],
        ], 'Rider details fetched.');
    }

    // Invite a rider with OTP activation (SMS + Email)
    public function invite(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'mobile_no' => 'required|string',
            'send_otp' => 'nullable|boolean',
        ]);

        $company = Auth::guard('company_user')->user()->company;

        $riderByMobile = Rider::where('mobile_no', $request->mobile_no)->first();
        $riderByEmail = $request->email
            ? Rider::where('email', $request->email)->first()
            : null;

        if ($riderByMobile && $riderByEmail && $riderByMobile->id !== $riderByEmail->id) {
            return $this->error('Email or mobile number already exists for another rider.', [], 422);
        }

        $rider = $riderByMobile ?? $riderByEmail;

        if (! $rider) {
            $rider = Rider::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile_no' => $request->mobile_no,
                'identification_number' => $this->generateUniqueIdentificationNumber(),
                'status' => 'pending',
                'invited_at' => now(),
            ]);
        } else {
            $rider->name = $request->name ?? $rider->name;
            if ($request->email && ! $rider->email) {
                $rider->email = $request->email;
            }
            if (! $rider->identification_number) {
                $rider->identification_number = $this->generateUniqueIdentificationNumber();
            }
            if ($rider->status !== 'active') {
                $rider->status = 'pending';
                $rider->invited_at = now();
            }
            $rider->save();
        }

        $company->riders()->syncWithoutDetaching([$rider->id]);

        $otpSent = null;
        if ($rider->status !== 'active' && $request->boolean('send_otp', true)) {
            $otp = $this->otpService->create(
                'rider_activation',
                $rider->mobile_no,
                $rider->email,
                'sms_email',
                $request->ip(),
                $request->userAgent()
            );
            $otpSent = $this->otpNotifier->sendToBoth($rider->mobile_no, $rider->email, $otp);
        }

        $this->logRiderActivity('rider_linked', $rider, "Rider {$rider->name} linked to company");

        return $this->success([
            'rider' => $rider,
            'otp_sent' => $otpSent,
            'requires_activation' => $rider->status !== 'active',
        ], 'Rider invited and linked to company.');
    }

    // Remove (unlink) a rider from the company
    public function destroy(Request $request, $id)
    {
        $request->merge(['id' => $id]);
        $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $company = Auth::guard('company_user')->user()->company;
        
        // Get the rider before unlinking for logging
        $rider = $company->riders()->find($id);
        if (! $rider) {
            return $this->error('Rider not found in your company.', [], 404);
        }
        
        $company->riders()->detach($id);
        
        // Log activity
        $this->logRiderActivity('rider_unlinked', $rider, "Rider {$rider->name} unlinked from company");

        return $this->success(null, 'Rider unlinked from company.');
    }

    private function generateUniqueIdentificationNumber(): string
    {
        do {
            $identificationNumber = Str::upper(Str::random(20));
        } while (Rider::where('identification_number', $identificationNumber)->exists());

        return $identificationNumber;
    }
}
