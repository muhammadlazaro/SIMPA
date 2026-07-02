<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('aplikasis', function (Blueprint $table) {
            $table->boolean('security_test_passed')->nullable()->after('doc_studi_kelayakan_path');
            $table->foreignId('security_tested_by')->nullable()->after('security_test_passed')->constrained('users')->nullOnDelete();
            $table->timestamp('security_tested_at')->nullable()->after('security_tested_by');
            $table->text('security_test_notes')->nullable()->after('security_tested_at');

            $table->index(['security_test_passed', 'status'], 'aplikasis_security_passed_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aplikasis', function (Blueprint $table) {
            $table->dropIndex('aplikasis_security_passed_status_idx');
            $table->dropConstrainedForeignId('security_tested_by');
            $table->dropColumn(['security_test_passed', 'security_tested_at', 'security_test_notes']);
        });
    }
};
