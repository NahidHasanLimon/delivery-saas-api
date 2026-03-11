<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\DeliverySource;
use App\Enums\DeliveryStatus;
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
    public function createStandalone(Company $company, array $payload): Delivery
    {
        $effectiveDeliveryManId = $payload['delivery_man_id'] ?? null;
        $status = $this->resolveStatus($payload['status'] ?? null, $effectiveDeliveryManId);

        return DB::transaction(function () use ($company, $payload, $effectiveDeliveryManId, $status) {
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
                effectiveDeliveryManId: $effectiveDeliveryManId,
                order: null,
            );

            $this->createStandaloneDeliveryItems($payload['items'] ?? [], $delivery, $company);

            return $delivery->load(['customer', 'deliveryMan', 'order', 'deliveryItems']);
        });
    }

    public function createFromOrder(Company $company, Order $order, array $payload): Delivery
    {
        $order->loadMissing('orderItems');

        if (! $order->needs_delivery) {
            throw ValidationException::withMessages([
                'order_id' => 'Selected order does not require delivery.',
            ]);
        }

        if (! $order->delivery_address) {
            throw ValidationException::withMessages([
                'order_id' => 'Selected order does not have a delivery address.',
            ]);
        }

        $effectiveDeliveryManId = $payload['delivery_man_id'] ?? null;
        $status = $this->resolveStatus($payload['status'] ?? null, $effectiveDeliveryManId);

        return DB::transaction(function () use ($company, $order, $payload, $effectiveDeliveryManId, $status) {
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
                effectiveDeliveryManId: $effectiveDeliveryManId,
                order: $order,
            );

            $this->copyOrderItemsToDelivery($order, $delivery, $company->id);

            return $delivery->load(['customer', 'deliveryMan', 'order', 'deliveryItems']);
        });
    }

    private function resolveStatus(?string $requestedStatus, ?int $effectiveDeliveryManId): string
    {
        $status = $requestedStatus ?? ($effectiveDeliveryManId ? DeliveryStatus::ASSIGNED->value : DeliveryStatus::PENDING->value);

        if ($status === DeliveryStatus::ASSIGNED->value && ! $effectiveDeliveryManId) {
            throw ValidationException::withMessages([
                'delivery_man_id' => 'delivery_man_id is required when status is assigned.',
            ]);
        }

        return $status;
    }

    private function createDeliveryRecord(
        Company $company,
        array $payload,
        string $source,
        int $customerId,
        array $pickupData,
        array $dropData,
        string $status,
        ?int $effectiveDeliveryManId,
        ?Order $order,
    ): Delivery {
        return Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order?->id,
            'delivery_source' => $source,
            'delivery_man_id' => $effectiveDeliveryManId,
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
        if (! empty($payload['drop_address_id']) || ! empty($payload['drop_address'])) {
            return $this->resolveAddress($payload, 'drop', $companyId);
        }

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
}
