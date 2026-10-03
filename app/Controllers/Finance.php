<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Money;
use App\Domain\InstallmentSchedule;
use App\Domain\ValidationException;
use App\Services\Access;
use App\Services\BankReconciliationService;
use App\Services\CashSessionService;
use App\Services\DocumentAttachmentService;
use App\Services\FinancialDocumentService;
use App\Services\ObligationService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;

final class Finance extends BaseController
{

    public function documentStore(?string $id = null): string
    {
        return $this->unavailable();
    }

    public function documentDetail(string $id): string
    {
        $document = (new FinancialDocumentService())->find((int) $id);
        $issued = new \DateTimeImmutable((string) $document['fecha_emision']);
        $nextMonth = $issued->modify('first day of next month');
        $defaultFirst = $nextMonth->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), min((int) $issued->format('d'), (int) $nextMonth->format('t')))->format('Y-m-d');
        $months = $this->request->getGet('months') ?? old('months', '6');
        $firstDueOn = $this->request->getGet('first_due_on') ?? old('first_due_on', $defaultFirst);
        $creditPreview = [];
        $creditError = null;
        if ($document['estado'] === 'BORRADOR' && $this->request->getGet('months') !== null) {
            try {
                $creditPreview = InstallmentSchedule::monthly((string) $document['importe_base'], $months, $firstDueOn, (string) $document['fecha_emision']);
            } catch (\InvalidArgumentException $error) {
                $creditError = $error->getMessage();
            }
        }
        $canViewAttachments = Access::can((int) auth()->id(), 'archivos.ver');
        $attachments = $canViewAttachments
            ? (new DocumentAttachmentService())->listForDocument((int) $id)
            : [];

        return $this->renderPage('finance/document_detail', [
            'title' => 'Documento #' . (int) $id,
            'description' => 'Revisa el borrador, sus líneas y el saldo generado al confirmarlo.',
            'document' => $document,
            'creditMonths' => is_scalar($months) ? (string) $months : '',
            'creditFirstDueOn' => is_string($firstDueOn) ? $firstDueOn : '',
            'creditPreview' => $creditPreview,
            'creditError' => $creditError,
            'canConfirm' => Access::can((int) auth()->id(), 'cartera.ver'),
            'canViewAttachments' => $canViewAttachments,
            'attachmentCount' => count($attachments),
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'pagePattern' => 'detail',
        ]);
    }

    public function documentEdit(string $id): string|RedirectResponse
    {
        $document = (new FinancialDocumentService())->find((int) $id);
        if ($document['estado'] !== 'BORRADOR') {
            return redirect()->to(site_url('documentos/' . (int) $id))
                ->with('error', 'El documento confirmado no admite edición.');
        }

        return $this->renderPage('finance/document_edit', [
            'title' => 'Editar borrador #' . (int) $id,
            'description' => 'Actualiza el documento antes de confirmar sus cuotas.',
            'document' => $document,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'pagePattern' => 'form',
        ]);
    }

    public function documentUpdate(?string $id = null): RedirectResponse
    {
        $documentId = (int) $id;
        $lines = $this->request->getPost('lines');
        $lines = is_array($lines) ? array_values(array_filter($lines, static function ($line): bool {
            return !is_array($line)
                || !is_string($line['description'] ?? '') || !is_string($line['amount'] ?? '')
                || trim(($line['description'] ?? '') . ($line['amount'] ?? '')) !== '';
        })) : [];
        foreach ($lines as &$line) {
            if (is_array($line) && ($line['item_id'] ?? null) === '') {
                $line['item_id'] = null;
            }
        }
        unset($line);

        try {
            $key = $this->request->getPost('idempotency_key');
            $revision = $this->request->getPost('draft_revision');
            (new FinancialDocumentService())->updateDraft((int) auth()->id(), $documentId, [
                'type' => $this->request->getPost('type'),
                'number' => $this->request->getPost('number'),
                'issued_on' => $this->request->getPost('issued_on'),
                'concept' => $this->request->getPost('concept'),
                'lines' => $lines,
            ], is_string($key) ? $key : '', is_string($revision) ? $revision : '');

            return redirect()->to(site_url('documentos/' . $documentId))
                ->with('message', 'Borrador actualizado. Revisa el documento antes de confirmar sus cuotas.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('documentos/' . $documentId . '/editar'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function documentConfirm(?string $id = null): RedirectResponse
    {
        $documentId = (int) $id;
        $dueDates = $this->request->getPost('due_dates');
        $dueDates = is_array($dueDates) ? array_values(array_filter($dueDates, static fn ($date): bool => is_string($date) && trim($date) !== '')) : [];
        try {
            if ($this->request->getPost('plan_mode') === 'monthly') {
                (new FinancialDocumentService())->confirmMonthly(
                    (int) auth()->id(), $documentId,
                    $this->request->getPost('months'), $this->request->getPost('first_due_on'),
                    (string) $this->request->getPost('idempotency_key')
                );
            } elseif ($this->request->getPost('plan_mode') === null) {
                (new FinancialDocumentService())->confirm(
                    (int) auth()->id(),
                    $documentId,
                    $dueDates,
                    (string) $this->request->getPost('idempotency_key')
                );
            } else {
                throw new \InvalidArgumentException('El plan de pago no es válido.');
            }

            return redirect()->to(site_url('documentos/' . $documentId))
                ->with('message', 'Documento confirmado y cuotas generadas correctamente.');
        } catch (ValidationException|\InvalidArgumentException $error) {
            $errors = $error instanceof ValidationException ? $error->errors : ['due_dates' => $error->getMessage()];
            return redirect()->to(site_url('documentos/' . $documentId))->withInput()->with('errors', $errors);
        }
    }

    public function documentFiles(string $id): string
    {
        $document = (new FinancialDocumentService())->find((int) $id);
        $service = new DocumentAttachmentService();

        return $this->renderPage('finance/document_files', [
            'title' => 'Archivos del documento #' . (int) $id,
            'description' => 'Respaldo privado del documento, disponible únicamente para usuarios autorizados.',
            'document' => $document,
            'files' => $service->listForDocument((int) $id),
            'canManage' => Access::can((int) auth()->id(), 'archivos.gestionar'),
            'pagePattern' => 'detail',
        ]);
    }

    public function documentFilesSave(?string $id = null): RedirectResponse
    {
        $documentId = (int) $id;
        try {
            $result = (new DocumentAttachmentService())->store(
                (int) auth()->id(),
                $documentId,
                $this->request->getFile('attachment'),
                (string) $this->request->getPost('notes')
            );

            return redirect()->to(site_url('documentos/' . $documentId . '/archivos'))
                ->with('message', 'Archivo "' . $result['name'] . '" adjuntado correctamente.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('documentos/' . $documentId . '/archivos'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function downloadAttachment(string $id): DownloadResponse
    {
        $attachment = (new DocumentAttachmentService())->findForDownload((int) auth()->id(), (int) $id);
        $download = $this->response->download((string) $attachment['absolute_path'], null, true);
        if (!$download instanceof DownloadResponse) {
            throw new \RuntimeException('No se pudo preparar la descarga.');
        }

        return $download->setFileName((string) $attachment['nombre_original'])->noCache();
    }

    public function obligation(string $id): string
    {
        $obligation = (new ObligationService())->find((int) $id);

        return $this->renderPage('finance/obligation', [
            'title' => 'Obligación #' . (int) $id,
            'description' => 'Saldo, cronograma vigente, aplicaciones e historial de reprogramaciones.',
            'obligation' => $obligation,
            'canReprogram' => $obligation['estado'] !== 'BORRADOR'
                && $obligation['estado'] !== 'PAGADA'
                && Access::can((int) auth()->id(), 'cartera.reprogramar'),
            'mode' => 'detail',
            'pagePattern' => 'detail',
        ]);
    }

    public function obligationReprogram(string $id): string|RedirectResponse
    {
        $obligation = (new ObligationService())->find((int) $id);
        if ($obligation['estado'] === 'BORRADOR' || $obligation['estado'] === 'PAGADA') {
            return redirect()->to(site_url('obligaciones/' . (int) $id))
                ->with('error', 'La obligación no tiene cuotas pendientes que puedan reprogramarse.');
        }

        return $this->renderPage('finance/obligation', [
            'title' => 'Reprogramar obligación #' . (int) $id,
            'description' => 'Actualiza las fechas pendientes sin modificar importes ni aplicaciones de pago.',
            'obligation' => $obligation,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'mode' => 'reprogram',
            'pagePattern' => 'form',
        ]);
    }

    public function obligationReprogramSave(?string $id = null): RedirectResponse
    {
        $obligationId = (int) $id;
        $dueDates = $this->request->getPost('due_dates');
        try {
            $result = (new ObligationService())->reprogram(
                (int) auth()->id(),
                $obligationId,
                is_array($dueDates) ? $dueDates : [],
                (string) $this->request->getPost('reason'),
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('obligaciones/' . $obligationId))
                ->with('message', 'Cronograma reprogramado. Se actualizaron ' . $result['installments_updated'] . ' cuotas.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('obligaciones/' . $obligationId . '/reprogramar'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function paymentConfirm(?string $id = null): string
    {
        return $this->unavailable();
    }

    public function paymentDetail(string $id): string
    {
        $service = new PaymentService();
        $payment = $service->find((int) $id);
        $canApply = $payment['estado'] === 'CONFIRMADO' && (float) $payment['disponible'] > 0;

        return $this->renderPage('finance/payment_detail', [
            'title' => 'Cobro #' . (int) $id,
            'description' => 'Resultado confirmado, distribución aplicada y saldo aún disponible.',
            'payment' => $payment,
            'receipt' => (new ReceiptService())->findForPayment((int) auth()->id(), (int) $id),
            'installments' => $canApply ? $service->openInstallmentsForClient((int) $payment['cliente_id']) : [],
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'canRevert' => $payment['estado'] === 'CONFIRMADO' && Access::can((int) auth()->id(), 'pagos.revertir'),
            'canReapply' => $payment['estado'] === 'CONFIRMADO' && Access::can((int) auth()->id(), 'pagos.reaplicar'),
            'pagePattern' => 'detail',
        ]);
    }

    public function paymentApplications(?string $id = null): RedirectResponse
    {
        $paymentId = (int) $id;
        $posted = $this->request->getPost('applications');
        $applications = [];
        foreach (is_array($posted) ? array_values($posted) : [] as $application) {
            if (!is_array($application)) {
                $applications[] = $application;
                continue;
            }
            $amount = trim(is_string($application['amount'] ?? null) ? $application['amount'] : '');
            if ($amount === '') {
                continue;
            }
            $applications[] = $application;
        }

        try {
            $result = (new PaymentService())->applyAvailable(
                (int) auth()->id(),
                $paymentId,
                $applications,
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('pagos/' . $paymentId))
                ->with('message', 'Se aplicaron $' . $result['applied_now'] . '. Saldo disponible: $' . $result['available'] . '.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('pagos/' . $paymentId))->withInput()->with('errors', $error->errors);
        }
    }

    public function paymentRevertForm(string $id): string|RedirectResponse
    {
        $service = new PaymentService();
        $payment = $service->find((int) $id);
        if ($payment['estado'] === 'REVERTIDO') {
            return redirect()->to(site_url('pagos/' . (int) $id))->with('error', 'El cobro ya fue revertido.');
        }
        $portfolioAfterCents = Money::cents($service->portfolioBalanceForClient((int) $payment['cliente_id']), true)
            + Money::cents((string) $payment['aplicado'], true);

        return $this->renderPage('finance/payment_revert', [
            'title' => 'Revertir cobro #' . (int) $id,
            'description' => 'Confirma una corrección administrativa sin eliminar el historial financiero.',
            'payment' => $payment,
            'portfolioAfterReversal' => Money::decimal($portfolioAfterCents),
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'pagePattern' => 'form',
        ]);
    }

    public function paymentRevert(?string $id = null): RedirectResponse
    {
        $paymentId = (int) $id;
        try {
            $result = (new PaymentService())->reversePayment(
                (int) auth()->id(),
                $paymentId,
                (string) $this->request->getPost('reason'),
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('pagos/' . $paymentId))
                ->with('message', 'Cobro revertido. Se restituyeron $' . $result['portfolio_restored'] . ' a la cartera.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('pagos/' . $paymentId . '/revertir'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function applicationRevertForm(string $id): string|RedirectResponse
    {
        $service = new PaymentService();
        $application = $service->findApplication((int) $id);
        if ($application['estado'] === 'REVERTIDA') {
            return redirect()->to(site_url('pagos/' . (int) $application['pago_id']))
                ->with('error', 'La aplicación ya no tiene efecto financiero vigente.');
        }
        $payment = $service->find((int) $application['pago_id']);
        $portfolioAfterCents = Money::cents($service->portfolioBalanceForClient((int) $application['cliente_id']), true)
            + Money::cents((string) $application['importe']);
        $availableAfterCents = Money::cents((string) $payment['disponible'], true)
            + Money::cents((string) $application['importe']);

        return $this->renderPage('finance/application_revert', [
            'title' => 'Revertir aplicación #' . (int) $id,
            'description' => 'Libera una distribución incorrecta sin anular el cobro recibido.',
            'application' => $application,
            'portfolioAfterReversal' => Money::decimal($portfolioAfterCents),
            'availableAfterReversal' => Money::decimal($availableAfterCents),
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'pagePattern' => 'form',
        ]);
    }

    public function applicationRevert(?string $id = null): RedirectResponse
    {
        $applicationId = (int) $id;
        $service = new PaymentService();
        $application = $service->findApplication($applicationId);
        try {
            $result = $service->reverseApplication(
                (int) auth()->id(),
                $applicationId,
                (string) $this->request->getPost('reason'),
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('pagos/' . (int) $result['payment_id']))
                ->with('message', 'Aplicación revertida. El cobro tiene $' . $result['available'] . ' disponibles para redistribuir.');
        } catch (ValidationException $error) {
            $target = isset($error->errors['reason'])
                ? 'aplicaciones/' . $applicationId . '/revertir'
                : 'pagos/' . (int) $application['pago_id'];

            return redirect()->to(site_url($target))->withInput()->with('errors', $error->errors);
        }
    }

    public function bankStatementInbox(): string
    {
        return $this->renderPage('finance/bank', (new BankReconciliationService())->inbox($this->request->getGet()) + [
            'title' => 'Comprobantes bancarios',
            'description' => 'Bandeja transversal para identificar, revisar y conciliar ingresos reportados.',
            'pagePattern' => 'list',
            'mode' => 'index',
        ]);
    }

    public function bankStatementCreate(): string
    {
        return $this->renderPage('finance/bank', [
            'title' => 'Registrar comprobante',
            'description' => 'Carga evidencia sin afectar saldo hasta su revisión.',
            'pagePattern' => 'form',
            'mode' => 'create',
            'bankAccounts' => (new PaymentService())->activeBankAccounts(),
            'idempotencyKey' => bin2hex(random_bytes(16)),
        ]);
    }

    public function bankStatementStore(?string $id = null): RedirectResponse
    {
        try {
            $result = (new BankReconciliationService())->create(
                (int) auth()->id(),
                $this->request->getPost(),
                $this->request->getFile('attachment'),
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('reportes-pago/' . (int) $result['report_id']))
                ->with('message', 'Comprobante registrado. No afectará la cartera hasta ser conciliado.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('reportes-pago/nuevo'))->withInput()->with('errors', $error->errors);
        }
    }

    public function bankStatementDetail(string $id): string
    {
        return $this->renderPage('finance/bank', [
            'title' => 'Comprobante bancario #' . (int) $id,
            'description' => 'Compara la evidencia reportada y conserva cada decisión de conciliación.',
            'pagePattern' => 'detail',
            'mode' => 'detail',
            'statement' => (new BankReconciliationService())->find((int) $id),
            'canReview' => Access::can((int) auth()->id(), 'pagos.bancarios.confirmar'),
            'canIdentify' => Access::can((int) auth()->id(), 'pagos.crear'),
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'identifyKey' => bin2hex(random_bytes(16)),
        ]);
    }

    public function bankStatementReview(?string $id = null): RedirectResponse
    {
        $reportId = (int) $id;
        try {
            $result = (new BankReconciliationService())->review(
                (int) auth()->id(), $reportId, $this->request->getPost(),
                (string) $this->request->getPost('idempotency_key')
            );
            $message = match ($result['decision']) {
                'APROBADO' => 'Comprobante aprobado y cobro confirmado.',
                'VINCULADO' => 'Comprobante vinculado al cobro existente sin duplicarlo.',
                'RECHAZADO' => 'Comprobante rechazado sin afectar la cartera.',
                default => 'Comprobante reabierto para una nueva revisión.',
            };

            return redirect()->to(site_url('reportes-pago/' . $reportId))->with('message', $message);
        } catch (ValidationException $error) {
            return redirect()->to(site_url('reportes-pago/' . $reportId))->withInput()->with('errors', $error->errors);
        }
    }

    public function bankUnknown(): string
    {
        $rows = (new BankReconciliationService())->unidentified();
        foreach ($rows as &$row) {
            $row['idempotency_key'] = bin2hex(random_bytes(16));
        }
        unset($row);

        return $this->renderPage('finance/bank', [
            'title' => 'Ingresos no identificados',
            'description' => 'Clasifica pagos en cola para evitar afectar cartera.',
            'pagePattern' => 'form',
            'mode' => 'unknown',
            'rows' => $rows,
        ]);
    }

    public function bankIdentify(?string $id = null): RedirectResponse
    {
        $reportId = (int) $id;
        try {
            (new BankReconciliationService())->identify(
                (int) auth()->id(), $reportId, (int) $this->request->getPost('cliente_id'),
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('reportes-pago/' . $reportId))
                ->with('message', 'Cliente identificado. El comprobante continúa pendiente de conciliación.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('ingresos-bancarios/no-identificados'))->withInput()->with('errors', $error->errors);
        }
    }

    public function downloadBankStatementFile(string $id): DownloadResponse
    {
        $file = (new BankReconciliationService())->findFile((int) auth()->id(), (int) $id);
        $download = $this->response->download((string) $file['absolute_path'], null, true);
        if (!$download instanceof DownloadResponse) {
            throw new \RuntimeException('No se pudo preparar la descarga.');
        }

        return $download->setFileName((string) $file['nombre_original'])->noCache();
    }

    public function cash(): string
    {
        $cash = (new CashSessionService())->dashboard((int) auth()->id());

        return $this->renderPage('finance/cash', [
            'title' => 'Mi caja',
            'description' => 'Consulta el turno vigente, su fondo y el efectivo esperado.',
            'pagePattern' => 'detail',
            'cash' => $cash,
            'canCreateMovement' => $cash['active'] !== null && Access::can((int) auth()->id(), 'caja.movimientos'),
            'canCloseShift' => $cash['active'] !== null && Access::can((int) auth()->id(), 'caja.cerrar'),
            'canHandoff' => Access::can((int) auth()->id(), 'caja.entregar'),
            'idempotencyKey' => bin2hex(random_bytes(16)),
        ]);
    }

    public function cashOpen(): RedirectResponse
    {
        try {
            $result = (new CashSessionService())->open((int) auth()->id(), [
                'caja_id' => $this->request->getPost('caja_id'),
                'fondo_inicial' => $this->request->getPost('fondo_inicial'),
            ], (string) $this->request->getPost('idempotency_key'));

            return redirect()->to(site_url('mi-caja'))
                ->with('message', 'Turno #' . $result['shift_id'] . ' abierto en la caja ' . $result['register_code'] . '.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('mi-caja'))->withInput()->with('errors', $error->errors);
        }
    }

    public function cashMovement(string $id): string
    {
        $shift = (new CashSessionService())->movementContext((int) auth()->id(), (int) $id);

        return $this->renderPage('finance/cash_movement', [
            'title' => 'Registrar movimiento',
            'description' => 'Registra una entrada o salida justificada dentro del turno abierto.',
            'pagePattern' => 'form',
            'shift' => $shift,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'breadcrumbs' => [
                ['label' => 'Mi caja', 'url' => site_url('mi-caja')],
                ['label' => 'Registrar movimiento'],
            ],
        ]);
    }

    public function cashMovementStore(?string $id = null): RedirectResponse
    {
        $shiftId = (int) $id;
        try {
            $result = (new CashSessionService())->addMovement((int) auth()->id(), $shiftId, [
                'tipo' => $this->request->getPost('tipo'),
                'importe' => $this->request->getPost('importe'),
                'concepto' => $this->request->getPost('concepto'),
            ], (string) $this->request->getPost('idempotency_key'));

            return redirect()->to(site_url('mi-caja'))->with(
                'message',
                'Movimiento #' . $result['movement_id'] . ' registrado. Efectivo esperado: $' . $result['expected_after'] . '.'
            );
        } catch (ValidationException $error) {
            return redirect()->to(site_url('turnos/' . $shiftId . '/movimientos/nuevo'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function cashClose(string $id): string
    {
        $shift = (new CashSessionService())->closingContext((int) auth()->id(), (int) $id);

        return $this->renderPage('finance/cash_close', [
            'title' => 'Cerrar turno',
            'description' => 'Compara el efectivo contado con el esperado antes de liberar la caja.',
            'pagePattern' => 'form',
            'shift' => $shift,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'breadcrumbs' => [
                ['label' => 'Mi caja', 'url' => site_url('mi-caja')],
                ['label' => 'Cerrar turno'],
            ],
        ]);
    }

    public function cashCloseStore(?string $id = null): RedirectResponse
    {
        $shiftId = (int) $id;
        try {
            $result = (new CashSessionService())->close((int) auth()->id(), $shiftId, [
                'efectivo_contado' => $this->request->getPost('efectivo_contado'),
                'motivo_diferencia' => $this->request->getPost('motivo_diferencia'),
            ], (string) $this->request->getPost('idempotency_key'));

            $difference = (float) $result['difference'];
            $detail = $difference === 0.0
                ? 'El arqueo no presentó diferencias.'
                : 'Diferencia registrada: ' . ($difference > 0 ? '+' : '−') . '$' . number_format(abs($difference), 2, '.', '') . '.';

            return redirect()->to(site_url('mi-caja'))
                ->with('message', 'Turno #' . $result['shift_id'] . ' cerrado. ' . $detail);
        } catch (ValidationException $error) {
            return redirect()->to(site_url('turnos/' . $shiftId . '/cerrar'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function cashHandoff(string $id): string
    {
        $shift = (new CashSessionService())->handoffContext((int) auth()->id(), (int) $id);

        return $this->renderPage('finance/cash_handoff', [
            'title' => $shift['entrega_id'] === null ? 'Entregar turno' : 'Entrega del turno',
            'description' => 'Separa el efectivo entregado del fondo remanente y deja constancia de la recepción.',
            'pagePattern' => $shift['entrega_id'] === null ? 'form' : 'detail',
            'shift' => $shift,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'breadcrumbs' => [
                ['label' => 'Mi caja', 'url' => site_url('mi-caja')],
                ['label' => 'Entrega del turno'],
            ],
        ]);
    }

    public function cashHandoffStore(?string $id = null): RedirectResponse
    {
        $shiftId = (int) $id;
        try {
            $result = (new CashSessionService())->deliver((int) auth()->id(), $shiftId, [
                'recibido_por' => $this->request->getPost('recibido_por'),
                'fondo_remanente' => $this->request->getPost('fondo_remanente'),
                'observacion_entrega' => $this->request->getPost('observacion_entrega'),
            ], (string) $this->request->getPost('idempotency_key'));

            return redirect()->to(site_url('turnos/' . $shiftId . '/entregar'))
                ->with('message', 'Entrega #' . $result['handoff_id'] . ' registrada. Pendiente de recepción por $' . $result['delivered'] . '.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('turnos/' . $shiftId . '/entregar'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function cashHandoffReceive(?string $id = null): RedirectResponse
    {
        $shiftId = (int) $id;
        try {
            $result = (new CashSessionService())->receive((int) auth()->id(), $shiftId, [
                'observacion_recepcion' => $this->request->getPost('observacion_recepcion'),
            ], (string) $this->request->getPost('idempotency_key'));

            return redirect()->to(site_url('turnos/' . $shiftId . '/entregar'))
                ->with('message', 'Recepción confirmada por $' . $result['delivered'] . '.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('turnos/' . $shiftId . '/entregar'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    private function unavailable(): string
    {
        $this->response->setStatusCode(409);
        return $this->renderPage('finance/unavailable', [
            'title' => 'Operación no disponible',
            'description' => 'Esta operación todavía no está implementada. No se guardó ni confirmó ningún cambio.',
        ]);
    }
}
