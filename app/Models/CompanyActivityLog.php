<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Get the company that owns the activity log.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the company user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(CompanyUser::class, 'user_id');
    }

    /**
     * Get the subject model (polymorphic relationship).
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter activities by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter activities by action.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get recent activities.
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Static method to log activity.
     */
    public static function log($companyId, $action, $description, $options = [])
    {
        return static::create([
            'company_id' => $companyId,
            'user_id' => $options['user_id'] ?? auth('company_user')->id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $options['subject_type'] ?? null,
            'subject_id' => $options['subject_id'] ?? null,
            'properties' => $options['properties'] ?? null,
            'ip_address' => $options['ip_address'] ?? request()->ip(),
            'user_agent' => $options['user_agent'] ?? request()->userAgent(),
        ]);
    }
}
