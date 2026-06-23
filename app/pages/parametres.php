<?php
/**
 * Page : Parametres de l'application (CRUD leger - modification uniquement)
 * Accessible : admin seulement
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('parametres');

$csrf      = Security::generateCSRF();
$pageTitle = 'Parametres';
$active    = 'parametres';
$pageIcon  = 'bi-gear';
require_once INCLUDES_PATH . '/layout_head.php';

$TYPE_ICONS = [
    'string'  => ['bi-fonts',          '#23408F', 'Texte'],
    'integer' => ['bi-123',            '#1E9C4B', 'Nombre'],
    'boolean' => ['bi-toggle-on',      '#F3C300', 'Actif/Inactif'],
    'email'   => ['bi-envelope',       '#23408F', 'Email'],
    'url'     => ['bi-link-45deg',     '#6c757d', 'URL'],
];
?>
<style>
.param-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,30,54,.05);}
.param-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:16px 22px;}
.param-head h5{margin:0;font-size:1rem;font-weight:700;}
.param-head p{margin:0;font-size:.8rem;opacity:.8;}
.param-row{display:flex;align-items:center;gap:14px;padding:13px 18px;border-bottom:1px solid #f1f4f9;transition:background .15s;}
.param-row:last-child{border-bottom:none;}
.param-row:hover{background:#fafcff;}
.param-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex:0 0 auto;}
.param-label{flex:1 1 auto;}
.param-label .pl{font-weight:600;font-size:.9rem;color:#2C3E50;}
.param-label .pd{font-size:.76rem;color:#7b8aa0;}
.param-val{font-family:monospace;font-size:.88rem;background:#f5f7fa;padding:.25rem .6rem;border-radius:6px;color:#23408F;font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.param-type{font-size:.7rem;padding:.18rem .5rem;border-radius:20px;font-weight:700;}
.t-string{background:#e8f0fe;color:#23408F;}
.t-integer{background:#d1e7dd;color:#0a5c36;}
.t-boolean{background:#fff8e0;color:#856404;}
.t-email,.t-url{background:#f3e8ff;color:#6b21a8;}
.save-bar{background:#f5f7fa;border-top:1px solid #eef1f6;padding:14px 18px;display:flex;justify-content:flex-end;gap:10px;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-gear me-2" style="color:var(--anac-primary)"></i>Parametres de l'application</h1>
    <div class="sub">Configuration generale d'AGAI. Toute modification est journalisee.</div>
  </div>
</div>

<div id="paramZone">
  <div class="text-center py-5"><span class="spinner-border text-primary"></span></div>
</div>

<!-- Modale edition d'un parametre -->
<div class="modal fade" id="paramModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="paramForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier le parametre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="p_nom">
        <div class="mb-3">
          <label class="form-label fw-bold" id="p_label_lbl"></label>
          <div class="text-muted small mb-2" id="p_desc_lbl"></div>
          <div id="p_input_wrap"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="p_submit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/parametres';
function post(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API,data,null,'json'); }

const GROUPS = {
  'Application': ['site_name','site_url','default_lang','date_format','timezone'],
  'Securite':    ['session_timeout','max_login_attempts','lockout_time','2fa_enabled'],
  'Notifications':['email_notifications'],
};
const TYPE_ICONS = <?php echo json_encode($TYPE_ICONS, JSON_UNESCAPED_UNICODE); ?>;

function paramTypeInfo(type){
  const t = TYPE_ICONS[type] || ['bi-sliders','#6c757d','Autre'];
  return {icon:t[0], color:t[1], label:t[2]};
}
function typeClass(type){ return 't-'+(type||'string').split('/')[0]; }

function valDisplay(p){
  if(p.type_param==='boolean') return p.valeur_param==='1'?'<span class="badge bg-success">Actif</span>':'<span class="badge bg-secondary">Inactif</span>';
  if(p.type_param==='integer' && p.nom_param==='session_timeout') return Math.round(p.valeur_param/60)+' min ('+p.valeur_param+'s)';
  if(p.type_param==='integer' && p.nom_param==='lockout_time') return Math.round(p.valeur_param/60)+' min ('+p.valeur_param+'s)';
  return '<span class="param-val">'+esc(p.valeur_param||'-')+'</span>';
}

function buildInput(p){
  const v=esc(p.valeur_param||'');
  if(p.type_param==='boolean'){
    return '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="p_val" role="switch" '+(p.valeur_param==='1'?'checked':'')+'></div>';
  }
  if(p.type_param==='integer'){
    return '<input type="number" class="form-control" id="p_val" value="'+v+'" min="0" required>';
  }
  if(p.type_param==='email'){
    return '<input type="email" class="form-control" id="p_val" value="'+v+'" required>';
  }
  if(p.type_param==='url'){
    return '<input type="url" class="form-control" id="p_val" value="'+v+'">';
  }
  return '<input type="text" class="form-control" id="p_val" value="'+v+'" required>';
}

let PARAMS = {};

function render(data){
  data.forEach(function(p){ PARAMS[p.nom_param]=p; });
  let html='<div class="row g-3">';
  Object.entries(GROUPS).forEach(function([grp, keys]){
    const items=keys.map(function(k){ return PARAMS[k]; }).filter(Boolean);
    if(!items.length) return;
    html+='<div class="col-12 col-lg-6"><div class="param-card">';
    html+='<div class="param-head"><h5><i class="bi bi-'+(grp==='Application'?'app-indicator':grp==='Securite'?'shield-lock':'bell')+' me-2"></i>'+esc(grp)+'</h5><p>'+items.length+' parametre(s)</p></div>';
    items.forEach(function(p){
      const ti=paramTypeInfo(p.type_param);
      html+='<div class="param-row">'
        +'<div class="param-icon" style="background:'+esc(ti.color)+'22"><i class="bi '+esc(ti.icon)+'" style="color:'+esc(ti.color)+'"></i></div>'
        +'<div class="param-label"><div class="pl">'+esc(p.nom_param.replace(/_/g,' '))+'</div><div class="pd">'+esc(p.description||'')+'</div></div>'
        +'<span class="param-type '+typeClass(p.type_param)+'">'+esc(ti.label)+'</span>'
        +'<div class="ms-2 text-end">'+valDisplay(p)+'</div>'
        +'<button type="button" class="btn btn-sm btn-outline-primary ms-2 btn-edit-param" data-nom="'+esc(p.nom_param)+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
        +'</div>';
    });
    html+='</div></div>';
  });
  // Autres parametres non groupes
  const listed=Object.values(GROUPS).flat();
  const others=data.filter(function(p){ return !listed.includes(p.nom_param); });
  if(others.length){
    html+='<div class="col-12"><div class="param-card">';
    html+='<div class="param-head"><h5><i class="bi bi-sliders me-2"></i>Autres parametres</h5></div>';
    others.forEach(function(p){
      const ti=paramTypeInfo(p.type_param);
      html+='<div class="param-row">'
        +'<div class="param-icon" style="background:#6c757d22"><i class="bi '+esc(ti.icon)+'" style="color:#6c757d"></i></div>'
        +'<div class="param-label"><div class="pl">'+esc(p.nom_param.replace(/_/g,' '))+'</div><div class="pd">'+esc(p.description||'')+'</div></div>'
        +'<div>'+valDisplay(p)+'</div>'
        +'<button type="button" class="btn btn-sm btn-outline-primary ms-2 btn-edit-param" data-nom="'+esc(p.nom_param)+'" title="Modifier"><i class="bi bi-pencil"></i></button>'
        +'</div>';
    });
    html+='</div></div>';
  }
  html+='</div>';
  $('#paramZone').html(html);
}

function load(){
  post({action:'list'}).done(function(r){
    if(!r.success){ $('#paramZone').html('<div class="alert alert-danger">'+esc(r.message||'Erreur')+'</div>'); return; }
    render(r.data||[]);
  }).fail(function(){ $('#paramZone').html('<div class="alert alert-danger">Echec du chargement.</div>'); });
}

$(document).on('click','.btn-edit-param',function(){
  const nom=$(this).data('nom');
  const p=PARAMS[nom]; if(!p) return;
  $('#p_nom').val(p.nom_param);
  $('#p_label_lbl').text(p.nom_param.replace(/_/g,' '));
  $('#p_desc_lbl').text(p.description||'');
  $('#p_input_wrap').html(buildInput(p));
  new bootstrap.Modal('#paramModal').show();
});

$('#paramForm').on('submit',function(e){
  e.preventDefault();
  const nom=$('#p_nom').val();
  const p=PARAMS[nom]; if(!p) return;
  let val;
  if(p.type_param==='boolean'){ val=$('#p_val').is(':checked')?'1':'0'; }
  else { val=($('#p_val').val()||'').trim(); }
  const btn=$('#p_submit'); const h=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  post({action:'update',nom_param:nom,valeur_param:val}).done(function(r){
    btn.prop('disabled',false).html(h);
    if(r.success){
      bootstrap.Modal.getInstance(document.getElementById('paramModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',timer:1400,showConfirmButton:false});
      load();
    } else { Swal.fire({icon:'error',title:'Erreur',text:r.message,confirmButtonColor:'#23408F'}); }
  }).fail(function(){ btn.prop('disabled',false).html(h); Swal.fire({icon:'error',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

load();
</script>