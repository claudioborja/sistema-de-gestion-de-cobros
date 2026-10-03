# Portal limitado del cliente

Estado: **implementado en código, sin validar en runtime**.

El portal permite únicamente registrar comprobantes propios de transferencias o depósitos y consultar su revisión. No presenta cartera, cuotas, pagos confirmados, anticipos, recibos, estados de cuenta ni reportes internos.

## Activación administrativa

1. Un administrador crea previamente una cuenta con función **Cliente**.
2. Desde el expediente del cliente selecciona ese usuario y crea una invitación.
3. El sistema revoca invitaciones anteriores sin usar para ese usuario, almacena solo el hash del nuevo token y muestra el enlace una vez.
4. El usuario inicia sesión, abre el enlace y confirma la vinculación antes de siete días.
5. `cliente_usuarios` impide que una misma cuenta quede asociada a más de un cliente.

El portal se habilita explícitamente desde **Configuración → Negocio** después de revisar el aviso de privacidad y el procedimiento de invitaciones.

## Interfaces

- `GET /portal`: entrada del cliente; dirige a sus comprobantes o explica que necesita una invitación.
- `GET/POST /portal/invitacion/{token}`: revisión y consumo de la invitación personal.
- `GET /portal/reportes-pago`: comprobantes propios y estados de revisión.
- `GET /portal/reportes-pago/nuevo`: formulario de transferencia o depósito con evidencia obligatoria.
- `POST /portal/reportes-pago`: persiste el reporte con el cliente derivado de la sesión.
- `GET /portal/reportes-pago/{id}`: resultado e historial visible para el propietario.
- `GET /portal/reportes-pago/archivos/{id}`: descarga privada autorizada por recurso.

Registrar evidencia no crea un pago, no confirma dinero y no reduce deuda. La conciliación continúa siendo una acción interna autorizada.

## Persistencia pendiente de aplicación

La migración `2026-09-27-000003_CreateCustomerPortal` crea `cliente_invitaciones` y `cliente_usuarios`. No se ejecutó por la instrucción vigente de no realizar pruebas ni migraciones.
