<?php
declare(strict_types=1);
namespace App\Domain;
final class ClientInput
{
    public static function validate(array $input): array
    {
        $data = [];
        foreach (['nombre','direccion','identificacion','tipo','pais','email','telefono'] as $key) {
            $data[$key] = is_string($input[$key] ?? '') ? trim($input[$key] ?? '') : '';
        }
        $data['nombre'] = preg_replace('/\s+/u', ' ', $data['nombre']);
        $data['identificacion'] = strtoupper(preg_replace('/[\s-]+/', '', $data['identificacion']));
        $data['pais'] = strtoupper($data['pais'] ?: 'EC');
        $data['tipo'] = $data['tipo'] ?: 'CEDULA';
        $errors = [];
        if (mb_strlen($data['nombre']) < 2 || mb_strlen($data['nombre']) > 160) { $errors['nombre'] = 'Escribe entre 2 y 160 caracteres.'; }
        if (mb_strlen($data['direccion']) > 240) { $errors['direccion'] = 'Máximo 240 caracteres.'; }
        if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190)) { $errors['email'] = 'Escribe un correo válido.'; }
        if ($data['telefono'] !== '' && !preg_match('/^[+0-9 ()-]{5,30}$/D', $data['telefono'])) { $errors['telefono'] = 'Revisa el teléfono (5 a 30 caracteres).'; }
        if (!preg_match('/^[A-Z]{2}$/D', $data['pais'])) { $errors['pais'] = 'Usa el código de país de dos letras.'; }
        if (!in_array($data['tipo'], ['CEDULA','RUC','PASAPORTE','OTRO'], true)) { $errors['tipo'] = 'Selecciona un tipo válido.'; }
        if ($data['identificacion'] !== '' && !preg_match('/^[A-Z0-9]{3,40}$/D', $data['identificacion'])) { $errors['identificacion'] = 'Usa entre 3 y 40 letras o números.'; }
        if ($errors) { throw new ValidationException($errors); }
        return $data;
    }
}
