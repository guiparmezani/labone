<?php

namespace Tests\Feature;

use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\TimeClock;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlTimeLogSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Run against the dedicated MySQL regression database.');
        }

        $this->assertSame('labone_timer_regression_test', DB::connection()->getDatabaseName());
        DB::statement("SET SESSION time_zone = '-03:00'");
        DB::statement('SET SESSION explicit_defaults_for_timestamp = OFF');
    }

    public function test_nova_instalacao_preserva_inicio_com_defaults_legados_do_mysql(): void
    {
        $this->artisan('migrate:fresh', [
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/2026_09_22_000001_create_projects_table.php',
            ],
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertTimerKeepsItsStart();
    }

    public function test_migracao_corrige_schema_existente_sem_reescrever_lancamentos(): void
    {
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();
        $existing = TimeLog::factory()->create();
        $before = DB::table('time_logs')->where('id', $existing->id)->first();
        DB::statement('ALTER TABLE time_logs MODIFY started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        $migration = require database_path('migrations/2026_10_09_182744_prevent_time_log_start_from_auto_updating.php');
        $migration->up();

        $this->assertEquals($before, DB::table('time_logs')->where('id', $existing->id)->first());
        $this->assertTimerKeepsItsStart();
    }

    private function assertTimerKeepsItsStart(): void
    {
        $operator = User::factory()->operator()->create();
        $editor = User::factory()->leader()->create();
        $subtask = Subtask::factory()->create();
        $clock = app(TimeClock::class);
        $start = now()->startOfSecond();
        $this->travelTo($start);
        $log = $clock->start($operator, $subtask);
        $originalStart = $log->refresh()->getRawOriginal('started_at');

        $this->travelTo($start->copy()->addMinutes(15));
        $clock->stop($operator, $log->id);
        $log->refresh();

        $this->assertSame($originalStart, $log->getRawOriginal('started_at'));
        $this->assertSame(15, $log->minutes());
        $report = DB::table('time_logs')->where('id', $log->id)
            ->selectRaw(TimeLog::durationSql().' AS minutes')->first();
        $this->assertSame(15, (int) $report->minutes);

        $log->forceFill(['updated_by' => $editor->id])->save();
        $this->assertSame($originalStart, $log->refresh()->getRawOriginal('started_at'));
        $this->assertSame(15, $log->minutes());
    }
}
