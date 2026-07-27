<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's PharmacyController::updateBatch() rules exactly. */
class UpdateMedicineBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'manufacture_date' => 'nullable|date',
            'expiry_date'      => 'nullable|date',
            'quantity'         => 'required|integer|min:0',
            'status'           => 'nullable|in:valid,expired,damaged',
        ];
    }
}
