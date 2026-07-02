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
            // Deployment staging tracking
            $table->timestamp('deployed_staging_at')->nullable()->after('security_test_notes');
            $table->foreignId('deployed_staging_by')->nullable()->constrained('users')->nullOnDelete()->after('deployed_staging_at');

            // Deployment production tracking
            $table->timestamp('deployed_production_at')->nullable()->after('deployed_staging_by');
            $table->foreignId('deployed_production_by')->nullable()->constrained('users')->nullOnDelete()->after('deployed_production_at');

            // Optional deployment notes
            $table->string('deployment_notes', 500)->nullable()->after('deployed_production_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aplikasis', function (Blueprint $table) {
            $table->dropForeign(['deployed_staging_by']);
            $table->dropForeign(['deployed_production_by']);
            $table->dropColumn([
                'deployed_staging_at',
                'deployed_staging_by',
                'deployed_production_at',
                'deployed_production_by',
                'deployment_notes',
            ]);
        });
    }
};
