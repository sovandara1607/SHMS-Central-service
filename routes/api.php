<?php

use App\Http\Controllers\AppointmentsApiController;
use App\Http\Controllers\BillingApiController;
use App\Http\Controllers\DepartmentsApiController;
use App\Http\Controllers\HospitalSettingsApiController;
use App\Http\Controllers\LabApiController;
use App\Http\Controllers\LabReportApiController;
use App\Http\Controllers\MedicalProceduresApiController;
use App\Http\Controllers\MedicalRecordsApiController;
use App\Http\Controllers\MedicalReportsApiController;
use App\Http\Controllers\PatientAssignmentsApiController;
use App\Http\Controllers\PatientsApiController;
use App\Http\Controllers\PharmacyApiController;
use App\Http\Controllers\PrescriptionsApiController;
use App\Http\Controllers\RoomAssignmentsApiController;
use App\Http\Controllers\RoomsApiController;
use App\Http\Controllers\StaffShiftsApiController;
use App\Http\Controllers\VitalSignsApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::middleware('central-service.key')->group(function () {
    Route::get('/lab-reports/{labReportId}/status', [LabReportApiController::class, 'status']);
    Route::post('/lab-reports/{labReportId}/regenerate', [LabReportApiController::class, 'regenerate']);

    Route::get('/hospital-settings', [HospitalSettingsApiController::class, 'show']);
    Route::put('/hospital-settings', [HospitalSettingsApiController::class, 'update']);

    Route::get('/patients/search', [PatientsApiController::class, 'search']);
    Route::apiResource('patients', PatientsApiController::class)->except(['destroy', 'update']);
    Route::post('patients/{id}/adjust', [PatientsApiController::class, 'adjust']);
    Route::post('patients/{id}/discharge', [PatientsApiController::class, 'discharge']);
    Route::post('patients/{id}/doctor-assignments', [PatientAssignmentsApiController::class, 'storeDoctor']);
    Route::post('doctor-assignments/{id}/end', [PatientAssignmentsApiController::class, 'endDoctor']);
    Route::post('patients/{id}/nurse-assignments', [PatientAssignmentsApiController::class, 'storeNurse']);
    Route::post('nurse-assignments/{id}/end', [PatientAssignmentsApiController::class, 'endNurse']);

    Route::apiResource('appointments', AppointmentsApiController::class)->except(['destroy']);
    Route::post('appointments/{id}/cancel', [AppointmentsApiController::class, 'cancel']);
    Route::post('appointments/{id}/complete', [AppointmentsApiController::class, 'complete']);

    Route::apiResource('medical-records', MedicalRecordsApiController::class)->only(['index', 'store', 'show']);
    Route::post('medical-records/{id}/adjust', [MedicalRecordsApiController::class, 'adjust']);
    Route::post('medical-records/{id}/prescriptions', [PrescriptionsApiController::class, 'store']);
    Route::post('medical-records/{id}/reports', [MedicalReportsApiController::class, 'store']);
    Route::post('medical-records/{id}/procedures', [MedicalProceduresApiController::class, 'store']);
    Route::post('medical-records/{id}/vital-signs', [VitalSignsApiController::class, 'storeForRecord']);
    Route::get('medical-reports', [MedicalReportsApiController::class, 'index']);

    Route::get('vital-signs', [VitalSignsApiController::class, 'index']);
    Route::post('vital-signs', [VitalSignsApiController::class, 'store']);

    Route::get('lab', [LabApiController::class, 'index']);
    Route::get('lab-orders/{id}', [LabApiController::class, 'showOrder']);
    Route::post('lab-orders', [LabApiController::class, 'storeOrder']);
    Route::post('lab-orders/{id}/status', [LabApiController::class, 'updateOrderStatus']);
    Route::get('lab-results/{id}', [LabApiController::class, 'showResult']);
    Route::put('lab-results/{id}', [LabApiController::class, 'updateResult']);
    Route::post('lab-results', [LabApiController::class, 'enterResult']);
    Route::get('lab-equipment', [LabApiController::class, 'equipment']);

    Route::get('bills', [BillingApiController::class, 'index']);
    Route::get('bills/{id}', [BillingApiController::class, 'show']);
    Route::post('bills', [BillingApiController::class, 'store']);
    Route::post('bills/{id}/items', [BillingApiController::class, 'addItem']);
    Route::post('bills/{id}/pay', [BillingApiController::class, 'pay']);

    Route::get('pharmacy', [PharmacyApiController::class, 'index']);
    Route::get('medicines/all', [PharmacyApiController::class, 'listAllMedicines']);
    Route::post('medicines', [PharmacyApiController::class, 'storeMedicine']);
    Route::get('medicines/{id}', [PharmacyApiController::class, 'showMedicine']);
    Route::put('medicines/{id}', [PharmacyApiController::class, 'updateMedicine']);
    Route::get('medicine-batches', [PharmacyApiController::class, 'listBatches']);
    Route::post('medicine-batches', [PharmacyApiController::class, 'storeBatch']);
    Route::get('medicine-batches/{id}', [PharmacyApiController::class, 'showBatch']);
    Route::put('medicine-batches/{id}', [PharmacyApiController::class, 'updateBatch']);
    Route::get('dispensing-page', [PharmacyApiController::class, 'dispensingPage']);
    Route::get('prescriptions/{id}/pharmacy', [PharmacyApiController::class, 'showPrescription']);
    Route::get('prescriptions/{id}/dispense-data', [PharmacyApiController::class, 'dispenseFormData']);
    Route::get('dispensing/{id}', [PharmacyApiController::class, 'showDispensing']);
    Route::post('dispensing', [PharmacyApiController::class, 'dispense']);
    Route::get('laboratories/all', [LabApiController::class, 'listAllLaboratories']);

    Route::get('departments', [DepartmentsApiController::class, 'index']);
    Route::post('departments', [DepartmentsApiController::class, 'store']);
    Route::get('departments/{id}', [DepartmentsApiController::class, 'show']);
    Route::put('departments/{id}', [DepartmentsApiController::class, 'update']);

    Route::get('rooms', [RoomsApiController::class, 'index']);
    Route::post('rooms', [RoomsApiController::class, 'store']);
    Route::get('room-beds', [RoomAssignmentsApiController::class, 'allBeds']);
    Route::get('room-assignments', [RoomAssignmentsApiController::class, 'allAssignments']);
    Route::get('rooms/{id}', [RoomsApiController::class, 'show']);
    Route::put('rooms/{id}', [RoomsApiController::class, 'update']);
    Route::get('rooms/{id}/beds', [RoomAssignmentsApiController::class, 'bedsForRoom']);
    Route::post('rooms/{id}/beds', [RoomAssignmentsApiController::class, 'storeBed']);

    Route::get('beds/{id}/assign-data', [RoomAssignmentsApiController::class, 'assignFormData']);
    Route::post('beds/{id}/assign', [RoomAssignmentsApiController::class, 'assign']);
    Route::post('room-assignments/{id}/release', [RoomAssignmentsApiController::class, 'release']);
    Route::post('patients/{id}/release-room', [RoomAssignmentsApiController::class, 'releaseForPatient']);
    Route::get('patients/{id}/room-assignments', [RoomAssignmentsApiController::class, 'forPatient']);

    Route::get('staff-shifts', [StaffShiftsApiController::class, 'index']);
    Route::get('schedule', [StaffShiftsApiController::class, 'schedulePage']);
    Route::get('schedule/{id}', [StaffShiftsApiController::class, 'show']);
    Route::post('schedule', [StaffShiftsApiController::class, 'store']);
    Route::put('schedule/{id}', [StaffShiftsApiController::class, 'update']);
    Route::post('schedule/{id}/cancel', [StaffShiftsApiController::class, 'cancel']);
});
