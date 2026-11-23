document.addEventListener('click', function (e) {
  const trigger = e.target.closest('[data-confirm]');
  if (trigger && !confirm(trigger.getAttribute('data-confirm') || 'Are you sure?')) e.preventDefault();
});
