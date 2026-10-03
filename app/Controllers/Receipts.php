<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\ReceiptService;
use CodeIgniter\HTTP\DownloadResponse;

final class Receipts extends BaseController
{
    public function detail(string $id): string
    {
        $receipt = (new ReceiptService())->find((int) auth()->id(), (int) $id);
        return $this->renderPage('receipts/detail', [
            'title' => 'Recibo ' . $receipt['code'],
            'description' => 'Instantánea del cobro emitida al confirmar el pago.',
            'receipt' => $receipt,
            'pagePattern' => 'detail',
        ]);
    }

    public function pdf(string $id): DownloadResponse
    {
        $service = new ReceiptService();
        $receipt = $service->find((int) auth()->id(), (int) $id);
        $download = $this->response->download(
            'recibo-' . strtolower((string) $receipt['code']) . '-copia.pdf',
            $service->renderPdf((int) auth()->id(), (int) $id),
            true
        );
        if (!$download instanceof DownloadResponse) {
            throw new \RuntimeException('No se pudo preparar la copia del recibo.');
        }
        return $download->setContentType('application/pdf')->noCache();
    }
}
