<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $rooms = DB::table('room as r')
            ->leftJoin('department as dep', 'dep.department_id', '=', 'r.department_id')
            ->leftJoin('bed as b', 'b.room_id', '=', 'r.room_id')
            ->when($q !== '', fn ($query) => $query->where('r.room_id', 'ilike', "%$q%")->orWhere('r.room_number', 'ilike', "%$q%"))
            ->groupBy('r.room_id', 'r.room_number', 'r.room_type', 'r.floor_number', 'dep.department_name', 'r.status')
            ->orderBy('r.floor_number')->orderBy('r.room_number')
            ->selectRaw("r.room_id, r.room_number, r.room_type, r.floor_number, dep.department_name,
                         count(b.bed_id) as bed_count, count(*) filter (where b.status='available') as beds_available, r.status")
            ->paginate((int) $request->query('per_page', 20), ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'data' => $rooms->items(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'last_page'    => $rooms->lastPage(),
                'total'        => $rooms->total(),
                'per_page'     => $rooms->perPage(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $room = Room::find($id);
        if (! $room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        return response()->json($room);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return response()->json($room->fresh(), 201);
    }

    public function update(UpdateRoomRequest $request, string $id): JsonResponse
    {
        $room = Room::find($id);
        if (! $room) {
            return response()->json(['message' => 'Room not found'], 404);
        }
        $room->update($request->validated());

        return response()->json($room->fresh());
    }
}
