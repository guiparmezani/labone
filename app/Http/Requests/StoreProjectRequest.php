<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\Formato;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->isOperator()) {
            $this->merge(['budget_cents' => 0, 'planned_minutes' => 0]);

            return;
        }

        $this->merge([
            'budget_cents' => $this->orcamentoEmCentavos(),
            'planned_minutes' => Formato::minutosDeHoras($this->input('planned_hours')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'budget' => ['nullable', 'string'],
            'budget_cents' => ['required', 'integer', 'min:0'],
            'planned_hours' => ['required', 'string'],
            'planned_minutes' => ['required', 'integer', 'min:0'],
        ];

        if ($this->user()?->isOperator()) {
            unset($rules['budget'], $rules['planned_hours']);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do projeto.',
            'name.min' => 'O nome precisa ter pelo menos 2 caracteres.',
            'name.max' => 'O nome pode ter no máximo 160 caracteres.',
            'notes.max' => 'As observações podem ter no máximo 2000 caracteres.',
            'budget_cents.required' => 'Informe o orçamento em reais.',
            'budget_cents.integer' => 'Informe o orçamento em reais.',
            'budget_cents.min' => 'O orçamento não pode ser negativo.',
            'planned_hours.required' => 'Informe as horas previstas.',
            'planned_minutes.required' => 'Informe as horas previstas.',
            'planned_minutes.min' => 'As horas previstas não podem ser negativas.',
        ];
    }

    protected function orcamentoEmCentavos(): ?int
    {
        $valor = $this->input('budget');

        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return 0;
        }

        return Formato::centavos(is_string($valor) ? $valor : null);
    }
}
