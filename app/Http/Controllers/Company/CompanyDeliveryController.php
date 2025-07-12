<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Delivery;
use App\Models\Customer;
use App\Models\Item;
use App\Models\DeliveryItem;
use App\Enums\DeliveryStatus;

class CompanyDeliveryController extends Controller
{
    use LogsCompanyActivity;
    // List deliveries for the authenticated company
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $deliveries = $company->deliveries()->with(['customer', 'deliveryMan', 'items'])->get();
        
        // Format each delivery's items
        $formattedDeliveries = $deliveries->map(function ($delivery) {
            $deliveryArray = $delivery->toArray();
            $deliveryArray['items'] = $delivery->formatted_items;
            return $deliveryArray;
        });
        
        return $this->success($formattedDeliveries, 'Deliveries fetched.');
    }
   


    // Create a delivery (with new or existing customer)
    public function store(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $request->validate([
            'delivery_man_id' => 'nullable|exists:delivery_men,id',
            'customer_id'     => 'nullable|exists:customers,id',

            // Customer creation fields (required when creating new customer - no customer_id provided)
            'customer_name'   => 'required_without:customer_id|string|max:255',
            'customer_email'  => [
                'required_without:customer_id',
                'email',
                'max:255',
            ],
            'customer_mobile_no'  => [
                'required_without:customer_id',
                'string',
                'unique:customers,mobile_no,NULL,id,company_id,' . $company->id
            ],

            // Pickup address - either ID or manual input
            'pickup_address_id' => 'nullable|exists:addresses,id',
            'pickup_label'      => 'required_without:pickup_address_id|string',
            'pickup_address'    => 'required_without:pickup_address_id|string',

            // Drop address - either ID or manual input
            'drop_address_id'   => 'nullable|exists:addresses,id',
            'drop_label'        => 'required_without:drop_address_id|string',
            'drop_address'      => 'required_without:drop_address_id|string',

            'delivery_notes'  => 'nullable|string',
            'delivery_type'   => 'nullable|string',
            'expected_delivery_time' => 'nullable|date',
            'delivery_mode'   => 'nullable|string',
            'amount'          => 'nullable|numeric|min:0',

            // Items for delivery
            'items' => 'nullable|array',
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.name' => [
                'required_without:items.*.item_id',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && Item::where('company_id', $company->id)->where('name', $value)->exists()) {
                        // Extract the index from the attribute path (e.g., "items.0.name" -> "Item #1")
                        preg_match('/items\.(\d+)\.name/', $attribute, $matches);
                        $itemIndex = isset($matches[1]) ? ($matches[1] + 1) : 'one of your items';
                        $fail("Item #{$itemIndex}: An item with the name '{$value}' already exists for your company.");
                    }
                }
            ],
            'items.*.code' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && Item::where('company_id', $company->id)->where('code', $value)->exists()) {
                        // Extract the index from the attribute path (e.g., "items.0.code" -> "Item #1")
                        preg_match('/items\.(\d+)\.code/', $attribute, $matches);
                        $itemIndex = isset($matches[1]) ? ($matches[1] + 1) : 'one of your items';
                        $fail("Item #{$itemIndex}: An item with the code '{$value}' already exists for your company.");
                    }
                }
            ],
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.item_notes' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string', // delivery-specific item notes
        ]);

        if ($request->customer_id) {
            $customer = Customer::where('company_id', $company->id)->findOrFail($request->customer_id);
        } else {
            $customer = Customer::create([
                'company_id' => $company->id,
                'name'       => $request->customer_name,
                'mobile_no'  => $request->customer_mobile_no,
                'email'      => $request->customer_email,
            ]);
        }

        // Handle pickup address
        $pickupData = $this->handleAddress($request, 'pickup', $company->id);
        
        // Handle drop address  
        $dropData = $this->handleAddress($request, 'drop', $company->id);

        $delivery = Delivery::create([
            'company_id'      => $company->id,
            'delivery_man_id' => $request->delivery_man_id,
            'customer_id'     => $customer->id,
            
            // Pickup address data
            'pickup_address_id' => $pickupData['address_id'],
            'pickup_label'      => $pickupData['label'],
            'pickup_address'    => $pickupData['address'],
            'pickup_latitude'   => $pickupData['latitude'],
            'pickup_longitude'  => $pickupData['longitude'],
            
            // Drop address data
            'drop_address_id'   => $dropData['address_id'],
            'drop_label'        => $dropData['label'],
            'drop_address'      => $dropData['address'],
            'drop_latitude'     => $dropData['latitude'],
            'drop_longitude'    => $dropData['longitude'],
            
            'delivery_notes'  => $request->delivery_notes,
            'delivery_type'   => $request->delivery_type,
            'expected_delivery_time' => $request->expected_delivery_time,
            'delivery_mode'   => $request->delivery_mode,
            'amount'          => $request->amount,
            
            // Set assigned_at only if delivery man is assigned during creation
            'assigned_at'     => $request->delivery_man_id ? now() : null,
            // Status will be 'assigned' if delivery man provided, otherwise 'pending'
            'status'          => $request->delivery_man_id ? DeliveryStatus::ASSIGNED->value : DeliveryStatus::PENDING->value,
        ]);

        // Handle delivery items
        if ($request->has('items') && is_array($request->items)) {
            $this->handleDeliveryItems($request->items, $delivery, $company);
        }

        // Log activity
        $this->logDeliveryActivity('delivery_created', $delivery->load(['customer', 'deliveryMan', 'items']));

        // Load relationships and return with formatted items
        $delivery->load(['customer', 'deliveryMan', 'items']);
        $response = $delivery->toArray();
        $response['items'] = $delivery->formatted_items;

        return $this->success($response, 'Delivery created successfully.');
    }

    // Show a single delivery for the authenticated company
    public function show($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $delivery = $company->deliveries()->with(['customer', 'deliveryMan', 'items'])->findOrFail($id);
        
        // Format response with clean items structure
        $response = $delivery->toArray();
        $response['items'] = $delivery->formatted_items;
        
        return $this->success($response, 'Delivery fetched.');
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

    /**
     * Handle address data - either from saved address or manual input
     */
    private function handleAddress($request, $type, $companyId)
    {
        $addressIdField = $type . '_address_id';
        $labelField = $type . '_label';
        $addressField = $type . '_address';

        // If address ID is provided, fetch from saved addresses
        if ($request->has($addressIdField) && $request->$addressIdField) {
            $address = \App\Models\Address::where('id', $request->$addressIdField)
                ->where('company_id', $companyId)
                ->firstOrFail();

            return [
                'address_id' => $address->id,
                'label' => $address->label,
                'address' => $address->address,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
            ];
        }

        // Manual address input - geocode it
        $address = $request->$addressField;
        $latitude = null;
        $longitude = null;

        try {
            $response = \Illuminate\Support\Facades\Http::get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
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

        return [
            'address_id' => null,
            'label' => $request->$labelField,
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Handle delivery items - either from existing items or create new ones
     */
    private function handleDeliveryItems($items, $delivery, $company)
    {
        foreach ($items as $itemData) {
            if (isset($itemData['item_id']) && $itemData['item_id']) {
                // Use existing item
                $item = Item::where('id', $itemData['item_id'])
                    ->where('company_id', $company->id)
                    ->firstOrFail();
            } else {
                // Create new item
                $item = Item::create([
                    'company_id' => $company->id,
                    'name' => $itemData['name'],
                    'code' => $itemData['code'] ?? null,
                    'unit' => $itemData['unit'] ?? null,
                    'notes' => $itemData['item_notes'] ?? null,
                    'is_active' => true,
                ]);
            }

            // Create delivery item record
            DeliveryItem::create([
                'company_id' => $company->id,
                'delivery_id' => $delivery->id,
                'item_id' => $item->id,
                'quantity' => $itemData['quantity'],
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }
}

// Note: Add a unique index on ['company_id', 'mobile_no'] in your customers table migration for full DB-level enforcement.
