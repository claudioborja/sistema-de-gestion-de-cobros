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
- Documentos comerciales y archivos privados.
- Obligaciones, créditos, cuotas y estados de cuenta.
- Cobros en efectivo, transferencias, depósitos y pagos mixtos.
- Aplicación de pagos, anticipos, ajustes, reversiones y devoluciones.
- Apertura, arqueo, cierre y entrega de caja.
- Servicios recurrentes mensuales o anuales.
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

5. Si necesitas un administrador exclusivamente para desarrollo, ejecuta:

   ```bash
   php spark db:seed 'App\Database\Seeds\LocalAccess'
   ```

   La contraseña generada se guarda en `writable/local-access.json`, archivo excluido del repositorio.

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

## Seguridad y datos

- No incorpores `.env`, contraseñas, registros, respaldos ni datos reales al repositorio.
- El directorio público del servidor web debe apuntar exclusivamente a `public/`.
- Solo `writable/` debe necesitar permisos de escritura en producción.
- Las credenciales de desarrollo generadas localmente no deben reutilizarse en producción.
- La carga de información real y el despliegue en producción requieren completar las revisiones funcionales, de seguridad, respaldo y restauración.

## Estado de desarrollo

La base técnica y los primeros módulos administrativos están implementados de forma parcial. El siguiente objetivo es completar usuarios, permisos individuales, archivos privados e idempotencia antes de construir cartera, pagos y caja.

## Licencia

El proyecto conserva la licencia MIT incluida con la base de CodeIgniter. Consulta [LICENSE](LICENSE).
