<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $date = $request->query('date');
        $status = $request->query('status', 'all');
        $patientId = $request->query('patient_id');
        $perPage = (int) $request->query('per_page', 20);

        $appointments = DB::table('appointment as a')
            ->join('patient as p', 'p.patient_id', '=', 'a.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'a.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('staff as bs', 'bs.staff_id', '=', 'a.booked_by')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('a.appointment_id', 'ilike', $like)
                        ->orWhereRaw("(p.first_name || ' ' || p.last_name) ilike ?", [$like])
                        ->orWhereRaw("(s.first_name || ' ' || s.last_name) ilike ?", [$like]);
                });
            })
            ->when($date, fn ($query) => $query->where('a.appointment_date', $date))
            ->when($status !== 'all', fn ($query) => $query->where('a.status', $status))
            ->when($patientId, fn ($query) => $query->where('a.patient_id', $patientId))
            ->orderByDesc('a.appointment_date')->orderByDesc('a.appointment_time')
            ->selectRaw("a.*, (p.first_name||' '||p.last_name) as patient_name, p.patient_id as patient_id, (s.first_name||' '||s.last_name) as doctor_name, (bs.first_name||' '||bs.last_name) as booked_by_name")
            ->paginate($perPage);

        $today = now()->toDateString();
        $month = $request->query('month', now()->format('Y-m'));
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $calendar = DB::table('appointment')
            ->whereBetween('appointment_date', [$monthStart, $monthEnd])
            ->selectRaw('appointment_date, COUNT(*) as count')
            ->groupBy('appointment_date')
            ->pluck('count', 'appointment_date');

        return response()->json([
            'data' => $appointments->items(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page'    => $appointments->lastPage(),
                'total'        => $appointments->total(),
                'per_page'     => $appointments->perPage(),
            ],
            'stats' => [
                'today'     => Appointment::where('appointment_date', $today)->count(),
                'this_week' => Appointment::whereBetween('appointment_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->count(),
                'scheduled' => Appointment::where('status', 'scheduled')->count(),
                'cancelled' => Appointment::where('status', 'cancelled')->count(),
            ],
            'calendar' => $calendar,
        ]);
    }

    public function show(string $appointment): JsonResponse
    {
        $model = Appointment::with(['patient', 'doctor.staff', 'bookedByStaff'])->findOrFail($appointment);

        return response()->json($this->present($model));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->slotTaken($data['doctor_id'], $data['appointment_date'], $data['appointment_time'])) {
            return response()->json(['message' => 'That doctor already has a scheduled appointment in this slot.'], 409);
        }

        $data['booked_by'] = $request->input('booked_by');
        $appointment = Appointment::create($data);

        return response()->json($this->present($appointment->load('patient', 'doctor.staff', 'bookedByStaff')), 201);
    }

    public function update(UpdateAppointmentRequest $request, string $appointment): JsonResponse
    {
        $model = Appointment::findOrFail($appointment);
        $data = $request->validated();

        if ($this->slotTaken($data['doctor_id'], $data['appointment_date'], $data['appointment_time'], $model->appointment_id)) {
            return response()->json(['message' => 'That doctor already has a scheduled appointment in this slot.'], 409);
        }

        $model->update($data);

        return response()->json($this->present($model->fresh(['patient', 'doctor.staff', 'bookedByStaff'])));
    }

    public function cancel(Request $request, string $appointment): JsonResponse
    {
        $model = Appointment::findOrFail($appointment);
        $model->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('cancellation_reason', 'Cancelled by staff'),
        ]);

        return response()->json($this->present($model->fresh(['patient', 'doctor.staff', 'bookedByStaff'])));
    }

    public function complete(string $appointment): JsonResponse
    {
        $model = Appointment::findOrFail($appointment);

        // A single conditional UPDATE instead of check-then-act: the status
        // guard lives in the WHERE clause, so the database serializes
        // concurrent completions on the same row and only one can ever
        // match — no race between a double-click (or two staff members)
        // both reading 'scheduled' before either write lands.
        $updated = Appointment::where('appointment_id', $appointment)
            ->where('status', 'scheduled')
            ->update(['status' => 'completed']);

        if ($updated === 0) {
            return response()->json(['message' => 'Only a scheduled appointment can be marked completed.'], 409);
        }

        return response()->json($this->present($model->fresh(['patient', 'doctor.staff', 'bookedByStaff'])));
    }

    /** Already-booked times for a doctor on a date, so the create form can hint before submit (slotTaken() below remains the authoritative check). */
    public function bookedSlots(Request $request): JsonResponse
    {
        $doctorId = $request->query('doctor_id');
        $date = $request->query('date');
        if (! $doctorId || ! $date) {
            return response()->json([]);
        }

        $times = Appointment::where('doctor_id', $doctorId)
            ->where('appointment_date', $date)
            ->where('status', 'scheduled')
            ->orderBy('appointment_time')
            ->pluck('appointment_time')
            ->map(fn ($t) => substr((string) $t, 0, 5))
            ->values();

        return response()->json($times);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $like = '%' . $q . '%';
        $appointments = DB::table('appointment as a')
            ->join('patient as p', 'p.patient_id', '=', 'a.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'a.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->where(function ($sub) use ($like) {
                $sub->where('a.appointment_id', 'ilike', $like)
                    ->orWhereRaw("(p.first_name || ' ' || p.last_name) ilike ?", [$like]);
            })
            ->orderByDesc('a.appointment_date')->orderByDesc('a.appointment_time')
            ->limit(20)
            ->selectRaw("a.appointment_id, a.appointment_date, a.appointment_time, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name")
            ->get();

        return response()->json($appointments->map(fn ($a) => [
            'id' => $a->appointment_id,
            'label' => $a->appointment_id . ' — ' . $a->patient_name . ' with Dr. ' . $a->doctor_name . ', ' . $a->appointment_date . ' ' . substr($a->appointment_time, 0, 5),
        ]));
    }

    private function slotTaken(string $doctorId, string $date, string $time, ?string $exceptId = null): bool
    {
        return Appointment::where('doctor_id', $doctorId)
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->where('status', 'scheduled')
            ->when($exceptId, fn ($q) => $q->where('appointment_id', '!=', $exceptId))
            ->exists();
    }

    private function present(Appointment $appointment): array
    {
        return [
            ...$appointment->only([
                'appointment_id', 'patient_id', 'doctor_id', 'booked_by',
                'appointment_date', 'appointment_time', 'reason', 'status',
                'cancellation_reason', 'created_at', 'updated_at',
            ]),
            'patient' => $appointment->patient?->only([
                'patient_id', 'first_name', 'last_name', 'date_of_birth',
            ]),
            'doctor_name' => $appointment->doctor?->name(),
            'booked_by_name' => $appointment->bookedByStaff?->fullName(),
        ];
    }
}
