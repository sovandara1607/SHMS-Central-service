<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignBedRequest;
use App\Http\Requests\StoreBedRequest;
use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Bed-level view + patient room/bed assignment, nested under Room & Bed Management. */
class RoomAssignmentsApiController extends Controller
{
    /** Backs the Room Assignments tab on the patient detail modal. */
    public function forPatient(string $patientId): JsonResponse
    {
        $assignments = RoomAssignment::with('room', 'bed')
            ->where('patient_id', $patientId)
            ->orderByDesc('assigned_at')
            ->get();

        return response()->json($assignments->map(fn (RoomAssignment $ra) => [
            ...$ra->only(['room_assignment_id', 'patient_id', 'room_id', 'bed_id', 'assigned_by', 'assigned_at', 'released_at', 'status']),
            'room' => $ra->room?->only(['room_id', 'room_number', 'room_type']),
            'bed' => $ra->bed?->only(['bed_id', 'bed_number']),
        ])->values());
    }

    /** Backs the "Beds" tab on the Room & Bed Management page — all beds, across all rooms. */
    public function allBeds(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $beds = DB::table('bed as b')
            ->join('room as r', 'r.room_id', '=', 'b.room_id')
            ->leftJoin('department as dep', 'dep.department_id', '=', 'r.department_id')
            ->leftJoin('room_assignment as ra', fn ($join) => $join->on('ra.bed_id', '=', 'b.bed_id')->where('ra.status', 'active'))
            ->leftJoin('patient as p', 'p.patient_id', '=', 'ra.patient_id')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('b.bed_id', 'ilike', $like)
                        ->orWhere('r.room_number', 'ilike', $like);
                });
            })
            ->when($status, fn ($query) => $query->where('b.status', $status))
            ->orderBy('r.floor_number')->orderBy('r.room_number')->orderBy('b.bed_number')
            ->selectRaw("b.*, r.room_number, r.room_type, dep.department_name,
                         ra.room_assignment_id, ra.patient_id, ra.assigned_at, (p.first_name||' '||p.last_name) as patient_name")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'data' => $beds->items(),
            'meta' => [
                'current_page' => $beds->currentPage(),
                'last_page'    => $beds->lastPage(),
                'total'        => $beds->total(),
                'per_page'     => $beds->perPage(),
            ],
        ]);
    }

    /** Backs the "Room Assignments" tab on the Room & Bed Management page — all assignments, across all patients. */
    public function allAssignments(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');

        $assignments = DB::table('room_assignment as ra')
            ->join('patient as p', 'p.patient_id', '=', 'ra.patient_id')
            ->join('room as r', 'r.room_id', '=', 'ra.room_id')
            ->leftJoin('bed as b', 'b.bed_id', '=', 'ra.bed_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'ra.assigned_by')
            ->when($status !== 'all', fn ($query) => $query->where('ra.status', $status))
            ->orderByDesc('ra.assigned_at')
            ->selectRaw("ra.*, (p.first_name||' '||p.last_name) as patient_name, r.room_number, b.bed_number,
                         (s.first_name||' '||s.last_name) as assigned_by_name")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'data' => $assignments->items(),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page'    => $assignments->lastPage(),
                'total'        => $assignments->total(),
                'per_page'     => $assignments->perPage(),
            ],
        ]);
    }

    public function bedsForRoom(string $roomId): JsonResponse
    {
        $room = Room::with('department')->find($roomId);
        if (! $room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $beds = DB::table('bed as b')
            ->leftJoin('room_assignment as ra', function ($join) {
                $join->on('ra.bed_id', '=', 'b.bed_id')->where('ra.status', 'active');
            })
            ->leftJoin('patient as p', 'p.patient_id', '=', 'ra.patient_id')
            ->where('b.room_id', $roomId)
            ->orderBy('b.bed_number')
            ->selectRaw("b.*, ra.room_assignment_id, ra.patient_id, ra.assigned_at,
                         (p.first_name||' '||p.last_name) as patient_name")
            ->get();

        return response()->json([
            'room' => [
                ...$room->only(['room_id', 'room_number', 'room_type', 'floor_number', 'department_id', 'status']),
                'department' => $room->department?->only(['department_id', 'department_name']),
            ],
            'beds' => $beds,
        ]);
    }

    public function storeBed(StoreBedRequest $request, string $roomId): JsonResponse
    {
        $room = Room::find($roomId);
        if (! $room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $bed = Bed::create(['room_id' => $room->room_id, 'bed_number' => $request->validated('bed_number')]);

        return response()->json($bed->fresh(), 201);
    }

    public function assignFormData(string $bedId): JsonResponse
    {
        $bed = Bed::with('room')->find($bedId);
        if (! $bed) {
            return response()->json(['message' => 'Bed not found'], 404);
        }
        if ($bed->status !== 'available') {
            return response()->json(['message' => 'This bed is not available.'], 409);
        }

        return response()->json([
            ...$bed->only(['bed_id', 'room_id', 'bed_number', 'status']),
            'room' => $bed->room?->only(['room_id', 'room_number', 'room_type']),
        ]);
    }

    public function assign(AssignBedRequest $request, string $bedId): JsonResponse
    {
        try {
            $result = DB::transaction(function () use ($bedId, $request) {
                // lockForUpdate() only holds its row lock for the life of an
                // active transaction — issued outside one, Postgres releases
                // it right after this single autocommitted SELECT, so two
                // concurrent requests could both read "available" and both
                // assign the same bed. The lock (and the availability check
                // it protects) must live inside the transaction to actually
                // serialize concurrent assignment attempts.
                $bed = Bed::with('room')->lockForUpdate()->find($bedId);
                if (! $bed) {
                    throw new \RuntimeException('Bed not found', 404);
                }
                if ($bed->status !== 'available') {
                    throw new \RuntimeException('This bed is not available.', 409);
                }

                $assignment = RoomAssignment::create([
                    'patient_id' => $request->validated('patient_id'),
                    'room_id' => $bed->room_id,
                    'bed_id' => $bed->bed_id,
                    'assigned_by' => $request->input('assigned_by'),
                    'status' => 'active',
                ]);
                $bed->update(['status' => 'occupied']);
                $bed->room->update(['status' => 'occupied']);

                return ['assignment' => $assignment, 'bed' => $bed];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 409);
        }

        return response()->json([
            'assignment_id' => $result['assignment']->room_assignment_id,
            'room_id' => $result['bed']->room_id,
            'bed_id' => $result['bed']->bed_id,
            'bed_number' => $result['bed']->bed_number,
        ], 201);
    }

    public function release(string $id): JsonResponse
    {
        $assignment = RoomAssignment::find($id);
        if (! $assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }
        if ($assignment->status !== 'active') {
            return response()->json(['message' => 'This assignment is already released.'], 409);
        }

        $this->releaseAssignment($assignment);

        return response()->json([
            'room_assignment_id' => $assignment->room_assignment_id,
            'room_id' => $assignment->room_id,
            'patient_id' => $assignment->patient_id,
        ]);
    }

    /** Release whichever bed a patient currently occupies, if any (called on discharge). */
    public function releaseForPatient(string $patientId): JsonResponse
    {
        $assignment = RoomAssignment::where('patient_id', $patientId)->where('status', 'active')->first();
        if ($assignment) {
            $this->releaseAssignment($assignment);
        }

        return response()->json(['released' => (bool) $assignment]);
    }

    private function releaseAssignment(RoomAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment) {
            $assignment->update(['status' => 'completed', 'released_at' => now()]);

            if ($assignment->bed_id) {
                Bed::where('bed_id', $assignment->bed_id)->update(['status' => 'available']);
            }

            $stillOccupied = RoomAssignment::where('room_id', $assignment->room_id)
                ->where('status', 'active')->exists();
            if (! $stillOccupied) {
                Room::where('room_id', $assignment->room_id)->update(['status' => 'available']);
            }
        });
    }
}
