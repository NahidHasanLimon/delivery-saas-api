<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyActivityLog;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyActivityListTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_activities_endpoint_returns_paginated_company_scoped_results(): void
    {
        $companyA = Company::factory()->create();
        $companyAUser = CompanyUser::factory()->create(['company_id' => $companyA->id]);
        $companyB = Company::factory()->create();
        $companyBUser = CompanyUser::factory()->create(['company_id' => $companyB->id]);

        CompanyActivityLog::create([
            'company_id' => $companyA->id,
            'user_id' => $companyAUser->id,
            'action' => 'delivery_created',
            'description' => 'Delivery created',
            'subject_type' => null,
            'subject_id' => null,
            'properties' => ['tracking_number' => 'DL-A-1'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        CompanyActivityLog::create([
            'company_id' => $companyA->id,
            'user_id' => $companyAUser->id,
            'action' => 'delivery_assigned',
            'description' => 'Delivery assigned',
            'subject_type' => null,
            'subject_id' => null,
            'properties' => ['tracking_number' => 'DL-A-2'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        CompanyActivityLog::create([
            'company_id' => $companyB->id,
            'user_id' => $companyBUser->id,
            'action' => 'delivery_created',
            'description' => 'Other company delivery',
            'subject_type' => null,
            'subject_id' => null,
            'properties' => ['tracking_number' => 'DL-B-1'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $response = $this->actingAs($companyAUser, 'company_user')
            ->getJson('/api/company/activities?per_page=1&page=1&sort_by=id&sort_order=asc');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.activities')
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.activities.0.company_id', $companyA->id)
            ->assertJsonPath('data.activities.0.action', 'delivery_created')
            ->assertJsonPath('data.activities.0.entity.type', 'none')
            ->assertJsonPath('data.activities.0.entity.label', 'N/A');
    }

    public function test_company_activity_details_endpoint_returns_single_company_scoped_activity(): void
    {
        $company = Company::factory()->create();
        $companyUser = CompanyUser::factory()->create(['company_id' => $company->id]);

        $activity = CompanyActivityLog::create([
            'company_id' => $company->id,
            'user_id' => $companyUser->id,
            'action' => 'delivery_created',
            'description' => 'Delivery created',
            'subject_type' => null,
            'subject_id' => null,
            'properties' => ['tracking_number' => 'DL-1'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $response = $this->actingAs($companyUser, 'company_user')
            ->getJson('/api/company/activities/' . $activity->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $activity->id)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.action', 'delivery_created')
            ->assertJsonPath('data.entity.type', 'none')
            ->assertJsonPath('data.entity.label', 'N/A');
    }

    public function test_company_activity_details_endpoint_returns_404_for_other_company_activity(): void
    {
        $companyA = Company::factory()->create();
        $companyAUser = CompanyUser::factory()->create(['company_id' => $companyA->id]);
        $companyB = Company::factory()->create();
        $companyBUser = CompanyUser::factory()->create(['company_id' => $companyB->id]);

        $otherCompanyActivity = CompanyActivityLog::create([
            'company_id' => $companyB->id,
            'user_id' => $companyBUser->id,
            'action' => 'delivery_created',
            'description' => 'Other company delivery',
            'subject_type' => null,
            'subject_id' => null,
            'properties' => ['tracking_number' => 'DL-B-1'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $response = $this->actingAs($companyAUser, 'company_user')
            ->getJson('/api/company/activities/' . $otherCompanyActivity->id);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Activity not found.');
    }
}
