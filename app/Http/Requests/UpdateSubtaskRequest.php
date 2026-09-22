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
        return [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'kind' => ['required', Rule::enum(SubtaskKind::class)],
            'budget_cents' => [
                Rule::requiredIf(fn () => $this->input('kind') === SubtaskKind::ThirdParty->value),
                'nullable',
                'integer',
                'min:0',
            ],
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
            'budget_cents.required' => 'Informe o orçamento da equipe terceira.',
            'budget_cents.min' => 'O orçamento não pode ser negativo.',
        ];
    }
}
