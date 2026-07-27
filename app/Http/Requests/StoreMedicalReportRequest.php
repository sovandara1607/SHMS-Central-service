<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|string|in:Progress Report,Discharge Summary,Referral Letter,Diagnostic Summary,Consultation Report',
            'report_content' => 'required|string',
        ];
    }
}
