<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fund_settings', function (Blueprint $table) {
            $table->foreignId('auditor_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE fund_settings ADD CONSTRAINT independent_auditor CHECK (auditor_id IS NULL OR treasurer_id IS NULL OR auditor_id <> treasurer_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE fund_settings DROP CHECK independent_auditor');
        Schema::table('fund_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auditor_id');
        });
    }
};
