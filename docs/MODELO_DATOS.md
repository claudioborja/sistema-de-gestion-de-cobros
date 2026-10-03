# Modelo físico del núcleo financiero

Estado: **fundamento implementado; integración HTTP y concurrencia multiproceso pendientes**.

Este documento describe la primera migración evolutiva del incremento I2. El SRS sigue siendo la fuente normativa. No habilita operaciones reales por sí solo.

## Alcance de la migración

La migración `2026-09-13-000002_CreateFinancialCore` incorpora:

- `operaciones`: identidad idempotente, actor, acción, hash canónico y resultado recuperable.
- `obligaciones`: deuda raíz por cliente, origen e importe base; no almacena saldo.
- `documentos_obligacion`: identidad documental normalizada, uno a uno con la obligación.
- `obligacion_detalles`: conceptos e importes pactados, independientes de cambios posteriores del catálogo.
- `cuotas` y `cuota_versiones`: identidad estable y versiones insert-only del cronograma.
- `cuentas_bancarias`, `pagos` y `pagos_bancarios`: recepción confirmada y modalidad bancaria tipada.
- `reportes_pago`, `reporte_archivos` y `reporte_revisiones`: evidencia bancaria previa al cobro, identificación del titular e historial insert-only de conciliación.
- `aplicaciones_pago`: distribución de un pago entre cuotas.
- `aplicacion_reversiones` y `pago_reversiones`: espacio tipado para correcciones futuras sin borrar hechos.
- `archivos_documento`: metadatos, integridad y ubicación privada de los respaldos vinculados a documentos.
- `recibo_series`, `recibos` y `recibo_pagos`: numeración bloqueada, instantánea emitida y relación inmutable con los pagos.
- `cliente_invitaciones` y `cliente_usuarios`: invitaciones dirigidas de un solo uso y vínculo exclusivo entre una cuenta externa y un cliente.

Todas las tablas usan InnoDB, `utf8mb4`, claves foráneas con `ON DELETE RESTRICT` e importes `DECIMAL(18,2)`. Las claves idempotentes y hashes usan comparación binaria ASCII.

## Dependencias e invariantes

```text
clientes
├── obligaciones ── documentos_obligacion
│   ├── archivos_documento
│   ├── obligacion_detalles
│   └── cuotas ── cuota_versiones
└── pagos ── pagos_bancarios
            └── aplicaciones_pago ── cuotas

operaciones identifica cada creación, confirmación, pago o corrección.
```

- Un borrador conserva el importe y los conceptos, pero no tiene cuotas ni afecta la cartera.
- Confirmar crea exactamente un cronograma cuya suma coincide con `importe_base`.
- El saldo se deriva de la versión vigente de cada cuota menos aplicaciones vigentes.
- Un pago puede quedar total o parcialmente disponible; no existe un saldo editable paralelo.
- Una aplicación solo puede unir un pago y una cuota del mismo cliente.
- Los hechos confirmados no usan borrado en cascada; una corrección se representará mediante registros relacionados.
- Los archivos conservan nombre original solo para presentación; el almacenamiento usa nombres aleatorios fuera de `public/` y valida el MIME detectado.
- La misma clave idempotente, actor, acción y contenido recupera el resultado guardado. Reutilizarla con contenido diferente produce conflicto.

## Servicios iniciales

### `FinancialDocumentService`

- `createDraft(...)`: valida cliente, documento y líneas; calcula el total con centavos enteros y persiste el borrador en una transacción.
- `confirm(...)`: bloquea la obligación, crea cuotas/versiones y congela la confirmación.
- `balance(...)`: deriva el saldo vigente sin leer una columna de saldo mutable.

### `PaymentService`

- `confirmBankTransfer(...)`: valida permiso, cliente, cuenta, referencia y aplicaciones; bloquea recursos en orden estable y confirma pago/aplicaciones en una transacción.
- Conserva el disponible no aplicado como diferencia derivada entre pago y aplicaciones.
- `applyAvailable(...)`: distribuye posteriormente el saldo disponible sin duplicar efectos ante reintentos.
- `reversePayment(...)`: registra una corrección administrativa con motivo, conserva los hechos originales y restituye el saldo aplicado a cartera.
- `reverseApplication(...)`: anula solo una distribución, aumenta nuevamente el disponible del pago y conserva su motivo auditado.
- `confirmBankTransfer(...)` emite además un recibo interno dentro de la misma transacción; la copia PDF se genera fuera de ella.
- No implementa todavía cobros en efectivo, vinculación del pago con turnos de caja ni devoluciones.

### `ReceiptService`

- `issueForPayment(...)`: asigna el siguiente número de la serie bajo bloqueo y conserva negocio, cliente, responsable, pago y aplicaciones como instantánea JSON.
- `find(...)`: devuelve la instantánea histórica y superpone el estado vigente de una reversión posterior.
- `renderPdf(...)`: genera una copia marcada con el mismo número, sin escribir ni revertir datos financieros.

### `CashSessionService`

- `dashboard(...)`: consulta el turno abierto del usuario, deriva entradas, salidas y efectivo esperado y presenta sus últimos cierres.
- `open(...)`: reserva caja y usuario, registra el fondo inicial y conserva resultado idempotente y auditoría dentro de la transacción.
- `movementContext(...)`: expone únicamente el turno abierto propio y el efectivo disponible antes de registrar una entrada o salida.
- `addMovement(...)`: bloquea el turno, recalcula el efectivo esperado y registra aportes, retiros o gastos idempotentes; una salida nunca puede superar el disponible.
- `closingContext(...)`: prepara el arqueo con fondo, entradas, salidas y efectivo esperado derivados del turno abierto propio.
- `close(...)`: bloquea y recalcula el turno, compara el conteo físico, exige explicación de diferencias y libera las claves activas de caja y usuario.
- `handoffContext(...)`: limita la entrega al responsable, la recepción al destinatario y expone los valores inmutables del cierre.
- `deliver(...)`: deriva el efectivo entregado desde el contado y el fondo remanente y crea una única entrega pendiente e idempotente.
- `receive(...)`: permite que solo el destinatario confirme una entrega pendiente y conserva su propia operación y auditoría.
- `turnos_caja` conserva apertura, fondo y futuros datos de cierre; sus columnas activas con índices únicos impiden más de un turno abierto por caja o usuario.
- `movimientos_caja` registra importes positivos tipificados como aportes, cobros, retiros, gastos o devoluciones, con operación y responsable obligatorios.
- `entregas_turno` separa importe entregado y fondo remanente, impide más de una entrega por turno y exige confirmación del destinatario para pasar a recibida.

### `CustomerPortalService`

- Genera invitaciones administrativas dirigidas a un usuario cliente concreto; solo conserva el hash del token y revoca invitaciones anteriores sin usar.
- Activa una invitación vigente dentro de una operación idempotente y persiste un único vínculo por usuario en `cliente_usuarios`.
- Deriva el cliente desde la sesión para listar, crear y consultar comprobantes; ningún formulario del portal acepta `cliente_id`.
- Autoriza cada detalle y descarga mediante la relación persistida con el reporte, sin conceder acceso a cartera, pagos confirmados, recibos ni estados de cuenta.

### `DocumentAttachmentService`

- Valida PDF, PNG y JPEG hasta 2 MB, guarda el contenido bajo `writable/uploads/documentos/{id}` y registra hash SHA-256.
- Lista metadatos sin revelar rutas físicas y autoriza cada descarga mediante `archivos.ver`.
- Audita cargas y descargas; los archivos no se sirven directamente desde el servidor web.

### Dominio

- `Money` convierte cadenas decimales a centavos enteros y viceversa.
- `InstallmentSchedule` distribuye cuotas iguales sin punto flotante; el residuo se asigna a las últimas cuotas.
- `OperationService` centraliza reserva, comparación y resultado idempotente.

## Consultas e índices previstos

Los índices actuales responden a los primeros patrones confirmados:

- obligaciones por cliente y fecha;
- pagos por cliente;
- cuotas por obligación;
- versiones por vencimiento;
- aplicaciones por pago y por cuota;
- pagos bancarios por cuenta y fecha;
- operaciones por actor y fecha.

Antes de ampliar listados se medirán sus consultas con `EXPLAIN`. No se añadieron índices de cobertura especulativos.

## Límites del incremento

- Los controladores financieros continúan respondiendo indisponibilidad; los servicios aún no están conectados a formularios HTTP.
- Falta probar idempotencia y sobreaplicación con dos conexiones concurrentes reales.
- La apertura, los movimientos manuales, el cierre y la entrega/recepción ya están modelados; faltan cobros en efectivo, ajustes, devoluciones y apertura histórica.
- La migración descendente es solo para desarrollo vacío. En entornos con datos se emplearán migraciones evolutivas; no se promete que revertir código revierta hechos financieros.

## Verificación reproducible

```bash
php spark migrate --all
vendor/bin/phpunit --no-coverage tests/integration/FinancialCoreTest.php
```

Casos cubiertos: ausencia de saldo editable, división exacta de 100.00 en tres cuotas, borrador sin deuda, confirmación repetida, anticipo, aplicación entre clientes e idempotencia conflictiva.

## Suscripciones recurrentes

La migración `2026-09-18-000001_CreateSubscriptions` incorpora `contratos`, `contrato_condiciones`, `contrato_eventos`, `periodos_contrato` y `renovacion_decisiones`. Los períodos apuntan a las obligaciones existentes con origen `PERIODO`; una clave única por contrato/inicio evita duplicados. Las condiciones conservan versiones y las aceptaciones se vinculan a la versión aprobada. Ver [operación, reglas y cron](SUSCRIPCIONES.md).
