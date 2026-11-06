// Client-side validation (server-side validation is the real protection)
(function () {
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      var pw = form.querySelector('input[name="password"][data-strong]');
      var cf = form.querySelector('input[name="confirm"]');
      if (pw) {
        var ok = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(pw.value);
        pw.setCustomValidity(ok ? '' : 'Min 8 chars with upper, lower case and a number.');
      }
      if (pw && cf) cf.setCustomValidity(pw.value === cf.value ? '' : 'Passwords do not match.');
      if (!form.checkValidity()) { ev.preventDefault(); ev.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });

  // Confirmation popup for destructive actions: <form data-confirm="Delete this?">
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.dataset.confirm)) ev.preventDefault();
    });
  });

  // Live avatar/food image preview: <input type="file" data-preview="#imgId">
  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var f = inp.files[0], img = document.querySelector(inp.dataset.preview);
      if (!f || !img) return;
      if (f.size > 2 * 1024 * 1024) { alert('Image must be 2 MB or smaller.'); inp.value = ''; return; }
      img.src = URL.createObjectURL(f);
    });
  });
})();
