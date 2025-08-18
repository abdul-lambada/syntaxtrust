<!-- Bootstrap core JavaScript-->
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Core plugin JavaScript-->
<script src="assets/vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- Custom scripts for all pages-->
<script src="assets/js/sb-admin-2.min.js"></script>

<!-- Page level plugins -->
<script src="assets/vendor/chart.js/Chart.min.js"></script>

<!-- Page level custom scripts -->
<script src="assets/js/demo/chart-area-demo.js"></script>
<script src="assets/js/demo/chart-pie-demo.js"></script>

<!-- Page level plugins -->
<script src="assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Page level custom scripts -->
<script src="assets/js/demo/datatables-demo.js"></script>

<?php if (!empty($_SESSION['csrf_token'])): ?>
<script>
  window.SYNTRUST = window.SYNTRUST || {};
  window.SYNTRUST.csrf = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";
  jQuery(function($){
    var markedOnce = false;
    $('#alertsDropdown').on('show.bs.dropdown', function(){
      if (markedOnce) return;
      markedOnce = true;
      $.post('api/notifications_mark_read.php', { csrf_token: window.SYNTRUST.csrf })
        .done(function(resp){
          try { if (typeof resp === 'string') resp = JSON.parse(resp); } catch(e) {}
          // Remove unread badge counter on bell
          var $badge = $('#alertsDropdown .badge-counter');
          if ($badge.length) { $badge.remove(); }
          // Remove 'New' badges in dropdown items
          $(".dropdown-list[aria-labelledby='alertsDropdown'] .badge.badge-danger").remove();
        })
        .fail(function(){
          // Allow retry next open if request fails
          markedOnce = false;
        });
    });
  });
</script>
<?php endif; ?>
