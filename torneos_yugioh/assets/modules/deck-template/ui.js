(function () {
  'use strict';
  const modal = document.querySelector('[data-deck-template-modal]');
  const opener = document.querySelector('[data-open-deck-sheet=""]');
  if (!modal || !opener) return;
  const file = modal.querySelector('[data-deck-template-file]');
  const status = modal.querySelector('[data-deck-template-status]');
  const form = modal.querySelector('form');
  const template = `# STATIONX-DECK-TEMPLATE/1
# Edita este TXT y conserva las secciones. Cantidad permitida: 1 a 3.
[DECK]
nombre=Mi nuevo deck
arquetipo=
estilo=
notas=

[MAIN]
3;Nombre de carta del Main

[EXTRA]
1;Nombre de carta del Extra

[SIDE]
1;Nombre de carta del Side

[RESERVA]
# Opcional: 1;Nombre de carta de Reserva
`;
  let previousFocus;
  function close() {
    modal.hidden = true;
    document.body.classList.remove('deck-template-open');
    previousFocus?.focus();
  }
  opener.addEventListener('click', event => {
    event.preventDefault();
    event.stopImmediatePropagation();
    previousFocus = document.activeElement;
    modal.hidden = false;
    document.body.classList.add('deck-template-open');
    modal.querySelector('[data-deck-template-close]')?.focus();
  }, true);
  modal.querySelectorAll('[data-deck-template-close]').forEach(button => button.addEventListener('click', close));
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  document.addEventListener('keydown', event => { if (!modal.hidden && event.key === 'Escape') close(); });
  modal.querySelector('[data-deck-template-download]')?.addEventListener('click', () => {
    const href = URL.createObjectURL(new Blob([template], { type: 'text/plain;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = href;
    anchor.download = 'stationx-plantilla-deck.txt';
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(href), 1000);
  });
  file?.addEventListener('change', () => {
    const selected = file.files?.[0];
    status.textContent = selected ? `${selected.name} seleccionado` : '';
  });
  form?.addEventListener('submit', event => {
    if (!file.files?.length || !/\.txt$/i.test(file.files[0].name) || file.files[0].size > 131072) {
      event.preventDefault();
      status.textContent = 'Selecciona un archivo .txt de hasta 128 KiB.';
    }
  });
  modal.querySelector('[data-deck-template-sync]')?.addEventListener('click', () => {
    // Refresh the authoritative server-rendered roster, not a browser-only copy.
    location.reload();
  });
})();
