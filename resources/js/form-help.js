// Explanations shared by the form sections throughout the application.
const sectionHelp = {
  'Datos del cliente': 'Registra el nombre y los datos de contacto del cliente. Solo el nombre es obligatorio.',
  'Identificación': 'Indica el tipo de documento, su número y el país emisor para identificar al cliente y revisar posibles duplicados.',
  'Datos del ítem': 'Define el producto o servicio del catálogo y su precio de referencia. Guardarlo no genera una deuda.',
  'Condiciones': 'Configura las condiciones de crédito del cliente para sus operaciones.',
  'Datos de acceso': 'Configura la cuenta de la persona y su función, que determina los permisos de acceso.',
  'Permisos individuales': 'Añade permisos específicos a los que ya tiene la persona por su función.',
  'Estado de la cuenta': 'Activa o restringe el ingreso de esta persona al sistema conservando su historial.',
  'Actualizar identidad': 'Actualiza tu nombre de usuario y el correo asociado a tu cuenta.',
  'Cambiar contraseña': 'Introduce tu contraseña actual y confirma la nueva para cambiar tus credenciales.',
  'Información legal y fiscal': 'Configura la identificación y los datos del negocio que se utilizarán en sus documentos.',
  'Medios disponibles': 'Elige los medios de pago habilitados y el que aparecerá seleccionado inicialmente.',
  'Cuenta bancaria para recepción': 'Registra la cuenta del negocio destinada a recibir transferencias de los clientes.',
  'Cuentas receptoras': 'Estas son las cuentas del negocio. Solo las activas se pueden seleccionar al registrar transferencias; las inactivas conservan su historial.',
  'Nueva cuenta bancaria': 'Registra el banco, número, titular, tipo y un nombre para identificar la cuenta al cobrar.',
  'Editar cuenta bancaria': 'Actualiza los datos de la cuenta. Si ya recibió cobros, el banco y el número se conservan para proteger el historial.',
  'Asunto del correo de comprobante': 'Define el título del correo que acompaña al comprobante. Puedes usar las variables indicadas debajo de la plantilla.',
  'Plantilla de comprobante': 'Redacta el mensaje del comprobante. Las variables entre llaves se sustituyen por los datos correspondientes al envío.',
  'Plantilla de recordatorio de pago': 'Prepara el texto del recordatorio de cobro con los datos del cliente y del vencimiento.',
  'Servicio y condiciones de cobro': 'Asigna un servicio al cliente y define frecuencia, precio, calendario y plazo de pago. La suscripción genera cuentas por cobrar; los pagos se registran por separado.',
  'Vista previa antes de activar': 'Revisa las fechas e importes de los primeros períodos antes de confirmar la suscripción.',
  'Pausar, reactivar o cancelar': 'Cambia el estado desde el inicio de un período sin cargos. Las deudas anteriores se conservan.',
  'Programar nuevo precio': 'Aplica un precio nuevo desde un período futuro sin cargos, conservando el calendario original.',
  'Aceptar un período': 'Registra la autorización del cliente y su evidencia para un período que requiere aceptación.',
  'Datos del cobro': 'Registra el dinero recibido del cliente y los datos que permiten identificar el pago.',
  'Datos del documento': 'Prepara el documento del cliente con su concepto, fecha e importes.',
  'Encabezado del documento': 'Identifica el documento, su fecha y su concepto. El borrador todavía no genera deuda.',
  'Líneas documentadas': 'Detalla los productos o servicios, cantidades y precios. El total se calcula a partir de estas líneas.',
  'Confirmar y generar cuotas': 'Confirma el documento y distribuye su total entre las fechas de vencimiento elegidas.',
  'Plazo de la venta a crédito': 'Elige cuántos meses tendrá el crédito y cuándo vence la primera cuota. El total se divide sin intereses.',
  'Venta a crédito por meses': 'Registra una venta, acuerda el plazo mensual y confirma el calendario. Después aplica los cobros a las cuotas pendientes.',
  'Vista previa del crédito': 'Revisa cada vencimiento y su importe. Solo al confirmar se generan las cuotas y la deuda de esta venta.',
  'Transferencia recibida': 'Registra el importe, la fecha, la cuenta receptora y la referencia de la transferencia recibida.',
  'Distribución en cuotas': 'Asigna el dinero recibido a las cuotas pendientes. La parte no distribuida queda disponible para después.',
  'Aplicar saldo disponible': 'Distribuye el saldo del cobro entre documentos pendientes sin superar el saldo disponible.',
  'Revisión': 'Comprueba el cliente, el medio de pago y las cuotas antes de confirmar el cobro.',
  'Nuevo cronograma': 'Modifica las fechas de vencimiento de las cuotas sin cambiar sus importes ni los pagos registrados.',
  'Motivo de la reprogramación': 'Explica por qué se cambian los vencimientos. La justificación quedará en el historial.',
  'Motivo de la corrección': 'Explica por qué debe revertirse el cobro. El movimiento original y sus aplicaciones se conservarán en el historial.',
  'Motivo de la redistribución': 'Explica por qué se retira esta aplicación. El importe vuelve al cobro para poder distribuirlo nuevamente.',
  'Motivo administrativo': 'Registra una justificación clara de la corrección para que pueda revisarse en el historial.',
  'Adjuntar respaldo': 'Carga un archivo que respalde el documento y añade notas para identificarlo.',
  'Archivo': 'Selecciona el respaldo respetando los formatos y el tamaño máximo indicados en el formulario.',
  'Notas': 'Añade una descripción que ayude a reconocer el contenido del respaldo.',
  'Resolver comprobante': 'Revisa los datos y la evidencia del comprobante antes de registrar su resolución.',
  'Importe recibido USD': 'Indica el valor efectivamente recibido en dólares.',
  'Fecha bancaria': 'Indica la fecha en que se registró el movimiento en el banco.',
  'Cuenta receptora': 'Identifica la cuenta bancaria que recibió el dinero.',
  'Referencia bancaria': 'Introduce el identificador del banco para localizar y cotejar el movimiento.',
  'Tipo': 'Selecciona el tipo de documento que vas a registrar.',
  'Número': 'Introduce el número que identifica este documento.',
  'Fecha de emisión': 'Indica la fecha en que se emitió el documento.',
  'Concepto': 'Describe el producto, servicio o motivo del documento.',
  'Desde': 'Limita los resultados a los movimientos desde esta fecha.',
  'Hasta': 'Limita los resultados a los movimientos hasta esta fecha.',
  'Tipo de movimiento': 'Filtra el estado de cuenta por la clase de movimiento que deseas consultar.',
  'Bienvenido de nuevo': 'Ingresa tus credenciales para acceder a las funciones autorizadas de tu cuenta.',
  'Recuperar acceso': 'Introduce el correo registrado para solicitar instrucciones de recuperación.',
  'Restablecer contraseña': 'Define y confirma una nueva contraseña para recuperar el acceso.',
  'Actualizar contraseña': 'Define y confirma la nueva contraseña de tu cuenta.',
};

let helpSequence = 0;

export function installFormHelp(root = document) {
  root.querySelectorAll('legend, h2, h3, label > .fieldset-legend').forEach(heading => {
    if (heading.querySelector('[data-form-help]')) return;
    const title = heading.textContent.trim().replace(/\s+/g, ' ');
    const text = sectionHelp[title]
      || (/^Cuota #/.test(title) ? 'Selecciona la nueva fecha de vencimiento de esta cuota. Su importe y sus pagos se conservan.' : null)
      || (/^Documento #/.test(title) ? 'Indica cuánto del cobro se aplica a este documento, según su saldo pendiente.' : null)
      || (/^Vencimiento /.test(title) ? 'Define la fecha y el importe de esta cuota del documento.' : null)
      || (/^Línea /.test(title) ? 'Detalla el ítem, la cantidad y el precio que componen esta línea del documento.' : null)
      || (/^Estado: /.test(title) ? 'Activa o desactiva el cliente para nuevas operaciones conservando su historial.' : null);
    if (!text) return;

    const help = document.createElement('span');
    help.className = 'tooltip tooltip-bottom form-help';
    help.dataset.formHelp = '';
    help.setAttribute('aria-label', `Información: ${title}`);
    const icon = document.createElement('i');
    icon.dataset.lucide = 'info';
    icon.className = 'icon';
    icon.setAttribute('aria-hidden', 'true');

    const panel = document.createElement('span');
    panel.id = `form-section-help-${++helpSequence}`;
    panel.className = 'tooltip-content form-help-content';
    panel.setAttribute('role', 'tooltip');
    panel.textContent = text;
    help.setAttribute('aria-describedby', panel.id);
    help.append(icon, panel);
    help.addEventListener('mouseenter', () => {
      const anchor = help.getBoundingClientRect();
      const width = panel.getBoundingClientRect().width;
      const center = anchor.left + anchor.width / 2;
      const left = Math.max(16, Math.min(center - width / 2, window.innerWidth - width - 16));
      help.style.setProperty('--tt-trans', `${left - center}px`);
    });
    heading.append(help);
  });
}
