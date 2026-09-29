<?php

namespace App\Http\Requests;

use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Models\Subtask;
use App\Support\Formato;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);

        if ($this->user()?->isOperator()) {
            $this->merge([
                'kind' => SubtaskKind::Internal->value,
                'budget_cents' => null,
                'planned_minutes' => null,
                'realized_cents' => null,
                'alert_percentage' => null,
                'alert_enabled' => false,
            ]);

            return;
        }

        $kind = (string) $this->input('kind');
        $revision = $this->boolean('is_revision');

        $alert = trim((string) $this->input('alert_percentage'));

        $this->merge([
            'budget_cents' => $this->moneyOrInvalid('budget'),
            'planned_minutes' => $this->hoursOrInvalid('planned_hours'),
            'realized_cents' => $this->moneyOrInvalid('realized'),
            'alert_enabled' => $alert !== '',
            'alert_percentage' => $alert === '' ? null : $alert,
            'is_revision' => $revision,
            'revision_notes' => trim((string) $this->input('revision_notes')) ?: null,
            'revision_of_subtask_id' => $revision ? ($this->input('revision_of_subtask_id') ?: null) : null,
            'kind' => $kind,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $projectId = $this->project()->id;

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:160', $this->uniqueName($projectId)],
        ];

        if ($this->user()?->isOperator()) {
            return $rules;
        }

        $rules['kind'] = ['required', Rule::enum(SubtaskKind::class)];
        $rules['budget_cents'] = [
            Rule::requiredIf(fn () => $this->input('kind') === SubtaskKind::ThirdParty->value),
            'nullable',
            'integer',
            'min:0',
        ];
        $rules['planned_minutes'] = ['nullable', 'integer', 'min:0'];
        $rules['realized_cents'] = ['nullable', 'integer', 'min:0'];
        $rules['alert_enabled'] = ['boolean'];
        $rules['alert_percentage'] = ['nullable', 'integer', 'min:1', 'max:100'];
        $rules['is_revision'] = ['boolean'];
        $rules['revision_notes'] = ['nullable', 'string', 'max:2000'];
        $rules['revision_of_subtask_id'] = [
            'nullable',
            'integer',
            Rule::exists('subtasks', 'id')->where(function ($query) {
                $project = $this->route('project');
                $query->where('kind', SubtaskKind::ThirdParty->value);

                if ($project instanceof Project) {
                    $query->where('project_id', $project->id);
                }
            }),
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da tarefa.',
            'name.min' => 'O nome precisa ter pelo menos 2 caracteres.',
            'name.max' => 'O nome pode ter no máximo 160 caracteres.',
            'kind.required' => 'Escolha o tipo da tarefa.',
            'budget_cents.required' => 'Informe o orçamento da equipe terceira.',
            'budget_cents.integer' => 'Informe o valor previsto em reais.',
            'budget_cents.min' => 'O orçamento não pode ser negativo.',
            'planned_minutes.integer' => 'Informe o tempo previsto com um número.',
            'planned_minutes.min' => 'O tempo previsto não pode ser negativo.',
            'realized_cents.integer' => 'Informe o valor realizado em reais.',
            'realized_cents.min' => 'O valor realizado não pode ser negativo.',
            'alert_percentage.integer' => 'A porcentagem precisa ser um número de 1 a 100.',
            'alert_percentage.min' => 'A porcentagem precisa ser um número de 1 a 100.',
            'alert_percentage.max' => 'A porcentagem precisa ser um número de 1 a 100.',
            'revision_notes.max' => 'A descrição pode ter no máximo 2000 caracteres.',
            'revision_of_subtask_id.exists' => 'A revisão precisa apontar para uma equipe terceira deste projeto.',
        ];
    }

    private function moneyOrInvalid(string $field): int|string|null
    {
        $raw = trim((string) $this->input($field));

        if ($raw === '') {
            return null;
        }

        return Formato::centavos($raw) ?? 'invalid';
    }

    private function hoursOrInvalid(string $field): int|string|null
    {
        $raw = trim((string) $this->input($field));

        if ($raw === '') {
            return null;
        }

        return Formato::minutosDeHoras($raw) ?? 'invalid';
    }

    private function uniqueName(int $projectId, ?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($projectId, $ignoreId): void {
            if (Subtask::nameTaken($projectId, (string) $value, $ignoreId)) {
                $fail('Já existe uma tarefa com este nome neste projeto.');
            }
        };
    }

    public function project(): Project
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $project;
    }
}
