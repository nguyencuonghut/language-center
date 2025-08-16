<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'type', 'description', 'qty', 'unit_price', 'amount'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
