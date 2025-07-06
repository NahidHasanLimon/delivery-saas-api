<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use App\Models\Delivery;
use App\Models\DeliveryMan;
use App\Models\Customer;
use App\Models\CompanyActivityLog;
use App\Enums\DeliveryStatus;
use Illuminate\Support\Carbon;

class CompanyDashboardController extends Controller
{
    public function summary(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;
        $today = now()->toDateString();

        $totalDeliveries = $company->deliveries()->count();
        $activeDeliverymen = $company->deliveryMen()->count();
        $totalCustomers = $company->customers()->count();
        
        // Get all delivery status counts in a single query
        $deliveryStatusCounts = $company->deliveries()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get today's delivered deliveries and revenue in a single query
        $todayStats = $company->deliveries()
            ->where('status', DeliveryStatus::DELIVERED)
            ->whereDate('delivered_at', $today)
            ->selectRaw('COUNT(*) as delivered_today, COALESCE(SUM(amount), 0) as revenue')
            ->first();

        // Recent deliveries (last 5)
        $recentDeliveries = $company->deliveries()
            ->with(['customer', 'deliveryMan'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent activity (based on delivery timestamps)
        $recentActivity = $this->getRecentActivity($company);

        // Top performers (delivery men with most delivered deliveries this month)
        $topPerformers = $this->getTopPerformers($company);

        return $this->success([
            'summary' => [
                'total_deliveries' => $totalDeliveries,
                'active_deliverymen' => $activeDeliverymen,
                'total_customers' => $totalCustomers,
                'delivery_status_counts' => [
                    'pending' => $deliveryStatusCounts[DeliveryStatus::PENDING->value] ?? 0,
                    'assigned' => $deliveryStatusCounts[DeliveryStatus::ASSIGNED->value] ?? 0,
                    'in_progress' => $deliveryStatusCounts[DeliveryStatus::IN_PROGRESS->value] ?? 0,
                    'delivered' => $deliveryStatusCounts[DeliveryStatus::DELIVERED->value] ?? 0,
                    'cancelled' => $deliveryStatusCounts[DeliveryStatus::CANCELLED->value] ?? 0,
                ],
                'delivered_today' => $todayStats->delivered_today ?? 0,
                'revenue_today' => $todayStats->revenue ?? 0,
            ],
            'recent_deliveries' => $recentDeliveries,
            'recent_activity' => $recentActivity,
            'top_performers' => $topPerformers,
        ], 'Dashboard data fetched.');
    }

    /**
     * Get recent activity based on activity logs
     */
    private function getRecentActivity($company)
    {
        return CompanyActivityLog::forCompany($company->id)
            ->with(['user'])
            ->recent(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user_name' => $log->user ? $log->user->name : 'System',
                    'created_at' => $log->created_at,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                ];
            });
    }

    /**
     * Get top 3 performing delivery men
     */
    private function getTopPerformers($company)
    {
        $startOfMonth = now()->startOfMonth();
        
        $topPerformers = $company->deliveryMen()
            ->withCount(['deliveries as delivered_deliveries' => function ($query) use ($startOfMonth) {
                $query->where('status', DeliveryStatus::DELIVERED)
                      ->where('delivered_at', '>=', $startOfMonth);
            }])
            ->having('delivered_deliveries', '>', 0) // Only get delivery men with actual deliveries
            ->orderBy('delivered_deliveries', 'desc')
            ->limit(3)
            ->get();

        if ($topPerformers->isEmpty()) {
            return [];
        }

        return $topPerformers->map(function ($performer) use ($startOfMonth) {
            // Calculate additional metrics for each performer
            $totalDeliveries = $performer->deliveries()
                ->where('delivered_at', '>=', $startOfMonth)
                ->count();

            $onTimeDeliveries = $performer->deliveries()
                ->where('delivered_at', '>=', $startOfMonth)
                ->where('status', DeliveryStatus::DELIVERED)
                ->whereColumn('delivered_at', '<=', 'expected_delivery_time')
                ->count();

            $onTimeRate = $totalDeliveries > 0 ? round(($onTimeDeliveries / $totalDeliveries) * 100, 1) : 0;

            return [
                'id' => $performer->id,
                'name' => $performer->name,
                'email' => $performer->email,
                'mobile_no' => $performer->mobile_no,
                'delivered_deliveries' => $performer->delivered_deliveries ?? 0,
                'on_time_rate' => $onTimeRate,
                'total_deliveries_this_month' => $totalDeliveries,
            ];
        })->toArray();
    }
}
