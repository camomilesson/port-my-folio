<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ProcessGalleryImage
{
    /** @return array{bytes: string, width: int, height: int} */
    public function handle(UploadedFile $file): array
    {
        $bytes = file_get_contents($file->getRealPath());
        $size = @getimagesizefromstring($bytes);
        if (! $size || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['image' => 'Upload a valid JPEG, PNG, or WebP image.']);
        }
        [$width, $height] = $size;
        if ($width > 12000 || $height > 12000 || $width * $height > 24000000) {
            throw ValidationException::withMessages(['image' => 'Images must be at most 12,000 pixels per side and 24 megapixels in total.']);
        }
        if ($this->isAnimated($bytes, $size['mime'])) {
            throw ValidationException::withMessages(['image' => 'Animated images are not supported. Upload a still image.']);
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'This image could not be decoded. Try exporting it again.']);
        }
        try {
            if ($size['mime'] === 'image/jpeg') {
                $exif = @exif_read_data($file->getRealPath());
                $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($source, IMG_FLIP_HORIZONTAL);
                }
                $degrees = match ($orientation) {
                    3, 4 => 180, 6, 7 => -90, 5, 8 => 90, default => 0
                };
                if ($degrees !== 0) {
                    $rotated = imagerotate($source, $degrees, 0);
                    imagedestroy($source);
                    $source = $rotated;
                }
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, 2400 / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            ob_start();
            try {
                if (! imagewebp($target, null, 85)) {
                    throw new \RuntimeException('Unable to encode gallery image.');
                }
                $processed = ob_get_contents();
            } finally {
                ob_end_clean();
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
        if (strlen($processed) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['image' => 'The processed image is too large. Try a smaller image.']);
        }

        return ['bytes' => $processed, 'width' => $targetWidth, 'height' => $targetHeight];
    }

    private function isAnimated(string $bytes, string $mime): bool
    {
        if (! in_array($mime, ['image/png', 'image/webp'], true)) {
            return false;
        }
        $offset = $mime === 'image/png' ? 8 : 12;
        $length = strlen($bytes);
        while ($offset + 8 <= $length) {
            $type = substr($bytes, $offset + ($mime === 'image/png' ? 4 : 0), 4);
            if (($mime === 'image/png' && $type === 'acTL') || ($mime === 'image/webp' && in_array($type, ['ANIM', 'ANMF'], true))) {
                return true;
            }
            $chunkSize = unpack($mime === 'image/png' ? 'N' : 'V', substr($bytes, $offset + ($mime === 'image/png' ? 0 : 4), 4))[1];
            $offset += $mime === 'image/png' ? 12 + $chunkSize : 8 + $chunkSize + ($chunkSize % 2);
        }

        return false;
    }
}
