/* Anteprima media in dialog: solo presentazione, usa il link protetto già presente. */
(function () {
  'use strict';

  if (!('HTMLDialogElement' in window) || !HTMLDialogElement.prototype.showModal) { return; }

  var dialog = document.createElement('dialog');
  dialog.className = 'archivio-preview-dialog';
  dialog.setAttribute('aria-label', 'Anteprima del file');

  var shell = document.createElement('div');
  shell.className = 'archivio-preview-dialog__shell';
  var header = document.createElement('header');
  header.className = 'archivio-preview-dialog__header';
  var title = document.createElement('h2');
  title.className = 'archivio-preview-dialog__title';
  var actions = document.createElement('div');
  actions.className = 'archivio-preview-dialog__actions';
  var openLink = document.createElement('a');
  openLink.className = 'btn btn-sm btn-outline';
  openLink.target = '_blank';
  openLink.rel = 'noopener';
  openLink.textContent = 'Apri in una nuova scheda';
  var closeButton = document.createElement('button');
  closeButton.className = 'btn btn-sm btn-gold';
  closeButton.type = 'button';
  closeButton.textContent = 'Chiudi';
  actions.appendChild(openLink);
  actions.appendChild(closeButton);
  header.appendChild(title);
  header.appendChild(actions);

  var stage = document.createElement('div');
  stage.className = 'archivio-preview-dialog__stage';
  shell.appendChild(header);
  shell.appendChild(stage);
  dialog.appendChild(shell);
  document.body.appendChild(dialog);

  function chiudi() {
    if (dialog.open) { dialog.close(); }
  }

  closeButton.addEventListener('click', chiudi);
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) { chiudi(); }
  });
  dialog.addEventListener('close', function () {
    stage.replaceChildren();
    openLink.removeAttribute('href');
  });

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
    var link = event.target.closest ? event.target.closest('a.archivio-file__anteprima[href]') : null;
    if (!link || !document.body.contains(link)) { return; }

    var card = link.closest('.archivio-file');
    var mediaLabel = card && card.querySelector('.archivio-file__tipo');
    var mediaTitle = card && card.querySelector('.archivio-file__nome');
    var isVideo = mediaLabel && mediaLabel.textContent.trim().toLowerCase() === 'video';
    var element = document.createElement(isVideo ? 'video' : 'img');

    event.preventDefault();
    title.textContent = mediaTitle ? mediaTitle.textContent.trim() : 'Anteprima media';
    openLink.href = link.href;
    element.className = 'archivio-preview-dialog__media';
    if (isVideo) {
      element.controls = true;
      element.preload = 'metadata';
      element.playsInline = true;
      element.setAttribute('aria-label', title.textContent);
    } else {
      element.alt = title.textContent;
      element.decoding = 'async';
    }
    element.src = link.href;
    stage.replaceChildren(element);
    dialog.showModal();
    closeButton.focus();
  });
})();
