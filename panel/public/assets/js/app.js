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
