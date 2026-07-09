document.addEventListener('submit', function (event) {
  var message = event.target.getAttribute('data-confirm');
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});

document.addEventListener('show.bs.modal', function (event) {
  if (event.target.id !== 'rename-modal') return;
  var button = event.relatedTarget;
  if (!button) return;
  document.getElementById('rename-target').value = button.getAttribute('data-rename-target') || '';
  document.getElementById('rename-name').value = button.getAttribute('data-rename-name') || '';
});

function updateRewriteModeFields(modal) {
  var mode = modal.querySelector('#site-config-rewrite-mode');
  var preset = modal.querySelector('[data-rewrite-preset]');
  var custom = modal.querySelector('[data-rewrite-custom]');
  if (!mode || !preset || !custom) return;
  preset.classList.toggle('d-none', mode.value !== 'preset');
  custom.classList.toggle('d-none', mode.value !== 'custom');
}

document.addEventListener('show.bs.modal', function (event) {
  if (event.target.id !== 'site-config-modal') return;
  var button = event.relatedTarget;
  if (!button) return;
  var modal = event.target;
  modal.querySelector('#site-config-id').value = button.getAttribute('data-site-id') || '';
  modal.querySelector('#site-config-name').value = button.getAttribute('data-site-name') || '';
  modal.querySelector('#site-config-http-port').value = button.getAttribute('data-site-http-port') || '80';
  modal.querySelector('#site-config-https-port').value = button.getAttribute('data-site-https-port') || '443';
  modal.querySelector('#site-config-rewrite-mode').value = button.getAttribute('data-site-rewrite-mode') || 'preset';
  modal.querySelector('#site-config-rewrite-rule').value = button.getAttribute('data-site-rewrite-rule') || 'default';
  modal.querySelector('#site-config-rewrite-custom').value = button.getAttribute('data-site-rewrite-custom') || '';
  updateRewriteModeFields(modal);
});

document.addEventListener('change', function (event) {
  if (event.target.id !== 'site-config-rewrite-mode') return;
  updateRewriteModeFields(document.getElementById('site-config-modal'));
});

document.addEventListener('change', function (event) {
  var className = event.target.getAttribute('data-check-all');
  if (!className) return;
  document.querySelectorAll('.' + className).forEach(function (checkbox) {
    checkbox.checked = event.target.checked;
  });
});

document.addEventListener('show.bs.modal', function (event) {
  if (event.target.id !== 'extract-modal') return;
  var button = event.relatedTarget;
  if (!button) return;
  var log = event.target.querySelector('[data-extract-log]');
  var submit = event.target.querySelector('[data-extract-submit]');
  var refresh = event.target.querySelector('[data-extract-refresh]');
  document.getElementById('extract-target').value = button.getAttribute('data-extract-target') || '';
  document.getElementById('extract-name').value = button.getAttribute('data-extract-name') || '';
  document.getElementById('extract-destination').value = button.getAttribute('data-extract-destination') || '';
  document.getElementById('extract-overwrite').checked = false;
  if (log) log.textContent = '等待开始...';
  if (submit) submit.disabled = false;
  if (refresh) refresh.classList.add('d-none');
});

document.addEventListener('submit', function (event) {
  var form = event.target;
  if (!form.matches('[data-extract-form]')) return;
  event.preventDefault();

  var log = form.querySelector('[data-extract-log]');
  var submit = form.querySelector('[data-extract-submit]');
  var refresh = form.querySelector('[data-extract-refresh]');
  var decoder = new TextDecoder();

  function appendLog(text) {
    if (!log) return;
    if (log.textContent === '等待开始...') log.textContent = '';
    log.textContent += text;
    log.scrollTop = log.scrollHeight;
  }

  if (submit) submit.disabled = true;
  if (refresh) refresh.classList.add('d-none');
  if (log) log.textContent = '';
  appendLog('正在连接解压任务...\n');

  fetch(form.action, {
    method: 'POST',
    body: new FormData(form),
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'fetch' }
  }).then(function (response) {
    if (!response.body || !response.body.getReader) {
      return response.text().then(function (text) {
        appendLog(text);
      });
    }

    var reader = response.body.getReader();
    function readChunk() {
      return reader.read().then(function (result) {
        if (result.done) return;
        appendLog(decoder.decode(result.value, { stream: true }));
        return readChunk();
      });
    }
    return readChunk().then(function () {
      appendLog(decoder.decode());
    });
  }).catch(function (error) {
    appendLog('请求失败：' + error.message + '\n');
  }).finally(function () {
    if (submit) submit.disabled = false;
    if (refresh) refresh.classList.remove('d-none');
  });
});

(function () {
  var forms = document.querySelectorAll('.monaco-form');
  if (!forms.length) return;

  var monacoBase = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min';

  function loadMonaco(callback) {
    if (window.monaco && window.monaco.editor) {
      callback();
      return;
    }

    function configureAndLoad() {
      window.require.config({ paths: { vs: monacoBase + '/vs' } });
      window.MonacoEnvironment = {
        getWorkerUrl: function () {
          var worker = 'self.MonacoEnvironment={baseUrl:"' + monacoBase + '/"};importScripts("' + monacoBase + '/vs/base/worker/workerMain.js");';
          return 'data:text/javascript;charset=utf-8,' + encodeURIComponent(worker);
        }
      };
      window.require(['vs/editor/editor.main'], callback);
    }

    if (window.require && window.require.config) {
      configureAndLoad();
      return;
    }

    var script = document.createElement('script');
    script.src = monacoBase + '/vs/loader.js';
    script.async = true;
    script.onload = configureAndLoad;
    document.head.appendChild(script);
  }

  function updateStatus(editor, status) {
    var position = editor.getPosition();
    if (!position || !status) return;
    status.textContent = 'Ln ' + position.lineNumber + ', Col ' + position.column;
  }

  function initEditor(form) {
    var source = form.querySelector('.monaco-source');
    var target = form.querySelector('[data-editor-target]');
    var status = form.querySelector('.monaco-editor-status');
    var loadState = form.querySelector('.monaco-load-state');
    if (!source || !target) return;

    var editor = window.monaco.editor.create(target, {
      value: source.value,
      language: form.getAttribute('data-editor-language') || 'plaintext',
      theme: 'vs-dark',
      automaticLayout: true,
      minimap: { enabled: false },
      fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
      fontSize: 13,
      lineHeight: 20,
      scrollBeyondLastLine: false,
      wordWrap: 'on',
      tabSize: 4,
      insertSpaces: true,
      renderLineHighlight: 'all',
      padding: { top: 12, bottom: 12 }
    });

    form.classList.add('is-monaco-ready');
    if (loadState) loadState.textContent = 'Monaco Editor';
    updateStatus(editor, status);

    editor.onDidChangeCursorPosition(function () {
      updateStatus(editor, status);
    });

    editor.onDidChangeModelContent(function () {
      source.value = editor.getValue();
    });

    form.addEventListener('submit', function () {
      source.value = editor.getValue();
    });

    window.addEventListener('resize', function () {
      editor.layout();
    });
  }

  loadMonaco(function () {
    Array.prototype.forEach.call(forms, initEditor);
  });
})();
