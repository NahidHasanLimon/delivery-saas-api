<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_store_persists_unit_price(): void
    {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($companyUser, 'company_user')
            ->postJson('/api/company/items', [
                'name' => 'Item A',
                'code' => 'ITEM-A',
                'unit' => 'pcs',
                'unit_price' => 125.75,
                'is_active' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Item A')
            ->assertJsonPath('data.unit_price', '125.75');

        $this->assertDatabaseHas('items', [
            'company_id' => $company->id,
            'name' => 'Item A',
            'unit_price' => 125.75,
        ]);
    }

    public function test_item_update_persists_unit_price(): void
    {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);
        $item = Item::create([
            'company_id' => $company->id,
            'name' => 'Item B',
            'code' => 'ITEM-B',
            'unit' => 'pcs',
            'unit_price' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($companyUser, 'company_user')
            ->putJson('/api/company/items/' . $item->id, [
                'name' => 'Item B',
                'code' => 'ITEM-B',
                'unit' => 'pcs',
                'unit_price' => 55.25,
                'is_active' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.unit_price', '55.25');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'unit_price' => 55.25,
        ]);
    }
}

