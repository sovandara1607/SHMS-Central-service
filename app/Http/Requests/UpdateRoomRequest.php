<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mirrors Database-final's AdminController::validateRoom() rules exactly. */
class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'department_id' => 'nullable|exists:department,department_id',
            'room_number'   => 'nullable|string|max:100',
            'room_type'     => 'nullable|in:general,private,icu,emergency,operating_room',
            'floor_number'  => 'nullable|integer',
            'status'        => 'nullable|in:available,occupied,maintenance',
            'rate_per_day'  => 'nullable|numeric',
        ];
    }
}
