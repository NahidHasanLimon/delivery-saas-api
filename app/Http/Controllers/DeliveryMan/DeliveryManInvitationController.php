<?php

namespace App\Http\Controllers\DeliveryMan;

use App\Http\Controllers\Controller;
use App\Models\CompanyDeliveryManInvite;
use App\Models\DeliveryMan;
use App\Models\OTPVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryManInvitationController extends Controller
{
    public function verify(Request $request, int $invite_id)
    {
        $request->validate([
            'otp' => 'required|string',
        ]);

        $invite = CompanyDeliveryManInvite::find($invite_id);
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
            ->where('purpose', 'company_delivery_invite')
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

            $deliveryMan = DeliveryMan::where('mobile_no', $invite->mobile_number)->first();
            if (! $deliveryMan) {
                $deliveryMan = DeliveryMan::create([
                    'name' => 'Rider ' . substr($invite->mobile_number, -4),
                    'mobile_no' => $invite->mobile_number,
                    'identification_number' => $this->generateUniqueIdentificationNumber(),
                    'status' => 'pending',
                ]);
            }

            $pivot = DB::table('company_delivery_man')
                ->where('company_id', $invite->company_id)
                ->where('delivery_man_id', $deliveryMan->id)
                ->first();

            if ($pivot) {
                DB::table('company_delivery_man')
                    ->where('company_id', $invite->company_id)
                    ->where('delivery_man_id', $deliveryMan->id)
                    ->update([
                        'status' => 'active',
                        'updated_at' => Carbon::now(),
                    ]);
            } else {
                DB::table('company_delivery_man')->insert([
                    'company_id' => $invite->company_id,
                    'delivery_man_id' => $deliveryMan->id,
                    'status' => 'active',
                    'joined_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            $invite->status = 'verified';
            $invite->delivery_man_id = $deliveryMan->id;
            $invite->save();

            return $deliveryMan;
        });

        return $this->success([
            'invite_id' => $invite->id,
            'company_id' => $invite->company_id,
            'delivery_man_id' => $result->id,
            'invite_status' => 'verified',
            'linked' => true,
        ], 'Invitation verified and delivery man linked successfully.');
    }

    private function generateUniqueIdentificationNumber(): string
    {
        do {
            $identificationNumber = Str::upper(Str::random(20));
        } while (DeliveryMan::where('identification_number', $identificationNumber)->exists());

        return $identificationNumber;
    }
}
