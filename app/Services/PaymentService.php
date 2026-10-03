<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money;
use App\Domain\ValidationException;
use CodeIgniter\Database\BaseConnection;

final class PaymentService
{
    /** @return list<array<string, mixed>> */
    public function activeBankAccounts(): array
    {
        return db_connect()->table('cuentas_bancarias')
            ->where('activa', 1)->orderBy('institucion')->orderBy('alias')->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function openInstallmentsForClient(int $clientId): array
    {
        $rows = db_connect()->query("SELECT c.id, c.numero_cuota, o.id obligacion_id, o.concepto,
            d.tipo, d.numero_completo, cv.fecha_vencimiento, cv.importe_programado,
            COALESCE(SUM(CASE WHEN ar.aplicacion_id IS NULL AND pr.pago_id IS NULL THEN ap.importe ELSE 0 END), 0) aplicado
            FROM cuotas c
            JOIN obligaciones o ON o.id = c.obligacion_id
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            JOIN cuota_versiones cv ON cv.cuota_id = c.id
             AND cv.version = (SELECT MAX(cv2.version) FROM cuota_versiones cv2 WHERE cv2.cuota_id = c.id)
            LEFT JOIN aplicaciones_pago ap ON ap.cuota_id = c.id
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = ap.pago_id
            WHERE o.cliente_id = ? AND o.operacion_confirmacion_id IS NOT NULL
            GROUP BY c.id, c.numero_cuota, o.id, o.concepto, d.tipo, d.numero_completo,
                cv.fecha_vencimiento, cv.importe_programado
            ORDER BY cv.fecha_vencimiento, o.id, c.numero_cuota", [$clientId])->getResultArray();
        $open = [];
        foreach ($rows as $row) {
            $balance = Money::cents((string) $row['importe_programado']) - Money::cents((string) $row['aplicado'], true);
            if ($balance <= 0) {
                continue;
            }
            $row['saldo'] = Money::decimal($balance);
            $open[] = $row;
        }

        return $open;
    }

    /** @return list<array<string, mixed>> */
    public function listForClient(int $clientId): array
    {
        $rows = db_connect()->query("SELECT p.id, p.importe, p.fecha_declarada, p.medio_pago,
            pb.referencia, pr.pago_id pago_revertido,
            COALESCE(SUM(CASE WHEN ar.aplicacion_id IS NULL AND pr.pago_id IS NULL THEN ap.importe ELSE 0 END), 0) aplicado
            FROM pagos p
            LEFT JOIN pagos_bancarios pb ON pb.pago_id = p.id
            LEFT JOIN aplicaciones_pago ap ON ap.pago_id = p.id
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            WHERE p.cliente_id = ?
            GROUP BY p.id, p.importe, p.fecha_declarada, p.medio_pago, pb.referencia, pr.pago_id
            ORDER BY p.fecha_declarada DESC, p.id DESC", [$clientId])->getResultArray();
        foreach ($rows as &$row) {
            $row['estado'] = $row['pago_revertido'] === null ? 'CONFIRMADO' : 'REVERTIDO';
            $row['disponible'] = $row['pago_revertido'] === null
                ? Money::decimal(Money::cents((string) $row['importe']) - Money::cents((string) $row['aplicado'], true))
                : '0.00';
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function find(int $paymentId): array
    {
        $db = db_connect();
        $row = $db->query("SELECT p.id, p.cliente_id, p.importe, p.fecha_declarada, p.medio_pago,
            c.nombre cliente, pb.referencia, pb.fecha_bancaria, cb.institucion, cb.alias,
            pr.pago_id pago_revertido, ro.motivo motivo_reversion, ro.efectiva_en fecha_reversion
            FROM pagos p
            JOIN clientes c ON c.id = p.cliente_id
            LEFT JOIN pagos_bancarios pb ON pb.pago_id = p.id
            LEFT JOIN cuentas_bancarias cb ON cb.id = pb.cuenta_bancaria_id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            LEFT JOIN operaciones ro ON ro.id = pr.operacion_id
            WHERE p.id = ?", [$paymentId])->getRowArray();
        if (!$row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $applications = $db->query("SELECT ap.id, ap.importe, c.numero_cuota, o.id obligacion_id,
            o.concepto, d.tipo, d.numero_completo, ar.aplicacion_id aplicacion_revertida,
            aro.motivo motivo_reversion, aro.efectiva_en fecha_reversion
            FROM aplicaciones_pago ap
            JOIN cuotas c ON c.id = ap.cuota_id
            JOIN obligaciones o ON o.id = c.obligacion_id
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN operaciones aro ON aro.id = ar.operacion_id
            WHERE ap.pago_id = ?
            ORDER BY ap.id", [$paymentId])->getResultArray();
        $appliedCents = 0;
        foreach ($applications as &$application) {
            $active = $row['pago_revertido'] === null && $application['aplicacion_revertida'] === null;
            $application['estado'] = $active ? 'VIGENTE' : 'REVERTIDA';
            if ($active) {
                $appliedCents += Money::cents((string) $application['importe']);
            }
        }
        unset($application);
        $row['aplicaciones'] = $applications;
        $row['aplicado_historico'] = Money::decimal($appliedCents);
        $row['aplicado'] = $row['pago_revertido'] === null ? Money::decimal($appliedCents) : '0.00';
        $row['disponible'] = $row['pago_revertido'] === null
            ? Money::decimal(Money::cents((string) $row['importe']) - $appliedCents)
            : '0.00';
        $row['estado'] = $row['pago_revertido'] === null ? 'CONFIRMADO' : 'REVERTIDO';

        return $row;
    }

    /** @return array<string, mixed> */
    public function findApplication(int $applicationId): array
    {
        $row = db_connect()->query("SELECT ap.id, ap.pago_id, ap.cuota_id, ap.importe,
            p.cliente_id, p.importe pago_importe, c.numero_cuota, o.id obligacion_id,
            o.concepto, d.tipo, d.numero_completo, cl.nombre cliente,
            pr.pago_id pago_revertido, ar.aplicacion_id aplicacion_revertida,
            aro.motivo motivo_reversion, aro.efectiva_en fecha_reversion
            FROM aplicaciones_pago ap
            JOIN pagos p ON p.id = ap.pago_id
            JOIN clientes cl ON cl.id = p.cliente_id
            JOIN cuotas c ON c.id = ap.cuota_id
            JOIN obligaciones o ON o.id = c.obligacion_id
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN operaciones aro ON aro.id = ar.operacion_id
            WHERE ap.id = ?", [$applicationId])->getRowArray();
        if (!$row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $row['estado'] = $row['pago_revertido'] === null && $row['aplicacion_revertida'] === null
            ? 'VIGENTE'
            : 'REVERTIDA';

        return $row;
    }

    public function confirmBankTransfer(int $actorId, int $clientId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'pagos.crear');
        Access::require($actorId, 'pagos.bancarios.confirmar');
        $data = $this->validate($input);
        $payload = ['client_id' => $clientId] + $data;
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'pagos.transferencia.confirmar', $idempotencyKey, $payload);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            $client = $db->query('SELECT id, activo FROM clientes WHERE id = ? FOR UPDATE', [$clientId])->getRowArray();
            if (!$client) {
                throw new ValidationException(['client' => 'El cliente no existe.']);
            }
            if ((int) $client['activo'] !== 1) {
                throw new ValidationException(['client' => 'El cliente inactivo no admite cobros nuevos.']);
            }
            $account = $db->query('SELECT id, activa FROM cuentas_bancarias WHERE id = ? FOR UPDATE', [$data['bank_account_id']])->getRowArray();
            if (!$account || (int) $account['activa'] !== 1) {
                throw new ValidationException(['bank_account_id' => 'La cuenta bancaria no está disponible.']);
            }

            $applicationCents = 0;
            $seen = [];
            $normalizedApplications = $data['applications'];
            usort($normalizedApplications, static fn (array $left, array $right): int => $left['installment_id'] <=> $right['installment_id']);
            foreach ($normalizedApplications as $application) {
                $installmentId = $application['installment_id'];
                if (isset($seen[$installmentId])) {
                    throw new ValidationException(['applications' => 'Cada cuota debe aparecer una sola vez.']);
                }
                $seen[$installmentId] = true;
                $row = $db->query('SELECT c.id, o.cliente_id, o.operacion_confirmacion_id FROM cuotas c JOIN obligaciones o ON o.id = c.obligacion_id WHERE c.id = ? FOR UPDATE', [$installmentId])->getRowArray();
                if (!$row || (int) $row['cliente_id'] !== $clientId || $row['operacion_confirmacion_id'] === null) {
                    throw new ValidationException(['applications' => 'Todas las cuotas deben pertenecer al cliente y estar confirmadas.']);
                }
                $amountCents = Money::cents($application['amount']);
                if ($amountCents > $this->installmentBalanceCents($db, $installmentId)) {
                    throw new ValidationException(['applications' => 'Una aplicación supera el saldo actual de la cuota.']);
                }
                $applicationCents += $amountCents;
            }
            $paymentCents = Money::cents($data['amount']);
            if ($applicationCents > $paymentCents) {
                throw new ValidationException(['applications' => 'Las aplicaciones superan el importe recibido.']);
            }

            $db->table('pagos')->insert([
                'cliente_id' => $clientId,
                'origen' => 'OPERATIVO',
                'medio_pago' => 'TRANSFERENCIA',
                'importe' => $data['amount'],
                'fecha_declarada' => $data['bank_date'],
                'operacion_confirmacion_id' => $operation['id'],
            ]);
            $paymentId = (int) $db->insertID();
            $db->table('pagos_bancarios')->insert([
                'pago_id' => $paymentId,
                'cuenta_bancaria_id' => $data['bank_account_id'],
                'referencia' => $data['reference'],
                'fecha_bancaria' => $data['bank_date'],
            ]);
            foreach ($normalizedApplications as $application) {
                $db->table('aplicaciones_pago')->insert([
                    'pago_id' => $paymentId,
                    'cuota_id' => $application['installment_id'],
                    'importe' => $application['amount'],
                    'operacion_id' => $operation['id'],
                ]);
            }
            $receipt = (new ReceiptService())->issueForPayment($db, $paymentId, $actorId);
            $result = [
                'operation_id' => (int) $operation['id'],
                'payment_id' => $paymentId,
                'receipt_id' => $receipt['id'],
                'receipt_code' => $receipt['code'],
                'amount' => $data['amount'],
                'applied' => Money::decimal($applicationCents),
                'available' => Money::decimal($paymentCents - $applicationCents),
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'pagos.transferencia.confirmar', 'pago:' . $paymentId, ['importe' => $data['amount'], 'aplicado' => $result['applied']]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo confirmar el pago.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public function portfolioBalanceForClient(int $clientId): string
    {
        $balanceCents = 0;
        foreach ($this->openInstallmentsForClient($clientId) as $installment) {
            $balanceCents += Money::cents((string) $installment['saldo']);
        }

        return Money::decimal($balanceCents);
    }

    /** @return array<string, mixed> */
    public function reversePayment(int $actorId, int $paymentId, string $reason, string $idempotencyKey): array
    {
        Access::require($actorId, 'pagos.revertir');
        $reason = $this->validateCorrectionReason($reason);
        $payload = ['payment_id' => $paymentId, 'reason' => $reason];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve(
                $db,
                $actorId,
                'pagos.revertir',
                $idempotencyKey,
                $payload,
                $reason
            );
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $payment = $db->query('SELECT id, cliente_id, importe, operacion_confirmacion_id FROM pagos WHERE id = ? FOR UPDATE', [$paymentId])->getRowArray();
            if (!$payment) {
                throw new ValidationException(['payment' => 'El cobro no existe.']);
            }
            $existing = $db->query('SELECT pago_id FROM pago_reversiones WHERE pago_id = ?', [$paymentId])->getRowArray();
            if ($payment['operacion_confirmacion_id'] === null || $existing) {
                throw new ValidationException(['payment' => 'El cobro ya fue revertido o no está confirmado.']);
            }
            $applied = $db->query("SELECT COALESCE(SUM(ap.importe), 0) importe
                FROM aplicaciones_pago ap
                LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
                WHERE ap.pago_id = ? AND ar.aplicacion_id IS NULL", [$paymentId])->getRowArray();
            $releasedCents = Money::cents((string) $applied['importe'], true);

            $db->table('pago_reversiones')->insert([
                'pago_id' => $paymentId,
                'clase_correccion' => 'ANULACION_ADMINISTRATIVA',
                'operacion_id' => $operation['id'],
            ]);
            $result = [
                'operation_id' => (int) $operation['id'],
                'payment_id' => $paymentId,
                'amount' => (string) $payment['importe'],
                'portfolio_restored' => Money::decimal($releasedCents),
                'status' => 'REVERTIDO',
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'pagos.revertir', 'pago:' . $paymentId, [
                'motivo' => $reason,
                'importe' => (string) $payment['importe'],
                'cartera_restituida' => $result['portfolio_restored'],
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo revertir el cobro.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function reverseApplication(int $actorId, int $applicationId, string $reason, string $idempotencyKey): array
    {
        Access::require($actorId, 'pagos.reaplicar');
        $reason = $this->validateCorrectionReason($reason);
        $identity = db_connect()->query('SELECT pago_id FROM aplicaciones_pago WHERE id = ?', [$applicationId])->getRowArray();
        if (!$identity) {
            throw new ValidationException(['application' => 'La aplicación no existe.']);
        }
        $paymentId = (int) $identity['pago_id'];
        $payload = ['application_id' => $applicationId, 'reason' => $reason];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve(
                $db,
                $actorId,
                'pagos.aplicaciones.revertir',
                $idempotencyKey,
                $payload,
                $reason
            );
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $payment = $db->query('SELECT p.id, p.importe, pr.pago_id pago_revertido FROM pagos p LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id WHERE p.id = ? FOR UPDATE', [$paymentId])->getRowArray();
            if (!$payment || $payment['pago_revertido'] !== null) {
                throw new ValidationException(['payment' => 'No se puede corregir una aplicación de un cobro revertido.']);
            }
            $application = $db->query('SELECT id, importe FROM aplicaciones_pago WHERE id = ? AND pago_id = ? FOR UPDATE', [$applicationId, $paymentId])->getRowArray();
            $existing = $db->query('SELECT aplicacion_id FROM aplicacion_reversiones WHERE aplicacion_id = ?', [$applicationId])->getRowArray();
            if (!$application || $existing) {
                throw new ValidationException(['application' => 'La aplicación ya fue revertida o no está disponible.']);
            }

            $db->table('aplicacion_reversiones')->insert([
                'aplicacion_id' => $applicationId,
                'operacion_id' => $operation['id'],
            ]);
            $active = $db->query("SELECT COALESCE(SUM(ap.importe), 0) importe
                FROM aplicaciones_pago ap
                LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
                WHERE ap.pago_id = ? AND ar.aplicacion_id IS NULL", [$paymentId])->getRowArray();
            $availableCents = Money::cents((string) $payment['importe']) - Money::cents((string) $active['importe'], true);
            $result = [
                'operation_id' => (int) $operation['id'],
                'application_id' => $applicationId,
                'payment_id' => $paymentId,
                'amount' => (string) $application['importe'],
                'available' => Money::decimal($availableCents),
                'status' => 'REVERTIDA',
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'pagos.aplicaciones.revertir', 'aplicacion:' . $applicationId, [
                'motivo' => $reason,
                'importe' => (string) $application['importe'],
                'pago_id' => $paymentId,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo revertir la aplicación.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function applyAvailable(int $actorId, int $paymentId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'pagos.editar');
        $applications = $this->validateApplications($input);
        usort($applications, static fn (array $left, array $right): int => $left['installment_id'] <=> $right['installment_id']);
        $payload = ['payment_id' => $paymentId, 'applications' => $applications];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'pagos.aplicaciones.crear', $idempotencyKey, $payload);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $payment = $db->query("SELECT p.id, p.cliente_id, p.importe, p.operacion_confirmacion_id,
                pr.pago_id pago_revertido
                FROM pagos p
                LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
                WHERE p.id = ? FOR UPDATE", [$paymentId])->getRowArray();
            if (!$payment) {
                throw new ValidationException(['payment' => 'El cobro no existe.']);
            }
            if ($payment['operacion_confirmacion_id'] === null || $payment['pago_revertido'] !== null) {
                throw new ValidationException(['payment' => 'Solo se puede distribuir un cobro confirmado y vigente.']);
            }

            $applied = $db->query("SELECT COALESCE(SUM(ap.importe), 0) importe
                FROM aplicaciones_pago ap
                LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
                WHERE ap.pago_id = ? AND ar.aplicacion_id IS NULL", [$paymentId])->getRowArray();
            $previouslyAppliedCents = Money::cents((string) $applied['importe'], true);
            $paymentCents = Money::cents((string) $payment['importe']);
            $availableCents = $paymentCents - $previouslyAppliedCents;
            if ($availableCents <= 0) {
                throw new ValidationException(['applications' => 'El cobro ya no tiene saldo disponible.']);
            }

            $newlyAppliedCents = 0;
            $seen = [];
            foreach ($applications as $application) {
                $installmentId = $application['installment_id'];
                if (isset($seen[$installmentId])) {
                    throw new ValidationException(['applications' => 'Cada cuota debe aparecer una sola vez.']);
                }
                $seen[$installmentId] = true;
                $installment = $db->query('SELECT c.id, o.cliente_id, o.operacion_confirmacion_id FROM cuotas c JOIN obligaciones o ON o.id = c.obligacion_id WHERE c.id = ? FOR UPDATE', [$installmentId])->getRowArray();
                if (!$installment || (int) $installment['cliente_id'] !== (int) $payment['cliente_id'] || $installment['operacion_confirmacion_id'] === null) {
                    throw new ValidationException(['applications' => 'Todas las cuotas deben pertenecer al cliente y estar confirmadas.']);
                }
                $amountCents = Money::cents($application['amount']);
                if ($amountCents > $this->installmentBalanceCents($db, $installmentId)) {
                    throw new ValidationException(['applications' => 'Una aplicación supera el saldo actual de la cuota.']);
                }
                $newlyAppliedCents += $amountCents;
            }
            if ($newlyAppliedCents > $availableCents) {
                throw new ValidationException(['applications' => 'Las aplicaciones superan el saldo disponible del cobro.']);
            }

            foreach ($applications as $application) {
                $db->table('aplicaciones_pago')->insert([
                    'pago_id' => $paymentId,
                    'cuota_id' => $application['installment_id'],
                    'importe' => $application['amount'],
                    'operacion_id' => $operation['id'],
                ]);
            }
            $totalAppliedCents = $previouslyAppliedCents + $newlyAppliedCents;
            $result = [
                'operation_id' => (int) $operation['id'],
                'payment_id' => $paymentId,
                'applied_now' => Money::decimal($newlyAppliedCents),
                'applied' => Money::decimal($totalAppliedCents),
                'available' => Money::decimal($paymentCents - $totalAppliedCents),
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'pagos.aplicaciones.crear', 'pago:' . $paymentId, [
                'aplicado' => $result['applied_now'],
                'disponible' => $result['available'],
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo aplicar el saldo del cobro.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    private function validate(array $input): array
    {
        $errors = [];
        try {
            $amount = Money::decimal(Money::cents(is_string($input['amount'] ?? null) ? $input['amount'] : ''));
        } catch (\InvalidArgumentException $error) {
            $errors['amount'] = $error->getMessage();
            $amount = '0.00';
        }
        $accountId = filter_var($input['bank_account_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($accountId === false) {
            $errors['bank_account_id'] = 'Selecciona una cuenta bancaria válida.';
        }
        $reference = trim(is_string($input['reference'] ?? null) ? $input['reference'] : '');
        if (mb_strlen($reference) < 2 || mb_strlen($reference) > 120) {
            $errors['reference'] = 'Escribe una referencia bancaria de 2 a 120 caracteres.';
        }
        $bankDate = is_string($input['bank_date'] ?? null) ? $input['bank_date'] : '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $bankDate);
        if ($date === false || $date->format('Y-m-d') !== $bankDate) {
            $errors['bank_date'] = 'La fecha bancaria no es válida.';
        }
        $applications = [];
        foreach (is_array($input['applications'] ?? null) ? array_values($input['applications']) : [] as $index => $application) {
            if (!is_array($application)) {
                $errors['applications'] = 'La distribución no es válida.';
                continue;
            }
            $installmentId = filter_var($application['installment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            try {
                $applicationAmount = Money::decimal(Money::cents(is_string($application['amount'] ?? null) ? $application['amount'] : ''));
            } catch (\InvalidArgumentException $error) {
                $errors['applications.' . $index . '.amount'] = $error->getMessage();
                $applicationAmount = '0.00';
            }
            if ($installmentId === false) {
                $errors['applications.' . $index . '.installment_id'] = 'La cuota no es válida.';
            }
            $applications[] = ['installment_id' => (int) $installmentId, 'amount' => $applicationAmount];
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        return ['amount' => $amount, 'bank_account_id' => (int) $accountId, 'reference' => $reference, 'bank_date' => $bankDate, 'applications' => $applications];
    }

    /** @return list<array{installment_id: int, amount: string}> */
    private function validateApplications(array $input): array
    {
        $errors = [];
        $applications = [];
        foreach (array_values($input) as $index => $application) {
            if (!is_array($application)) {
                $errors['applications'] = 'La distribución no es válida.';
                continue;
            }
            $installmentId = filter_var($application['installment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            try {
                $amount = Money::decimal(Money::cents(is_string($application['amount'] ?? null) ? $application['amount'] : ''));
            } catch (\InvalidArgumentException $error) {
                $errors['applications.' . $index . '.amount'] = $error->getMessage();
                $amount = '0.00';
            }
            if ($installmentId === false) {
                $errors['applications.' . $index . '.installment_id'] = 'La cuota no es válida.';
            }
            $applications[] = ['installment_id' => (int) $installmentId, 'amount' => $amount];
        }
        if ($applications === [] && $errors === []) {
            $errors['applications'] = 'Indica al menos un importe para distribuir.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        return $applications;
    }

    private function validateCorrectionReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new ValidationException(['reason' => 'Explica el motivo de la reversión en 10 a 500 caracteres.']);
        }

        return $reason;
    }

    private function installmentBalanceCents(BaseConnection $db, int $installmentId): int
    {
        $version = $db->query('SELECT importe_programado FROM cuota_versiones WHERE cuota_id = ? ORDER BY version DESC LIMIT 1', [$installmentId])->getRowArray();
        if (!$version) {
            throw new ValidationException(['applications' => 'La cuota no tiene una versión financiera vigente.']);
        }
        $applied = $db->query("SELECT COALESCE(SUM(ap.importe), 0) importe
            FROM aplicaciones_pago ap
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = ap.pago_id
            WHERE ap.cuota_id = ? AND ar.aplicacion_id IS NULL AND pr.pago_id IS NULL", [$installmentId])->getRowArray();

        return Money::cents((string) $version['importe_programado']) - Money::cents((string) $applied['importe'], true);
    }
}
