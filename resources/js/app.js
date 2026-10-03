import '../css/app.css';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-buttons-dt/css/buttons.dataTables.css';
import 'toastify-js/src/toastify.css';
import 'tom-select/dist/css/tom-select.default.css';
import Alpine from '@alpinejs/csp';
import DataTable from 'datatables.net-dt';
import 'datatables.net-buttons-dt';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';
import JSZip from 'jszip';
import Swal from 'sweetalert2/dist/sweetalert2.esm.js';
import 'sweetalert2/dist/sweetalert2.css';
import Toastify from 'toastify-js';
import TomSelect from 'tom-select';
import { dataTableDefaults, dataTableSelector } from './datatables-config.js';
import { installFormHelp } from './form-help.js';
import { installDocumentLines } from './document-lines.js';
import {
  Activity, ArrowLeft, ArrowRight, ArrowUpRight, BadgeCheck, BadgeDollarSign,
  Banknote, BarChart3, Building2, CalendarClock, ChartNoAxesCombined, Check,
  CheckCheck, ChevronLeft, CircleAlert, CircleCheck, CircleUserRound, ClipboardCopy,
  Clock3, Download, Eye, FileBarChart, FileDown, FileSpreadsheet, FileText, FileX,
  Filter, FolderOpen, History, Inbox, Info, KeyRound, Landmark, Link, LogIn,
  LayoutDashboard, List, ListTree, LogOut, MailKey, MailPlus, Menu, MessagesSquare, Package,
  PanelLeftClose, Paperclip, Pencil, Plus, Printer, Receipt, ReceiptText, RotateCcw, Save,
  ScanBarcode, ScanLine, ScanSearch, Search, Send, Settings2, ShieldAlert,
  ShieldCheck, Trash2, TriangleAlert, Upload, UserCheck, UserPlus, UserRound, UserRoundCheck, UserX,
  Users, UsersRound, Wallet, WalletCards, WalletMinimal, X, createIcons,
} from 'lucide';

const interfaceIcons = {
  Activity, ArrowLeft, ArrowRight, ArrowUpRight, BadgeCheck, BadgeDollarSign,
  Banknote, BarChart3, Building2, CalendarClock, ChartNoAxesCombined, Check,
  CheckCheck, ChevronLeft, CircleAlert, CircleCheck, CircleUserRound, ClipboardCopy,
  Clock3, Download, Eye, FileBarChart, FileDown, FileSpreadsheet, FileText, FileX,
  Filter, FolderOpen, History, Inbox, Info, KeyRound, Landmark, Link, LogIn,
  LayoutDashboard, List, ListTree, LogOut, MailKey, MailPlus, Menu, MessagesSquare, Package,
  PanelLeftClose, Paperclip, Pencil, Plus, Printer, Receipt, ReceiptText, RotateCcw, Save,
  ScanBarcode, ScanLine, ScanSearch, Search, Send, Settings2, ShieldAlert,
  ShieldCheck, Trash2, TriangleAlert, Upload, UserCheck, UserPlus, UserRound, UserRoundCheck, UserX,
  Users, UsersRound, Wallet, WalletCards, WalletMinimal, X,
};

const buttonIconRules = [
  [/^(buscar|filtrar)/i, 'filter'],
  [/^guardar/i, 'save'],
  [/^(cancelar|cerrar)$/i, 'x'],
  [/^(volver|anterior)/i, 'arrow-left'],
  [/^(abrir|ver|comparar)/i, 'eye'],
  [/^editar/i, 'pencil'],
  [/^(continuar|siguiente|ir a)/i, 'arrow-right'],
  [/^(confirmar|aplicar)/i, 'check-check'],
  [/^(registrar|nuevo)/i, 'plus'],
  [/^identificar/i, 'scan-search'],
  [/^(adjuntar|archivos)/i, 'paperclip'],
  [/^(descargar|generar pdf)/i, 'download'],
  [/^revertir/i, 'rotate-ccw'],
  [/^reactivar/i, 'user-check'],
  [/^desactivar/i, 'user-x'],
  [/^enviar/i, 'send'],
  [/^(iniciar sesión|ir a iniciar sesión)/i, 'log-in'],
];

function renderInterfaceIcons(root = document) {
  root.querySelectorAll('.btn').forEach(button => {
    if (button.querySelector('svg, [data-lucide]')) return;
    const label = button.textContent.trim().replace(/\s+/g, ' ');
    const rule = buttonIconRules.find(([pattern]) => pattern.test(label));
    if (!rule) return;
    const icon = document.createElement('i');
    icon.dataset.lucide = rule[1];
    icon.className = 'icon';
    icon.setAttribute('aria-hidden', 'true');
    button.prepend(icon);
  });
  createIcons({ icons: interfaceIcons });
}

DataTable.Buttons.jszip(JSZip);

Alpine.data('navigationState', () => ({
  compact: false,
  init() {
    this.compact = localStorage.getItem('cobros-sidebar') === 'compact';
    this.sync();
  },
  toggle() {
    this.compact = !this.compact;
    localStorage.setItem('cobros-sidebar', this.compact ? 'compact' : 'expanded');
    this.sync();
  },
  sync() {
    document.documentElement.dataset.sidebar = this.compact ? 'compact' : 'expanded';
    const button = this.$root.querySelector('.sidebar-compact-toggle');
    button?.setAttribute('aria-pressed', String(this.compact));
    button?.setAttribute('aria-label', this.compact ? 'Expandir navegación' : 'Compactar navegación');
  },
}));
window.Alpine = Alpine;
Alpine.start();

const dataTableLanguage = {
  emptyTable: 'No hay datos disponibles',
  info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
  infoEmpty: 'Sin registros para mostrar',
  infoFiltered: '(filtrados de _MAX_ registros)',
  lengthMenu: 'Mostrar _MENU_ registros',
  loadingRecords: 'Cargando…',
  processing: 'Procesando…',
  search: 'Buscar:',
  zeroRecords: 'No se encontraron coincidencias',
  paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
};

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
})[character]);

for (const table of document.querySelectorAll(dataTableSelector)) {
  const exportColumns = ':not(.dt-actions)';
  const exportTitle = table.querySelector('caption')?.textContent.trim() || document.title;
  const serverExportButton = (label, icon, urlValue) => ({
    text: `<i data-lucide="${icon}" class="icon" aria-hidden="true"></i>${label}`,
    className: 'btn btn-soft',
    titleAttr: `Exportar ${label} con todos los registros filtrados`,
    action(_event, dataTable) {
      const url = new URL(urlValue, window.location.origin);
      const search = dataTable.search();
      const status = document.querySelector('[data-datatable-filter="clients"] [name="estado"]')?.value || '';
      if (search) url.searchParams.set('q', search);
      if (status) url.searchParams.set('estado', status);
      window.location.assign(url);
    },
  });
  const exportButtons = [
    {
      extend: 'copyHtml5', text: '<i data-lucide="clipboard-copy" class="icon" aria-hidden="true"></i>Copiar',
      className: 'btn btn-soft', titleAttr: 'Copiar datos de la tabla',
      exportOptions: { columns: exportColumns },
    },
    table.dataset.exportCsv ? serverExportButton('CSV', 'file-down', table.dataset.exportCsv) : {
      extend: 'csvHtml5', text: '<i data-lucide="file-down" class="icon" aria-hidden="true"></i>CSV',
      className: 'btn btn-soft', title: exportTitle, filename: exportTitle,
      exportOptions: { columns: exportColumns },
    },
    table.dataset.exportXlsx ? serverExportButton('Excel', 'file-spreadsheet', table.dataset.exportXlsx) : {
      extend: 'excelHtml5', text: '<i data-lucide="file-spreadsheet" class="icon" aria-hidden="true"></i>Excel',
      className: 'btn btn-soft', title: exportTitle, filename: exportTitle,
      exportOptions: { columns: exportColumns },
    },
    {
      extend: 'print', text: '<i data-lucide="printer" class="icon" aria-hidden="true"></i>Imprimir',
      className: 'btn btn-soft', title: exportTitle,
      exportOptions: { columns: exportColumns },
    },
  ];
  const common = {
    ...dataTableDefaults,
    order: [],
    language: dataTableLanguage,
  };
  let instance;

  if (table.dataset.datatable === 'clients') {
    const filter = document.querySelector('[data-datatable-filter="clients"]');
    const canEdit = table.dataset.canEdit === 'true';
    const recordsBase = table.dataset.recordsBase || '';
    instance = new DataTable(table, {
      ...common,
      processing: true,
      serverSide: true,
      layout: { topStart: 'pageLength', topEnd: { buttons: exportButtons }, bottomStart: 'info', bottomEnd: 'paging' },
      ajax: {
        url: table.dataset.source,
        data(request) {
          request.estado = filter?.querySelector('[name="estado"]')?.value || '';
        },
      },
      search: { search: filter?.querySelector('[name="q"]')?.value || '' },
      columns: [
        {
          data: 'nombre',
          render(data, type, row) {
            if (type !== 'display') return data;
            return `<div class="max-w-md break-words font-semibold">${escapeHtml(data)}</div><span class="font-data text-xs text-base-content/70">Cliente #${Number(row.id)}</span>`;
          },
        },
        { data: 'identificacion', render: data => escapeHtml(data || 'No informada') },
        {
          data: 'activo',
          render: active => `<span class="badge ${active ? 'badge-success badge-soft' : 'badge-ghost'}">${active ? 'Activo' : 'Inactivo'}</span>`,
        },
        {
          data: 'id', orderable: false, searchable: false,
          render(id, type, row) {
            if (type !== 'display') return Number(id);
            const safeId = Number(id);
            const name = escapeHtml(row.nombre);
            return `<div class="flex justify-end gap-2"><a class="btn" href="${recordsBase}/${safeId}/expediente">Ver<span class="sr-only"> expediente de ${name}</span></a>${canEdit ? `<a class="btn" href="${recordsBase}/${safeId}">Editar<span class="sr-only"> ${name}</span></a>` : ''}</div>`;
          },
        },
      ],
    });

    filter?.addEventListener('submit', event => {
      if (window.matchMedia('(min-width: 48rem)').matches) {
        event.preventDefault();
        instance.search(filter.querySelector('[name="q"]')?.value || '').draw();
      }
    });
  } else if (table.dataset.datatable === 'audit') {
    const filter = document.querySelector('[data-datatable-filter="audit"]');
    instance = new DataTable(table, {
      ...common,
      processing: true,
      serverSide: true,
      ordering: false,
      layout: { topStart: 'pageLength', topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
      ajax: {
        url: table.dataset.source,
        data(request) {
          request.accion = filter?.querySelector('[name="accion"]')?.value || '';
        },
      },
      search: { search: filter?.querySelector('[name="q"]')?.value || '' },
      columns: [
        { data: 'occurredAt', render: data => `<span class="font-data whitespace-nowrap">${escapeHtml(data)}</span>` },
        { data: 'action', render: data => escapeHtml(data) },
        { data: 'reference', render: data => `<span class="break-words font-medium">${escapeHtml(data)}</span>` },
        { data: 'actor', render: data => escapeHtml(data) },
        {
          data: null, orderable: false, searchable: false,
          render(_data, type, row) {
            if (type !== 'display') return '';
            return `<button class="btn btn-sm" type="button" aria-haspopup="dialog" data-audit-detail data-audit-id="${Number(row.eventId)}" data-audit-date="${escapeHtml(row.occurredAt)}" data-audit-action="${escapeHtml(row.action)}" data-audit-reference="${escapeHtml(row.reference)}" data-audit-actor="${escapeHtml(row.actor)}" data-audit-details="${escapeHtml(row.details)}"><i data-lucide="eye" class="icon" aria-hidden="true"></i>Detalles</button>`;
          },
        },
      ],
    });

    filter?.addEventListener('submit', event => {
      if (window.matchMedia('(min-width: 48rem)').matches) {
        event.preventDefault();
        instance.search(filter.querySelector('[name="q"]')?.value || '').draw();
      }
    });
  } else {
    instance = new DataTable(table, {
      ...common,
      layout: { topStart: 'pageLength', topEnd: ['search', { buttons: exportButtons }], bottomStart: 'info', bottomEnd: 'paging' },
      columnDefs: [{ targets: 'dt-actions', orderable: false, searchable: false }],
    });
  }

  if (instance) {
    document.documentElement.classList.add('has-datatables');
    instance.on('draw', () => renderInterfaceIcons(table.closest('.dt-container') || document));
  }
}

installDocumentLines(row => {
  installFormHelp(row);
  renderInterfaceIcons(row);
});
installFormHelp();
renderInterfaceIcons();

const auditDetailModal = document.querySelector('#audit-detail-modal');
document.addEventListener('click', event => {
  const target = event.target instanceof Element ? event.target.closest('[data-audit-detail]') : null;
  if (!(target instanceof HTMLElement) || !(auditDetailModal instanceof HTMLDialogElement)) return;
  const fields = {
    '#audit-detail-id': `Evento #${target.dataset.auditId || '—'}`,
    '#audit-detail-date': target.dataset.auditDate || 'No informada',
    '#audit-detail-action': target.dataset.auditAction || 'No informada',
    '#audit-detail-reference': target.dataset.auditReference || 'No informado',
    '#audit-detail-actor': target.dataset.auditActor || 'Sistema',
    '#audit-detail-data': target.dataset.auditDetails || 'No se registraron detalles adicionales.',
  };
  for (const [selector, value] of Object.entries(fields)) {
    const field = auditDetailModal.querySelector(selector);
    if (field) field.textContent = value;
  }
  auditDetailModal.showModal();
});

for (const trigger of document.querySelectorAll('[data-dialog-open]')) {
  trigger.addEventListener('click', event => {
    const dialog = document.getElementById(trigger.dataset.dialogOpen || '');
    if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== 'function') return;
    event.preventDefault();
    dialog.showModal();
  });
}
for (const button of document.querySelectorAll('[data-dialog-close]')) {
  button.addEventListener('click', () => button.closest('dialog')?.close());
}

for (const dialog of document.querySelectorAll('dialog[data-auto-open]')) {
  if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== 'function') continue;
  dialog.classList.remove('modal-open');
  if (!dialog.open) dialog.showModal();
}

for (const dialog of document.querySelectorAll('dialog[data-close-url]')) {
  dialog.addEventListener('close', () => {
    const destination = new URL(dialog.dataset.closeUrl || '', window.location.href);
    if (window.location.pathname !== destination.pathname || window.location.search !== destination.search) {
      window.history.replaceState({}, '', `${destination.pathname}${destination.search}${destination.hash}`);
    }
  });
}

function prepareSelect(element) {
  // Tom Select copies the original class list; daisyUI's native select
  // geometry must not be copied onto the custom wrapper.
  element.classList.remove('select', 'select-sm', 'input', 'input-sm');
  element.dataset.controlLabel = element.getAttribute('aria-label') || element.closest('label')?.querySelector('span')?.textContent?.trim() || 'Seleccionar opción';
}

function nameSelect(element, instance) {
  element.setAttribute('aria-hidden', 'true');
  instance.control_input.removeAttribute('aria-labelledby');
  instance.control_input.setAttribute('aria-label', element.dataset.controlLabel);
  instance.dropdown_content.removeAttribute('aria-labelledby');
  instance.dropdown_content.setAttribute('aria-label', element.dataset.controlLabel);
}

for (const element of document.querySelectorAll('[data-enhanced-select]')) {
  prepareSelect(element);
  const instance = new TomSelect(element, {
    create: false, allowEmptyOption: true,
    render: { no_results: () => '<div class="no-results">No hay coincidencias.</div>' },
  });
  nameSelect(element, instance);
}

for (const element of document.querySelectorAll('[data-client-select]')) {
  prepareSelect(element);
  const status = document.createElement('p');
  status.className = 'mt-2 text-sm text-base-content/70';
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  element.after(status);
  let requestController;
  let requestNumber = 0;
  const instance = new TomSelect(element, {
    valueField: 'id', labelField: 'nombre', searchField: ['nombre', 'identificacion'],
    maxItems: 1, create: false, maxOptions: 20, loadThrottle: 300,
    shouldLoad: query => query.trim().length >= 2,
    load(query, callback) {
      requestController?.abort();
      const controller = new AbortController();
      requestController = controller;
      const currentRequest = ++requestNumber;
      const url = new URL(element.dataset.source, window.location.origin);
      url.searchParams.set('q', query.trim());
      if (element.dataset.activeOnly === 'true') url.searchParams.set('estado', '1');
      status.textContent = 'Buscando clientes…';
      const timeout = window.setTimeout(() => controller.abort(), 10000);
      fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } })
        .then(response => {
          if (response.status === 401 || response.redirected) throw new Error('La sesión venció. Vuelve a iniciar sesión.');
          if (!response.ok) throw new Error('No se pudo buscar clientes. Inténtalo nuevamente.');
          return response.json();
        })
        .then(payload => {
          if (currentRequest !== requestNumber) return callback();
          if (!Array.isArray(payload.data)) throw new Error('La búsqueda no devolvió una respuesta válida.');
          status.textContent = payload.data.length ? `${payload.data.length} clientes encontrados.` : 'No hay clientes con ese nombre o identificación.';
          callback(payload.data);
        })
        .catch(error => {
          if (currentRequest === requestNumber) {
            status.textContent = error.name === 'AbortError' ? 'La búsqueda tardó demasiado. Vuelve a intentarlo.' : error.message;
            delete this.loadedSearches[query];
          }
          callback();
        })
        .finally(() => window.clearTimeout(timeout));
    },
    render: {
      option(data, escape) {
        return `<div><strong>${escape(data.nombre)}</strong><div class="text-xs text-base-content/70">${escape(data.identificacion || 'Sin identificación')}</div></div>`;
      },
      item(data, escape) { return `<div>${escape(data.nombre)}</div>`; },
      no_results: () => '<div class="no-results">Sin coincidencias. Revisa el nombre o identificación.</div>',
      not_loading: () => '<div class="no-results">Escribe al menos 2 caracteres.</div>',
      loading: () => '<div class="spinner">Buscando clientes…</div>',
    },
  });
  nameSelect(element, instance);
}

for (const message of document.querySelectorAll('[data-toast-success]')) {
  Toastify({
    text: message.dataset.toastSuccess,
    duration: 4000,
    gravity: 'top',
    position: 'right',
    close: true,
    stopOnFocus: true,
    className: 'cobros-toast',
  }).showToast();
  message.remove();
}

const drawerToggle = document.querySelector('#app-drawer');
for (const link of document.querySelectorAll('.app-sidebar a')) {
  link.addEventListener('click', () => {
    if (drawerToggle instanceof HTMLInputElement && window.innerWidth < 1024) drawerToggle.checked = false;
  });
}

for (const form of document.querySelectorAll('form')) {
  form.addEventListener('submit', async event => {
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const confirmation = submitter?.dataset.confirm || form.dataset.confirm;
    if (!confirmation || form.dataset.confirmed === 'true') return;
    event.preventDefault();
    const result = await Swal.fire({
      title: 'Confirmar acción',
      text: confirmation,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true,
      buttonsStyling: false,
      customClass: { popup: 'cobros-confirmation', actions: 'gap-2', confirmButton: 'btn btn-error', cancelButton: 'btn' },
    });
    if (result.isConfirmed) {
      form.dataset.confirmed = 'true';
      submitter ? form.requestSubmit(submitter) : form.requestSubmit();
    }
  });
}

for (const form of document.querySelectorAll('form[method="post"]')) {
  form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const button = event.submitter instanceof HTMLButtonElement ? event.submitter : form.querySelector('button[type="submit"]');
    if (!button) return;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    const spinner = document.createElement('span');
    spinner.className = 'loading loading-spinner loading-sm';
    spinner.setAttribute('aria-hidden', 'true');
    button.prepend(spinner);
  });
}

const errorSummary = document.querySelector('#form-errors');
if (errorSummary instanceof HTMLElement) requestAnimationFrame(() => errorSummary.focus());
