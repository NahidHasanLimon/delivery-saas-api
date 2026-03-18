<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderPaymentMethod;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDeliveryStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_cod_delivery_delivered_with_full_collection_marks_order_paid_and_completed(): void
    {
        [$companyUser, $delivery, $order] = $this->createOrderLinkedDelivery(OrderPaymentMethod::COD->value);

        $response = $this->actingAs($companyUser, 'company_user')
            ->patchJson('/api/company/deliveries/' . $delivery->id, [
                'status' => DeliveryStatus::DELIVERED->value,
                'collected_amount' => 1060,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery.status', DeliveryStatus::DELIVERED->value)
            ->assertJsonPath('data.delivery.collected_amount', 1060)
            ->assertJsonPath('data.order.delivery_status', OrderDeliveryStatus::DELIVERED->value)
            ->assertJsonPath('data.order.payment_status', OrderPaymentStatus::PAID->value)
            ->assertJsonPath('data.order.status', OrderStatus::COMPLETED->value)
            ->assertJsonPath('data.order.collectible_amount', '0.00');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => OrderDeliveryStatus::DELIVERED->value,
            'payment_status' => OrderPaymentStatus::PAID->value,
            'status' => OrderStatus::COMPLETED->value,
        ]);
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => DeliveryStatus::DELIVERED->value,
        ]);
        $this->assertDatabaseHas('company_activity_logs', [
            'company_id' => $companyUser->company_id,
            'action' => 'delivery_completed',
            'subject_type' => \App\Models\Delivery::class,
            'subject_id' => $delivery->id,
        ]);
        $this->assertDatabaseHas('company_activity_logs', [
            'company_id' => $companyUser->company_id,
            'action' => 'order_payment_collected',
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_cod_delivery_delivered_with_wrong_collected_amount_fails(): void
    {
        [$companyUser, $delivery] = $this->createOrderLinkedDelivery(OrderPaymentMethod::COD->value);

        $response = $this->actingAs($companyUser, 'company_user')
            ->patchJson('/api/company/deliveries/' . $delivery->id, [
                'status' => DeliveryStatus::DELIVERED->value,
                'collected_amount' => 1000,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.collected_amount.0', 'For COD delivery completion, collected_amount must equal collectible_amount.');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => DeliveryStatus::PENDING->value,
        ]);
    }

    public function test_non_cod_delivery_delivered_marks_order_completed_without_overwriting_payment_fields(): void
    {
        [$companyUser, $delivery, $order] = $this->createOrderLinkedDelivery(
            OrderPaymentMethod::ONLINE->value,
            [
                'payment_status' => OrderPaymentStatus::PAID->value,
                'paid_amount' => 1060,
                'collectible_amount' => 0,
            ],
            [
                'collectible_amount' => 0,
                'collected_amount' => 0,
            ]
        );

        $response = $this->actingAs($companyUser, 'company_user')
            ->patchJson('/api/company/deliveries/' . $delivery->id, [
                'status' => DeliveryStatus::DELIVERED->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.order.delivery_status', OrderDeliveryStatus::DELIVERED->value)
            ->assertJsonPath('data.order.status', OrderStatus::COMPLETED->value)
            ->assertJsonPath('data.order.payment_status', OrderPaymentStatus::PAID->value)
            ->assertJsonPath('data.order.paid_amount', '1060.00')
            ->assertJsonPath('data.order.collectible_amount', '0.00');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => OrderDeliveryStatus::DELIVERED->value,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => OrderPaymentStatus::PAID->value,
        ]);
        $this->assertDatabaseHas('company_activity_logs', [
            'company_id' => $companyUser->company_id,
            'action' => 'order_delivery_completed',
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_order_linked_delivery_in_progress_syncs_order_delivery_status(): void
    {
        [$companyUser, $delivery, $order] = $this->createOrderLinkedDelivery(OrderPaymentMethod::CASH->value);

        $response = $this->actingAs($companyUser, 'company_user')
            ->patchJson('/api/company/deliveries/' . $delivery->id, [
                'status' => DeliveryStatus::IN_PROGRESS->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery.status', DeliveryStatus::IN_PROGRESS->value)
            ->assertJsonPath('data.order.delivery_status', OrderDeliveryStatus::IN_PROGRESS->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => OrderDeliveryStatus::IN_PROGRESS->value,
        ]);
    }

    public function test_order_linked_delivery_cancelled_syncs_order_delivery_status_to_failed(): void
    {
        [$companyUser, $delivery, $order] = $this->createOrderLinkedDelivery(OrderPaymentMethod::CASH->value);

        $response = $this->actingAs($companyUser, 'company_user')
            ->patchJson('/api/company/deliveries/' . $delivery->id, [
                'status' => DeliveryStatus::CANCELLED->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery.status', DeliveryStatus::CANCELLED->value)
            ->assertJsonPath('data.order.delivery_status', OrderDeliveryStatus::FAILED->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => OrderDeliveryStatus::FAILED->value,
        ]);
    }

    public function test_delivered_order_linked_delivery_returned_syncs_order_delivery_status(): void
    {
        [$companyUser, $delivery, $order] = $this->createOrderLinkedDelivery(
            OrderPaymentMethod::ONLINE->value,
            [
                'delivery_status' => OrderDeliveryStatus::DELIVERED->value,
                'status' => OrderStatus::COMPLETED->value,
            ],
            [
                'status' => DeliveryStatus::DELIVERED->value,
                'delivered_at' => now(),
                'collectible_amount' => 0,
                'collected_amount' => 0,
            ]
        );

        $response = $this->actingAs($companyUser, 'company_user')
            ->patchJson('/api/company/deliveries/' . $delivery->id, [
                'status' => DeliveryStatus::RETURNED->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery.status', DeliveryStatus::RETURNED->value)
            ->assertJsonPath('data.order.delivery_status', OrderDeliveryStatus::RETURNED->value);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => DeliveryStatus::RETURNED->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => OrderDeliveryStatus::RETURNED->value,
        ]);
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => DeliveryStatus::RETURNED->value,
        ]);
    }

    private function createOrderLinkedDelivery(
        string $paymentMethod,
        array $orderOverrides = [],
        array $deliveryOverrides = []
    ): array {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $order = Order::create(array_merge([
            'company_id' => $company->id,
            'order_number' => 'ORD-' . now()->format('YmdHis') . random_int(100, 999),
            'customer_id' => $customer->id,
            'is_delivery_order' => true,
            'order_source' => null,
            'status' => OrderStatus::CONFIRMED->value,
            'delivery_status' => OrderDeliveryStatus::PENDING->value,
            'delivery_contact_name' => 'Receiver',
            'delivery_mobile_number' => '01700000000',
            'delivery_address' => 'Dhaka',
            'delivery_area' => 'Mirpur',
            'delivery_latitude' => null,
            'delivery_longitude' => null,
            'subtotal_amount' => 1000,
            'delivery_fee' => 60,
            'adjustment_amount' => 0,
            'total_amount' => 1060,
            'payment_method' => $paymentMethod,
            'payment_status' => OrderPaymentStatus::UNPAID->value,
            'paid_amount' => 0,
            'collectible_amount' => 1060,
            'note' => null,
            'internal_note' => null,
            'created_by' => $companyUser->id,
            'updated_by' => $companyUser->id,
        ], $orderOverrides));

        $delivery = Delivery::create(array_merge([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'delivery_source' => 'order',
            'rider_id' => null,
            'customer_id' => $customer->id,
            'tracking_number' => 'DLTEST' . random_int(1000, 9999),
            'pickup_address_id' => null,
            'pickup_label' => 'Warehouse',
            'pickup_address' => 'Warehouse Address',
            'pickup_latitude' => null,
            'pickup_longitude' => null,
            'drop_address_id' => null,
            'drop_label' => 'Receiver',
            'drop_address' => 'Dhaka',
            'drop_latitude' => null,
            'drop_longitude' => null,
            'delivery_notes' => null,
            'expected_delivery_time' => now()->addDay(),
            'delivery_method' => 'own',
            'provider_name' => null,
            'status' => DeliveryStatus::PENDING->value,
            'collectible_amount' => 1060,
            'collected_amount' => 0,
        ], $deliveryOverrides));

        return [$companyUser, $delivery, $order];
    }
}
