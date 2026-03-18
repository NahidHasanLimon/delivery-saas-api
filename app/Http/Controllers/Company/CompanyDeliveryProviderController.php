<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProvider;
use Illuminate\Http\Request;

class CompanyDeliveryProviderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        $query = DeliveryProvider::query()
            ->select(['id', 'name', 'code', 'is_active', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        } else {
            $query->where('is_active', true);
        }

        $providers = $query->get();

        return $this->success([
            'providers' => $providers,
        ], 'Delivery providers fetched successfully.');
    }
}
