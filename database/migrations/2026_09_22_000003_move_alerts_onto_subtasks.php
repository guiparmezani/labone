<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtasks', function (Blueprint $table) {
            $table->unsignedInteger('planned_minutes')->nullable()->after('budget_cents');
            $table->unsignedBigInteger('realized_cents')->nullable()->after('planned_minutes');
            $table->unsignedTinyInteger('alert_percentage')->nullable()->after('realized_cents');
            $table->boolean('alert_enabled')->default(false)->after('alert_percentage');
        });

        Schema::dropIfExists('project_alerts');
    }

    public function down(): void
    {
        Schema::table('subtasks', function (Blueprint $table) {
            $table->dropColumn([
                'planned_minutes',
                'realized_cents',
                'alert_percentage',
                'alert_enabled',
            ]);
        });
    }
};
