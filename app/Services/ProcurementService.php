<?php

namespace App\Services;

use App\Models\Request;
use App\Models\RequestItem;
use App\Models\StatusHistory;
use App\Models\Stock;
use App\Models\User;
use App\Models\ProcurementOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProcurementService
{
    /**
     * Get department report with Redis caching (TTL: 1 hour).
     */
    public function getTopDepartmentsReport()
    {
        return Cache::remember('report:top_departments', now()->addHour(), function () {
            return DB::table('requests')
                ->join('departments', 'requests.department_id', '=', 'departments.id')
                ->select('departments.name', DB::raw('count(requests.id) as total_requests'))
                ->where('requests.created_at', '>=', now()->subMonths(3))
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('total_requests')
                ->limit(5)
                ->get();
        });
    }

    /**
     * Clear report cache when a new request is made or state changes.
     */
    protected function clearReportCache()
    {
        Cache::forget('report:top_departments');
    }
    public function createRequest(array $data, User $user)
    {
        return DB::transaction(function () use ($data, $user) {
            $request = Request::create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'] ?? $user->department_id,
                'notes' => $data['notes'] ?? null,
                'status' => 'DRAFT',
                'total_amount' => 0,
            ]);

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $stock = Stock::findOrFail($item['stock_id']);
                $subtotal = $item['qty_requested'] * $stock->unit_price;

                RequestItem::create([
                    'request_id' => $request->id,
                    'stock_id' => $stock->id,
                    'qty_requested' => $item['qty_requested'],
                    'snapshot_price' => $stock->unit_price,
                    'subtotal' => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            $request->update(['total_amount' => $totalAmount]);
            $this->logStatusChange($request, 'DRAFT', $user);

            return $request->load('items.stock');
        });
    }

    /**
     * Submit a request for verification.
     */
    public function submitRequest(Request $request, User $user, string $expectedUpdatedAt = null)
    {
        return $this->updateStatus($request, 'SUBMITTED', $user, $expectedUpdatedAt);
    }

    /**
     * Verify a request (Purchasing).
     */
    public function verifyRequest(Request $request, User $user, string $expectedUpdatedAt = null)
    {
        return $this->updateStatus($request, 'VERIFIED', $user, $expectedUpdatedAt);
    }

    /**
     * Approve a request (Manager).
     */
    public function approveRequest(Request $request, User $user, string $comments = null, string $expectedUpdatedAt = null)
    {
        return DB::transaction(function () use ($request, $user, $comments, $expectedUpdatedAt) {

            $request = Request::where('id', $request->id)->lockForUpdate()->first();

            if ($request->status !== 'VERIFIED') {
                throw new \Exception("Request is not in a verifiable state.");
            }

            $request->approvals()->create([
                'approver_id' => $user->id,
                'action' => 'APPROVED',
                'comments' => $comments,
            ]);

            return $this->updateStatus($request, 'APPROVED', $user, $expectedUpdatedAt);
        });
    }

    /**
     * Reject a request.
     */
    public function rejectRequest(Request $request, User $user, string $comments, string $expectedUpdatedAt = null)
    {
        return DB::transaction(function () use ($request, $user, $comments, $expectedUpdatedAt) {
            $request = Request::where('id', $request->id)->lockForUpdate()->first();
            
            $request->approvals()->create([
                'approver_id' => $user->id,
                'action' => 'REJECTED',
                'comments' => $comments,
            ]);

            return $this->updateStatus($request, 'REJECTED', $user, $expectedUpdatedAt);
        });
    }

    /**
     * Check stock and potentially trigger procurement.
     */
    public function checkStock(Request $request, User $user, string $expectedUpdatedAt = null)
    {
        return DB::transaction(function () use ($request, $user, $expectedUpdatedAt) {
            $this->updateStatus($request, 'CHECKING_STOCK', $user, $expectedUpdatedAt);
            
            foreach ($request->items as $item) {
                $affected = DB::table('stock')
                    ->where('id', $item->stock_id)
                    ->where('quantity', '>=', $item->qty_requested)
                    ->decrement('quantity', $item->qty_requested);

                if ($affected === 0) {
                    throw new \Exception("Stock insufficient for item: " . ($item->stock->name ?? $item->stock_id));
                }
            }

            return $this->updateStatus($request, 'COMPLETED', $user);
        });
    }

    /**
     * Generate Procurement Order (PO) for vendor.
     */
    public function createProcurementOrder(Request $request, int $vendorId, User $user, string $expectedUpdatedAt = null)
    {
        return DB::transaction(function () use ($request, $vendorId, $user, $expectedUpdatedAt) {
            $po = ProcurementOrder::create([
                'request_id' => $request->id,
                'vendor_id' => $vendorId,
                'po_number' => 'PO-' . strtoupper(Str::random(8)),
                'status' => 'ORDERED',
                'total_cost' => $request->total_amount, // Simplified
            ]);

            $this->updateStatus($request, 'IN_PROCUREMENT', $user, $expectedUpdatedAt);
            return $po;
        });
    }

    /**
     * Generic status updater and history logger with Optimistic Locking.
     */
    protected function updateStatus(Request $request, string $newStatus, User $user, string $expectedUpdatedAt = null)
    {
        $oldStatus = $request->status;

        if ($expectedUpdatedAt) {
            $dbUpdatedAt = $request->updated_at->toDateTimeString();
            if ($dbUpdatedAt !== $expectedUpdatedAt) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json(['error' => 'Conflict: Request has been modified by another user.'], 409)
                );
            }
        }

        $request->update(['status' => $newStatus]);
        $this->logStatusChange($request, $newStatus, $user, $oldStatus);
        return $request;
    }

    protected function logStatusChange(Request $request, string $newStatus, User $user, string $oldStatus = null)
    {
        StatusHistory::create([
            'request_id' => $request->id,
            'user_id' => $user->id,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_at' => now(),
        ]);
    }
}
