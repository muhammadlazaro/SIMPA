<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfcs', function (Blueprint $table) {
            $table->string('pelaksana')->nullable()->after('deskripsi');
            // keep status_tindaklanjut as string; values will be validated at request layer
        });
    }

    public function down(): void
    {
        Schema::table('rfcs', function (Blueprint $table) {
            $table->dropColumn('pelaksana');
        });
    }
};


