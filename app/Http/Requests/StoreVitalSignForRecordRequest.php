<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors Database-final's ClinicalController::storeForRecord() rules
 * exactly. Deliberately has no patient_id/medical_record_id fields, unlike
 * StoreVitalSignRequest — both are derived from the {recordId} route
 * parameter in VitalSignsApiController::storeForRecord() itself, not
 * trusted from the client, so requiring them here would reject every
 * legitimate request (the client never sends them for this route) while
 * adding no real validation value.
 */
class StoreVitalSignForRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'temperature'    => 'nullable|numeric',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate'     => 'nullable|integer',
            'height'         => 'nullable|numeric',
            'weight'         => 'nullable|numeric',
        ];
    }
}
