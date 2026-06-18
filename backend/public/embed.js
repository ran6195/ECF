/**
 * ECF — Edysma Centralized Forms · loader embed.js
 *
 * Vanilla JS, zero dipendenze. Per ogni elemento [data-ecf-form] sulla pagina:
 *  1. fa fetch dell'HTML del form da /api/embed/{uuid}/render
 *  2. lo inietta in uno Shadow DOM (isolamento totale del CSS)
 *  3. intercetta il submit, invia i dati in JSON e mostra l'esito.
 *
 * La base URL dell'API è ricavata dall'attributo src dello <script> (o da data-api).
 * Funziona con più form nella stessa pagina e con un singolo tag <script>.
 */
(function () {
  'use strict';

  // --- Ricava la base URL dell'API dal tag <script> corrente ---
  function resolveApiBase() {
    var current = document.currentScript;
    if (!current) {
      // Fallback: cerca uno <script> che includa "embed.js".
      var scripts = document.getElementsByTagName('script');
      for (var i = scripts.length - 1; i >= 0; i--) {
        if (scripts[i].src && scripts[i].src.indexOf('embed.js') !== -1) {
          current = scripts[i];
          break;
        }
      }
    }
    if (!current) return '';

    // data-api ha la precedenza, altrimenti deriva dall'src.
    var explicit = current.getAttribute('data-api');
    if (explicit) return explicit.replace(/\/$/, '');

    var src = current.src || '';
    // Rimuove "/embed.js" (con eventuale query) per ottenere l'origine API.
    return src.replace(/\/embed\.js(\?.*)?$/, '');
  }

  var API_BASE = resolveApiBase();

  function endpoint(uuid, action) {
    return API_BASE + '/api/embed/' + encodeURIComponent(uuid) + '/' + action;
  }

  // --- Caricamento di un singolo form ---
  function loadForm(container) {
    var uuid = container.getAttribute('data-ecf-form');
    if (!uuid || container.__ecfLoaded) return;
    container.__ecfLoaded = true;

    fetch(endpoint(uuid, 'render'), { method: 'GET' })
      .then(function (res) {
        if (!res.ok) throw new Error('Form non disponibile (' + res.status + ')');
        return res.text();
      })
      .then(function (html) {
        var shadow = container.shadowRoot || container.attachShadow({ mode: 'open' });
        shadow.innerHTML = html;
        wireForm(shadow, uuid);
      })
      .catch(function (err) {
        container.textContent = 'Impossibile caricare il modulo.';
        if (window.console) console.error('[ECF]', err);
      });
  }

  // --- Collega gli handler al form dentro lo shadow root ---
  function wireForm(shadow, uuid) {
    var form = shadow.querySelector('form.ecf-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      submitForm(shadow, form, uuid);
    });
  }

  function submitForm(shadow, form, uuid) {
    clearErrors(shadow);

    var button = form.querySelector('.ecf-submit');
    var messageBox = shadow.querySelector('.ecf-message');
    var data = collectValues(form);
    data.source_url = window.location.href;

    if (button) button.disabled = true;

    fetch(endpoint(uuid, 'submit'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(function (res) {
        return res.json().then(function (body) {
          return { status: res.status, body: body };
        });
      })
      .then(function (result) {
        if (result.status >= 200 && result.status < 300 && result.body.success) {
          form.reset();
          showMessage(messageBox, result.body.message || 'Inviato con successo.', 'is-success');
        } else if (result.status === 422 && result.body.errors) {
          renderErrors(shadow, form, result.body.errors);
          showMessage(messageBox, result.body.message || 'Controlla i campi evidenziati.', 'is-error');
        } else {
          showMessage(messageBox, result.body.message || 'Si è verificato un errore.', 'is-error');
        }
      })
      .catch(function () {
        showMessage(messageBox, 'Errore di rete. Riprova.', 'is-error');
      })
      .finally(function () {
        if (button) button.disabled = false;
      });
  }

  // --- Raccoglie i valori dei campi del form ---
  function collectValues(form) {
    var data = {};
    var elements = form.querySelectorAll('input, textarea, select');

    elements.forEach(function (el) {
      var name = el.name;
      if (!name) return;

      // checkbox multipli: name="key[]"
      if (name.slice(-2) === '[]') {
        var key = name.slice(0, -2);
        if (!Array.isArray(data[key])) data[key] = [];
        if (el.checked) data[key].push(el.value);
        return;
      }

      if (el.type === 'checkbox') {
        data[name] = el.checked ? (el.value || '1') : '';
        return;
      }

      if (el.type === 'radio') {
        if (el.checked) data[name] = el.value;
        else if (!(name in data)) data[name] = '';
        return;
      }

      data[name] = el.value;
    });

    return data;
  }

  // --- Gestione messaggi ed errori ---
  function showMessage(box, text, cls) {
    if (!box) return;
    box.textContent = text;
    box.classList.remove('is-success', 'is-error');
    box.classList.add(cls);
    box.hidden = false;
  }

  function clearErrors(shadow) {
    shadow.querySelectorAll('.ecf-field-error').forEach(function (el) { el.remove(); });
    shadow.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    var box = shadow.querySelector('.ecf-message');
    if (box) { box.hidden = true; box.textContent = ''; }
  }

  function renderErrors(shadow, form, errors) {
    Object.keys(errors).forEach(function (key) {
      var messages = errors[key];
      var field = form.querySelector('[name="' + key + '"], [name="' + key + '[]"]');
      var container = field ? field.closest('.ecf-field') : null;
      if (field && field.classList) field.classList.add('is-invalid');

      if (container) {
        var p = document.createElement('p');
        p.className = 'ecf-field-error';
        p.textContent = Array.isArray(messages) ? messages.join(' ') : String(messages);
        container.appendChild(p);
      }
    });
  }

  // --- Avvio: trova tutti i form e caricali ---
  function init() {
    var containers = document.querySelectorAll('[data-ecf-form]');
    containers.forEach(loadForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
