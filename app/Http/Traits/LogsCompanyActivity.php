<?php

namespace App\Http\Traits;

use App\Models\CompanyActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsCompanyActivity
{
    /**
     * Log activity for the authenticated company.
     */
    protected function logActivity($action, $description, $subject = null, $properties = null)
    {
        $companyUser = Auth::guard('company_user')->user();
        
        if (!$companyUser) {
            return;
        }

        $options = [
            'user_id' => $companyUser->id,
            'properties' => $properties,
        ];

        if ($subject) {
            $options['subject_type'] = get_class($subject);
            $options['subject_id'] = $subject->id;
        }

        CompanyActivityLog::log(
            $companyUser->company_id,
            $action,
            $description,
            $options
        );
    }

    /**
     * Log delivery-related activity.
     */
    protected function logDeliveryActivity($action, $delivery, $description = null)
    {
        $descriptions = [
            'delivery_created' => "New delivery {$delivery->tracking_number} created for {$delivery->customer->name}",
            'delivery_assigned' => "Delivery {$delivery->tracking_number} assigned to {$delivery->deliveryMan->name}",
            'delivery_status_changed' => "Delivery {$delivery->tracking_number} status changed to {$delivery->status}",
            'delivery_completed' => "Delivery {$delivery->tracking_number} completed for {$delivery->customer->name}",
            'delivery_updated' => "Delivery {$delivery->tracking_number} updated",
        ];

        $finalDescription = $description ?? $descriptions[$action] ?? "Delivery action: {$action}";

        $this->logActivity($action, $finalDescription, $delivery);
    }

    /**
     * Log customer-related activity.
     */
    protected function logCustomerActivity($action, $customer, $description = null)
    {
        $descriptions = [
            'customer_created' => "New customer created: {$customer->name}",
            'customer_updated' => "Customer {$customer->name} updated",
            'customer_deleted' => "Customer {$customer->name} deleted",
        ];

        $finalDescription = $description ?? $descriptions[$action] ?? "Customer action: {$action}";

        $this->logActivity($action, $finalDescription, $customer);
    }

    /**
     * Log delivery man-related activity.
     */
    protected function logDeliveryManActivity($action, $deliveryMan, $description = null)
    {
        $descriptions = [
            'delivery_man_linked' => "Delivery man {$deliveryMan->name} linked to company",
            'delivery_man_unlinked' => "Delivery man {$deliveryMan->name} unlinked from company",
        ];

        $finalDescription = $description ?? $descriptions[$action] ?? "Delivery man action: {$action}";

        $this->logActivity($action, $finalDescription, $deliveryMan);
    }
}
