<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Address;

class CompanyCustomerAddressController extends Controller
{
    use LogsCompanyActivity;

    /**
     * Get all addresses for a specific customer
     */
    public function index($customerId)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $addresses = $customer->addresses()
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($addresses, 'Customer addresses fetched.');
    }

    /**
     * Store a new address for a customer
     */
    public function store(Request $request, $customerId)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $request->validate([
            'address_type' => 'nullable|string',
            'label' => 'nullable|string|max:255',
            'address' => 'required|string',
        ]);

        // Geocode the address
        $latitude = null;
        $longitude = null;
        try {
            $response = \Illuminate\Support\Facades\Http::get('https://nominatim.openstreetmap.org/search', [
                'q' => $request->address,
                'format' => 'json',
                'limit' => 1
            ]);
            if ($response->successful() && isset($response[0])) {
                $latitude = $response[0]['lat'] ?? null;
                $longitude = $response[0]['lon'] ?? null;
            }
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        $address = Address::create([
            'company_id' => $company->id,
            'addressable_id' => $customer->id,
            'addressable_type' => Customer::class,
            'address_type' => $request->address_type,
            'label' => $request->label,
            'address' => $request->address,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        // Log activity
        $this->logActivity(
            'customer_address_added',
            "New address '{$address->label}' added for customer {$customer->name}",
            $customer
        );

        return $this->success($address, 'Address created successfully.');
    }

    /**
     * Update an address
     */
    public function update(Request $request, $customerId, $addressId)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $address = $customer->addresses()
            ->findOrFail($addressId);

        $request->validate([
            'address_type' => 'nullable|string',
            'label' => 'nullable|string|max:255',
            'address' => 'sometimes|required|string',
        ]);

        // Re-geocode if address changed
        if ($request->has('address') && $request->address !== $address->address) {
            try {
                $response = \Illuminate\Support\Facades\Http::get('https://nominatim.openstreetmap.org/search', [
                    'q' => $request->address,
                    'format' => 'json',
                    'limit' => 1
                ]);
                if ($response->successful() && isset($response[0])) {
                    $address->latitude = $response[0]['lat'] ?? null;
                    $address->longitude = $response[0]['lon'] ?? null;
                }
            } catch (\Exception $e) {
                // Log error but don't fail
            }
        }

        $address->update($request->only(['address_type', 'label', 'address']));

        // Log activity
        $this->logActivity(
            'customer_address_updated',
            "Address '{$address->label}' updated for customer {$customer->name}",
            $customer
        );

        return $this->success($address, 'Address updated successfully.');
    }

    /**
     * Delete an address
     */
    public function destroy($customerId, $addressId)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $address = $customer->addresses()
            ->findOrFail($addressId);

        // Log activity before deletion
        $this->logActivity(
            'customer_address_deleted',
            "Address '{$address->label}' deleted for customer {$customer->name}",
            $customer
        );

        $address->delete();

        return $this->success(null, 'Address deleted successfully.');
    }
}
