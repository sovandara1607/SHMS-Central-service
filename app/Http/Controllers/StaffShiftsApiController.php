<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffShiftRequest;
use App\Http\Requests\UpdateStaffShiftRequest;
use App\Models\StaffShift;
use App\Support\DropdownCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffShiftsApiController extends Controller
{
    /** Backs the nurse-assignment shift dropdown on the patient detail modal. */
    public function index(): JsonResponse
    {
        return response()->json(DropdownCache::remember(
            'recent-shifts',
            fn () => StaffShift::orderByDesc('shift_date')->limit(50)->get()
        ));
    }

    /** Backs the flat, paginated "Schedule Management" list page. */
    public function schedulePage(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $role = $request->query('role');
        $shifts = DB::table('staff_shift as sh')
            ->join('staff as s', 's.staff_id', '=', 'sh.staff_id')
            ->leftJoin('users as u', 'u.staff_id', '=', 's.staff_id')
            ->when($status, fn ($q) => $q->where('sh.status', $status))
            ->when($role, fn ($q) => $q->where('u.role', $role))
            ->orderByDesc('sh.shift_date')
            ->selectRaw("sh.*, (s.first_name||' '||s.last_name) as staff_name")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        // One scan of `staff_shift` with per-bucket FILTERs instead of 4
        // separate count() queries (see analysis.md §4.8).
        $stats = DB::table('staff_shift')->selectRaw(
            "count(*) filter (where status = 'scheduled') as scheduled,
             count(*) filter (where status = 'completed') as completed,
             count(*) filter (where status = 'on_leave') as on_leave,
             count(*) filter (where status = 'cancelled') as cancelled"
        )->first();

        return response()->json([
            'data' => $shifts->items(),
            'meta' => [
                'current_page' => $shifts->currentPage(),
                'last_page'    => $shifts->lastPage(),
                'total'        => $shifts->total(),
                'per_page'     => $shifts->perPage(),
            ],
            'stats' => [
                'scheduled' => (int) $stats->scheduled,
                'completed' => (int) $stats->completed,
                'on_leave'  => (int) $stats->on_leave,
                'cancelled' => (int) $stats->cancelled,
            ],
        ]);
    }

    public function show(string $shift): JsonResponse
    {
        $model = StaffShift::with('staff')->findOrFail($shift);

        return response()->json($this->present($model));
    }

    public function store(StoreStaffShiftRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->hasOverlap($data['staff_id'], $data['shift_date'], $data['start_time'], $data['end_time'])) {
            return response()->json(['message' => 'This staff member already has an overlapping shift on this date.'], 409);
        }

        $shift = StaffShift::create($data + ['status' => 'scheduled']);

        return response()->json($this->present($shift->load('staff')), 201);
    }

    public function update(UpdateStaffShiftRequest $request, string $shift): JsonResponse
    {
        $model = StaffShift::findOrFail($shift);
        $data = $request->validated();

        if ($this->hasOverlap($model->staff_id, $data['shift_date'], $data['start_time'], $data['end_time'], $model->shift_id)) {
            return response()->json(['message' => 'This staff member already has an overlapping shift on this date.'], 409);
        }

        $model->update($data);

        return response()->json($this->present($model->fresh('staff')));
    }

    /**
     * Same staff member, overlapping time range, not cancelled — a real
     * double-booking. shift_type includes 'night', and nothing requires
     * end_time > start_time, so an overnight shift (e.g. 22:00-06:00) is
     * expected input. Comparing raw start_time/end_time as plain times
     * broke for any pair involving one of those, since e.g. 01:00 isn't
     * "greater than" 22:00 — every interval below is anchored to a real
     * datetime instead, pushing the end past midnight whenever end <= start
     * so the comparison is correct regardless of day rollover. Candidates
     * also include the day before, since an overnight shift that started
     * yesterday can still be running during today's early hours.
     */
    private function hasOverlap(string $staffId, string $date, string $start, string $end, ?string $exceptShiftId = null): bool
    {
        [$newStart, $newEnd] = $this->shiftInterval($date, $start, $end);

        $candidates = StaffShift::where('staff_id', $staffId)
            ->whereIn('shift_date', [$date, Carbon::parse($date)->subDay()->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->when($exceptShiftId, fn ($q) => $q->where('shift_id', '!=', $exceptShiftId))
            ->get();

        foreach ($candidates as $shift) {
            [$existingStart, $existingEnd] = $this->shiftInterval($shift->shift_date, $shift->start_time, $shift->end_time);
            if ($newStart < $existingEnd && $newEnd > $existingStart) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function shiftInterval(string $date, string $start, string $end): array
    {
        $date = Carbon::parse($date)->toDateString();
        $startsAt = Carbon::parse("{$date} {$start}");
        $endsAt = Carbon::parse("{$date} {$end}");
        if ($endsAt <= $startsAt) {
            $endsAt->addDay();
        }

        return [$startsAt, $endsAt];
    }

    public function cancel(string $shift): JsonResponse
    {
        $model = StaffShift::findOrFail($shift);
        $model->update(['status' => 'cancelled']);

        return response()->json($this->present($model->fresh('staff')));
    }

    private function present(StaffShift $shift): array
    {
        return [
            ...$shift->only(['shift_id', 'staff_id', 'shift_date', 'start_time', 'end_time', 'shift_type', 'status']),
            'staff_name' => $shift->staff?->fullName(),
        ];
    }
}
