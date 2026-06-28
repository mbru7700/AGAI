<?php
/**
 * Page : Suivi des Fiches de Non-Conformite (FNC)
 * Tableau de suivi complet avec tous les champs requis
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('suivi_nc');
$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$pageTitle = 'Suivi NC';
$active    = 'suivi_nc';
$pageIcon  = 'bi-clipboard-check';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:16px;}
.kpi-card{background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:12px 14px;box-shadow:0 1px 3px rgba(16,30,54,.04);text-align:center;position:relative;overflow:hidden;}
.kpi-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;}
.kc-red::before{background:#D32F2F;} .kc-gold::before{background:#F3C300;}
.kc-blue::before{background:#23408F;} .kc-green::before{background:#1E9C4B;}
.kc-purple::before{background:#7c3aed;}
.kpi-num{font-size:1.6rem;font-weight:800;line-height:1;color:#2C3E50;}
.kpi-lbl{font-size:.72rem;color:#7b8aa0;margin-top:3px;}
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:12px 16px;margin-bottom:12px;}
.tbl-wrap{background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;padding:9px 10px;white-space:nowrap;font-weight:600;}
table.tbl tbody td{padding:8px 10px;border-bottom:1px solid #f1f4f9;vertical-align:top;font-size:.8rem;}
table.tbl tbody tr:hover{background:#fafcff;}
table.tbl tbody tr.en-retard{background:#fff5f5;}
.cat-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:800;}
.cat-critique{background:#fee2e2;color:#991b1b;}
.cat-majeur{background:#fef3c7;color:#92400e;}
.cat-mineur{background:#dbeafe;color:#1e40af;}
.cat-observation{background:#d1fae5;color:#065f46;}
.stat-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:700;}
.stat-1{background:#e8f0fe;color:#23408F;}
.stat-2{background:#fee2e2;color:#991b1b;}
.stat-3{background:#d1fae5;color:#065f46;}
.stat-4{background:#fef3c7;color:#92400e;}
.retard-icon{color:#D32F2F;font-size:.8rem;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-clipboard-check me-2" style="color:#23408F"></i>Suivi des Non-Conformites</h1>
    <div class="sub">Tableau de suivi complet des fiches NC — etat, delais, efficacite, cloture.</div>
  </div>
  <button class="btn btn-sm btn-outline-success" id="btnExcelNC"><i class="bi bi-file-earmark-excel me-1"></i>Exporter Excel</button>
</div>

<!-- KPI -->
<div class="kpi-grid" id="kpiRow">
  <div class="kpi-card kc-blue" title="Total des FNC enregistrees"><div class="kpi-num" id="k_total">-</div><div class="kpi-lbl">Total FNC</div></div>
  <div class="kpi-card kc-gold" title="FNC en statut Ouvert"><div class="kpi-num" id="k_ouv">-</div><div class="kpi-lbl">Ouvertes</div></div>
  <div class="kpi-card kc-red" title="FNC Critiques"><div class="kpi-num" id="k_crit" style="color:#D32F2F">-</div><div class="kpi-lbl">Critiques</div></div>
  <div class="kpi-card kc-gold" title="FNC Majeures"><div class="kpi-num" id="k_maj" style="color:#b58a00">-</div><div class="kpi-lbl">Majeures</div></div>
  <div class="kpi-card kc-blue" title="FNC Mineures"><div class="kpi-num" id="k_min">-</div><div class="kpi-lbl">Mineures</div></div>
  <div class="kpi-card kc-green" title="FNC Fermees"><div class="kpi-num" id="k_ferm" style="color:#1E9C4B">-</div><div class="kpi-lbl">Fermees</div></div>
  <div class="kpi-card kc-red" title="FNC en retard (date reponse exigee depassee)"><div class="kpi-num" id="k_retard" style="color:#D32F2F">-</div><div class="kpi-lbl">En retard</div></div>
</div>

<!-- Filtres -->
<div class="filter-bar">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">N Audit</div><select id="fAudit" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-md-2"><div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Categorie</div>
      <select id="fCat" class="form-select form-select-sm">
        <option value="">Toutes</option><option value="critique">Critique</option>
        <option value="majeur">Majeur</option><option value="mineur">Mineur</option>
        <option value="observation">Observation</option>
      </select>
    </div>
    <div class="col-md-2"><div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Statut</div>
      <select id="fStatut" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="4">Ouvert</option><option value="1">Accepte non verifie</option>
        <option value="2">Rejete</option><option value="3">Ferme</option>
      </select>
    </div>
    <div class="col-md-3"><div style="font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px">Recherche</div>
      <input type="text" id="fSearch" class="form-control form-control-sm" placeholder="N FNC, Operateur...">
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" id="btnResetFiltres" style="font-size:.78rem;flex:1"><i class="bi bi-x-lg me-1"></i>Reset</button>
    </div>
  </div>
</div>

<!-- Tableau de suivi -->
<div class="tbl-wrap" style="overflow-x:auto">
  <table class="tbl" style="min-width:1600px">
    <thead><tr>
      <th>#</th>
      <th>N FNC</th>
      <th>N Audit</th>
      <th>Source</th>
      <th>Date audit</th>
      <th>Date emiss. FNC</th>
      <th>Date transm. rapport</th>
      <th>Delai transm.</th>
      <th>Operateur</th>
      <th>Activite oper.</th>
      <th>Domaine</th>
      <th>Lieu</th>
      <th>Sous-domaine</th>
      <th>Referentiel</th>
      <th>Libelle / Etat</th>
      <th>Categorie</th>
      <th>Ref. Regl.</th>
      <th>Date rep. exigee</th>
      <th>Date lim. conformite</th>
      <th>Agent ANAC</th>
      <th>Statut</th>
      <th>Cloture</th>
      <th>Actions</th>
    </tr></thead>
    <tbody id="tbodySuivi">
      <tr><td colspan="23" style="padding:30px;text-align:center;color:#9aa7bd"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>
<div id="pagiBox" style="display:flex;gap:4px;justify-content:center;margin-top:12px;flex-wrap:wrap"></div>

<!-- MODALE SUIVI / MISE A JOUR -->
<div class="modal fade" id="modalSuivi" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-pencil-square me-2" style="color:#F3C300"></i>Mise a jour suivi &mdash; <span id="suiviNumFnc">-</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="suivi_idfnc">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Statut <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" id="suivi_statut">
              <option value="4">Ouvert</option>
              <option value="1">Accepte mais non verifie</option>
              <option value="2">Rejetee</option>
              <option value="3">Ferme</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Date effective de cloture</label>
            <input type="date" class="form-control form-control-sm" id="suivi_cloture">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Statut delais efficacite</label>
            <select class="form-select form-select-sm" id="suivi_delais_eff">
              <option value="">-- Choisir --</option>
              <option value="D">Depasse (D)</option>
              <option value="ND">Non Depasse (ND)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.82rem">Efficacite de mise en conformite</label>
            <input type="text" class="form-control form-control-sm" id="suivi_efficacite" placeholder="Description efficacite">
          </div>
          <div class="col-12">
            <label class="form-label fw-bold" style="font-size:.82rem">Preuve de suivi et verification</label>
            <textarea class="form-control form-control-sm" id="suivi_preuve" rows="2" placeholder="Decrire les preuves..."></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-bold" style="font-size:.82rem">Observations / Courriers / Relance</label>
            <textarea class="form-control form-control-sm" id="suivi_obs" rows="3" placeholder="Courriers, relances, observations..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="btnSaveSuivi"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF  = '<?php echo Security::escape($csrf); ?>';
const API   = AGAI_BASE + '/api/nonconformites';
const IS_CI = <?php echo $isCI ? 'true' : 'false'; ?>;
let ALL_FNC = [];

function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF},d), null, 'json'); }
function esc(s){ const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
function fmtDate(s){ if(!s||s==='0000-00-00'||!s) return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

const CATEG_CLS = {critique:'cat-critique',majeur:'cat-majeur',mineur:'cat-mineur',observation:'cat-observation'};
const STAT_CLS  = {1:'stat-1',2:'stat-2',3:'stat-3',4:'stat-4'};
const STAT_LBL  = {1:'Accepte non verifie',2:'Rejete',3:'Ferme',4:'Ouvert'};
const TYPE_LBL  = {audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};

function loadStats(){
  apiPost({action:'stats'}).done(function(res){
    if(!res.success) return;
    $('#k_total').text(res.total||0); $('#k_ouv').text(res.ouvertes||0);
    $('#k_crit').text(res.critiques||0); $('#k_maj').text(res.majeures||0);
    $('#k_min').text(res.mineures||0); $('#k_ferm').text(res.fermees||0);
    $('#k_retard').text(res.en_retard||0);
  });
}

$('#fAudit').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous les audits'});

function loadList(){
  apiPost({action:'list',f_audit:$('#fAudit').val()||0,f_statut:$('#fStatut').val()||'',f_categorie:$('#fCat').val()||''}).done(function(res){
    if(!res.success){ $('#tbodySuivi').html('<tr><td colspan="23" style="padding:20px;text-align:center;color:#D32F2F">Erreur</td></tr>'); return; }
    ALL_FNC = res.data||[];
    fillAuditFilter();
    renderTable(getFiltered());
  });
}

function fillAuditFilter(){
  const seen={}, cur=$('#fAudit').val();
  let opts='<option value="">Tous les audits</option>';
  ALL_FNC.forEach(function(f){
    if(!seen[f.idaudit]){ seen[f.idaudit]=1; opts+='<option value="'+f.idaudit+'">'+esc(f.num_audit)+'</option>'; }
  });
  $('#fAudit').html(opts);
  if(cur) $('#fAudit').val(cur);
  $('#fAudit').trigger('change.select2');
}

function getFiltered(){
  const fA=$('#fAudit').val(), fC=$('#fCat').val(), fS=$('#fStatut').val(), fQ=($('#fSearch').val()||'').toLowerCase().trim();
  return ALL_FNC.filter(function(f){
    if(fA && String(f.idaudit)!==String(fA)) return false;
    if(fC && f.categorie!==fC) return false;
    if(fS && String(f.statut)!==String(fS)) return false;
    if(fQ && !((f.num_fnc||'').toLowerCase().includes(fQ)||(f.nomorga||'').toLowerCase().includes(fQ)||(f.num_audit||'').toLowerCase().includes(fQ))) return false;
    return true;
  });
}

function renderTable(list){
  if(!list.length){
    $('#tbodySuivi').html('<tr><td colspan="23" style="padding:30px;text-align:center;color:#9aa7bd"><i class="bi bi-inbox me-2"></i>Aucune FNC trouvee</td></tr>'); return;
  }
  const today=new Date().toISOString().substring(0,10);
  let h='';
  list.forEach(function(f,i){
    const retard=f.date_reponse_exigee&&f.date_reponse_exigee<today&&f.statut<3;
    const src=(TYPE_LBL[f.type_activite]||f.type_activite||'-')+' / '+(f.cadre||'').replace(/_/g,' ');
    h+='<tr'+(retard?' class="en-retard"':'')+'>'
      +'<td style="font-size:.75rem;color:#9aa7bd">'+(i+1)+'</td>'
      +'<td><strong style="color:#D32F2F;font-family:monospace;font-size:.82rem">'+esc(f.num_fnc)+'</strong></td>'
      +'<td><strong style="color:#23408F;font-size:.8rem">'+esc(f.num_audit||'-')+'</strong></td>'
      +'<td style="font-size:.75rem;max-width:120px">'+esc(src)+'</td>'
      +'<td style="font-size:.78rem;white-space:nowrap">'+fmtDate(f.date_previsionnelle)+'</td>'
      +'<td style="font-size:.78rem;white-space:nowrap">'+fmtDate(f.date_emission)+'</td>'
      +'<td style="font-size:.78rem;white-space:nowrap">'+fmtDate(f.date_transmission_rapport)+'</td>'
      +'<td style="font-size:.78rem;text-align:center">'+(f.delais_transmission!==null?f.delais_transmission+'j':'-')+'</td>'
      +'<td style="font-size:.8rem;font-weight:600;max-width:120px">'+esc(f.nomorga||'-')+'</td>'
      +'<td style="font-size:.75rem">'+esc(f.type_activite_operateur||TYPE_LBL[f.type_activite]||'-')+'</td>'
      +'<td style="font-size:.78rem"><span style="color:#23408F;font-weight:700">'+esc(f.nomdomaine||'-')+'</span></td>'
      +'<td style="font-size:.75rem">'+esc(f.ville||f.indicateur_oaci||f.site_inspection||'-')+'</td>'
      +'<td style="font-size:.75rem;max-width:120px">'+esc(f.sousdomaines_noms||'-')+'</td>'
      +'<td style="font-size:.75rem">'+esc(f.reglements_codes||'-')+'</td>'
      +'<td style="font-size:.75rem;max-width:150px"><div style="max-height:50px;overflow:hidden;text-overflow:ellipsis">'+esc((f.libelle||f.description_constatation||'-').substring(0,100))+'</div>'+
        '<span style="font-size:.68rem;color:#9aa7bd">'+esc(f.etat||'-').replace(/_/g,' ')+'</span></td>'
      +'<td><span class="cat-badge '+(CATEG_CLS[f.categorie]||'')+'">'+esc(f.categorie||'-')+'</span></td>'
      +'<td style="font-size:.75rem">'+esc(f.ref_reglement||'-')+'</td>'
      +'<td style="font-size:.78rem;white-space:nowrap'+(retard?' color:#D32F2F;font-weight:700':'')+'">'
        +(retard?'<i class="bi bi-clock-history retard-icon me-1"></i>':'')
        +fmtDate(f.date_reponse_exigee)+'</td>'
      +'<td style="font-size:.78rem;white-space:nowrap">'+fmtDate(f.date_limite_mise_conformite)+'</td>'
      +'<td style="font-size:.75rem">'+esc(f.nom_inspecteur||'-')+'</td>'
      +'<td><span class="stat-badge '+(STAT_CLS[f.statut]||'')+'">'+esc(STAT_LBL[f.statut]||'-')+'</span></td>'
      +'<td style="font-size:.78rem;white-space:nowrap">'+fmtDate(f.date_effective_cloture)+'</td>'
      +'<td style="white-space:nowrap">'
        +(IS_CI?'<button class="btn btn-xs btn-outline-primary me-1 btn-suivi-edit" data-id="'+f.idfnc+'" data-num="'+esc(f.num_fnc)+'" title="Mettre a jour"><i class="bi bi-pencil"></i></button>':'')
        +'<button class="btn btn-xs btn-outline-danger btn-print-suivi" data-id="'+f.idfnc+'" title="Imprimer"><i class="bi bi-printer"></i></button>'
      +'</td>'
      +'</tr>';
  });
  $('#tbodySuivi').html(h);
}

$('#fAudit').on('change',function(){ renderTable(getFiltered()); });
$('#fCat,#fStatut').on('change',function(){ renderTable(getFiltered()); });
$('#fSearch').on('input',function(){ renderTable(getFiltered()); });
$('#btnResetFiltres').on('click',function(){
  $('#fAudit').val('').trigger('change'); $('#fCat,#fStatut').val(''); $('#fSearch').val('');
  renderTable(ALL_FNC);
});

/* ===== MODALE SUIVI ===== */
$(document).on('click','.btn-suivi-edit',function(){
  const id=$(this).data('id'), num=$(this).data('num');
  const f=ALL_FNC.find(function(x){return String(x.idfnc)===String(id);});
  if(!f) return;
  $('#suivi_idfnc').val(id); $('#suiviNumFnc').text(num);
  $('#suivi_statut').val(f.statut||4);
  $('#suivi_cloture').val(f.date_effective_cloture||'');
  $('#suivi_efficacite').val(f.efficacite_mise_conformite||'');
  $('#suivi_delais_eff').val(f.statut_delais_efficacite||'');
  $('#suivi_preuve').val(f.preuve_suivi||'');
  $('#suivi_obs').val(f.observations_courriers||'');
  new bootstrap.Modal('#modalSuivi').show();
});
$('#btnSaveSuivi').on('click',function(){
  const id=$('#suivi_idfnc').val();
  const btn=$(this); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost({
    action:'update_suivi', idfnc:id,
    statut:$('#suivi_statut').val(),
    date_effective_cloture:$('#suivi_cloture').val(),
    efficacite_mise_conformite:$('#suivi_efficacite').val(),
    statut_delais_efficacite:$('#suivi_delais_eff').val(),
    preuve_suivi:$('#suivi_preuve').val(),
    observations_courriers:$('#suivi_obs').val(),
  }).done(function(res){
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer');
    if(res.success){
      bootstrap.Modal.getInstance('#modalSuivi').hide();
      Swal.fire({icon:'success',title:'Suivi mis a jour',timer:1500,showConfirmButton:false});
      loadList(); loadStats();
    } else Swal.fire({icon:'error',text:res.message});
  });
});

/* ===== IMPRESSION depuis liste ===== */
$(document).on('click','.btn-print-suivi',function(){
  const id=$(this).data('id');
  window.open(AGAI_BASE+'/ouverture-nc?print='+id,'_blank');
});

/* ===== INIT ===== */
loadStats(); loadList();
</script>