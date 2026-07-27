<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's MedicalRecordController::store() rules exactly. */
class StoreMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patient,patient_id',
            'doctor_id'  => 'required|exists:doctor,doctor_id',
            'appointment_id' => 'nullable|exists:appointment,appointment_id',
            'symptoms'   => 'nullable|string',
            'diagnosis'  => 'required|string',
            'treatment_notes' => 'nullable|string',
            'temperature'    => 'nullable|numeric',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate'     => 'nullable|integer',
            'height'         => 'nullable|numeric',
            'weight'         => 'nullable|numeric',
        ];
    }
}
