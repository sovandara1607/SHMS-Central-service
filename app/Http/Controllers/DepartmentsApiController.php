<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $departments = Department::query()
            ->when($q !== '', fn ($query) => $query->where('department_id', 'ilike', "%$q%")->orWhere('department_name', 'ilike', "%$q%"))
            ->orderBy('department_name')->get();

        return response()->json($departments);
    }

    public function show(string $id): JsonResponse
    {
        $department = Department::find($id);
        if (! $department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        return response()->json($department);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return response()->json($department->fresh(), 201);
    }

    public function update(UpdateDepartmentRequest $request, string $id): JsonResponse
    {
        $department = Department::find($id);
        if (! $department) {
            return response()->json(['message' => 'Department not found'], 404);
        }
        $department->update($request->validated());

        return response()->json($department->fresh());
    }
}
