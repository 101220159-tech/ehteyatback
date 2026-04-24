<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;

trait HandlesImageUpload
{
    /**
     * Convert any uploaded file (image or PDF) to a base64 data URI.
     * The result can be stored directly in a LONGTEXT column and used
     * as an <img src="..."> or embedded PDF by the frontend without
     * needing access to local storage.
     */
    protected function fileToDataUri(UploadedFile $file): string
    {
        $mime   = $file->getMimeType() ?? 'application/octet-stream';
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        return "data:{$mime};base64,{$base64}";
    }
}
