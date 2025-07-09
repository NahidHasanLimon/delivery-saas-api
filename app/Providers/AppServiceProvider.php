<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         Relation::morphMap([
        'company'      => \App\Models\Company::class,
        'customer'     => \App\Models\Customer::class,
        'delivery_man' => \App\Models\DeliveryMan::class,
        // Add others as needed
    ]);
    }
}
