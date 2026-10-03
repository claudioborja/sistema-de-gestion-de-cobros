<?php

declare(strict_types=1);

namespace App\Services;

final class ClientInsightsService
{
    /** @return array<string, mixed> */
    public function record(int $id): array
    {
        $client = (new ClientService())->find($id);
        $events = db_connect()->table('auditoria_eventos a')
            ->join('users u', 'u.id = a.usuario_id', 'left')
            ->select('a.accion, a.registrado_en, u.username')
            ->where('a.referencia_textual', 'cliente:' . $id)
            ->orderBy('a.registrado_en', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit(20)
            ->get()->getResultArray();

        foreach ($events as &$event) {
            $event['label'] = $this->eventLabel((string) $event['accion']);
            $event['actor'] = (string) ($event['username'] ?: 'Sistema');
            $event['fecha'] = $this->localDate((string) $event['registrado_en']);
        }

        return ['client' => $client, 'events' => $events];
    }

    /** @return array<string, mixed> */
    public function duplicates(int $id): array
    {
        $client = (new ClientService())->find($id);
        $currentName = $this->normalizedName((string) $client['nombre']);
        $rows = db_connect()->table('clientes c')
            ->select('c.id, c.nombre, c.activo')
            ->select('(SELECT numero_normalizado FROM cliente_identificaciones WHERE cliente_id=c.id ORDER BY tipo LIMIT 1) AS identificacion', false)
            ->where('c.id !=', $id)
            ->orderBy('c.nombre')
            ->limit(500)
            ->get()->getResultArray();

        $matches = [];
        foreach ($rows as $row) {
            $candidate = $this->normalizedName((string) $row['nombre']);
            $nameMatch = $currentName !== '' && $candidate !== ''
                && (str_contains($candidate, $currentName) || str_contains($currentName, $candidate));
            $identityMatch = ($client['identificacion'] ?? '') !== ''
                && hash_equals((string) $client['identificacion'], (string) ($row['identificacion'] ?? ''));
            if ($nameMatch || $identityMatch) {
                $row['reason'] = $identityMatch ? 'Misma identificación' : 'Nombre similar';
                $matches[] = $row;
            }
        }

        return ['client' => $client, 'matches' => $matches];
    }

    private function eventLabel(string $action): string
    {
        return match ($action) {
            'clientes.crear' => 'Cliente registrado',
            'clientes.editar' => 'Datos actualizados',
            'clientes.estado' => 'Estado modificado',
            'portal.invitacion.crear' => 'Invitación de portal creada',
            'portal.invitacion.activar' => 'Acceso de portal activado',
            default => 'Actividad administrativa',
        };
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function paginate(array $filters): array
    {
        $queryText = mb_substr(is_string($filters['q'] ?? null) ? trim($filters['q']) : '', 0, 100);
        $page = max(1, min(100000, (int) ($filters['page'] ?? 1)));
        $status = is_string($filters['estado'] ?? null) ? $filters['estado'] : '';
        $query = db_connect()->table('clientes c');
        if ($queryText !== '') {
            $query->groupStart()->like('c.nombre', $queryText)
                ->orWhereIn('c.id', db_connect()->table('cliente_identificaciones')->select('cliente_id')->like('numero_normalizado', preg_replace('/[^A-Za-z0-9]/', '', $queryText) ?? ''))
                ->groupEnd();
        }
        if (in_array($status, ['0', '1'], true)) { $query->where('c.activo', (int) $status); }
        $total = $query->countAllResults(false);
        $page = min($page, max(1, (int) ceil($total / 5)));
        $rows = $query->select('c.id, c.nombre, c.activo')
            ->select('(SELECT numero_normalizado FROM cliente_identificaciones WHERE cliente_id=c.id ORDER BY tipo LIMIT 1) AS identificacion', false)
            ->orderBy('c.nombre')->orderBy('c.id')->limit(5, ($page - 1) * 5)
            ->get()->getResultArray();

        return [
            'data' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'nombre' => (string) $row['nombre'],
                'identificacion' => (string) ($row['identificacion'] ?? ''),
                'activo' => (bool) $row['activo'],
            ], $rows),
            'pagination' => ['page' => $page, 'perPage' => 5, 'total' => $total, 'pages' => max(1, (int) ceil($total / 5))],
        ];
    }

    /** @param array<string, mixed> $request @return array<string, mixed> */
    public function datatable(array $request): array
    {
        $draw = max(0, min(1000000, (int) ($request['draw'] ?? 0)));
        $start = max(0, min(1000000, (int) ($request['start'] ?? 0)));
        $requestedLength = (int) ($request['length'] ?? 5);
        $length = in_array($requestedLength, [5, 10, 25], true) ? $requestedLength : 5;
        $searchValue = is_array($request['search'] ?? null) ? ($request['search']['value'] ?? '') : ($request['q'] ?? '');
        $search = is_string($searchValue) ? $searchValue : '';
        $search = mb_substr(trim($search), 0, 100);
        $status = is_string($request['estado'] ?? null) ? $request['estado'] : '';

        $makeQuery = static function () use ($search, $status) {
            $db = db_connect();
            $query = $db->table('clientes c');
            if ($search !== '') {
                $identity = preg_replace('/[^A-Za-z0-9]/', '', $search) ?? '';
                $query->groupStart()->like('c.nombre', $search)
                    ->orWhereIn('c.id', $db->table('cliente_identificaciones')->select('cliente_id')->like('numero_normalizado', $identity))
                    ->groupEnd();
            }
            if (in_array($status, ['0', '1'], true)) {
                $query->where('c.activo', (int) $status);
            }
            return $query;
        };

        $recordsTotal = db_connect()->table('clientes')->countAllResults();
        $recordsFiltered = $makeQuery()->countAllResults();
        $order = is_array($request['order'] ?? null) ? ($request['order'][0] ?? []) : [];
        $orderColumn = is_array($order) ? (int) ($order['column'] ?? 0) : 0;
        $orderDirection = is_array($order) && strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $allowedColumns = [0 => 'c.nombre', 1 => 'identificacion', 2 => 'c.activo'];
        $column = $allowedColumns[$orderColumn] ?? 'c.nombre';

        $rows = $makeQuery()
            ->select('c.id, c.nombre, c.activo')
            ->select('(SELECT numero_normalizado FROM cliente_identificaciones WHERE cliente_id=c.id ORDER BY tipo LIMIT 1) AS identificacion', false)
            ->orderBy($column, $orderDirection)
            ->orderBy('c.id', 'ASC')
            ->limit($length, $start)
            ->get()->getResultArray();

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'nombre' => (string) $row['nombre'],
                'identificacion' => (string) ($row['identificacion'] ?? ''),
                'activo' => (bool) $row['activo'],
            ], $rows),
        ];
    }

    /** @param array<string, mixed> $filters @return list<array{id:int,nombre:string,identificacion:string,activo:bool}> */
    public function exportRows(array $filters): array
    {
        $searchValue = $filters['q'] ?? '';
        $search = is_string($searchValue) ? mb_substr(trim($searchValue), 0, 100) : '';
        $status = is_string($filters['estado'] ?? null) ? $filters['estado'] : '';
        $db = db_connect();
        $query = $db->table('clientes c');

        if ($search !== '') {
            $identity = preg_replace('/[^A-Za-z0-9]/', '', $search) ?? '';
            $query->groupStart()->like('c.nombre', $search)
                ->orWhereIn('c.id', $db->table('cliente_identificaciones')->select('cliente_id')->like('numero_normalizado', $identity))
                ->groupEnd();
        }
        if (in_array($status, ['0', '1'], true)) {
            $query->where('c.activo', (int) $status);
        }

        $rows = $query->select('c.id, c.nombre, c.activo')
            ->select('(SELECT numero_normalizado FROM cliente_identificaciones WHERE cliente_id=c.id ORDER BY tipo LIMIT 1) AS identificacion', false)
            ->orderBy('c.nombre', 'ASC')->orderBy('c.id', 'ASC')->get()->getResultArray();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'nombre' => (string) $row['nombre'],
            'identificacion' => (string) ($row['identificacion'] ?? ''),
            'activo' => (bool) $row['activo'],
        ], $rows);
    }

    private function normalizedName(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($value)) ?: mb_strtolower($value);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    private function localDate(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
    }
}
