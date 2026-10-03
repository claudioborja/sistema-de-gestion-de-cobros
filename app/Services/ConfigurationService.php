<?php
declare(strict_types=1);

namespace App\Services;

use App\Domain\ValidationException;

final class ConfigurationService
{
    private const string KEY_BUSINESS = 'configuration_business';
    private const string KEY_MESSAGES = 'configuration_messages';

    public function business(): array
    {
        return $this->read(self::KEY_BUSINESS, $this->businessDefaults());
    }

    public function payments(): array
    {
        return ['methods' => ['transfer'], 'default_method' => 'transfer',
            'active_accounts' => count((new PaymentService())->activeBankAccounts())];
    }

    public function messages(): array
    {
        return $this->read(self::KEY_MESSAGES, $this->messagesDefaults());
    }

    public function saveBusiness(int $actorId, array $input): void
    {
        Access::require($actorId, 'configuracion.gestionar');

        $errors = [];
        $businessName = $this->sanitizeText($input['business_name'] ?? '', 120, true);
        if (mb_strlen($businessName) < 2) {
            $errors['business_name'] = 'Indica el nombre del negocio (mínimo 2 caracteres).';
        }

        $documentType = $input['document_type'] ?? '';
        if (!in_array($documentType, ['RUC', 'NIT', 'CC', 'No aplica'], true)) {
            $errors['document_type'] = 'Selecciona un tipo de documento válido.';
        }

        $documentValue = $this->sanitizeText($input['document_value'] ?? '', 40);
        if ($documentType !== 'No aplica' && $documentValue === '') {
            $errors['document_value'] = 'Completa el número de documento o marca "No aplica".';
        }

        $currency = $input['currency'] ?? '';
        if ($currency !== 'USD') {
            $errors['currency'] = 'Esta instalación opera únicamente en USD.';
        }

        $timezone = $input['timezone'] ?? '';
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Selecciona una zona horaria válida.';
        }

        $invoicePrefix = $this->sanitizeText($input['invoice_prefix'] ?? '', 15, false);
        if ($invoicePrefix === '' || !preg_match('/^[A-Z0-9\-_.]+$/D', $invoicePrefix)) {
            $errors['invoice_prefix'] = 'El prefijo debe tener letras, números o - _ .';
        }

        $invoiceSeed = $this->sanitizeInteger($input['invoice_seed'] ?? null);
        if ($invoiceSeed < 1) {
            $errors['invoice_seed'] = 'El número inicial del comprobante debe ser mayor a 0.';
        }

        $graceDays = $this->sanitizeInteger($input['grace_days'] ?? null);
        if ($graceDays < 0 || $graceDays > 365) {
            $errors['grace_days'] = 'Los días de gracia deben estar entre 0 y 365.';
        }

        $hasFooter = $this->sanitizeText($input['invoice_footer'] ?? '', 240);

        if ($errors) {
            throw new ValidationException($errors);
        }

        $this->write(self::KEY_BUSINESS, [
            'business_name' => $businessName,
            'document_type' => $documentType,
            'document_value' => $documentValue,
            'currency' => $currency,
            'timezone' => $timezone,
            'invoice_prefix' => $invoicePrefix,
            'invoice_seed' => $invoiceSeed,
            'grace_days' => $graceDays,
            'require_signature' => $this->sanitizeCheckbox($input['require_signature'] ?? null),
            'portal_enabled' => $this->sanitizeCheckbox($input['portal_enabled'] ?? null),
            'invoice_footer' => $hasFooter,
            'updated_at' => date('c'),
            'updated_by' => $actorId,
        ]);
    }


    public function saveMessages(int $actorId, array $input): void
    {
        Access::require($actorId, 'configuracion.gestionar');

        $errors = [];
        $invoiceSubject = $this->sanitizeText($input['invoice_subject'] ?? '', 140, true);
        if (mb_strlen($invoiceSubject) < 5) {
            $errors['invoice_subject'] = 'El asunto debe tener al menos 5 caracteres.';
        }

        $invoiceBody = $this->sanitizeText($input['invoice_body'] ?? '', 1200, true);
        if (mb_strlen($invoiceBody) < 10) {
            $errors['invoice_body'] = 'El texto del comprobante debe tener al menos 10 caracteres.';
        }

        $reminderBody = $this->sanitizeText($input['reminder_body'] ?? '', 1200, true);
        if (mb_strlen($reminderBody) < 10) {
            $errors['reminder_body'] = 'El texto de recordatorio debe tener al menos 10 caracteres.';
        }

        $notificationMethod = $input['default_message_method'] ?? '';
        if ($notificationMethod !== 'email') {
            $errors['default_message_method'] = 'El canal habilitado en esta etapa es correo.';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $this->write(self::KEY_MESSAGES, [
            'invoice_subject' => $invoiceSubject,
            'invoice_body' => $invoiceBody,
            'reminder_body' => $reminderBody,
            'default_message_method' => $notificationMethod,
            'invoice_send_copy' => $this->sanitizeCheckbox($input['invoice_send_copy'] ?? null),
            'reminder_copy_self' => $this->sanitizeCheckbox($input['reminder_copy_self'] ?? null),
            'updated_at' => date('c'),
            'updated_by' => $actorId,
        ]);
    }

    private function read(string $key, array $defaults): array
    {
        $row = db_connect()->table('configuracion_sistema')->where('clave', $key)->get()->getRowArray();
        return $row ? array_replace($defaults, json_decode($row['valor'], true, 512, JSON_THROW_ON_ERROR)) : $defaults;
    }

    private function write(string $key, array $data): void
    {
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $db->table('configuracion_sistema')->upsert(['clave' => $key, 'valor' => json_encode($data, JSON_THROW_ON_ERROR)]);
            Audit::record((int) $data['updated_by'], 'configuracion.guardar', $key);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo guardar la configuración.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    private function sanitizeText(mixed $value, int $maxLength, bool $preserveCase = true): string
    {
        $text = is_string($value) ? trim($value) : '';
        if (!$preserveCase) {
            $text = mb_strtoupper($text);
        }
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }
        return $text;
    }

    private function sanitizeInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '' && is_numeric($value)) {
            return (int) round((float) $value);
        }
        return 0;
    }

    private function sanitizeCheckbox(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'on', 'true'], true);
    }

    private function businessDefaults(): array
    {
        return [
            'business_name' => 'Mi negocio',
            'document_type' => 'RUC',
            'document_value' => '',
            'currency' => 'USD',
            'timezone' => 'America/Guayaquil',
            'invoice_prefix' => 'FAC',
            'invoice_seed' => 1,
            'grace_days' => 0,
            'require_signature' => false,
            'portal_enabled' => false,
            'invoice_footer' => 'Gracias por su pago.',
            'updated_at' => null,
            'updated_by' => null,
        ];
    }


    private function messagesDefaults(): array
    {
        return [
            'invoice_subject' => 'Comprobante de pago generado',
            'invoice_body' => 'Hola {{cliente}}. Tu comprobante {{comprobante}} por {{monto}} quedó registrado el {{fecha}}.',
            'reminder_body' => 'Hola {{cliente}}. Este es un recordatorio de pago de {{monto}} con fecha {{fecha_vencimiento}}.',
            'default_message_method' => 'email',
            'invoice_send_copy' => false,
            'reminder_copy_self' => false,
            'updated_at' => null,
            'updated_by' => null,
        ];
    }
}
