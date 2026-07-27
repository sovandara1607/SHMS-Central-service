<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's RoomAssignmentController::storeBed() rules exactly. */
class StoreBedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'bed_number' => 'nullable|string|max:100',
        ];
    }
}
