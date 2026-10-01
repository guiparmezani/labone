<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('hourly_rate_cents')->nullable()->after('active');
            $table->string('shift_start', 5)->nullable()->after('hourly_rate_cents');
            $table->string('shift_end', 5)->nullable()->after('shift_start');
            $table->string('shift_afternoon_start', 5)->nullable()->after('shift_end');
            $table->string('shift_afternoon_end', 5)->nullable()->after('shift_afternoon_start');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'hourly_rate_cents',
                'shift_start',
                'shift_end',
                'shift_afternoon_start',
                'shift_afternoon_end',
            ]);
        });
    }
};
