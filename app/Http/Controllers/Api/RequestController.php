<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request as ProcurementRequest;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use OpenApi\Attributes as OA;

class RequestController extends Controller
{
    protected ProcurementService $service;

    public function __construct(ProcurementService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: "/api/requests/export",
        summary: "Export requests to CSV (Supports 5M+ records using Chunking)",
        security: [['sanctum' => []]],
        tags: ["Requests"],
        responses: [new OA\Response(response: 200, description: "CSV file stream")]
    )]
    public function exportCsv()
    {
        $fileName = 'requests_report_'.now()->format('Ymd_His').'.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'User', 'Department', 'Status', 'Amount', 'Date']);

            // Using chunk to handle millions of records
            ProcurementRequest::with(['user', 'department'])->chunk(5000, function ($requests) use ($file) {
                foreach ($requests as $req) {
                    fputcsv($file, [
                        $req->id,
                        $req->user->name,
                        $req->department->name,
                        $req->status,
                        $req->total_amount,
                        $req->created_at
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    #[OA\Post(
        path: "/api/requests",
        summary: "Create a new procurement request",
        security: [['sanctum' => []]],
        tags: ["Requests"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "department_id", type: "integer", example: 1),
                    new OA\Property(property: "notes", type: "string", example: "Need new equipment"),
                    new OA\Property(
                        property: "items",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "stock_id", type: "integer", example: 1),
                                new OA\Property(property: "qty_requested", type: "integer", example: 2)
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Success"),
            new OA\Response(response: 422, description: "Validation Error")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => 'sometimes|exists:departments,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.stock_id' => 'required|exists:stock,id',
            'items.*.qty_requested' => 'required|integer|min:1',
        ]);

        $user = $request->user(); // Assuming auth helper works
        $procurementRequest = $this->service->createRequest($validated, $user);

        return response()->json([
            'message' => 'Request created successfully',
            'data' => $procurementRequest,
        ], 201);
    }

    #[OA\Get(
        path: "/api/requests",
        summary: "List all procurement requests",
        security: [['sanctum' => []]],
        tags: ["Requests"],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [new OA\Response(response: 200, description: "Paginated list of requests")]
    )]
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $query = ProcurementRequest::with(['user', 'department', 'items.stock']);

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(15);

        return response()->json($requests);
    }

    #[OA\Post(
        path: "/api/requests/{id}/approve",
        summary: "Approve a procurement request (Manager Only)",
        security: [['sanctum' => []]],
        tags: ["Workflows"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "comments", type: "string", example: "Approved for budget"),
                    new OA\Property(property: "last_updated_at", type: "string", example: "2024-03-27 05:00:00")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Approved"),
            new OA\Response(response: 409, description: "Conflict"),
            new OA\Response(response: 422, description: "Constraint Violation")
        ]
    )]
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate(['last_updated_at' => 'required|string']);
        $procurementRequest = ProcurementRequest::findOrFail($id);
        
        try {
            $updated = $this->service->approveRequest($procurementRequest, $request->user(), $request->comments, $request->last_updated_at);
            return response()->json([
                'message' => 'Request approved',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    #[OA\Post(
        path: "/api/requests/{id}/reject",
        summary: "Reject a procurement request",
        security: [['sanctum' => []]],
        tags: ["Workflows"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "comments", type: "string", example: "Budget too high"),
                    new OA\Property(property: "last_updated_at", type: "string", example: "2024-03-27 05:00:00")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Rejected"),
            new OA\Response(response: 409, description: "Conflict")
        ]
    )]
    public function reject(Request $request, int $id): JsonResponse
    {
        $procurementRequest = ProcurementRequest::findOrFail($id);
        
        $request->validate([
            'comments' => 'required|string',
            'last_updated_at' => 'required|string'
        ]);

        $updated = $this->service->rejectRequest($procurementRequest, $request->user(), $request->comments, $request->last_updated_at);
        
        return response()->json([
            'message' => 'Request rejected',
            'data' => $updated,
        ]);
    }

    #[OA\Post(
        path: "/api/requests/{id}/check-stock",
        summary: "Attempt to fulfill from stock immediately",
        security: [['sanctum' => []]],
        tags: ["Workflows"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "last_updated_at", type: "string", example: "2024-03-27 05:00:00")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Successfully fulfilled from stock"),
            new OA\Response(response: 409, description: "Conflict"),
            new OA\Response(response: 422, description: "Insufficient stock")
        ]
    )]
    public function checkStock(Request $request, int $id): JsonResponse
    {
        $request->validate(['last_updated_at' => 'required|string']);
        $procurementRequest = ProcurementRequest::findOrFail($id);
        
        try {
            $updated = $this->service->checkStock($procurementRequest, $request->user(), $request->last_updated_at);
            return response()->json([
                'message' => 'Stock fulfilled, status updated to COMPLETED',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    
    #[OA\Post(
        path: "/api/requests/{id}/procure",
        summary: "Generate Procurement Order (PO) to vendors",
        security: [['sanctum' => []]],
        tags: ["Workflows"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "vendor_id", type: "integer", example: 1),
                    new OA\Property(property: "last_updated_at", type: "string", example: "2024-03-27 05:00:00")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "PO Created"),
            new OA\Response(response: 409, description: "Conflict")
        ]
    )]
    public function procure(Request $request, int $id): JsonResponse
    {
        $procurementRequest = ProcurementRequest::findOrFail($id);
        
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'last_updated_at' => 'required|string'
        ]);

        $po = $this->service->createProcurementOrder($procurementRequest, $request->vendor_id, $request->user(), $request->last_updated_at);
        
        return response()->json([
            'message' => 'Procurement Order generated',
            'data' => $po,
        ], 201);
    }
}
