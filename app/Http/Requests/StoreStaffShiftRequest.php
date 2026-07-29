<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'staff_id' => 'required|exists:staff,staff_id',
            'shift_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'shift_type' => 'required|in:morning,afternoon,night',
        ];
    }
}
