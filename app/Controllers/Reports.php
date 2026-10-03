<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Audit;
use App\Services\OperationsService;
use App\Services\ReportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class Reports extends BaseController
{
    public function index(): string
    {
        return $this->renderPage('reports/index', (new OperationsService())->reportOverview() + [
            'title' => 'Panel de reportes',
            'description' => 'Accesos consolidados a clientes, trazabilidad y reportes disponibles.',
            'pagePattern' => 'dashboard',
        ]);
    }

    public function portfolio(): string
    {
        return $this->renderPage('reports/portfolio', (new ReportService())->portfolio($this->request->getGet()) + [
            'title' => 'Reporte de cartera',
            'description' => 'Saldos vigentes, vencimientos y antigüedad de toda la cartera confirmada.',
            'pagePattern' => 'detail',
        ]);
    }

    public function portfolioCsv()
    {
        $report = (new ReportService())->portfolio($this->request->getGet(), true);
        $rows = array_map(static fn (array $row): array => [
            (string) $row['id'], (string) $row['cliente'], (string) $row['concepto'],
            (string) ($row['numero_completo'] ?? ''), (string) ($row['vencimiento'] ?? ''),
            (string) $row['saldo'], (string) $row['estado'], (string) $row['antiguedad'],
        ], $report['items']);
        Audit::record((int) auth()->id(), 'reportes.cartera.exportar_csv', 'reporte:cartera', ['registros' => count($rows)]);

        return $this->csv('reporte-cartera.csv', ['Obligación', 'Cliente', 'Concepto', 'Documento', 'Vencimiento', 'Saldo', 'Estado', 'Antigüedad'], $rows);
    }

    public function portfolioXlsx()
    {
        $report = (new ReportService())->portfolio($this->request->getGet(), true);
        $rows = array_map(static fn (array $row): array => [
            (string) $row['id'], (string) $row['cliente'], (string) $row['concepto'],
            (string) ($row['numero_completo'] ?? ''), (string) ($row['vencimiento'] ?? ''),
            (float) $row['saldo'], (string) $row['estado'], (string) $row['antiguedad'],
        ], $report['items']);
        Audit::record((int) auth()->id(), 'reportes.cartera.exportar_xlsx', 'reporte:cartera', ['registros' => count($rows)]);

        return $this->xlsx('reporte-cartera.xlsx', 'Cartera', ['Obligación', 'Cliente', 'Concepto', 'Documento', 'Vencimiento', 'Saldo', 'Estado', 'Antigüedad'], $rows, ['F']);
    }

    public function payments(): string
    {
        return $this->renderPage('reports/payments', (new ReportService())->payments($this->request->getGet()) + [
            'title' => 'Reporte de pagos',
            'description' => 'Cobros confirmados y revertidos, con fecha, origen, medio y saldo disponible.',
            'pagePattern' => 'detail',
        ]);
    }

    public function paymentsCsv()
    {
        $report = (new ReportService())->payments($this->request->getGet(), true);
        $rows = array_map(static fn (array $row): array => [
            (string) $row['id'], (string) $row['cliente'], (string) $row['fecha'],
            (string) $row['importe'], (string) $row['aplicado'], (string) $row['disponible'],
            (string) $row['estado'], (string) $row['origen_label'], (string) $row['medio_label'],
            (string) ($row['referencia'] ?? ''),
        ], $report['rows']);
        Audit::record((int) auth()->id(), 'reportes.pagos.exportar_csv', 'reporte:pagos', ['registros' => count($rows)]);

        return $this->csv('reporte-pagos.csv', ['Pago', 'Cliente', 'Fecha', 'Importe', 'Aplicado', 'Disponible', 'Estado', 'Origen', 'Medio', 'Referencia'], $rows);
    }

    public function paymentsXlsx()
    {
        $report = (new ReportService())->payments($this->request->getGet(), true);
        $rows = array_map(static fn (array $row): array => [
            (string) $row['id'], (string) $row['cliente'], (string) $row['fecha'],
            (float) $row['importe'], (float) $row['aplicado'], (float) $row['disponible'],
            (string) $row['estado'], (string) $row['origen_label'], (string) $row['medio_label'],
            (string) ($row['referencia'] ?? ''),
        ], $report['rows']);
        Audit::record((int) auth()->id(), 'reportes.pagos.exportar_xlsx', 'reporte:pagos', ['registros' => count($rows)]);

        return $this->xlsx('reporte-pagos.xlsx', 'Pagos', ['Pago', 'Cliente', 'Fecha', 'Importe', 'Aplicado', 'Disponible', 'Estado', 'Origen', 'Medio', 'Referencia'], $rows, ['D', 'E', 'F']);
    }

    /** @param list<string> $headers @param list<list<string>> $rows */
    private function csv(string $filename, array $headers, array $rows)
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'No se pudo preparar la exportación.']);
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $value): string => $this->safeExportText($value), $row));
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content === false ? '' : $content);
    }

    /** @param list<string> $headers @param list<list<string|float>> $rows @param list<string> $numericColumns */
    private function xlsx(string $filename, string $title, array $headers, array $rows, array $numericColumns)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 2);
                if (is_float($value)) {
                    $sheet->setCellValue($coordinate, $value);
                } else {
                    $sheet->setCellValueExplicit($coordinate, $this->safeExportText($value), DataType::TYPE_STRING);
                }
            }
        }
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        foreach ($numericColumns as $column) {
            $sheet->getStyle($column . '2:' . $column . max(2, count($rows) + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $temporaryFile = tempnam(WRITEPATH, 'report-export-');
        if ($temporaryFile === false) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'No se pudo preparar la exportación.']);
        }
        try {
            (new Xlsx($spreadsheet))->save($temporaryFile);
            $content = file_get_contents($temporaryFile);
        } finally {
            @unlink($temporaryFile);
            $spreadsheet->disconnectWorksheets();
        }

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content === false ? '' : $content);
    }

    private function safeExportText(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }
}
