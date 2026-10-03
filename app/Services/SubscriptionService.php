<?php

declare(strict_types=1);
namespace App\Services;

use App\Domain\Money;
use App\Domain\SubscriptionCalendar as Calendar;
use App\Domain\ValidationException;

final class SubscriptionService
{
    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
    }

    public function validate(array $input): array
    {
        $errors=[];
        $str=static fn(string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $data=['item_id'=>$str('item_id'), 'start'=>$str('start'), 'end'=>$str('end'), 'months'=>$str('months'),
            'policy'=>$str('policy'), 'amount'=>$str('amount'), 'billing'=>$str('billing'), 'days'=>$str('days'), 'acceptance'=>$str('acceptance')];
        if (!ctype_digit($data['item_id']) || (int)$data['item_id'] < 1) { $errors['item_id']='Selecciona un servicio activo del catálogo.'; }
        if (!in_array($data['months'],['1','12'],true)) { $errors['months']='Selecciona mensual o anual.'; }
        if (!ctype_digit($data['days']) || (int)$data['days']>365) { $errors['days']='El plazo debe estar entre 0 y 365 días.'; }
        if (!in_array($data['acceptance'],['0','1'],true)) { $errors['acceptance']='Selecciona la modalidad de renovación.'; }
        try {
            Calendar::date($data['start']);
            if ($data['start']<'2000-01-01' || $data['start']>'2090-12-31') { throw new \InvalidArgumentException('El inicio debe estar entre 2000 y 2090.'); }
        } catch (\InvalidArgumentException $e) { $errors['start']=$e->getMessage(); }
        try { $data['amount']=Money::decimal(Money::cents($data['amount'])); } catch (\InvalidArgumentException $e) { $errors['amount']=$e->getMessage(); }
        if (!$errors) {
            try { Calendar::periods($data['start'],(int)$data['months'],$data['policy'],$data['amount'],$data['billing'],(int)$data['days']); }
            catch (\InvalidArgumentException $e) { $errors['policy']=$e->getMessage(); }
        }
        if ($data['end'] !== '') {
            try { Calendar::date($data['end']); if ($data['end'] <= $data['start']) { throw new \InvalidArgumentException('El final debe ser posterior al inicio.'); } }
            catch (\InvalidArgumentException $e) { $errors['end']=$e->getMessage(); }
        }
        if ($errors) { throw new ValidationException($errors); }
        if ($data['end'] !== '') {
            $periods=Calendar::periods($data['start'],(int)$data['months'],$data['policy'],$data['amount'],$data['billing'],(int)$data['days'],1200);
            if (!in_array($data['end'],array_column($periods,'end'),true)) { throw new ValidationException(['end'=>'El final debe coincidir con el inicio del siguiente período; no se cobra un período parcial.']); }
        }
        return $data;
    }

    public function preview(array $input): array
    {
        $d=$this->validate($input);
        return Calendar::periods($d['start'],(int)$d['months'],$d['policy'],$d['amount'],$d['billing'],(int)$d['days']);
    }

    public function create(int $actor, int $clientId, array $input, string $key): array
    {
        Access::require($actor,'contratos.gestionar');
        $d=$this->validate($input);
        return $this->transaction($actor,$clientId,null,'contratos.crear',$key,['client'=>$clientId]+$d,null,function($db,$op) use($clientId,$d) {
            $item=$db->table('items')->where('id',(int)$d['item_id'])->where('tipo','SERVICIO')->where('activo',1)->get()->getRowArray();
            if (!$item) { throw new ValidationException(['item_id'=>'Selecciona un servicio activo del catálogo.']); }
            $db->table('contratos')->insert(['cliente_id'=>$clientId,'item_id'=>$item['id'],'servicio'=>$item['nombre'],
                'fecha_inicio'=>$d['start'],'fecha_fin_pactada'=>$d['end'] ?: null,'frecuencia_meses'=>(int)$d['months'],
                'politica_calendario'=>$d['policy'],'operacion_id'=>$op]);
            $id=(int)$db->insertID();
            $db->table('contrato_condiciones')->insert(['contrato_id'=>$id,'version'=>1,'vigente_desde'=>$d['start'],
                'importe_periodo'=>$d['amount'],'momento_cobro'=>$d['billing'],'plazo_dias'=>(int)$d['days'],
                'requiere_aceptacion'=>(int)$d['acceptance'],'operacion_id'=>$op]);
            return ['id'=>$id];
        },true);
    }

    public function find(int $id): array
    {
        $db=db_connect();
        $row=$db->query('SELECT s.*, c.nombre cliente FROM contratos s JOIN clientes c ON c.id=s.cliente_id WHERE s.id=?',[$id])->getRowArray();
        if (!$row) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        $row['conditions']=$db->table('contrato_condiciones')->where('contrato_id',$id)->orderBy('vigente_desde')->get()->getResultArray();
        $row['events']=$db->query('SELECT e.*, o.motivo FROM contrato_eventos e JOIN operaciones o ON o.id=e.operacion_id WHERE e.contrato_id=? ORDER BY e.fecha_efectiva',[$id])->getResultArray();
        $row['acceptances']=$db->query('SELECT r.*, o.registrada_en, u.username FROM renovacion_decisiones r JOIN operaciones o ON o.id=r.operacion_id JOIN users u ON u.id=o.usuario_id WHERE r.contrato_id=?',[$id])->getResultArray();
        $row['periods']=$db->table('periodos_contrato')->where('contrato_id',$id)->orderBy('periodo_desde')->get()->getResultArray();
        $row['status']=$this->statusAt($row,self::today());
        return $row;
    }

    public function listing(?int $clientId, string $query='', int $page=1): array
    {
        $db=db_connect();
        $builder=$db->table('contratos s')->select('s.*, c.nombre cliente')->join('clientes c','c.id=s.cliente_id');
        if ($clientId !== null) { $builder->where('s.cliente_id',$clientId); }
        if ($query !== '') { $builder->groupStart()->like('c.nombre',mb_substr($query,0,100))->orLike('s.servicio',mb_substr($query,0,100))->groupEnd(); }
        $total=$builder->countAllResults(false);
        $rows=$builder->orderBy('s.id','DESC')->limit(25,(max(1,$page)-1)*25)->get()->getResultArray();
        foreach ($rows as &$row) {
            $s=$this->find((int)$row['id']); $row['status']=$s['status'];
            $condition=$this->conditionAt($s,max(self::today(),$s['fecha_inicio']));
            $row['amount']=$condition['importe_periodo'];
        }
        return ['rows'=>$rows,'total'=>$total,'page'=>max(1,$page)];
    }

    /** Projections include skipped and unaccepted periods; they never create debt. */
    public function projection(array $s, string $through): array
    {
        Calendar::date($through);
        $first=$s['conditions'][0];
        $start=Calendar::date($s['fecha_inicio']); $until=Calendar::date($through);
        $distance=max(0,((int)$until->format('Y')-(int)$start->format('Y'))*12+(int)$until->format('n')-(int)$start->format('n'));
        $count=min(1200,intdiv($distance,(int)$s['frecuencia_meses'])+4);
        $base=Calendar::periods($s['fecha_inicio'],(int)$s['frecuencia_meses'],$s['politica_calendario'],$first['importe_periodo'],$first['momento_cobro'],(int)$first['plazo_dias'],$count);
        $rows=[];
        foreach ($base as $p) {
            if ($s['fecha_fin_pactada'] && $p['end']>$s['fecha_fin_pactada']) { break; }
            $condition=$this->conditionAt($s,$p['start']);
            if ($p['start'] !== $s['fecha_inicio']) { $p['amount']=$condition['importe_periodo']; }
            $p['version']=(int)$condition['version'];
            $p['eligible']=$condition['momento_cobro']==='ANTICIPADO' ? $p['start'] : $p['end'];
            $p['due']=Calendar::date($p['eligible'])->modify('+'.$condition['plazo_dias'].' days')->format('Y-m-d');
            $p['status']=$this->statusAt($s,$p['start']);
            $p['obligation_id']=null;
            foreach ($s['periods'] as $generated) {
                if ($generated['periodo_desde']===$p['start']) { $p['obligation_id']=(int)$generated['obligacion_id']; $p['status']='GENERADO'; break; }
            }
            if ($p['status']==='ACTIVA') {
                $accepted=!(bool)$condition['requiere_aceptacion'];
                foreach ($s['acceptances'] as $a) {
                    if ($a['periodo_desde']===$p['start'] && (int)$a['condicion_version']===$p['version']) { $accepted=true; }
                }
                $p['status']=$accepted ? ($p['eligible']<=$through ? 'POR_GENERAR' : 'PROYECTADO') : 'PENDIENTE_ACEPTACION';
            }
            $rows[]=$p;
        }
        return $rows;
    }

    public function generate(int $actor, int $id, ?string $through=null): int
    {
        Access::require($actor,'contratos.generar');
        $through ??= self::today(); Calendar::date($through);
        $s=$this->find($id); $created=0;
        foreach ($this->projection($s,$through) as $p) {
            if ($p['status']!=='POR_GENERAR') { continue; }
            $result=$this->transaction($actor,(int)$s['cliente_id'],$id,'contratos.generar',
                'periodo-'.$id.'-'.$p['start'],['id'=>$id,'start'=>$p['start']],null,
                function($db,$op,$locked) use($p,$through,$id) {
                    // Re-evaluate under client/contract locks, including conditions and events.
                    $current=null;
                    foreach ($this->projection($locked,$through) as $candidate) { if ($candidate['start']===$p['start']) { $current=$candidate; break; } }
                    if (!$current || $current['status']!=='POR_GENERAR') { return ['created'=>false]; }
                    $p=$current;
                    $concept=mb_substr($locked['servicio'].' · '.$p['start'].' al '.$p['last'],0,300);
                    $db->table('obligaciones')->insert(['cliente_id'=>$locked['cliente_id'],'origen'=>'PERIODO','concepto'=>$concept,
                        'fecha_origen'=>$p['eligible'],'importe_base'=>$p['amount'],'operacion_creacion_id'=>$op,'operacion_confirmacion_id'=>$op]);
                    $obligation=(int)$db->insertID();
                    $db->table('documentos_obligacion')->insert(['obligacion_id'=>$obligation,'tipo'=>'OTRO','numero_completo'=>'SUS-'.$id.'-'.$p['start'],
                        'numero_normalizado'=>'SUS'.$id.str_replace('-','',$p['start']),'fecha_emision'=>$p['eligible']]);
                    $db->table('obligacion_detalles')->insert(['obligacion_id'=>$obligation,'renglon'=>1,'item_id'=>$locked['item_id'],'descripcion_pactada'=>$concept,'cantidad'=>'1','importe_linea_documentado'=>$p['amount']]);
                    $db->table('cuotas')->insert(['obligacion_id'=>$obligation,'numero_cuota'=>1]);
                    $db->table('cuota_versiones')->insert(['cuota_id'=>$db->insertID(),'version'=>1,'fecha_vencimiento'=>$p['due'],'importe_programado'=>$p['amount'],'operacion_id'=>$op]);
                    $db->table('periodos_contrato')->insert(['contrato_id'=>$id,'periodo_desde'=>$p['start'],'periodo_hasta'=>$p['end'],'condicion_version'=>$p['version'],'obligacion_id'=>$obligation]);
                    return ['created'=>true,'obligation_id'=>$obligation];
                },false,true);
            if ($result['created'] ?? false) { $created++; }
        }
        return $created;
    }

    public function state(int $actor,int $id,string $type,string $date,string $reason,string $key): array
    {
        Access::require($actor,'contratos.gestionar'); $this->reason($reason); $this->validDate($date);
        if (!in_array($type,['PAUSA','REACTIVACION','CANCELACION'],true)) { throw new ValidationException(['type'=>'Selecciona una acción válida.']); }
        $s=$this->find($id);
        return $this->transaction($actor,(int)$s['cliente_id'],$id,'contratos.estado',$key,compact('id','type','date','reason'),$reason,function($db,$op,$s) use($id,$type,$date) {
            $this->boundary($s,$date);
            $last=$s['events'] ? $s['events'][array_key_last($s['events'])] : null;
            if ($last && ($date<=$last['fecha_efectiva'] || $last['tipo']==='CANCELACION')) { throw new ValidationException(['date'=>'La fecha debe ser posterior al último evento y el contrato no puede estar cancelado.']); }
            $state=$this->statusAt($s,$date);
            if (($type==='REACTIVACION' && $state!=='PAUSADA') || ($type==='PAUSA' && $state!=='ACTIVA')) { throw new ValidationException(['type'=>'La transición no corresponde al estado del servicio.']); }
            foreach ($s['periods'] as $p) { if ($p['periodo_desde'] >= $date) { throw new ValidationException(['date'=>'Ya hay cargos desde esa fecha. Elige un período posterior; las deudas anteriores se conservan.']); } }
            $db->table('contrato_eventos')->insert(['contrato_id'=>$id,'tipo'=>$type,'fecha_efectiva'=>$date,'operacion_id'=>$op]);
            return ['id'=>$id];
        });
    }

    public function price(int $actor,int $id,string $date,string $amount,string $reason,string $key): array
    {
        Access::require($actor,'contratos.gestionar'); $this->reason($reason); $this->validDate($date);
        try { $amount=Money::decimal(Money::cents($amount)); } catch (\InvalidArgumentException $e) { throw new ValidationException(['amount'=>$e->getMessage()]); }
        if ($date<=self::today()) { throw new ValidationException(['date'=>'El nuevo precio debe comenzar en un período futuro.']); }
        $s=$this->find($id);
        return $this->transaction($actor,(int)$s['cliente_id'],$id,'contratos.precio',$key,compact('id','date','amount','reason'),$reason,function($db,$op,$s) use($id,$date,$amount) {
            $this->boundary($s,$date);
            $last=$s['conditions'][array_key_last($s['conditions'])];
            if ($date<=$last['vigente_desde'] || $this->statusAt($s,$date)==='CANCELADA') { throw new ValidationException(['date'=>'Elige un período posterior a la última tarifa y anterior a la cancelación.']); }
            foreach ($s['periods'] as $p) { if ($p['periodo_desde'] >= $date) { throw new ValidationException(['date'=>'No se puede cambiar el precio de períodos generados.']); } }
            $last['version']=(int)$last['version']+1; $last['vigente_desde']=$date; $last['importe_periodo']=$amount; $last['operacion_id']=$op;
            $db->table('contrato_condiciones')->insert($last);
            return ['id'=>$id];
        });
    }

    public function accept(int $actor,int $id,string $date,string $evidence,string $key,?int $expectedVersion=null): array
    {
        Access::require($actor,'contratos.gestionar'); $this->validDate($date);
        if (mb_strlen(trim($evidence))<10 || mb_strlen($evidence)>1000) { throw new ValidationException(['evidence'=>'Registra la evidencia de aceptación (10 a 1000 caracteres).']); }
        $s=$this->find($id);
        return $this->transaction($actor,(int)$s['cliente_id'],$id,'contratos.aceptar',$key,compact('id','date','evidence','expectedVersion'),null,function($db,$op,$s) use($id,$date,$evidence,$expectedVersion) {
            $this->boundary($s,$date);
            if ($date<self::today()) { throw new ValidationException(['date'=>'La aceptación tardía requiere revisión administrativa. Crea una propuesta con inicio vigente; no se emitirán cargos retroactivos.']); }
            $condition=$this->conditionAt($s,$date);
            if ($expectedVersion !== null && $expectedVersion !== (int)$condition['version']) { throw new ValidationException(['date'=>'El precio cambió desde que abriste la pantalla. Revisa y acepta la nueva propuesta.']); }
            if (!$condition['requiere_aceptacion'] || $this->statusAt($s,$date)!=='ACTIVA') { throw new ValidationException(['date'=>'El período no requiere aceptación o no está activo.']); }
            if ($db->table('renovacion_decisiones')->where(['contrato_id'=>$id,'periodo_desde'=>$date,'condicion_version'=>$condition['version']])->countAllResults()) { return ['id'=>$id]; }
            $db->table('renovacion_decisiones')->insert(['contrato_id'=>$id,'periodo_desde'=>$date,'condicion_version'=>$condition['version'],'evidencia'=>trim($evidence),'operacion_id'=>$op]);
            return ['id'=>$id];
        });
    }

    private function transaction(int $actor,int $client,?int $id,string $action,string $key,array $payload,?string $reason,callable $work,bool $active=false,bool $generation=false): array
    {
        $db=db_connect(); $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $c=$db->query('SELECT id,activo FROM clientes WHERE id=? FOR UPDATE',[$client])->getRowArray();
            if (!$c || ($active && !$c['activo'])) { throw new ValidationException(['client'=>'Selecciona un cliente activo.']); }
            $s=null;
            if ($id !== null) { $db->query('SELECT id FROM contratos WHERE id=? FOR UPDATE',[$id]); $s=$this->find($id); }
            // Unique period is authoritative across web and cron actors; do not replay another actor's operation.
            if ($generation && $db->table('periodos_contrato')->where(['contrato_id'=>$id,'periodo_desde'=>$payload['start']])->countAllResults()) { $db->transRollback(); return ['created'=>false]; }
            $operations=new OperationService(); $op=$operations->reserve($db,$actor,$action,$key,$payload,$reason);
            if ($op['result']!==null) { $db->transRollback(); return $generation ? ['created'=>false] : $op['result']; }
            $result=$work($db,(int)$op['id'],$s);
            // A candidate invalidated by a concurrent pause must remain generatable after reactivation.
            if ($generation && !($result['created'] ?? false)) { $db->transRollback(); return $result; }
            $operations->complete($db,(int)$op['id'],$result);
            Audit::record($actor,$action,'contrato:'.($id ?? $result['id']),['resultado'=>$result]);
            if (!$db->transStatus() || !$db->transCommit()) { throw new \RuntimeException('No se pudo confirmar la operación.'); }
            return $result;
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }

    private function conditionAt(array $s,string $date): array
    {
        $condition=$s['conditions'][0];
        foreach ($s['conditions'] as $c) { if ($c['vigente_desde'] <= $date) { $condition=$c; } }
        return $condition;
    }
    private function statusAt(array $s,string $date): string
    {
        $state='ACTIVA';
        foreach ($s['events'] as $e) { if ($e['fecha_efectiva'] <= $date) { $state=match($e['tipo']) {'PAUSA'=>'PAUSADA','CANCELACION'=>'CANCELADA',default=>'ACTIVA'}; } }
        if ($s['fecha_fin_pactada'] && $date >= $s['fecha_fin_pactada']) { return 'FINALIZADA'; }
        return $state;
    }
    private function boundary(array $s,string $date): void
    {
        foreach ($this->projection($s,$date) as $p) { if ($p['start']===$date) { return; } }
        throw new ValidationException(['date'=>'Elige el inicio exacto de un período de la proyección. Los cambios a mitad de período requieren revisión administrativa.']);
    }
    private function reason(string $reason): void
    {
        if (mb_strlen(trim($reason))<10 || mb_strlen($reason)>500) { throw new ValidationException(['reason'=>'Escribe un motivo de 10 a 500 caracteres.']); }
    }
    private function validDate(string $date): void
    {
        try { Calendar::date($date); } catch (\InvalidArgumentException $e) { throw new ValidationException(['date'=>$e->getMessage()]); }
    }
}
