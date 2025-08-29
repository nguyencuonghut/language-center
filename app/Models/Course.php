<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'audience', 'language', 'active'
    ];

    /**
     * Map display language names to language codes
     */
    protected static $languageMap = [
        'tiếng anh' => 'en',
        'tiếng trung' => 'zh',
        'tiếng hàn' => 'ko',
        'tiếng nhật' => 'ja',
    ];

    /**
     * Scope a query to search through course fields.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        if (!empty($search)) {
            $search = trim($search);
            return $query->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('audience', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }
}
