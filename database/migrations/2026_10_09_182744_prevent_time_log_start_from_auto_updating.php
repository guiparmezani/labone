<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE time_logs MODIFY started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
    }

    /**
     * Keep the start immutable when rolling back; automatic updates corrupt elapsed time.
     */
    public function down(): void {}
};
