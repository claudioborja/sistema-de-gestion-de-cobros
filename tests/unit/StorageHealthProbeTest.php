<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StorageHealthProbe;
use CodeIgniter\Test\CIUnitTestCase;

final class StorageHealthProbeTest extends CIUnitTestCase
{
    public function testItVerifiesPrivateStorageWithRealReadAndWrite(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cobros-storage-' . bin2hex(random_bytes(6));
        $public = $base . DIRECTORY_SEPARATOR . 'public';
        $writable = $base . DIRECTORY_SEPARATOR . 'writable';
        mkdir($public, 0700, true);
        mkdir($writable, 0700, true);

        try {
            $result = (new StorageHealthProbe($writable, $public))->inspect();

            $this->assertSame('success', $result['level']);
            $this->assertSame('Operativo', $result['state']);
            $this->assertSame([], glob($writable . DIRECTORY_SEPARATOR . 'health-*') ?: []);
        } finally {
            rmdir($writable);
            rmdir($public);
            rmdir($base);
        }
    }

    public function testItRejectsStorageInsideThePublicDirectory(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cobros-storage-' . bin2hex(random_bytes(6));
        $public = $base . DIRECTORY_SEPARATOR . 'public';
        $writable = $public . DIRECTORY_SEPARATOR . 'writable';
        mkdir($writable, 0700, true);

        try {
            $result = (new StorageHealthProbe($writable, $public))->inspect();

            $this->assertSame('error', $result['level']);
            $this->assertSame('Ubicación insegura', $result['state']);
        } finally {
            rmdir($writable);
            rmdir($public);
            rmdir($base);
        }
    }
}
