<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FinancialDocumentService;
use App\Services\PaymentService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class PortfolioOverdueTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $refresh = true;

    public function testPaidHistoricalInstallmentDoesNotMakeFutureDebtOverdue(): void
    {
        $fixture = $this->receivable([$this->date('-10 days'), $this->date('+10 days')]);
        $this->pay($fixture, 0, '50.00');

        $row = $this->portfolio($fixture);
        $this->assertSame('CONFIRMADO', $row['estado']);
        $this->assertSame($this->date('+10 days'), $row['vencimiento']);
        $this->assertSame('50.00', $row['saldo']);
    }

    public function testPartiallyPaidHistoricalInstallmentRemainsOverdue(): void
    {
        $fixture = $this->receivable([$this->date('-10 days'), $this->date('+10 days')]);
        $this->pay($fixture, 0, '20.00');

        $row = $this->portfolio($fixture);
        $this->assertSame('VENCIDA', $row['estado']);
        $this->assertSame($this->date('-10 days'), $row['vencimiento']);
        $this->assertSame('80.00', $row['saldo']);
    }

    public function testFullyRecoveredObligationHasNoPendingDueDate(): void
    {
        $fixture = $this->receivable([$this->date('-10 days')]);
        $this->pay($fixture, 0, '100.00');

        $row = $this->portfolio($fixture);
        $this->assertSame('RECUPERADA', $row['estado']);
        $this->assertNull($row['vencimiento']);
        $this->assertSame('0.00', $row['saldo']);
    }

    public function testInstallmentDueTodayIsNotOverdue(): void
    {
        $fixture = $this->receivable([$this->date('today')]);

        $row = $this->portfolio($fixture);
        $this->assertSame('CONFIRMADO', $row['estado']);
        $this->assertSame($this->date('today'), $row['vencimiento']);
        $this->assertSame('100.00', $row['saldo']);
    }

    public function testReversingPaymentRestoresHistoricalOverdueInstallment(): void
    {
        $fixture = $this->receivable([$this->date('-10 days'), $this->date('+10 days')]);
        $paymentId = $this->pay($fixture, 0, '50.00');
        (new PaymentService())->reversePayment($fixture['actor_id'], $paymentId, 'Transferencia registrada por error', 'portfolio-reverse-payment');

        $row = $this->portfolio($fixture);
        $this->assertSame('VENCIDA', $row['estado']);
        $this->assertSame($this->date('-10 days'), $row['vencimiento']);
        $this->assertSame('100.00', $row['saldo']);
    }

    public function testReversingApplicationRestoresHistoricalOverdueInstallment(): void
    {
        $fixture = $this->receivable([$this->date('-10 days'), $this->date('+10 days')]);
        $paymentId = $this->pay($fixture, 0, '50.00');
        $application = $this->db->table('aplicaciones_pago')->where('pago_id', $paymentId)->get()->getRowArray();
        (new PaymentService())->reverseApplication($fixture['actor_id'], (int) $application['id'], 'Aplicación registrada por error', 'portfolio-reverse-application');

        $row = $this->portfolio($fixture);
        $this->assertSame('VENCIDA', $row['estado']);
        $this->assertSame($this->date('-10 days'), $row['vencimiento']);
        $this->assertSame('100.00', $row['saldo']);
    }

    private function date(string $relative): string
    {
        return (new \DateTimeImmutable($relative, new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
    }

    private function receivable(array $dueDates): array
    {
        $users = model(UserModel::class);
        $suffix = bin2hex(random_bytes(4));
        $actorId = (int) $users->insert(new User([
            'username' => 'portfolio-' . $suffix,
            'email' => 'portfolio-' . $suffix . '@example.test',
            'password' => 'Fixture-only-' . bin2hex(random_bytes(16)),
            'active' => 1,
        ]));
        $users->findById($actorId)->syncGroups('administrador');
        $this->db->table('clientes')->insert([
            'nombre' => 'Cliente de cartera', 'activo' => 1, 'version' => 1, 'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);
        $clientId = (int) $this->db->insertID();
        $documents = new FinancialDocumentService();
        $draft = $documents->createDraft($actorId, $clientId, [
            'type' => 'FACTURA', 'number' => 'PORT-' . $suffix, 'issued_on' => $this->date('-30 days'),
            'concept' => 'Cartera de prueba',
            'lines' => [['description' => 'Servicio', 'quantity' => '1', 'amount' => '100.00']],
        ], 'portfolio-draft-' . $suffix);
        $confirmed = $documents->confirm($actorId, (int) $draft['obligation_id'], $dueDates, 'portfolio-confirm-' . $suffix);
        $this->db->table('cuentas_bancarias')->insert([
            'institucion' => 'Banco de pruebas', 'numero_cuenta' => $suffix, 'alias' => 'Principal', 'activa' => 1,
        ]);

        return [
            'actor_id' => $actorId, 'client_id' => $clientId, 'obligation_id' => (int) $draft['obligation_id'],
            'installments' => $confirmed['installments'], 'account_id' => (int) $this->db->insertID(),
        ];
    }

    private function pay(array $fixture, int $installment, string $amount): int
    {
        $suffix = bin2hex(random_bytes(4));
        $result = (new PaymentService())->confirmBankTransfer($fixture['actor_id'], $fixture['client_id'], [
            'amount' => $amount, 'bank_account_id' => $fixture['account_id'],
            'reference' => 'PORT-PAY-' . $suffix, 'bank_date' => $this->date('today'),
            'applications' => [['installment_id' => $fixture['installments'][$installment]['id'], 'amount' => $amount]],
        ], 'portfolio-payment-' . $suffix);

        return (int) $result['payment_id'];
    }

    private function portfolio(array $fixture): array
    {
        $rows = (new FinancialDocumentService())->portfolioForClient($fixture['client_id']);
        $this->assertCount(1, $rows);
        $this->assertSame($fixture['obligation_id'], (int) $rows[0]['id']);

        return $rows[0];
    }
}
