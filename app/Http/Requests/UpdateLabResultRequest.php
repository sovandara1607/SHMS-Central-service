<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's LabController::updateResult() rules exactly. */
class UpdateLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'result_value'  => 'required|string',
            'result_status' => 'required|string',
            'remarks'       => 'nullable|string',
        ];
    }
}
