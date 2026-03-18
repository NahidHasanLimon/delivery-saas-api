<?php

namespace Tests\Feature;

use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderPaymentMethod;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyOrderDeliveryCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_delivered_cod_order_delivery_syncs_the_order(): void
    {
        [$companyUser, $order, $pickupAddress] = $this->createOrderWithItems(OrderPaymentMethod::COD->value);

        $response = $this->actingAs($companyUser, 'company_user')
            ->postJson('/api/company/orders/' . $order->id . '/delivery', [
                'pickup_address_id' => $pickupAddress->id,
                'delivery_method' => 'own',
                'status' => 'delivered',
                'collectible_amount' => 60,
                'collected_amount' => 60,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.order.delivery_status', OrderDeliveryStatus::DELIVERED->value)
            ->assertJsonPath('data.order.status', OrderStatus::COMPLETED->value)
            ->assertJsonPath('data.order.payment_status', OrderPaymentStatus::PAID->value)
            ->assertJsonPath('data.order.collectible_amount', '0.00');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => OrderDeliveryStatus::DELIVERED->value,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => OrderPaymentStatus::PAID->value,
        ]);
    }

    public function test_cannot_create_delivery_for_completed_order(): void
    {
        [$companyUser, $order, $pickupAddress] = $this->createOrderWithItems(
            OrderPaymentMethod::COD->value,
            [
                'status' => OrderStatus::COMPLETED->value,
                'delivery_status' => OrderDeliveryStatus::DELIVERED->value,
                'payment_status' => OrderPaymentStatus::PAID->value,
                'paid_amount' => 60,
                'collectible_amount' => 0,
            ]
        );

        $response = $this->actingAs($companyUser, 'company_user')
            ->postJson('/api/company/orders/' . $order->id . '/delivery', [
                'pickup_address_id' => $pickupAddress->id,
                'delivery_method' => 'own',
                'collectible_amount' => 60,
                'collected_amount' => 60,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.order_id.0', 'Delivery cannot be created for a completed order.');
    }

    private function createOrderWithItems(string $paymentMethod, array $orderOverrides = []): array
    {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $pickupAddress = Address::create([
            'company_id' => $company->id,
            'addressable_id' => $company->id,
            'addressable_type' => Company::class,
            'address_type' => 'warehouse',
            'label' => 'Main Warehouse',
            'address' => 'Dhaka Warehouse',
            'latitude' => null,
            'longitude' => null,
        ]);
        $item = Item::create([
            'company_id' => $company->id,
            'name' => 'Test Item',
            'code' => 'ITEM-' . random_int(1000, 9999),
            'unit' => 'pcs',
            'unit_price' => 12,
            'is_active' => true,
        ]);

        $order = Order::create(array_merge([
            'company_id' => $company->id,
            'order_number' => 'ORD-' . now()->format('YmdHis') . random_int(100, 999),
            'customer_id' => $customer->id,
            'is_delivery_order' => true,
            'order_source' => 'online_store',
            'status' => OrderStatus::CREATED->value,
            'delivery_status' => OrderDeliveryStatus::PENDING->value,
            'delivery_contact_name' => 'Receiver',
            'delivery_mobile_number' => '01700000000',
            'delivery_address' => 'Dhaka',
            'delivery_area' => 'Gulshan',
            'subtotal_amount' => 60,
            'delivery_fee' => 0,
            'adjustment_amount' => 0,
            'total_amount' => 60,
            'payment_method' => $paymentMethod,
            'payment_status' => OrderPaymentStatus::UNPAID->value,
            'paid_amount' => 0,
            'collectible_amount' => 60,
            'created_by' => $companyUser->id,
            'updated_by' => $companyUser->id,
        ], $orderOverrides));

        OrderItem::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'unit' => 'pcs',
            'unit_price' => 12,
            'quantity' => 5,
            'notes' => null,
        ]);

        return [$companyUser, $order, $pickupAddress];
    }
}
