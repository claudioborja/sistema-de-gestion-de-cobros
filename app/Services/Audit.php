<?php
declare(strict_types=1);
namespace App\Services;
final class Audit
{
    public static function record(int $actor, string $action, string $reference, array $detail=[]): void
    {
        db_connect()->table('auditoria_eventos')->insert(['usuario_id'=>$actor, 'accion'=>$action, 'referencia_textual'=>$reference, 'registrado_en'=>gmdate('Y-m-d H:i:s'), 'detalle_sanitizado'=>json_encode($detail,JSON_THROW_ON_ERROR)]);
    }
}
