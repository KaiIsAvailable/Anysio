<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    protected $disk = 'local';
    protected const PATH_MAP = [
        'tenant_ic'      => 'tenants/ic_photo',
        'tenant_ticket'  => 'tenants/tickets',
        'lease_stamping' => 'leases/stamping_certs',
        'user_receipt'   => 'users/receipts',
        'user_invoice'   => 'users/invoices',
    ];

    public function getPath(string $category, string $userId): string
    {
        $base = self::PATH_MAP[$category] ?? 'others';
        return "uploads/user_{$userId}/{$base}";
    }

    public function upload($file, string $userId, string $category)
    {
        $directory = $this->getPath($category, $userId);
        $filename = Str::uuid() . '.' . ($category === 'tenant_ic' ? $file->extension() : $file->getClientOriginalExtension());

        try {
            $fullPath = $file->storeAs($directory, $filename, $this->disk);
            if (!$fullPath && $category === 'tenant_ticket') {
                Storage::disk($this->disk)->delete($directory.'/'.$filename);
            }
        } catch (\Throwable $error) {
            if ($category === 'tenant_ticket') Storage::disk($this->disk)->delete($directory.'/'.$filename);
            throw $error;
        }
        
        return $fullPath; 
    }

    public function getStreamResponse(string $path)
    {
        // 1. 路径兼容性逻辑：优先检查传入路径，不存在则检查加了 private/ 前缀的旧路径
        if (!Storage::disk($this->disk)->exists($path)) {
            $oldPath = 'private/' . $path;
            $path = Storage::disk($this->disk)->exists($oldPath) ? $oldPath : $path;
        }

        // 2. 最终校验
        if (!Storage::disk($this->disk)->exists($path)) {
            abort(404, 'File not found on server.');
        }

        // 3. 获取 MIME 类型 (避免使用接口未定义的方法，改用原生 fInfo)
        $fullPath = Storage::disk($this->disk)->path($path);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        // 4. 返回流式响应 (高性能，不占用额外内存)
        return response()->stream(function () use ($path) {
            $stream = Storage::disk($this->disk)->readStream($path);
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
        ]);
    }

    // 如果你需要 mimeType (用于预览)
    public function getFileMimeType(?string $path)
    {
        // If path is null or empty, return default immediately
        if (empty($path)) {
            return 'application/octet-stream';
        }

        if (!Storage::disk($this->disk)->exists($path) && Storage::disk($this->disk)->exists('private/'.$path)) {
            $path = 'private/'.$path;
        }
        
        $root = Storage::disk($this->disk)->path('');
        $fullPath = $root . $path;

        if (!file_exists($fullPath)) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    public function delete(string $path)
    {
        if (empty($path)) return false;

        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->delete($path);
        }
        return false;
    }
}
