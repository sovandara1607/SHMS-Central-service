<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicalProcedureRequest;
use App\Models\MedicalProcedure;
use App\Models\MedicalRecord;
use Illuminate\Http\JsonResponse;

/**
 * Doctor-documented procedures. Like Prescriptions/Reports, the create form
 * hangs off the medical record it belongs to, so this only needs a store
 * endpoint.
 */
class MedicalProceduresApiController extends Controller
{
    public function store(StoreMedicalProcedureRequest $request, string $recordId): JsonResponse
    {
        $record = MedicalRecord::findOrFail($recordId);
        $data = $request->validated();

        $procedure = MedicalProcedure::create([
            'medical_record_id' => $record->medical_record_id,
            'patient_id' => $record->patient_id,
            'doctor_id' => $record->doctor_id,
            'procedure_name' => $data['procedure_name'],
            'procedure_details' => $data['procedure_details'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'procedure_date' => $data['procedure_date'] ?? now()->toDateString(),
        ]);

        return response()->json($procedure->fresh(), 201);
    }
}
