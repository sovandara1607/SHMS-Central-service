<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's SettingsController::update() validation rules exactly. */
class UpdateHospitalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'hospital_name'     => 'nullable|string|max:150',
            'hospital_code'     => 'nullable|string|max:50',
            'license_number'    => 'nullable|string|max:100',
            'address'           => 'nullable|string|max:255',
            'phone_number'      => 'nullable|string|max:50',
            'email'             => 'nullable|email|max:100',
            'website'           => 'nullable|string|max:150',
            'established_year'  => 'nullable|string|max:10',
            'hours_weekday'     => 'nullable|string|max:50',
            'hours_saturday'    => 'nullable|string|max:50',
            'hours_sunday'      => 'nullable|string|max:50',
            'hours_emergency'   => 'nullable|string|max:50',
            'total_beds'        => 'nullable|integer|min:0',
            'icu_beds'          => 'nullable|integer|min:0',
            'emergency_bays'    => 'nullable|integer|min:0',
            'operating_rooms'   => 'nullable|integer|min:0',
        ];
    }
}
