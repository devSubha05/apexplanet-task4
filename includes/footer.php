</main>
<footer class="rx-footer py-4 mt-auto text-center">
  <div class="container small">&copy; <?= date('Y') ?> <?= APP_NAME ?> &middot; ApexPlanet Internship Task 4</div>
</footer>

<!-- Full Image Lightbox Modal -->
<div class="modal fade" id="foodLightboxModal" tabindex="-1" aria-labelledby="foodLightboxTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 1rem; background: #1a1615;">
      <div class="modal-header border-0 pb-0 position-relative">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3 shadow-sm bg-dark bg-opacity-50 rounded-circle p-2" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 text-center">
        <div style="background: #000; min-height: 280px; display: flex; align-items: center; justify-content: center;">
          <img id="foodLightboxImg" src="" alt="Full view" class="img-fluid w-100" style="max-height: 72vh; object-fit: contain;">
        </div>
        <div class="p-3 text-white text-start d-flex flex-wrap justify-content-between align-items-center gap-3" style="background: #231c1a;">
          <div class="flex-grow-1" style="max-width: 500px;">
            <div class="d-flex align-items-center gap-2 mb-1">
              <span id="foodLightboxVeg" class="veg-dot"></span>
              <span id="foodLightboxCat" class="badge bg-secondary small"></span>
              <span id="foodLightboxShop" class="badge bg-danger bg-opacity-75 small"></span>
            </div>
            <h4 id="foodLightboxTitle" class="fw-bold mb-1 text-white"></h4>
            <p id="foodLightboxDesc" class="small text-white-50 mb-0"></p>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span id="foodLightboxPrice" class="fs-4 fw-bold text-warning"></span>
            <form id="foodLightboxCartForm" method="post" action="<?= url('cart.php') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="food_id" id="foodLightboxFoodId" value="">
              <button id="foodLightboxAddBtn" class="btn btn-rx px-4"><i class="bi bi-cart-plus me-1"></i> Add to Cart</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
