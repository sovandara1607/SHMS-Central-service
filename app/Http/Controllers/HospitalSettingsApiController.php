<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHospitalSettingsRequest;
use App\Models\HospitalSetting;
use Illuminate\Http\JsonResponse;

class HospitalSettingsApiController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(HospitalSetting::current());
    }

    public function update(UpdateHospitalSettingsRequest $request): JsonResponse
    {
        $settings = HospitalSetting::current();
        $settings->update($request->validated());

        return response()->json($settings->fresh());
    }
}
