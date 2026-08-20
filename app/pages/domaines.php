<?php
/**
 * Module : Domaines de surveillance - Donnees de structures
 * Meme design que inspecteurs et operateurs :
 * - KPI + panneau stats masquable/affichable
 * - En-tetes tableau bleu ANAC
 * - Bouton Voir : modale detail (sous-domaines, reglements, habilitations/inspecteurs)
 * - CRUD inchange (modale creation/edition)
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('domaines');
$csrf      = Security::generateCSRF();
$pageTitle = 'Domaines';
$active    = 'domaines';
$pageIcon  = 'bi-grid-3x3-gap';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00;} .ic-purple{background:rgba(90,24,154,.10);color:#5a189a;}
.ic-dark{background:rgba(44,62,80,.09);color:#2C3E50;}
.stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
/* Tableau */
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border:none;font-weight:600;}
table.tbl tbody td{padding:10px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.86rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
/* Badges */
.b-tag{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap;margin:.1rem;}
.b-blue{background:#e8f0fe;color:#23408F;} .b-green{background:#d1e7dd;color:#0a5c36;}
.b-gold{background:#fff3cd;color:#856404;} .b-muted{background:#f1f4f9;color:#7b8aa0;}
.b-purple{background:#f0e6ff;color:#5a189a;}
.dom-code{font-family:monospace;font-size:.9rem;font-weight:800;color:#23408F;background:#e8f0fe;padding:.15rem .5rem;border-radius:6px;}
/* Detail modale */
.det-card{border:1px solid #eef1f6;border-radius:12px;overflow:hidden;margin-bottom:10px;}
.det-card-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:9px 15px;font-weight:700;font-size:.83rem;display:flex;align-items:center;justify-content:space-between;}
.det-card-body{padding:12px 15px;}
.det-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px 16px;}
.dl{font-size:.67rem;text-transform:uppercase;color:#7b8aa0;font-weight:700;letter-spacing:.04em;margin-bottom:1px;}
.dv{font-size:.88rem;color:#2C3E50;font-weight:600;border-bottom:1px solid #f1f4f9;padding-bottom:3px;}
.item-row{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:8px;border:1px solid #eef1f6;margin-bottom:4px;font-size:.84rem;}
.kpi-info{font-size:.72rem;color:#b0bccd;cursor:help;margin-left:2px;vertical-align:middle;}
.kpi-info:hover{color:#23408F;}
.kpi-note{background:#eef3fb;border:1px solid #d5e1f5;border-radius:8px;padding:8px 12px;font-size:.8rem;color:#3a4a63;margin-bottom:10px;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-grid-3x3-gap me-2" style="color:var(--anac-primary)"></i>Domaines de surveillance</h1>
    <div class="sub">Domaines utilises dans les habilitations, audits et reglements OACI.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau domaine</button>
</div>

<!-- Toggle stats -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsLbl">Afficher les statistiques</span>
  </button>
</div>

<!-- Panneau stats masquable -->
<div id="statsPanel" class="mb-3" style="display:none">
  <div class="row g-3">
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Domaines</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-diagram-2-fill"></i></div><div><div class="stat-num" id="st_sous">0</div><div class="stat-lbl">Sous-domaines</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-gold"><i class="bi bi-journal-text"></i></div><div><div class="stat-num" id="st_regs">0</div><div class="stat-lbl">Reglements</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-hab" style="cursor:pointer"><div class="stat-ic ic-purple"><i class="bi bi-shield-check"></i></div><div><div class="stat-num" id="st_habs">0</div><div class="stat-lbl">Habilitations actives <i class="bi bi-info-circle-fill kpi-info" title="Une habilitation = un inspecteur habilite sur UN domaine. Un inspecteur peut cumuler plusieurs habilitations (une par domaine). Ce chiffre compte donc les couples inspecteur x domaine, cliquez pour le detail."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-aud" style="cursor:pointer"><div class="stat-ic ic-dark"><i class="bi bi-clipboard-check"></i></div><div><div class="stat-num" id="st_audits">0</div><div class="stat-lbl">Domaines audites <i class="bi bi-info-circle-fill kpi-info" title="Nombre de domaines distincts sur lesquels au moins un audit a ete mene. Cliquez pour voir chaque domaine et son nombre d'audits."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-insp" style="cursor:pointer"><div class="stat-ic ic-blue"><i class="bi bi-person-badge"></i></div><div><div class="stat-num" id="st_inspecteurs">0</div><div class="stat-lbl">Inspecteurs habilites <i class="bi bi-info-circle-fill kpi-info" title="Nombre d'inspecteurs DISTINCTS ayant au moins une habilitation. Un inspecteur habilite sur plusieurs domaines n'est compte qu'une fois ici (contrairement aux habilitations actives). Cliquez pour le detail."></i></div></div></div></div>
  </div>
</div>

<!-- Filtre -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-8">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Rechercher un domaine</label>
      <select id="filterDom" style="width:100%"></select>
    </div>
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Utilisation</label>
      <select id="filterUsage" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="1">Avec habilitations</option>
        <option value="2">Avec reglements</option>
        <option value="3">Non utilise</option>
      </select>
    </div>
  </div>
  <div class="mt-2 small text-muted" id="resCount"></div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead>
      <tr>
        <th style="width:14%">Code</th>
        <th style="width:30%">Libelle</th>
        <th style="width:12%">Sous-dom.</th>
        <th style="width:12%">Reglements</th>
        <th style="width:12%">Habilitations</th>
        <th style="width:8%">Audits</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="body">
      <tr><td colspan="7" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- MODALE : Nouveau / Edition -->
<div class="modal fade" id="domModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="domForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="domModalTitle">Nouveau domaine</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="d_id">
        <div class="mb-3">
          <label class="form-label fw-bold">Code du domaine <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="d_nom" maxlength="10" placeholder="ex : OPS, AGA, PEL" style="text-transform:uppercase;font-weight:700;letter-spacing:.05em" required>
          <div class="form-text" id="d_dup" style="display:none">
            <div style="background:#fff8e6;border:1px solid #f3d98a;border-radius:8px;padding:8px 12px;margin-top:6px">
              <div style="color:#8a6d00;font-weight:600;font-size:.82rem"><i class="bi bi-exclamation-triangle-fill me-1"></i><span id="d_dup_msg">Un domaine portant ce code existe deja.</span></div>
              <div style="margin-top:6px">
                <button type="button" class="btn btn-sm btn-warning" id="d_recup"><i class="bi bi-box-arrow-in-down me-1"></i>Recuperer ce domaine</button>
                <span class="text-muted" style="font-size:.75rem;margin-left:6px">Le libelle sera pre-rempli ; vous pourrez le modifier avant de valider.</span>
              </div>
            </div>
          </div>
          <div class="form-text">Code court, unique (ex : OPS, AGA, PEL, AVSEC).</div>
        </div>
        <div class="mb-2">
          <label class="form-label">Libelle du domaine <span class="text-muted small">(facultatif)</span></label>
          <input type="text" class="form-control" id="d_libel" maxlength="255" placeholder="ex : Exploitation technique des aeronefs">
          <div class="form-text">Description complete. Si vide, le code est utilise.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="domSubmit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE : detail habilitations / audites / inspecteurs (KPI) -->
<div class="modal fade" id="kpiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-list-check me-2" style="color:#23408F"></i><span id="kpiModalTitle"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="kpiModalBody">
        <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : Voir detail domaine -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:86vw">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-grid-3x3-gap me-2" style="color:#23408F"></i><span id="viewTitle"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="viewBody">
        <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/domaines';
let ROWS = [];
function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API,data,null,'json'); }
function fmtDate(s){ if(!s||String(s).substring(0,10)==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }
function nbBadge(n,icon,cls){
  const c=Number(n)||0;
  return '<span class="b-tag '+(c>0?cls:'b-muted')+'" title="'+c+'"><i class="bi bi-'+icon+' me-1"></i>'+c+'</span>';
}

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  try{localStorage.setItem('agai_stats_domaines',show?'1':'0');}catch(e){}
  if(show) loadStats();
}
$('#btnToggleStats').on('click',function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== STATS ===== */
function loadStats(){
  apiPost({action:'stats'}).done(res => {
    if(!res.success||!res.stats) return;
    const s=res.stats;
    $('#st_total').text(s.total||0);     $('#st_sous').text(s.sous||0);
    $('#st_regs').text(s.regs||0);       $('#st_habs').text(s.habs||0);
    $('#st_audits').text(s.audits||0);   $('#st_inspecteurs').text(s.inspecteurs||0);
  });
}

/* ===== LISTE / FILTRE / RENDU ===== */
function rowHtml(d){
  const used=(Number(d.nb_sd)+Number(d.nb_reg)+Number(d.nb_hab))>0;
  const lib=(d.libel_domaine||'').trim();
  return '<tr>'
    +'<td><span class="dom-code">'+esc(d.nomdomaine)+'</span></td>'
    +'<td style="color:#2C3E50">'+(lib&&lib!==d.nomdomaine?esc(lib):'<span class="text-muted">-</span>')+'</td>'
    +'<td>'+nbBadge(d.nb_sd,'diagram-2','b-green')+'</td>'
    +'<td>'+nbBadge(d.nb_reg,'journal-text','b-gold')+'</td>'
    +'<td>'+nbBadge(d.nb_hab,'shield-check','b-purple')+'</td>'
    +'<td>'+nbBadge(d.nb_aud,'clipboard-check','b-blue')+'</td>'
    +'<td style="text-align:right;white-space:nowrap">'
    +'<button class="btn btn-xs btn-outline-info me-1 act-view" data-id="'+esc(d.iddomaine)+'" style="padding:3px 7px" title="Voir le detail"><i class="bi bi-eye"></i></button>'
    +'<button class="btn btn-xs btn-outline-primary me-1 act-edit" data-id="'+esc(d.iddomaine)+'" style="padding:3px 7px" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +'<button class="btn btn-xs btn-outline-danger act-del" data-id="'+esc(d.iddomaine)+'" data-lib="'+esc(d.nomdomaine)+'" data-used="'+(used?1:0)+'" style="padding:3px 7px" title="Supprimer"><i class="bi bi-trash"></i></button>'
    +'</td></tr>';
}
function getFiltered(){
  const sel=$('#filterDom').val();
  const usage=$('#filterUsage').val();
  return ROWS.filter(d => {
    if(sel&&String(d.iddomaine)!==String(sel)) return false;
    if(usage==='1'&&Number(d.nb_hab)===0) return false;
    if(usage==='2'&&Number(d.nb_reg)===0) return false;
    if(usage==='3'&&(Number(d.nb_sd)+Number(d.nb_reg)+Number(d.nb_hab))>0) return false;
    return true;
  });
}
function render(){
  const list=getFiltered(); const tb=$('#body');
  if(!list.length){ tb.html('<tr><td colspan="7" class="empty"><i class="bi bi-inbox me-2"></i>Aucun domaine.</td></tr>'); }
  else { tb.html(list.map(rowHtml).join('')); }
  $('#resCount').html('<i class="bi bi-grid-3x3-gap me-1"></i>'+list.length+' domaine(s) affiches sur '+ROWS.length);
}
function fillFilter(){
  const sel=$('#filterDom'), cur=sel.val();
  let opts='<option value="">Tous les domaines</option>';
  ROWS.forEach(d => { opts+='<option value="'+esc(d.iddomaine)+'">'+esc(d.nomdomaine)+(d.libel_domaine&&d.libel_domaine.trim()&&d.libel_domaine.trim()!==d.nomdomaine?' - '+esc(d.libel_domaine.substring(0,50)):'')+'</option>'; });
  sel.html(opts);
  if(cur&&ROWS.some(d=>String(d.iddomaine)===String(cur))) sel.val(cur);
  if(sel.hasClass('select2-hidden-accessible')) sel.trigger('change.select2');
}
function loadList(){
  apiPost({action:'list'}).done(res => {
    if(!res.success){ $('#body').html('<tr><td colspan="7" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS=res.data||[]; fillFilter(); render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="7" class="empty">Echec.</td></tr>'); });
}
$('#filterDom').select2({theme:'bootstrap-5',placeholder:'Tous les domaines',allowClear:true,width:'100%'});
$('#filterDom,#filterUsage').on('change',render);

/* ===== MODALE VOIR DETAIL ===== */
/* ===== MODALES DETAIL DES KPI (habilitations / audites / inspecteurs) ===== */
let SYNTHESE=null;
function withSynthese(cb){
  if(SYNTHESE){ cb(SYNTHESE); return; }
  apiPost({action:'synthese'}).done(res => {
    if(!res.success){ $('#kpiModalBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>'); return; }
    SYNTHESE=res; cb(res);
  }).fail(()=>{ $('#kpiModalBody').html('<div class="alert alert-danger">Echec de chargement.</div>'); });
}
function habBadge(dexp){
  const exp=dexp?new Date(dexp):null;
  const days=exp?Math.round((exp-new Date())/86400000):null;
  if(days===null) return '<span class="b-tag b-muted">Sans date</span>';
  if(days<0) return '<span class="b-tag" style="background:#f8d7da;color:#842029">Expiree</span>';
  if(days<=90) return '<span class="b-tag" style="background:#fff3cd;color:#856404">'+days+' j</span>';
  return '<span class="b-tag b-green">Valide</span>';
}
function openKpi(kind){
  $('#kpiModalBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#kpiModal').show();
  withSynthese(function(s){
    let h='';
    if(kind==='hab'){
      $('#kpiModalTitle').text('Habilitations actives par domaine');
      const list=s.habilitations||[];
      if(!list.length){ h='<div class="text-center text-muted py-4">Aucune habilitation.</div>'; }
      else {
        const nbInsp=new Set(list.map(x=>x.trigr_inspecteur+'|'+x.nominspecteur)).size;
        h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Chaque ligne est une habilitation (un inspecteur sur un domaine). Ces <b>'+list.length+'</b> habilitations concernent <b>'+nbInsp+'</b> inspecteur(s) distinct(s) : un inspecteur habilite sur plusieurs domaines apparait sur plusieurs lignes.</div>';
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem"><thead><tr style="background:#f5f7fa">'
         +'<th>Domaine</th><th>Inspecteur</th><th>N habilitation</th><th>Periode</th><th class="text-center">Etat</th></tr></thead><tbody>';
        list.forEach(x=>{
          h+='<tr><td><span class="dom-code">'+esc(x.nomdomaine||'')+'</span></td>'
           +'<td><span style="font-weight:600">'+esc(((x.preninspect||'')+' '+(x.nominspecteur||'')).trim())+'</span> <span class="text-muted small">('+esc(x.trigr_inspecteur||'')+')</span></td>'
           +'<td>'+esc(x.numero_habilitation||'-')+'</td>'
           +'<td class="text-muted small">'+fmtDate(x.date_habilitation)+' au '+fmtDate(x.date_expiration)+'</td>'
           +'<td class="text-center">'+habBadge(x.date_expiration)+'</td></tr>';
        });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="5" style="font-weight:800;color:#23408F">Total : '+list.length+' habilitation(s)</td></tr></tfoot></table>';
      }
    } else if(kind==='aud'){
      $('#kpiModalTitle').text('Domaines audites');
      const list=s.domaines_audites||[];
      if(!list.length){ h='<div class="text-center text-muted py-4">Aucun domaine audite.</div>'; }
      else {
        h='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.84rem"><thead><tr style="background:#f5f7fa">'
         +'<th>Domaine</th><th>Libelle</th><th class="text-center">Nb audits</th></tr></thead><tbody>';
        list.forEach(x=>{
          h+='<tr><td><span class="dom-code">'+esc(x.nomdomaine||'')+'</span></td>'
           +'<td>'+esc((x.libel_domaine||'').trim()||'-')+'</td>'
           +'<td class="text-center"><span class="b-tag b-blue">'+x.nb_aud+'</span></td></tr>';
        });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="3" style="font-weight:800;color:#23408F">Total : '+list.length+' domaine(s) audite(s)</td></tr></tfoot></table>';
      }
    } else {
      $('#kpiModalTitle').text('Inspecteurs habilites');
      const list=s.inspecteurs||[];
      if(!list.length){ h='<div class="text-center text-muted py-4">Aucun inspecteur habilite.</div>'; }
      else {
        let totalHab=0; list.forEach(x=>totalHab+=Number(x.nb_dom));
        h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Chaque ligne est un inspecteur distinct. La colonne <b>Domaines habilites</b> indique sur combien de domaines il est habilite. Le cumul de cette colonne (<b>'+totalHab+'</b>) correspond au nombre total d\'habilitations actives.</div>';
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.84rem"><thead><tr style="background:#f5f7fa">'
         +'<th>Inspecteur</th><th>Trigramme</th><th class="text-center">Domaines habilites</th><th class="text-center">Prochaine echeance</th></tr></thead><tbody>';
        list.forEach(x=>{
          h+='<tr><td style="font-weight:600">'+esc(((x.preninspect||'')+' '+(x.nominspecteur||'')).trim())+'</td>'
           +'<td><span class="b-tag b-purple">'+esc(x.trigr_inspecteur||'-')+'</span></td>'
           +'<td class="text-center"><span class="b-tag b-blue">'+x.nb_dom+'</span></td>'
           +'<td class="text-center">'+fmtDate(x.prochaine_exp)+' '+habBadge(x.prochaine_exp)+'</td></tr>';
        });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="4" style="font-weight:800;color:#23408F">Total : '+list.length+' inspecteur(s) habilite(s)</td></tr></tfoot></table>';
      }
    }
    $('#kpiModalBody').html(h);
  });
}
$(document).on('click','.kpi-hab',function(){ openKpi('hab'); });
$(document).on('click','.kpi-aud',function(){ openKpi('aud'); });
$(document).on('click','.kpi-insp',function(){ openKpi('insp'); });

$(document).on('click','.act-view',function(){
  const id=$(this).data('id');
  const row=ROWS.find(d=>String(d.iddomaine)===String(id));
  $('#viewTitle').text(row?(row.nomdomaine+(row.libel_domaine&&row.libel_domaine.trim()?' - '+row.libel_domaine.trim().substring(0,60):'')):'...');
  $('#viewBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#viewModal').show();
  apiPost({action:'detail',iddomaine:id}).done(res => {
    if(!res.success){ $('#viewBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>'); return; }
    const d=res.data||{}, sd=res.sous_domaines||[], regs=res.reglements||[], habs=res.habilitations||[], auds=res.audits||[];
    function di(l,v){ return '<div><div class="dl">'+l+'</div><div class="dv">'+(v||'<span style="color:#aab4c0;font-style:italic">-</span>')+'</div></div>'; }
    let html='';
    // En-tete identite
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-grid-3x3-gap me-2"></i>Identification</div><div class="det-card-body"><div class="det-row">';
    html+=di('Code','<span class="dom-code">'+esc(d.nomdomaine||'')+'</span>');
    html+=di('Libelle',esc((d.libel_domaine||'').trim()||(d.nomdomaine||'')));
    html+=di('Sous-domaines','<span class="b-tag b-green">'+sd.length+'</span>');
    html+=di('Reglements','<span class="b-tag b-gold">'+regs.length+'</span>');
    html+=di('Habilitations','<span class="b-tag b-purple">'+habs.length+'</span>');
    html+=di('Audits associes','<span class="b-tag b-blue">'+auds.length+'</span>');
    html+='</div></div></div>';
    // Sous-domaines
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-diagram-2 me-2"></i>Sous-domaines ('+sd.length+')</div><div class="det-card-body">';
    if(!sd.length){ html+='<div class="text-muted small">Aucun sous-domaine pour ce domaine.</div>'; }
    else { sd.forEach(s => { html+='<div class="item-row"><span class="b-tag b-green">'+esc(s.nomsd||s.nom_sousdomaine||'-')+'</span></div>'; }); }
    html+='</div></div>';
    // Reglements
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-journal-text me-2"></i>Reglements ('+regs.length+')</div><div class="det-card-body">';
    if(!regs.length){ html+='<div class="text-muted small">Aucun reglement associe a ce domaine.</div>'; }
    else { regs.forEach(r => { html+='<div class="item-row"><span class="b-tag b-gold" style="font-family:monospace">'+esc(r.code_reglement||'-')+'</span><span style="font-size:.82rem">'+esc(r.libelle_reglement||'')+'</span></div>'; }); }
    html+='</div></div>';
    // Inspecteurs habilites
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-person-badge me-2"></i>Inspecteurs habilites ('+habs.length+')</div><div class="det-card-body">';
    if(!habs.length){ html+='<div class="text-muted small">Aucun inspecteur habilite sur ce domaine.</div>'; }
    else {
      habs.forEach(h => {
        const exp=h.date_expiration?new Date(h.date_expiration):null;
        const days=exp?Math.round((exp-new Date())/86400000):null;
        let badge='';
        if(days===null){badge='<span class="b-tag b-muted">Sans date</span>';}
        else if(days<0){badge='<span class="b-tag" style="background:#f8d7da;color:#842029">Expiree</span>';}
        else if(days<=90){badge='<span class="b-tag" style="background:#fff3cd;color:#856404">'+days+' j</span>';}
        else{badge='<span class="b-tag b-green">'+Math.round(days/30)+' mois</span>';}
        html+='<div class="item-row">'
          +'<span class="b-tag b-purple" style="font-size:.78rem">'+esc(h.trigr_inspecteur||h.trigr||'-')+'</span>'
          +'<span style="font-weight:600">'+esc(((h.preninspect||'')+' '+(h.nominspecteur||'')).trim())+'</span>'
          +'<span class="text-muted small">N '+esc(h.numero_habilitation||'-')+'</span>'
          +'<span class="text-muted small">'+fmtDate(h.date_habilitation)+' au '+fmtDate(h.date_expiration)+'</span>'
          +badge
          +'</div>';
      });
    }
    html+='</div></div>';
    // Audits recents
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-clipboard-check me-2"></i>Audits associes ('+auds.length+')</div><div class="det-card-body">';
    if(!auds.length){ html+='<div class="text-muted small">Aucun audit associe a ce domaine.</div>'; }
    else {
      const STATUT={1:'Planifie',2:'Reporte',3:'Effectue',4:'Suspendu',6:'Annule',7:'Inopine'};
      auds.slice(0,10).forEach(a => {
        html+='<div class="item-row">'
          +'<span style="font-family:monospace;font-size:.82rem;font-weight:700;color:#23408F">'+esc(a.num_audit||'-')+'</span>'
          +'<span class="b-tag b-blue" style="font-size:.72rem">'+esc(a.type_activite||'')+'</span>'
          +'<span class="text-muted small">'+fmtDate(a.date_previsionnelle)+'</span>'
          +'<span style="font-size:.78rem;font-weight:600">'+esc(STATUT[a.statut]||('-'))+'</span>'
          +'<span class="text-muted small ms-auto">'+esc(a.nomorga||'')+'</span>'
          +'</div>';
      });
      if(auds.length>10) html+='<div class="text-muted small mt-2">... et '+(auds.length-10)+' autres audits.</div>';
    }
    html+='</div></div>';
    $('#viewBody').html(html);
  });
});

/* ===== CRUD ===== */
$('#btnNew').on('click',function(){
  $('#domModalTitle').text('Nouveau domaine'); $('#d_id').val(''); $('#d_nom').val(''); $('#d_libel').val(''); $('#d_dup').hide();
  DUP_FOUND=null; setSubmitMode('create');
  new bootstrap.Modal('#domModal').show();
});
$(document).on('click','.act-edit',function(){
  const id=$(this).data('id');
  apiPost({action:'get',iddomaine:id}).done(res => {
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const d=res.data;
    $('#domModalTitle').text('Modifier le domaine'); $('#d_id').val(d.iddomaine);
    $('#d_nom').val(d.nomdomaine); $('#d_libel').val(d.libel_domaine); $('#d_dup').hide();
    DUP_FOUND=null; setSubmitMode('update');
    new bootstrap.Modal('#domModal').show();
  });
});
let dupTimer=null, DUP_FOUND=null;
function setSubmitMode(mode){
  const btn=$('#domSubmit');
  if(mode==='update'){ btn.html('<i class="bi bi-check2-circle me-1"></i>Valider'); btn.attr('data-mode','update'); }
  else { btn.html('<i class="bi bi-check-lg me-1"></i>Enregistrer'); btn.attr('data-mode','create'); }
}
$('#d_nom').on('input',function(){
  clearTimeout(dupTimer); const nom=$(this).val().trim();
  DUP_FOUND=null;
  if(!nom){ $('#d_dup').hide(); return; }
  if($('#d_id').val()){ $('#d_dup').hide(); return; } // en edition classique, pas de detection
  dupTimer=setTimeout(function(){
    apiPost({action:'check_nom',nomdomaine:nom,iddomaine:0}).done(res=>{
      if(res.success && res.exists){
        DUP_FOUND=res.data;
        $('#d_dup_msg').text(res.dans_agai
          ? 'Ce domaine est deja gere dans AGAI. Vous pouvez le recuperer pour le modifier.'
          : 'Ce domaine existe deja dans le referentiel SIGANAC (autre application). Recuperez-le pour l\'integrer a AGAI.');
        $('#d_dup').show();
      } else { $('#d_dup').hide(); DUP_FOUND=null; setSubmitMode('create'); }
    });
  },350);
});
$('#d_recup').on('click',function(){
  if(!DUP_FOUND) return;
  const d=DUP_FOUND;
  $('#d_id').val(d.iddomaine);
  $('#d_nom').val(d.nomdomaine||'');
  $('#d_libel').val((d.libel_domaine||'').trim());
  $('#domModalTitle').text('Recuperer / modifier le domaine');
  $('#d_dup').hide();
  setSubmitMode('update');
  Swal.fire({icon:'info',title:'Domaine recupere',text:'Verifiez et modifiez le libelle, puis cliquez sur Valider.',timer:2200,showConfirmButton:false,timerProgressBar:true});
});
$('#domForm').on('submit',function(e){
  e.preventDefault();
  const id=$('#d_id').val(), nom=$('#d_nom').val().trim();
  if(!nom){ Swal.fire({icon:'warning',title:'Code requis',text:'Indiquez le code du domaine (ex : OPS, AGA, PEL).',confirmButtonColor:'#23408F'}); return; }
  if(!id && DUP_FOUND){
    Swal.fire({icon:'warning',title:'Domaine existant',text:'Ce domaine existe deja. Cliquez sur "Recuperer ce domaine" pour le modifier, ou changez le code.',confirmButtonColor:'#23408F'});
    return;
  }
  const mode=$('#domSubmit').attr('data-mode')||(id?'update':'create');
  const data={action:(mode==='update'||id)?'update':'create',iddomaine:id,nomdomaine:nom.toUpperCase(),libel_domaine:$('#d_libel').val().trim()};
  const btn=$('#domSubmit'), html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res => {
    btn.prop('disabled',false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('domModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      SYNTHESE=null; loadList(); loadStats();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});
$(document).on('click','.act-del',function(){
  const id=$(this).data('id'), lib=$(this).data('lib'), used=String($(this).data('used'))==='1';
  Swal.fire({
    icon:used?'warning':'question', title:'Supprimer ce domaine ?',
    html:'<b>'+esc(lib)+'</b>'+(used?'<br><br><span style="color:#D32F2F">Ce domaine est utilise (habilitations, reglements, audits). La suppression sera refusee.</span>':''),
    showCancelButton:true,confirmButtonText:'Supprimer',cancelButtonText:'Annuler',
    confirmButtonColor:'#D32F2F',cancelButtonColor:'#6c757d'
  }).then(r => {
    if(!r.isConfirmed) return;
    apiPost({action:'delete',iddomaine:id}).done(res => {
      if(res.success){ Swal.fire({icon:'success',timer:1400,showConfirmButton:false}); SYNTHESE=null; loadList(); loadStats(); }
      else { Swal.fire({icon:'error',title:'Impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ===== DEMARRAGE ===== */
loadStats(); loadList();
(function(){ let v='0'; try{v=localStorage.getItem('agai_stats_domaines')||'0';}catch(e){} if(v==='1') setStatsVisible(true); })();
</script>