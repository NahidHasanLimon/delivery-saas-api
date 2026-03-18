<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyRiderInvite;
use App\Models\Rider;
use App\Models\OTPVerification;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyRiderInvitationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,verified,expired,canceled',
            'mobile_number' => 'nullable|string|max:13',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'sort_by' => 'nullable|string|in:id,created_at,updated_at,status,mobile_number',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $companyUser = Auth::guard('company_user')->user();
        $company = $companyUser->company;
        if (! $company) {
            return $this->error('Unauthorized company context.', [], 403);
        }

        $query = CompanyRiderInvite::query()
            ->where('company_id', $company->id)
            ->with(['rider:id,name,mobile_no,identification_number,status']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('mobile_number')) {
            $query->where('mobile_number', 'like', '%' . $this->normalizeRiderMobileNumber($request->mobile_number) . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $invites = $query->paginate($perPage);

        return $this->success([
            'invitations' => $invites->items(),
            'pagination' => [
                'current_page' => $invites->currentPage(),
                'per_page' => $invites->perPage(),
                'total' => $invites->total(),
                'last_page' => $invites->lastPage(),
                'from' => $invites->firstItem(),
                'to' => $invites->lastItem(),
                'has_more_pages' => $invites->hasMorePages(),
            ],
            'filters_applied' => [
                'status' => $request->status,
                'mobile_number' => $request->mobile_number,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ], 'Rider invitations fetched successfully.');
    }

    public function store(Request $request, SmsService $smsService)
    {
        // Canonical rider invite mobile format is 13 digits: 880 + local 10-digit number.
        $normalizedMobile = $this->normalizeRiderMobileNumber($request->input('mobile_number'));
        $request->merge(['mobile_number' => $normalizedMobile]);

        $request->validate([
            'mobile_number' => ['required', 'string', 'size:13', 'regex:/^880[0-9]{10}$/'],
        ]);

        $companyUser = Auth::guard('company_user')->user();
        $company = $companyUser->company;

        if (! $company) {
            return $this->error('Unauthorized company context.', [], 403);
        }

        $mobileNumber = trim($request->mobile_number);
        $rider = Rider::where('mobile_no', $mobileNumber)->first();
        $latestInvite = CompanyRiderInvite::query()
            ->where('company_id', $company->id)
            ->where('mobile_number', $mobileNumber)
            ->latest('id')
            ->first();

        if ($latestInvite && $latestInvite->status === 'verified') {
            $riderId = $rider?->id ?? $latestInvite->rider_id;

            if ($riderId) {
                if ($this->isActiveLinked($company->id, $riderId)) {
                    return $this->error('Rider is already in your company.', [], 422);
                }

                $this->upsertCompanyRiderLink($company->id, $riderId);

                return $this->success([
                    'invite_id' => $latestInvite->id,
                    'rider_id' => $riderId,
                    'mobile_number' => $this->maskMobile($mobileNumber),
                    'linked' => true,
                ], 'Rider linked to company.');
            }
        }

        if ($rider && $this->isActiveLinked($company->id, $rider->id)) {
            return $this->error('Rider is already in your company.', [], 422);
        }

        if ($latestInvite && $latestInvite->status === 'pending') {
            return $this->error(
                'Invitation already exists on ' . $latestInvite->created_at->format('Y-m-d H:i:s') . '.',
                [
                    'invite_id' => $latestInvite->id,
                    'status' => $latestInvite->status,
                    'created_at' => $latestInvite->created_at,
                ],
                422
            );
        } else {
            $invite = CompanyRiderInvite::create([
                'company_id' => $company->id,
                'mobile_number' => $mobileNumber,
                'rider_id' => $rider?->id,
                'status' => 'pending',
                'created_by' => $companyUser->id,
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        OTPVerification::create([
            'mobile_no' => $mobileNumber,
            'otp_code' => hash('sha256', $otp),
            'purpose' => 'company_rider_invite',
            'channel' => 'sms',
            'user_type' => 'rider',
            'ref_id_or_context_id' => $invite->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'is_verified' => false,
            'expires_at' => $expiresAt,
        ]);

        $smsService->sendOtp($mobileNumber, $otp);

        return $this->success([
            'invite_id' => $invite->id,
            'expires_at' => $expiresAt->toISOString(),
            'mobile_number' => $this->maskMobile($mobileNumber),
        ], 'Invitation OTP sent successfully.');
    }

    public function resend(Request $request, int $inviteId, SmsService $smsService)
    {
        $companyUser = Auth::guard('company_user')->user();
        $company = $companyUser->company;

        if (! $company) {
            return $this->error('Unauthorized company context.', [], 403);
        }

        $invite = CompanyRiderInvite::query()
            ->where('company_id', $company->id)
            ->find($inviteId);

        if (! $invite) {
            return $this->error('Invitation not found.', [], 404);
        }

        if ($invite->status !== 'pending') {
            return $this->error('Only pending invitations can be resent.', [], 422);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        OTPVerification::create([
            'mobile_no' => $invite->mobile_number,
            'otp_code' => hash('sha256', $otp),
            'purpose' => 'company_rider_invite',
            'channel' => 'sms',
            'user_type' => 'rider',
            'ref_id_or_context_id' => $invite->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'is_verified' => false,
            'expires_at' => $expiresAt,
        ]);

        $smsService->sendOtp($invite->mobile_number, $otp);

        return $this->success([
            'invite_id' => $invite->id,
            'expires_at' => $expiresAt->toISOString(),
            'mobile_number' => $this->maskMobile($invite->mobile_number),
        ], 'Invitation OTP resent successfully.');
    }

    private function isActiveLinked(int $companyId, int $riderId): bool
    {
        return DB::table('company_rider')
            ->where('company_id', $companyId)
            ->where('rider_id', $riderId)
            ->where('status', 'active')
            ->exists();
    }

    private function upsertCompanyRiderLink(int $companyId, int $riderId): void
    {
        $pivot = DB::table('company_rider')
            ->where('company_id', $companyId)
            ->where('rider_id', $riderId)
            ->first();

        if ($pivot) {
            DB::table('company_rider')
                ->where('company_id', $companyId)
                ->where('rider_id', $riderId)
                ->update([
                    'status' => 'active',
                    'updated_at' => Carbon::now(),
                ]);
            return;
        }

        DB::table('company_rider')->insert([
            'company_id' => $companyId,
            'rider_id' => $riderId,
            'status' => 'active',
            'joined_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function normalizeRiderMobileNumber(?string $mobileNumber): string
    {
        $normalized = preg_replace('/[^0-9]/', '', (string) $mobileNumber);

        if (strlen($normalized) === 10) {
            return '880' . $normalized;
        }

        if (str_starts_with($normalized, '880') && strlen($normalized) === 13) {
            return $normalized;
        }

        return $normalized;
    }

    private function maskMobile(string $mobile): string
    {
        $len = strlen($mobile);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4) . substr($mobile, -4);
    }
}
