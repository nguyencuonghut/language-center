<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'code', 'name', 'capacity', 'active'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function classSessions()
    {
        return $this->hasMany(ClassSession::class);
    }
}
