# Revisión del flujo de ventas y cobros — 18 de septiembre de 2026

Alcance: configuración bancaria, venta a crédito, selección de cuentas y aplicación de cobros; inspección de las rutas de caja, conciliación, reportes y configuración. No equivale a una auditoría exhaustiva de seguridad ni a validar todos los módulos del sistema.

## Corregido

| Hallazgo | Corrección |
| --- | --- |
| No había edición de documentos en borrador. | Desde el detalle se pueden editar encabezado y líneas antes de confirmar; el guardado conserva cliente y adjuntos, recalcula el total y comprueba cambios concurrentes. Los documentos confirmados no admiten esta edición. |
| El formulario de configuración bancaria guardaba datos en la sesión, mientras los cobros consultaban otra fuente (`cuentas_bancarias`). | La pantalla `/configuracion/pagos` administra directamente las cuentas que usa `PaymentService`. |
| No había administración de varias cuentas. | Alta, edición, desactivación y reactivación; banco, número, titular, alias y tipo. |
| Faltaba un acceso claro para configurar cuentas desde el cobro. | Acceso en Administración, tarjeta de Configuración y enlace desde el formulario de cobro para administradores. |
| Las configuraciones de negocio y plantillas se perdían al terminar la sesión. | Persistencia en `configuracion_sistema`, validación y auditoría transaccional. |
| La pantalla ofrecía medios, comisión y liquidación que el flujo de transferencias no aplicaba. | Se retiraron esos controles; la pantalla se centra en cuentas receptoras. |
| Cambiar el número de una cuenta usada alteraría la identificación de cobros históricos. | Se bloquean cambios de banco y número si existen pagos asociados; se permite desactivar sin borrar. |

Las cuentas existentes se conservan. Sus campos nuevos de titular y tipo deben completarse cuando corresponda; no se inventaron titulares. Los valores que solo existían en sesiones antiguas no se importan automáticamente como configuración global: deben revisarse y guardarse de nuevo.

## Pendientes confirmados

| Prioridad | Hallazgo e impacto | Evidencia |
| --- | --- | --- |
| Media | Caja y registro de efectivo aún no están implementados; el cobro operativo es por transferencia. | `Finance::cash()` y `PaymentService`. |
| Media | Los documentos capturan descripciones e importes manualmente; la vista de líneas no permite seleccionar ítems del catálogo. | `finance/client_documents.php`; el servicio admite `item_id`, pero la vista no lo envía. |
| Media | El importe de cada línea es su total, no un precio unitario multiplicado por cantidad. Puede confundirse al cargar varios artículos. | `FinancialDocumentService::validateDraft()` suma `amount` directamente. |
| Media | Numeración, gracia y plantillas se guardan, pero guardar ajustes no implementa numeración automática, recálculo de vencimientos ni envío de mensajes. | Consumidores de `ConfigurationService` y métodos actuales de creación de documentos. |

## Validación

Pruebas de alta y selección bancaria, rechazo de duplicados, permisos, edición, desactivación/reactivación, conservación de pagos, rechazo de nuevos pagos a cuentas inactivas y persistencia fuera de sesión. Revisión de pantallas en navegador y compilación de recursos.

## Instalación

Ejecutar `php spark migrate --all` antes de publicar estas vistas. La migración `2026-09-18-000002_PersistConfiguration` añade `titular` y `tipo` sin sustituir las cuentas, y crea `configuracion_sistema`. El rollback elimina estos campos y la configuración guardada: requiere respaldo previo y no debe usarse para resolver errores rutinarios.
