<?php // photo/admin/_footer.php ?>
</div><!-- /.admin-container -->
</main><!-- /.admin-main -->
<script>
(function () {
  var toggle = document.getElementById('archivioNavToggle');
  var links = document.getElementById('archivioNavLinks');
  if (!toggle || !links) { return; }
  toggle.addEventListener('click', function () {
    var aperto = links.classList.toggle('aperto');
    toggle.setAttribute('aria-expanded', aperto ? 'true' : 'false');
    toggle.setAttribute('aria-label', aperto ? 'Chiudi il menu' : 'Apri il menu');
  });
})();
</script>
</body>
</html>
