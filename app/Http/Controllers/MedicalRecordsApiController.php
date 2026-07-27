<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustMedicalRecordRequest;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAdjustment;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MedicalRecordsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $doctorId = $request->query('doctor_id');
        $patientId = $request->query('patient_id');
        $date = $request->query('date');
        $perPage = (int) $request->query('per_page', 20);

        $records = DB::table('medical_record as mr')
            ->join('patient as p', 'p.patient_id', '=', 'mr.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'mr.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('staff as cb', 'cb.staff_id', '=', 'mr.created_by')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('mr.medical_record_id', 'ilike', $like)
                    ->orWhere('mr.patient_id', 'ilike', $like)
                    ->orWhereRaw("(p.first_name||' '||p.last_name) ilike ?", [$like])
                    ->orWhereRaw("(s.first_name||' '||s.last_name) ilike ?", [$like]);
            })
            ->when($doctorId, fn ($query) => $query->where('mr.doctor_id', $doctorId))
            ->when($patientId, fn ($query) => $query->where('mr.patient_id', $patientId))
            ->when($date, fn ($query) => $query->whereDate('mr.created_at', $date))
            ->orderByDesc('mr.created_at')
            ->selectRaw("mr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, (cb.first_name||' '||cb.last_name) as created_by_name")
            ->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
                'total'        => $records->total(),
                'per_page'     => $records->perPage(),
            ],
        ]);
    }

    public function show(string $medicalRecord): JsonResponse
    {
        $record = MedicalRecord::with([
            'patient', 'doctor.staff',
            'adjustments' => fn ($q) => $q->orderByDesc('adjusted_at'),
            'prescriptions' => fn ($q) => $q->with('items.medicine')->orderByDesc('prescription_date'),
            'reports' => fn ($q) => $q->orderByDesc('generated_at'),
        ])->findOrFail($medicalRecord);

        return response()->json($this->present($record));
    }

    public function store(StoreMedicalRecordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $vitalFields = ['temperature', 'blood_pressure', 'heart_rate', 'height', 'weight'];
        $vitals = collect($data)->only($vitalFields)->filter()->all();
        $recordData = collect($data)->except($vitalFields)->all();
        $recordData['created_by'] = $request->input('created_by');
        $record = MedicalRecord::create($recordData);

        if (! empty($vitals)) {
            VitalSign::create($vitals + [
                'patient_id' => $record->patient_id,
                'medical_record_id' => $record->medical_record_id,
                'recorded_by' => $request->input('created_by'),
            ]);
        }

        return response()->json($this->present($record->load(
            'patient', 'doctor.staff', 'adjustments', 'prescriptions.items.medicine', 'reports'
        )), 201);
    }

    public function adjust(AdjustMedicalRecordRequest $request, string $medicalRecord): JsonResponse
    {
        $record = MedicalRecord::findOrFail($medicalRecord);
        $data = $request->validated();

        // The original is never overwritten; the amendment is appended.
        MedicalRecordAdjustment::create([
            'adjustment_id' => 'ADJ' . strtoupper(Str::random(8)),
            'medical_record_id' => $record->medical_record_id,
            'symptoms' => $data['symptoms'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'treatment_notes' => $data['treatment_notes'] ?? null,
            'adjusted_by' => $request->input('adjusted_by'),
            'reason' => $data['reason'],
        ]);

        return response()->json($this->present($record->fresh(
            'patient', 'doctor.staff', 'adjustments', 'prescriptions.items.medicine', 'reports'
        )));
    }

    private function present(MedicalRecord $record): array
    {
        return [
            ...$record->only([
                'medical_record_id', 'patient_id', 'doctor_id', 'appointment_id',
                'symptoms', 'diagnosis', 'treatment_notes', 'created_by', 'created_at',
            ]),
            'patient' => $record->patient?->only([
                'patient_id', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone_number',
            ]),
            'doctor_name' => $record->doctor?->name(),
            'adjustments' => $record->adjustments->map(fn ($a) => $a->only([
                'adjustment_id', 'medical_record_id', 'symptoms', 'diagnosis',
                'treatment_notes', 'adjusted_by', 'adjusted_at', 'reason',
            ]))->values(),
            'prescriptions' => $record->prescriptions->map(fn ($pr) => [
                ...$pr->only(['prescription_id', 'medical_record_id', 'patient_id', 'doctor_id', 'prescription_date', 'notes']),
                'items' => $pr->items->map(fn ($item) => [
                    ...$item->only(['prescription_item_id', 'prescription_id', 'medicine_id', 'dosage', 'frequency', 'duration', 'quantity']),
                    'medicine_name' => $item->medicine?->medicine_name,
                ])->values(),
            ])->values(),
            'reports' => $record->reports->map(fn ($r) => $r->only([
                'report_id', 'medical_record_id', 'patient_id', 'report_type',
                'report_content', 'generated_by', 'generated_at',
            ]))->values(),
        ];
    }
}
