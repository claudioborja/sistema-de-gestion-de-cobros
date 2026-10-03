<?php

declare(strict_types=1);

namespace App\Services;

final class ReportService
{
    private const PAGE_SIZE = 25;

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function portfolio(array $input, bool $all = false): array
    {
        $filters = $this->portfolioFilters($input);
        $db = db_connect();
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $soon = (new \DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
        $todaySql = $db->escape($today);
        $soonSql = $db->escape($soon);
        $applied = "SELECT ap.cuota_id, SUM(ap.importe) aplicado
            FROM aplicaciones_pago ap
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = ap.pago_id
            WHERE ar.aplicacion_id IS NULL AND pr.pago_id IS NULL
            GROUP BY ap.cuota_id";
        $latestVersions = 'SELECT cuota_id, MAX(version) version FROM cuota_versiones GROUP BY cuota_id';
        $base = "SELECT o.id, o.cliente_id, c.nombre cliente, o.concepto, o.fecha_origen,
                d.tipo, d.numero_completo,
                MIN(CASE WHEN GREATEST(cv.importe_programado - COALESCE(aa.aplicado, 0), 0) > 0
                    THEN cv.fecha_vencimiento END) vencimiento,
                SUM(GREATEST(cv.importe_programado - COALESCE(aa.aplicado, 0), 0)) saldo
            FROM obligaciones o
            JOIN clientes c ON c.id = o.cliente_id
            LEFT JOIN documentos_obligacion d ON d.obligacion_id = o.id
            JOIN cuotas q ON q.obligacion_id = o.id
            JOIN ({$latestVersions}) lv ON lv.cuota_id = q.id
            JOIN cuota_versiones cv ON cv.cuota_id = lv.cuota_id AND cv.version = lv.version
            LEFT JOIN ({$applied}) aa ON aa.cuota_id = q.id
            WHERE o.operacion_confirmacion_id IS NOT NULL
            GROUP BY o.id, o.cliente_id, c.nombre, o.concepto, o.fecha_origen, d.tipo, d.numero_completo";

        [$where, $params] = $this->portfolioWhere($filters, $todaySql, $soonSql);
        $summary = $db->query("SELECT COUNT(*) total, COALESCE(SUM(r.saldo), 0) saldo_total,
                COALESCE(SUM(r.vencimiento < {$todaySql}), 0) vencidas,
                COALESCE(SUM(r.vencimiento >= {$todaySql} AND r.vencimiento <= {$soonSql}), 0) por_vencer
            FROM ({$base}) r WHERE r.saldo > 0{$where}", $params)->getRowArray() ?? [];
        $total = (int) ($summary['total'] ?? 0);
        $page = min($filters['page'], max(1, (int) ceil($total / self::PAGE_SIZE)));
        $limit = $all ? '' : ' LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE);
        $rows = $db->query("SELECT r.* FROM ({$base}) r WHERE r.saldo > 0{$where}
            ORDER BY r.vencimiento IS NULL, r.vencimiento, r.id{$limit}", $params)->getResultArray();

        foreach ($rows as &$row) {
            $row['estado'] = $this->portfolioStatus($row['vencimiento'], $today, $soon);
            $row['antiguedad'] = $this->aging((string) ($row['vencimiento'] ?? ''), $today);
        }
        unset($row);

        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'filters' => $filters,
            'summary' => [
                'balance' => (string) ($summary['saldo_total'] ?? '0.00'),
                'overdue' => (int) ($summary['vencidas'] ?? 0),
                'dueSoon' => (int) ($summary['por_vencer'] ?? 0),
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function payments(array $input, bool $all = false): array
    {
        $filters = $this->paymentFilters($input);
        $db = db_connect();
        $applications = "SELECT ap.pago_id, SUM(ap.importe) aplicado
            FROM aplicaciones_pago ap
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            WHERE ar.aplicacion_id IS NULL
            GROUP BY ap.pago_id";
        $base = "SELECT p.id, p.cliente_id, c.nombre cliente, p.importe,
                COALESCE(p.fecha_declarada, DATE(op.efectiva_en)) fecha,
                p.fecha_declarada fecha_declarada, op.efectiva_en confirmado_en,
                p.origen, p.medio_pago, pb.referencia,
                CASE WHEN pr.pago_id IS NULL THEN 'CONFIRMADO' ELSE 'REVERTIDO' END estado,
                CASE WHEN pr.pago_id IS NULL THEN COALESCE(aa.aplicado, 0) ELSE 0 END aplicado,
                CASE WHEN pr.pago_id IS NULL THEN GREATEST(p.importe - COALESCE(aa.aplicado, 0), 0) ELSE 0 END disponible
            FROM pagos p
            JOIN clientes c ON c.id = p.cliente_id
            JOIN operaciones op ON op.id = p.operacion_confirmacion_id
            LEFT JOIN pagos_bancarios pb ON pb.pago_id = p.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            LEFT JOIN ({$applications}) aa ON aa.pago_id = p.id";
        [$where, $params] = $this->paymentWhere($filters);
        $summary = $db->query("SELECT COUNT(*) total, COALESCE(SUM(r.importe), 0) importe_total,
                COALESCE(SUM(r.estado = 'CONFIRMADO'), 0) confirmados,
                COALESCE(SUM(r.estado = 'REVERTIDO'), 0) revertidos
            FROM ({$base}) r WHERE 1 = 1{$where}", $params)->getRowArray() ?? [];
        $total = (int) ($summary['total'] ?? 0);
        $page = min($filters['page'], max(1, (int) ceil($total / self::PAGE_SIZE)));
        $limit = $all ? '' : ' LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE);
        $rows = $db->query("SELECT r.* FROM ({$base}) r WHERE 1 = 1{$where}
            ORDER BY r.fecha DESC, r.id DESC{$limit}", $params)->getResultArray();

        foreach ($rows as &$row) {
            $row['estado'] = $row['estado'] === 'REVERTIDO' ? 'Revertido' : 'Confirmado';
            $row['origen_label'] = match ($row['origen']) {
                'HISTORICO' => 'Histórico',
                'APERTURA' => 'Apertura',
                default => 'Operativo',
            };
            $row['medio_label'] = match ($row['medio_pago']) {
                'TRANSFERENCIA' => 'Transferencia',
                'DEPOSITO' => 'Depósito',
                'EFECTIVO' => 'Efectivo',
                default => 'No especificado',
            };
        }
        unset($row);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'filters' => $filters,
            'summary' => [
                'amount' => (string) ($summary['importe_total'] ?? '0.00'),
                'confirmed' => (int) ($summary['confirmados'] ?? 0),
                'reversed' => (int) ($summary['revertidos'] ?? 0),
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function portfolioFilters(array $input): array
    {
        $status = is_string($input['estado'] ?? null) ? $input['estado'] : '';
        if (!in_array($status, ['', 'vencida', 'por_vencer', 'pendiente'], true)) {
            $status = '';
        }

        return [
            'q' => mb_substr(trim(is_string($input['q'] ?? null) ? $input['q'] : ''), 0, 100),
            'estado' => $status,
            'page' => max(1, min(100000, (int) ($input['page'] ?? 1))),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function paymentFilters(array $input): array
    {
        $state = is_string($input['estado'] ?? null) ? $input['estado'] : '';
        $origin = is_string($input['origen'] ?? null) ? $input['origen'] : '';
        $method = is_string($input['medio'] ?? null) ? $input['medio'] : '';

        return [
            'q' => mb_substr(trim(is_string($input['q'] ?? null) ? $input['q'] : ''), 0, 100),
            'desde' => $this->validDate($input['desde'] ?? null),
            'hasta' => $this->validDate($input['hasta'] ?? null),
            'estado' => in_array($state, ['', 'confirmado', 'revertido'], true) ? $state : '',
            'origen' => in_array($origin, ['', 'OPERATIVO', 'HISTORICO', 'APERTURA'], true) ? $origin : '',
            'medio' => in_array($method, ['', 'TRANSFERENCIA', 'DEPOSITO', 'EFECTIVO'], true) ? $method : '',
            'page' => max(1, min(100000, (int) ($input['page'] ?? 1))),
        ];
    }

    /** @param array<string, mixed> $filters @return array{0:string,1:list<string>} */
    private function portfolioWhere(array $filters, string $todaySql, string $soonSql): array
    {
        $where = '';
        $params = [];
        if ($filters['q'] !== '') {
            $where .= " AND (r.cliente LIKE ? ESCAPE '!' OR r.concepto LIKE ? ESCAPE '!' OR COALESCE(r.numero_completo, '') LIKE ? ESCAPE '!')";
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $params = [$term, $term, $term];
        }
        $where .= match ($filters['estado']) {
            'vencida' => " AND r.vencimiento < {$todaySql}",
            'por_vencer' => " AND r.vencimiento >= {$todaySql} AND r.vencimiento <= {$soonSql}",
            'pendiente' => " AND (r.vencimiento IS NULL OR r.vencimiento > {$soonSql})",
            default => '',
        };

        return [$where, $params];
    }

    /** @param array<string, mixed> $filters @return array{0:string,1:list<string>} */
    private function paymentWhere(array $filters): array
    {
        $where = '';
        $params = [];
        if ($filters['q'] !== '') {
            $where .= " AND (r.cliente LIKE ? ESCAPE '!' OR COALESCE(r.referencia, '') LIKE ? ESCAPE '!')";
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $params[] = $term;
            $params[] = $term;
        }
        if ($filters['desde'] !== '') {
            $where .= ' AND r.fecha >= ?';
            $params[] = $filters['desde'];
        }
        if ($filters['hasta'] !== '') {
            $where .= ' AND r.fecha <= ?';
            $params[] = $filters['hasta'];
        }
        if ($filters['estado'] !== '') {
            $where .= ' AND r.estado = ?';
            $params[] = strtoupper($filters['estado']);
        }
        if ($filters['origen'] !== '') {
            $where .= ' AND r.origen = ?';
            $params[] = $filters['origen'];
        }
        if ($filters['medio'] !== '') {
            $where .= ' AND r.medio_pago = ?';
            $params[] = $filters['medio'];
        }

        return [$where, $params];
    }

    private function portfolioStatus(mixed $dueDate, string $today, string $soon): string
    {
        if (!is_string($dueDate) || $dueDate === '' || $dueDate > $soon) {
            return 'Pendiente';
        }

        return $dueDate < $today ? 'Vencida' : 'Por vencer';
    }

    private function aging(string $dueDate, string $today): string
    {
        if ($dueDate === '') {
            return 'Sin fecha';
        }
        $days = (int) (new \DateTimeImmutable($dueDate))->diff(new \DateTimeImmutable($today))->format('%r%a');
        if ($days <= 0) {
            return 'Al día';
        }

        return match (true) {
            $days <= 30 => '1–30 días',
            $days <= 60 => '31–60 días',
            $days <= 90 => '61–90 días',
            default => 'Más de 90 días',
        };
    }

    private function validDate(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
