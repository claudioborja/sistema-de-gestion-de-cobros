<?php
namespace Tests\Unit;
use App\Domain\ClientInput;
use CodeIgniter\Test\CIUnitTestCase;
final class ClientInputTest extends CIUnitTestCase
{
    public function testNormalizesIdentityWithoutInventingUnknownFields(): void
    {
        $this->assertTrue(class_exists(ClientInput::class));
        $data = ClientInput::validate(['nombre'=>'  María   Pérez ', 'identificacion'=>' ab-123 ', 'tipo'=>'PASAPORTE', 'pais'=>'ec', 'saldo'=>'999']);
        $this->assertSame('María Pérez', $data['nombre']);
        $this->assertSame('AB123', $data['identificacion']);
        $this->assertArrayNotHasKey('saldo', $data);
        $this->assertSame('', ClientInput::validate(['nombre'=>'Sin identificación'])['identificacion']);
    }
    public function testMissingNameAndInvalidEmailAreRejected(): void
    {
        $this->assertTrue(class_exists(ClientInput::class));
        $this->expectException(\App\Domain\ValidationException::class);
        ClientInput::validate(['nombre'=>'', 'email'=>'not-an-email']);
    }
}
