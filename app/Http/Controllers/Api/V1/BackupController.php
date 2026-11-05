<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackupResource;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[OA\Tag(name: 'Backups', description: 'Database backup management endpoints')]
class BackupController extends Controller
{
    #[OA\Get(
        path: '/backups',
        summary: 'List backups',
        description: 'Return a list of all available database backups',
        tags: ['Backups'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of backups',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'keep_days', type: 'integer', example: 7),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(BackupService $backups): JsonResponse
    {
        return response()->json([
            'data' => BackupResource::collection($backups->all())->resolve(),
            'meta' => [
                'keep_days' => (int) config('backup.keep_days'),
            ],
        ]);
    }

    #[OA\Post(
        path: '/backups',
        summary: 'Create backup',
        description: 'Run a new database backup',
        tags: ['Backups'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Backup created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'name', type: 'string', example: 'backup-2024-01-01.sql.gz'),
                            new OA\Property(property: 'size', type: 'integer', example: 102400),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                        ]),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'tables', type: 'integer', example: 15),
                            new OA\Property(property: 'rows', type: 'integer', example: 5000),
                            new OA\Property(property: 'message', type: 'string', example: 'Backup completed.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ]
    )]
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

    #[OA\Get(
        path: '/backups/{name}/download',
        summary: 'Download backup',
        description: 'Download a specific backup file',
        tags: ['Backups'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'name',
                in: 'path',
                required: true,
                description: 'Backup filename',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Backup file download',
                content: new OA\MediaType(mediaType: 'application/gzip')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Backup not found'),
        ]
    )]
    public function download(string $name, BackupService $backups): BinaryFileResponse|JsonResponse
    {
        $path = $backups->find($name);

        if ($path === null) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }

    #[OA\Delete(
        path: '/backups/{name}',
        summary: 'Delete backup',
        description: 'Delete a specific backup file',
        tags: ['Backups'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'name',
                in: 'path',
                required: true,
                description: 'Backup filename',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Backup deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Backup not found'),
        ]
    )]
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
