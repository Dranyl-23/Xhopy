<?php

namespace App\Domain\Catalog\Services;

final class MoneyFormatter
{
    public static function toCentavos(string|int|float $amount): int
    {
        $normalized = str_replace([',', ' '], '', trim((string) $amount));
        if ($normalized === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            return 0;
        }

        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $padded = substr(str_pad($fraction, 2, '0'), 0, 2);
        $centavos = ((int) $whole * 100) + (int) $padded;

        return $negative ? -$centavos : $centavos;
    }

    public static function format(string|int|float $amount, string $currency = 'PHP'): string
    {
        $centavos = self::toCentavos($amount);
        $negative = $centavos < 0;
        $absCentavos = abs($centavos);
        $whole = intdiv($absCentavos, 100);
        $cents = $absCentavos % 100;

        $symbol = match (strtoupper($currency)) {
            'PHP' => '₱',
            'USD' => '$',
            default => strtoupper($currency).' ',
        };

        $formatted = number_format($whole, 0, '.', ',').'.'.str_pad((string) $cents, 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$symbol.$formatted;
    }

    public static function toFloatAmount(string|int|float $amount): float
    {
        return round(self::toCentavos($amount) / 100, 2);
    }

    public static function discountLabel(
        string|int|float $currentAmount,
        string|int|float|null $compareAtAmount,
        string $currency = 'PHP',
    ): ?string {
        if ($compareAtAmount === null) {
            return null;
        }

        $current = self::toCentavos($currentAmount);
        $original = self::toCentavos($compareAtAmount);

        if ($original <= $current) {
            return null;
        }

        $savings = $original - $current;
        $whole = intdiv($savings, 100);
        $cents = $savings % 100;
        $symbol = match (strtoupper($currency)) {
            'PHP' => '₱',
            'USD' => '$',
            default => strtoupper($currency).' ',
        };

        $formattedSavings = $cents === 0
            ? number_format($whole, 0, '.', ',')
            : number_format($whole, 0, '.', ',').'.'.str_pad((string) $cents, 2, '0', STR_PAD_LEFT);

        return 'Save '.$symbol.$formattedSavings;
    }
}
