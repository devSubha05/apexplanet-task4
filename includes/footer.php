</main>
<footer class="rx-footer py-4 mt-5 text-center">
  <div class="container small">&copy; <?= date('Y') ?> <?= APP_NAME ?> &middot; ApexPlanet Internship Task 4</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
