<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the central-service.key middleware, not per-user auth
    }

    public function rules(): array
    {
        return [
            'shift_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'shift_type' => 'required|in:morning,afternoon,night',
            'status' => 'required|in:scheduled,completed,cancelled,on_leave',
        ];
    }
}
