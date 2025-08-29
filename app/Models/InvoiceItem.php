<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public const TYPES = [
        'tuition' => 'Học phí',
        'adjust' => 'Điều chỉnh',
        'transfer_out' => 'Chuyển ra',
        'transfer_in' => 'Chuyển vào',
        'refund' => 'Hoàn tiền',
    ];

    protected $fillable = [
        'invoice_id', 'type', 'description', 'qty', 'unit_price', 'amount'
    ];

    protected $appends = ['type_label'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the Vietnamese label for the invoice item type
     *
     * @return string
     */
    public function getTypeLabelAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
