<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;

final class DocumentAttachmentService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    /** @return list<array<string, mixed>> */
    public function listForDocument(int $obligationId): array
    {
        $this->requireDocument($obligationId);
        $rows = db_connect()->query("SELECT a.id, a.nombre_original, a.tipo_mime, a.extension,
            a.tamano_bytes, a.notas, a.cargado_en, u.username cargado_por_nombre
            FROM archivos_documento a
            JOIN users u ON u.id = a.cargado_por
            WHERE a.obligacion_id = ?
            ORDER BY a.cargado_en DESC, a.id DESC", [$obligationId])->getResultArray();

        foreach ($rows as &$row) {
            $row['tamano_legible'] = $this->formatSize((int) $row['tamano_bytes']);
            $uploadedAt = new \DateTimeImmutable((string) $row['cargado_en'], new \DateTimeZone('UTC'));
            $row['cargado_en_legible'] = $uploadedAt->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
            $row['tipo_legible'] = match ((string) $row['extension']) {
                'pdf' => 'PDF',
                'png' => 'Imagen PNG',
                default => 'Imagen JPEG',
            };
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function store(int $actorId, int $obligationId, ?UploadedFile $file, string $notes): array
    {
        Access::require($actorId, 'archivos.gestionar');
        $this->requireDocument($obligationId);
        $validated = $this->validateUpload($file, $notes);

        $relativeDirectory = 'documentos/' . $obligationId;
        $absoluteDirectory = WRITEPATH . 'uploads/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0770, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('No se pudo preparar el almacenamiento privado.');
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . $validated['extension'];
        $relativePath = $relativeDirectory . '/' . $storedName;
        $absolutePath = $absoluteDirectory . '/' . $storedName;
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $file->move($absoluteDirectory, $storedName, false);
            $hash = hash_file('sha256', $absolutePath);
            if (!is_string($hash)) {
                throw new \RuntimeException('No se pudo verificar la integridad del archivo.');
            }

            $db->table('archivos_documento')->insert([
                'obligacion_id' => $obligationId,
                'nombre_original' => $validated['name'],
                'ruta_relativa' => $relativePath,
                'tipo_mime' => $validated['mime'],
                'extension' => $validated['extension'],
                'tamano_bytes' => $validated['size'],
                'hash_sha256' => $hash,
                'notas' => $validated['notes'] === '' ? null : $validated['notes'],
                'cargado_por' => $actorId,
                'cargado_en' => gmdate('Y-m-d H:i:s.u'),
            ]);
            $attachmentId = (int) $db->insertID();
            Audit::record($actorId, 'documentos.archivo.cargar', 'archivo:' . $attachmentId, [
                'obligacion_id' => $obligationId,
                'tipo_mime' => $validated['mime'],
                'tamano_bytes' => $validated['size'],
                'hash_sha256' => $hash,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo registrar el archivo.');
            }

            return ['id' => $attachmentId, 'name' => $validated['name']];
        } catch (\Throwable $error) {
            $db->transRollback();
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function findForDownload(int $actorId, int $attachmentId): array
    {
        Access::require($actorId, 'archivos.ver');
        $row = db_connect()->query("SELECT a.*, o.cliente_id
            FROM archivos_documento a
            JOIN obligaciones o ON o.id = a.obligacion_id
            WHERE a.id = ?", [$attachmentId])->getRowArray();
        if (!$row) {
            throw PageNotFoundException::forPageNotFound();
        }

        $storageRoot = realpath(WRITEPATH . 'uploads');
        $absolutePath = realpath(WRITEPATH . 'uploads/' . (string) $row['ruta_relativa']);
        if ($storageRoot === false || $absolutePath === false
            || !str_starts_with($absolutePath, $storageRoot . DIRECTORY_SEPARATOR)
            || !is_file($absolutePath)) {
            throw PageNotFoundException::forPageNotFound();
        }

        Audit::record($actorId, 'documentos.archivo.descargar', 'archivo:' . $attachmentId, [
            'obligacion_id' => (int) $row['obligacion_id'],
        ]);
        $row['absolute_path'] = $absolutePath;

        return $row;
    }

    /** @return array{name: string, mime: string, extension: string, size: int, notes: string} */
    private function validateUpload(?UploadedFile $file, string $notes): array
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['attachment' => 'Selecciona un archivo para adjuntar.']);
        }
        if (!$file->isValid()) {
            $message = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El archivo supera el límite permitido de 2 MB.'
                : 'No se pudo recibir el archivo. Intenta nuevamente.';
            throw new ValidationException(['attachment' => $message]);
        }

        $size = $file->getSize();
        if ($size === false || $size <= 0) {
            throw new ValidationException(['attachment' => 'El archivo está vacío o no puede leerse.']);
        }
        if ($size > self::MAX_BYTES) {
            throw new ValidationException(['attachment' => 'El archivo supera el límite permitido de 2 MB.']);
        }

        $mime = strtolower($file->getMimeType());
        if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
            throw new ValidationException(['attachment' => 'Formato no permitido. Usa PDF, PNG o JPEG.']);
        }

        $clientName = basename(str_replace('\\', '/', $file->getClientName()));
        $clientName = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $clientName));
        if ($clientName === '') {
            $clientName = 'archivo.' . self::ALLOWED_MIME_TYPES[$mime];
        }
        $clientName = mb_substr($clientName, 0, 190);
        $cleanNotes = trim((string) preg_replace('/\s+/u', ' ', strip_tags($notes)));

        return [
            'name' => $clientName,
            'mime' => $mime,
            'extension' => self::ALLOWED_MIME_TYPES[$mime],
            'size' => $size,
            'notes' => mb_substr($cleanNotes, 0, 500),
        ];
    }

    private function requireDocument(int $obligationId): void
    {
        if ($obligationId < 1 || db_connect()->table('documentos_obligacion')->where('obligacion_id', $obligationId)->countAllResults() !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
}
