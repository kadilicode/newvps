  </div><!-- end page-content -->
</div><!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
setTimeout(() => {
  document.querySelectorAll('.alert-dismissible').forEach(el => {
    try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){}
  });
}, 6000);
</script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
