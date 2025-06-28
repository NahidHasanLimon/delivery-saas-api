<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DeliveryMan;

class DeliveryManFactory extends Factory
{
    protected $model = DeliveryMan::class;

    public function definition(): array
    {
        $countryCode = "88";
        $prefixes = ['013', '014', '015', '016', '017', '018', '019'];
        $prefix = $this->faker->randomElement($prefixes);
        return [
            'name'      => $this->faker->name(),
            'mobile_no' => $this->faker
                ->unique()
                ->numerify("{$countryCode}{$prefix}########"),
            'password'  => bcrypt('password'),
        ];
    }
}
