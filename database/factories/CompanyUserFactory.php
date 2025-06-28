<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CompanyUser;
use App\Models\Company;

class CompanyUserFactory extends Factory
{
    protected $model = CompanyUser::class;

    public function definition(): array
    {
        $countryCode = "88";
        $prefixes = ['013', '014', '015', '016', '017', '018', '019'];
        $prefix = $this->faker->randomElement($prefixes);
        $companyIds = Company::pluck('id')->toArray();
        return [
            'company_id' => $companyIds ? $this->faker->randomElement($companyIds) : Company::factory(),
            'name'       => $this->faker->name(),
            'email'      => $this->faker->unique()->safeEmail(),
            'mobile_no' => $this->faker
                ->unique()
                ->numerify("{$countryCode}{$prefix}########"),
            'password'   => bcrypt('password'),
            'role'       => 'admin',
        ];
    }
}
