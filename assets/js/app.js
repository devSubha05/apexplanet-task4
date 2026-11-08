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

  // Full-size Food Image Lightbox on click
  var lightboxEl = document.getElementById('foodLightboxModal');
  if (lightboxEl) {
    var modal = null;
    var modalImg = document.getElementById('foodLightboxImg');
    var modalTitle = document.getElementById('foodLightboxTitle');
    var modalDesc = document.getElementById('foodLightboxDesc');
    var modalPrice = document.getElementById('foodLightboxPrice');
    var modalVeg = document.getElementById('foodLightboxVeg');
    var modalCat = document.getElementById('foodLightboxCat');
    var modalShop = document.getElementById('foodLightboxShop');
    var modalId = document.getElementById('foodLightboxFoodId');
    var modalForm = document.getElementById('foodLightboxCartForm');

    document.addEventListener('click', function (e) {
      var target = e.target.closest('.food-img-clickable, .food-card img, .thumb, .food-img-wrapper');
      if (!target) return;

      var img = target.tagName === 'IMG' ? target : target.querySelector('img');
      if (!img) return;

      var fullSrc = img.dataset.fullImg || img.src;
      var title = img.dataset.title || img.alt || 'Dish Details';
      var desc = img.dataset.desc || '';
      var price = img.dataset.price || '';
      var isVeg = img.dataset.veg;
      var cat = img.dataset.cat || '';
      var shop = img.dataset.shop || '';
      var foodId = img.dataset.id || '';

      if (modalImg) modalImg.src = fullSrc;
      if (modalTitle) modalTitle.textContent = title;
      if (modalDesc) {
        modalDesc.textContent = desc;
        modalDesc.style.display = desc ? 'block' : 'none';
      }
      if (modalPrice) modalPrice.textContent = price;

      if (modalVeg) {
        if (isVeg === '1') {
          modalVeg.className = 'veg-dot me-1';
          modalVeg.title = 'Vegetarian';
          modalVeg.style.display = 'inline-block';
        } else if (isVeg === '0') {
          modalVeg.className = 'nonveg-dot me-1';
          modalVeg.title = 'Non-Vegetarian';
          modalVeg.style.display = 'inline-block';
        } else {
          modalVeg.style.display = 'none';
        }
      }

      if (modalCat) {
        modalCat.textContent = cat;
        modalCat.style.display = cat ? 'inline-block' : 'none';
      }

      if (modalShop) {
        modalShop.textContent = shop ? 'By ' + shop : '';
        modalShop.style.display = shop ? 'inline-block' : 'none';
      }

      if (modalId) modalId.value = foodId;
      if (modalForm) modalForm.style.display = foodId ? 'block' : 'none';

      if (!modal && window.bootstrap && window.bootstrap.Modal) {
        modal = new window.bootstrap.Modal(lightboxEl);
      }
      if (modal) modal.show();
    });
  }
})();
