<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVitalSignForRecordRequest;
use App\Http\Requests\StoreVitalSignRequest;
use App\Models\MedicalRecord;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VitalSignsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vitals = DB::table('vital_signs as v')
            ->join('patient as p', 'p.patient_id', '=', 'v.patient_id')
            ->orderByDesc('v.recorded_at')
            ->selectRaw("v.*, (p.first_name||' '||p.last_name) as patient_name")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'data' => $vitals->items(),
            'meta' => [
                'current_page' => $vitals->currentPage(),
                'last_page'    => $vitals->lastPage(),
                'total'        => $vitals->total(),
                'per_page'     => $vitals->perPage(),
            ],
        ]);
    }

    public function store(StoreVitalSignRequest $request): JsonResponse
    {
        $vital = VitalSign::create($request->validated() + [
            'recorded_by' => $request->input('recorded_by'),
        ]);

        return response()->json($vital, 201);
    }

    /**
     * Vitals recorded from within a medical record's detail view. Like
     * MedicalReportsApiController/MedicalProceduresApiController, patient_id
     * is derived from the record itself rather than trusted from the
     * client — the flat store() above trusts a client-supplied patient_id
     * because there's no record to anchor to, but here nothing should be
     * able to attach a vital sign to the wrong patient by tampering with
     * the request.
     */
    public function storeForRecord(StoreVitalSignForRecordRequest $request, string $recordId): JsonResponse
    {
        $record = MedicalRecord::findOrFail($recordId);
        $data = $request->validated();

        $vital = VitalSign::create([
            'patient_id' => $record->patient_id,
            'medical_record_id' => $record->medical_record_id,
            'temperature' => $data['temperature'] ?? null,
            'blood_pressure' => $data['blood_pressure'] ?? null,
            'heart_rate' => $data['heart_rate'] ?? null,
            'height' => $data['height'] ?? null,
            'weight' => $data['weight'] ?? null,
            'recorded_by' => $request->input('recorded_by'),
        ]);

        return response()->json($vital, 201);
    }
}
