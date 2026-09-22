<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\SubtaskKind;
use App\Enums\TimeLogSource;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\Formato;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dados para o piloto local. Troque a senha antes de entregar ao cliente.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->person('Administrador', 'admin@labone.test', Role::Admin);
        $leader = $this->person('Marina Líder', 'lider@labone.test', Role::Leader);
        $joana = $this->person('Joana Operadora', 'joana@labone.test', Role::Operator);
        $carlos = $this->person('Carlos Operador', 'carlos@labone.test', Role::Operator);

        if (Project::query()->where('name', 'Molde da tampa')->exists()) {
            return;
        }

        $tampa = Project::query()->create([
            'name' => 'Molde da tampa',
            'notes' => 'Primeiro lote do piloto.',
            'status' => ProjectStatus::Open,
            'budget_cents' => 2500000,
            'planned_minutes' => 40 * 60,
            'created_by' => $leader->id,
        ]);
        $usinagem = $this->internal($tampa, 'Usinagem', $leader);
        $acabamento = $this->internal($tampa, 'Acabamento', $leader);
        $this->thirdParty($tampa, 'Tratamento térmico', 180000, $leader);

        $embalagem = Project::query()->create([
            'name' => 'Embalagem 500 ml',
            'status' => ProjectStatus::Open,
            'budget_cents' => 900000,
            'planned_minutes' => 16 * 60,
            'created_by' => $leader->id,
        ]);
        $this->internal($embalagem, 'Ajuste de cavidade', $leader);

        $antigo = Project::query()->create([
            'name' => 'Lote encerrado',
            'status' => ProjectStatus::Closed,
            'budget_cents' => 400000,
            'planned_minutes' => 8 * 60,
            'closed_at' => Carbon::parse('2026-08-28 18:00', Formato::TZ)->utc(),
            'created_by' => $admin->id,
        ]);
        $arquivo = $this->internal($antigo, 'Revisão final', $admin);

        $this->finished($carlos, $usinagem, '2026-09-08 07:30', '2026-09-08 11:30', $leader);
        $this->finished($joana, $acabamento, '2026-09-09 13:00', '2026-09-09 16:15', $leader);
        $this->finished($carlos, $arquivo, '2026-08-20 08:00', '2026-08-20 12:00', $admin);

        TimeLog::query()->create([
            'user_id' => $joana->id,
            'subtask_id' => $usinagem->id,
            'started_at' => Carbon::now(Formato::TZ)->subMinutes(25)->utc(),
            'ended_at' => null,
            'source' => TimeLogSource::Timer,
            'created_by' => $joana->id,
            'updated_by' => $joana->id,
        ]);
    }

    private function person(string $name, string $email, Role $role): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'senha-segura',
                'role' => $role,
                'active' => true,
            ],
        );
    }

    private function internal(Project $project, string $name, User $author): Subtask
    {
        return Subtask::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'kind' => SubtaskKind::Internal,
            'budget_cents' => null,
            'created_by' => $author->id,
        ]);
    }

    private function thirdParty(Project $project, string $name, int $cents, User $author): Subtask
    {
        return Subtask::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'kind' => SubtaskKind::ThirdParty,
            'budget_cents' => $cents,
            'created_by' => $author->id,
        ]);
    }

    private function finished(User $worker, Subtask $subtask, string $start, string $end, User $editor): void
    {
        TimeLog::query()->create([
            'user_id' => $worker->id,
            'subtask_id' => $subtask->id,
            'started_at' => Carbon::parse($start, Formato::TZ)->utc(),
            'ended_at' => Carbon::parse($end, Formato::TZ)->utc(),
            'source' => TimeLogSource::Manual,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);
    }
}
