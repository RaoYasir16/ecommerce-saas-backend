<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the merchant's activity logs.
     */
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;

        $query = ActivityLog::query()
            ->where('company_id', $companyId)
            ->with(['causer:id,name,email', 'subject'])
            ->latest('id');

        // Filter by log type / name (e.g. product, order, category, store_setting)
        if ($type = $request->input('type') ?? $request->input('log_name')) {
            $query->where('log_name', $type);
        }

        // Filter by event (e.g. created, updated, deleted)
        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        // Search by description or log_name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('log_name', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $activities = $query->paginate($perPage);

        return $this->apiResponse(
            true,
            'Activity logs fetched successfully.',
            $activities
        );
    }

    /**
     * Display the specified activity log.
     */
    public function show($id)
    {
        $companyId = auth()->user()->company_id;

        $activity = ActivityLog::where('company_id', $companyId)
            ->with(['causer:id,name,email', 'subject'])
            ->find($id);

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

    /**
     * Get list of unique activity log types for filtering.
     */
    public function types()
    {
        $companyId = auth()->user()->company_id;

        $types = ActivityLog::where('company_id', $companyId)
            ->whereNotNull('log_name')
            ->distinct()
            ->pluck('log_name');

        return $this->apiResponse(
            true,
            'Activity types retrieved successfully.',
            $types
        );
    }
}

