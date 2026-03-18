<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\DeliverySource;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCreationService
{
    public function __construct(
        private readonly DeliveryStatusTransitionService $deliveryStatusTransitionService
    ) {
    }

    public function createStandalone(Company $company, array $payload): Delivery
    {
        $effectiveRiderId = $payload['rider_id'] ?? null;
        $status = $this->resolveStatus($payload['status'] ?? null, $effectiveRiderId);

        return DB::transaction(function () use ($company, $payload, $effectiveRiderId, $status) {
            $customer = $this->resolveStandaloneCustomer($company->id, $payload);
            $pickupData = $this->resolveAddress($payload, 'pickup', $company->id);
            $dropData = $this->resolveAddress($payload, 'drop', $company->id);

            $delivery = $this->createDeliveryRecord(
                company: $company,
                payload: $payload,
                source: DeliverySource::STANDALONE->value,
                customerId: $customer->id,
                pickupData: $pickupData,
                dropData: $dropData,
                status: $status,
                effectiveRiderId: $effectiveRiderId,
                order: null,
            );

            $this->createStandaloneDeliveryItems($payload['items'] ?? [], $delivery, $company);

            return $delivery->load(['customer', 'rider', 'order', 'deliveryItems']);
        });
    }

    public function createFromOrder(Company $company, Order $order, array $payload): Delivery
    {
        $order->loadMissing('orderItems');

        if (! $order->is_delivery_order) {
            throw ValidationException::withMessages([
                'order_id' => 'Selected order does not require delivery.',
            ]);
        }

        if (! $order->delivery_address) {
            throw ValidationException::withMessages([
                'order_id' => 'Selected order does not have a delivery address.',
            ]);
        }

        if ($order->status === OrderStatus::COMPLETED->value) {
            throw ValidationException::withMessages([
                'order_id' => 'Delivery cannot be created for a completed order.',
            ]);
        }

        $effectiveRiderId = $payload['rider_id'] ?? null;
        $status = $this->resolveStatus($payload['status'] ?? null, $effectiveRiderId);
        $effectiveCollectibleAmount = round((float) ($payload['collectible_amount'] ?? $order->collectible_amount ?? 0), 2);

        $this->validateInitialOrderDeliveryState($order, $status, $payload, $effectiveCollectibleAmount);

        return DB::transaction(function () use ($company, $order, $payload, $effectiveRiderId, $status) {
            $pickupData = $this->resolveAddress($payload, 'pickup', $company->id);
            $dropData = $this->resolveOrderDropData($payload, $company->id, $order);

            $delivery = $this->createDeliveryRecord(
                company: $company,
                payload: $payload,
                source: DeliverySource::ORDER->value,
                customerId: $order->customer_id,
                pickupData: $pickupData,
                dropData: $dropData,
                status: $status,
                effectiveRiderId: $effectiveRiderId,
                order: $order,
            );

            $this->copyOrderItemsToDelivery($order, $delivery, $company->id);
            $this->syncOrderLinkedDeliveryState($delivery, $status);

            return $delivery->load(['customer', 'rider', 'order', 'deliveryItems']);
        });
    }

    private function resolveStatus(?string $requestedStatus, ?int $effectiveRiderId): string
    {
        $status = $requestedStatus ?? ($effectiveRiderId ? DeliveryStatus::ASSIGNED->value : DeliveryStatus::PENDING->value);

        if ($status === DeliveryStatus::ASSIGNED->value && ! $effectiveRiderId) {
            throw ValidationException::withMessages([
                'rider_id' => 'rider_id is required when status is assigned.',
            ]);
        }

        return $status;
    }

    private function validateInitialOrderDeliveryState(Order $order, string $status, array $payload, float $collectibleAmount): void
    {
        if ($status !== DeliveryStatus::DELIVERED->value) {
            return;
        }

        if ($order->payment_method !== 'cod') {
            return;
        }

        if (! array_key_exists('collected_amount', $payload)) {
            throw ValidationException::withMessages([
                'collected_amount' => 'collected_amount is required when creating a delivered COD delivery.',
            ]);
        }

        $collectedAmount = round((float) $payload['collected_amount'], 2);

        if ($collectedAmount < 0) {
            throw ValidationException::withMessages([
                'collected_amount' => 'collected_amount must be greater than or equal to 0.',
            ]);
        }

        if (! $this->isSameMoney($collectedAmount, $collectibleAmount)) {
            throw ValidationException::withMessages([
                'collected_amount' => 'For COD delivery completion, collected_amount must equal collectible_amount.',
            ]);
        }
    }

    private function createDeliveryRecord(
        Company $company,
        array $payload,
        string $source,
        int $customerId,
        array $pickupData,
        array $dropData,
        string $status,
        ?int $effectiveRiderId,
        ?Order $order,
    ): Delivery {
        return Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order?->id,
            'delivery_source' => $source,
            'rider_id' => $effectiveRiderId,
            'customer_id' => $customerId,

            'pickup_address_id' => $pickupData['address_id'],
            'pickup_label' => $pickupData['label'],
            'pickup_address' => $pickupData['address'],
            'pickup_latitude' => $pickupData['latitude'],
            'pickup_longitude' => $pickupData['longitude'],

            'drop_address_id' => $dropData['address_id'],
            'drop_label' => $dropData['label'],
            'drop_address' => $dropData['address'],
            'drop_latitude' => $dropData['latitude'],
            'drop_longitude' => $dropData['longitude'],

            'delivery_notes' => $payload['delivery_notes'] ?? $order?->note,
            'expected_delivery_time' => $payload['expected_delivery_time'] ?? null,
            'delivery_method' => $payload['delivery_method'] ?? null,
            'provider_name' => $this->resolveProviderName($payload),
            'collectible_amount' => $payload['collectible_amount'] ?? $order?->collectible_amount ?? 0,
            'collected_amount' => $payload['collected_amount'] ?? 0,

            'assigned_at' => $status === DeliveryStatus::ASSIGNED->value ? now() : null,
            'picked_at' => $status === DeliveryStatus::IN_PROGRESS->value ? now() : null,
            'delivered_at' => $status === DeliveryStatus::DELIVERED->value ? now() : null,
            'cancelled_at' => $status === DeliveryStatus::CANCELLED->value ? now() : null,
            'in_progress_at' => $status === DeliveryStatus::IN_PROGRESS->value ? now() : null,
            'status' => $status,
        ]);
    }

    private function resolveStandaloneCustomer(int $companyId, array $payload): Customer
    {
        if (! empty($payload['customer_id'])) {
            return Customer::where('company_id', $companyId)->findOrFail($payload['customer_id']);
        }

        return Customer::create([
            'company_id' => $companyId,
            'name' => $payload['customer_name'],
            'mobile_no' => $payload['customer_mobile_no'],
            'email' => $payload['customer_email'],
        ]);
    }

    private function resolveAddress(array $payload, string $type, int $companyId): array
    {
        $addressIdField = $type . '_address_id';
        $labelField = $type . '_label';
        $addressField = $type . '_address';
        $latitudeField = $type . '_latitude';
        $longitudeField = $type . '_longitude';

        if (! empty($payload[$addressIdField])) {
            $address = Address::where('id', $payload[$addressIdField])
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

        return [
            'address_id' => null,
            'label' => $payload[$labelField] ?? null,
            'address' => $payload[$addressField] ?? null,
            'latitude' => $payload[$latitudeField] ?? null,
            'longitude' => $payload[$longitudeField] ?? null,
        ];
    }

    private function resolveOrderDropData(array $payload, int $companyId, Order $order): array
    {
        return [
            'address_id' => null,
            'label' => $order->delivery_contact_name,
            'address' => $order->delivery_address,
            'latitude' => $order->delivery_latitude,
            'longitude' => $order->delivery_longitude,
        ];
    }

    private function resolveProviderName(array $payload): ?string
    {
        if (($payload['delivery_method'] ?? null) !== DeliveryMethod::THIRD_PARTY->value) {
            return null;
        }

        return $payload['provider_name'] ?? null;
    }

    private function syncOrderLinkedDeliveryState(Delivery $delivery, string $status): void
    {
        if (! $delivery->order_id) {
            return;
        }

        $deliveryStatus = DeliveryStatus::from($status);
        $this->deliveryStatusTransitionService->syncOrderOnDeliveryStatusChange($delivery, $deliveryStatus);

        if ($deliveryStatus === DeliveryStatus::DELIVERED) {
            $this->deliveryStatusTransitionService->syncOrderOnDeliveryDelivered($delivery);
        }
    }

    private function createStandaloneDeliveryItems(array $items, Delivery $delivery, Company $company): void
    {
        foreach ($items as $itemData) {
            if (! empty($itemData['id'])) {
                $item = Item::where('id', $itemData['id'])
                    ->where('company_id', $company->id)
                    ->firstOrFail();
            } else {
                $item = Item::create([
                    'company_id' => $company->id,
                    'name' => $itemData['name'],
                    'code' => $itemData['code'] ?? null,
                    'unit' => $itemData['unit'] ?? null,
                    'unit_price' => $itemData['unit_price'] ?? 0,
                    'notes' => $itemData['item_notes'] ?? null,
                    'is_active' => true,
                ]);
            }

            $unitPrice = $itemData['unit_price'] ?? $item->unit_price ?? 0;
            $quantity = (int) $itemData['quantity'];

            DeliveryItem::create([
                'company_id' => $company->id,
                'delivery_id' => $delivery->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'unit' => $itemData['unit'] ?? $item->unit,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => round(((float) $unitPrice) * $quantity, 2),
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }

    private function copyOrderItemsToDelivery(Order $order, Delivery $delivery, int $companyId): void
    {
        foreach ($order->orderItems as $orderItem) {
            DeliveryItem::create([
                'company_id' => $companyId,
                'delivery_id' => $delivery->id,
                'item_id' => $orderItem->item_id,
                'item_name' => $orderItem->item_name,
                'unit' => $orderItem->unit,
                'unit_price' => $orderItem->unit_price,
                'quantity' => $orderItem->quantity,
                'line_total' => round(((float) $orderItem->unit_price) * (int) $orderItem->quantity, 2),
                'notes' => $orderItem->notes,
            ]);
        }
    }

    private function isSameMoney(float $left, float $right): bool
    {
        return abs($left - $right) < 0.00001;
    }
}
