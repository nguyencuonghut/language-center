<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'method', 'paid_at', 'amount', 'ref_no'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
