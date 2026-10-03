<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\InstallmentSchedule;
use CodeIgniter\Test\CIUnitTestCase;

final class MonthlyCreditTest extends CIUnitTestCase
{
    public function testMonthlyPlanKeepsAnchorDayAndExactTotal(): void
    {
        $this->assertTrue(method_exists(InstallmentSchedule::class, 'monthly'));
        $rows = InstallmentSchedule::monthly('100.00', '3', '2028-01-31', '2028-01-10');
        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], array_column($rows, 'due_date'));
        $this->assertSame(['33.33', '33.33', '33.34'], array_column($rows, 'amount'));
    }

    public function testTwelveMonthsCrossYearAndShortMonthWithoutDateDrift(): void
    {
        $this->assertTrue(method_exists(InstallmentSchedule::class, 'monthly'));
        $rows = InstallmentSchedule::monthly('1200.00', '12', '2026-08-31', '2026-08-01');
        $this->assertCount(12, $rows);
        $this->assertSame('2027-02-28', $rows[6]['due_date']);
        $this->assertSame('2027-03-31', $rows[7]['due_date']);
        $this->assertSame('2027-07-31', $rows[11]['due_date']);
        $this->assertSame(array_fill(0, 12, '100.00'), array_column($rows, 'amount'));
    }

    public function testInvalidTermsNeverProduceASchedule(): void
    {
        $this->assertTrue(method_exists(InstallmentSchedule::class, 'monthly'));
        foreach (['0', '-1', '1.5', '121', 'abc', [], ''] as $months) {
            try {
                InstallmentSchedule::monthly('100.00', $months, '2026-10-01', '2026-09-01');
                $this->fail('Aceptó un plazo inválido.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (['2026-02-30', '2026-08-31', [], ''] as $first) {
            try {
                InstallmentSchedule::monthly('100.00', '6', $first, '2026-09-01');
                $this->fail('Aceptó un vencimiento inválido o anterior a la venta.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        InstallmentSchedule::monthly('0.02', '3', '2026-10-01', '2026-09-01');
    }
}
