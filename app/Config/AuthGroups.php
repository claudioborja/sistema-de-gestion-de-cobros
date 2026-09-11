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
        'catalogo.gestionar'=>'Administrar catálogo', 'pagos.bancarios.confirmar'=>'Confirmar pagos bancarios',
    ];
    public array $matrix = [
        'administrador'=>['clientes.*', 'catalogo.gestionar', 'pagos.bancarios.confirmar'],
        'cajera'=>['clientes.ver'], 'cliente'=>[],
    ];
}
