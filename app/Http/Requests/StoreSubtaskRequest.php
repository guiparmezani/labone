<?php

namespace App\Http\Requests;

use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Support\Formato;
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

        $kind = $this->input('kind');
        $this->merge([
            'budget_cents' => $kind === SubtaskKind::ThirdParty->value
                ? Formato::centavos($this->input('budget'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:160'],
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

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da subtarefa.',
            'name.min' => 'O nome precisa ter pelo menos 2 caracteres.',
            'name.max' => 'O nome pode ter no máximo 160 caracteres.',
            'kind.required' => 'Escolha o tipo da subtarefa.',
            'budget_cents.required' => 'Informe o orçamento da equipe terceira.',
            'budget_cents.min' => 'O orçamento não pode ser negativo.',
        ];
    }

    public function project(): Project
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $project;
    }
}
