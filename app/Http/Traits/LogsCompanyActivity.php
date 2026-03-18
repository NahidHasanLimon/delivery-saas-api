<?php

namespace App\Http\Traits;

use App\Models\CompanyActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsCompanyActivity
{
    /**
     * Log activity for the authenticated company.
     */
    protected function logActivity($action, $description, $subject = null, $properties = null): void
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
        $customerName = $delivery->customer ? $delivery->customer->name : 'Unknown Customer';
        $riderName = $delivery->rider ? $delivery->rider->name : 'unassigned';
        $trackingNumber = $delivery->tracking_number ?? 'Unknown';

        $descriptions = [
            'delivery_created' => "New delivery {$trackingNumber} created for {$customerName}",
            'delivery_assigned' => "Delivery {$trackingNumber} assigned to {$riderName}",
            'delivery_status_changed' => "Delivery {$trackingNumber} status changed to {$delivery->status}",
            'delivery_completed' => "Delivery {$trackingNumber} completed for {$customerName}",
            'delivery_updated' => "Delivery {$trackingNumber} updated",
        ];

        $finalDescription = $description ?? $descriptions[$action] ?? "Delivery action: {$action}";

        $this->logActivity($action, $finalDescription, $delivery);
    }

    /**
     * Log customer-related activity.
     */
    protected function logCustomerActivity($action, $customer, $description = null)
    {
        $customerName = $customer ? $customer->name : 'Unknown Customer';

        $descriptions = [
            'customer_created' => "New customer created: {$customerName}",
            'customer_updated' => "Customer {$customerName} updated",
            'customer_deleted' => "Customer {$customerName} deleted",
        ];

        $finalDescription = $description ?? $descriptions[$action] ?? "Customer action: {$action}";

        $this->logActivity($action, $finalDescription, $customer);
    }

    /**
     * Log rider-related activity.
     */
    protected function logRiderActivity($action, $rider, $description = null)
    {
        $riderName = $rider ? $rider->name : 'Unknown Rider';

        $descriptions = [
            'rider_linked' => "Rider {$riderName} linked to company",
            'rider_unlinked' => "Rider {$riderName} unlinked from company",
        ];

        $finalDescription = $description ?? $descriptions[$action] ?? "Rider action: {$action}";

        $this->logActivity($action, $finalDescription, $rider);
    }
}
