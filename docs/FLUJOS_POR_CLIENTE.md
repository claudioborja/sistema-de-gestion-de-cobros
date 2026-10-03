# Flujos por cliente

Corrección de navegación y de selectores, 11 de septiembre de 2026. Fuente contractual: SRS 11.1.1 y 11.2.

El directorio abre un expediente real. Desde allí se entra a Documentos, Cartera, Cobros, Estado de cuenta y Crédito. Los nuevos documentos y cobros conservan el cliente de la ruta, visible y no editable. El servidor verifica la existencia del cliente y los permisos antes de mostrar la sección.

Los accesos históricos `/documentos`, `/cartera`, `/pagos` y sus formularios de creación muestran selección de cliente y redirigen a su expediente. Caja y conciliación siguen siendo transversales; reportes mantiene consultas consolidadas.

## Límite encontrado

El esquema versionado actual implementa clientes, identificaciones, contactos, catálogo y auditoría. No implementa documentos, obligaciones, pagos, aplicaciones ni turnos de caja. Las vistas financieras anteriores usaban importes e identificadores fijos y sus POST respondían éxito sin persistir.

Se retiran esos datos del recorrido operativo. Las pantallas indican la indisponibilidad; no se pueden confirmar operaciones. Los POST financieros antiguos responden 409 y los detalles sin registro persistido responden 404. Los POST contextuales rechazan con 422 un cliente de formulario diferente del de la ruta. Esto no reemplaza el desarrollo pendiente del dominio financiero ni declara esa funcionalidad terminada.

## Selectores

Tom Select usa un único control visual, búsqueda remota con mínimo de dos caracteres, mensajes en español, límite de resultados, cancelación de solicitudes superadas y tiempo de espera. La sesión vencida recibe 401 JSON. SweetAlert2 utiliza la distribución sin inyección de estilos y su CSS se compila localmente.

## Comprobaciones puntuales

En `http://cobros.test`, con la cuenta local administrativa:

- Buscar un cliente real y continuar abrió `/clientes/1/pagos/nuevo` con su identidad.
- Documentos, cartera, cobros, estado de cuenta y crédito del cliente respondieron 200 y conservaron el contexto.
- Un cliente inexistente y un pago de ejemplo sin persistencia respondieron 404.
- El expediente presentaba un error MySQL 1052 por `ORDER BY id` ambiguo; se corrigió a `a.id` y volvió a cargar.
- La zona horaria permitió buscar `Bogota` y seleccionarla con teclado, sin guardar configuración.
- La búsqueda sin coincidencias mostró un mensaje explícito. Buscador y expediente no desbordaron a 390 px de ancho.
- La compilación Vite y la revisión de sintaxis PHP terminaron correctamente. No se ejecutó la suite completa de pruebas.
