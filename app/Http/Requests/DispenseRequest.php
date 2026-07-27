<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's PharmacyController::dispense() rules exactly. */
class DispenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'prescription_id' => 'required|exists:prescription,prescription_id',
            'patient_id'      => 'required|exists:patient,patient_id',
        ];
    }
}
