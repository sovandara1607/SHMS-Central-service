<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientDoctorAssignmentRequest;
use App\Http\Requests\StorePatientNurseAssignmentRequest;
use App\Models\Patient;
use App\Models\PatientDoctorAssignment;
use App\Models\PatientNurseAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Long-term / special-care doctor and nurse assignments for admitted
 * patients. Mirrors Database-final's PatientAssignmentController 1:1.
 */
class PatientAssignmentsApiController extends Controller
{
    public function storeDoctor(StorePatientDoctorAssignmentRequest $request, string $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        $assignment = PatientDoctorAssignment::create([
            'patient_id'  => $patient->patient_id,
            'doctor_id'   => $request->validated('doctor_id'),
            'role'        => $request->validated('role'),
            'assigned_by' => $request->input('assigned_by'),
        ]);

        return response()->json($assignment->fresh(), 201);
    }

    public function endDoctor(string $id): JsonResponse
    {
        $assignment = PatientDoctorAssignment::findOrFail($id);
        $assignment->update(['status' => 'completed', 'ended_at' => now()]);

        return response()->json($assignment->fresh());
    }

    public function storeNurse(StorePatientNurseAssignmentRequest $request, string $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        $assignment = PatientNurseAssignment::create([
            'patient_id'  => $patient->patient_id,
            'nurse_id'    => $request->validated('nurse_id'),
            'shift_id'    => $request->validated('shift_id'),
            'assigned_by' => $request->input('assigned_by'),
        ]);

        return response()->json($assignment->fresh(), 201);
    }

    public function endNurse(string $id): JsonResponse
    {
        $assignment = PatientNurseAssignment::findOrFail($id);
        $assignment->update(['status' => 'completed', 'ended_at' => now()]);

        return response()->json($assignment->fresh());
    }
}
