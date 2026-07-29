<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's ProcedureController::store() rules exactly. */
class StoreMedicalProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'procedure_name' => 'required|string|max:100',
            'procedure_details' => 'nullable|string',
            'outcome' => 'nullable|string',
            'procedure_date' => 'nullable|date',
        ];
    }
}
