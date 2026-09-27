<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE time_logs DROP INDEX time_logs_one_open_per_user');
            DB::statement('ALTER TABLE time_logs DROP COLUMN open_user_id');
        } else {
            DB::statement('DROP INDEX IF EXISTS time_logs_one_open_per_user');
        }

        Schema::table('subtasks', function (Blueprint $table) {
            $table->boolean('is_revision')->default(false);
            $table->text('revision_notes')->nullable();
            $table->foreignId('revision_of_subtask_id')->nullable()->constrained('subtasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subtasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_of_subtask_id');
            $table->dropColumn(['is_revision', 'revision_notes']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE time_logs ADD open_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (IF(ended_at IS NULL, user_id, NULL)) STORED');
            DB::statement('CREATE UNIQUE INDEX time_logs_one_open_per_user ON time_logs (open_user_id)');
        } else {
            DB::statement('CREATE UNIQUE INDEX time_logs_one_open_per_user ON time_logs (user_id) WHERE ended_at IS NULL');
        }
    }
};
