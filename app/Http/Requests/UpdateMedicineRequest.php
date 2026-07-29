<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's PharmacyController::updateMedicine() rules exactly. */
class UpdateMedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'medicine_name' => 'required|string|max:100',
            'medicine_type' => 'nullable|string|max:50',
            'manufacturer'  => 'nullable|string|max:100',
            'unit_price'    => 'nullable|numeric',
            'stock_quantity' => 'nullable|integer',
            'status'        => 'nullable|in:available,unavailable',
        ];
    }
}
