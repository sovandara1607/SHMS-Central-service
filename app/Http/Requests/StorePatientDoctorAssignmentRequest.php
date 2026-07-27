<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's PatientAssignmentController::storeDoctor() rules exactly. */
class StorePatientDoctorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'doctor_id' => 'required|string|exists:doctor,doctor_id',
            'role'      => 'nullable|in:main_doctor,consultant,specialist',
        ];
    }
}
