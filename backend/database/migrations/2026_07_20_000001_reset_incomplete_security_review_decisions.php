<?php

use App\Models\Aplikasi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('aplikasis')
            ->where('status', Aplikasi::STATUS_UJI_KEAMANAN)
            ->whereNotNull('security_test_passed')
            ->update([
                'security_test_passed' => null,
                'security_test_notes' => null,
                'security_tested_by' => null,
                'security_tested_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Keputusan parsial tidak dapat direkonstruksi dengan aman.
    }
};
