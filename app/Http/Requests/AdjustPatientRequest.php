<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same field set as StorePatientRequest, but nothing here is applied
 * to the patient row directly — it's logged as a patient_adjustment entry.
 * Insurance fields are the exception: those still apply normally.
 */
class AdjustPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'gender'     => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'phone_number'  => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:100',
            'address'       => 'nullable|string|max:255',
            'blood_type'    => 'nullable|string|max:5',
            'allergy'       => 'nullable|string',
            'emergency_contact_name'  => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'patient_status' => 'nullable|in:active,admitted,icu,discharged,inactive',
            'insurance_provider' => 'nullable|string|max:100',
            'policy_number'      => 'nullable|string|max:100',
            'coverage_details'   => 'nullable|string',
            'policy_start'       => 'nullable|date',
            'policy_end'         => 'nullable|date',
            'reason' => 'required|string',
        ];
    }
}
