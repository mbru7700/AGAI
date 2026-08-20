<?php
/**
 * Module : Reglements - Donnees de structures
 * Design uniforme : KPI masquables, en-tetes bleu ANAC, bouton Voir,
 * filtres Select2, CRUD inchange.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('reglements');
$csrf      = Security::generateCSRF();
$pageTitle = 'Reglements';
$active    = 'reglements';
$pageIcon  = 'bi-journal-text';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00;} .ic-purple{background:rgba(90,24,154,.10);color:#5a189a;}
.ic-dark{background:rgba(44,62,80,.09);color:#2C3E50;} .ic-red{background:rgba(211,47,47,.10);color:#D32F2F;}
.stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:10px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.86rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
.b-tag{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap;margin:.1rem;}
.kpi-info{font-size:.72rem;color:#b0bccd;cursor:help;margin-left:2px;vertical-align:middle;}
.kpi-info:hover{color:#23408F;}
.kpi-note{background:#eef3fb;border:1px solid #d5e1f5;border-radius:8px;padding:8px 12px;font-size:.8rem;color:#3a4a63;margin-bottom:10px;}
.b-blue{background:#e8f0fe;color:#23408F;} .b-green{background:#d1e7dd;color:#0a5c36;}
.b-gold{background:#fff3cd;color:#856404;} .b-muted{background:#f1f4f9;color:#7b8aa0;}
.b-purple{background:#f0e6ff;color:#5a189a;} .b-red{background:#f8d7da;color:#842029;}
.reg-code{font-family:monospace;font-size:.88rem;font-weight:800;color:#23408F;background:#e8f0fe;padding:.15rem .5rem;border-radius:6px;}
.form-section{font-size:.73rem;text-transform:uppercase;letter-spacing:.05em;color:#23408F;font-weight:700;border-bottom:2px solid #23408F;padding-bottom:4px;margin:8px 0 10px;}
.det-card{border:1px solid #eef1f6;border-radius:12px;overflow:hidden;margin-bottom:10px;}
.det-card-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:9px 15px;font-weight:700;font-size:.83rem;}
.det-card-body{padding:12px 15px;}
.det-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px 16px;}
.dl{font-size:.67rem;text-transform:uppercase;color:#7b8aa0;font-weight:700;letter-spacing:.04em;margin-bottom:1px;}
.dv{font-size:.88rem;color:#2C3E50;font-weight:600;border-bottom:1px solid #f1f4f9;padding-bottom:3px;}
.item-row{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:8px;border:1px solid #eef1f6;margin-bottom:4px;font-size:.84rem;flex-wrap:wrap;}
.item-row:hover{background:#fafcff;}
.desc-block{background:#f8fafc;border-left:4px solid #23408F;border-radius:0 8px 8px 0;padding:10px 14px;font-size:.86rem;line-height:1.55;color:#2C3E50;margin-top:6px;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-journal-text me-2" style="color:var(--anac-primary)"></i>Reglements</h1>
    <div class="sub">Textes reglementaires vises dans les audits et habilitations OACI/ANAC.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau reglement</button>
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
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-journal-text"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Reglements</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-dom" style="cursor:pointer"><div class="stat-ic ic-green"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="stat-num" id="st_dom">0</div><div class="stat-lbl">Domaines couverts <i class="bi bi-info-circle-fill kpi-info" title="Nombre de domaines OACI ayant au moins un reglement rattache. Cliquez pour voir chaque domaine et son nombre de reglements."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-util" style="cursor:pointer"><div class="stat-ic ic-gold"><i class="bi bi-clipboard-check-fill"></i></div><div><div class="stat-num" id="st_aud">0</div><div class="stat-lbl">Utilises en audits <i class="bi bi-info-circle-fill kpi-info" title="Nombre de reglements DISTINCTS cites dans au moins un audit. Un reglement cite dans plusieurs audits n'est compte qu'une fois ici. Cliquez pour le detail."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-cite" style="cursor:pointer"><div class="stat-ic ic-purple"><i class="bi bi-shield-check-fill"></i></div><div><div class="stat-num" id="st_cite">0</div><div class="stat-lbl">Citations totales <i class="bi bi-info-circle-fill kpi-info" title="Nombre total de citations de reglements dans les audits. Un reglement cite dans 3 audits compte pour 3 citations : ce chiffre est donc superieur ou egal a 'Utilises en audits'. Cliquez pour le detail."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-desc" style="cursor:pointer"><div class="stat-ic ic-dark"><i class="bi bi-check2-circle"></i></div><div><div class="stat-num" id="st_avec">0</div><div class="stat-lbl">Avec description <i class="bi bi-info-circle-fill kpi-info" title="Nombre de reglements dont le champ description est renseigne. Cliquez pour la liste."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-jamais" style="cursor:pointer"><div class="stat-ic ic-red"><i class="bi bi-journal-x"></i></div><div><div class="stat-num" id="st_jamais">0</div><div class="stat-lbl">Jamais utilises <i class="bi bi-info-circle-fill kpi-info" title="Reglements definis mais jamais cites dans un audit. Utile pour identifier ceux a integrer aux futures activites de supervision. Cliquez pour la liste."></i></div></div></div></div>
  </div>
</div>

<!-- Filtres -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Domaine</label>
      <select id="filterDom" style="width:100%"></select>
    </div>
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Reglement</label>
      <select id="filterReg" style="width:100%"></select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Utilisation</label>
      <select id="filterUsage" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="1">Utilises en audit</option>
        <option value="0">Jamais utilises</option>
        <option value="2">Avec description</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="visibility:hidden">-</label>
      <button class="btn btn-sm btn-outline-secondary w-100" id="btnReset"><i class="bi bi-x-lg me-1"></i>Reset</button>
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
        <th style="width:32%">Libelle</th>
        <th style="width:20%">Domaine</th>
        <th style="width:14%">Audits</th>
        <th style="width:8%">Desc.</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="body">
      <tr><td colspan="6" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- MODALE : Nouveau / Edition -->
<div class="modal fade" id="regModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" id="regForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="regModalTitle">Nouveau reglement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="r_id">
        <div class="form-section">Identification</div>
        <div class="row g-3 mb-2">
          <div class="col-md-5">
            <label class="form-label fw-bold">Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="r_code" maxlength="50" required
                   placeholder="ex : RAG.8.2, OACI-Annexe6">
            <div class="form-text" id="r_dup" style="display:none;color:#D32F2F">
              <i class="bi bi-exclamation-triangle me-1"></i>Ce code existe deja pour ce domaine.
            </div>
          </div>
          <div class="col-md-7">
            <label class="form-label fw-bold">Libelle <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="r_lib" maxlength="255" required
                   placeholder="ex : Reglement sur la navigabilite des aeronefs">
          </div>
          <div class="col-12">
            <label class="form-label fw-bold">Domaine <span class="text-danger">*</span></label>
            <select id="r_dom" style="width:100%"></select>
          </div>
        </div>
        <div class="form-section">Description</div>
        <div class="mb-2">
          <textarea class="form-control" id="r_desc" rows="3" maxlength="2000"
                    placeholder="Description detaillee, references normatives, portee du reglement..."></textarea>
          <div class="form-text">Facultatif. Texte libre.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="regSubmit">
          <i class="bi bi-check-lg me-1"></i>Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE : detail des KPI -->
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

<!-- MODALE : Voir detail -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:84vw">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title">
          <i class="bi bi-journal-text me-2" style="color:#23408F"></i><span id="viewTitle"></span>
        </h5>
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
const API  = AGAI_BASE + '/api/reglements';
let ROWS=[], DOMAINES=[];

function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API,data,null,'json'); }
function fmtDate(s){
  if(!s) return '-';
  const d=String(s).substring(0,10);
  if(d==='0000-00-00'||d==='') return '-';
  const p=d.split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s;
}
function nbBadge(n,icon,cls){
  const c=Number(n)||0;
  return '<span class="b-tag '+(c>0?cls:'b-muted')+'"><i class="bi bi-'+icon+' me-1"></i>'+c+'</span>';
}
function domLabel(d){ return esc(d.nomdomaine)+(d.libel_domaine&&d.libel_domaine.trim()&&d.libel_domaine.trim()!==d.nomdomaine?' - '+esc(d.libel_domaine.trim().substring(0,40)):''); }

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  try{localStorage.setItem('agai_stats_reglements',show?'1':'0');}catch(e){}
  if(show) loadStats();
}
$('#btnToggleStats').on('click',function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== STATS ===== */
function loadStats(){
  apiPost({action:'stats'}).done(res => {
    if(!res.success||!res.stats) return;
    const s=res.stats;
    $('#st_total').text(s.total||0);  $('#st_dom').text(s.dom_couverts||0);
    $('#st_aud').text(s.nb_aud||0);   $('#st_cite').text(s.nb_cite||0);
    $('#st_avec').text(s.avec_desc||0); $('#st_jamais').text(s.jamais||0);
  });
}

/* ===== DOMAINES ===== */
function loadDomaines(){
  return apiPost({action:'domaines'}).done(res => {
    if(!res.success) return;
    DOMAINES=res.data||[];
    // Filtre domaine
    let fopts='<option value="">Tous les domaines</option>';
    DOMAINES.forEach(d=>{ fopts+='<option value="'+esc(d.iddomaine)+'">'+domLabel(d)+'</option>'; });
    const fc=$('#filterDom').val();
    $('#filterDom').html(fopts);
    if(fc) $('#filterDom').val(fc);
    if($('#filterDom').hasClass('select2-hidden-accessible')) $('#filterDom').trigger('change.select2');
    // Select modale
    let mopts='<option value="">-- Choisir un domaine --</option>';
    DOMAINES.forEach(d=>{ mopts+='<option value="'+esc(d.iddomaine)+'">'+domLabel(d)+'</option>'; });
    $('#r_dom').html(mopts);
  });
}

/* ===== LISTE / FILTRE / RENDU ===== */
function rowHtml(r){
  const hasDesc = r.description&&String(r.description).trim().length>0;
  const libTrunc = r.libelle_reglement&&r.libelle_reglement.length>55 ? r.libelle_reglement.substring(0,55)+'...' : r.libelle_reglement||'-';
  return '<tr>'
    +'<td><span class="reg-code">'+esc(r.code_reglement)+'</span></td>'
    +'<td style="color:#2C3E50" title="'+esc(r.libelle_reglement||'')+'">'+esc(libTrunc)+'</td>'
    +'<td>'+(r.nomdomaine?'<span class="b-tag b-blue">'+esc(r.nomdomaine)+'</span>':'<span class="b-tag b-red">Sans domaine</span>')+'</td>'
    +'<td>'+nbBadge(r.nb_aud,'clipboard-check','b-gold')+'</td>'
    +'<td><span class="b-tag '+(hasDesc?'b-green':'b-muted')+'" title="'+(hasDesc?'Description disponible':'Pas de description')+'">'
    +'<i class="bi bi-'+(hasDesc?'check-circle':'dash-circle')+'"></i></span></td>'
    +'<td style="text-align:right;white-space:nowrap">'
    +'<button class="btn btn-xs btn-outline-info me-1 act-view" data-id="'+esc(r.idreglement)+'" style="padding:3px 7px" title="Voir le detail"><i class="bi bi-eye"></i></button>'
    +'<button class="btn btn-xs btn-outline-primary me-1 act-edit" data-id="'+esc(r.idreglement)+'" style="padding:3px 7px" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +'<button class="btn btn-xs btn-outline-danger act-del" data-id="'+esc(r.idreglement)+'" data-lib="'+esc(r.code_reglement)+'" data-used="'+(Number(r.nb_aud)>0?1:0)+'" style="padding:3px 7px" title="Supprimer"><i class="bi bi-trash"></i></button>'
    +'</td></tr>';
}

function getFiltered(){
  const dom=$('#filterDom').val();
  const reg=$('#filterReg').val();
  const usage=$('#filterUsage').val();
  return ROWS.filter(r=>{
    if(dom  && String(r.iddomaine)!==String(dom))  return false;
    if(reg  && String(r.idreglement)!==String(reg)) return false;
    if(usage==='1' && Number(r.nb_aud)===0) return false;
    if(usage==='0' && Number(r.nb_aud)>0)  return false;
    if(usage==='2' && (!r.description||!String(r.description).trim())) return false;
    return true;
  });
}

function render(){
  const list=getFiltered(); const tb=$('#body');
  if(!list.length){ tb.html('<tr><td colspan="6" class="empty"><i class="bi bi-inbox me-2"></i>Aucun reglement.</td></tr>'); }
  else { tb.html(list.map(rowHtml).join('')); }
  $('#resCount').html('<i class="bi bi-journal-text me-1"></i>'+list.length+' reglement(s) affiches sur '+ROWS.length);
}

function fillFilterReg(){
  const dom=$('#filterDom').val();
  const cur=$('#filterReg').val();
  const list=dom?ROWS.filter(r=>String(r.iddomaine)===String(dom)):ROWS;
  let opts='<option value="">Tous les reglements</option>';
  list.forEach(r=>{ opts+='<option value="'+esc(r.idreglement)+'">'+esc(r.code_reglement)+' - '+esc((r.libelle_reglement||'').substring(0,50))+'</option>'; });
  $('#filterReg').html(opts);
  if(cur&&list.some(r=>String(r.idreglement)===String(cur))) $('#filterReg').val(cur);
  if($('#filterReg').hasClass('select2-hidden-accessible')) $('#filterReg').trigger('change.select2');
}

function loadList(){
  apiPost({action:'list'}).done(res=>{
    if(!res.success){ $('#body').html('<tr><td colspan="6" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS=res.data||[]; fillFilterReg(); render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="6" class="empty">Echec.</td></tr>'); });
}

$('#filterDom').select2({theme:'bootstrap-5',placeholder:'Tous les domaines',allowClear:true,width:'100%'});
$('#filterReg').select2({theme:'bootstrap-5',placeholder:'Tous les reglements',allowClear:true,width:'100%'});
$('#filterDom').on('change',function(){ fillFilterReg(); render(); });
$('#filterReg,#filterUsage').on('change',render);
$('#btnReset').on('click',function(){
  $('#filterDom').val('').trigger('change');
  $('#filterReg').val('').trigger('change');
  $('#filterUsage').val('');
  render();
});

/* ===== MODALES DETAIL DES KPI ===== */
let SYNTHESE=null;
function withSynthese(cb){
  if(SYNTHESE){ cb(SYNTHESE); return; }
  apiPost({action:'synthese'}).done(res => {
    if(!res.success){ $('#kpiModalBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>'); return; }
    SYNTHESE=res; cb(res);
  }).fail(()=>{ $('#kpiModalBody').html('<div class="alert alert-danger">Echec de chargement.</div>'); });
}
function openKpi(kind){
  $('#kpiModalBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#kpiModal').show();
  withSynthese(function(s){
    let h='';
    if(kind==='dom'){
      $('#kpiModalTitle').text('Domaines couverts par des reglements');
      const list=s.domaines||[]; let tot=0; list.forEach(x=>tot+=Number(x.nb_reg));
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Chaque domaine OACI ayant au moins un reglement rattache, avec le nombre de reglements. Le total des reglements correspond au KPI "Reglements".</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Aucun domaine couvert.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.84rem"><thead><tr style="background:#f5f7fa"><th>Domaine</th><th>Libelle</th><th class="text-center">Reglements</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td><span class="b-tag b-blue">'+esc(x.nomdomaine||'')+'</span></td><td>'+esc((x.libel_domaine||'').trim()||'-')+'</td><td class="text-center"><span class="b-tag b-green">'+x.nb_reg+'</span></td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="2" style="font-weight:800;color:#23408F">Total : '+list.length+' domaine(s)</td><td class="text-center" style="font-weight:800;color:#23408F">'+tot+'</td></tr></tfoot></table>';
      }
    } else if(kind==='util'){
      $('#kpiModalTitle').text('Reglements utilises en audits');
      const list=s.utilises||[]; let totCite=0; list.forEach(x=>totCite+=Number(x.nb_cite));
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Reglements cites dans au moins un audit. La colonne "Citations" indique combien de fois chacun a ete cite ; leur cumul (<b>'+totCite+'</b>) correspond au KPI "Citations totales", tandis que le nombre de lignes correspond au KPI "Utilises en audits".</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Aucun reglement utilise.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem"><thead><tr style="background:#f5f7fa"><th>Code</th><th>Libelle</th><th>Domaine</th><th class="text-center">Citations</th><th class="text-center">Audits</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td><span class="b-tag b-purple">'+esc(x.code_reglement||'')+'</span></td><td style="max-width:260px">'+esc((x.libelle_reglement||'').trim()||'-')+'</td><td>'+esc(x.nomdomaine||'-')+'</td><td class="text-center"><span class="b-tag b-gold">'+x.nb_cite+'</span></td><td class="text-center">'+x.nb_audits+'</td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="3" style="font-weight:800;color:#23408F">Total : '+list.length+' reglement(s), '+totCite+' citation(s)</td><td class="text-center" style="font-weight:800;color:#23408F">'+totCite+'</td><td></td></tr></tfoot></table>';
      }
    } else if(kind==='cite'){
      $('#kpiModalTitle').text('Citations totales des reglements');
      const list=s.utilises||[]; let totCite=0; list.forEach(x=>totCite+=Number(x.nb_cite));
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Une citation = une occurrence d\'un reglement dans un audit. Le meme reglement peut etre cite dans plusieurs audits (plusieurs citations). Le total ci-dessous est le KPI "Citations totales".</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Aucune citation.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem"><thead><tr style="background:#f5f7fa"><th>Code</th><th>Domaine</th><th class="text-center">Citations</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td><span class="b-tag b-purple">'+esc(x.code_reglement||'')+'</span></td><td>'+esc(x.nomdomaine||'-')+'</td><td class="text-center"><span class="b-tag b-gold">'+x.nb_cite+'</span></td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="2" style="font-weight:800;color:#23408F">TOTAL des citations</td><td class="text-center" style="font-weight:800;color:#23408F">'+totCite+'</td></tr></tfoot></table>';
      }
    } else if(kind==='desc'){
      $('#kpiModalTitle').text('Reglements avec description');
      const list=s.avec_desc||[];
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Reglements dont le champ description est renseigne.</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Aucun reglement avec description.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem"><thead><tr style="background:#f5f7fa"><th>Code</th><th>Libelle</th><th>Domaine</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td><span class="b-tag b-blue">'+esc(x.code_reglement||'')+'</span></td><td style="max-width:320px">'+esc((x.libelle_reglement||'').trim()||'-')+'</td><td>'+esc(x.nomdomaine||'-')+'</td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="3" style="font-weight:800;color:#23408F">Total : '+list.length+' reglement(s) documente(s)</td></tr></tfoot></table>';
      }
    } else {
      $('#kpiModalTitle').text('Reglements jamais utilises');
      const list=s.jamais||[];
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Reglements definis mais jamais cites dans un audit. A considerer pour les futures activites de supervision.</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Tous les reglements ont ete utilises.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem"><thead><tr style="background:#f5f7fa"><th>Code</th><th>Libelle</th><th>Domaine</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td><span class="b-tag b-red">'+esc(x.code_reglement||'')+'</span></td><td style="max-width:320px">'+esc((x.libelle_reglement||'').trim()||'-')+'</td><td>'+esc(x.nomdomaine||'-')+'</td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="3" style="font-weight:800;color:#23408F">Total : '+list.length+' reglement(s) jamais utilise(s)</td></tr></tfoot></table>';
      }
    }
    $('#kpiModalBody').html(h);
  });
}
$(document).on('click','.kpi-dom',function(){ openKpi('dom'); });
$(document).on('click','.kpi-util',function(){ openKpi('util'); });
$(document).on('click','.kpi-cite',function(){ openKpi('cite'); });
$(document).on('click','.kpi-desc',function(){ openKpi('desc'); });
$(document).on('click','.kpi-jamais',function(){ openKpi('jamais'); });

/* ===== MODALE VOIR DETAIL ===== */
$(document).on('click','.act-view',function(){
  const id=$(this).data('id');
  const row=ROWS.find(r=>String(r.idreglement)===String(id));
  $('#viewTitle').text(row?(row.code_reglement+' - '+(row.libelle_reglement||'').substring(0,50)):'...');
  $('#viewBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#viewModal').show();
  apiPost({action:'detail',idreglement:id}).done(res=>{
    if(!res.success){ $('#viewBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>'); return; }
    const r=res.data||{}, auds=res.audits||[];

    function di(l,v){ return '<div><div class="dl">'+l+'</div><div class="dv">'+(v||'<span style="color:#aab4c0;font-style:italic">-</span>')+'</div></div>'; }

    let html='';
    // Identification
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-journal-text me-2"></i>Identification</div>'
      +'<div class="det-card-body"><div class="det-row">'
      +di('Code','<span class="reg-code">'+esc(r.code_reglement||'')+'</span>')
      +di('Libelle','<span style="font-weight:700">'+esc(r.libelle_reglement||'')+'</span>')
      +di('Domaine',r.nomdomaine?'<span class="b-tag b-blue">'+esc(r.nomdomaine)+'</span>'+'<span class="text-muted small ms-1">'+esc(r.libel_domaine||'')+'</span>':'<span class="b-tag b-red">Non classe</span>')
      +di('Audits vises','<span class="b-tag '+(auds.length>0?'b-gold':'b-muted')+'">'+auds.length+'</span>')
      +'</div>'
      +(r.description&&String(r.description).trim()
        ?'<div style="margin-top:10px"><div class="dl mb-1">Description</div><div class="desc-block">'+esc(r.description)+'</div></div>'
        :'')
      +'</div></div>';

    // Audits
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-clipboard-check me-2"></i>Audits ou ce reglement est vise ('+auds.length+')</div><div class="det-card-body">';
    if(!auds.length){
      html+='<div class="text-muted small text-center py-2"><i class="bi bi-info-circle me-1"></i>Ce reglement n\'a pas encore ete vise dans un audit.</div>';
    } else {
      const STATUT={1:{t:'Planifie',c:'b-blue'},2:{t:'Reporte',c:'b-gold'},3:{t:'Effectue',c:'b-green'},4:{t:'Suspendu',c:'b-red'},6:{t:'Annule',c:'b-muted'},7:{t:'Inopine',c:'b-purple'}};
      auds.forEach(a=>{
        const s=STATUT[a.statut]||{t:a.statut||'-',c:'b-muted'};
        html+='<div class="item-row">'
          +'<span style="font-family:monospace;font-size:.82rem;font-weight:700;color:#23408F">'+esc(a.num_audit||'-')+'</span>'
          +'<span class="b-tag b-blue" style="font-size:.7rem">'+esc(a.type_activite||'')+'</span>'
          +'<span class="text-muted small">'+fmtDate(a.date_previsionnelle)+'</span>'
          +'<span class="b-tag '+s.c+'" style="font-size:.7rem">'+s.t+'</span>'
          +'<span class="b-tag b-muted" style="font-size:.7rem">'+esc(a.nomorga||'-')+'</span>'
          +'<span class="text-muted small ms-auto" style="font-size:.78rem">'+esc(a.nom_inspecteur||'')+'</span>'
          +'</div>';
      });
    }
    html+='</div></div>';
    $('#viewBody').html(html);
  });
});

/* ===== CRUD ===== */
function initDomSelect(){
  if($('#r_dom').hasClass('select2-hidden-accessible')) $('#r_dom').select2('destroy');
  $('#r_dom').select2({theme:'bootstrap-5',dropdownParent:$('#regModal'),placeholder:'Choisir un domaine...',width:'100%'});
}
$('#btnNew').on('click',function(){
  $('#regModalTitle').text('Nouveau reglement');
  $('#r_id').val(''); $('#r_code').val(''); $('#r_lib').val(''); $('#r_desc').val(''); $('#r_dup').hide();
  initDomSelect(); $('#r_dom').val('').trigger('change');
  new bootstrap.Modal('#regModal').show();
  setTimeout(()=>$('#r_code').focus(),300);
});
$(document).on('click','.act-edit',function(){
  const id=$(this).data('id');
  apiPost({action:'get',idreglement:id}).done(res=>{
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const r=res.data;
    $('#regModalTitle').text('Modifier le reglement');
    $('#r_id').val(r.idreglement); $('#r_code').val(r.code_reglement);
    $('#r_lib').val(r.libelle_reglement); $('#r_desc').val(r.description||''); $('#r_dup').hide();
    initDomSelect(); $('#r_dom').val(String(r.iddomaine)).trigger('change');
    new bootstrap.Modal('#regModal').show();
  });
});
let dupTimer=null;
function checkDup(){
  clearTimeout(dupTimer);
  const code=$('#r_code').val().trim(), dom=$('#r_dom').val();
  if(!code||!dom){ $('#r_dup').hide(); return; }
  dupTimer=setTimeout(function(){
    apiPost({action:'check_code',code_reglement:code,iddomaine:dom,idreglement:$('#r_id').val()||0})
      .done(res=>{ $('#r_dup').toggle(!!(res.success&&res.exists)); });
  },350);
}
$('#r_code').on('input',checkDup); $(document).on('change','#r_dom',checkDup);

$('#regForm').on('submit',function(e){
  e.preventDefault();
  const id=$('#r_id').val(), code=$('#r_code').val().trim(), lib=$('#r_lib').val().trim(), dom=$('#r_dom').val();
  if(!code){ Swal.fire({icon:'warning',title:'Code requis',confirmButtonColor:'#23408F'}); return; }
  if(!lib) { Swal.fire({icon:'warning',title:'Libelle requis',confirmButtonColor:'#23408F'}); return; }
  if(!dom) { Swal.fire({icon:'warning',title:'Domaine requis',text:'Choisissez le domaine auquel appartient ce reglement.',confirmButtonColor:'#23408F'}); return; }
  const data={action:id?'update':'create',idreglement:id,code_reglement:code,libelle_reglement:lib,iddomaine:dom,description:$('#r_desc').val().trim()};
  const btn=$('#regSubmit'),html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res=>{
    btn.prop('disabled',false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('regModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      SYNTHESE=null; loadList(); loadStats();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

$(document).on('click','.act-del',function(){
  const id=$(this).data('id'), lib=$(this).data('lib'), used=String($(this).data('used'))==='1';
  Swal.fire({
    icon:used?'warning':'question', title:'Supprimer ce reglement ?',
    html:'<b>'+esc(lib)+'</b>'+(used?'<br><br><span style="color:#D32F2F">Vise dans des audits. La suppression sera refusee.</span>':''),
    showCancelButton:true,confirmButtonText:'Supprimer',cancelButtonText:'Annuler',
    confirmButtonColor:'#D32F2F',cancelButtonColor:'#6c757d'
  }).then(r=>{
    if(!r.isConfirmed) return;
    apiPost({action:'delete',idreglement:id}).done(res=>{
      if(res.success){ Swal.fire({icon:'success',timer:1400,showConfirmButton:false}); SYNTHESE=null; loadList(); loadStats(); }
      else { Swal.fire({icon:'error',title:'Impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ===== DEMARRAGE ===== */
loadStats();
loadDomaines().always(loadList);
(function(){
  let v='0'; try{v=localStorage.getItem('agai_stats_reglements')||'0';}catch(e){}
  if(v==='1') setStatsVisible(true);
})();
</script>