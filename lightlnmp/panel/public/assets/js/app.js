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
