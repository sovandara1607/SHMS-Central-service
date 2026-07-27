<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's LabController::updateOrderStatus() rules exactly. */
class UpdateLabOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ];
    }
}
