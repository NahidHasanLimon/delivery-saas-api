<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsCompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;

class CompanyCustomerController extends Controller
{
    use LogsCompanyActivity;
    // List customers (paginated, descending)
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $customers = Customer::where('company_id', $company->id)
            ->orderByDesc('id')
            ->paginate($request->get('per_page', 15));
        return $this->success($customers, 'Customers fetched.');
    }

    // Show customer details with orders (deliveries)
    public function show($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $customer = Customer::where('company_id', $company->id)
            ->with('deliveries')
            ->findOrFail($id);
        return $this->success($customer, 'Customer details fetched.');
    }

    // Create customer
    public function store(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile_no' => 'required|string|unique:customers,mobile_no,NULL,id,company_id,' . $company->id,
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:customers,email,NULL,id,company_id,' . $company->id
            ],
            'address' => 'nullable|string',
            'customer_code' => 'nullable|string|max:255',
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'mobile_no' => $request->mobile_no,
            'email' => $request->email,
            'address' => $request->address,
            'customer_code' => $request->customer_code,
        ]);
        
        // Log activity
        $this->logCustomerActivity('customer_created', $customer);
        
        return $this->success($customer, 'Customer created successfully.');
    }

    // Update customer
    public function update(Request $request, $id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $customer = Customer::where('company_id', $company->id)->findOrFail($id);
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'mobile_no' => 'sometimes|required|string|unique:customers,mobile_no,' . $customer->id . ',id,company_id,' . $company->id,
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:customers,email,' . $customer->id . ',id,company_id,' . $company->id
            ],
            'address' => 'nullable|string',
            'customer_code' => 'nullable|string|max:255',
        ]);
        $customer->update($request->only(['name', 'mobile_no', 'email', 'address', 'customer_code']));
        
        // Log activity
        $this->logCustomerActivity('customer_updated', $customer);
        
        return $this->success($customer, 'Customer updated successfully.');
    }

    // Delete customer
    public function destroy($id)
    {
        $company = Auth::guard('company_user')->user()->company;
        $customer = Customer::where('company_id', $company->id)->findOrFail($id);
        
        // Log activity before deletion
        $this->logCustomerActivity('customer_deleted', $customer);
        
        $customer->delete();
        return $this->success(null, 'Customer deleted successfully.');
    }
}
