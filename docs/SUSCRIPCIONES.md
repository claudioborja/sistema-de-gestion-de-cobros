# Suscripciones y servicios recurrentes

## Operación

1. Crear un ítem de tipo **Servicio** en Catálogo, por ejemplo **Acceso a Moodle**.
2. Abrir **Suscripciones → Nueva suscripción**, seleccionar el cliente, o entrar en **Clientes → Expediente → Suscripciones**.
3. Indicar servicio, precio, inicio, frecuencia mensual/anual, calendario, momento de cobro y plazo de pago. El final opcional es el primer día sin servicio y debe coincidir con un límite de período.
4. Revisar los primeros tres períodos y activar con esas condiciones. Activar guarda el contrato; la generación manual o programada crea sus cargos elegibles.
5. En el detalle, **Generar cargos hasta hoy** recupera períodos pendientes sin duplicar los existentes. Cada cargo aparece en Documentos, Cartera y Estado de cuenta del cliente.
6. **Registrar pago** abre los cobros del mismo cliente. Aplicar el pago a la mensualidad correspondiente actualiza su saldo; las demás mensualidades conservan su deuda.

El catálogo define el servicio; cada suscripción guarda su precio pactado y el nombre del servicio. No se integran cuentas ni accesos de Moodle: este módulo administra la suscripción y su cobranza.

## Calendario y cambios

- Desde inicio: conserva el día original; 31 de enero → último día de febrero → 31 de marzo. El aniversario del 29 de febrero usa el último día disponible y vuelve al 29 en año bisiesto.
- Calendario proporcional: el primer período termina al comenzar el siguiente mes y se prorratea con días reales y redondeo HALF_UP a centavos.
- Calendario completo: el primer período abreviado cuesta el precio mensual completo.
- Anual: usa el aniversario de inicio. No admite calendario mensual proporcional/completo.
- Anticipado: genera al inicio del período. Vencido: genera al finalizar. El plazo se suma a esa fecha contractual, aunque el proceso se ejecute tarde.
- La pantalla muestra el último día cubierto; la base almacena el final exclusivo.
- Pausas, reactivaciones y cancelaciones se registran en orden cronológico, en límites de período, con motivo. No se pueden modificar períodos ya generados. Se preservan las deudas anteriores. Una cancelación es definitiva.
- Un período completamente prestado con cobro vencido puede generarse al llegar a la fecha de cancelación.
- Los cambios de precio afectan períodos futuros no generados; conservan versiones anteriores y no alteran frecuencia ni ancla. Para cambiar de frecuencia, finalizar el contrato y crear otro desde el límite pactado.
- Renovación con aceptación: cada período necesita evidencia textual (persona, fecha y referencia del acuerdo). Se conserva quién la registró, cuándo y para qué versión. Un nuevo precio requiere otra aceptación. No se admiten aceptaciones tardías automáticas ni cambios a mitad de período: requieren revisión administrativa y un nuevo acuerdo vigente. No existe un flujo de ajuste/prorrateo retroactivo automático.

## Ejecución automática

Aplicar la migración con `php spark migrate --all`. El mismo servicio genera desde la pantalla y desde CLI:

```sh
php spark suscripciones:generar ID_USUARIO
```

El usuario debe estar activo y tener `contratos.generar`. No se usa la identidad del primer administrador de forma implícita. El comando procesa lotes de 100 contratos, confirma cada período en una transacción y continúa ante fallos de otros contratos; devuelve código de error si hubo fallos. Revisar `writable/logs` y volver a ejecutar permite recuperar cargos sin duplicarlos.

Ejemplo de cron cada hora (sustituir PHP, ruta y usuario por los del hosting):

```cron
17 * * * * cd /ruta/cobros && /usr/bin/php spark suscripciones:generar ID_USUARIO >> /ruta/cobros/writable/logs/subscriptions-cron.log 2>&1
```

La fecha de negocio se calcula en `America/Guayaquil`. Mantener la base de datos y el cron operativos; con el equipo apagado no se ejecutan cargos. Al volver a ejecutarse se recuperan períodos elegibles respetando pausas, cancelaciones y aceptaciones. La tarea local no se transfiere automáticamente a cPanel.

## Permisos y consistencia

- `contratos.ver`: administrador y cajera; consulta interna.
- `contratos.gestionar`: administrador; alta, cambios de estado, precio y aceptación.
- `contratos.generar`: administrador y usuario del cron autorizado.
- POST exige sesión, permiso y CSRF. Las vistas GET no generan deudas.
- Bloqueo del cliente y contrato antes de crear el período, clave única `(contrato_id, periodo_desde)` y transacción única para obligación, cuota, documento y vínculo.
- Los formularios de alta requieren una vista previa conservada en la sesión; su contenido se verifica antes de activar.

## Verificación y actualización

```sh
vendor/bin/phpunit --no-coverage tests/unit/SubscriptionCalendarTest.php
vendor/bin/phpunit --no-coverage tests/integration/SubscriptionTest.php
npm run build
```

Las pruebas usan exclusivamente `cobros_test`. Incluyen calendario, prorrateo, aceptación tardía/precio cambiado, permisos, CSRF, generación simultánea, precio versionado, pausa/cancelación y pago aplicado a una mensualidad.

Antes de una actualización de producción, respaldar la base y verificar el PHP del cron. La migración solo añade tablas. Para retirar la funcionalidad sin perder historia: desactivar su tarea y sus accesos, conservando las tablas. No revertir la migración con contratos o cargos reales.
