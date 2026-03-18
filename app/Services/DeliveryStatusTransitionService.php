<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderPaymentMethod;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\CompanyActivityLog;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeliveryStatusTransitionService
{
    public function update(Delivery $delivery, array $payload, ?Model $actor = null): array
    {
        return DB::transaction(function () use ($delivery, $payload, $actor) {
            $delivery->loadMissing(['customer', 'rider', 'order', 'deliveryItems']);

            $originalRiderId = $delivery->rider_id;

            if (array_key_exists('rider_id', $payload)) {
                $delivery->rider_id = $payload['rider_id'];
                $delivery->assigned_at = $payload['rider_id'] ? now() : null;
                $delivery->save();

                if ($originalRiderId !== $delivery->rider_id) {
                    $delivery->loadMissing(['customer', 'rider']);
                    $this->logCompanyActivity(
                        action: 'delivery_assigned',
                        delivery: $delivery,
                        actor: $actor,
                        description: 'Delivery ' . ($delivery->tracking_number ?? 'Unknown') . ' assigned to ' . ($delivery->rider?->name ?? 'unassigned'),
                        properties: [
                            'previous_rider_id' => $originalRiderId,
                            'rider_id' => $delivery->rider_id,
                        ],
                    );
                }
            }

            if (array_key_exists('collected_amount', $payload)) {
                $delivery->collected_amount = round((float) $payload['collected_amount'], 2);
            }

            if (array_key_exists('status', $payload)) {
                $currentStatus = DeliveryStatus::from($delivery->status);
                $newStatus = DeliveryStatus::from($payload['status']);

                if (! $currentStatus->canTransitionTo($newStatus)) {
                    throw ValidationException::withMessages([
                        'status' => 'Invalid status transition from ' . $currentStatus->value . ' to ' . $newStatus->value . '.',
                    ]);
                }

                if ($newStatus === DeliveryStatus::DELIVERED) {
                    $this->markDelivered($delivery, $payload, $actor);
                } else {
                    $this->applyStatusTransition($delivery, $newStatus, $actor);
                }
            } else {
                $delivery->save();
            }

            $delivery->load(['customer', 'rider', 'order', 'deliveryItems']);
            $order = $delivery->order?->fresh();

            return [
                'delivery' => $delivery,
                'order' => $order,
            ];
        });
    }

    public function markDelivered(Delivery $delivery, array $payload, ?Model $actor = null): void
    {
        $order = $delivery->order;
        $isCodOrder = $order && $order->payment_method === OrderPaymentMethod::COD->value;

        if ($isCodOrder) {
            if (! array_key_exists('collected_amount', $payload)) {
                throw ValidationException::withMessages([
                    'collected_amount' => 'collected_amount is required when completing a COD delivery.',
                ]);
            }

            $collectedAmount = round((float) $payload['collected_amount'], 2);
            $collectibleAmount = round((float) $delivery->collectible_amount, 2);

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

            $delivery->collected_amount = $collectedAmount;
        }

        $delivery->status = DeliveryStatus::DELIVERED->value;
        $delivery->delivered_at = now();
        $delivery->save();

        $this->syncOrderOnDeliveryStatusChange($delivery, DeliveryStatus::DELIVERED);
        $this->syncOrderOnDeliveryDelivered($delivery);
        $this->logStatusChange($delivery, DeliveryStatus::DELIVERED, $actor);
        $this->logDeliveryCompletedActivity($delivery, $actor);
    }

    private function applyStatusTransition(Delivery $delivery, DeliveryStatus $newStatus, ?Model $actor = null): void
    {
        $delivery->status = $newStatus->value;

        if ($newStatus === DeliveryStatus::IN_PROGRESS) {
            $delivery->in_progress_at = now();
            $delivery->picked_at = $delivery->picked_at ?? now();
        }

        if ($newStatus === DeliveryStatus::CANCELLED) {
            $delivery->cancelled_at = now();
        }

        $delivery->save();
        $this->syncOrderOnDeliveryStatusChange($delivery, $newStatus);
        $this->logStatusChange($delivery, $newStatus, $actor);
    }

    public function syncOrderOnDeliveryStatusChange(Delivery $delivery, DeliveryStatus $status): void
    {
        $order = $delivery->order;

        if (! $order || ! $delivery->order_id) {
            return;
        }

        $order->delivery_status = $this->mapDeliveryStatusToOrderDeliveryStatus($status)->value;
        $order->save();
    }

    public function syncOrderOnDeliveryDelivered(Delivery $delivery): void
    {
        $order = $delivery->order;

        if (! $order || ! $delivery->order_id) {
            return;
        }

        if ($order->payment_method === OrderPaymentMethod::COD->value) {
            $order->paid_amount = $order->total_amount;
            $order->collectible_amount = 0;
            $order->payment_status = OrderPaymentStatus::PAID->value;
            $order->status = OrderStatus::COMPLETED->value;
        } else {
            $order->status = OrderStatus::COMPLETED->value;
        }

        $order->save();
    }

    private function logStatusChange(Delivery $delivery, DeliveryStatus $status, ?Model $actor = null): void
    {
        if (! Schema::hasTable('delivery_status_logs')) {
        } else {
            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status' => $status->value,
                'changed_by_id' => $actor?->getKey(),
                'changed_by_type' => $actor ? class_basename($actor) : null,
                'remarks' => null,
                'changed_at' => now(),
            ]);
        }

        $this->logCompanyActivity(
            action: 'delivery_status_changed',
            delivery: $delivery,
            actor: $actor,
            description: 'Delivery ' . ($delivery->tracking_number ?? 'Unknown') . ' status changed to ' . $status->value,
            properties: [
                'status' => $status->value,
            ],
        );
    }

    private function isSameMoney(float $left, float $right): bool
    {
        return abs($left - $right) < 0.00001;
    }

    private function mapDeliveryStatusToOrderDeliveryStatus(DeliveryStatus $status): OrderDeliveryStatus
    {
        return match ($status) {
            DeliveryStatus::PENDING => OrderDeliveryStatus::PENDING,
            DeliveryStatus::ASSIGNED => OrderDeliveryStatus::ASSIGNED,
            DeliveryStatus::IN_PROGRESS => OrderDeliveryStatus::IN_PROGRESS,
            DeliveryStatus::DELIVERED => OrderDeliveryStatus::DELIVERED,
            DeliveryStatus::RETURNED => OrderDeliveryStatus::RETURNED,
            DeliveryStatus::CANCELLED => OrderDeliveryStatus::FAILED,
        };
    }

    private function logDeliveryCompletedActivity(Delivery $delivery, ?Model $actor = null): void
    {
        $delivery->loadMissing(['customer', 'order']);

        $this->logCompanyActivity(
            action: 'delivery_completed',
            delivery: $delivery,
            actor: $actor,
            description: 'Delivery ' . ($delivery->tracking_number ?? 'Unknown') . ' completed for ' . ($delivery->customer?->name ?? 'Unknown Customer'),
            properties: [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'collected_amount' => $delivery->collected_amount,
                'collectible_amount' => $delivery->collectible_amount,
            ],
        );

        if (! $delivery->order) {
            return;
        }

        $this->logCompanyActivity(
            action: 'order_delivery_completed',
            delivery: $delivery,
            actor: $actor,
            description: 'Order ' . $delivery->order->order_number . ' marked delivered from delivery ' . ($delivery->tracking_number ?? 'Unknown'),
            subject: $delivery->order,
            properties: [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order->id,
                'delivery_status' => $delivery->order->delivery_status,
                'order_status' => $delivery->order->status,
            ],
        );

        if ($delivery->order->payment_method === OrderPaymentMethod::COD->value) {
            $this->logCompanyActivity(
                action: 'order_payment_collected',
                delivery: $delivery,
                actor: $actor,
                description: 'COD payment collected for order ' . $delivery->order->order_number,
                subject: $delivery->order,
                properties: [
                    'delivery_id' => $delivery->id,
                    'order_id' => $delivery->order->id,
                    'paid_amount' => $delivery->order->paid_amount,
                    'payment_status' => $delivery->order->payment_status,
                ],
            );
        }
    }

    private function logCompanyActivity(
        string $action,
        Delivery $delivery,
        ?Model $actor = null,
        string $description = '',
        array $properties = [],
        ?Model $subject = null,
    ): void {
        if (! Schema::hasTable('company_activity_logs')) {
            return;
        }

        $subject ??= $delivery;

        CompanyActivityLog::log(
            $delivery->company_id,
            $action,
            $description,
            [
                'user_id' => $actor?->getKey(),
                'subject_type' => get_class($subject),
                'subject_id' => $subject->getKey(),
                'properties' => $properties,
            ]
        );
    }
}
