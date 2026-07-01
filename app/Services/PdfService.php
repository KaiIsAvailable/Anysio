<?php
namespace App\Services;

use Spatie\Browsershot\Browsershot;
use App\Services\FileService;

class PdfService
{
    protected $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function generateInvoicePdf(string $html, int $userId, string $fileName): string
    {
        // 1. 获取 FileService 规划好的安全路径
        // 假设 category 是 'user_receipt'
        $directory = $this->fileService->getPath('user_invoice', (string)$userId);
        $fullPath = $directory . '/' . $fileName;

        // 2. 确保目录存在
        // 注意：Browsershot 保存时需要完整物理路径
        $storagePath = storage_path('app/' . $fullPath);
        $directoryPath = dirname($storagePath);
        
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        // 3. 生成 PDF
        Browsershot::html($html)
            ->format('A4')
            ->showBackground()
            ->save($storagePath);

        // 4. 返回在 FileService 体系下的标准相对路径
        return $fullPath; 
    }
}