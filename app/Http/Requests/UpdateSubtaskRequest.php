<?php

namespace App\Http\Requests;

use App\Enums\SubtaskKind;
use App\Models\Subtask;
use App\Support\Formato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subtask = $this->route('subtask');

        return $subtask instanceof Subtask
            && ($this->user()?->can('update', $subtask) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $subtask = $this->route('subtask');
        $admin = $this->user()?->isAdmin() ?? false;
        $kind = (string) $this->input('kind');
        $revision = $this->boolean('is_revision');

        $planned = $admin || ! $subtask instanceof Subtask
            ? $this->hoursOrInvalid('planned_hours')
            : $subtask->planned_minutes;

        $budget = $this->moneyOrInvalid('budget');
        if (! $admin && $subtask instanceof Subtask && $kind === SubtaskKind::ThirdParty->value) {
            $budget = $subtask->kind === SubtaskKind::ThirdParty ? $subtask->budget_cents : null;
        }

        $alert = trim((string) $this->input('alert_percentage'));

        $this->merge([
            'budget_cents' => $budget,
            'planned_minutes' => $planned,
            'realized_cents' => $this->moneyOrInvalid('realized'),
            'alert_enabled' => $alert !== '',
            'alert_percentage' => $alert === '' ? null : $alert,
            'is_revision' => $revision,
            'revision_notes' => $revision ? ($this->input('revision_notes') ?: null) : null,
            'revision_of_subtask_id' => $revision ? ($this->input('revision_of_subtask_id') ?: null) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'kind' => ['required', Rule::enum(SubtaskKind::class)],
            'budget_cents' => [
                Rule::requiredIf(fn () => $this->user()?->isAdmin() && $this->input('kind') === SubtaskKind::ThirdParty->value),
                'nullable',
                'integer',
                'min:0',
            ],
            'is_revision' => ['boolean'],
            'revision_notes' => ['nullable', 'string', 'max:2000'],
            'revision_of_subtask_id' => [
                'nullable',
                'integer',
                Rule::exists('subtasks', 'id')->where(function ($query) {
                    $subtask = $this->route('subtask');
                    $query->where('kind', SubtaskKind::ThirdParty->value);

                    if ($subtask instanceof Subtask) {
                        $query->where('project_id', $subtask->project_id);
                    }
                }),
            ],
            'planned_minutes' => ['nullable', 'integer', 'min:0'],
            'realized_cents' => ['nullable', 'integer', 'min:0'],
            'alert_enabled' => ['boolean'],
            'alert_percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da subtarefa.',
            'name.min' => 'O nome precisa ter pelo menos 2 caracteres.',
            'kind.required' => 'Escolha o tipo da subtarefa.',
            'budget_cents.required' => 'Informe o valor previsto.',
            'budget_cents.integer' => 'Informe o valor previsto em reais.',
            'budget_cents.min' => 'O valor previsto não pode ser negativo.',
            'planned_minutes.integer' => 'Informe o tempo previsto com um número.',
            'planned_minutes.min' => 'O tempo previsto não pode ser negativo.',
            'realized_cents.integer' => 'Informe o valor realizado em reais.',
            'realized_cents.min' => 'O valor realizado não pode ser negativo.',
            'alert_percentage.integer' => 'A porcentagem precisa ser um número de 1 a 100.',
            'alert_percentage.min' => 'A porcentagem precisa ser um número de 1 a 100.',
            'alert_percentage.max' => 'A porcentagem precisa ser um número de 1 a 100.',
            'revision_notes.max' => 'A descrição da revisão pode ter no máximo 2000 caracteres.',
            'revision_of_subtask_id.exists' => 'A revisão precisa apontar para uma equipe terceira deste projeto.',
        ];
    }

    /**
     * Vazio vira null. Texto que não é dinheiro vira um valor inválido para a regra integer.
     */
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
}
