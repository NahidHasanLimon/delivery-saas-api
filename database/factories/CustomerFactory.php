<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;
use App\Models\Company;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $countryCode = "88";
        $prefixes = ['013', '014', '015', '016', '017', '018', '019'];
        $prefix = $this->faker->randomElement($prefixes);
        return [
            'company_id' => Company::factory(),
            'name'       => $this->faker->name(),
            'mobile_no' => $this->faker
                ->unique()
                ->numerify("{$countryCode}{$prefix}########"),
            'email'      => $this->faker->unique()->safeEmail(),
            'address'    => $this->faker->address(),
        ];
    }
}
