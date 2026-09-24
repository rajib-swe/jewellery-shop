<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingsResource;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;

class SettingsController extends Controller
{
    public function show(SettingsService $settings): SettingsResource
    {
        return SettingsResource::make($settings->all());
    }

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
