<?php

namespace App\Http\Controllers\Company;

use App\Enums\OrderDeliveryMedium;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderPaymentMethod;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyOrderController extends Controller
{
    public function options(): \Illuminate\Http\JsonResponse
    {
        return $this->success([
            'filters' => [
                'order_number' => [
                    'type' => 'text',
                    'label' => 'Order Number',
                    'hint' => 'Search by full or partial order number.',
                ],
                'customer_mobile_no' => [
                    'type' => 'text',
                    'label' => 'Customer Mobile',
                    'hint' => 'Filter by customer mobile number.',
                ],
                'customer_code' => [
                    'type' => 'text',
                    'label' => 'Customer Code',
                    'hint' => 'Filter by customer code.',
                ],
                'order_type' => [
                    'type' => 'select',
                    'label' => 'Order Type',
                    'hint' => 'Choose order category.',
                    'default' => '',
                    'options' => array_merge([['label' => 'All', 'value' => '']], OrderType::options()),
                ],
                'delivery_medium' => [
                    'type' => 'select',
                    'label' => 'Delivery Medium',
                    'hint' => 'How delivery is handled.',
                    'default' => '',
                    'options' => array_merge([['label' => 'All', 'value' => '']], OrderDeliveryMedium::options()),
                ],
                'status' => [
                    'type' => 'select',
                    'label' => 'Order Status',
                    'hint' => 'Business status of the order.',
                    'default' => '',
                    'options' => array_merge([['label' => 'All', 'value' => '']], OrderStatus::options()),
                ],
                'delivery_status' => [
                    'type' => 'select',
                    'label' => 'Delivery Status',
                    'hint' => 'Progress of delivery execution.',
                    'default' => '',
                    'options' => array_merge([['label' => 'All', 'value' => '']], OrderDeliveryStatus::options()),
                ],
                'payment_method' => [
                    'type' => 'select',
                    'label' => 'Payment Method',
                    'hint' => 'How payment is collected.',
                    'default' => '',
                    'options' => array_merge([['label' => 'All', 'value' => '']], OrderPaymentMethod::options()),
                ],
                'payment_status' => [
                    'type' => 'select',
                    'label' => 'Payment Status',
                    'hint' => 'Current payment completion state.',
                    'default' => '',
                    'options' => array_merge([['label' => 'All', 'value' => '']], OrderPaymentStatus::options()),
                ],
                'drop_mobile_number' => [
                    'type' => 'text',
                    'label' => 'Recipient Mobile',
                    'hint' => 'Filter by drop-off contact mobile.',
                ],
                'from_date' => [
                    'type' => 'date',
                    'label' => 'From Date',
                    'hint' => 'Orders created on or after this date.',
                ],
                'to_date' => [
                    'type' => 'date',
                    'label' => 'To Date',
                    'hint' => 'Orders created on or before this date.',
                ],
                'per_page' => [
                    'type' => 'select',
                    'label' => 'Rows Per Page',
                    'hint' => 'Number of records to show per page.',
                    'default' => 10,
                    'options' => [
                        ['label' => 'All', 'value' => ''],
                        ['label' => '10', 'value' => 10],
                        ['label' => '15', 'value' => 15],
                        ['label' => '20', 'value' => 20],
                        ['label' => '50', 'value' => 50],
                        ['label' => '100', 'value' => 100],
                    ],
                ],
            ],
        ], 'Order filter options fetched successfully.');
    }

    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;

        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'order_number' => 'nullable|string|max:64',
            'customer_mobile_no' => 'nullable|string|max:32',
            'customer_code' => 'nullable|string|max:255',
            'order_type' => 'nullable|string|in:' . implode(',', OrderType::values()),
            'delivery_medium' => 'nullable|string|in:' . implode(',', OrderDeliveryMedium::values()),
            'status' => 'nullable|string|in:' . implode(',', OrderStatus::values()),
            'delivery_status' => 'nullable|string|in:' . implode(',', OrderDeliveryStatus::values()),
            'payment_method' => 'nullable|string|in:' . implode(',', OrderPaymentMethod::values()),
            'payment_status' => 'nullable|string|in:' . implode(',', OrderPaymentStatus::values()),
            'drop_mobile_number' => 'nullable|string|max:32',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'sort_by' => 'nullable|string|in:id,order_number,created_at,updated_at,amount,paid_amount,collectible_amount,status,delivery_status,payment_status',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $query = Order::query()
            ->where('company_id', $company->id)
            ->with(['customer:id,name,mobile_no']);

        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%' . trim($request->order_number) . '%');
        }
        if ($request->filled('customer_mobile_no')) {
            $customerMobile = trim($request->customer_mobile_no);
            $query->whereHas('customer', function ($q) use ($customerMobile) {
                $q->where('mobile_no', 'like', '%' . $customerMobile . '%');
            });
        }
        if ($request->filled('customer_code')) {
            $customerCode = trim($request->customer_code);
            $query->whereHas('customer', function ($q) use ($customerCode) {
                $q->where('customer_code', 'like', '%' . $customerCode . '%');
            });
        }
        if ($request->filled('order_type')) {
            $query->where('order_type', $request->order_type);
        }
        if ($request->filled('delivery_medium')) {
            $query->where('delivery_medium', $request->delivery_medium);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('drop_mobile_number')) {
            $query->where('drop_mobile_number', 'like', '%' . trim($request->drop_mobile_number) . '%');
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
        $orders = $query->paginate($perPage);

        return $this->success([
            'orders' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
                'has_more_pages' => $orders->hasMorePages(),
            ],
            'filters_applied' => [
                'order_number' => $request->order_number,
                'customer_mobile_no' => $request->customer_mobile_no,
                'customer_code' => $request->customer_code,
                'order_type' => $request->order_type,
                'delivery_medium' => $request->delivery_medium,
                'status' => $request->status,
                'delivery_status' => $request->delivery_status,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status,
                'drop_mobile_number' => $request->drop_mobile_number,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ], 'Orders fetched successfully.');
    }

    public function store(Request $request)
    {
        $companyUser = Auth::guard('company_user')->user();
        $company = $companyUser->company;

        $request->validate([
            'order_number' => 'nullable|string|max:64|unique:orders,order_number,NULL,id,company_id,' . $company->id,

            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'customer_name' => 'required_without:customer_id|string|max:255',
            'customer_mobile_no' => [
                'required_without:customer_id',
                'string',
                'max:32',
                'unique:customers,mobile_no,NULL,id,company_id,' . $company->id,
            ],
            'customer_email' => [
                'nullable',
                'email',
                'max:255',
                'unique:customers,email,NULL,id,company_id,' . $company->id,
            ],

            'order_type' => 'required|string|in:' . implode(',', OrderType::values()),
            'delivery_medium' => 'nullable|string|in:' . implode(',', OrderDeliveryMedium::values()),
            'status' => 'required|string|in:' . implode(',', OrderStatus::values()),
            'delivery_status' => 'nullable|string|in:' . implode(',', OrderDeliveryStatus::values()),

            'drop_contact_name' => 'nullable|string|max:128',
            'drop_mobile_number' => 'nullable|string|max:32',
            'drop_address' => 'nullable|string',
            'drop_area' => 'nullable|string|max:128',
            'drop_latitude' => 'nullable|numeric|between:-90,90',
            'drop_longitude' => 'nullable|numeric|between:-180,180',

            'amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:' . implode(',', OrderPaymentMethod::values()),
            'payment_status' => 'required|string|in:' . implode(',', OrderPaymentStatus::values()),
            'paid_amount' => 'nullable|numeric|min:0',
            'collectible_amount' => 'nullable|numeric|min:0',

            'note' => 'nullable|string|max:255',
            'internal_note' => 'nullable|string|max:255',

            'assigned_delivery_man_id' => [
                'nullable',
                Rule::exists('company_delivery_man', 'delivery_man_id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],

            'items' => 'required|array|min:1',
            'items.*.id' => [
                'nullable',
                Rule::exists('items', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'items.*.name' => 'required_without:items.*.id|string|max:255',
            'items.*.unit' => 'nullable|string|max:64',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        $order = DB::transaction(function () use ($request, $company, $companyUser) {
            if ($request->customer_id) {
                $customer = Customer::where('company_id', $company->id)->findOrFail($request->customer_id);
            } else {
                $customer = Customer::create([
                    'company_id' => $company->id,
                    'name' => $request->customer_name,
                    'mobile_no' => $request->customer_mobile_no,
                    'email' => $request->customer_email,
                ]);
            }

            $order = Order::create([
                'company_id' => $company->id,
                'order_number' => $request->order_number ?: $this->generateOrderNumber($company->id),
                'customer_id' => $customer->id,
                'order_type' => $request->order_type,
                'delivery_medium' => $request->delivery_medium,
                'status' => $request->status,
                'delivery_status' => $request->order_type === OrderType::COUNTER->value ? null : $request->delivery_status,
                'drop_contact_name' => $request->drop_contact_name,
                'drop_mobile_number' => $request->drop_mobile_number,
                'drop_address' => $request->drop_address,
                'drop_area' => $request->drop_area,
                'drop_latitude' => $request->drop_latitude,
                'drop_longitude' => $request->drop_longitude,
                'amount' => $request->amount ?? 0,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status,
                'paid_amount' => $request->paid_amount ?? 0,
                'collectible_amount' => $request->collectible_amount ?? 0,
                'note' => $request->note,
                'internal_note' => $request->internal_note,
                'assigned_delivery_man_id' => $request->assigned_delivery_man_id,
                'created_by' => $companyUser->id,
                'updated_by' => $companyUser->id,
            ]);

            foreach ($request->items as $itemData) {
                if (! empty($itemData['id'])) {
                    $item = Item::where('company_id', $company->id)->findOrFail($itemData['id']);
                } else {
                    $item = Item::create([
                        'company_id' => $company->id,
                        'name' => $itemData['name'],
                        'unit' => $itemData['unit'] ?? null,
                        'unit_price' => $itemData['unit_price'] ?? 0,
                        'is_active' => true,
                    ]);
                }

                OrderItem::create([
                    'company_id' => $company->id,
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price ?? 0,
                    'quantity' => $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            return $order;
        });

        $order->load(['customer', 'orderItems.item']);

        return $this->success($order, 'Order created successfully.', 201);
    }

    public function show($id)
    {
        $company = Auth::guard('company_user')->user()->company;

        $order = Order::query()
            ->where('company_id', $company->id)
            ->with([
                'customer:id,name,mobile_no,email,customer_code,address',
                'orderItems.item',
            ])
            ->find($id);

        if (! $order) {
            return $this->error('Order not found.', [], 404);
        }

        return $this->success($order, 'Order fetched successfully.');
    }

    private function generateOrderNumber(int $companyId): string
    {
        do {
            $candidate = 'ORD-' . $companyId . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Order::where('company_id', $companyId)->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
