<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyActivityController extends Controller
{
    public function index(Request $request)
    {
        $company = Auth::guard('company_user')->user()->company;

        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'action' => 'nullable|string|max:100',
            'subject_type' => 'nullable|string|max:255',
            'subject_id' => 'nullable|integer|min:1',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'sort_by' => 'nullable|string|in:id,action,created_at,updated_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $query = CompanyActivityLog::query()
            ->forCompany($company->id)
            ->with(['user:id,name,email', 'subject']);

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = (int) $request->get('per_page', 15);
        $activities = $query->paginate($perPage);

        return $this->success([
            'activities' => collect($activities->items())
                ->map(fn (CompanyActivityLog $activity) => $this->transformActivity($activity))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'last_page' => $activities->lastPage(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
                'has_more_pages' => $activities->hasMorePages(),
            ],
            'filters_applied' => [
                'action' => $request->action,
                'subject_type' => $request->subject_type,
                'subject_id' => $request->subject_id,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ], 'Activities fetched successfully.');
    }

    public function show(int $id)
    {
        $company = Auth::guard('company_user')->user()->company;

        $activity = CompanyActivityLog::query()
            ->forCompany($company->id)
            ->with(['user:id,name,email', 'subject'])
            ->find($id);

        if (! $activity) {
            return $this->error('Activity not found.', [], 404);
        }

        return $this->success($this->transformActivity($activity), 'Activity fetched successfully.');
    }

    private function transformActivity(CompanyActivityLog $activity): array
    {
        $entityType = $this->normalizeSubjectType($activity->subject_type);

        return [
            'id' => $activity->id,
            'company_id' => $activity->company_id,
            'user_id' => $activity->user_id,
            'action' => $activity->action,
            'description' => $activity->description,
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'properties' => $activity->properties,
            'ip_address' => $activity->ip_address,
            'user_agent' => $activity->user_agent,
            'created_at' => $activity->created_at,
            'updated_at' => $activity->updated_at,
            'user' => $activity->user,
            'subject' => $this->buildSubjectBrief($activity),
            'entity' => [
                'type' => $entityType,
                'id' => $activity->subject_id,
                'label' => $this->resolveEntityLabel($activity),
                'model' => $activity->subject_type,
            ],
        ];
    }

    private function buildSubjectBrief(CompanyActivityLog $activity): ?array
    {
        $subject = $activity->subject;
        $type = $this->normalizeSubjectType($activity->subject_type);

        if (! $subject) {
            if (! $activity->subject_id) {
                return null;
            }

            return [
                'id' => $activity->subject_id,
                'type' => $type,
                'label' => $this->resolveEntityLabel($activity),
            ];
        }

        $brief = [
            'id' => $subject->id,
            'type' => $type,
            'label' => $this->resolveEntityLabel($activity),
        ];

        if (isset($subject->tracking_number) && $subject->tracking_number) {
            $brief['tracking_number'] = $subject->tracking_number;
        }

        if (isset($subject->order_number) && $subject->order_number) {
            $brief['order_number'] = $subject->order_number;
        }

        if (isset($subject->name) && $subject->name) {
            $brief['name'] = $subject->name;
        }

        return $brief;
    }

    private function normalizeSubjectType(?string $subjectType): string
    {
        return match ($subjectType) {
            \App\Models\Delivery::class => 'delivery',
            \App\Models\Order::class => 'order',
            \App\Models\Customer::class => 'customer',
            \App\Models\Rider::class => 'rider',
            \App\Models\Item::class => 'item',
            \App\Models\Address::class => 'address',
            \App\Models\CompanyUser::class => 'company_user',
            null => 'none',
            default => strtolower(class_basename($subjectType)),
        };
    }

    private function resolveEntityLabel(CompanyActivityLog $activity): string
    {
        $subject = $activity->subject;
        $type = $this->normalizeSubjectType($activity->subject_type);

        if (! $subject) {
            return $activity->subject_id ? ucfirst($type) . ' #' . $activity->subject_id : 'N/A';
        }

        if (isset($subject->tracking_number) && $subject->tracking_number) {
            return (string) $subject->tracking_number;
        }

        if (isset($subject->order_number) && $subject->order_number) {
            return (string) $subject->order_number;
        }

        if (isset($subject->name) && $subject->name) {
            return (string) $subject->name;
        }

        if (isset($subject->label) && $subject->label) {
            return (string) $subject->label;
        }

        return ucfirst($type) . ' #' . ($activity->subject_id ?? $subject->id);
    }
}
