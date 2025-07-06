<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Delivery;
use App\Models\Customer;
use App\Enums\DeliveryStatus;

class CompanyDeliveryController extends Controller
{
    use LogsCompanyActivity;
    // List deliveries for the authenticated company
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $deliveries = $company->deliveries()->with(['customer', 'deliveryMan'])->get();
        return $this->success($deliveries, 'Deliveries fetched.');
    }
   


    // Create a delivery (with new or existing customer)
    public function store(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $request->validate([
            'delivery_man_id' => 'nullable|exists:delivery_men,id',
            'customer_id'     => 'nullable|exists:customers,id',

            'customer_name'   => 'required_without:customer_id|string|max:255',
            'customer_mobile_no'  => [
                'required_without:customer_id',
                'string',
                'unique:customers,mobile_no,NULL,id,company_id,' . $company->id
            ],
            'customer_address'=> 'required_without:customer_id|string',

            'delivery_address'=> 'required|string',
            // latitude and longitude removed from validation
            'delivery_notes'  => 'nullable|string',
            'delivery_type'   => 'nullable|string',
            'expected_delivery_time' => 'nullable|date',
            'delivery_mode'   => 'nullable|string',
            'assigned_at'     => 'nullable|date',
            // 'details'         => 'nullable|string', // removed, not in migration
            // add other fields as needed
        ]);

        if ($request->customer_id) {
            $customer = Customer::where('company_id', $company->id)->findOrFail($request->customer_id);
        } else {
            $customer = Customer::create([
                'company_id' => $company->id,
                'name'       => $request->customer_name,
                'mobile_no'  => $request->customer_mobile_no,
                'address'    => $request->customer_address,
            ]);
        }

        // Geocode the delivery address
        $latitude = null;
        $longitude = null;
        try {
            $response = \Illuminate\Support\Facades\Http::get('https://nominatim.openstreetmap.org/search', [
                'q' => $request->delivery_address,
                'format' => 'json',
                'limit' => 1
            ]);
            if ($response->successful() && isset($response[0])) {
                $latitude = $response[0]['lat'] ?? null;
                $longitude = $response[0]['lon'] ?? null;
            }
        } catch (\Exception $e) {
            // Optionally log the error, but don't fail the request
        }

        $delivery = Delivery::create([
            'company_id'      => $company->id,
            'delivery_man_id' => $request->delivery_man_id,
            'customer_id'     => $customer->id,
            'delivery_address'=> $request->delivery_address,
            'latitude'        => $latitude,
            'longitude'       => $longitude,
            'delivery_notes'  => $request->delivery_notes,
            'delivery_type'   => $request->delivery_type,
            'expected_delivery_time' => $request->expected_delivery_time,
            'delivery_mode'   => $request->delivery_mode,
            'assigned_at'     => $request->assigned_at,
            'delivered_at'    => $request->delivered_at,
            'amount'          => $request->amount, // add amount to creation
        ]);

        // Log activity
        $this->logDeliveryActivity('delivery_created', $delivery->load(['customer', 'deliveryMan']));

        return $this->success($delivery, 'Delivery created successfully.');
    }

    // Show a single delivery for the authenticated company
    public function show($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $delivery = $company->deliveries()->with(['customer', 'deliveryMan'])->findOrFail($id);
        return $this->success($delivery, 'Delivery fetched.');
    }

    // Update delivery: assign delivery man, update status, and timestamps
    public function update(Request $request, $id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $delivery = $company->deliveries()->findOrFail($id);
      

        $request->validate([
            'delivery_man_id' => 'nullable|exists:delivery_men,id',
            'status' => 'nullable|in:' . implode(',', DeliveryStatus::values()),
        ]);

        if ($request->has('status')) {
            $currentStatus = DeliveryStatus::from($delivery->status);
            $newStatus = DeliveryStatus::from($request->status);
            if (!$currentStatus->canTransitionTo($newStatus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status transition from ' . $currentStatus->value . ' to ' . $newStatus->value,
                ], 422);
            }
            $delivery->status = $request->status;
            if ($newStatus === DeliveryStatus::DELIVERED) {
                $delivery->delivered_at = now();
            }
            
            // Log status change activity
            $this->logDeliveryActivity('delivery_status_changed', $delivery->load(['customer', 'deliveryMan']));
        }
        
        if ($request->has('delivery_man_id')) {
            $delivery->delivery_man_id = $request->delivery_man_id;
            $delivery->assigned_at = now();
            
            // Log assignment activity
            $this->logDeliveryActivity('delivery_assigned', $delivery->load(['customer', 'deliveryMan']));
        }
        
        $delivery->save();

        $delivery->load(['customer', 'deliveryMan']);

        return $this->success($delivery, 'Delivery updated successfully.');
    }
}

// Note: Add a unique index on ['company_id', 'mobile_no'] in your customers table migration for full DB-level enforcement.
