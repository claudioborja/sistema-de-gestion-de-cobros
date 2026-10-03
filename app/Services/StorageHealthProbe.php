<?php

declare(strict_types=1);

namespace App\Services;

final class StorageHealthProbe
{
    public function __construct(
        private readonly string $writablePath,
        private readonly string $publicPath,
    ) {
    }

    /** @return array{level:string,state:string,detail:string} */
    public function inspect(): array
    {
        $writable = realpath($this->writablePath);
        $public = realpath($this->publicPath);
        if ($writable === false || !is_dir($writable) || !is_writable($writable)) {
            return ['level' => 'error', 'state' => 'No escribible', 'detail' => 'El almacenamiento privado no está disponible para escritura.'];
        }
        if ($public !== false && ($writable === $public || str_starts_with($writable . DIRECTORY_SEPARATOR, $public . DIRECTORY_SEPARATOR))) {
            return ['level' => 'error', 'state' => 'Ubicación insegura', 'detail' => 'El almacenamiento privado quedó dentro del directorio público.'];
        }

        $temporary = tempnam($writable, 'health-');
        if ($temporary === false) {
            throw new \RuntimeException('No se pudo crear el archivo de control.');
        }
        try {
            $written = file_put_contents($temporary, 'cobros-health', LOCK_EX);
            if ($written !== 13 || file_get_contents($temporary) !== 'cobros-health') {
                throw new \RuntimeException('La comprobación de lectura y escritura no coincidió.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return ['level' => 'success', 'state' => 'Operativo', 'detail' => 'Lectura y escritura verificadas fuera del directorio público.'];
    }
}
