<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AdminBackupStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $name = (string) config('backup.backup.name');
        $disk = Storage::disk($diskName);

        $files = collect($disk->exists($name) ? $disk->files($name) : [])
            ->filter(fn (string $f) => str_ends_with($f, '.zip'))
            ->sort()
            ->values();

        $latest = $files->last();

        return response()->json([
            'disk' => $diskName,
            'backup_directory' => $name,
            'backup_count' => $files->count(),
            'latest_backup' => $latest,
        ]);
    }
}
