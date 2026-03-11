<?php

namespace App\Http\Controllers\Company;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use App\Models\Order;
use App\Services\DeliveryCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CompanyOrderDeliveryController extends Controller
{
    use LogsCompanyActivity;

    public function store(Request $request, int $orderId, DeliveryCreationService $deliveryCreationService)
    {
        $company = Auth::guard('company_user')->user()->company;

        $validated = $request->validate([
            'delivery_man_id' => [
                'nullable',
                Rule::exists('company_delivery_man', 'delivery_man_id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'pickup_address_id' => [
                'nullable',
                Rule::exists('addresses', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'pickup_label' => 'required_without:pickup_address_id|string',
            'pickup_address' => 'required_without:pickup_address_id|string',
            'pickup_latitude' => 'nullable|numeric|between:-90,90',
            'pickup_longitude' => 'nullable|numeric|between:-180,180',
            'drop_address_id' => [
                'nullable',
                Rule::exists('addresses', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'drop_label' => 'nullable|string',
            'drop_address' => 'nullable|string',
            'drop_latitude' => 'nullable|numeric|between:-90,90',
            'drop_longitude' => 'nullable|numeric|between:-180,180',
            'delivery_notes' => 'nullable|string',
            'expected_delivery_time' => 'nullable|date',
            'delivery_method' => 'required|string|in:' . implode(',', DeliveryMethod::values()),
            'provider_name' => [
                Rule::requiredIf($request->input('delivery_method') === DeliveryMethod::THIRD_PARTY->value),
                'string',
                'max:100',
            ],
            'status' => 'nullable|string|in:' . implode(',', DeliveryStatus::values()),
            'collectible_amount' => 'nullable|numeric|min:0',
            'collected_amount' => 'nullable|numeric|min:0',
        ]);

        $order = Order::query()
            ->where('company_id', $company->id)
            ->findOrFail($orderId);

        $delivery = $deliveryCreationService->createFromOrder($company, $order, $validated);

        $this->logDeliveryActivity('delivery_created', $delivery);

        $response = $delivery->toArray();
        $response['items'] = $delivery->formatted_items;

        return $this->success($response, 'Delivery created from order successfully.', 201);
    }
}
