<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrescriptionRequest;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Doctor-side prescribing. Mirrors Database-final's PrescriptionController —
 * the form is embedded in the medical record show view, so this only needs
 * a store endpoint.
 */
class PrescriptionsApiController extends Controller
{
    public function store(StorePrescriptionRequest $request, string $recordId): JsonResponse
    {
        $record = MedicalRecord::findOrFail($recordId);
        $data = $request->validated();

        $prescription = DB::transaction(function () use ($record, $data) {
            $prescription = Prescription::create([
                'medical_record_id' => $record->medical_record_id,
                'patient_id' => $record->patient_id,
                'doctor_id' => $record->doctor_id,
                'prescription_date' => now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                PrescriptionItem::create([
                    'prescription_id' => $prescription->prescription_id,
                    'medicine_id' => $item['medicine_id'],
                    'dosage' => $item['dosage'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                ]);
            }

            return $prescription;
        });

        return response()->json([
            'prescription_id' => $prescription->prescription_id,
            'medical_record_id' => $record->medical_record_id,
        ], 201);
    }
}
