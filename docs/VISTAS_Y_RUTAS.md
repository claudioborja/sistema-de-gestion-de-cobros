# Inventario de vistas y rutas

Este documento registra las pantallas del sistema, sus rutas HTTP y su estado de implementación. Las rutas marcadas como pendientes son contratos de trabajo propuestos: no existen todavía en la aplicación y pueden ajustarse antes de implementar el módulo correspondiente.

## Actualización de flujos — 11 de septiembre de 2026

Para navegación financiera prevalece [FLUJOS_POR_CLIENTE.md](FLUJOS_POR_CLIENTE.md) y SRS 11.1.1 sobre las referencias históricas de este inventario. Documentos, cartera y cobros son secciones del expediente, con rutas `/clientes/{id}/documentos`, `/clientes/{id}/cartera` y `/clientes/{id}/pagos`. Los accesos globales antiguos solicitan primero cliente. Las rutas de creación se abren con `/nuevo` dentro de cada sección.

Estas interfaces son **PARCIALES**: falta persistencia financiera. Los identificadores e importes de demostración se retiraron del flujo operativo; los detalles inexistentes responden 404 y las escrituras financieras no implementadas responden 409, sin confirmar operaciones ficticias. Reportes, caja y conciliación mantienen alcance transversal.

## Estados

- **TERMINADA:** la vista y sus rutas necesarias existen para el alcance indicado.
- **PARCIAL:** existe una primera versión, pero faltan capacidades, validación funcional o criterios de aceptación.
- **PENDIENTE:** todavía no existe la vista o la ruta.
- **SOPORTE:** componente compartido o respuesta del sistema sin URL navegable propia.

Una vista marcada como terminada no implica que todo su módulo esté terminado. Las operaciones mutables deben usar `POST`, protección CSRF, autorización en el servidor y validación de datos. Las rutas `GET` no deben modificar información.

## 1. Vistas y rutas existentes

Estas rutas se encuentran declaradas actualmente en `app/Config/Routes.php`.

| Módulo | Vista | Método y ruta | Acción o controlador | Acceso | Estado | Observación |
|---|---|---|---|---|---|---|
| Acceso | Iniciar sesión | `GET /login` | `LoginController::loginView` | Público | **TERMINADA** | Presenta el formulario de ingreso. |
| Acceso | Procesar ingreso | `POST /login` | `LoginController::loginAction` | Público con límite de intentos | **TERMINADA** | Acción sin vista propia; autentica mediante Shield. |
| Acceso | Cerrar sesión | `POST /logout` | `LoginController::logoutAction` | Usuario autenticado | **TERMINADA** | Acción sin vista propia; no se permite cierre mediante `GET`. |
| Inicio | Panel administrativo | `GET /` | `Dashboard::index` | `clientes.ver` | **PARCIAL** | Resume clientes, catálogo, puesta en marcha y actividad propia auditada. Se ampliará cuando existan cartera, cobros, verificaciones y renovaciones. |
| Clientes | Listado de clientes | `GET /clientes` | `Clients::index` | `clientes.ver` | **PARCIAL** | Incluye búsqueda, filtro de estado y paginación básica. Falta el listado avanzado y acceso al expediente. |
| Clientes | Nuevo cliente | `GET /clientes/nuevo` | `Clients::form` | `clientes.crear` | **PARCIAL** | Formulario existente. Faltan advertencias completas de posibles duplicados y condiciones de crédito. |
| Clientes | Guardar cliente nuevo | `POST /clientes` | `Clients::save` | `clientes.crear` | **PARCIAL** | Acción sin vista propia; valida, persiste y audita. |
| Clientes | Editar cliente | `GET /clientes/{id}` | `Clients::form/{id}` | `clientes.editar` | **PARCIAL** | Reutiliza el formulario y aplica control de versión. No sustituye el expediente del cliente. |
| Clientes | Guardar edición | `POST /clientes/{id}` | `Clients::save/{id}` | `clientes.editar` | **PARCIAL** | Acción sin vista propia; conserva control de concurrencia y auditoría. |
| Clientes | Activar o desactivar | `POST /clientes/{id}/estado` | `Clients::state/{id}` | `clientes.desactivar` | **PARCIAL** | Cambia el estado sin eliminar historial. |
| Catálogo | Listado de productos y servicios | `GET /catalogo` | `Catalog::index` | `catalogo.gestionar` | **PARCIAL** | Incluye búsqueda y paginación básica. |
| Catálogo | Nuevo ítem | `GET /catalogo/nuevo` | `Catalog::form` | `catalogo.gestionar` | **PARCIAL** | Registra producto o servicio, código y precio de referencia. |
| Catálogo | Guardar ítem nuevo | `POST /catalogo` | `Catalog::save` | `catalogo.gestionar` | **PARCIAL** | Acción sin vista propia; valida importe decimal y código único. |
| Catálogo | Editar ítem | `GET /catalogo/{id}` | `Catalog::form/{id}` | `catalogo.gestionar` | **PARCIAL** | Reutiliza el formulario y permite cambiar el estado. |
| Catálogo | Guardar edición | `POST /catalogo/{id}` | `Catalog::save/{id}` | `catalogo.gestionar` | **PARCIAL** | Acción sin vista propia; usa control de versión. |
| Seguridad | Acceso restringido | Respuesta `403`, sin ruta directa | `errors/access.php` | Usuario sin permiso | **TERMINADA** | Respuesta controlada cuando una ruta interna es denegada. |

### Componentes compartidos existentes

| Componente | Archivo | Uso | Estado |
|---|---|---|---|
| Plantilla interna | `app/Views/layouts/app.php` | Drawer adaptable, área de trabajo y ensamblaje de parciales | **SOPORTE** |
| Plantilla de acceso | `app/Views/layouts/auth.php` | Marco común para autenticación y respuestas de acceso | **SOPORTE** |
| Navegación lateral | `app/Views/partials/sidebar.php` | Menú autorizado, modalidad expandida y compacta | **SOPORTE** |
| Barra superior | `app/Views/partials/topbar.php` | Drawer móvil, contexto, usuario y cierre de sesión | **SOPORTE** |
| Encabezado de página | `app/Views/partials/page_header.php` | Contexto, título, descripción, línea contable y acción principal | **SOPORTE** |
| Migas de pan | `app/Views/partials/breadcrumbs.php` | Ubicación jerárquica desde el segundo nivel | **SOPORTE** |
| Mensajes | `app/Views/partials/feedback.php` | Resultados globales de las operaciones | **SOPORTE** |
| Estado vacío | `app/Views/partials/empty_state.php` | Causa, explicación y siguiente acción | **SOPORTE** |
| Confirmación | `app/Views/partials/confirmation_modal.php` | Confirmación de acciones con consecuencias relevantes | **SOPORTE** |
| Paginación | `app/Views/partials/pagination.php` | Navegación entre páginas de listados | **SOPORTE** |
| Errores HTTP | `app/Views/errors/` | Respuestas 400, 404, 500 y producción | **SOPORTE** |

Las vistas existentes comparten el tema `cobros`, el armazón adaptable y los componentes anteriores. El estado **PARCIAL** de un módulo continúa indicando capacidades funcionales pendientes, no una diferencia visual.

## 2. Acceso, usuarios y configuración

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Recuperar acceso | `GET /recuperar-acceso` | `POST /recuperar-acceso` | Público con límite de intentos | **PARCIAL** | Emite un enlace temporal y valida el correo antes de enviar recuperación. |
| Restablecer contraseña | `GET /restablecer-acceso/{token}` | `POST /restablecer-acceso/{token}` | Token válido | **PARCIAL** | Permite establecer una nueva contraseña y consume el token de recuperación. |
| Verificación de segundo factor | `GET /segundo-factor` | `POST /segundo-factor/verificar` | Administrador autenticado | **PENDIENTE** | Completar el segundo factor antes de abrir la sesión administrativa. |
| Usuarios | `GET /usuarios` | — | `usuarios.ver` | **TERMINADA** | Lista usuarios, grupo, estado y último acceso sin mostrar secretos. |
| Nuevo usuario | `GET /usuarios/nuevo` | `POST /usuarios` | `usuarios.crear` | **TERMINADA** | Crea una cuenta individual y asigna administrador, cajera o cliente. |
| Editar usuario y permisos | `GET /usuarios/{id}` | `POST /usuarios/{id}`, `POST /usuarios/{id}/estado`, `POST /usuarios/{id}/permisos` | `usuarios.editar` | **TERMINADA** | Edita identidad, función, estado y permisos individuales; impide desactivar al último administrador y audita cambios. |
| Perfil propio | `GET /mi-cuenta` | `POST /mi-cuenta`, `POST /mi-cuenta/contrasena` | Usuario autenticado | **TERMINADA** | Consulta y actualiza identidad y contraseña, verificando la contraseña actual antes del cambio. |
| Configuración del negocio | `GET /configuracion/negocio` | `POST /configuracion/negocio` | `configuracion.gestionar` | **PENDIENTE** | Empresa, moneda, zona horaria, numeración y parámetros operativos. |
| Medios y cuentas receptoras | `GET /configuracion/pagos` | `POST /configuracion/pagos` | `configuracion.gestionar` | **PENDIENTE** | Configurar medios de pago y cuentas sin exponer secretos. |

## 3. Clientes y catálogo

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Expediente del cliente | `GET /clientes/{id}/expediente` | — | `clientes.ver` y acceso al recurso | **PARCIAL** | Reúne identidad, contactos y auditoría; se ampliará al incorporar cartera, pagos y seguimiento. |
| Condiciones de crédito | `GET /clientes/{id}/credito` | `POST /clientes/{id}/credito` | `clientes.credito` | **PENDIENTE** | Versionar condiciones y advertencias o bloqueos de nuevo crédito. |
| Posibles duplicados | `GET /clientes/{id}/duplicados` | `POST /clientes/{id}/descartar-duplicado` | `clientes.editar` | **PARCIAL** | Advierte coincidencias por identificación o nombre sin fusionar; falta descartar falsos positivos. |
| Datos paginados de clientes | `GET /clientes/datos` | — | `clientes.ver` | **TERMINADA** | Entrega búsqueda, filtro de estado y paginación autorizada mediante JSON. |
| Detalle de ítem | `GET /catalogo/{id}/detalle` | — | `catalogo.gestionar` | **PARCIAL** | Consulta la ficha comercial sin confundirla con inventario; el historial de uso depende de documentos. |

## 4. Documentos, obligaciones y cartera

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Documentos | `GET /documentos` | — | `documentos.ver` | **PENDIENTE** | Buscar por cliente, número, fechas y estado. |
| Nuevo documento | `GET /documentos/nuevo` | `POST /documentos` | `documentos.crear` | **PENDIENTE** | Crear un borrador con cliente, ítems, importe, fecha y respaldo. |
| Detalle del documento | `GET /documentos/{id}` | `POST /documentos/{id}/confirmar` | `clientes.ver`; confirmar: `cartera.ver` | **TERMINADA** | Diferencia borrador y confirmado; confirma una sola vez y ofrece edición solo en borrador. |
| Editar borrador | `GET /documentos/{id}/editar` | `POST /documentos/{id}` | `cartera.ver` | **TERMINADA** | Modifica encabezado y líneas de forma atómica; valida número, importes y revisión concurrente, conserva cliente y adjuntos, y rechaza documentos confirmados. |
| Respaldos del documento | `GET /documentos/{id}/archivos` | `POST /documentos/{id}/archivos` | `archivos.ver`, `archivos.gestionar` | **TERMINADA** | Valida PDF, PNG y JPEG hasta 2 MB; almacena fuera de `public/` con nombre físico aleatorio, hash y auditoría. |
| Descarga privada | `GET /archivos/{id}/descargar` | — | `archivos.ver` | **TERMINADA** | Autoriza cada descarga, audita el acceso y no expone la ruta física. |
| Cartera general | `GET /cartera` | — | `cartera.ver` | **PENDIENTE** | Mostrar pendiente, vencido, por vencer y antigüedad con filtros. |
| Detalle de obligación | `GET /obligaciones/{id}` | — | `cartera.ver` | **TERMINADA** | Muestra documento, principal, saldo derivado, cronograma vigente, aplicaciones de pago y versiones históricas. |
| Reprogramar obligación | `GET /obligaciones/{id}/reprogramar` | `POST /obligaciones/{id}/reprogramaciones` | `cartera.reprogramar` | **TERMINADA** | Crea versiones insert-only de las fechas pendientes, conserva importes y aplicaciones, exige motivo e idempotencia y registra auditoría. |
| Estado de cuenta | `GET /clientes/{id}/estado-cuenta` | — | `cartera.ver` | **TERMINADA** | Libro mayor con documentos, cobros, reversiones, saldo acumulado, corte por fechas y filtro por tipo. La generación PDF queda fuera del alcance acordado. |

## 5. Pagos, aplicaciones y comprobantes bancarios

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Registrar cobro | `GET /clientes/{id}/pagos/nuevo` | `POST /clientes/{id}/pagos` | `pagos.crear` | **TERMINADA** | Confirma una transferencia y permite distribuirla ahora o conservar saldo disponible. |
| Resultado de operación | `GET /operaciones/resultado/{clave}` | — | Actor o usuario autorizado | **PENDIENTE** | Recuperar el resultado idempotente después de un corte de conexión. |
| Pagos | `GET /clientes/{id}/pagos` | — | `pagos.ver` | **PARCIAL** | Lista el historial del cliente; faltan filtros transversales por medio, fecha, responsable y origen. |
| Detalle de pago | `GET /pagos/{id}` | `POST /pagos/{id}/aplicaciones` | `pagos.ver` y permiso de aplicación | **TERMINADA** | Muestra aplicaciones vigentes o históricas y permite distribuir el disponible. |
| Revertir pago | `GET /pagos/{id}/revertir` | `POST /pagos/{id}/revertir` | `pagos.revertir` | **TERMINADA** | Conserva el pago y sus aplicaciones, exige motivo y anula su efecto financiero con trazabilidad idempotente. |
| Comprobantes bancarios | `GET /reportes-pago` | `GET/POST /reportes-pago/nuevo` | `pagos.ver`, `pagos.crear` | **IMPLEMENTADA SIN VALIDAR** | Registra cuenta, monto, fecha, referencia, cliente opcional y evidencia privada sin crear un pago; separa estados y conserva trazabilidad. |
| Revisar comprobante | `GET /reportes-pago/{id}` | `POST /reportes-pago/{id}/revisiones` | `pagos.bancarios.confirmar` | **IMPLEMENTADA SIN VALIDAR** | Mantiene revisiones insert-only; permite rechazar/reabrir, aprobar creando un cobro disponible o vincular una coincidencia exacta sin duplicarla. |
| Ingresos no identificados | `GET /ingresos-bancarios/no-identificados` | `POST /ingresos-bancarios/{id}/identificar` | `pagos.bancarios.confirmar`, `pagos.crear` | **IMPLEMENTADA SIN VALIDAR** | Asigna el titular económico sin confirmar dinero ni afectar cartera. |

## 6. Caja y recibos

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Mi caja | `GET /mi-caja` | `POST /mi-caja/abrir` | `caja.operar`, `caja.abrir` | **IMPLEMENTADA SIN VALIDAR** | Consulta el turno propio y permite abrirlo en una caja activa disponible; muestra fondo, movimientos, cobros y efectivo esperado. |
| Abrir caja | Integrada en `GET /mi-caja` | `POST /mi-caja/abrir` | `caja.abrir` | **IMPLEMENTADA SIN VALIDAR** | Reserva caja y usuario, registra fondo y evita aperturas simultáneas mediante transacción e índices únicos. |
| Movimiento de caja | `GET /turnos/{id}/movimientos/nuevo` | `POST /turnos/{id}/movimientos` | `caja.movimientos` | **IMPLEMENTADA SIN VALIDAR** | Registra aportes, retiros y gastos inmutables; bloquea el turno e impide salidas superiores al efectivo esperado. |
| Cerrar turno | `GET /turnos/{id}/cerrar` | `POST /turnos/{id}/cerrar` | `caja.cerrar` | **IMPLEMENTADA SIN VALIDAR** | Recalcula el esperado bajo bloqueo, registra el contado, exige explicación de diferencias y libera caja y usuario. |
| Entregar turno | `GET /turnos/{id}/entregar` | `POST /turnos/{id}/entregar`, `POST /turnos/{id}/recibir` | `caja.entregar` | **IMPLEMENTADA SIN VALIDAR** | El responsable separa fondo remanente e importe entregado; el destinatario confirma desde su bandeja y un turno solo admite una entrega. |
| Cajas físicas | `GET /cajas`, `GET /cajas/nueva`, `GET /cajas/{id}/editar` | `POST /cajas`, `POST /cajas/{id}` | `caja.gestionar` | **PARCIAL** | Registro, edición, activación/desactivación, auditoría y control de versión. La consulta de turnos, cierres y diferencias se incorporará después. |
| Recibo | `GET /recibos/{id}` | `GET /recibos/{id}/pdf` | `pagos.ver` | **TERMINADA** | Se emite con la transferencia confirmada, conserva una instantánea inmutable, descarga una copia PDF con el mismo número y señala reversiones posteriores. |

## 7. Ajustes, devoluciones e importación inicial

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Nuevo ajuste | `GET /ajustes/nuevo` | `POST /ajustes` | `ajustes.crear` | **PENDIENTE** | Registrar crédito, débito o retención con efecto y respaldo explícitos. |
| Revertir aplicación | `GET /aplicaciones/{id}/revertir` | `POST /aplicaciones/{id}/revertir` | `pagos.reaplicar` | **TERMINADA** | Conserva la aplicación original, libera el importe para redistribuir y registra motivo e identidad idempotente. |
| Registrar devolución | `GET /devoluciones/nueva` | `POST /devoluciones` | `devoluciones.crear` | **PENDIENTE** | Limitar la devolución al disponible y registrar la salida real. |
| Solicitudes excepcionales | `GET /autorizaciones-excepcionales` | `POST /autorizaciones-excepcionales` | Cajera o administrador según acción | **PENDIENTE** | Autorizar una única acción, cliente e importe. |
| Importaciones | `GET /importaciones` | — | `importaciones.ver` | **PENDIENTE** | Mostrar lotes, origen, estado, totales y errores. |
| Nueva importación | `GET /importaciones/nueva` | `POST /importaciones/prevalidar` | `importaciones.crear` | **PENDIENTE** | Cargar CSV/XLSX sin afectar cartera durante la prevalidación. |
| Vista previa del lote | `GET /importaciones/{id}/vista-previa` | `POST /importaciones/{id}/confirmar` | `importaciones.confirmar` | **PENDIENTE** | Presentar filas, errores y conciliación antes de confirmar. |
| Detalle de importación | `GET /importaciones/{id}` | `POST /importaciones/{id}/reanudar` | `importaciones.ver` y permiso de reanudación | **PENDIENTE** | Conservar identidad por fuente/fila y estado de ejecución parcial. |

## 8. Servicios recurrentes

Implementados bajo el nombre **Suscripciones**. Ver [reglas y cron](SUSCRIPCIONES.md).

| Vista | Ruta | Acciones | Permiso |
|---|---|---|---|
| Listado | `GET /suscripciones` | Buscar cliente o servicio; paginar | `contratos.ver` |
| Por cliente | `GET /clientes/{id}/suscripciones` | Consultar y crear desde expediente | `contratos.ver` |
| Nueva | `GET /clientes/{id}/suscripciones/nueva` | `POST /clientes/{id}/suscripciones/vista-previa`, `POST /clientes/{id}/suscripciones` | `contratos.gestionar` |
| Detalle y proyección | `GET /suscripciones/{id}` | Historial, saldos, períodos, condiciones | `contratos.ver` |
| Cambios | `POST /suscripciones/{id}/estado`, `POST /suscripciones/{id}/precio` | Pausa, reactivación, cancelación, tarifa futura | `contratos.gestionar` |
| Aceptación | `POST /suscripciones/{id}/aceptar` | Evidencia textual vinculada a período y versión | `contratos.gestionar` |
| Generación | `POST /suscripciones/{id}/generar` | Cargos elegibles hasta hoy, sin duplicados | `contratos.generar` |

## 9. Seguimiento y comunicaciones

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Agenda de cobranza | `GET /seguimiento/agenda` | — | `seguimiento.ver` | **PENDIENTE** | Reunir vencimientos, compromisos incumplidos y comprobantes pendientes. |
| Nueva gestión | `GET /clientes/{id}/gestiones/nueva` | `POST /clientes/{id}/gestiones` | `seguimiento.crear` | **PENDIENTE** | Registrar canal, resultado, observación y siguiente acción. |
| Compromiso de pago | `GET /clientes/{id}/compromisos/nuevo` | `POST /clientes/{id}/compromisos` | `seguimiento.crear` | **PENDIENTE** | No descontar deuda hasta recibir y aplicar un pago confirmado. |
| Incidencia o disputa | `GET /clientes/{id}/incidencias/nueva` | `POST /clientes/{id}/incidencias` | `incidencias.crear` | **PENDIENTE** | Suspender avisos relacionados sin alterar el saldo. |
| Plantillas de mensajes | `GET /configuracion/mensajes` | `POST /configuracion/mensajes` | `configuracion.gestionar` | **PENDIENTE** | Previsualizar con datos de prueba antes de activar. |
| Cola de correos | `GET /operacion/correos` | `POST /operacion/correos/{id}/reintentar` | `operacion.ver` | **PENDIENTE** | Mostrar intentos y errores sin afirmar entrega solo por aceptación SMTP. |
| Notificaciones internas | `GET /notificaciones` | `POST /notificaciones/{id}/leer` | Usuario autenticado | **PENDIENTE** | Mostrar únicamente notificaciones del usuario autorizado. |

## 10. Portal limitado del cliente

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Activar invitación | `GET /portal/invitacion/{token}` | `POST /portal/invitacion/{token}` | Invitación válida para el usuario autenticado | **IMPLEMENTADA SIN VALIDAR** | Vincula una cuenta cliente mediante token de un solo uso, hash persistido, destinatario fijo y vencimiento de siete días. |
| Mis comprobantes | `GET /portal/reportes-pago` | — | Cliente autenticado y recurso propio | **IMPLEMENTADA SIN VALIDAR** | Muestra únicamente comprobantes del cliente derivado de `cliente_usuarios`, con estado e historial de revisión. |
| Registrar comprobante | `GET /portal/reportes-pago/nuevo` | `POST /portal/reportes-pago` | Cliente autenticado y vinculado | **IMPLEMENTADA SIN VALIDAR** | Fija el cliente desde la sesión, exige evidencia privada y no confirma el ingreso ni modifica deuda. |
| Resultado del comprobante | `GET /portal/reportes-pago/{id}` | `GET /portal/reportes-pago/archivos/{id}` | Propietario del reporte | **IMPLEMENTADA SIN VALIDAR** | Limita detalle, revisiones y descarga de evidencia al cliente propietario; no expone cartera, pagos ni recibos. |

El portal no tendrá vistas de cartera, cuotas, estados de cuenta, recibos, anticipos ni reportes internos durante la primera etapa.

## 11. Reportes y operación

| Vista planificada | Método y ruta de vista | Acciones relacionadas | Acceso propuesto | Estado | Criterio principal |
|---|---|---|---|---|---|
| Panel gerencial | `GET /reportes` | — | `reportes.ver` | **PARCIAL** | Consolida clientes, catálogo y actividad, con acceso a cartera y pagos globales; aún no incorpora indicadores financieros propios. |
| Reporte de cartera | `GET /reportes/cartera` | `GET /reportes/cartera.csv`, `GET /reportes/cartera.xlsx` | `reportes.ver` | **IMPLEMENTADA SIN VALIDAR** | Filtra saldos abiertos por cliente, concepto, documento y vencimiento; pagina 25 registros y exporta el conjunto filtrado completo. No se ejecutaron pruebas por instrucción del usuario. |
| Reporte de pagos | `GET /reportes/pagos` | `GET /reportes/pagos.csv`, `GET /reportes/pagos.xlsx` | `reportes.ver` | **IMPLEMENTADA SIN VALIDAR** | Filtra cobros por fecha, estado, origen y medio; distingue operativo, histórico y apertura, conserva reversiones y exporta el conjunto filtrado completo. No se ejecutaron pruebas por instrucción del usuario. |
| Auditoría | `GET /operacion/auditoria` | — | `auditoria.ver` | **TERMINADA** | Filtra y pagina eventos por registro, acción o usuario sin exponer secretos. |
| Estado operativo | `GET /operacion/estado` | — | `operacion.ver` | **PARCIAL** | Comprueba runtime PHP, esquema y latencia de base, sesión/cookies y escritura privada. Valida configuración de correo y consume evidencia de tareas y respaldos cuando existe; no simula disponibilidad. |
| Contingencias | `GET /operacion/contingencias` | `POST /operacion/contingencias` | `operacion.contingencias` | **PENDIENTE** | Registrar referencia externa y exigir conciliación posterior. |

## 12. Orden recomendado de implementación

1. Completar usuarios, permisos individuales, recuperación de acceso y configuración.
2. Completar expediente de clientes, catálogo y archivos privados.
3. Implementar documentos, obligaciones, cartera y estados de cuenta.
4. Implementar pagos, aplicaciones, comprobantes bancarios, caja y recibos.
5. Incorporar ajustes, devoluciones e importación inicial.
6. Incorporar contratos recurrentes, seguimiento y comunicaciones.
7. Validar el portal limitado y completar las herramientas de operación pendientes.

## 13. Regla de actualización

Al implementar una vista se debe actualizar esta lista en el mismo cambio:

1. Confirmar que la ruta esté declarada explícitamente.
2. Registrar el controlador, servicio y permiso aplicable.
3. Mantener separadas la ruta de presentación y las acciones mutables.
4. Cambiar **PENDIENTE** a **PARCIAL** cuando exista una primera versión funcional.
5. Cambiar a **TERMINADA** únicamente después de validar autorización, persistencia, errores, estados vacíos, versión móvil y pruebas correspondientes.
