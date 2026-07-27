<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's LabController::storeOrder() rules exactly. */
class StoreLabOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'patient_id'     => 'required|exists:patient,patient_id',
            'doctor_id'      => 'required|exists:doctor,doctor_id',
            'technician_id'  => 'nullable|exists:lab_technician,technician_id',
            'test_name'      => 'required|string|max:100',
            'priority'       => 'nullable|string',
            'notes'          => 'nullable|string',
        ];
    }
}
