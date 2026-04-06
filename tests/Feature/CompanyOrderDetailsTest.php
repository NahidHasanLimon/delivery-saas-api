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

class CompanyOrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_details_includes_all_linked_deliveries(): void
    {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-' . now()->format('YmdHis') . random_int(100, 999),
            'customer_id' => $customer->id,
            'is_delivery_order' => true,
            'status' => OrderStatus::CONFIRMED->value,
            'delivery_status' => OrderDeliveryStatus::PENDING->value,
            'delivery_contact_name' => 'Receiver',
            'delivery_mobile_number' => '01700000000',
            'delivery_address' => 'Dhaka',
            'delivery_area' => 'Gulshan',
            'subtotal_amount' => 120,
            'delivery_fee' => 0,
            'adjustment_amount' => 0,
            'total_amount' => 120,
            'payment_method' => OrderPaymentMethod::COD->value,
            'payment_status' => OrderPaymentStatus::UNPAID->value,
            'paid_amount' => 0,
            'due_amount' => 120,
            'created_by' => $companyUser->id,
            'updated_by' => $companyUser->id,
        ]);

        Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'delivery_source' => 'order',
            'customer_id' => $customer->id,
            'pickup_label' => 'Main Warehouse',
            'pickup_address' => 'Warehouse Address',
            'drop_label' => 'Receiver',
            'drop_address' => 'Dhaka',
            'delivery_method' => 'own',
            'status' => DeliveryStatus::CREATED->value,
            'collectible_amount' => 120,
            'collected_amount' => 0,
        ]);

        Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'delivery_source' => 'order',
            'customer_id' => $customer->id,
            'pickup_label' => 'Main Warehouse',
            'pickup_address' => 'Warehouse Address',
            'drop_label' => 'Receiver',
            'drop_address' => 'Dhaka',
            'delivery_method' => 'own',
            'status' => DeliveryStatus::ASSIGNED->value,
            'collectible_amount' => 120,
            'collected_amount' => 0,
        ]);

        $response = $this->actingAs($companyUser, 'company_user')
            ->getJson('/api/company/orders/' . $order->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonCount(2, 'data.deliveries')
            ->assertJsonPath('data.deliveries.0.order_id', $order->id)
            ->assertJsonPath('data.deliveries.1.order_id', $order->id);
    }
}
