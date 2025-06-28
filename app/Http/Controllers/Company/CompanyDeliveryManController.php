<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DeliveryMan;
use App\Models\Company;

class CompanyDeliveryManController extends Controller
{
    // List all delivery men for the authenticated company
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $deliveryMen = $company->deliveryMen()->get();
        return $this->success($deliveryMen, 'Delivery men fetched.');
    }

    // Add (link or create) a delivery man to the company (by email or phone)
    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'nullable|email',
            'mobile_no' => 'required|string',
            // add other fields as needed
        ]);

        // Check for existing by email or mobile_no
        $deliveryManByEmail = $request->email ? DeliveryMan::where('email', $request->email)->first() : null;
        $deliveryManByMobile = DeliveryMan::where('mobile_no', $request->mobile_no)->first();

        if ($deliveryManByEmail && $deliveryManByMobile && $deliveryManByEmail->id !== $deliveryManByMobile->id) {
            return $this->error('Both email and mobile_no must be unique to create a new delivery man.', [], 422);
        }

        $deliveryMan = $deliveryManByEmail ?: $deliveryManByMobile;

        if (!$deliveryMan) {
            $deliveryMan = DeliveryMan::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'mobile_no' => $request->mobile_no,
                // set other fields as needed
            ]);
        }

        $company = Auth::guard('company_user')->user()->company;
        $company->deliveryMen()->syncWithoutDetaching([$deliveryMan->id]);

        return $this->success($deliveryMan, 'Delivery man linked to company.');
    }

    // Remove (unlink) a delivery man from the company
    public function destroy(Request $request, $id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $company->deliveryMen()->detach($id);
        return $this->success(null, 'Delivery man unlinked from company.');
    }
}
