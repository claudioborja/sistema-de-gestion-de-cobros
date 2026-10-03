export function installDocumentLines(onAdded) {
  document.querySelectorAll('[data-document-lines]').forEach(container => {
    const form = container.closest('form');
    const template = container.querySelector('[data-document-line]').cloneNode(true);
    const status = form.querySelector('[data-lines-status]');
    function renumber() {
      const rows = [...container.querySelectorAll('[data-document-line]')];
      rows.forEach((row, index) => {
        row.querySelector('[data-line-title]').textContent = `Línea ${index + 1}`;
        row.querySelectorAll('input').forEach(input => {
          input.name = input.name.replace(/^lines\[\d+\]/, `lines[${index}]`);
          input.required = index === 0;
        });
        const remove = row.querySelector('[data-remove-line]');
        remove.disabled = rows.length === 1;
        remove.setAttribute('aria-label', `Quitar ítem ${index + 1}`);
        row.querySelector('[data-form-help]')?.setAttribute('aria-label', `Información: Línea ${index + 1}`);
      });
      status.textContent = `${rows.length} ${rows.length === 1 ? 'ítem en el documento' : 'ítems en el documento'}.`;
    }
    form.querySelector('[data-add-line]').addEventListener('click', () => {
      const row = template.cloneNode(true);
      row.querySelectorAll('input').forEach(input => {
        input.value = input.name.endsWith('[quantity]') ? '1' : '';
      });
      container.append(row);
      renumber();
      onAdded(row);
      row.querySelector('input').focus();
    });
    container.addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-line]');
      if (!remove || container.children.length <= 1) return;
      const row = remove.closest('[data-document-line]');
      const next = row.nextElementSibling || row.previousElementSibling;
      row.remove();
      renumber();
      next.querySelector('input').focus();
    });
    renumber();
  });
}
