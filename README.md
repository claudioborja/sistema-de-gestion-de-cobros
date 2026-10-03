# Sistema de gestión de cobros

Aplicación web para administrar clientes, productos y servicios, obligaciones, pagos y procesos de caja de un negocio pequeño. El sistema está orientado a mantener saldos verificables, trazabilidad de las operaciones y permisos diferenciados para administración, caja y acceso limitado de clientes.

> El proyecto se encuentra en desarrollo. Actualmente están disponibles la estructura base, la autenticación y los módulos iniciales de clientes y catálogo. Los módulos financieros todavía no deben utilizarse con operaciones reales.

## Funcionalidades disponibles

- Autenticación con CodeIgniter Shield.
- Roles iniciales de administrador, cajera y cliente.
- Autorización por permisos en las rutas internas.
- Panel principal con indicadores básicos de clientes.
- Registro, edición, búsqueda y activación o desactivación de clientes.
- Identificaciones y medios de contacto asociados a cada cliente.
- Catálogo básico de productos y servicios.
- Validación de importes con representación decimal exacta.
- Auditoría inicial de cambios.
- Protección CSRF, escape de salida y cabeceras de seguridad.
- Interfaz adaptable construida con Tailwind CSS y daisyUI.

## Funcionalidades planificadas

- Administración completa de usuarios y permisos individuales.
- Documentos comerciales con archivos privados y descargas autorizadas.
- Obligaciones, créditos, cuotas y estados de cuenta.
- Cobros en efectivo, transferencias, depósitos y pagos mixtos.
- Aplicación de pagos, anticipos, ajustes, reversiones y devoluciones.
- Apertura, arqueo, cierre y entrega de caja.
- Importación y conciliación de saldos iniciales.
- Seguimiento de cobranza, compromisos y notificaciones.
- Portal limitado para que los clientes registren comprobantes propios.
- Reportes, exportaciones y documentos internos en PDF.

## Tecnologías

- PHP 8.2 o superior.
- CodeIgniter 4.
- CodeIgniter Shield.
- MySQL 8 con InnoDB y controlador MySQLi.
- Vite, Tailwind CSS, daisyUI y Lucide.
- PHPUnit para pruebas automatizadas.

## Requisitos

- PHP con las extensiones `intl`, `mbstring`, `mysqli`, `json` y `curl`.
- Composer 2.
- MySQL 8.
- Node.js y npm únicamente para compilar los recursos del frontend.

## Instalación local

1. Clona el repositorio e instala las dependencias de PHP:

   ```bash
   composer install
   ```

2. Crea el archivo de configuración local:

   ```bash
   cp .env.example .env
   ```

3. Crea las bases de datos de desarrollo y pruebas, y configura en `.env` sus nombres, usuarios y contraseñas. El archivo `.env` no debe incorporarse al control de versiones.

4. Aplica todas las migraciones, incluidas las de Shield:

   ```bash
   php spark migrate --all
   ```

5. Para crear o restablecer las cuentas de demostración locales, ejecuta:

   ```bash
   php spark db:seed 'App\Database\Seeds\LocalAccess'
   ```

   Esto solo funciona en `development` con la base `cobros_dev`. Las cuentas son:

   | Usuario / correo | Rol | Clave local |
   | --- | --- | --- |
   | `admin` / `admin@cobros.test` | Administrador | `Prueba2026!` |
   | `cajera` / `cajera@cobros.test` | Cajera | `Prueba2026!` |
   | `cliente` / `cliente@cobros.test` | Cliente | `Prueba2026!` |

   El seeder restablece esas claves en cada ejecución y guarda el detalle en `writable/local-access.json`, archivo excluido del repositorio. Nunca debe ejecutarse en producción.

   Para poblar el entorno local con clientes, catálogo y movimientos financieros ficticios, ejecuta después:

   ```bash
   php spark db:seed 'App\Database\Seeds\DemoData'
   ```

   Este conjunto solo se admite en `development` sobre `cobros_dev`. Sus referencias usan el prefijo `DEMO-` y volver a ejecutar el comando no crea duplicados.

   Para agregar suscripciones recurrentes de demostracion, incluido un servicio mensual de Moodle con cargos generados, ejecuta:

   ```bash
   php spark db:seed 'App\Database\Seeds\DemoSubscriptions'
   ```

   Este seeder tambien es idempotente y usa los clientes y servicios creados por `DemoData`.

6. Instala y compila los recursos del frontend:

   ```bash
   npm ci
   npm run build
   ```

7. Inicia el servidor local:

   ```bash
   php spark serve
   ```

La aplicación quedará disponible normalmente en `http://localhost:8080`.

## Estructura principal

```text
app/Controllers/           Controladores HTTP
app/Domain/                Validaciones y reglas de dominio
app/Services/              Casos de uso y operaciones transaccionales
app/Database/Migrations/   Esquema versionado de la base de datos
app/Database/Seeds/        Datos controlados para desarrollo
app/Views/                 Vistas renderizadas en el servidor
resources/                 Fuentes CSS y JavaScript
public/                    Punto de entrada y recursos públicos compilados
tests/                     Pruebas unitarias y de integración
writable/                  Caché, sesiones, registros y archivos privados
```

## Documentación

- [Inventario de vistas y rutas](docs/VISTAS_Y_RUTAS.md): pantallas existentes y planificadas, permisos, acciones y estado de implementación.
- [Arquitectura visual](docs/ARQUITECTURA_VISUAL.md): esquema común para listados, formularios, detalles y operaciones financieras.
- [Sistema visual maestro](design-system/sistema-de-cobros/MASTER.md): tokens, geometría, componentes y reglas de mantenimiento.

## Seguridad y datos

- No incorpores `.env`, contraseñas, registros, respaldos ni datos reales al repositorio.
- El directorio público del servidor web debe apuntar exclusivamente a `public/`.
- Solo `writable/` debe necesitar permisos de escritura en producción.
- Las credenciales de desarrollo generadas localmente no deben reutilizarse en producción.
- La carga de información real y el despliegue en producción requieren completar las revisiones funcionales, de seguridad, respaldo y restauración.

## Diagnóstico operativo

La ruta protegida `/operacion/estado` ejecuta comprobaciones independientes del runtime PHP, la base de datos, la sesión y el almacenamiento privado. La configuración de correo se informa sin realizar envíos durante cada consulta.

Las tareas programadas y los respaldos pueden publicar su última ejecución correcta en `writable/health/scheduler-heartbeat.json` y `writable/health/backup-heartbeat.json`:

```json
{
  "status": "success",
  "completed_at": "2026-09-14T10:45:00-05:00"
}
```

Si no existe evidencia, la pantalla muestra **No supervisado**; nunca convierte la ausencia de información en un estado operativo.

## Estado de desarrollo

Las cuentas receptoras se administran en **Administración → Cuentas bancarias** (`/configuracion/pagos`), con alta, edición y cambio de estado. Solo las activas aparecen al registrar transferencias. La configuración del negocio y las plantillas se guardan en base de datos. Consulta los hallazgos corregidos y pendientes en [revisión del flujo de cobros](docs/REVISION_FLUJO_COBROS.md).

La base técnica, los módulos administrativos principales, documentos, cartera, cobros, recibos internos con copia PDF, estados de cuenta y archivos privados ya tienen flujos operativos. Continúan pendientes conciliación bancaria, operación de turnos de caja, reportes globales y los módulos posteriores descritos en el inventario de vistas.

## Licencia

El proyecto conserva la licencia MIT incluida con la base de CodeIgniter. Consulta [LICENSE](LICENSE).

## Venta a crédito por meses

En **Clientes → Crédito → Nueva venta a crédito** (o **Documentos → Nuevo documento**), registra los ítems y guarda el borrador. En el detalle, indica el plazo de 1 a 120 meses y la fecha de la primera cuota; pulsa **Revisar plan de cuotas** y confirma el calendario mostrado. Se divide el total sin intereses y los centavos restantes se distribuyen en las últimas cuotas. Los meses cortos usan su último día y luego recuperan el día original.

La vista previa no genera deuda. La confirmación guarda todas las cuotas de forma atómica, evita duplicados por reenvío y refleja el total en cartera. Usa **Registrar cobro** para aplicar cada pago a las cuotas. No se registra un pago automáticamente al confirmar la venta.

## Suscripciones recurrentes

Disponible en **Suscripciones** y en el expediente de cada cliente: planes mensuales/anuales, vista previa, cargos por período, aceptación, pausa, cancelación y cambios futuros de precio. Los pagos usan la cartera existente.

Generación programable: `php spark suscripciones:generar ID_USUARIO`. Consulta [operación y configuración de cron](docs/SUSCRIPCIONES.md).

## Editar un documento en borrador

En el detalle del documento, pulsa **Editar borrador** para corregir tipo, número, fecha, concepto e ítems. El importe de cada línea es su total; al guardar se recalcula el total del documento. El cliente y los archivos adjuntos se conservan. Solo pueden editarse documentos sin confirmar, con permiso `cartera.ver`. Si otra persona modifica el borrador mientras está abierto, se rechaza el guardado para evitar sobrescribir sus cambios; vuelve a abrir la edición y revisa los datos actuales.

## Caja: primera entrega

En **Caja y conciliación → Cajas**, el administrador puede registrar cajas físicas con código único, editar su nombre y activarlas o desactivarlas. Esta primera entrega administra las cajas; apertura, cobros en efectivo y cierre se incorporarán por etapas. Consulta [alcance y pasos de revisión](docs/CAJA.md).
