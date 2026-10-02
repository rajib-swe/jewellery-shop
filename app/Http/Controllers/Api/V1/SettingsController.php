<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingsResource;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Settings', description: 'Shop settings endpoints')]
class SettingsController extends Controller
{
    #[OA\Get(
        path: '/settings',
        summary: 'Get settings',
        description: 'Return the current shop settings',
        tags: ['Settings'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Shop settings',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(SettingsService $settings): SettingsResource
    {
        return SettingsResource::make($settings->all());
    }

    #[OA\Put(
        path: '/settings',
        summary: 'Update settings',
        description: 'Update shop settings',
        tags: ['Settings'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'shop_name', type: 'string', example: 'Gold & Silver Jewellers'),
                    new OA\Property(property: 'address', type: 'string', example: '123 Main Street'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'shop@example.com'),
                    new OA\Property(property: 'tax_rate', type: 'number', example: 18.0),
                    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settings updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Settings updated.'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(
        UpdateSettingsRequest $request,
        SettingsService $settings,
    ): SettingsResource {
        $values = $request->validated();
        $logo = $request->file('shop_logo');

        unset($values['shop_logo'], $values['remove_shop_logo']);

        $updatedSettings = $settings->update(
            $values,
            $logo instanceof UploadedFile ? $logo : null,
            $request->boolean('remove_shop_logo'),
        );

        return SettingsResource::make($updatedSettings)->additional([
            'meta' => [
                'message' => 'Settings updated.',
            ],
        ]);
    }
}
