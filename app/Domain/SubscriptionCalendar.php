<?php

declare(strict_types=1);
namespace App\Domain;

/** Civil periods [start, end), always calculated from the original anchor. */
final class SubscriptionCalendar
{
    public static function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('America/Guayaquil'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Indica una fecha válida.');
        }
        return $date;
    }

    public static function periods(string $start, int $months, string $policy, string $amount, string $billing, int $days, int $count = 3): array
    {
        $anchor = self::date($start);
        if (!in_array($months, [1,12], true) || !in_array($policy, ['ANCLA','PROPORCIONAL','COMPLETO'], true)
            || ($months === 12 && $policy !== 'ANCLA') || !in_array($billing, ['ANTICIPADO','VENCIDO'], true)
            || $days < 0 || $days > 365 || $count < 1 || $count > 1200) {
            throw new \InvalidArgumentException('Revisa la frecuencia, calendario y plazo de pago.');
        }
        $cents = Money::cents($amount);
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $from = $i === 0 ? $anchor : self::boundary($anchor, $i * $months, $policy);
            $to = self::boundary($anchor, ($i + 1) * $months, $policy);
            $price = $cents;
            if ($i === 0 && $policy === 'PROPORCIONAL') {
                $covered = (int) $anchor->diff($to)->days;
                $total = (int) $anchor->format('t');
                // Integer HALF_UP; divide before multiplying to avoid overflow.
                $price = intdiv($cents, $total) * $covered + intdiv(($cents % $total) * $covered * 2 + $total, $total * 2);
            }
            if ($price < 1) { throw new \InvalidArgumentException('El importe proporcional debe alcanzar al menos un centavo.'); }
            $eligible = $billing === 'ANTICIPADO' ? $from : $to;
            $rows[] = ['start'=>$from->format('Y-m-d'), 'end'=>$to->format('Y-m-d'), 'last'=>$to->modify('-1 day')->format('Y-m-d'),
                'eligible'=>$eligible->format('Y-m-d'), 'due'=>$eligible->modify('+' . $days . ' days')->format('Y-m-d'), 'amount'=>Money::decimal($price)];
        }
        return $rows;
    }

    private static function boundary(\DateTimeImmutable $anchor, int $months, string $policy): \DateTimeImmutable
    {
        $month = $anchor->modify('first day of this month')->modify('+' . $months . ' months');
        $day = $policy === 'ANCLA' ? min((int) $anchor->format('j'), (int) $month->format('t')) : 1;
        return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), $day);
    }
}
