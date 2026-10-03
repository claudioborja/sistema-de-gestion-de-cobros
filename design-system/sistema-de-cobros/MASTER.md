# Sistema visual maestro

Este archivo es la fuente de verdad para todas las interfaces del Sistema de Cobros. Una vista nueva debe respetar estas reglas o documentar expresamente una excepción en `pages/`.

## 1. Dirección visual

**Concepto:** libro mayor operativo, con una lectura azul tecnológica.

La interfaz debe transmitir precisión, control y calma. La personalidad proviene de una cuadrícula rigurosa, números tabulares y una línea vertical azul cobalto que recuerda el margen de un libro contable. No se utilizan gradientes, efectos decorativos, tarjetas innecesarias ni animaciones de entrada.

**Firma visual:** la línea contable. Todo encabezado de página comienza con una línea vertical de `3px` en color primario. El encabezado muestra título y acción; no repite la sección, el rol ni explicaciones evidentes.

## 2. Principios invariables

1. Todas las páginas comparten el mismo armazón, ancho, márgenes y orden vertical.
2. Una cuadrícula base de `8px` gobierna espacios, alturas y separaciones.
3. Cada grupo de acciones tiene una sola acción primaria.
4. Los estados se expresan con texto e icono; nunca dependen únicamente del color.
5. Los importes, fechas comparables, identificadores y totales usan cifras tabulares.
6. Las listas usan tablas en escritorio y registros apilados en móvil cuando una tabla pierda legibilidad.
7. Los formularios mantienen etiquetas visibles, ayuda breve y errores junto al campo.
8. Las acciones financieras muestran contexto y resultado antes de confirmar.
9. Ningún componente define colores, radios o sombras fuera de los tokens compartidos.
10. Las vistas no duplican el armazón: extienden la plantilla y ensamblan parciales.
11. La primera pantalla útil debe quedar visible sin desplazamiento siempre que la cantidad real de datos lo permita; el espacio vertical se reserva para controles y resultados, no para decoración.

## 3. Tema daisyUI

Nombre del tema: `cobros`.

```css
@plugin "daisyui" {
  themes: false;
  logs: false;
}

@plugin "daisyui/theme" {
  name: "cobros";
  default: true;
  prefersdark: false;
  color-scheme: light;
  --color-base-100: #ffffff;
  --color-base-200: #f5f8fe;
  --color-base-300: #d9e5f7;
  --color-base-content: #102a56;
  --color-primary: #1e40af;
  --color-primary-content: #ffffff;
  --color-secondary: #163b78;
  --color-secondary-content: #ffffff;
  --color-accent: #0284c7;
  --color-accent-content: #ffffff;
  --color-neutral: #102a56;
  --color-neutral-content: #eff6ff;
  --color-info: #0369a1;
  --color-info-content: #ffffff;
  --color-success: #15803d;
  --color-success-content: #ffffff;
  --color-warning: #b45309;
  --color-warning-content: #ffffff;
  --color-error: #b42318;
  --color-error-content: #ffffff;
  --radius-selector: 0.5rem;
  --radius-field: 0.5rem;
  --radius-box: 0.75rem;
  --size-selector: 0.25rem;
  --size-field: 0.25rem;
  --border: 1px;
  --depth: 0;
  --noise: 0;
}
```

### Uso del color

- `base-*`: estructura, fondos y separadores; ocupa la mayor parte de la pantalla.
- `primary`: azul cobalto para acción principal, foco y línea contable; una aparición dominante por bloque.
- `secondary`: azul marino para navegación y acciones de apoyo.
- `accent`: azul cian para información o atención no crítica; no se usa como decoración.
- `info`, `success`, `warning`, `error`: estados semánticos acompañados siempre por texto.
- No se utilizan colores fijos de Tailwind en los componentes de negocio.

## 4. Tipografía

- **Interfaz y encabezados:** IBM Plex Sans, pesos `400`, `500` y `600`.
- **Importes, referencias y datos alineados:** IBM Plex Mono, pesos `400` y `500`.
- **Fallback:** `system-ui`, `sans-serif` y `ui-monospace`.
- Las fuentes se alojarán con los recursos de la aplicación; producción no dependerá de una CDN.

| Rol | Tamaño | Interlineado | Peso |
|---|---:|---:|---:|
| Título de página | `28px` | `36px` | `600` |
| Título de sección | `20px` | `28px` | `600` |
| Título menor | `16px` | `24px` | `600` |
| Texto | `16px` | `24px` | `400` |
| Texto auxiliar | `14px` | `20px` | `400` |
| Etiqueta compacta | `12px` | `16px` | `500` |
| Importe principal | `28px` | `36px` | `500`, monoespaciado |

No se usa texto menor de `12px`. Los textos funcionales se escriben en español, voz activa y estilo oración.

## 5. Geometría y espaciado

### Escala

| Token | Valor | Uso |
|---|---:|---|
| `--space-1` | `4px` | Ajuste mínimo |
| `--space-2` | `8px` | Icono y texto |
| `--space-3` | `12px` | Controles compactos |
| `--space-4` | `16px` | Separación estándar |
| `--space-5` | `24px` | Interior de superficies |
| `--space-6` | `32px` | Separación entre secciones |
| `--space-7` | `48px` | Cambio de bloque principal |

### Cuadrícula

- Escritorio desde `1280px`: 12 columnas, separación de `24px`.
- Tableta entre `768px` y `1279px`: 8 columnas, separación de `20px`.
- Móvil hasta `767px`: 4 columnas, separación de `16px`.
- Ancho máximo de contenido: `1440px`.
- Márgenes laterales: `32px` escritorio, `24px` tableta y `16px` móvil.
- Barra lateral: `240px` expandida y `72px` compacta.
- Barra superior: `64px`.
- Objetivo táctil mínimo: `44px × 44px`.

## 6. Armazón universal

Toda vista interna sigue este orden:

```text
Aplicación
├── Navegación lateral / drawer
└── Área de trabajo
    ├── Barra superior
    └── Contenedor de página
        ├── Migas de pan, si existe más de un nivel
        ├── Encabezado compacto con línea contable
        │   ├── Título único
        │   └── Acción primaria
        ├── Mensajes y errores
        ├── Resumen opcional de 2 a 4 indicadores
        ├── Herramientas locales: búsqueda, filtros y orden
        └── Contenido principal
```

La descripción de página se omite por defecto. Solo aparece cuando explica una restricción, consecuencia o decisión que no puede deducirse del título y del contenido. La ausencia de un bloque opcional no cambia los márgenes de los bloques restantes.

## 7. Familias de página

### Listado

```text
[Encabezado.....................................][Acción primaria]
[Filtros y búsqueda.............................................]
[Tabla / registros..............................................]
[Resumen del conjunto.............................][Paginación]
```

- Filtros y tabla comparten los mismos bordes laterales.
- Los números se alinean a la derecha y las acciones en la última columna.
- Estado vacío, carga y error ocupan el mismo espacio del cuerpo de resultados.

### Formulario

```text
[Encabezado......................................................]
[Formulario: 8 columnas.............][Resumen: 4 columnas.......]
[Sección A.......................................................]
[Sección B.......................................................]
[Cancelar.............................][Guardar cambios...........]
```

- En móvil, resumen, formulario y acciones se apilan en ese orden.
- Las acciones permanecen al final del formulario; no se fijan sobre el contenido.
- Formularios cortos pueden usar `dialog`; contratos, caja y operaciones financieras usan página completa.

### Detalle

```text
[Encabezado + estado.............................][Acciones.....]
[Resumen de identidad............................................]
[Navegación local................................................]
[Contenido principal: 8 columnas][Contexto: 4 columnas.........]
[Historial cronológico...........................................]
```

- El estado aparece junto al título y se repite en texto dentro del resumen.
- El historial nunca sustituye el estado actual.

### Operación financiera guiada

```text
[1 Contexto]──[2 Distribución]──[3 Revisión]──[4 Resultado]
[Contenido del paso..............................................]
[Volver............................................][Continuar...]
```

- Cada paso tiene una sola decisión principal.
- Cliente, moneda, importe total e idempotencia permanecen visibles.
- El resultado final tiene identidad propia y puede recuperarse mediante su clave.

## 8. Componentes canónicos

| Necesidad | Componente base | Regla |
|---|---|---|
| Navegación adaptable | `drawer`, `menu`, `navbar` | Un único árbol de navegación para móvil y escritorio. |
| Secuencia de trabajo | `steps`, parcial de flujo | Muestra paso actual y siguiente acción sin sustituir la navegación. |
| Encabezado de página | Parcial propio | Línea contable, título único y ranura de acción. La ayuda es excepcional. |
| Acciones | `btn` | Una `btn-primary` por grupo; destructivas con `btn-error`. |
| Resumen numérico | `stats`, `stat` | Entre 2 y 4 indicadores; cifras tabulares. |
| Sección contenida | `card card-border` | Solo cuando el borde agrupa una unidad real; no anidar tarjetas. |
| Estado | `badge` | Texto visible y color semántico estable. |
| Mensaje | `alert` | `role="alert"` para error; `role="status"` para resultado no urgente. |
| Formulario | `fieldset`, `input`, `select`, `textarea`, `label` | Alturas coherentes y etiqueta visible. |
| Resultados | `table` | Wrapper `overflow-x-auto` solo cuando no sea viable el patrón móvil. |
| Exportación de tablas | DataTables Buttons + `btn` | Grupo Copiar, CSV, Excel e Imprimir con icono y texto; se excluye la columna de acciones. |
| Carga | `skeleton` | Reserva la geometría final y evita saltos. |
| Consulta o acción corta | `dialog` + `modal` | Misma entidad, hasta tres campos, foco contenido y salida clara; la ruta completa permanece como respaldo. |
| Ubicación | `breadcrumbs` | Solo desde el segundo nivel de navegación. |
| Iconos | Lucide | Tamaño `20px`, trazo coherente y `aria-hidden` si son decorativos. No se añaden iconos decorativos al encabezado; las acciones con icono mantienen etiqueta visible. |

### Navegación funcional

La barra lateral no presenta vistas técnicas ni formularios como destinos aislados. Las entradas de primer nivel se agrupan así y se filtran por permisos:

1. **Principal:** Inicio.
2. **Gestión de clientes:** Clientes, Catálogo.
3. **Caja y conciliación:** Comprobantes, Mi caja.
4. **Análisis:** Reportes.
5. **Administración:** Usuarios, Configuración.
6. **Sistema:** Estado, Contingencias, Auditoría.

Los detalles, ediciones, expedientes y configuraciones específicas se alcanzan desde su listado o mediante navegación local. No se duplican en la barra lateral.

Cada grupo funcional del lateral usa un submenú `<details>` de daisyUI. Solo el grupo de la ruta activa se abre inicialmente; los demás permanecen contraídos. En modo compacto se conservan los iconos y las etiquetas accesibles.

Documentos, cartera y cobros pertenecen al expediente. La navegación local mantiene visible nombre e identificación del cliente; un formulario no permite sustituirlo. Los accesos sin cliente presentan selección previa. Caja, conciliación y reportes conservan alcance transversal. Véase SRS 11.1.1.

Las vistas que participan en una operación muestran la secuencia **cliente → expediente → operación → resultado** y una instrucción de siguiente paso. El tablero presenta esta ruta como punto de entrada; no se muestran pantallas financieras como tareas independientes sin contexto.

### Librerías de interacción

| Librería | Uso canónico |
|---|---|
| DataTables 3 | Todas las tablas de la aplicación en escritorio, con 5 registros iniciales y opciones 5, 10 y 25; los conjuntos que pueden crecer, como clientes y auditoría, usan procesamiento del servidor. |
| Alpine CSP | Estado declarativo pequeño, como la navegación compacta, sin evaluación de expresiones inseguras. |
| Toastify | Confirmaciones breves de operaciones exitosas. |
| SweetAlert2 | Confirmación previa de acciones financieras, irreversibles o sensibles, explicando la consecuencia. |
| Tom Select | Búsquedas de cliente y documento; búsqueda remota cuando el conjunto pueda crecer. |
| Lucide | Registro centralizado de iconos funcionales y decorativos. |

## 9. Estados obligatorios

Cada patrón debe prever:

- Carga inicial con geometría reservada.
- Estado vacío con causa y siguiente acción.
- Resultado filtrado sin coincidencias.
- Error recuperable con instrucción concreta.
- Error de validación resumido e indicado junto a cada campo.
- Guardado en curso con control deshabilitado y `aria-busy`.
- Resultado pendiente después de un corte de conexión.
- Sesión vencida.
- Acceso restringido.
- Conflicto de versión o saldo modificado.
- Éxito confirmado con referencia de operación.

## 10. Comportamiento adaptable

| Elemento | Escritorio | Móvil |
|---|---|---|
| Navegación | Barra lateral visible | Drawer activado desde la barra superior |
| Encabezado | Texto y acciones en una línea | Acciones debajo del título, ancho completo cuando corresponda |
| Indicadores | 2 a 4 columnas iguales | 1 o 2 columnas |
| Formulario | Rejilla de 2 columnas | Una columna |
| Tabla | Columnas completas | Registros apilados o desplazamiento solo si es indispensable |
| Detalle | Contenido `8/4` | Contexto antes del historial |
| Acciones de fila | Etiqueta e icono | Menú o botones con etiqueta, nunca solo hover |

Puntos de comprobación: `360px`, `768px`, `1024px` y `1440px`.

## 11. Movimiento y respuesta

- Transiciones funcionales entre `120ms` y `180ms`.
- No hay animaciones de entrada ni desplazamientos decorativos.
- No se anima ancho, alto ni posición del contenido principal.
- El estado de un botón cambia inmediatamente al enviar.
- Se respeta `prefers-reduced-motion`.
- Una animación nunca sustituye un mensaje de estado.

## 12. Accesibilidad

- Contraste mínimo `4.5:1` para texto normal.
- Foco visible de al menos `2px`, sin quedar oculto bajo barras persistentes.
- Orden de tabulación nativo y coherente con la lectura.
- Etiquetas conectadas mediante `for` e `id`.
- Errores mediante `aria-describedby` y un resumen con `role="alert"`.
- Encabezados jerárquicos; una sola etiqueta `h1` por página.
- Tablas con `caption`, encabezados y alcance correcto.
- Iconos decorativos ocultos del árbol accesible.
- Estados y gráficos comprensibles sin depender del color.

## 13. Arquitectura mantenible

```text
app/Views/
├── layouts/
│   ├── app.php
│   └── auth.php
├── partials/
│   ├── sidebar.php
│   ├── topbar.php
│   ├── breadcrumbs.php
│   ├── page_header.php
│   ├── feedback.php
│   ├── empty_state.php
│   └── pagination.php
└── components/
    ├── status_badge.php
    ├── stat.php
    ├── field_error.php
    └── operation_reference.php

resources/css/
├── app.css
├── theme.css
├── layout.css
└── utilities.css

resources/js/
├── app.js
└── modules/
    ├── drawer.js
    ├── form-state.js
    └── confirmation.js
```

La propuesta anterior se aplicará gradualmente; no se crearán archivos vacíos. Cada extracción debe reemplazar duplicación real.

### Contrato mínimo de una vista

Cada controlador prepara, como mínimo, los datos que correspondan entre:

- `title`: título único y breve.
- `description`: propósito o contexto de la vista.
- `breadcrumbs`: jerarquía autorizada.
- `primaryAction`: única acción principal disponible.
- `secondaryActions`: acciones auxiliares.
- `status`: estado del recurso cuando exista.
- `filters`: valores normalizados de los filtros.
- `rows` o `resource`: datos ya preparados para presentación.
- `pagination`: metadatos de navegación.
- `feedback`: mensajes de resultado o error.

La vista no consulta la base de datos ni decide permisos de negocio; únicamente presenta capacidades ya autorizadas.

## 14. Criterio de conformidad

Una vista cumple el sistema visual cuando:

1. Usa el armazón universal y uno de los cuatro patrones de página.
2. Conserva cuadrícula, espacios, tipografía y tokens semánticos.
3. Reutiliza los componentes canónicos sin duplicar estilos.
4. Incluye estados vacío, carga, error y respuesta de la operación.
5. Funciona con teclado y conserva el foco visible.
6. Se revisa en los cuatro anchos definidos.
7. No introduce excepciones visuales sin documentarlas en `pages/`.
