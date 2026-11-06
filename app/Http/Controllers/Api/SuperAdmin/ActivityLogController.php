<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of platform activity logs for Super Admin.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->with(['causer', 'subject', 'company:id,name,subdomain'])
            ->latest('id');

        // Optional filter by specific company
        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

        // Filter by log type / name
        if ($type = $request->input('type') ?? $request->input('log_name')) {
            $query->where('log_name', $type);
        }

        // Filter by event (created, updated, deleted, etc.)
        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        // Search in description or log_name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('log_name', 'like', "%{$search}%");
            });
        }

        // Date range
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 5), 100);
        $activities = $query->paginate($perPage);

        return $this->apiResponse(
            true,
            'Super admin activity logs fetched successfully.',
            $activities
        );
    }

    /**
     * Display the specified activity log.
     */
    public function show($id)
    {
        $activity = ActivityLog::with(['causer', 'subject', 'company'])->find($id);

        if (!$activity) {
            return $this->apiResponse(
                false,
                'Activity log not found.',
                null,
                404
            );
        }

        return $this->apiResponse(
            true,
            'Activity log retrieved successfully.',
            $activity
        );
    }
}

