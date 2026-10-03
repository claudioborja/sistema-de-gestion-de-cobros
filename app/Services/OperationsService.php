<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Session\Handlers\FileHandler;
use Config\App as AppConfig;
use Config\Cookie as CookieConfig;
use Config\Email as EmailConfig;
use Config\Session as SessionConfig;

final class OperationsService
{
    /** @var array<string, string> */
    private const AUDIT_ACTIONS = [
        'demo.datos.poblados' => 'Datos de demostración preparados',
        'clientes.crear' => 'Cliente registrado',
        'clientes.editar' => 'Cliente actualizado',
        'clientes.estado' => 'Estado de cliente modificado',
        'clientes.descartar_duplicado' => 'Posible duplicado descartado',
        'clientes.exportar_csv' => 'Clientes exportados a CSV',
        'clientes.exportar_xlsx' => 'Clientes exportados a Excel',
        'catalogo.guardar' => 'Ítem de catálogo guardado',
        'cuentas_bancarias.guardar' => 'Cuenta bancaria guardada',
        'cuentas_bancarias.estado' => 'Estado de cuenta bancaria modificado',
        'configuracion.guardar' => 'Configuración del negocio guardada',
        'usuarios.alta_local' => 'Usuario administrativo creado',
        'usuarios.acceso_local_actualizado' => 'Acceso local actualizado',
        'usuarios.crear' => 'Usuario creado',
        'usuarios.editar' => 'Usuario actualizado',
        'usuarios.estado' => 'Estado de usuario modificado',
        'usuarios.permisos' => 'Permisos de usuario actualizados',
        'usuarios.perfil' => 'Perfil actualizado',
        'usuarios.contrasena' => 'Contraseña actualizada',
        'documentos.borrador.crear' => 'Borrador de documento creado',
        'documentos.confirmar' => 'Documento confirmado',
        'documentos.archivo.cargar' => 'Archivo de documento cargado',
        'documentos.archivo.descargar' => 'Archivo de documento descargado',
        'obligaciones.reprogramar' => 'Obligación reprogramada',
        'pagos.transferencia.confirmar' => 'Transferencia confirmada',
        'pagos.registrar' => 'Pago registrado',
        'pagos.revertir' => 'Pago revertido',
        'pagos.aplicaciones.crear' => 'Aplicación de pago creada',
        'pagos.aplicaciones.revertir' => 'Aplicación de pago revertida',
        'reportes.cartera.exportar_csv' => 'Cartera exportada a CSV',
        'reportes.cartera.exportar_xlsx' => 'Cartera exportada a Excel',
        'reportes.pagos.exportar_csv' => 'Pagos exportados a CSV',
        'reportes.pagos.exportar_xlsx' => 'Pagos exportados a Excel',
        'reportes_pago.registrar' => 'Comprobante bancario registrado',
        'reportes_pago.identificar' => 'Titular de comprobante identificado',
        'reportes_pago.revisar' => 'Comprobante bancario revisado',
        'reportes_pago.archivo.descargar' => 'Evidencia bancaria descargada',
        'caja.turno.abrir' => 'Turno de caja abierto',
        'caja.movimiento.registrar' => 'Movimiento de caja registrado',
        'caja.turno.cerrar' => 'Turno de caja cerrado',
        'caja.turno.entregar' => 'Turno de caja entregado',
        'caja.turno.recibir' => 'Turno de caja recibido',
        'portal.invitacion.crear' => 'Invitación de portal creada',
        'portal.invitacion.activar' => 'Acceso de portal activado',
        'portal.reportes_pago.archivo.descargar' => 'Comprobante propio descargado',
    ];

    /** @var array<string, array{component:string, probe:callable}>|null */
    private ?array $healthProbes;

    private \Closure $clock;

    /**
     * @param array<string, array{component:string, probe:callable}>|null $healthProbes
     */
    public function __construct(?array $healthProbes = null, ?callable $clock = null)
    {
        $this->healthProbes = $healthProbes;
        $this->clock = $clock === null
            ? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', new \DateTimeZone('America/Guayaquil'))
            : \Closure::fromCallable($clock);
    }

    /** @return array<string, mixed> */
    public function reportOverview(): array
    {
        $db = db_connect();
        $clients = $db->table('clientes')->countAllResults();
        $clientsActive = $db->table('clientes')->where('activo', 1)->countAllResults();
        $items = $db->table('items')->countAllResults();
        $itemsActive = $db->table('items')->where('activo', 1)->countAllResults();
        $localStart = new \DateTimeImmutable('today', new \DateTimeZone('America/Guayaquil'));
        $utcStart = $localStart->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $eventsToday = $db->table('auditoria_eventos')->where('registrado_en >=', $utcStart)->countAllResults();

        return compact('clients', 'clientsActive', 'items', 'itemsActive', 'eventsToday');
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function audit(array $filters = []): array
    {
        $queryText = mb_substr(trim(is_string($filters['q'] ?? null) ? $filters['q'] : ''), 0, 100);
        $action = is_string($filters['accion'] ?? null) ? $filters['accion'] : '';
        $page = max(1, min(100000, (int) ($filters['page'] ?? 1)));
        $requestedPageSize = (int) ($filters['pageSize'] ?? 5);
        $pageSize = in_array($requestedPageSize, [5, 10, 25], true) ? $requestedPageSize : 5;
        $start = array_key_exists('start', $filters) ? max(0, min(1000000, (int) $filters['start'])) : null;
        $actions = self::AUDIT_ACTIONS;
        if (!array_key_exists($action, $actions)) { $action = ''; }
        $query = db_connect()->table('auditoria_eventos a')
            ->join('users u', 'u.id = a.usuario_id', 'left')
            ->join('clientes c', "a.referencia_textual = CONCAT('cliente:', c.id)", 'left', false)
            ->join('items i', "a.referencia_textual = CONCAT('item:', i.id)", 'left', false)
            ->join('users ru', "a.referencia_textual = CONCAT('usuario:', ru.id)", 'left', false);
        if ($queryText !== '') {
            $query->groupStart()->like('a.referencia_textual', $queryText)->orLike('a.accion', $queryText)
                ->orLike('u.username', $queryText)->orLike('c.nombre', $queryText)
                ->orLike('i.nombre', $queryText)->orLike('ru.username', $queryText);
            $matchingActions = array_keys(array_filter(
                $actions,
                static fn (string $label): bool => mb_stripos($label, $queryText) !== false,
            ));
            if ($matchingActions !== []) {
                $query->orWhereIn('a.accion', $matchingActions);
            }
            $query->groupEnd();
        }
        if ($action !== '') { $query->where('a.accion', $action); }
        $total = $query->countAllResults(false);
        $page = min($page, max(1, (int) ceil($total / $pageSize)));
        $offset = $start ?? (($page - 1) * $pageSize);
        $rows = $query
            ->select('a.id, a.accion, a.referencia_textual, a.registrado_en, a.detalle_sanitizado, u.username')
            ->orderBy('a.registrado_en', 'DESC')->orderBy('a.id', 'DESC')
            ->limit($pageSize, $offset)->get()->getResultArray();

        $events = array_map(function (array $row): array {
            return [
                'eventId' => (int) $row['id'],
                'action' => $this->actionLabel((string) $row['accion']),
                'reference' => $this->referenceLabel((string) $row['referencia_textual']),
                'actor' => (string) ($row['username'] ?: 'Sistema'),
                'occurredAt' => (new \DateTimeImmutable((string) $row['registrado_en'], new \DateTimeZone('UTC')))
                    ->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i'),
                'details' => $this->auditDetails((string) ($row['detalle_sanitizado'] ?? '')),
            ];
        }, $rows);

        return compact('events', 'total', 'page', 'pageSize', 'queryText', 'action', 'actions');
    }

    /** @param array<string, mixed> $request @return array<string, mixed> */
    public function auditDatatable(array $request): array
    {
        $draw = max(0, min(1000000, (int) ($request['draw'] ?? 0)));
        $start = max(0, min(1000000, (int) ($request['start'] ?? 0)));
        $requestedLength = (int) ($request['length'] ?? 5);
        $length = in_array($requestedLength, [5, 10, 25], true) ? $requestedLength : 5;
        $searchValue = is_array($request['search'] ?? null) ? ($request['search']['value'] ?? '') : ($request['q'] ?? '');
        $result = $this->audit([
            'q' => is_string($searchValue) ? $searchValue : '',
            'accion' => $request['accion'] ?? '',
            'pageSize' => $length,
            'start' => $start,
        ]);

        return [
            'draw' => $draw,
            'recordsTotal' => db_connect()->table('auditoria_eventos')->countAllResults(),
            'recordsFiltered' => $result['total'],
            'data' => $result['events'],
        ];
    }

    public function clearAudit(): int
    {
        $db = db_connect();
        $db->table('auditoria_eventos')->where('id >', 0)->delete();

        return $db->affectedRows();
    }

    private function auditDetails(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '{}' || $raw === '[]') {
            return 'No se registraron detalles adicionales.';
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $raw;
        }
    }

    /**
     * @return array{
     *   checks:list<array{key:string,component:string,state:string,detail:string,level:string}>,
     *   summary:array{success:int,warning:int,error:int,unknown:int},
     *   checkedAt:string,
     *   checkedAtIso:string
     * }
     */
    public function status(): array
    {
        $checks = [];
        $summary = ['success' => 0, 'warning' => 0, 'error' => 0, 'unknown' => 0];
        foreach ($this->healthProbes ?? $this->defaultHealthProbes() as $key => $definition) {
            try {
                $result = ($definition['probe'])();
                $level = in_array($result['level'] ?? null, array_keys($summary), true) ? $result['level'] : 'unknown';
                $state = trim((string) ($result['state'] ?? 'Sin datos')) ?: 'Sin datos';
                $detail = trim((string) ($result['detail'] ?? 'La comprobación no devolvió detalles.')) ?: 'La comprobación no devolvió detalles.';
            } catch (\Throwable $error) {
                log_message('warning', 'Falló la comprobación operativa "{check}": {message}', ['check' => $key, 'message' => $error->getMessage()]);
                $level = 'error';
                $state = 'No disponible';
                $detail = 'La comprobación no pudo completarse. Revisa los registros protegidos del servidor.';
            }
            $summary[$level]++;
            $checks[] = [
                'key' => (string) $key,
                'component' => $definition['component'],
                'state' => $state,
                'detail' => $detail,
                'level' => $level,
            ];
        }

        $now = ($this->clock)()->setTimezone(new \DateTimeZone('America/Guayaquil'));
        return [
            'checks' => $checks,
            'summary' => $summary,
            'checkedAt' => $now->format('d/m/Y H:i:s'),
            'checkedAtIso' => $now->format(DATE_ATOM),
        ];
    }

    /** @return array<string, array{component:string, probe:callable}> */
    private function defaultHealthProbes(): array
    {
        return [
            'application' => ['component' => 'Aplicación web', 'probe' => fn (): array => $this->probeApplication()],
            'database' => ['component' => 'Base de datos', 'probe' => fn (): array => $this->probeDatabase()],
            'session' => ['component' => 'Sesión y cookies', 'probe' => fn (): array => $this->probeSession()],
            'storage' => ['component' => 'Almacenamiento privado', 'probe' => fn (): array => (new StorageHealthProbe(
                WRITEPATH,
                defined('FCPATH') ? (string) constant('FCPATH') : ROOTPATH . 'public' . DIRECTORY_SEPARATOR,
            ))->inspect()],
            'email' => ['component' => 'Correo saliente', 'probe' => fn (): array => $this->probeEmail()],
            'scheduler' => ['component' => 'Tareas programadas', 'probe' => fn (): array => $this->probeHeartbeat(WRITEPATH . 'health/scheduler-heartbeat.json', 3600, 'ejecución')],
            'backups' => ['component' => 'Respaldos', 'probe' => fn (): array => $this->probeHeartbeat(WRITEPATH . 'health/backup-heartbeat.json', 129600, 'copia de respaldo')],
        ];
    }

    /** @return array{level:string,state:string,detail:string} */
    private function probeApplication(): array
    {
        $requiredExtensions = ['curl', 'intl', 'json', 'mbstring', 'mysqli'];
        $missing = array_values(array_filter($requiredExtensions, static fn (string $extension): bool => !extension_loaded($extension)));
        if (PHP_VERSION_ID < 80200 || $missing !== []) {
            return [
                'level' => 'error',
                'state' => 'Requisitos incompletos',
                'detail' => $missing === []
                    ? 'La versión de PHP no cumple el mínimo 8.2.'
                    : 'Faltan ' . count($missing) . ' extensiones PHP requeridas.',
            ];
        }

        return [
            'level' => 'success',
            'state' => 'Operativa',
            'detail' => 'PHP ' . PHP_VERSION . ' · entorno ' . ENVIRONMENT . ' · extensiones requeridas disponibles.',
        ];
    }

    /** @return array{level:string,state:string,detail:string} */
    private function probeDatabase(): array
    {
        $startedAt = hrtime(true);
        $database = db_connect();
        $row = $database->query('SELECT 1 AS health_check')->getRowArray();
        if ((int) ($row['health_check'] ?? 0) !== 1) {
            throw new \RuntimeException('La consulta de control no devolvió el valor esperado.');
        }
        $latency = max(0.1, round((hrtime(true) - $startedAt) / 1_000_000, 1));
        $requiredTables = ['migrations', 'users', 'clientes', 'items', 'obligaciones', 'pagos', 'auditoria_eventos'];
        $missingTables = array_values(array_filter($requiredTables, static fn (string $table): bool => !$database->tableExists($table, false)));

        if ($missingTables !== []) {
            return [
                'level' => 'warning',
                'state' => 'Esquema incompleto',
                'detail' => 'Conexión verificada en ' . number_format($latency, 1) . ' ms; faltan ' . count($missingTables) . ' tablas requeridas.',
            ];
        }

        return [
            'level' => 'success',
            'state' => 'Operativa',
            'detail' => 'Consulta de control y tablas esenciales verificadas en ' . number_format($latency, 1) . ' ms.',
        ];
    }

    /** @return array{level:string,state:string,detail:string} */
    private function probeSession(): array
    {
        $session = config(SessionConfig::class);
        $cookie = config(CookieConfig::class);
        $application = config(AppConfig::class);
        if (!auth()->loggedIn() || (int) auth()->id() < 1) {
            return ['level' => 'error', 'state' => 'Sin sesión', 'detail' => 'No se pudo confirmar una identidad autenticada.'];
        }

        $handler = (new \ReflectionClass($session->driver))->getShortName();
        if ($session->driver === FileHandler::class && (!is_dir($session->savePath) || !is_writable($session->savePath))) {
            return ['level' => 'error', 'state' => 'Almacenamiento inválido', 'detail' => 'El controlador de sesión no puede escribir en su almacenamiento configurado.'];
        }

        $secureCookiesRequired = ENVIRONMENT === 'production'
            || strtolower((string) parse_url($application->baseURL, PHP_URL_SCHEME)) === 'https';
        $cookieSafe = $cookie->httponly
            && in_array($cookie->samesite, ['Lax', 'Strict'], true)
            && (!$secureCookiesRequired || $cookie->secure);
        if (!$cookieSafe) {
            return ['level' => 'warning', 'state' => 'Revisar configuración', 'detail' => 'La sesión funciona, pero las opciones de la cookie requieren revisión para este entorno.'];
        }

        return [
            'level' => 'success',
            'state' => 'Operativa',
            'detail' => 'Identidad autenticada · ' . $handler . ' · cookie HttpOnly y SameSite ' . $cookie->samesite . '.',
        ];
    }

    /** @return array{level:string,state:string,detail:string} */
    private function probeEmail(): array
    {
        $email = config(EmailConfig::class);
        if (filter_var($email->fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['level' => 'unknown', 'state' => 'No configurado', 'detail' => 'Falta definir un remitente válido; no se intentó enviar correo.'];
        }

        $protocolReady = match ($email->protocol) {
            'smtp' => $email->SMTPHost !== '' && $email->SMTPPort > 0,
            'sendmail' => is_executable($email->mailPath),
            'mail' => function_exists('mail'),
            default => false,
        };
        if (!$protocolReady) {
            return ['level' => 'warning', 'state' => 'Configuración incompleta', 'detail' => 'El remitente existe, pero el transporte de correo no está listo.'];
        }

        return ['level' => 'warning', 'state' => 'Configurado', 'detail' => 'La configuración está completa; la entrega requiere una prueba explícita y no se ejecuta al cargar esta página.'];
    }

    /** @return array{level:string,state:string,detail:string} */
    private function probeHeartbeat(string $path, int $maximumAgeSeconds, string $eventLabel): array
    {
        if (!is_file($path)) {
            return ['level' => 'unknown', 'state' => 'No supervisado', 'detail' => 'No existe evidencia registrada de la última ' . $eventLabel . '.'];
        }
        $contents = file_get_contents($path);
        $payload = is_string($contents) ? json_decode($contents, true) : null;
        if (!is_array($payload) || !is_string($payload['completed_at'] ?? null)) {
            return ['level' => 'warning', 'state' => 'Evidencia inválida', 'detail' => 'El registro de la última ' . $eventLabel . ' no tiene un formato válido.'];
        }

        try {
            $completedAt = new \DateTimeImmutable($payload['completed_at']);
        } catch (\Throwable) {
            return ['level' => 'warning', 'state' => 'Evidencia inválida', 'detail' => 'La fecha registrada para la última ' . $eventLabel . ' no es válida.'];
        }
        $now = ($this->clock)();
        $age = max(0, $now->getTimestamp() - $completedAt->getTimestamp());
        $displayDate = $completedAt->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
        if (($payload['status'] ?? '') !== 'success') {
            return ['level' => 'error', 'state' => 'Última ejecución fallida', 'detail' => 'La evidencia más reciente (' . $displayDate . ') registra un fallo.'];
        }
        if ($age > $maximumAgeSeconds) {
            return ['level' => 'warning', 'state' => 'Evidencia vencida', 'detail' => 'La última ' . $eventLabel . ' correcta fue el ' . $displayDate . '.'];
        }

        return ['level' => 'success', 'state' => 'Al día', 'detail' => 'Última ' . $eventLabel . ' correcta: ' . $displayDate . '.'];
    }

    private function actionLabel(string $action): string
    {
        return self::AUDIT_ACTIONS[$action] ?? 'Acción administrativa';
    }

    private function referenceLabel(string $reference): string
    {
        if (preg_match('/^(cliente|item|usuario):(\d+)$/D', $reference, $matches) !== 1) { return $reference ?: 'Registro interno'; }
        $sources = [
            'cliente' => ['table' => 'clientes', 'field' => 'nombre', 'label' => 'Cliente'],
            'item' => ['table' => 'items', 'field' => 'nombre', 'label' => 'Ítem'],
            'usuario' => ['table' => 'users', 'field' => 'username', 'label' => 'Usuario'],
        ];
        $source = $sources[$matches[1]];
        $row = db_connect()->table($source['table'])->select($source['field'])->where('id', (int) $matches[2])->get()->getRowArray();
        return $row ? (string) $row[$source['field']] : $source['label'] . ' #' . $matches[2];
    }
}
