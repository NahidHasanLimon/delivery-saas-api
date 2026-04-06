<?php

namespace Tests\Feature;

use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderPaymentMethod;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCustomerDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_details_returns_total_orders_last_three_orders_and_saved_addresses(): void
    {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        for ($i = 1; $i <= 4; $i++) {
            Order::create([
                'company_id' => $company->id,
                'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . $i . random_int(100, 999),
                'customer_id' => $customer->id,
                'is_delivery_order' => true,
                'order_source' => 'online_store',
                'status' => OrderStatus::CREATED->value,
                'delivery_status' => OrderDeliveryStatus::PENDING->value,
                'delivery_contact_name' => 'Receiver',
                'delivery_mobile_number' => '01700000000',
                'delivery_address' => 'Dhaka',
                'delivery_area' => 'Gulshan',
                'subtotal_amount' => 100,
                'delivery_fee' => 0,
                'adjustment_amount' => 0,
                'total_amount' => 100,
                'payment_method' => OrderPaymentMethod::CASH->value,
                'payment_status' => OrderPaymentStatus::UNPAID->value,
                'paid_amount' => 0,
                'due_amount' => 100,
                'created_by' => $companyUser->id,
                'updated_by' => $companyUser->id,
            ]);
        }

        $customer->addresses()->create([
            'company_id' => $company->id,
            'address_type' => 'home',
            'label' => 'Home',
            'address' => 'Banani, Dhaka',
            'latitude' => null,
            'longitude' => null,
        ]);

        $customer->addresses()->create([
            'company_id' => $company->id,
            'address_type' => 'office',
            'label' => 'Office',
            'address' => 'Gulshan, Dhaka',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->actingAs($companyUser, 'company_user')
            ->getJson('/api/company/customers/' . $customer->id);

        $response->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.total_orders', 4)
            ->assertJsonCount(3, 'data.last_orders')
            ->assertJsonCount(2, 'data.saved_addresses');
    }
}
