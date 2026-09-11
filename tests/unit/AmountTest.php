<?php
namespace Tests\Unit;
use App\Domain\Amount;
use CodeIgniter\Test\CIUnitTestCase;
final class AmountTest extends CIUnitTestCase
{
    public function testExactPriceAvoidsFloatRounding(): void
    {
        $this->assertTrue(class_exists(Amount::class), 'El dominio debe validar importes exactos.');
        $this->assertSame('100.10', Amount::price('100.1'));
        $this->assertSame('0.00', Amount::price('0'));
        $this->assertSame('9999999999999999.99', Amount::price('9999999999999999.99'));
    }
    public function testRejectsAmbiguousOrOutOfRangeMoney(): void
    {
        $this->assertTrue(class_exists(Amount::class));
        foreach (['-1', '1e3', '1,20', '1.999', '10000000000000000', 'NaN'] as $value) {
            try { Amount::price($value); $this->fail('Aceptó importe inválido: '.$value); }
            catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }
}
