<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's PatientAssignmentController::storeNurse() rules exactly. */
class StorePatientNurseAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'nurse_id' => 'required|string|exists:nurse,nurse_id',
            'shift_id' => 'nullable|string|exists:staff_shift,shift_id',
        ];
    }
}
