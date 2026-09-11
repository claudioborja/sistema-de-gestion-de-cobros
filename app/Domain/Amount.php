<?php
declare(strict_types=1);
namespace App\Domain;
final class Amount
{
    public static function price(string $value): string
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
            throw new \InvalidArgumentException('Usa un importe positivo o cero, con punto y hasta dos decimales.');
        }
        return $parts[1].'.'.str_pad($parts[2] ?? '', 2, '0');
    }
}
