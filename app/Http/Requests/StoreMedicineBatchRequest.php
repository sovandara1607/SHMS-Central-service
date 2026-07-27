<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's PharmacyController::storeBatch() rules exactly. */
class StoreMedicineBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'medicine_id'      => 'required|exists:medicine,medicine_id',
            'batch_number'     => 'nullable|string|max:100',
            'manufacture_date' => 'nullable|date',
            'expiry_date'      => 'nullable|date',
            'quantity'         => 'required|integer|min:0',
            'status'           => 'nullable|in:valid,expired,damaged',
        ];
    }
}
