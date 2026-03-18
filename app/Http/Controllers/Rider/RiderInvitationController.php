<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CompanyRiderInvite;
use App\Models\Rider;
use App\Models\OTPVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RiderInvitationController extends Controller
{
    public function verify(Request $request, int $invite_id)
    {
        $request->validate([
            'otp' => 'required|string',
        ]);

        $invite = CompanyRiderInvite::find($invite_id);
        if (! $invite) {
            return $this->error('Invitation not found.', [], 404);
        }

        $companyUser = Auth::guard('company_user')->user();
        if ($companyUser && (! $companyUser->company || (int) $companyUser->company->id !== (int) $invite->company_id)) {
            return $this->error('You are not authorized to verify this invitation.', [], 403);
        }

        if ($invite->status !== 'pending') {
            return $this->error('Invitation is not pending.', [], 422);
        }

        $otpRow = OTPVerification::query()
            ->where('mobile_no', $invite->mobile_number)
            ->where('purpose', 'company_rider_invite')
            ->where('ref_id_or_context_id', $invite->id)
            ->where('is_verified', false)
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id')
            ->first();

        if (! $otpRow) {
            return $this->error('Invalid or expired OTP.', [], 422);
        }

        $providedOtpHash = hash('sha256', $request->otp);
        $otpMatched = hash_equals($otpRow->otp_code, $providedOtpHash) || hash_equals($otpRow->otp_code, $request->otp);

        if (! $otpMatched) {
            return $this->error('Invalid or expired OTP.', [], 422);
        }

        $result = DB::transaction(function () use ($invite, $otpRow) {
            $otpRow->is_verified = true;
            $otpRow->verified_at = Carbon::now();
            $otpRow->save();

            $rider = Rider::where('mobile_no', $invite->mobile_number)->first();
            if (! $rider) {
                $rider = Rider::create([
                    'name' => 'Rider ' . substr($invite->mobile_number, -4),
                    'mobile_no' => $invite->mobile_number,
                    'identification_number' => $this->generateUniqueIdentificationNumber(),
                    'status' => 'pending',
                ]);
            }

            $pivot = DB::table('company_rider')
                ->where('company_id', $invite->company_id)
                ->where('rider_id', $rider->id)
                ->first();

            if ($pivot) {
                DB::table('company_rider')
                    ->where('company_id', $invite->company_id)
                    ->where('rider_id', $rider->id)
                    ->update([
                        'status' => 'active',
                        'updated_at' => Carbon::now(),
                    ]);
            } else {
                DB::table('company_rider')->insert([
                    'company_id' => $invite->company_id,
                    'rider_id' => $rider->id,
                    'status' => 'active',
                    'joined_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            $invite->status = 'verified';
            $invite->rider_id = $rider->id;
            $invite->save();

            return $rider;
        });

        return $this->success([
            'invite_id' => $invite->id,
            'company_id' => $invite->company_id,
            'rider_id' => $result->id,
            'invite_status' => 'verified',
            'linked' => true,
        ], 'Invitation verified and rider linked successfully.');
    }

    private function generateUniqueIdentificationNumber(): string
    {
        do {
            $identificationNumber = Str::upper(Str::random(20));
        } while (Rider::where('identification_number', $identificationNumber)->exists());

        return $identificationNumber;
    }
}
