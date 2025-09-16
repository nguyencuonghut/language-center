<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Certificate extends Model
{
    protected $fillable = ['code','name','description'];

    public function teachers(): BelongsToMany {
        return $this->belongsToMany(Teacher::class)
            ->withTimestamps()
            ->withPivot(['credential_no','issued_by','issued_at','expires_at','file_path']);
    }
}
