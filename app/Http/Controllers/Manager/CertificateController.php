<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use App\Http\Requests\Certificate\StoreCertificateRequest;
use App\Http\Requests\Certificate\UpdateCertificateRequest;
use Inertia\Inertia;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $q = Certificate::query()
            ->when($request->filled('keyword'), function ($qq) use ($request) {
                $kw = '%'.$request->keyword.'%';
                $qq->where(function ($w) use ($kw) {
                    $w->where('name','like',$kw)
                      ->orWhere('code','like',$kw)
                      ->orWhere('description','like',$kw);
                });
            })
            ->orderBy('name');

        $certs = $q->paginate(12)->withQueryString();

        return Inertia::render('Manager/Certificates/Index', [
            'certificates' => $certs,
            'filters' => ['keyword' => (string) $request->keyword],
        ]);
    }

    public function create()
    {
        return Inertia::render('Manager/Certificates/Create');
    }

    public function store(StoreCertificateRequest $request)
    {
        Certificate::create($request->validated());
        return redirect()->route('manager.certificates.index')->with('success','Tạo chứng chỉ thành công.');
    }

    public function edit(Certificate $certificate)
    {
        return Inertia::render('Manager/Certificates/Edit', [
            'certificate' => $certificate->only(['id','code','name','description']),
        ]);
    }

    public function update(UpdateCertificateRequest $request, Certificate $certificate)
    {
        $certificate->update($request->validated());
        return redirect()->route('manager.certificates.edit', $certificate->id)->with('success','Cập nhật chứng chỉ thành công.');
    }

    public function destroy(Certificate $certificate)
    {
        // Kiểm tra ràng buộc
        if ($certificate->teachers()->exists()) return back()->with('error','Không thể xóa: chứng chỉ đã gán cho giáo viên.');
        $certificate->delete();
        return redirect()->route('manager.certificates.index')->with('success','Đã xóa chứng chỉ.');
    }
}
