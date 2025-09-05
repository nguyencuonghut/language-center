<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class TransferAuditController extends Controller
{
    /**
     * Display audit log for a specific transfer
     */
    public function show(Transfer $transfer)
    {
        $transfer->load([
            'student:id,code,name',
            'fromClass:id,code,name',
            'toClass:id,code,name',
            'retargetedToClass:id,code,name',
            'createdBy:id,name',
            'revertedBy:id,name',
            'retargetedBy:id,name',
            'lastModifiedBy:id,name',
        ]);

        // Compile detailed audit trail
        $auditTrail = $this->compileAuditTrail($transfer);

        return Inertia::render('Manager/Transfers/Audit', [
            'transfer' => $transfer,
            'audit_trail' => $auditTrail,
        ]);
    }

    /**
     * Export audit log for a transfer
     */
    public function export(Transfer $transfer, Request $request)
    {
        $format = $request->get('format', 'json'); // json, csv, pdf

        $transfer->load([
            'student:id,code,name',
            'fromClass:id,code,name',
            'toClass:id,code,name',
            'retargetedToClass:id,code,name',
            'createdBy:id,name',
            'revertedBy:id,name',
            'retargetedBy:id,name',
        ]);

        $auditTrail = $this->compileAuditTrail($transfer);

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($transfer, $auditTrail);
            case 'pdf':
                return $this->exportToPdf($transfer, $auditTrail);
            default:
                return response()->json([
                    'transfer' => $transfer,
                    'audit_trail' => $auditTrail,
                ]);
        }
    }

    /**
     * Search audit logs across all transfers
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        $userId = $request->get('user_id');
        $action = $request->get('action'); // created, reverted, retargeted
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $transfers = Transfer::with([
            'student:id,code,name',
            'fromClass:id,code,name',
            'toClass:id,code,name',
            'createdBy:id,name',
            'revertedBy:id,name',
            'retargetedBy:id,name',
        ]);

        // Filter by date range
        if ($startDate && $endDate) {
            $transfers->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        // Filter by action and user
        if ($action && $userId) {
            // Both action and user specified
            switch ($action) {
                case 'created':
                    $transfers->where('created_by', $userId);
                    break;
                case 'reverted':
                    $transfers->where('reverted_by', $userId);
                    break;
                case 'retargeted':
                    $transfers->where('retargeted_by', $userId);
                    break;
            }
        } elseif ($userId) {
            // Only user specified - search by any user action
            $transfers->where(function($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhere('reverted_by', $userId)
                  ->orWhere('retargeted_by', $userId);
            });
        } elseif ($action) {
            // Only action specified - filter by action type
            switch ($action) {
                case 'created':
                    // All transfers (since all are created)
                    break;
                case 'reverted':
                    $transfers->whereNotNull('reverted_at');
                    break;
                case 'retargeted':
                    $transfers->whereNotNull('retargeted_at');
                    break;
            }
        }

        // Text search in status_history and change_log
        if ($query) {
            $transfers->where(function($q) use ($query) {
                $q->whereRaw("JSON_EXTRACT(status_history, '$[*].description') LIKE ?", ["%{$query}%"])
                  ->orWhereRaw("JSON_EXTRACT(change_log, '$[*].description') LIKE ?", ["%{$query}%"])
                  ->orWhere('reason', 'LIKE', "%{$query}%")
                  ->orWhere('notes', 'LIKE', "%{$query}%");
            });
        }

        $results = $transfers->orderByDesc('created_at')->paginate(20);

        // Get all users for filter dropdown - simplified approach
        $users = User::whereIn('id', function($query) {
            $query->selectRaw('DISTINCT created_by as user_id')
                  ->from('transfers')
                  ->whereNotNull('created_by')
                  ->unionAll(
                      DB::table('transfers')
                        ->selectRaw('DISTINCT reverted_by as user_id')
                        ->whereNotNull('reverted_by')
                  )
                  ->unionAll(
                      DB::table('transfers')
                        ->selectRaw('DISTINCT retargeted_by as user_id')
                        ->whereNotNull('retargeted_by')
                  );
        })->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Manager/Transfers/AuditSearch', [
            'transfers' => $results,
            'users' => $users,
            'filters' => $request->only(['q', 'user_id', 'action', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Export search results
     */
    public function exportSearch(Request $request)
    {
        $format = $request->get('format', 'json');

        // Reuse search logic
        $query = $request->get('q');
        $userId = $request->get('user_id');
        $action = $request->get('action');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $transfers = Transfer::with([
            'student:id,code,name',
            'fromClass:id,code,name',
            'toClass:id,code,name',
            'createdBy:id,name',
            'revertedBy:id,name',
            'retargetedBy:id,name',
        ]);

        // Apply same filters as search method
        if ($startDate && $endDate) {
            $transfers->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        if ($action && $userId) {
            // Both action and user specified
            switch ($action) {
                case 'created':
                    $transfers->where('created_by', $userId);
                    break;
                case 'reverted':
                    $transfers->where('reverted_by', $userId);
                    break;
                case 'retargeted':
                    $transfers->where('retargeted_by', $userId);
                    break;
            }
        } elseif ($userId) {
            // Only user specified
            $transfers->where(function($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhere('reverted_by', $userId)
                  ->orWhere('retargeted_by', $userId);
            });
        } elseif ($action) {
            // Only action specified
            switch ($action) {
                case 'created':
                    // All transfers (since all are created)
                    break;
                case 'reverted':
                    $transfers->whereNotNull('reverted_at');
                    break;
                case 'retargeted':
                    $transfers->whereNotNull('retargeted_at');
                    break;
            }
        }

        if ($query) {
            $transfers->where(function($q) use ($query) {
                $q->whereRaw("JSON_EXTRACT(status_history, '$[*].description') LIKE ?", ["%{$query}%"])
                  ->orWhereRaw("JSON_EXTRACT(change_log, '$[*].description') LIKE ?", ["%{$query}%"])
                  ->orWhere('reason', 'LIKE', "%{$query}%")
                  ->orWhere('notes', 'LIKE', "%{$query}%");
            });
        }

        $results = $transfers->orderByDesc('created_at')->get();

        switch ($format) {
            case 'csv':
                return $this->exportSearchToCsv($results, $request->all());
            default:
                return response()->json([
                    'filters' => $request->all(),
                    'total_records' => $results->count(),
                    'data' => $results,
                ]);
        }
    }

    /**
     * Export search results to CSV
     */
    private function exportSearchToCsv($transfers, $filters)
    {
        $filename = "transfers_audit_search_" . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($transfers, $filters) {
            $file = fopen('php://output', 'w');

            // Write filters as header comments
            fputcsv($file, ['# Audit Search Export']);
            fputcsv($file, ['# Generated at: ' . now()->format('Y-m-d H:i:s')]);
            if (!empty($filters['q'])) fputcsv($file, ['# Search Query: ' . $filters['q']]);
            if (!empty($filters['user_id'])) fputcsv($file, ['# User ID: ' . $filters['user_id']]);
            if (!empty($filters['action'])) fputcsv($file, ['# Action: ' . $filters['action']]);
            if (!empty($filters['start_date'])) fputcsv($file, ['# Start Date: ' . $filters['start_date']]);
            if (!empty($filters['end_date'])) fputcsv($file, ['# End Date: ' . $filters['end_date']]);
            fputcsv($file, ['# Total Records: ' . $transfers->count()]);
            fputcsv($file, ['']); // Empty row

            // Write headers
            fputcsv($file, [
                'Transfer ID',
                'Student Code',
                'Student Name',
                'From Class',
                'To Class',
                'Status',
                'Created By',
                'Created At',
                'Reverted By',
                'Reverted At',
                'Retargeted By',
                'Retargeted At',
                'Reason',
                'Notes',
                'Transfer Fee'
            ]);

            // Write data
            foreach ($transfers as $transfer) {
                fputcsv($file, [
                    $transfer->id,
                    $transfer->student?->code ?? '',
                    $transfer->student?->name ?? '',
                    $transfer->fromClass?->code ?? '',
                    $transfer->toClass?->code ?? '',
                    $transfer->status,
                    $transfer->createdBy?->name ?? '',
                    $transfer->created_at?->format('Y-m-d H:i:s') ?? '',
                    $transfer->revertedBy?->name ?? '',
                    $transfer->reverted_at?->format('Y-m-d H:i:s') ?? '',
                    $transfer->retargetedBy?->name ?? '',
                    $transfer->retargeted_at?->format('Y-m-d H:i:s') ?? '',
                    $transfer->reason ?? '',
                    $transfer->notes ?? '',
                    $transfer->transfer_fee ?? 0
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Compile detailed audit trail from transfer data
     */
    private function compileAuditTrail(Transfer $transfer): array
    {
        $trail = [];

        // 1. Creation event
        $trail[] = [
            'id' => 'created',
            'action' => 'created',
            'timestamp' => $transfer->created_at,
            'user' => $transfer->createdBy?->name ?? 'System',
            'user_id' => $transfer->created_by,
            'description' => 'Tạo chuyển lớp',
            'details' => [
                'student' => $transfer->student?->code . ' - ' . $transfer->student?->name,
                'from_class' => $transfer->fromClass?->code . ' - ' . $transfer->fromClass?->name,
                'to_class' => $transfer->toClass?->code . ' - ' . $transfer->toClass?->name,
                'reason' => $transfer->reason,
                'transfer_fee' => $transfer->transfer_fee,
            ],
            'type' => 'info'
        ];

        // 2. Status history
        foreach ($transfer->status_history ?? [] as $index => $change) {
            $trail[] = [
                'id' => 'status_' . $index,
                'action' => 'status_change',
                'timestamp' => $change['timestamp'] ?? null,
                'user' => $change['user_name'] ?? 'Unknown',
                'user_id' => $change['user_id'] ?? null,
                'description' => $change['description'] ?? 'Thay đổi trạng thái',
                'details' => [
                    'from_status' => $change['from_status'] ?? '',
                    'to_status' => $change['to_status'] ?? '',
                ],
                'type' => 'warning'
            ];
        }

        // 3. Change log
        foreach ($transfer->change_log ?? [] as $index => $change) {
            $trail[] = [
                'id' => 'change_' . $index,
                'action' => 'field_change',
                'timestamp' => $change['timestamp'] ?? null,
                'user' => $change['user_name'] ?? 'Unknown',
                'user_id' => $change['user_id'] ?? null,
                'description' => $change['description'] ?? 'Thay đổi dữ liệu',
                'details' => [
                    'field' => $change['field'] ?? '',
                    'old_value' => $change['old_value'] ?? '',
                    'new_value' => $change['new_value'] ?? '',
                ],
                'type' => 'info'
            ];
        }

        // 4. Revert event (if exists)
        if ($transfer->reverted_at) {
            $trail[] = [
                'id' => 'reverted',
                'action' => 'reverted',
                'timestamp' => $transfer->reverted_at,
                'user' => $transfer->revertedBy?->name ?? 'Unknown',
                'user_id' => $transfer->reverted_by,
                'description' => 'Hoàn tác chuyển lớp',
                'details' => [
                    'reason' => 'Từ audit log revert', // Would need to store this separately
                ],
                'type' => 'danger'
            ];
        }

        // 5. Retarget event (if exists)
        if ($transfer->retargeted_at) {
            $trail[] = [
                'id' => 'retargeted',
                'action' => 'retargeted',
                'timestamp' => $transfer->retargeted_at,
                'user' => $transfer->retargetedBy?->name ?? 'Unknown',
                'user_id' => $transfer->retargeted_by,
                'description' => 'Đổi hướng chuyển lớp',
                'details' => [
                    'new_target' => $transfer->retargetedToClass?->code . ' - ' . $transfer->retargetedToClass?->name,
                ],
                'type' => 'success'
            ];
        }

        // Sort by timestamp
        usort($trail, function($a, $b) {
            $timeA = $a['timestamp'] ? strtotime($a['timestamp']) : 0;
            $timeB = $b['timestamp'] ? strtotime($b['timestamp']) : 0;
            return $timeA - $timeB;
        });

        return $trail;
    }

    /**
     * Export audit trail to CSV
     */
    private function exportToCsv(Transfer $transfer, array $auditTrail)
    {
        $filename = "transfer_{$transfer->id}_audit_" . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($transfer, $auditTrail) {
            $file = fopen('php://output', 'w');

            // Write headers
            fputcsv($file, [
                'Thời gian',
                'Hành động',
                'Người thực hiện',
                'Mô tả',
                'Chi tiết'
            ]);

            // Write audit trail
            foreach ($auditTrail as $entry) {
                fputcsv($file, [
                    $entry['timestamp'] ?? '',
                    $entry['action'] ?? '',
                    $entry['user'] ?? '',
                    $entry['description'] ?? '',
                    json_encode($entry['details'] ?? [], JSON_UNESCAPED_UNICODE)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export audit trail to PDF (placeholder - would need PDF library)
     */
    private function exportToPdf(Transfer $transfer, array $auditTrail)
    {
        // Would implement with library like DomPDF or similar
        return response()->json([
            'message' => 'PDF export not implemented yet',
            'transfer_id' => $transfer->id,
            'audit_entries' => count($auditTrail)
        ]);
    }
}
