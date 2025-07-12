<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Item;

class CompanyItemController extends Controller
{
    use LogsCompanyActivity;

    /**
     * Get all items for the authenticated company
     */
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $query = $company->items();
        
        // Filter by active status if requested
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        
        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        $items = $query->orderBy('name')->paginate(15);
        
        return $this->success($items, 'Items fetched successfully.');
    }

    /**
     * Store a new item
     */
    public function store(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $request->validate([
            'name' => 'required|string|max:255|unique:items,name,NULL,id,company_id,' . $company->id,
            'code' => 'nullable|string|max:255|unique:items,code,NULL,id,company_id,' . $company->id,
            'unit' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $item = Item::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'code' => $request->code,
            'unit' => $request->unit,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true),
        ]);

        // Log activity
        $this->logActivity(
            'item_created',
            "Item '{$item->name}' created",
            $item
        );

        return $this->success($item, 'Item created successfully.');
    }

    /**
     * Show a specific item
     */
    public function show($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $item = $company->items()->findOrFail($id);
        
        return $this->success($item, 'Item fetched successfully.');
    }

    /**
     * Update an item
     */
    public function update(Request $request, $id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $item = $company->items()->findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255|unique:items,name,' . $item->id . ',id,company_id,' . $company->id,
            'code' => 'nullable|string|max:255|unique:items,code,' . $item->id . ',id,company_id,' . $company->id,
            'unit' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $item->update([
            'name' => $request->name,
            'code' => $request->code,
            'unit' => $request->unit,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', $item->is_active),
        ]);

        // Log activity
        $this->logActivity(
            'item_updated',
            "Item '{$item->name}' updated",
            $item
        );

        return $this->success($item, 'Item updated successfully.');
    }

    /**
     * Delete an item
     */
    public function destroy($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $item = $company->items()->findOrFail($id);
        
        // Check if item is used in any deliveries
        if ($item->deliveryItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete item that is used in deliveries. You can deactivate it instead.',
            ], 422);
        }
        
        $itemName = $item->name;
        $item->delete();

        // Log activity
        $this->logActivity(
            'item_deleted',
            "Item '{$itemName}' deleted",
            null
        );

        return $this->success(null, 'Item deleted successfully.');
    }
}
