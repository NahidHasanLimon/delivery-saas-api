<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CompanyUser;

class CompanyNotificationController extends Controller
{
    protected $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Update device token for the authenticated company user
     */
    public function updateDeviceToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string'
        ]);

        $user = Auth::guard('company_user')->user();
        $user->update([
            'device_token' => $request->device_token
        ]);

        return $this->success(null, 'Device token updated successfully.');
    }

    /**
     * Send test notification to the authenticated user
     */
    public function sendTestNotification(Request $request)
    {
        $user = Auth::guard('company_user')->user();

        if (!$user->device_token) {
            return response()->json([
                'success' => false,
                'message' => 'No device token found. Please register your device first.'
            ], 400);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500'
        ]);

        try {
            $result = $this->fcmService->sendToOne(
                $user->device_token,
                $request->title,
                $request->message,
                [
                    'type' => 'test_notification',
                    'sent_by' => $user->name,
                    'timestamp' => now()->toISOString()
                ],
                [
                    'actions' => [
                        [
                            'action' => 'dismiss',
                            'title' => 'Dismiss'
                        ]
                    ]
                ]
            );

            return $this->success($result, 'Test notification sent successfully.');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to all company users
     */
    public function sendToCompany(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'data' => 'nullable|array'
        ]);

        try {
            // Get company user tokens (business logic stays in controller)
            $companyUsers = CompanyUser::where('company_id', $company->id)
                ->whereNotNull('device_token')
                ->pluck('device_token')
                ->filter()
                ->toArray();

            if (empty($companyUsers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users with device tokens found in your company.'
                ], 400);
            }

            // Use clean FCM service method
            $results = $this->fcmService->sendToMany(
                $companyUsers,
                $request->title,
                $request->message,
                array_merge([
                    'type' => 'company_announcement',
                    'sent_by' => Auth::guard('company_user')->user()->name,
                    'timestamp' => now()->toISOString()
                ], $request->data ?? [])
            );

            $successCount = collect($results)->where('success', true)->count();
            $totalCount = count($results);

            return $this->success([
                'results' => $results,
                'summary' => [
                    'total_sent' => $totalCount,
                    'successful' => $successCount,
                    'failed' => $totalCount - $successCount
                ]
            ], "Notification sent to {$successCount} out of {$totalCount} company users.");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to specific company users
     */
    public function sendToUsers(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:company_users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'data' => 'nullable|array'
        ]);

        $users = CompanyUser::where('company_id', $company->id)
            ->whereIn('id', $request->user_ids)
            ->whereNotNull('device_token')
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No users found with device tokens.'
            ], 400);
        }

        try {
            $tokens = $users->pluck('device_token')->toArray();
            
            $results = $this->fcmService->sendToMany(
                $tokens,
                $request->title,
                $request->message,
                array_merge([
                    'type' => 'targeted_message',
                    'sent_by' => Auth::guard('company_user')->user()->name,
                    'timestamp' => now()->toISOString()
                ], $request->data ?? [])
            );

            $successCount = collect($results)->where('success', true)->count();
            $totalCount = count($results);

            return $this->success([
                'results' => $results,
                'summary' => [
                    'total_sent' => $totalCount,
                    'successful' => $successCount,
                    'failed' => $totalCount - $successCount
                ]
            ], "Notification sent to {$successCount} out of {$totalCount} selected users.");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of company users with their notification status
     */
    public function getCompanyUsers()
    {
        $company = Auth::guard('company_user')->user()->company;
        
        $users = CompanyUser::where('company_id', $company->id)
            ->select('id', 'name', 'email', 'role', 'device_token', 'created_at')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'can_receive_notifications' => !is_null($user->device_token),
                    'created_at' => $user->created_at
                ];
            });

        return $this->success([
            'users' => $users,
            'summary' => [
                'total_users' => $users->count(),
                'with_notifications' => $users->where('can_receive_notifications', true)->count(),
                'without_notifications' => $users->where('can_receive_notifications', false)->count()
            ]
        ], 'Company users fetched successfully.');
    }
}
