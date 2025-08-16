<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'address', 'active'
    ];

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function managerUsers()
    {
        return $this->belongsToMany(User::class, 'manager_branch')->withTimestamps();
    }
}
