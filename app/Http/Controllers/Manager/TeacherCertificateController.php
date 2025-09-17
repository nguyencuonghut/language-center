<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Certificate;
use App\Http\Requests\Teacher\AttachCertificateRequest;
use Illuminate\Support\Facades\Storage;

class TeacherCertificateController extends Controller
{
    public function attach(AttachCertificateRequest $request, Teacher $teacher)
    {
        $data = $request->validated();

        $pivot = [
            'credential_no' => $data['credential_no'] ?? null,
            'issued_by' => $data['issued_by'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $pivot['file_path'] = $request->file('file')->store('private/certificates','local');
        }

        $teacher->certificates()->syncWithoutDetaching([
            $data['certificate_id'] => $pivot
        ]);

        return back()->with('success','Đã gán chứng chỉ cho giáo viên.');
    }

    public function detach(Teacher $teacher, Certificate $certificate)
    {
        // Lấy thông tin pivot
        $pivot = $teacher->certificates()
            ->where('certificate_id', $certificate->id)
            ->first()?->pivot;

        if ($pivot && $pivot->file_path) {
            Storage::disk('local')->delete($pivot->file_path);
        }

        $teacher->certificates()->detach($certificate->id);

        return back()->with('success','Đã bỏ gán chứng chỉ.');
    }
}
