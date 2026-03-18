<?php

namespace App\Http\Controllers\Company;

use App\Enums\DeliverySource;
use App\Enums\DeliveryMethod;
use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Delivery;
use App\Models\DeliveryProvider;
use App\Models\Item;
use App\Models\Order;
use App\Enums\DeliveryStatus;
use App\Rules\NoDuplicateItem;
use App\Services\DeliveryCreationService;
use App\Services\DeliveryStatusTransitionService;
use Illuminate\Validation\Rule;

class CompanyDeliveryController extends Controller
{
    use LogsCompanyActivity;
    // List deliveries for the authenticated company with filtering, searching, and pagination
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;

        // Validate request parameters
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'rider_id' => [
                'nullable',
                Rule::exists('company_rider', 'rider_id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'order_id' => [
                'nullable',
                Rule::exists('orders', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'delivery_source' => 'nullable|string|in:' . implode(',', DeliverySource::values()),
            'delivery_method' => 'nullable|string|in:' . implode(',', DeliveryMethod::values()),
            'status' => 'nullable|string|in:' . implode(',', DeliveryStatus::values()),
            'tracking_number' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_mobile' => 'nullable|string|max:20',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'sort_by' => 'nullable|string|in:created_at,updated_at,expected_delivery_time,delivered_at,collectible_amount,collected_amount',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $query = $company->deliveries()
            ->with(['customer', 'rider', 'order', 'deliveryItems']);

        // Apply filters
        if ($request->filled('rider_id')) {
            $query->where('rider_id', $request->rider_id);
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('delivery_source')) {
            $query->where('delivery_source', $request->delivery_source);
        }

        if ($request->filled('delivery_method')) {
            $query->where('delivery_method', $request->delivery_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tracking_number')) {
            $query->where('tracking_number', 'LIKE', '%' . $request->tracking_number . '%');
        }

        // Search by customer details
        if ($request->filled('customer_name')) {
            $query->whereHas('customer', function($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->customer_name . '%');
            });
        }

        if ($request->filled('customer_mobile')) {
            $query->whereHas('customer', function($q) use ($request) {
                $q->where('mobile_no', 'LIKE', '%' . $request->customer_mobile . '%');
            });
        }

        // Date range filters
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $deliveries = $query->paginate($perPage);

        // Format each delivery's items
        $formattedDeliveries = $deliveries->getCollection()->map(function ($delivery) {
            $deliveryArray = $delivery->toArray();
            $deliveryArray['items'] = $delivery->formatted_items;
            return $deliveryArray;
        });

        // Replace the collection in pagination with formatted data
        $deliveries->setCollection($formattedDeliveries);

        return $this->success([
            'deliveries' => $deliveries->items(),
            'pagination' => [
                'current_page' => $deliveries->currentPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
                'last_page' => $deliveries->lastPage(),
                'from' => $deliveries->firstItem(),
                'to' => $deliveries->lastItem(),
                'has_more_pages' => $deliveries->hasMorePages(),
            ],
            'filters_applied' => [
                'rider_id' => $request->rider_id,
                'order_id' => $request->order_id,
                'delivery_source' => $request->delivery_source,
                'delivery_method' => $request->delivery_method,
                'status' => $request->status,
                'tracking_number' => $request->tracking_number,
                'customer_name' => $request->customer_name,
                'customer_mobile' => $request->customer_mobile,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ], 'Deliveries fetched successfully.');
    }



    // Create a delivery (with new or existing customer)
    public function store(Request $request, DeliveryCreationService $deliveryCreationService)
    {
        $company = Auth::guard('company_user')->user()->company;
        $deliverySource = $request->input(
            'delivery_source',
            $request->filled('order_id') ? DeliverySource::ORDER->value : DeliverySource::STANDALONE->value
        );

        $request->merge(['delivery_source' => $deliverySource]);

        $validated = $request->validate([
            'delivery_source' => 'required|string|in:' . implode(',', DeliverySource::values()),
            'order_id' => [
                Rule::requiredIf($deliverySource === DeliverySource::ORDER->value),
                'nullable',
                Rule::exists('orders', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'rider_id' => [
                'nullable',
                Rule::exists('company_rider', 'rider_id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'customer_id'     => [
                'nullable',
                Rule::exists('customers', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],

            // Standalone customer creation fields
            'customer_name'   => [
                Rule::requiredIf($deliverySource === DeliverySource::STANDALONE->value && ! $request->filled('customer_id')),
                'nullable',
                'string',
                'max:255',
            ],
            'customer_email'  => [
                Rule::requiredIf($deliverySource === DeliverySource::STANDALONE->value && ! $request->filled('customer_id')),
                'nullable',
                'email',
                'max:255',
            ],
            'customer_mobile_no'  => [
                Rule::requiredIf($deliverySource === DeliverySource::STANDALONE->value && ! $request->filled('customer_id')),
                'nullable',
                'string',
                'unique:customers,mobile_no,NULL,id,company_id,' . $company->id
            ],

            // Pickup address - either ID or manual input
            'pickup_address_id' => [
                'nullable',
                Rule::exists('addresses', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'pickup_label'      => 'required_without:pickup_address_id|string',
            'pickup_address'    => 'required_without:pickup_address_id|string',
            'pickup_latitude'   => 'nullable|numeric|between:-90,90',
            'pickup_longitude'  => 'nullable|numeric|between:-180,180',

            // Drop address - either ID or manual input
            'drop_address_id'   => [
                Rule::prohibitedIf($deliverySource === DeliverySource::ORDER->value),
                'nullable',
                Rule::exists('addresses', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'drop_label'        => [
                Rule::prohibitedIf($deliverySource === DeliverySource::ORDER->value),
                Rule::requiredIf($deliverySource === DeliverySource::STANDALONE->value && ! $request->filled('drop_address_id')),
                'nullable',
                'string',
            ],
            'drop_address'      => [
                Rule::prohibitedIf($deliverySource === DeliverySource::ORDER->value),
                Rule::requiredIf($deliverySource === DeliverySource::STANDALONE->value && ! $request->filled('drop_address_id')),
                'nullable',
                'string',
            ],
            'drop_latitude'     => [
                Rule::prohibitedIf($deliverySource === DeliverySource::ORDER->value),
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'drop_longitude'    => [
                Rule::prohibitedIf($deliverySource === DeliverySource::ORDER->value),
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'delivery_notes'  => 'nullable|string',
            'expected_delivery_time' => 'nullable|date',
            'delivery_method'   => 'required|string|in:' . implode(',', DeliveryMethod::values()),
            'provider_name'   => [
                Rule::requiredIf($request->input('delivery_method') === DeliveryMethod::THIRD_PARTY->value),
                'string',
                'max:100',
            ],
            'status'          => 'nullable|string|in:' . implode(',', DeliveryStatus::values()),
            'collectible_amount' => 'nullable|numeric|min:0',
            'collected_amount' => 'nullable|numeric|min:0',

            // Standalone items for delivery
            'items' => [
                Rule::requiredIf($deliverySource === DeliverySource::STANDALONE->value),
                'nullable',
                'array',
                new NoDuplicateItem,
            ],
            'items.*.id' => [
                'nullable',
                Rule::exists('items', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'items.*.name' => [
                'required_without:items.*.id',
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
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.item_notes' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string', // delivery-specific item notes
        ]);

        if ($deliverySource === DeliverySource::ORDER->value) {
            $order = Order::query()
                ->where('company_id', $company->id)
                ->findOrFail($validated['order_id']);

            $delivery = $deliveryCreationService->createFromOrder($company, $order, $validated);
        } else {
            $delivery = $deliveryCreationService->createStandalone($company, $validated);
        }

        // Log activity
        $this->logDeliveryActivity('delivery_created', $delivery);

        // Load relationships and return with formatted items
        $response = $delivery->toArray();
        $response['items'] = $delivery->formatted_items;

        return $this->success($response, 'Delivery created successfully.');
    }

    // Show a single delivery for the authenticated company
    public function show($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $delivery = $company->deliveries()->with(['customer', 'rider', 'order', 'deliveryItems'])->findOrFail($id);

        // Format response with clean items structure
        $response = $delivery->toArray();
        $response['items'] = $delivery->formatted_items;

        return $this->success($response, 'Delivery fetched.');
    }

    // Update delivery: assign rider, update status, and timestamps
    public function update(Request $request, $id, DeliveryStatusTransitionService $deliveryStatusTransitionService)
    {
        $company = Auth::guard('company_user')->user()->company;
        $delivery = $company->deliveries()->findOrFail($id);
        $companyUser = Auth::guard('company_user')->user();

        $request->validate([
            'rider_id' => [
                'nullable',
                Rule::exists('company_rider', 'rider_id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'status' => 'nullable|in:' . implode(',', DeliveryStatus::values()),
            'collected_amount' => 'nullable|numeric|min:0',
        ]);

        $result = $deliveryStatusTransitionService->update($delivery, $request->only([
            'rider_id',
            'status',
            'collected_amount',
        ]), $companyUser);

        return $this->success([
            'delivery' => $result['delivery'],
            'order' => $result['order'],
        ], 'Delivery updated successfully.');
    }

    /**
     * Get delivery options for form dropdowns
     */
    public function getDeliveryOptions(): \Illuminate\Http\JsonResponse
    {
        return $this->success([
            'filters' => [
                'delivery_source' => [
                    'type' => 'select',
                    'label' => 'Delivery Source',
                    'hint' => 'Filter deliveries by standalone or order source.',
                    'default' => '',
                    'options' => array_merge([['label' => 'All', 'value' => '']], DeliverySource::options()),
                ],
            ],
            'delivery_sources' => DeliverySource::options(),
            'delivery_methods' => DeliveryMethod::options(),
            'delivery_providers' => DeliveryProvider::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'sort_order']),
            'delivery_statuses' => DeliveryStatus::options(),
        ], 'Delivery options fetched successfully.');
    }
}

// Note: Add a unique index on ['company_id', 'mobile_no'] in your customers table migration for full DB-level enforcement.
