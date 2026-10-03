<?php
namespace Tests\Unit;
use App\Domain\SubscriptionCalendar;
use CodeIgniter\Test\CIUnitTestCase;
final class SubscriptionCalendarTest extends CIUnitTestCase
{
    public function testMonthEndKeepsOriginalAnchor(): void
    {
        $p = SubscriptionCalendar::periods('2026-01-31', 1, 'ANCLA', '20.00', 'ANTICIPADO', 5, 3);
        $this->assertSame(['2026-01-31','2026-02-28','2026-03-31'], array_column($p, 'start'));
        $this->assertSame('2026-04-30', $p[2]['end']);
        $this->assertSame('2026-02-05', $p[0]['due']);
    }
    public function testProrationAndArrearsUseCivilDates(): void
    {
        $p = SubscriptionCalendar::periods('2026-04-16', 1, 'PROPORCIONAL', '60.00', 'VENCIDO', 10, 3);
        $this->assertSame('30.00', $p[0]['amount']);
        $this->assertSame('2026-05-01', $p[0]['eligible']);
        $this->assertSame('2026-05-11', $p[0]['due']);
        $this->assertSame('60.00', $p[1]['amount']);
    }
    public function testLeapAnniversaryReturnsToFebruary29(): void
    {
        $p = SubscriptionCalendar::periods('2024-02-29', 12, 'ANCLA', '120.00', 'ANTICIPADO', 0, 5);
        $this->assertSame('2025-02-28', $p[1]['start']);
        $this->assertSame('2028-02-29', $p[4]['start']);
    }
    public function testRejectsInvalidCalendarDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SubscriptionCalendar::periods('2026-02-30', 1, 'ANCLA', '20.00', 'ANTICIPADO', 0, 3);
    }
}
