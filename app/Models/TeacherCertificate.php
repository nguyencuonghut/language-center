<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherCertificate extends Model
{
    protected $table = 'teacher_certificate';

    protected $fillable = [
        'teacher_id',
        'certificate_id',
        'credential_no',
        'issued_by',
        'issued_at',
        'expires_at',
        'file_path',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }
}
