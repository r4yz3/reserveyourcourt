<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OptimizeReceipt
{
    public function __construct(private OptimizeImage $images) {}

    public function handle(UploadedFile $file, int $businessId): string
    {
        $image = $this->images->encode($file);
        $path = 'receipts/'.$businessId.'/'.Str::uuid().'.webp';
        if (! Storage::disk(config('filesystems.receipts_disk'))->put($path, $image['bytes'])) {
            throw new \RuntimeException('Receipt storage failed.');
        }

        return $path;
    }
}
