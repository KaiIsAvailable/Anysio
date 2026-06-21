<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    protected $disk = 'local';
    protected const PATH_MAP = [
        'tenant_ic'      => 'tenants/ic_photo',
        'lease_stamping' => 'leases/stamping_certs',
        'user_receipt'   => 'users/receipts',
    ];

    public function getPath(string $category, string $userId): string
    {
        $base = self::PATH_MAP[$category] ?? 'others';
        return "uploads/user_{$userId}/{$base}";
    }

    public function upload($file, string $userId, string $category)
    {
        $directory = $this->getPath($category, $userId);
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $fullPath = $file->storeAs($directory, $filename, $this->disk);
        
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
    public function getFileMimeType(string $path)
    {
        // 获取磁盘的根路径 (例如 storage/app/private)
        $root = Storage::disk($this->disk)->path('');
        $fullPath = $root . $path;

        // 检查文件是否存在
        if (!file_exists($fullPath)) {
            return 'application/octet-stream'; // 默认值
        }

        // 使用 PHP 原生的 finfo 获取 MIME 类型，这是最底层且最可靠的方式
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