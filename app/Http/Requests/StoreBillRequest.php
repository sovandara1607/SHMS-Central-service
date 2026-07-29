<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's BillingController::store() rules exactly. */
class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'patient_id'     => 'required|exists:patient,patient_id',
            'appointment_id' => 'nullable|exists:appointment,appointment_id',
            'items'                    => 'nullable|array',
            'items.*.item_type'        => 'required_with:items|in:service,medicine,lab_test,procedure,room',
            'items.*.description'      => 'nullable|string|max:255',
            'items.*.quantity'         => 'required_with:items|integer|min:1',
            'items.*.unit_price'       => 'required_with:items|numeric|min:0',
        ];
    }
}
