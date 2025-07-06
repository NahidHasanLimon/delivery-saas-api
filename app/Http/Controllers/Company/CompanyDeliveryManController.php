<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DeliveryMan;
use App\Models\Company;

class CompanyDeliveryManController extends Controller
{
    use LogsCompanyActivity;
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

        $exists = DeliveryMan::where(function($q) use ($request) {
            if ($request->email) {
                $q->where('email', $request->email);
            }
            $q->orWhere('mobile_no', $request->mobile_no);
        })->exists();

        if ($exists) {
            return $this->error('Email or mobile number already exists.', [], 422);
        }

        $deliveryMan = DeliveryMan::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'mobile_no' => $request->mobile_no,
            // set other fields as needed
        ]);

        $company = Auth::guard('company_user')->user()->company;
        $company->deliveryMen()->syncWithoutDetaching([$deliveryMan->id]);

        // Log activity
        $this->logDeliveryManActivity('delivery_man_linked', $deliveryMan, "Delivery man {$deliveryMan->name} linked to company");

        return $this->success($deliveryMan, 'Delivery man created and linked to company.');
    }

    // Remove (unlink) a delivery man from the company
    public function destroy(Request $request, $id)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        // Get the delivery man before unlinking for logging
        $deliveryMan = $company->deliveryMen()->findOrFail($id);
        
        $company->deliveryMen()->detach($id);
        
        // Log activity
        $this->logDeliveryManActivity('delivery_man_unlinked', $deliveryMan, "Delivery man {$deliveryMan->name} unlinked from company");
        
        return $this->success(null, 'Delivery man unlinked from company.');
    }
}
