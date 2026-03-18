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
            'rider_id' => [
                'nullable',
                Rule::exists('company_rider', 'rider_id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'pickup_address_id' => [
                'required',
                Rule::exists('addresses', 'id')
                    ->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'pickup_label' => 'prohibited',
            'pickup_address' => 'prohibited',
            'pickup_latitude' => 'prohibited',
            'pickup_longitude' => 'prohibited',
            'drop_address_id' => 'prohibited',
            'drop_label' => 'prohibited',
            'drop_address' => 'prohibited',
            'drop_latitude' => 'prohibited',
            'drop_longitude' => 'prohibited',
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
