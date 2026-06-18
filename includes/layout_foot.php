</main>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
window.AGAI_BASE = '<?php echo SITE_URL; ?>';

(function(){
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  const bg = document.getElementById('burger');
  if (bg) bg.addEventListener('click', () => { sb.classList.add('open'); ov.classList.add('show'); });
  if (ov) ov.addEventListener('click', () => { sb.classList.remove('open'); ov.classList.remove('show'); });
})();

document.querySelectorAll('.nav-soon').forEach(a => {
  a.addEventListener('click', e => {
    e.preventDefault();
    Swal.fire({ icon:'info', title: a.dataset.module || 'Module', text:'Ce module sera disponible prochainement.', confirmButtonColor:'#23408F', timer:2400, timerProgressBar:true });
  });
});

// Activation des infobulles Bootstrap
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

// Echappement HTML pour l'affichage securise dans les tableaux
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// Copie dans le presse-papier avec repli (http non securise)
function copyText(text){
  if (navigator.clipboard && window.isSecureContext) { return navigator.clipboard.writeText(text); }
  return new Promise((resolve, reject) => {
    const ta = document.createElement('textarea');
    ta.value = text; ta.style.position='fixed'; ta.style.opacity='0';
    document.body.appendChild(ta); ta.focus(); ta.select();
    try { document.execCommand('copy'); resolve(); } catch(e){ reject(e); }
    document.body.removeChild(ta);
  });
}
</script>
</body>
</html>
