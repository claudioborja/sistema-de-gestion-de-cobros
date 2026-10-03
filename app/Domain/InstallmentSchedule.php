<?php

declare(strict_types=1);

namespace App\Domain;

final class InstallmentSchedule
{
    public static function monthly(string $total, mixed $months, mixed $firstDueOn, string $issuedOn): array
    {
        if ((!is_string($months) && !is_int($months)) || !preg_match('/^[1-9][0-9]{0,2}$/D', (string) $months)
            || (int) $months > 120) {
            throw new \InvalidArgumentException('El plazo debe ser un número entero entre 1 y 120 meses.');
        }
        if (!is_string($firstDueOn) || !self::isDate($firstDueOn) || !self::isDate($issuedOn) || $firstDueOn < $issuedOn) {
            throw new \InvalidArgumentException('La primera cuota debe tener una fecha válida igual o posterior a la emisión.');
        }
        if (Money::cents($total) < (int) $months) {
            throw new \InvalidArgumentException('Reduce el plazo: cada cuota debe ser de al menos un centavo.');
        }
        $first = new \DateTimeImmutable($firstDueOn);
        $monthStart = $first->modify('first day of this month');
        $anchor = (int) $first->format('d');
        $dates = [];
        for ($index = 0; $index < (int) $months; $index++) {
            $month = $monthStart->modify('+' . $index . ' months');
            $date = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min($anchor, (int) $month->format('t')))->format('Y-m-d');
            if (!self::isDate($date)) {
                throw new \InvalidArgumentException('El calendario excede el rango de fechas permitido.');
            }
            $dates[] = $date;
        }

        return self::equal($total, $dates);
    }

    public static function equal(string $total, array $dueDates): array
    {
        if ($dueDates === []) {
            throw new \InvalidArgumentException('Define al menos una cuota.');
        }

        $totalCents = Money::cents($total);
        $count = count($dueDates);
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;
        $schedule = [];

        foreach (array_values($dueDates) as $index => $date) {
            if (!is_string($date) || !self::isDate($date)) {
                throw new \InvalidArgumentException('Las fechas de vencimiento no son válidas.');
            }
            $schedule[] = [
                'number' => $index + 1,
                'due_date' => $date,
                'amount' => Money::decimal($base + ($index >= $count - $remainder ? 1 : 0)),
            ];
        }

        return $schedule;
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
