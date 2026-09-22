<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Reais, horas e horário de Brasília. O banco guarda centavos e UTC.
 */
class Formato
{
    public const TZ = 'America/Sao_Paulo';

    public static function reais(int $centavos): string
    {
        return 'R$ '.number_format($centavos / 100, 2, ',', '.');
    }

    public static function reaisEntrada(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }

    public static function minutos(int $minutos): string
    {
        $sinal = $minutos < 0 ? '-' : '';
        $minutos = abs($minutos);

        return sprintf('%s%dh %02dmin', $sinal, intdiv($minutos, 60), $minutos % 60);
    }

    public static function horasEntrada(int $minutos): string
    {
        return number_format($minutos / 60, 2, ',', '');
    }

    public static function centavos(?string $valor): ?int
    {
        $limpo = preg_replace('/[^\d,.-]/', '', trim((string) $valor)) ?? '';

        if ($limpo === '' || $limpo === '-') {
            return null;
        }

        if (str_contains($limpo, ',')) {
            $limpo = str_replace('.', '', $limpo);
            $limpo = str_replace(',', '.', $limpo);
        }

        if (! is_numeric($limpo)) {
            return null;
        }

        return (int) round(((float) $limpo) * 100);
    }

    public static function minutosDeHoras(?string $valor): ?int
    {
        $limpo = trim(str_replace(',', '.', (string) $valor));

        if ($limpo === '' || ! is_numeric($limpo)) {
            return null;
        }

        return (int) round(((float) $limpo) * 60);
    }

    public static function local(?CarbonInterface $utc): string
    {
        if ($utc === null) {
            return '';
        }

        return Carbon::instance($utc)->timezone(self::TZ)->format('Y-m-d\TH:i');
    }

    public static function dataHora(CarbonInterface $utc): string
    {
        return Carbon::instance($utc)->timezone(self::TZ)->format('d/m/Y H:i');
    }

    public static function hora(CarbonInterface $utc): string
    {
        return Carbon::instance($utc)->timezone(self::TZ)->format('H:i');
    }

    public static function data(CarbonInterface $utc): string
    {
        return Carbon::instance($utc)->timezone(self::TZ)->format('d/m/Y');
    }

    public static function interpretarLocal(string $valor): Carbon
    {
        return Carbon::parse($valor, self::TZ)->utc();
    }

    public static function horasCsv(int $minutos): string
    {
        return number_format($minutos / 60, 2, ',', '');
    }
}
