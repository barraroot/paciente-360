<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\AppointmentsController;
use App\Http\Controllers\Api\V1\Public\AppointmentTypesController;
use App\Http\Controllers\Api\V1\Public\MessagesController;
use App\Http\Controllers\Api\V1\Public\PatientsController;
use App\Http\Controllers\Api\V1\Public\PrescriptionsController;
use App\Http\Controllers\Api\V1\Public\ProfessionalsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Pública v1 — Fase 8 (T008 + T230)
|--------------------------------------------------------------------------
|
| Endpoints expostos a integradores externos via Bearer Sanctum (ou OAuth
| Client Credentials para enterprise — Q18). Tenant resolvido pelo token,
| nunca por header `X-Tenant-Slug` ou parâmetro de URL — Princípio II.
|
| Escopo (Q14): patients (RW), appointments (RW), messages (R),
| prescriptions (R, controladas SEMPRE mascaradas), appointment-types (R),
| professionals (R). Recursos fora retornam 404 (não 401).
|
| Middlewares aplicados em grupo:
|   - oauth.authenticator (Q18, passa adiante se desabilitado)
|   - auth:sanctum
|   - api_public.tenant_not_suspended (503)
|   - api_public.rate_limit (por token+plano + cap IP)
|   - throttle:api-public (cap defensivo global)
|
*/

Route::prefix('v1')
    ->middleware([
        'oauth.authenticator',
        'auth:sanctum',
        'api_public.tenant_not_suspended',
        'api_public.rate_limit',
    ])
    ->name('api_public.')
    ->group(function (): void {
        // Patients (RW)
        Route::get('/patients', [PatientsController::class, 'index'])->name('patients.index');
        Route::post('/patients', [PatientsController::class, 'store'])->name('patients.store');
        Route::get('/patients/{patient}', [PatientsController::class, 'show'])->whereNumber('patient')->name('patients.show');
        Route::patch('/patients/{patient}', [PatientsController::class, 'update'])->whereNumber('patient')->name('patients.update');

        // Appointments (RW)
        Route::get('/appointments', [AppointmentsController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AppointmentsController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}', [AppointmentsController::class, 'show'])->whereNumber('appointment')->name('appointments.show');
        Route::patch('/appointments/{appointment}', [AppointmentsController::class, 'update'])->whereNumber('appointment')->name('appointments.update');
        Route::delete('/appointments/{appointment}', [AppointmentsController::class, 'destroy'])->whereNumber('appointment')->name('appointments.destroy');

        // Messages (R)
        Route::get('/messages', [MessagesController::class, 'index'])->name('messages.index');
        Route::get('/messages/{message}', [MessagesController::class, 'show'])->whereNumber('message')->name('messages.show');

        // Prescriptions (R, controladas mascaradas)
        Route::get('/prescriptions', [PrescriptionsController::class, 'index'])->name('prescriptions.index');
        Route::get('/prescriptions/{prescription}', [PrescriptionsController::class, 'show'])->whereNumber('prescription')->name('prescriptions.show');

        // AppointmentTypes (R)
        Route::get('/appointment-types', [AppointmentTypesController::class, 'index'])->name('appointment_types.index');
        Route::get('/appointment-types/{appointment_type}', [AppointmentTypesController::class, 'show'])->whereNumber('appointment_type')->name('appointment_types.show');

        // Professionals (R)
        Route::get('/professionals', [ProfessionalsController::class, 'index'])->name('professionals.index');
        Route::get('/professionals/{professional}', [ProfessionalsController::class, 'show'])->whereNumber('professional')->name('professionals.show');
    });
