<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Address;

class CompanyAddressController extends Controller
{
    use LogsCompanyActivity;

    /**
     * Get all addresses for the authenticated company
     */
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $query = $company->addresses()
            ->where('addressable_type', 'company')
            ->where('addressable_id', $company->id);
        
        // Filter by address type if requested
        if ($request->has('type')) {
            $query->where('address_type', $request->type);
        }
        
        // Search by label or address
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }
        
        $addresses = $query->orderBy('address_type')->orderBy('label')->get();
        
        return $this->success($addresses, 'Company addresses fetched successfully.');
    }

    /**
     * Store a new company address
     */
    public function store(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $request->validate([
            'address_type' => 'required|string|in:warehouse,pickup_point,office,distribution_center,store,other',
            'label' => 'required|string|max:255',
            'address' => 'required|string',
        ]);

        // Check for duplicate labels within the same company
        $existingAddress = Address::where('company_id', $company->id)
            ->where('addressable_type', 'company')
            ->where('addressable_id', $company->id)
            ->where('label', $request->label)
            ->exists();

        if ($existingAddress) {
            return response()->json([
                'success' => false,
                'message' => "An address with the label '{$request->label}' already exists for your company.",
            ], 422);
        }

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
            'addressable_id' => $company->id,
            'addressable_type' => 'company',
            'address_type' => $request->address_type,
            'label' => $request->label,
            'address' => $request->address,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        // Log activity
        $this->logActivity(
            'company_address_created',
            "New {$address->address_type} address '{$address->label}' added to company",
            $address
        );

        return $this->success($address, 'Company address created successfully.');
    }

    /**
     * Show a specific company address
     */
    public function show($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $address = Address::where('company_id', $company->id)
            ->where('addressable_type', 'company')
            ->where('addressable_id', $company->id)
            ->findOrFail($id);
        
        return $this->success($address, 'Company address fetched successfully.');
    }

    /**
     * Update a company address
     */
    public function update(Request $request, $id)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $address = Address::where('company_id', $company->id)
            ->where('addressable_type', 'company')
            ->where('addressable_id', $company->id)
            ->findOrFail($id);

        $request->validate([
            'address_type' => 'sometimes|required|string|in:warehouse,pickup_point,office,distribution_center,store,other',
            'label' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string',
        ]);

        // Check for duplicate labels if label is being updated
        if ($request->has('label') && $request->label !== $address->label) {
            $existingAddress = Address::where('company_id', $company->id)
                ->where('addressable_type', 'company')
                ->where('addressable_id', $company->id)
                ->where('label', $request->label)
                ->where('id', '!=', $address->id)
                ->exists();

            if ($existingAddress) {
                return response()->json([
                    'success' => false,
                    'message' => "An address with the label '{$request->label}' already exists for your company.",
                ], 422);
            }
        }

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
            'company_address_updated',
            "Company address '{$address->label}' updated",
            $address
        );

        return $this->success($address, 'Company address updated successfully.');
    }

    /**
     * Delete a company address
     */
    public function destroy($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $address = Address::where('company_id', $company->id)
            ->where('addressable_type', 'company')
            ->where('addressable_id', $company->id)
            ->findOrFail($id);

        // Check if address is being used in any deliveries
        $isUsedInDeliveries = \App\Models\Delivery::where('company_id', $company->id)
            ->where(function($query) use ($address) {
                $query->where('pickup_address_id', $address->id)
                      ->orWhere('drop_address_id', $address->id);
            })
            ->exists();

        if ($isUsedInDeliveries) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete address that is being used in deliveries.',
            ], 422);
        }

        // Log activity before deletion
        $this->logActivity(
            'company_address_deleted',
            "Company address '{$address->label}' deleted",
            null
        );

        $address->delete();

        return $this->success(null, 'Company address deleted successfully.');
    }

    /**
     * Get addresses by type
     */
    public function getByType($type)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $addresses = Address::where('company_id', $company->id)
            ->where('addressable_type', 'company')
            ->where('addressable_id', $company->id)
            ->where('address_type', $type)
            ->orderBy('label')
            ->get();
        
        return $this->success($addresses, ucfirst($type) . ' addresses fetched successfully.');
    }
}
