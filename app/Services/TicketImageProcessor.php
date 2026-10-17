<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class TicketImageProcessor
{
    public function upload(UploadedFile $file, string $managerId)
    {
        $fail = fn ($reason) => ValidationException::withMessages(['attachments' => $file->getClientOriginalName().': '.$reason]);
        $extension = strtolower($file->getClientOriginalExtension());
        $limit = config("customer_service.types.$extension.max_kb") * 1024;
        if ($file->getSize() > $limit) throw $fail('maximum 2 MB per image.');
        $info = @getimagesize($file->getRealPath());
        if (!$info || !$info[0] || !$info[1]) throw $fail('unable to read image dimensions.');
        [$width, $height] = $info;
        $pixels = $width * $height;
        $dimension = config('customer_service.image_max_input_dimension');
        if ($width > $dimension || $height > $dimension || $pixels > config('customer_service.image_max_pixels')) {
            throw $fail('image dimensions exceed the safe processing limit (8192px per side, 12 megapixels).');
        }
        // GD only encodes still images. Preserve small animations instead of silently flattening them.
        if ($this->animated($file, $extension)) {
            if (max($width, $height) > config('customer_service.image_max_dimension')) {
                throw $fail('animated images must already be at most 1920px on the longest side.');
            }
            return app(FileService::class)->upload($file, $managerId, 'tenant_ticket');
        }
        // Bound decoded buffers, rotation, resizing and encoder working memory before decoding.
        $memoryLimit = ini_get('memory_limit');
        $available = $memoryLimit === '-1' ? PHP_INT_MAX : $this->bytes($memoryLimit) - memory_get_usage(true);
        if ($pixels * 16 + 32 * 1024 * 1024 > $available) throw $fail('image is too large to process safely.');
        $image = null;
        $resized = null;
        $temporary = null;
        try {
            $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
            if (!$image) throw $fail('image could not be decoded.');
            if (in_array($extension, ['jpg', 'jpeg'], true)) {
                $exif = @exif_read_data($file->getRealPath());
                $orientation = (int) ($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
                if ($orientation === 4) imageflip($image, IMG_FLIP_VERTICAL);
                $angle = match ($orientation) { 3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0 };
                if ($angle) {
                    $rotated = imagerotate($image, $angle, 0);
                    if (!$rotated) throw $fail('image orientation could not be corrected.');
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
            $scale = min(1, config('customer_service.image_max_dimension') / max(imagesx($image), imagesy($image)));
            $resized = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            if (!imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), imagesx($image), imagesy($image))) {
                throw $fail('image could not be resized.');
            }
            $temporary = tempnam(sys_get_temp_dir(), 'ticket-image-');
            if (!$temporary) throw new \RuntimeException('Unable to allocate image temporary file.');
            $written = match ($extension) {
                'jpg', 'jpeg' => imagejpeg($resized, $temporary, 80),
                'png' => imagepng($resized, $temporary, 6),
                'webp' => imagewebp($resized, $temporary, 80),
                'gif' => imagegif($resized, $temporary),
                default => false,
            };
            clearstatcache(true, $temporary);
            if (!$written || !filesize($temporary)) throw $fail('processed image could not be saved.');
            if (filesize($temporary) > $limit) throw $fail('processed image exceeds the 2 MB limit. Please choose a smaller image.');
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
            if (!in_array($mime, config("customer_service.types.$extension.mimes"), true)) throw $fail('processed image format is invalid.');
            return app(FileService::class)->upload(new UploadedFile($temporary, 'image.'.$extension, $mime, null, true), $managerId, 'tenant_ticket');
        } finally {
            if ($image) imagedestroy($image);
            if ($resized) imagedestroy($resized);
            if ($temporary && is_file($temporary)) unlink($temporary);
        }
    }

    private function bytes(string $value): int
    {
        $unit = strtolower(substr(trim($value), -1));
        return (int) $value * match ($unit) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
    }

    private function animated(UploadedFile $file, string $extension): bool
    {
        if (!in_array($extension, ['gif', 'webp'], true)) return false;
        $data = file_get_contents($file->getRealPath());
        if ($extension === 'webp') return substr($data, 12, 4) === 'VP8X' && (ord($data[20] ?? "\0") & 2) !== 0;
        $length = strlen($data);
        $packed = ord($data[10] ?? "\0");
        $offset = 13 + (($packed & 128) ? 3 * (2 ** (($packed & 7) + 1)) : 0);
        $frames = 0;
        while ($offset < $length) {
            $block = ord($data[$offset++]);
            if ($block === 0x3b) break;
            if ($block === 0x2c) {
                if (++$frames > 1) return true;
                if ($offset + 9 > $length) break;
                $packed = ord($data[$offset + 8]);
                $offset += 9 + (($packed & 128) ? 3 * (2 ** (($packed & 7) + 1)) : 0) + 1;
            } elseif ($block === 0x21) {
                $offset++; // Extension label, followed by data sub-blocks.
            } else break;
            while ($offset < $length) {
                $size = ord($data[$offset++]);
                if (!$size) break;
                $offset += $size;
            }
        }
        return false;
    }
}
