<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class OptimizeImage
{
    public function encode(UploadedFile $file, int $maxEdge = 2400, int $quality = 92, string $field = 'receipt'): array
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] * $info[1] > 20000000 || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages([$field => 'Use a valid JPEG, PNG or WebP image under 20 megapixels.']);
        }
        abort_unless(function_exists('imagewebp') && function_exists('imagecreatefromstring'), 503, 'Image optimization requires PHP GD with WebP support. Ask the server administrator to enable it.');
        $source = @imagecreatefromstring($file->getContent());
        if (! $source) {
            throw ValidationException::withMessages([$field => 'This image cannot be decoded. Please upload another receipt.']);
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) {
                3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
            };
            if ($angle) {
                $source = imagerotate($source, $angle, 0);
            }
        }
        $ratio = min(1, $maxEdge / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $ratio));
        $height = max(1, (int) round(imagesy($source) * $ratio));
        $output = imagecreatetruecolor($width, $height);
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        try {
            $success = imagewebp($output, null, $quality);
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (! $success || ! $bytes) {
            throw ValidationException::withMessages([$field => 'Image optimization failed. Please try another image.']);
        }

        return ['bytes' => $bytes, 'width' => $width, 'height' => $height];
    }
}
