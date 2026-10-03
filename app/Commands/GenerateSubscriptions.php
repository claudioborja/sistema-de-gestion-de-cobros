<?php

declare(strict_types=1);
namespace App\Commands;
use App\Services\{Access,SubscriptionService};
use CodeIgniter\CLI\{BaseCommand,CLI};

final class GenerateSubscriptions extends BaseCommand
{
    protected $group='Cobros';
    protected $name='suscripciones:generar';
    protected $description='Genera cargos elegibles sin duplicar períodos. Requiere el ID de un usuario interno autorizado.';
    protected $usage='suscripciones:generar <usuario_id>';
    protected $arguments=['usuario_id'=>'Usuario activo con permiso contratos.generar.'];
    public function run(array $params)
    {
        $actor=filter_var($params[0] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$actor) { CLI::error('Indica el ID de un usuario autorizado.'); return EXIT_ERROR; }
        Access::require($actor,'contratos.generar');
        $service=new SubscriptionService(); $last=0; $created=0; $failures=0;
        do {
            $rows=db_connect()->table('contratos')->select('id')->where('id >',$last)->orderBy('id')->limit(100)->get()->getResultArray();
            foreach ($rows as $row) {
                $last=(int)$row['id'];
                try { $created+=$service->generate($actor,$last); }
                catch (\Throwable $e) { $failures++; log_message('error','Generación de suscripción {id}: {message}',['id'=>$last,'message'=>$e->getMessage()]); CLI::error('Falló la suscripción #'.$last.'. Consulta el registro y vuelve a ejecutar para recuperar.'); }
            }
        } while (count($rows)===100);
        CLI::write('Cargos generados: '.$created.'. Fallos: '.$failures.'.');
        return $failures ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
