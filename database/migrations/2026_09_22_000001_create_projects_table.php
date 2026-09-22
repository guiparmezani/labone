<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('budget_cents');
            $table->unsignedInteger('planned_minutes');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->unsignedBigInteger('budget_cents')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('subtask_id')->constrained();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('source');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
            $table->index(['subtask_id', 'started_at']);
        });

        // Um ponto aberto por pessoa. MySQL não tem índice parcial; a coluna gerada vira null quando o ponto fecha.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE time_logs ADD open_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (IF(ended_at IS NULL, user_id, NULL)) STORED');
            DB::statement('CREATE UNIQUE INDEX time_logs_one_open_per_user ON time_logs (open_user_id)');
        } else {
            DB::statement('CREATE UNIQUE INDEX time_logs_one_open_per_user ON time_logs (user_id) WHERE ended_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('time_logs');
        Schema::dropIfExists('subtasks');
        Schema::dropIfExists('projects');
    }
};
