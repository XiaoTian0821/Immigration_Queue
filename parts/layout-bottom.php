  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?= $base ?>assets/js/app.js" data-csrf="<?= Security::csrfToken() ?>"></script>
<?= isset($page_js) ? $page_js : '' ?>
</body>
</html>
