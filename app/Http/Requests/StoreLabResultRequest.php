<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's LabController::enterResult() rules exactly. */
class StoreLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'test_order_id' => 'required|exists:lab_test_order,test_order_id',
            'result_value'  => 'required|string',
            'result_status' => 'required|string',
            'remarks'       => 'nullable|string',
        ];
    }
}
