<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentMethod;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_defaults_status_to_created_when_omitted(): void
    {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($companyUser, 'company_user')
            ->postJson('/api/company/orders', [
                'customer_name' => 'Customer One',
                'customer_mobile_no' => '01700000000',
                'is_delivery_order' => false,
                'payment_method' => OrderPaymentMethod::CASH->value,
                'payment_status' => OrderPaymentStatus::PAID->value,
                'paid_amount' => 1000,
                'collectible_amount' => 0,
                'items' => [
                    [
                        'name' => 'Item One',
                        'unit' => 'pcs',
                        'unit_price' => 1000,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::CREATED->value);

        $this->assertDatabaseHas('orders', [
            'company_id' => $company->id,
            'status' => OrderStatus::CREATED->value,
        ]);
    }
}
