<?php

namespace App\Models;

// app/Models/StudentLedgerEntry.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLedgerEntry extends Model
{
    protected $fillable = [
        'student_id',
        'entry_date',
        'type',
        'ref_type',
        'ref_id',
        'debit',
        'credit',
        'note',
        'meta'
    ];

    protected $casts = [
        'entry_date'=>'date',
        'meta'=>'array',
        'debit'=>'decimal:2',
        'credit'=>'decimal:2'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

