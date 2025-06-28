<?php
// File: database/factories/CompanyFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Company;

class CompanyFactory extends Factory
{

    protected $model = Company::class;

    public function definition()
    {
        $countryCode = "88";
        $prefixes = ['013', '014', '015', '016', '017', '018', '019'];
        $prefix = $this->faker->randomElement($prefixes);
        return [
            'name'      => $this->faker->company,
            'email'     => $this->faker->unique()->companyEmail,
            'mobile_no' => $this->faker
                ->unique()
                ->numerify("{$countryCode}{$prefix}########"),
            'address'   => $this->faker->address,
            'is_active' => true,
        ];
    }
}
