# Caja: entregas pequeñas

## Parte 1 — Registro de cajas físicas

Disponible en **Caja y conciliación → Cajas** (`/cajas`), para usuarios con permiso `caja.gestionar`. El administrador lo recibe mediante `caja.*`; las cajeras no lo reciben por defecto.

- Crear una caja con código único, nombre y estado.
- Editar los datos, desactivar y reactivar sin borrar el registro.
- Rechazar códigos duplicados y formularios cuya versión quedó desactualizada.
- Registrar altas y cambios en auditoría dentro de la misma transacción.

**Activa** indica que la caja está habilitada en el catálogo; no significa que tenga un turno abierto. Esta entrega no registra fondos, turnos, cobros ni movimientos de dinero.

Para revisar: crea `CAJA-01`, modifica su nombre, desactívala y vuelve a activarla. Prueba un código duplicado. Abre la misma edición en dos pestañas y guarda cambios en ambas: la segunda debe pedir revisar la versión actual.

Instalación: `php spark migrate --all` aplica `2026-09-18-000003_CreateCashRegisters`, que añade la tabla `cajas`. No carga cajas ficticias ni altera pagos existentes.

## Parte 2 — Mi caja y apertura de turno

La vista **Mi caja** (`/mi-caja`) ya consulta el turno real del usuario y permite abrirlo desde la misma pantalla.

- Solo ofrece cajas activas que no estén ocupadas.
- Impide que una caja o un usuario mantengan dos turnos abiertos al mismo tiempo mediante restricciones persistentes y control transaccional.
- Registra el fondo inicial, la operación idempotente y el evento de auditoría.
- Cuando existe un turno, muestra caja, fecha de apertura, fondo, cobros en efectivo, movimientos y efectivo esperado.
- Conserva espacio para el historial de cierres, que empezará a poblarse cuando se construya la vista de cierre.
- Una caja con turno abierto no puede desactivarse desde el catálogo.

La migración `2026-09-27-000002_CreateCashOperations` prepara `turnos_caja`, `movimientos_caja` y `entregas_turno`. Está creada pero no se ha aplicado ni validado por solicitud del usuario.

## Parte 3 — Movimientos justificados

Desde un turno abierto, **Registrar movimiento** abre `/turnos/{id}/movimientos/nuevo`.

- Registra aportes, retiros y gastos como hechos independientes; no edita el fondo inicial.
- Exige importe positivo y un concepto de 5 a 300 caracteres.
- Impide retiros o gastos superiores al efectivo esperado en el momento de confirmar.
- Bloquea el turno durante el cálculo para evitar que dos salidas consuman el mismo disponible.
- Conserva operación idempotente, usuario, fecha y auditoría dentro de la misma transacción.
- No permite operar turnos cerrados ni turnos abiertos por otro usuario.

Esta parte usa `movimientos_caja`, preparada por la migración anterior. Los cobros en efectivo se incorporarán como otra operación porque también deben crear `pagos`, aplicaciones y recibo.

## Parte 4 — Arqueo y cierre

La acción **Cerrar turno** abre `/turnos/{id}/cerrar` y presenta fondo, entradas, salidas y efectivo esperado calculados por el sistema.

- El usuario registra únicamente el efectivo físico contado; el esperado nunca llega desde el formulario.
- El cierre vuelve a bloquear el turno y recalcula los movimientos para evitar diferencias por operaciones concurrentes.
- Un faltante o sobrante exige una explicación de 5 a 500 caracteres.
- Conserva efectivo esperado, contado, diferencia firmada, motivo, operación idempotente, responsable y auditoría.
- El turno queda cerrado, deja de aceptar movimientos y libera la caja y al usuario para una nueva apertura.
- El cierre repetido con la misma clave recupera el resultado original sin cerrar dos veces.

El cierre usa las columnas ya preparadas en `turnos_caja`; no requiere una migración adicional. Sigue sin aplicarse ni validarse en runtime la migración `2026-09-27-000002_CreateCashOperations`.

## Parte 5 — Entrega y recepción

Cada cierre reciente ofrece **Entregar** en `/turnos/{id}/entregar`. La misma vista cambia según el estado del flujo.

- Solo el responsable del turno cerrado puede iniciar la entrega.
- Selecciona a otra persona activa con permiso `caja.entregar` y registra el fondo que permanece físicamente en caja.
- El sistema deriva el importe entregado como `efectivo contado - fondo remanente`; el formulario no permite declarar dos totales independientes.
- La restricción única sobre `turno_id` impide duplicar la entrega del mismo cierre.
- El destinatario encuentra la operación en **Recepciones pendientes** dentro de **Mi caja**, revisa ambos importes y confirma desde su propia sesión.
- Entrega y recepción conservan operaciones idempotentes separadas, responsables, fechas, observaciones y auditoría.
- El fondo remanente queda documentado en la entrega; no se copia automáticamente como fondo inicial de un turno posterior.

La tabla `entregas_turno` forma parte de la migración de caja aún no aplicada. Esta parte está implementada en código, pero no se ejecutó la migración ni se validó el flujo por la restricción vigente de no realizar pruebas.

## Parte 6 — Recibos internos

Disponible desde el detalle de cada transferencia confirmada, para usuarios con permiso `pagos.ver`.

- Emite un número único con el prefijo y número inicial configurados para el negocio.
- Conserva una instantánea inmutable del negocio, cliente, responsable, pago y aplicaciones existentes al confirmar.
- Permite consultar el recibo y descargar una copia PDF marcada como **COPIA**.
- Si el pago se revierte posteriormente, mantiene el contenido emitido y añade el estado, motivo y fecha de reversión a la consulta y al PDF.
- La generación del PDF ocurre después de la transacción financiera; un fallo de descarga no revierte ni repite el pago.
- Repetir una confirmación con la misma clave idempotente devuelve el mismo recibo y no consume otro número.

Rutas: `GET /recibos/{id}` y `GET /recibos/{id}/pdf`. La migración `2026-09-18-000004_CreateReceipts` incorpora `recibo_series`, `recibos` y `recibo_pagos`. Los cobros históricos anteriores a esta migración no reciben automáticamente una instantánea retroactiva.

## Próximas partes

3. Cobros en efectivo dentro del turno, separados de transferencias bancarias.

La parte 6 está terminada para el medio de pago disponible actualmente: transferencias bancarias. Cuando se incorporen efectivo y pagos mixtos, usarán la misma relación entre recibos y pagos.

Cada parte se entrega para revisión antes de iniciar la siguiente.
