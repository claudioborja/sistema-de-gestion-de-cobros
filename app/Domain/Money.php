<?php

declare(strict_types=1);

namespace App\Domain;

final class Money
{
    public static function cents(string $value, bool $allowZero = false): int
    {
        $normalized = Amount::price(trim($value));
        [$units, $cents] = explode('.', $normalized, 2);
        $result = ((int) $units * 100) + (int) $cents;
        if (!$allowZero && $result === 0) {
            throw new \InvalidArgumentException('El importe debe ser mayor que cero.');
        }

        return $result;
    }

    public static function decimal(int $cents): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('El importe no puede ser negativo.');
        }

        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
