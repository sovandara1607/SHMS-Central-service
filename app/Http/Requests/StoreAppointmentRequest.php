<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's AppointmentController::validateData() rules exactly. */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'patient_id'       => 'required|exists:patient,patient_id',
            'doctor_id'        => 'required|exists:doctor,doctor_id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'reason'           => 'nullable|string',
        ];
    }
}
