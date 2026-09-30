<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackupResource;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backups): JsonResponse
    {
        return response()->json([
            'data' => BackupResource::collection($backups->all())->resolve(),
            'meta' => [
                'keep_days' => (int) config('backup.keep_days'),
            ],
        ]);
    }

    public function store(BackupService $backups): JsonResponse
    {
        $backup = $backups->run();

        return response()->json([
            'data' => [
                'name' => $backup['name'],
                'size' => $backup['size'],
                'created_at' => $backup['created_at'],
            ],
            'meta' => [
                'tables' => $backup['tables'],
                'rows' => $backup['rows'],
                'message' => 'Backup completed.',
            ],
        ]);
    }

    public function download(string $name, BackupService $backups): BinaryFileResponse|JsonResponse
    {
        $path = $backups->find($name);

        if ($path === null) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(string $name, BackupService $backups): JsonResponse|Response
    {
        $path = $backups->find($name);

        if ($path === null) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        File::delete($path);

        return response()->noContent();
    }
}
