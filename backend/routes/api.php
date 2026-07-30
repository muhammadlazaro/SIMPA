<?php

use App\Enums\UserRole;
use App\Http\Controllers\AnalisaDesainController;
use App\Http\Controllers\AplikasiController;
use App\Http\Controllers\AplikasiDocumentController;
use App\Http\Controllers\AplikasiWorkflowController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonilController;
use App\Http\Controllers\RfcController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API root endpoint (browser/manual check)
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'API Backend aktif',
        'data' => [
            'service' => 'Sistem Manajemen Pengembangan Aplikasi',
            'login' => url('/api/login'),
            'health' => url('/health'),
        ],
    ]);
});

// Public routes (tidak perlu authentication) with rate limiting
Route::middleware('throttle:5,1')->group(function () {
    // Login: Max 5 attempts per minute to prevent brute force
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (perlu authentication) with rate limiting, input sanitization, and request logging
Route::middleware(['auth:sanctum', 'throttle:60,1', 'sanitize', 'log.requests'])->group(function () {
    $aplikasiIdPath = 'aplikasi/{id}';
    $analisaDesainIdPath = 'analisa-desain/{id}';
    $rfcIdPath = 'rfc/{id}';
    $securityReviewPath = 'aplikasi/{aplikasi}/security-review';

    // Auth routes (available for all authenticated users)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('role:'.UserRole::ADMIN_SISTEM->value);

    // Admin Sistem: personil and access management
    Route::middleware('role:'.UserRole::ADMIN_SISTEM->value)->group(function () {
        Route::prefix('personil')->group(function () {
            Route::get('stats', [PersonilController::class, 'stats']);
            Route::get('', [PersonilController::class, 'index']);
            Route::post('', [PersonilController::class, 'store']);
            Route::match(['put', 'patch'], '{id}', [PersonilController::class, 'update']);
            Route::delete('{id}', [PersonilController::class, 'destroy']);
            Route::delete('{id}/force', [PersonilController::class, 'forceDestroy']);
            Route::post('{id}/restore', [PersonilController::class, 'restore']);
        });
    });

    // Notifikasi in-app (semua role yang sudah login)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    // Read-only routes (available for semua role yang berwenang)
    Route::get('aplikasi', [AplikasiController::class, 'index']);
    Route::get('aplikasi/stats', [AplikasiController::class, 'stats']);
    Route::get('aplikasi/pengelola-notifications', [AplikasiController::class, 'pengelolaNotifications'])
        ->middleware('role:pengelola_aplikasi');
    Route::middleware('role:'
        .UserRole::PENGELOLA_APLIKASI->value.','
        .UserRole::UNIT_KERJA->value.','
        .UserRole::ANALIS_DESAIN->value.','
        .UserRole::TIM_IMPLEMENTASI_APLIKASI->value.','
        .UserRole::DEVOPS_DEVELOPER->value.','
        .UserRole::TIM_UJI_KEAMANAN->value
    )->group(function () {
        Route::prefix('aplikasi/{aplikasi}')->group(function () {
            Route::get('documents', [AplikasiDocumentController::class, 'index']);
            Route::post('documents', [AplikasiDocumentController::class, 'store']);
            Route::get('documents/{document}/preview', [AplikasiDocumentController::class, 'preview'])
                ->name('aplikasi.documents.preview');

            // Notes can be added by any role participating in the app
            Route::post('notes', [AplikasiWorkflowController::class, 'storeNote']);
            Route::match(['put', 'patch'], 'notes/{note}', [AplikasiWorkflowController::class, 'updateNote']);
            Route::delete('notes/{note}', [AplikasiWorkflowController::class, 'destroyNote']);

            // Workflow data (including notes history and checklists) can be viewed by all roles
            Route::get('workflow', [AplikasiWorkflowController::class, 'index']);
        });
    });
    Route::get($aplikasiIdPath, [AplikasiController::class, 'show']);
    Route::get('analisa-desain/summary', [AnalisaDesainController::class, 'summary']);
    Route::get('analisa-desain', [AnalisaDesainController::class, 'index']);
    Route::get($analisaDesainIdPath, [AnalisaDesainController::class, 'show']);
    Route::get('rfc', [RfcController::class, 'index']);
    Route::get('rfc/stats', [RfcController::class, 'stats']);
    Route::get($rfcIdPath, [RfcController::class, 'show']);

    // Unit kerja dan pengelola aplikasi sama-sama bisa mengajukan aplikasi baru
    Route::post('aplikasi', [AplikasiController::class, 'store'])->middleware(
        'role:'.UserRole::PENGELOLA_APLIKASI->value.','.UserRole::UNIT_KERJA->value
    );

    // Unit kerja mengajukan RFC; pengelola tetap dapat membuat RFC langsung bila diperlukan.
    Route::post('rfc', [RfcController::class, 'store'])->middleware(
        'role:'.UserRole::PENGELOLA_APLIKASI->value.','.UserRole::UNIT_KERJA->value
    );

    // Unit Kerja: tarik pengajuan sendiri (hanya jika status masih "Pengajuan")
    Route::delete('aplikasi/{id}/withdraw', [AplikasiController::class, 'withdraw'])
        ->middleware('role:'.UserRole::UNIT_KERJA->value);

    // Pengelola aplikasi routes (Aplikasi Management and RFC)
    Route::middleware('role:'.UserRole::PENGELOLA_APLIKASI->value)->group(function () use (
        $aplikasiIdPath,
        $rfcIdPath
    ) {
        // Aplikasi Management
        Route::put($aplikasiIdPath, [AplikasiController::class, 'update']);
        Route::patch($aplikasiIdPath, [AplikasiController::class, 'update']);
        Route::post($aplikasiIdPath.'/nonaktifkan', [AplikasiController::class, 'nonaktifkan']);
        Route::delete($aplikasiIdPath, [AplikasiController::class, 'destroy']);
        Route::post($aplikasiIdPath.'/restore', [AplikasiController::class, 'restore']);
        Route::get('aplikasi/trashed', [AplikasiController::class, 'trashed']);

        // RFC Management
        Route::put($rfcIdPath, [RfcController::class, 'update']);
        Route::patch($rfcIdPath, [RfcController::class, 'update']);
        Route::delete($rfcIdPath, [RfcController::class, 'destroy']);
    });

    // Analis desain mengelola checklist kelayakan/analisa pada halaman detail analisa.
    Route::middleware('role:'.UserRole::ANALIS_DESAIN->value)->group(function () {
        Route::prefix('aplikasi/{aplikasi}/checklists')->group(function () {
            Route::post('', [AplikasiWorkflowController::class, 'storeChecklist']);
            Route::match(['put', 'patch'], '{checklist}', [AplikasiWorkflowController::class, 'updateChecklist']);
            Route::delete('{checklist}', [AplikasiWorkflowController::class, 'destroyChecklist']);
        });
    });

    // Analisa Desain — akses bersama pengelola_aplikasi dan analis_desain
    Route::middleware('role:'
        .UserRole::PENGELOLA_APLIKASI->value.','
        .UserRole::ANALIS_DESAIN->value
    )->group(function () use ($analisaDesainIdPath) {
        Route::post('analisa-desain', [AnalisaDesainController::class, 'store']);
        Route::put($analisaDesainIdPath, [AnalisaDesainController::class, 'update']);
        Route::patch($analisaDesainIdPath, [AnalisaDesainController::class, 'update']);
        Route::delete($analisaDesainIdPath, [AnalisaDesainController::class, 'destroy']);
        Route::put('analisa-desain/batch/{aplikasiId}', [AnalisaDesainController::class, 'batchUpdate']);
    });

    // Tim implementasi (frontend/backend/devops)
    Route::middleware(
        'role:'
        .UserRole::TIM_IMPLEMENTASI_APLIKASI->value.','
        .UserRole::DEVOPS_DEVELOPER->value
    )->group(function () {
        Route::prefix('aplikasi/{aplikasi}/implementation-checklists')->group(function () {
            Route::get('', [AplikasiWorkflowController::class, 'implementationIndex']);
            Route::post('', [AplikasiWorkflowController::class, 'implementationStore']);
            Route::match(['put', 'patch'], '{checklist}', [AplikasiWorkflowController::class, 'implementationUpdate']);
            Route::delete('{checklist}', [AplikasiWorkflowController::class, 'implementationDestroy']);
        });
    });

    // Tim Uji Keamanan
    Route::middleware('role:'
        .UserRole::PENGELOLA_APLIKASI->value.','
        .UserRole::TIM_UJI_KEAMANAN->value
    )->group(function () use ($securityReviewPath) {
        Route::get($securityReviewPath, [AplikasiWorkflowController::class, 'securityReviewShow']);
    });
    Route::middleware('role:'.UserRole::TIM_UJI_KEAMANAN->value)->group(function () use ($securityReviewPath) {
        Route::put($securityReviewPath, [AplikasiWorkflowController::class, 'securityReviewUpdate']);
        Route::patch($securityReviewPath, [AplikasiWorkflowController::class, 'securityReviewUpdate']);
    });

    // DevOps — Deployment status tracking
    Route::prefix('aplikasi/{aplikasi}/deployment-status')->group(function () {
        Route::get('', [AplikasiWorkflowController::class, 'deploymentShow'])
            ->middleware('role:'
                .UserRole::PENGELOLA_APLIKASI->value.','
                .UserRole::DEVOPS_DEVELOPER->value);
        Route::match(['put', 'patch'], '', [AplikasiWorkflowController::class, 'deploymentUpdate'])
            ->middleware('role:'.UserRole::DEVOPS_DEVELOPER->value);
    });

    // Workflow Transitions
    Route::prefix('aplikasi/{aplikasi}/workflow')->group(function () {
        Route::get('histories', [AplikasiWorkflowController::class, 'statusHistories']);
        Route::post('verifikasi-pengajuan', [AplikasiWorkflowController::class, 'verifikasiPengajuan']);
        Route::post('perbaikan-pengajuan', [AplikasiWorkflowController::class, 'perbaikanPengajuan']);
        Route::post('studi-kelayakan', [AplikasiWorkflowController::class, 'studiKelayakan'])
            ->middleware('role:'.UserRole::ANALIS_DESAIN->value);
        Route::post('mulai-analisa-desain', [AplikasiWorkflowController::class, 'mulaiAnalisaDesain'])
            ->middleware('role:'.UserRole::ANALIS_DESAIN->value);
        Route::post('mulai-pengembangan', [AplikasiWorkflowController::class, 'mulaiPengembangan']);
        Route::post('siap-uat', [AplikasiWorkflowController::class, 'siapUat']);
        Route::post('verifikasi-uat', [AplikasiWorkflowController::class, 'verifikasiUat']);
        Route::post('selesai-perbaikan-uat', [AplikasiWorkflowController::class, 'selesaiPerbaikanUat']);
        Route::post('hasil-uji-keamanan', [AplikasiWorkflowController::class, 'hasilUjiKeamanan']);
        Route::post('selesai-perbaikan-keamanan', [AplikasiWorkflowController::class, 'selesaiPerbaikanKeamanan']);
        Route::post('deploy', [AplikasiWorkflowController::class, 'deployAplikasi']);
    });
}); // Close protected routes group
