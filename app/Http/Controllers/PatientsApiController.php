<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustPatientRequest;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use App\Models\PatientAdjustment;
use App\Models\PatientInsurance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatientsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');
        $assignedDoctorId = $request->query('assigned_doctor_id');
        $perPage = (int) $request->query('per_page', 20);

        $patients = Patient::query()
            ->select('patient.*')
            ->addSelect(['insurance_provider' => PatientInsurance::selectRaw('insurance_provider')
                ->whereColumn('patient_id', 'patient.patient_id')
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->limit(1),
            ])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('patient_id', 'ilike', $like)
                        ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like])
                        ->orWhere('phone_number', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like);
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('patient_status', $status))
            ->when($assignedDoctorId, function ($query) use ($assignedDoctorId) {
                $query->whereHas('doctorAssignments', fn ($q) => $q
                    ->where('doctor_id', $assignedDoctorId)
                    ->where('status', 'active'));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $patients->items(),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page'    => $patients->lastPage(),
                'total'        => $patients->total(),
                'per_page'     => $patients->perPage(),
            ],
        ]);
    }

    /**
     * Lightweight JSON lookup backing the patient-picker used by
     * appointment/billing/vitals/medical-record forms — never dump the full
     * patient table into a response (it's seeded to 1M+ rows for scale testing).
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $like = '%' . $q . '%';
        $patients = Patient::query()
            ->where('patient_id', 'ilike', $like)
            ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like])
            ->orderBy('last_name')
            ->limit(20)
            ->get(['patient_id', 'first_name', 'last_name', 'patient_status', 'gender', 'date_of_birth', 'phone_number']);

        return response()->json($patients->map(fn (Patient $p) => [
            'id' => $p->patient_id,
            'label' => $p->fullName() . ' (' . $p->patient_id . ')',
            'name' => $p->fullName(),
            'status' => $p->patient_status,
            'gender' => $p->gender,
            'date_of_birth' => $p->date_of_birth,
            'phone_number' => $p->phone_number,
        ]));
    }

    public function show(string $patient): JsonResponse
    {
        $model = Patient::with([
            'insurance',
            'doctorAssignments' => fn ($q) => $q->with('doctor.staff', 'assignedByStaff')->orderByDesc('assigned_at'),
            'nurseAssignments' => fn ($q) => $q->with('nurse.staff', 'shift', 'assignedByStaff')->orderByDesc('assigned_at'),
            'adjustments' => fn ($q) => $q->with('adjustedByStaff')->orderByDesc('adjusted_at'),
        ])->findOrFail($patient);

        return response()->json($this->present($model));
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        [$patientData, $insuranceData] = $this->splitPatientAndInsurance($request->validated());

        $patient = Patient::create($patientData);
        if ($insuranceData['insurance_provider'] ?? null) {
            PatientInsurance::create($insuranceData + ['patient_id' => $patient->patient_id]);
        }

        return response()->json($this->present($patient->load('insurance', 'doctorAssignments', 'nurseAssignments', 'adjustments.adjustedByStaff')), 201);
    }

    /**
     * The patient's demographic/clinical-narrative fields are never
     * overwritten (same "original preserved" policy as medical_record) —
     * submitted values are appended to patient_adjustment as an audit trail
     * instead. `patient_status` is the one exception: it drives live
     * application state elsewhere (dashboard ICU/critical counts, status
     * filters and badges throughout the app), the same way discharge()
     * already updates it directly rather than just logging it — so it's
     * applied to the row here too, for consistency with that. Insurance is
     * also an exception: it's a separate, period-based sub-resource, so it
     * still applies normally.
     */
    public function adjust(AdjustPatientRequest $request, string $patient): JsonResponse
    {
        $model = Patient::findOrFail($patient);
        $data = $request->validated();
        [$patientData, $insuranceData] = $this->splitPatientAndInsurance($data);

        PatientAdjustment::create($patientData + [
            'adjustment_id' => 'PADJ' . strtoupper(Str::random(8)),
            'patient_id' => $model->patient_id,
            'adjusted_by' => $request->input('adjusted_by'),
            'reason' => $data['reason'],
        ]);

        if (! empty($data['patient_status'])) {
            $model->update(['patient_status' => $data['patient_status']]);
        }

        if ($insuranceData['insurance_provider'] ?? null) {
            $insurance = $model->insurance()->latest('start_date')->first() ?? new PatientInsurance(['patient_id' => $model->patient_id]);
            $insurance->fill($insuranceData);
            $insurance->patient_id = $model->patient_id;
            $insurance->save();
        }

        return response()->json($this->present($model->fresh(['insurance', 'doctorAssignments', 'nurseAssignments', 'adjustments.adjustedByStaff'])));
    }

    public function discharge(string $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $patient->update(['patient_status' => 'discharged']);

        // A discharged patient shouldn't still show an active treating
        // doctor/nurse — bed release is handled separately by the caller
        // (releaseRoomForPatient), this closes out the other half of "who's
        // currently responsible for this patient."
        $patient->doctorAssignments()->where('status', 'active')->update(['status' => 'completed', 'ended_at' => now()]);
        $patient->nurseAssignments()->where('status', 'active')->update(['status' => 'completed', 'ended_at' => now()]);

        return response()->json($this->present($patient->fresh(['insurance', 'doctorAssignments', 'nurseAssignments', 'adjustments.adjustedByStaff'])));
    }

    /** @return array{0: array, 1: array} */
    private function splitPatientAndInsurance(array $data): array
    {
        $insurance = [
            'insurance_provider' => $data['insurance_provider'] ?? null,
            'policy_number'      => $data['policy_number'] ?? null,
            'coverage_details'   => $data['coverage_details'] ?? null,
            'start_date'         => $data['policy_start'] ?? null,
            'end_date'           => $data['policy_end'] ?? null,
        ];

        $patient = collect($data)->except(['insurance_provider', 'policy_number', 'coverage_details', 'policy_start', 'policy_end', 'reason'])->all();
        if (empty($patient['patient_status'])) {
            unset($patient['patient_status']);
        }

        return [$patient, $insurance];
    }

    private function present(Patient $patient): array
    {
        return [
            ...$patient->only([
                'patient_id', 'first_name', 'last_name', 'gender', 'date_of_birth',
                'phone_number', 'email', 'address', 'blood_type', 'allergy',
                'emergency_contact_name', 'emergency_contact_phone', 'patient_status',
                'created_at', 'updated_at',
            ]),
            'insurance' => $patient->insurance->map(fn (PatientInsurance $ins) => $ins->only([
                'insurance_id', 'patient_id', 'insurance_provider', 'policy_number',
                'coverage_details', 'start_date', 'end_date',
            ]))->values(),
            'doctor_assignments' => $patient->doctorAssignments->map(fn ($da) => [
                'assignment_id' => $da->assignment_id,
                'patient_id' => $da->patient_id,
                'doctor_id' => $da->doctor_id,
                'role' => $da->role,
                'status' => $da->status,
                'assigned_at' => $da->assigned_at,
                'ended_at' => $da->ended_at,
                'doctor_name' => $da->doctor?->name(),
                'assigned_by_name' => $da->assignedByStaff?->fullName(),
            ])->values(),
            'nurse_assignments' => $patient->nurseAssignments->map(fn ($na) => [
                'assignment_id' => $na->assignment_id,
                'patient_id' => $na->patient_id,
                'nurse_id' => $na->nurse_id,
                'shift_id' => $na->shift_id,
                'status' => $na->status,
                'assigned_at' => $na->assigned_at,
                'ended_at' => $na->ended_at,
                'nurse_name' => $na->nurse?->name(),
                'assigned_by_name' => $na->assignedByStaff?->fullName(),
                'shift' => $na->shift ? [
                    'shift_type' => $na->shift->shift_type,
                    'start_time' => $na->shift->start_time,
                    'end_time' => $na->shift->end_time,
                ] : null,
            ])->values(),
            'adjustments' => $patient->adjustments->map(fn ($a) => [
                ...$a->only([
                    'adjustment_id', 'patient_id', 'first_name', 'last_name', 'gender',
                    'date_of_birth', 'phone_number', 'email', 'address', 'blood_type',
                    'allergy', 'emergency_contact_name', 'emergency_contact_phone',
                    'patient_status', 'adjusted_by', 'adjusted_at', 'reason',
                ]),
                'adjusted_by_name' => $a->adjustedByStaff?->fullName(),
            ])->values(),
        ];
    }
}
