<?php
namespace Config;
class AuthGroups extends \CodeIgniter\Shield\Config\AuthGroups
{
    public string $defaultGroup = 'cliente';
    public array $groups = [
        'administrador'=>['title'=>'Administrador', 'description'=>'Propietario y operador de caja'],
        'cajera'=>['title'=>'Cajera', 'description'=>'Operaciones internas autorizadas'],
        'cliente'=>['title'=>'Cliente', 'description'=>'Acceso externo limitado'],
    ];
    public array $permissions = [
        'clientes.ver'=>'Consultar clientes', 'clientes.crear'=>'Crear clientes',
        'clientes.editar'=>'Editar clientes', 'clientes.desactivar'=>'Desactivar clientes',
        'catalogo.gestionar'=>'Administrar catálogo', 'auditoria.ver'=>'Consultar actividad auditada',
        'auditoria.limpiar'=>'Eliminar el historial de auditoría',
        'pagos.bancarios.confirmar'=>'Confirmar pagos bancarios',
        'pagos.crear'=>'Registrar pagos',
        'pagos.ver'=>'Consultar pagos',
        'pagos.editar'=>'Aplicar pagos',
        'pagos.reaplicar'=>'Revertir y redistribuir aplicaciones',
        'pagos.revertir'=>'Revertir pagos',
        'cartera.ver'=>'Consultar cartera',
        'contratos.ver'=>'Consultar suscripciones',
        'contratos.gestionar'=>'Crear y administrar suscripciones',
        'contratos.generar'=>'Generar cargos recurrentes',
        'cartera.reprogramar'=>'Reprogramar obligaciones',
        'archivos.ver'=>'Consultar archivos privados',
        'archivos.gestionar'=>'Adjuntar archivos privados',
        'caja.operar'=>'Operar caja propia',
        'caja.abrir'=>'Abrir turno de caja',
        'caja.movimientos'=>'Registrar movimientos de caja',
        'caja.cerrar'=>'Cerrar y arquear turnos de caja',
        'caja.entregar'=>'Entregar y recibir turnos de caja',
        'caja.gestionar'=>'Administrar cajas físicas',
        'usuarios.ver'=>'Consultar usuarios y permisos',
        'usuarios.crear'=>'Crear usuarios',
        'usuarios.editar'=>'Editar usuarios y su estado',
        'reportes.ver'=>'Consultar reportes administrativos',
        'operacion.ver'=>'Consultar el estado operativo',
        'configuracion.gestionar'=>'Administrar configuración del negocio',
    ];
    public array $matrix = [
        'administrador'=>['contratos.*', 'clientes.*', 'catalogo.gestionar', 'auditoria.*', 'pagos.*', 'cartera.*', 'archivos.*', 'caja.*', 'pagos.bancarios.confirmar', 'usuarios.*', 'reportes.ver', 'operacion.ver', 'configuracion.gestionar'],
        'cajera'=>['contratos.ver', 'clientes.ver', 'cartera.ver', 'archivos.ver', 'archivos.gestionar', 'pagos.ver', 'pagos.crear', 'pagos.editar', 'caja.operar', 'caja.abrir', 'caja.movimientos', 'caja.cerrar', 'caja.entregar'], 'cliente'=>[],
    ];
}
