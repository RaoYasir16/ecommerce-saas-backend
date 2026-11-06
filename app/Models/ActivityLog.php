<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends SpatieActivity
{
    protected static function booted()
    {
        parent::booted();

        static::creating(function (ActivityLog $activity) {
            if (!empty($activity->company_id)) {
                return;
            }

            // 1. Resolve from subject
            if ($activity->subject) {
                if ($activity->subject instanceof Company) {
                    $activity->company_id = $activity->subject->id;
                } elseif (isset($activity->subject->company_id)) {
                    $activity->company_id = $activity->subject->company_id;
                }
            }

            // 2. Resolve from causer
            if (empty($activity->company_id) && $activity->causer) {
                if ($activity->causer instanceof Company) {
                    $activity->company_id = $activity->causer->id;
                } elseif (isset($activity->causer->company_id)) {
                    $activity->company_id = $activity->causer->company_id;
                }
            }

            // 3. Resolve from authenticated guards
            if (empty($activity->company_id)) {
                $authUser = auth('user')->user() ?? auth()->user();
                if ($authUser && isset($authUser->company_id)) {
                    $activity->company_id = $authUser->company_id;
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

