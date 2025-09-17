<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileController extends Controller
{
    /**
     * Nhận ?path=private/teachers/xxx.jpg
     * -> Tạo temporary signed URL tới route('files.view') trong 5 phút
     * -> Redirect 302 sang URL đã ký
     */
    public function signed(Request $request)
    {
        $path = (string) $request->query('path', '');

        // Chuẩn hoá & kiểm tra path
        $path = ltrim($path, '/');
        if ($path === '' || Str::contains($path, ['..', '\\'])) {
            abort(400, 'Invalid path');
        }

        // Giới hạn thư mục private cho an toàn (tuỳ bạn mở rộng)
        if (! Str::startsWith($path, 'private/')) {
            abort(403, 'Forbidden');
        }

        // Kiểm tra tồn tại
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        // TODO (tuỳ chọn): kiểm tra Policy theo người dùng hiện tại & loại file
        // ví dụ: chỉ Admin/Manager xem được ảnh teacher bất kỳ, Teacher chỉ xem ảnh của mình

        // Mã hoá path để không lộ trực tiếp
        $token = Crypt::encryptString($path);

        // Tạo URL đã ký, hết hạn sau 5 phút
        $url = URL::temporarySignedRoute(
            'files.view',
            now()->addMinutes(5),
            ['p' => $token]
        );

        return redirect()->away($url);
    }

    /**
     * Nhận ?p=<encrypted> (đã ký)
     * -> Giải mã, kiểm tra lại & stream file
     */
    public function view(Request $request): StreamedResponse
    {
        $token = (string) $request->query('p', '');

        try {
            $path = Crypt::decryptString($token);
        } catch (\Throwable $e) {
            abort(400, 'Invalid token');
        }

        $path = ltrim($path, '/');
        if ($path === '' || Str::contains($path, ['..', '\\'])) {
            abort(400, 'Invalid path');
        }
        if (! Str::startsWith($path, 'private/')) {
            abort(403, 'Forbidden');
        }
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        // Tuỳ chọn kiểm tra Policy tại đây (giống ở signed())

        // Xác định mime
        $mime = Storage::disk('local')->mimeType($path) ?? 'application/octet-stream';
        $filename = basename($path);

        // Stream file (inline). Đổi thành download nếu muốn: response()->download()
        return Storage::disk('local')->response($path, $filename, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
