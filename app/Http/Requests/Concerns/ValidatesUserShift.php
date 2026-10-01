<?php

namespace App\Http\Requests\Concerns;

use App\Support\Formato;
use Illuminate\Validation\Validator;

trait ValidatesUserShift
{
    protected function mergeShift(): void
    {
        $this->merge([
            'hourly_rate_cents' => $this->valorHoraEmCentavos(),
            'shift_start' => $this->hora('shift_start'),
            'shift_end' => $this->hora('shift_end'),
            'shift_afternoon_start' => $this->hora('shift_afternoon_start'),
            'shift_afternoon_end' => $this->hora('shift_afternoon_end'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function shiftRules(): array
    {
        return [
            'hourly_rate' => ['nullable', 'string'],
            'hourly_rate_cents' => ['nullable', 'integer', 'min:0'],
            'shift_start' => ['nullable', 'date_format:H:i'],
            'shift_end' => ['nullable', 'date_format:H:i'],
            'shift_afternoon_start' => ['nullable', 'date_format:H:i'],
            'shift_afternoon_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function shiftMessages(): array
    {
        return [
            'hourly_rate_cents.integer' => 'Informe o valor hora em reais.',
            'hourly_rate_cents.min' => 'O valor hora não pode ser negativo.',
            'shift_start.date_format' => 'Informe o início da jornada, como 07:00.',
            'shift_end.date_format' => 'Informe o fim da jornada, como 17:00.',
            'shift_afternoon_start.date_format' => 'Informe o início da segunda jornada, como 13:00.',
            'shift_afternoon_end.date_format' => 'Informe o fim da segunda jornada, como 18:00.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $inicio = $this->input('shift_start');
            $fim = $this->input('shift_end');
            $tardeInicio = $this->input('shift_afternoon_start');
            $tardeFim = $this->input('shift_afternoon_end');

            if (($inicio === null) xor ($fim === null)) {
                $validator->errors()->add('shift_start', 'Informe o início e o fim da jornada.');
            }

            if (is_string($inicio) && is_string($fim) && $inicio >= $fim) {
                $validator->errors()->add('shift_end', 'O fim da jornada precisa ser depois do início.');
            }

            if (($tardeInicio === null) xor ($tardeFim === null)) {
                $validator->errors()->add('shift_afternoon_start', 'Informe o início e o fim da segunda jornada.');
            }

            if (($tardeInicio !== null || $tardeFim !== null) && ($inicio === null || $fim === null)) {
                $validator->errors()->add('shift_afternoon_start', 'Adicione a primeira jornada antes da segunda.');
            }

            if (is_string($tardeInicio) && is_string($tardeFim) && $tardeInicio >= $tardeFim) {
                $validator->errors()->add('shift_afternoon_end', 'O fim da segunda jornada precisa ser depois do início.');
            }

            if (is_string($tardeInicio) && is_string($fim) && $tardeInicio < $fim) {
                $validator->errors()->add('shift_afternoon_start', 'A segunda jornada começa depois do fim da primeira.');
            }
        });
    }

    private function hora(string $key): ?string
    {
        $valor = $this->input($key);

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return trim($valor);
    }

    private function valorHoraEmCentavos(): int|string|null
    {
        $valor = $this->input('hourly_rate');

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return Formato::centavos($valor) ?? 'invalido';
    }
}
