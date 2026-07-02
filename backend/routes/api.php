<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Enums\UserRole;
use App\Http\Controllers\AplikasiController;
use App\Http\Controllers\AplikasiWorkflowController;
use App\Http\Controllers\AplikasiDocumentController;
use App\Http\Controllers\AnalisaDesainController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonilController;
use App\Http\Controllers\RfcController;

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
        Route::get('personil/stats', [PersonilController::class, 'stats']);
        Route::get('personil', [PersonilController::class, 'index']);
        Route::post('personil', [PersonilController::class, 'store']);
        Route::put('personil/{id}', [PersonilController::class, 'update']);
        Route::patch('personil/{id}', [PersonilController::class, 'update']);
        Route::delete('personil/{id}', [PersonilController::class, 'destroy']);
        Route::delete('personil/{id}/force', [PersonilController::class, 'forceDestroy']);
        Route::post('personil/{id}/restore', [PersonilController::class, 'restore']);
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
        Route::get('aplikasi/{aplikasi}/documents', [AplikasiDocumentController::class, 'index']);
        Route::post('aplikasi/{aplikasi}/documents', [AplikasiDocumentController::class, 'store']);
        
        // Notes can be added by any role participating in the app
        Route::post('aplikasi/{aplikasi}/notes', [AplikasiWorkflowController::class, 'storeNote']);
        Route::put('aplikasi/{aplikasi}/notes/{note}', [AplikasiWorkflowController::class, 'updateNote']);
        Route::patch('aplikasi/{aplikasi}/notes/{note}', [AplikasiWorkflowController::class, 'updateNote']);
        Route::delete('aplikasi/{aplikasi}/notes/{note}', [AplikasiWorkflowController::class, 'destroyNote']);
        
        // Workflow data (including notes history and checklists) can be viewed by all roles
        Route::get('aplikasi/{aplikasi}/workflow', [AplikasiWorkflowController::class, 'index']);
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
        $analisaDesainIdPath,
        $rfcIdPath
    ) {
        // Aplikasi Management
        Route::put($aplikasiIdPath, [AplikasiController::class, 'update']);
        Route::patch($aplikasiIdPath, [AplikasiController::class, 'update']);
        Route::post($aplikasiIdPath.'/nonaktifkan', [AplikasiController::class, 'nonaktifkan']);
        Route::delete($aplikasiIdPath, [AplikasiController::class, 'destroy']);
        Route::post($aplikasiIdPath.'/restore', [AplikasiController::class, 'restore']);
        Route::get('aplikasi/trashed', [AplikasiController::class, 'trashed']);

        Route::post('aplikasi/{aplikasi}/checklists', [AplikasiWorkflowController::class, 'storeChecklist']);
        Route::put('aplikasi/{aplikasi}/checklists/{checklist}', [AplikasiWorkflowController::class, 'updateChecklist']);
        Route::patch('aplikasi/{aplikasi}/checklists/{checklist}', [AplikasiWorkflowController::class, 'updateChecklist']);
        Route::delete('aplikasi/{aplikasi}/checklists/{checklist}', [AplikasiWorkflowController::class, 'destroyChecklist']);

        // RFC Management
        Route::put($rfcIdPath, [RfcController::class, 'update']);
        Route::patch($rfcIdPath, [RfcController::class, 'update']);
        Route::delete($rfcIdPath, [RfcController::class, 'destroy']);
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
        Route::get('aplikasi/{aplikasi}/implementation-checklists', [AplikasiWorkflowController::class, 'implementationIndex']);
        Route::post('aplikasi/{aplikasi}/implementation-checklists', [AplikasiWorkflowController::class, 'implementationStore']);
        Route::put('aplikasi/{aplikasi}/implementation-checklists/{checklist}', [AplikasiWorkflowController::class, 'implementationUpdate']);
        Route::patch('aplikasi/{aplikasi}/implementation-checklists/{checklist}', [AplikasiWorkflowController::class, 'implementationUpdate']);
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
    Route::middleware('role:'
        .UserRole::PENGELOLA_APLIKASI->value.','
        .UserRole::DEVOPS_DEVELOPER->value
    )->group(function () {
        Route::get('aplikasi/{aplikasi}/deployment-status', [AplikasiWorkflowController::class, 'deploymentShow']);
    });
    Route::middleware('role:'.UserRole::DEVOPS_DEVELOPER->value)->group(function () {
        Route::put('aplikasi/{aplikasi}/deployment-status', [AplikasiWorkflowController::class, 'deploymentUpdate']);
        Route::patch('aplikasi/{aplikasi}/deployment-status', [AplikasiWorkflowController::class, 'deploymentUpdate']);
    });

    // Workflow Transitions
    Route::prefix('aplikasi/{aplikasi}/workflow')->group(function () {
        Route::get('histories', [AplikasiWorkflowController::class, 'statusHistories']);
        Route::post('verifikasi-pengajuan', [AplikasiWorkflowController::class, 'verifikasiPengajuan']);
        Route::post('perbaikan-pengajuan', [AplikasiWorkflowController::class, 'perbaikanPengajuan']);
        Route::post('studi-kelayakan', [AplikasiWorkflowController::class, 'studiKelayakan']);
        Route::post('mulai-analisa-desain', [AplikasiWorkflowController::class, 'mulaiAnalisaDesain']);
        Route::post('mulai-pengembangan', [AplikasiWorkflowController::class, 'mulaiPengembangan']);
        Route::post('siap-uat', [AplikasiWorkflowController::class, 'siapUat']);
        Route::post('verifikasi-uat', [AplikasiWorkflowController::class, 'verifikasiUat']);
        Route::post('selesai-perbaikan-uat', [AplikasiWorkflowController::class, 'selesaiPerbaikanUat']);
        Route::post('hasil-uji-keamanan', [AplikasiWorkflowController::class, 'hasilUjiKeamanan']);
        Route::post('selesai-perbaikan-keamanan', [AplikasiWorkflowController::class, 'selesaiPerbaikanKeamanan']);
        Route::post('deploy', [AplikasiWorkflowController::class, 'deployAplikasi']);
    });
}); // Close protected routes group
