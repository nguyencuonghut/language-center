<?php

namespace App\Services;

use App\Models\StudentLedgerEntry;
use Illuminate\Support\Arr;

class StudentLedger
{
    /**
     * Ghi một dòng NỢ (tăng công nợ)
     */
    public static function debit(array $payload): StudentLedgerEntry
    {
        $data = self::normalize($payload) + ['debit' => (float)($payload['amount'] ?? 0), 'credit' => 0];
        return self::upsertByRef($data);
    }

    /**
     * Ghi một dòng CÓ (giảm công nợ)
     */
    public static function credit(array $payload): StudentLedgerEntry
    {
        $data = self::normalize($payload) + ['debit' => 0, 'credit' => (float)($payload['amount'] ?? 0)];
        return self::upsertByRef($data);
    }

    /**
     * Chuẩn hoá input
     * Required keys: student_id, entry_date(Y-m-d), type, ref_type, ref_id, amount
     */
    protected static function normalize(array $p): array
    {
        return [
            'student_id' => (int) $p['student_id'],
            'entry_date' => (string) ($p['entry_date'] ?? now()->toDateString()),
            'type'       => (string) $p['type'],          // 'invoice','payment','adjustment',...
            'ref_type'   => $p['ref_type'] ?? null,       // 'invoices','payments',...
            'ref_id'     => $p['ref_id']   ?? null,
            'note'       => $p['note']     ?? null,
            'meta'       => Arr::get($p, 'meta', null),
        ];
    }

    /**
     * Tránh ghi trùng theo (ref_type, ref_id).
     * Nếu chưa có → create. Nếu đã có → update (để idempotent).
     */
    protected static function upsertByRef(array $data): StudentLedgerEntry
    {
        if ($data['ref_type'] && $data['ref_id']) {
            return tap(
                StudentLedgerEntry::firstOrNew([
                    'ref_type' => $data['ref_type'],
                    'ref_id'   => $data['ref_id'],
                ])
            , function ($entry) use ($data) {
                $entry->fill($data)->save();
            });
        }

        // Không có ref → luôn tạo mới
        return StudentLedgerEntry::create($data);
    }
}
