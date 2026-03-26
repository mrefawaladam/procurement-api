<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    #[OA\Get(
        path: '/api/reports/monthly-categories',
        tags: ['Reports'],
        summary: 'Top procurement categories by volume per month',
        responses: [
            new OA\Response(response: 200, description: 'Successful report generation')
        ]
    )]
    public function monthlyCategories()
    {
        // Using the user's provided SQL logic (optimized for Postgres)
        // We use a subquery with ROW_NUMBER to get the top category per month
        $results = DB::select("
            SELECT bulan, category, total_qty
            FROM (
                SELECT 
                    DATE_TRUNC('month', r.created_at)::DATE AS bulan,
                    s.category,
                    SUM(ri.qty_requested) AS total_qty,
                    ROW_NUMBER() OVER(
                        PARTITION BY DATE_TRUNC('month', r.created_at) 
                        ORDER BY SUM(ri.qty_requested) DESC
                    ) as rnk
                FROM requests r
                JOIN request_items ri ON r.id = ri.request_id
                JOIN stock s ON ri.stock_id = s.id
                GROUP BY DATE_TRUNC('month', r.created_at), s.category
            ) sub
            WHERE rnk = 1
            ORDER BY bulan DESC
        ");

        return response()->json($results);
    }

    #[OA\Get(
        path: '/api/reports/lead-time',
        tags: ['Reports'],
        summary: 'Average lead time from SUBMITTED to COMPLETED',
        responses: [
            new OA\Response(response: 200, description: 'Successful metric calculation')
        ]
    )]
    public function averageLeadTime()
    {
        // Lead time calculation based on status history
        $results = DB::select("
            WITH RequestTimestamps AS (
                SELECT request_id,
                    MIN(CASE WHEN new_status = 'SUBMITTED' THEN changed_at END) AS submitted_time,
                    MAX(CASE WHEN new_status = 'COMPLETED' THEN changed_at END) AS completed_time
                FROM status_history
                GROUP BY request_id
            )
            SELECT 
                AVG(completed_time - submitted_time) AS average_lead_time_interval,
                EXTRACT(EPOCH FROM AVG(completed_time - submitted_time)) / 3600 AS avg_hours
            FROM RequestTimestamps
            WHERE submitted_time IS NOT NULL AND completed_time IS NOT NULL
        ");

        return response()->json([
            'average_lead_time' => $results[0]->average_lead_time_interval ?? '0',
            'average_hours' => round($results[0]->avg_hours ?? 0, 2)
        ]);
    }
}
