<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's MedicalRecordController::adjust() rules exactly. */
class AdjustMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'symptoms'  => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'treatment_notes' => 'nullable|string',
            'reason'    => 'required|string',
        ];
    }
}
