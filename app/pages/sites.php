<?php
/**
 * Module : Sites d'inspection - Donnees de structures
 * Design uniforme : KPI masquables, en-tetes bleu ANAC, bouton Voir,
 * filtres Select2, CRUD inchange.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('sites');
$csrf      = Security::generateCSRF();
$pageTitle = 'Sites d\'inspection';
$active    = 'sites';
$pageIcon  = 'bi-geo-alt';
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
.oaci-badge{font-family:monospace;font-size:.92rem;font-weight:800;color:#23408F;background:#e8f0fe;padding:.2rem .6rem;border-radius:7px;letter-spacing:.08em;}
.det-card{border:1px solid #eef1f6;border-radius:12px;overflow:hidden;margin-bottom:10px;}
.det-card-head{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:9px 15px;font-weight:700;font-size:.83rem;}
.det-card-body{padding:12px 15px;}
.det-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px 16px;}
.dl{font-size:.67rem;text-transform:uppercase;color:#7b8aa0;font-weight:700;letter-spacing:.04em;margin-bottom:1px;}
.dv{font-size:.88rem;color:#2C3E50;font-weight:600;border-bottom:1px solid #f1f4f9;padding-bottom:3px;}
.item-row{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:8px;border:1px solid #eef1f6;margin-bottom:4px;font-size:.84rem;flex-wrap:wrap;}
.item-row:hover{background:#fafcff;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-geo-alt me-2" style="color:var(--anac-primary)"></i>Sites d'inspection</h1>
    <div class="sub">Sites identifies par leur indicateur OACI utilises dans la planification des audits.</div>
  </div>
  <button class="btn btn-anac" id="btnNew"><i class="bi bi-plus-lg me-2"></i>Nouveau site</button>
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
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-geo-alt-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Sites</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-pays" style="cursor:pointer"><div class="stat-ic ic-green"><i class="bi bi-flag-fill"></i></div><div><div class="stat-num" id="st_pays">0</div><div class="stat-lbl">Pays couverts <i class="bi bi-info-circle-fill kpi-info" title="Nombre de pays distincts ou se trouve au moins un site d'inspection. Cliquez pour voir chaque pays et son nombre de sites."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-avec" style="cursor:pointer"><div class="stat-ic ic-gold"><i class="bi bi-clipboard-check-fill"></i></div><div><div class="stat-num" id="st_aud">0</div><div class="stat-lbl">Sites avec audits <i class="bi bi-info-circle-fill kpi-info" title="Nombre de sites DISTINCTS ayant au moins un audit. Un site audite plusieurs fois n'est compte qu'une fois ici. Cliquez pour le detail."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-planif" style="cursor:pointer"><div class="stat-ic ic-purple"><i class="bi bi-hash"></i></div><div><div class="stat-num" id="st_total_aud">0</div><div class="stat-lbl">Audits planifies <i class="bi bi-info-circle-fill kpi-info" title="Nombre total d'audits rattaches a un site. Un site audite 5 fois compte pour 5 : ce chiffre est donc superieur ou egal a 'Sites avec audits'. Cliquez pour le detail par site."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-villes" style="cursor:pointer"><div class="stat-ic ic-dark"><i class="bi bi-map-fill"></i></div><div><div class="stat-num" id="st_villes">0</div><div class="stat-lbl">Villes distinctes <i class="bi bi-info-circle-fill kpi-info" title="Nombre de villes differentes ou se trouvent les sites d'inspection. Cliquez pour la liste."></i></div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card kpi-sans" style="cursor:pointer"><div class="stat-ic ic-red"><i class="bi bi-geo-alt"></i></div><div><div class="stat-num" id="st_sans">0</div><div class="stat-lbl">Sans audit <i class="bi bi-info-circle-fill kpi-info" title="Sites d'inspection enregistres mais jamais audites. Utile pour identifier ceux a couvrir lors des prochaines activites de supervision. Cliquez pour la liste."></i></div></div></div></div>
  </div>
</div>

<!-- Filtres -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Pays</label>
      <select id="filterPays" style="width:100%"></select>
    </div>
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Site</label>
      <select id="filterSite" style="width:100%"></select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Utilisation</label>
      <select id="filterUsage" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="1">Avec audits</option>
        <option value="0">Sans audit</option>
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
        <th style="width:12%">OACI</th>
        <th style="width:30%">Nom du site</th>
        <th style="width:18%">Ville</th>
        <th style="width:16%">Pays</th>
        <th style="width:12%">Audits</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="body">
      <tr><td colspan="6" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- MODALE : Nouveau / Edition -->
<div class="modal fade" id="siteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="siteForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="siteModalTitle">Nouveau site</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="s_id">
        <div class="mb-3">
          <label class="form-label fw-bold">Indicateur OACI <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="s_oaci" maxlength="10" required
                 placeholder="ex : FOOL, FOOG"
                 style="text-transform:uppercase;font-family:monospace;font-size:1rem;font-weight:700;letter-spacing:.1em">
          <div class="form-text" id="s_dup" style="display:none;color:#D32F2F">
            <i class="bi bi-exclamation-triangle me-1"></i>Cet indicateur OACI existe deja.
          </div>
          <div class="form-text">Code unique 4 a 10 caracteres (ex : FOOL, FOOG, FOOP).</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Nom du site <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="s_nom" maxlength="150" required
                 placeholder="ex : Aeroport Leon-Mba de Libreville">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Ville</label>
            <input type="text" class="form-control" id="s_ville" maxlength="150" placeholder="ex : Libreville">
          </div>
          <div class="col-md-6">
            <label class="form-label">Pays</label>
            <select id="s_pays" style="width:100%"></select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="siteSubmit">
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
          <i class="bi bi-geo-alt me-2" style="color:#23408F"></i><span id="viewTitle"></span>
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
const API  = AGAI_BASE + '/api/sites';
let ROWS=[], PAYS=[];

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

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  try{localStorage.setItem('agai_stats_sites',show?'1':'0');}catch(e){}
  if(show) loadStats();
}
$('#btnToggleStats').on('click',function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== STATS ===== */
function loadStats(){
  apiPost({action:'stats'}).done(res=>{
    if(!res.success||!res.stats) return;
    const s=res.stats;
    $('#st_total').text(s.total||0);     $('#st_pays').text(s.pays_couverts||0);
    $('#st_aud').text(s.sites_avec||0);  $('#st_total_aud').text(s.total_aud||0);
    $('#st_villes').text(s.villes||0);   $('#st_sans').text(s.sans_aud||0);
  });
}

/* ===== PAYS ===== */
function loadPays(){
  return apiPost({action:'pays'}).done(res=>{
    if(!res.success) return;
    PAYS=res.data||[];
    let fopts='<option value="">Tous les pays</option>';
    PAYS.forEach(p=>{ if(p.nompays&&p.nompays.trim()) fopts+='<option value="'+esc(p.idpays)+'">'+esc(p.nompays.trim())+'</option>'; });
    const fc=$('#filterPays').val();
    $('#filterPays').html(fopts); if(fc) $('#filterPays').val(fc);
    if($('#filterPays').hasClass('select2-hidden-accessible')) $('#filterPays').trigger('change.select2');
    let mopts='<option value="">-- Choisir un pays --</option>';
    PAYS.forEach(p=>{ if(p.nompays&&p.nompays.trim()) mopts+='<option value="'+esc(p.idpays)+'">'+esc(p.nompays.trim())+'</option>'; });
    $('#s_pays').html(mopts);
  });
}

/* ===== LISTE / FILTRE / RENDU ===== */
function rowHtml(s){
  const used=Number(s.nb_aud)>0;
  return '<tr>'
    +'<td><span class="oaci-badge">'+esc(s.indicateur_oaci)+'</span></td>'
    +'<td style="font-weight:600;color:#2C3E50">'+esc(s.nomsite)+'</td>'
    +'<td style="font-size:.84rem">'+esc(s.ville||'-')+'</td>'
    +'<td>'+(s.nompays&&String(s.nompays).trim()?'<span class="b-tag b-green"><i class="bi bi-flag me-1"></i>'+esc(s.nompays.trim())+'</span>':'<span class="b-tag b-muted">-</span>')+'</td>'
    +'<td>'+nbBadge(s.nb_aud,'clipboard-check','b-gold')+'</td>'
    +'<td style="text-align:right;white-space:nowrap">'
    +'<button class="btn btn-xs btn-outline-info me-1 act-view" data-id="'+esc(s.idsite)+'" style="padding:3px 7px" title="Voir le detail"><i class="bi bi-eye"></i></button>'
    +'<button class="btn btn-xs btn-outline-primary me-1 act-edit" data-id="'+esc(s.idsite)+'" style="padding:3px 7px" title="Modifier"><i class="bi bi-pencil"></i></button>'
    +'<button class="btn btn-xs btn-outline-danger act-del" data-id="'+esc(s.idsite)+'" data-lib="'+esc(s.indicateur_oaci+' - '+s.nomsite)+'" data-used="'+(used?1:0)+'" style="padding:3px 7px" title="Supprimer"><i class="bi bi-trash"></i></button>'
    +'</td></tr>';
}
function getFiltered(){
  const pays=$('#filterPays').val();
  const site=$('#filterSite').val();
  const usage=$('#filterUsage').val();
  return ROWS.filter(s=>{
    if(pays  && String(s.idpays||'')!==String(pays))   return false;
    if(site  && String(s.idsite)!==String(site))        return false;
    if(usage==='1' && Number(s.nb_aud)===0) return false;
    if(usage==='0' && Number(s.nb_aud)>0)  return false;
    return true;
  });
}
function render(){
  const list=getFiltered(); const tb=$('#body');
  if(!list.length){ tb.html('<tr><td colspan="6" class="empty"><i class="bi bi-inbox me-2"></i>Aucun site.</td></tr>'); }
  else { tb.html(list.map(rowHtml).join('')); }
  $('#resCount').html('<i class="bi bi-geo-alt me-1"></i>'+list.length+' site(s) affiches sur '+ROWS.length);
}
function fillFilters(){
  // Filtre site
  const cur=$('#filterSite').val();
  let opts='<option value="">Tous les sites</option>';
  ROWS.forEach(s=>{ opts+='<option value="'+esc(s.idsite)+'">'+esc(s.indicateur_oaci)+' - '+esc(s.nomsite)+'</option>'; });
  $('#filterSite').html(opts);
  if(cur&&ROWS.some(s=>String(s.idsite)===String(cur))) $('#filterSite').val(cur);
  if($('#filterSite').hasClass('select2-hidden-accessible')) $('#filterSite').trigger('change.select2');
}
function loadList(){
  apiPost({action:'list'}).done(res=>{
    if(!res.success){ $('#body').html('<tr><td colspan="6" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ROWS=res.data||[]; fillFilters(); render();
  }).fail(()=>{ $('#body').html('<tr><td colspan="6" class="empty">Echec.</td></tr>'); });
}
$('#filterPays').select2({theme:'bootstrap-5',placeholder:'Tous les pays',allowClear:true,width:'100%'});
$('#filterSite').select2({theme:'bootstrap-5',placeholder:'Tous les sites',allowClear:true,width:'100%'});
$('#filterPays,#filterSite,#filterUsage').on('change',render);
$('#btnReset').on('click',function(){
  $('#filterPays').val('').trigger('change');
  $('#filterSite').val('').trigger('change');
  $('#filterUsage').val(''); render();
});

/* ===== MODALE VOIR DETAIL ===== */
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
    if(kind==='pays'){
      $('#kpiModalTitle').text('Pays couverts');
      const list=s.pays||[]; let tot=0; list.forEach(x=>tot+=Number(x.nb_sites));
      const sansPays=Number(s.sites_sans_pays||0);
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Chaque pays distinct ou se trouve au moins un site, avec son nombre de sites. Le nombre de lignes correspond au KPI "Pays couverts". Les sites dont le pays n\'est pas renseigne sont regroupes en fin de liste et n\'entrent pas dans le decompte des pays.</div>';
      if(!list.length && !sansPays){ h+='<div class="text-center text-muted py-4">Aucun pays couvert.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.85rem"><thead><tr style="background:#f5f7fa"><th>Pays</th><th class="text-center">Sites</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td style="font-weight:600"><i class="bi bi-flag me-1" style="color:#1E9C4B"></i>'+esc(x.nompays||'-')+'</td><td class="text-center"><span class="b-tag b-green">'+x.nb_sites+'</span></td></tr>'; });
        if(sansPays>0){ h+='<tr><td style="font-style:italic;color:#8a97ab"><i class="bi bi-question-circle me-1"></i>Sans pays renseigne</td><td class="text-center"><span class="b-tag b-muted">'+sansPays+'</span></td></tr>'; }
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td style="font-weight:800;color:#23408F">'+list.length+' pays couvert(s)</td><td class="text-center" style="font-weight:800;color:#23408F">'+(tot+sansPays)+' site(s)</td></tr></tfoot></table>';
      }
    } else if(kind==='avec'){
      $('#kpiModalTitle').text('Sites avec audits');
      const list=s.sites_avec||[]; let totAud=0; list.forEach(x=>totAud+=Number(x.nb_aud));
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Sites ayant au moins un audit. La colonne "Audits" indique combien d\'audits par site ; leur cumul (<b>'+totAud+'</b>) correspond au KPI "Audits planifies", tandis que le nombre de lignes correspond au KPI "Sites avec audits".</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Aucun site audite.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.84rem"><thead><tr style="background:#f5f7fa"><th>OACI</th><th>Site</th><th>Ville</th><th>Pays</th><th class="text-center">Audits</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td><span class="b-tag b-blue">'+esc(x.indicateur_oaci||'')+'</span></td><td style="font-weight:600">'+esc(x.nomsite||'-')+'</td><td>'+esc(x.ville||'-')+'</td><td>'+esc(x.nompays||'-')+'</td><td class="text-center"><span class="b-tag b-gold">'+x.nb_aud+'</span></td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="4" style="font-weight:800;color:#23408F">Total : '+list.length+' site(s), '+totAud+' audit(s)</td><td class="text-center" style="font-weight:800;color:#23408F">'+totAud+'</td></tr></tfoot></table>';
      }
    } else if(kind==='planif'){
      $('#kpiModalTitle').text('Audits planifies par site');
      const list=s.sites_avec||[]; let totAud=0; list.forEach(x=>totAud+=Number(x.nb_aud));
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Un site peut concentrer plusieurs audits. Le total ci-dessous est le KPI "Audits planifies" (superieur ou egal au nombre de sites audites).</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Aucun audit planifie.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.84rem"><thead><tr style="background:#f5f7fa"><th>Site</th><th>Ville</th><th class="text-center">Nb audits</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td style="font-weight:600"><span class="b-tag b-blue">'+esc(x.indicateur_oaci||'')+'</span> '+esc(x.nomsite||'-')+'</td><td>'+esc(x.ville||'-')+'</td><td class="text-center"><span class="b-tag b-purple">'+x.nb_aud+'</span></td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="2" style="font-weight:800;color:#23408F">TOTAL des audits</td><td class="text-center" style="font-weight:800;color:#23408F">'+totAud+'</td></tr></tfoot></table>';
      }
    } else if(kind==='villes'){
      $('#kpiModalTitle').text('Villes distinctes');
      const list=s.villes||[]; let tot=0; list.forEach(x=>tot+=Number(x.nb_sites));
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Chaque ville distincte ou se trouvent des sites d\'inspection, avec son nombre de sites.</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Aucune ville renseignee.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.85rem"><thead><tr style="background:#f5f7fa"><th>Ville</th><th class="text-center">Sites</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td style="font-weight:600"><i class="bi bi-geo-alt me-1" style="color:#23408F"></i>'+esc(x.ville||'-')+'</td><td class="text-center"><span class="b-tag b-blue">'+x.nb_sites+'</span></td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td style="font-weight:800;color:#23408F">Total : '+list.length+' ville(s)</td><td class="text-center" style="font-weight:800;color:#23408F">'+tot+'</td></tr></tfoot></table>';
      }
    } else {
      $('#kpiModalTitle').text('Sites sans audit');
      const list=s.sans_audit||[];
      h='<div class="kpi-note"><i class="bi bi-info-circle me-1"></i>Sites d\'inspection enregistres mais jamais audites. A considerer pour les prochaines activites de supervision.</div>';
      if(!list.length){ h+='<div class="text-center text-muted py-4">Tous les sites ont ete audites.</div>'; }
      else {
        h+='<table class="table table-sm table-hover align-middle mb-0" style="font-size:.84rem"><thead><tr style="background:#f5f7fa"><th>OACI</th><th>Site</th><th>Ville</th><th>Pays</th></tr></thead><tbody>';
        list.forEach(x=>{ h+='<tr><td><span class="b-tag b-red">'+esc(x.indicateur_oaci||'')+'</span></td><td style="font-weight:600">'+esc(x.nomsite||'-')+'</td><td>'+esc(x.ville||'-')+'</td><td>'+esc(x.nompays||'-')+'</td></tr>'; });
        h+='</tbody><tfoot><tr style="border-top:2px solid #23408F"><td colspan="4" style="font-weight:800;color:#23408F">Total : '+list.length+' site(s) sans audit</td></tr></tfoot></table>';
      }
    }
    $('#kpiModalBody').html(h);
  });
}
$(document).on('click','.kpi-pays',function(){ openKpi('pays'); });
$(document).on('click','.kpi-avec',function(){ openKpi('avec'); });
$(document).on('click','.kpi-planif',function(){ openKpi('planif'); });
$(document).on('click','.kpi-villes',function(){ openKpi('villes'); });
$(document).on('click','.kpi-sans',function(){ openKpi('sans'); });

$(document).on('click','.act-view',function(){
  const id=$(this).data('id');
  const row=ROWS.find(s=>String(s.idsite)===String(id));
  $('#viewTitle').text(row?(row.indicateur_oaci+' - '+row.nomsite):'...');
  $('#viewBody').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
  new bootstrap.Modal('#viewModal').show();
  apiPost({action:'detail',idsite:id}).done(res=>{
    if(!res.success){ $('#viewBody').html('<div class="alert alert-danger">'+esc(res.message||'Erreur')+'</div>'); return; }
    const s=res.data||{}, auds=res.audits||[];
    function di(l,v){ return '<div><div class="dl">'+l+'</div><div class="dv">'+(v||'<span style="color:#aab4c0;font-style:italic">-</span>')+'</div></div>'; }
    let html='';
    // Identification
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-geo-alt me-2"></i>Identification du site</div>'
      +'<div class="det-card-body"><div class="det-row">'
      +di('Indicateur OACI','<span class="oaci-badge">'+esc(s.indicateur_oaci||'')+'</span>')
      +di('Nom du site','<span style="font-weight:700">'+esc(s.nomsite||'')+'</span>')
      +di('Ville',esc(s.ville||''))
      +di('Pays',s.nompays&&String(s.nompays).trim()?'<span class="b-tag b-green"><i class="bi bi-flag me-1"></i>'+esc(s.nompays.trim())+'</span>':'')
      +di('Audits associes','<span class="b-tag '+(auds.length>0?'b-gold':'b-muted')+'">'+auds.length+'</span>')
      +'</div></div></div>';
    // Audits
    html+='<div class="det-card"><div class="det-card-head"><i class="bi bi-clipboard-check me-2"></i>Audits realises sur ce site ('+auds.length+')</div><div class="det-card-body">';
    if(!auds.length){
      html+='<div class="text-muted small text-center py-2"><i class="bi bi-info-circle me-1"></i>Aucun audit planifie sur ce site.</div>';
    } else {
      const STATUT={1:{t:'Planifie',c:'b-blue'},2:{t:'Reporte',c:'b-gold'},3:{t:'Effectue',c:'b-green'},4:{t:'Suspendu',c:'b-red'},6:{t:'Annule',c:'b-muted'},7:{t:'Inopine',c:'b-purple'}};
      auds.forEach(a=>{
        const st=STATUT[a.statut]||{t:a.statut||'-',c:'b-muted'};
        html+='<div class="item-row">'
          +'<span style="font-family:monospace;font-size:.82rem;font-weight:700;color:#23408F">'+esc(a.num_audit||'-')+'</span>'
          +'<span class="b-tag b-blue" style="font-size:.7rem">'+esc(a.type_activite||'')+'</span>'
          +'<span class="text-muted small">'+fmtDate(a.date_previsionnelle)+'</span>'
          +'<span class="b-tag '+st.c+'" style="font-size:.7rem">'+st.t+'</span>'
          +'<span class="b-tag b-muted" style="font-size:.7rem">'+esc(a.nomorga||'-')+'</span>'
          +'<span class="text-muted small ms-auto" style="font-size:.78rem;color:#D32F2F;font-weight:600">'+esc(a.responsable||'')+'</span>'
          +'</div>';
      });
    }
    html+='</div></div>';
    $('#viewBody').html(html);
  });
});

/* ===== CRUD ===== */
function initPaysSelect(){
  if($('#s_pays').hasClass('select2-hidden-accessible')) $('#s_pays').select2('destroy');
  $('#s_pays').select2({theme:'bootstrap-5',dropdownParent:$('#siteModal'),placeholder:'Choisir un pays...',allowClear:true,width:'100%'});
}
$('#btnNew').on('click',function(){
  $('#siteModalTitle').text('Nouveau site');
  $('#s_id').val(''); $('#s_oaci').val(''); $('#s_nom').val(''); $('#s_ville').val(''); $('#s_dup').hide();
  initPaysSelect(); $('#s_pays').val('').trigger('change');
  new bootstrap.Modal('#siteModal').show();
  setTimeout(()=>$('#s_oaci').focus(),300);
});
$(document).on('click','.act-edit',function(){
  const id=$(this).data('id');
  apiPost({action:'get',idsite:id}).done(res=>{
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    const s=res.data;
    $('#siteModalTitle').text('Modifier le site');
    $('#s_id').val(s.idsite); $('#s_oaci').val(s.indicateur_oaci);
    $('#s_nom').val(s.nomsite); $('#s_ville').val(s.ville||''); $('#s_dup').hide();
    initPaysSelect();
    // idpays peut etre zerofill (ex: 021) -> chercher la valeur numerique et la valeur paddee
    const paysId = s.idpays ? String(parseInt(s.idpays, 10)) : '';
    const paysOpt = paysId ? ($('#s_pays option').filter(function(){ return parseInt($(this).val(),10)==parseInt(paysId,10); }).first().val()||'') : '';
    $('#s_pays').val(paysOpt||'').trigger('change');
    new bootstrap.Modal('#siteModal').show();
  });
});
let dupTimer=null;
$('#s_oaci').on('input',function(){
  clearTimeout(dupTimer);
  const oaci=$(this).val().trim().toUpperCase();
  $(this).val(oaci);
  if(!oaci){ $('#s_dup').hide(); return; }
  dupTimer=setTimeout(function(){
    apiPost({action:'check_oaci',indicateur_oaci:oaci,idsite:$('#s_id').val()||0})
      .done(res=>{ $('#s_dup').toggle(!!(res.success&&res.exists)); });
  },350);
});
$('#siteForm').on('submit',function(e){
  e.preventDefault();
  const id=$('#s_id').val(), oaci=$('#s_oaci').val().trim().toUpperCase(), nom=$('#s_nom').val().trim();
  if(!oaci){ Swal.fire({icon:'warning',title:'Indicateur OACI requis',confirmButtonColor:'#23408F'}); return; }
  if(!nom) { Swal.fire({icon:'warning',title:'Nom du site requis',confirmButtonColor:'#23408F'}); return; }
  const data={action:id?'update':'create',idsite:id,indicateur_oaci:oaci,nomsite:nom,
              ville:$('#s_ville').val().trim(),idpays:$('#s_pays').val()||null};
  const btn=$('#siteSubmit'),html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost(data).done(res=>{
    btn.prop('disabled',false).html(html);
    if(res.success){
      bootstrap.Modal.getInstance(document.getElementById('siteModal')).hide();
      Swal.fire({icon:'success',title:'Enregistre',text:res.message,timer:1600,showConfirmButton:false,timerProgressBar:true});
      SYNTHESE=null; loadList(); loadStats();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false).html(html); Swal.fire({icon:'error',text:'Echec.',confirmButtonColor:'#23408F'}); });
});
$(document).on('click','.act-del',function(){
  const id=$(this).data('id'), lib=$(this).data('lib'), used=String($(this).data('used'))==='1';
  Swal.fire({
    icon:used?'warning':'question', title:'Supprimer ce site ?',
    html:'<b>'+esc(lib)+'</b>'+(used?'<br><br><span style="color:#D32F2F">Rattache a des audits. La suppression sera refusee.</span>':''),
    showCancelButton:true,confirmButtonText:'Supprimer',cancelButtonText:'Annuler',
    confirmButtonColor:'#D32F2F',cancelButtonColor:'#6c757d'
  }).then(r=>{
    if(!r.isConfirmed) return;
    apiPost({action:'delete',idsite:id}).done(res=>{
      if(res.success){ Swal.fire({icon:'success',timer:1400,showConfirmButton:false}); SYNTHESE=null; loadList(); loadStats(); }
      else { Swal.fire({icon:'error',title:'Impossible',text:res.message,confirmButtonColor:'#23408F'}); }
    });
  });
});

/* ===== DEMARRAGE ===== */
loadStats();
loadPays().always(loadList);
(function(){
  let v='0'; try{v=localStorage.getItem('agai_stats_sites')||'0';}catch(e){}
  if(v==='1') setStatsVisible(true);
})();
</script>