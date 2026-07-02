<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Human-friendly landing page for backend service.
Route::get('/', function () {
    $appName = config('app.name', 'Sistem Manajemen Pengembangan Aplikasi');
    $apiRoot = url('/api');
    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $healthUrl = url('/health');
    $environment = app()->environment();

    $html = <<<HTML
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{$appName} - Backend API</title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background: #f6f7fb; color: #222; }
    .wrap { max-width: 720px; margin: 48px auto; padding: 0 16px; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
    h1 { margin: 0 0 8px; font-size: 24px; }
    p { margin: 0 0 14px; color: #4b5563; line-height: 1.5; }
    ul { margin: 0; padding-left: 18px; }
    li { margin: 8px 0; }
    .meta { margin-top: 14px; color: #6b7280; font-size: 13px; }
    .ok { display: inline-block; margin-bottom: 8px; color: #166534; font-size: 13px; }
  </style>
</head>
<body>
  <div class="wrap">
    <main class="card">
      <div class="ok">Backend Online</div>
      <h1>{$appName}</h1>
      <p>Layanan backend aktif. Gunakan tautan berikut:</p>
      <ul>
        <li><a href="{$frontendUrl}" target="_blank" rel="noopener noreferrer">Frontend</a></li>
        <li><a href="{$apiRoot}">API Root</a></li>
        <li><a href="{$healthUrl}">Health Check (JSON)</a></li>
      </ul>
      <div class="meta">Environment: {$environment}</div>
    </main>
  </div>
</body>
</html>
HTML;

    return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
});

// Machine-friendly health endpoint for monitoring.
Route::get('/health', function () {
  $appName = config('app.name', 'Sistem Manajemen Pengembangan Aplikasi');
  $criticalTables = ['users', 'aplikasis', 'migrations'];

  try {
    // Lightweight connection probe.
    DB::select('SELECT 1');

    $missingTables = collect($criticalTables)
      ->filter(fn (string $table) => !Schema::hasTable($table))
      ->values()
      ->all();

    if (!empty($missingTables)) {
      return response()->json([
        'status' => 'degraded',
        'service' => 'backend-api',
        'app' => $appName,
        'environment' => app()->environment(),
        'database' => [
          'ok' => false,
          'message' => 'Critical table missing',
          'missing_tables' => $missingTables,
        ],
        'timestamp' => now()->toIso8601String(),
      ], 503);
    }

    return response()->json([
      'status' => 'ok',
      'service' => 'backend-api',
      'app' => $appName,
      'environment' => app()->environment(),
      'database' => [
        'ok' => true,
        'message' => 'Connection and schema healthy',
      ],
      'timestamp' => now()->toIso8601String(),
    ]);
  } catch (\Throwable $e) {
    return response()->json([
      'status' => 'degraded',
      'service' => 'backend-api',
      'app' => $appName,
      'environment' => app()->environment(),
      'database' => [
        'ok' => false,
        'message' => $e->getMessage(),
      ],
      'timestamp' => now()->toIso8601String(),
    ], 503);
  }
});
