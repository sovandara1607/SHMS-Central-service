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
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'reason'           => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $date = $this->input('appointment_date');
            $time = $this->input('appointment_time');
            if (! $date || ! $time) {
                return;
            }
            if ($date === now()->toDateString() && $time < now()->format('H:i')) {
                $validator->errors()->add('appointment_time', 'The appointment time cannot be in the past.');
            }
        });
    }
}
