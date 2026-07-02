<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize status: "Analisa & Desain" -> "Analisa dan Desain"
        DB::table('aplikasis')
            ->where('status', 'Analisa & Desain')
            ->update(['status' => 'Analisa dan Desain']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert: "Analisa dan Desain" -> "Analisa & Desain"
        DB::table('aplikasis')
            ->where('status', 'Analisa dan Desain')
            ->update(['status' => 'Analisa & Desain']);
    }
};
