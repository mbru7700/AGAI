<?php
/**
 * Page : Ouverture des Fiches de Non-Conformite (FNC)
 * Module : nonconformites > ouverture_nc
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('ouverture_nc');
$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$uid       = (int)($_SESSION['user_id'] ?? 0);
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$pageTitle = 'Ouverture NC';
$active    = 'ouverture_nc';
$pageIcon  = 'bi-folder-plus';
$banierUrl = ASSETS_URL . '/images/banierenteanac.png';

// Recuperer le nom de l'inspecteur connecte pour le formulaire
$nomInspecteurConnecte = '';
$db = Database::getInstance();
$stInsp = $db->prepare("SELECT CONCAT(COALESCE(preninspect,''),' ',COALESCE(nominspecteur,'')) AS n FROM inspecteur WHERE iduser=? LIMIT 1");
$stInsp->execute([$uid]); $rowInsp = $stInsp->fetch();
if ($rowInsp && trim($rowInsp['n'])) {
    $nomInspecteurConnecte = trim($rowInsp['n']);
} else {
    $nomInspecteurConnecte = trim(($_SESSION['user']['prenom'] ?? '') . ' ' . ($_SESSION['user']['nom'] ?? ''));
}

require_once INCLUDES_PATH . '/layout_head.php';
// TinyMCE
$tinyKey = 'no-api-key';
?>
<script src="https://cdn.tiny.cloud/1/<?php echo $tinyKey; ?>/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<style>
.fnc-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;box-shadow:0 1px 3px rgba(16,30,54,.05);}
.fnc-card-header{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:10px 16px;border-radius:13px 13px 0 0;font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:8px;}
.fnc-card-header i{color:#F3C300;}
.fnc-card-body{padding:16px;}
.field-label{font-size:.78rem;font-weight:700;color:#2C3E50;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;}
.audit-info-box{background:#f0f4ff;border:1px solid #c5d4f5;border-radius:10px;padding:12px 16px;margin-bottom:16px;}
.audit-info-box .ai-num{font-size:1.1rem;font-weight:800;color:#23408F;}
.quota-bar{height:12px;background:#eef1f6;border-radius:6px;overflow:hidden;margin:6px 0;}
.quota-fill{height:100%;border-radius:6px;transition:width .5s;}
.section-divider{background:#23408F;color:#fff;padding:7px 14px;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border-radius:8px;margin:14px 0 10px;display:flex;align-items:center;gap:8px;}
.radio-opt{display:inline-flex;align-items:center;gap:6px;margin-right:14px;padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:all .15s;font-size:.83rem;}
.radio-opt input{display:none;}
.radio-opt.selected{border-color:#23408F;background:#e8f0fe;color:#23408F;font-weight:700;}
.snote{font-size:.76rem;color:#7b8aa0;font-style:italic;}
.categ-card{border:2px solid transparent;border-radius:10px;padding:10px 14px;cursor:pointer;transition:all .15s;text-align:center;font-size:.82rem;}
.categ-card.sel-critique{border-color:#D32F2F;background:#fff5f5;}
.categ-card.sel-majeur{border-color:#b58a00;background:#fffbeb;}
.categ-card.sel-mineur{border-color:#23408F;background:#e8f0fe;}
.categ-card.sel-observation{border-color:#1E9C4B;background:#f0fdf4;}
.num-fnc-display{font-family:monospace;font-size:1.2rem;font-weight:800;color:#23408F;background:#e8f0fe;border-radius:8px;padding:8px 16px;display:inline-block;}
.fnc-bloc{border:1.5px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px;position:relative;background:#fafcff;}
.fnc-bloc-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.fnc-bloc-num{background:#e8f0fe;color:#23408F;border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:800;}
/* SweetAlert au-dessus des modales Bootstrap */
.swal-over-modal{z-index:99999!important;}
.btn-remove-bloc{background:#fee2e2;color:#D32F2F;border:none;border-radius:6px;padding:4px 10px;font-size:.78rem;cursor:pointer;}
.btn-remove-bloc:hover{background:#D32F2F;color:#fff;}
.synthese-legend{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;}
.synthese-item{display:flex;align-items:flex-start;gap:10px;padding:6px 0;border-bottom:1px solid #f0f4ff;}
.synthese-item:last-child{border-bottom:none;}
.synth-badge{padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:800;flex:0 0 auto;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-folder-plus me-2" style="color:#D32F2F"></i>Ouverture des Fiches de Non-Conformite</h1>
    <div class="sub">Creez les FNC pour les audits ayant des criteres non satisfaisants (NCNS).</div>
  </div>
  <button class="btn btn-anac" id="btnNewFnc"><i class="bi bi-plus-lg me-1"></i>Ouvrir une FNC</button>
</div>

<!-- Tableau des FNC en cours -->
<div class="fnc-card">
  <div class="fnc-card-header"><i class="bi bi-list-check"></i>Fiches NC ouvertes</div>
  <div class="fnc-card-body p-0">
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:separate;border-spacing:0">
        <thead><tr style="background:#f5f7fa">
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">N FNC</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">N Audit</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">Operateur</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">Categorie</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">Statut</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">Date emission</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">Reponse exigee</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#5b6b85;font-weight:600;border-bottom:1px solid #eef1f6">Actions</th>
        </tr></thead>
        <tbody id="tbodyFnc">
          <tr><td colspan="8" style="padding:30px;text-align:center;color:#9aa7bd"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ===== MODALE SELECTION AUDIT ===== -->
<div class="modal fade" id="modalSelectAudit" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-search me-2" style="color:#F3C300"></i>Selectionner l'audit</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert" style="background:#e8f0fe;border-left:4px solid #23408F;border-radius:8px;padding:12px 14px;font-size:.84rem">
          <i class="bi bi-info-circle-fill me-2" style="color:#23408F"></i>
          Seuls les audits ayant des <strong>criteres non satisfaisants (NCNS &ge; 1)</strong> et dont toutes les fiches n'ont pas encore ete creees sont affichees.
        </div>
        <div class="mb-3">
          <label class="field-label">Choisir le numero d'audit <span class="text-danger">*</span></label>
          <select id="selectAudit" style="width:100%"><option value="">-- Selectionnez un audit --</option></select>
        </div>
        <div id="auditInfoBox" style="display:none" class="audit-info-box">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="ai-num" id="ai_num">-</div>
              <div style="font-size:.82rem;color:#555" id="ai_orga">-</div>
              <div style="font-size:.82rem;color:#555" id="ai_type">-</div>
            </div>
            <div class="text-end">
              <div style="font-size:.78rem;color:#7b8aa0">NCNS total</div>
              <div style="font-size:1.4rem;font-weight:800;color:#D32F2F" id="ai_ncns">-</div>
            </div>
          </div>
          <div class="mt-2">
            <div class="d-flex justify-content-between" style="font-size:.78rem">
              <span>Fiches crees : <strong id="ai_crees">0</strong></span>
              <span>Restant : <strong style="color:#D32F2F" id="ai_reste">0</strong></span>
            </div>
            <div class="quota-bar">
              <div class="quota-fill" id="ai_quota_fill" style="background:#1E9C4B;width:0%"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="btnContinueAudit" disabled><i class="bi bi-arrow-right-circle me-1"></i>Continuer</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODALE FORMULAIRE FNC ===== -->
<div class="modal fade" id="modalFnc" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#D32F2F,#991b1b)">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-text me-2" style="color:#F3C300"></i>
          Fiche de Non-Conformite &mdash; <span id="fnc_num_display">N/A</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Info quota -->
        <div id="quotaBanner" class="alert mb-3" style="background:#fff8e0;border:1px solid #fde68a;border-left:4px solid #F3C300;border-radius:8px;padding:10px 14px;font-size:.84rem">
          <i class="bi bi-exclamation-circle-fill me-1" style="color:#b58a00"></i>
          Audit <strong id="qb_num">-</strong> &mdash; NCNS : <strong id="qb_ncns" style="color:#D32F2F">-</strong> &mdash;
          Fiches crees : <strong id="qb_crees">0</strong> &mdash; Reste : <strong style="color:#D32F2F" id="qb_reste">-</strong>
        </div>

        <!-- Synthese categories -->
        <div class="synthese-legend">
          <div style="font-size:.78rem;font-weight:700;color:#23408F;text-transform:uppercase;margin-bottom:8px">Synthese des non-conformites &mdash; Regles de categorisation</div>
          <div class="synthese-item">
            <span class="synth-badge" style="background:#fee2e2;color:#991b1b">Critique</span>
            <div style="font-size:.78rem">Reponse &amp; action <strong>immediate</strong>. Date exigee = Date d'emission. Delai = Date d'emission.</div>
          </div>
          <div class="synthese-item">
            <span class="synth-badge" style="background:#fef3c7;color:#92400e">Majeur</span>
            <div style="font-size:.78rem">Delai correction <strong>&le; 03 mois</strong>. Au plus tard 1 mois apres reception rapport, l'operateur fournit un PAC. Date exigee = rapport +30j. Delai = rapport +3 mois.</div>
          </div>
          <div class="synthese-item">
            <span class="synth-badge" style="background:#dbeafe;color:#1e40af">Mineur</span>
            <div style="font-size:.78rem">Delai correction <strong>&le; 06 mois</strong>. Au plus tard 6 mois apres reception rapport, reponse argumentee. Date exigee = rapport +30j. Delai = rapport +6 mois.</div>
          </div>
          <div class="synthese-item">
            <span class="synth-badge" style="background:#d1fae5;color:#065f46">Observation</span>
            <div style="font-size:.78rem"><strong>Aucun delai</strong>. Les champs date sont masques.</div>
          </div>
        </div>

        <!-- Les blocs FNC dynamiques -->
        <div id="fncBlocs"></div>

        <!-- Bouton ajouter -->
        <div class="text-center mt-2" id="btnAddZone">
          <button type="button" class="btn" id="btnAddBloc" style="background:#23408F;color:#fff;font-weight:700;padding:8px 24px">
            <i class="bi bi-plus-circle me-2"></i>Ajouter une fiche NC
          </button>
          <div style="font-size:.76rem;color:#9aa7bd;margin-top:4px" id="addBlocHint"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
        <button type="button" class="btn" id="btnSaveFnc" style="background:#D32F2F;color:#fff;font-weight:700" disabled>
          <i class="bi bi-check-lg me-1"></i>Enregistrer les fiches
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODALE IMPRESSION FNC ===== -->
<div class="modal fade" id="modalPrint" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:90vw">
    <div class="modal-content" style="height:90vh;display:flex;flex-direction:column">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-printer me-2 text-danger"></i><span id="printTitle">FNC</span></h5>
        <div class="ms-auto d-flex gap-2 me-2">
          <button class="btn btn-danger fw-bold" id="btnDoPrint"><i class="bi bi-printer me-1"></i>Imprimer / PDF</button>
        </div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="flex:1;overflow:auto;background:#f5f7fa">
        <div id="printPreview" style="padding:20px"></div>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODALE AJOUT SOUS-DOMAINE ===== -->
<div class="modal fade" id="modalAddSD" tabindex="-1" aria-hidden="true" style="z-index:100060">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-diagram-3 me-2" style="color:#F3C300"></i>Nouveau sous-domaine</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="sd_bloc_idx">
        <input type="hidden" id="sd_dom_id">
        <div class="mb-3">
          <div style="background:#e8f0fe;border-radius:8px;padding:8px 12px;font-size:.83rem;margin-bottom:14px">
            <i class="bi bi-info-circle me-1" style="color:#23408F"></i>
            Domaine : <strong id="sd_dom_nom">-</strong>
          </div>
          <label class="form-label fw-bold" style="font-size:.85rem">Nom(s) du sous-domaine <span class="text-danger">*</span></label>
          <textarea class="form-control" id="sd_nom_input" rows="4"
            placeholder="Saisir un nom par ligne pour en ajouter plusieurs :&#10;Ex: Procedures operationnelles&#10;Formation equipage&#10;Maintenance ligne"></textarea>
          <div style="font-size:.75rem;color:#7b8aa0;margin-top:4px"><i class="bi bi-lightbulb me-1"></i>Saisir un sous-domaine par ligne pour en ajouter plusieurs d'un coup.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm" id="btnSaveSD"
          style="background:#23408F;color:#fff;font-weight:700">
          <i class="bi bi-check-lg me-1"></i>Ajouter
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODALE AJOUT REGLEMENT ===== -->
<div class="modal fade" id="modalAddReg" tabindex="-1" aria-hidden="true" style="z-index:100060">
  <div class="modal-dialog modal-dialog-centered" style="max-width:500px">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-book me-2" style="color:#F3C300"></i>Ajouter un reglement</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="reg_bloc_idx">
        <input type="hidden" id="reg_dom_id">
        <div style="background:#e8f0fe;border-radius:8px;padding:8px 12px;font-size:.83rem;margin-bottom:14px">
          <i class="bi bi-info-circle me-1" style="color:#23408F"></i>
          Domaine selectionne : <strong id="reg_dom_nom">-</strong>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" style="font-size:.85rem">Code / Reference <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="reg_code_input"
            placeholder="Ex: OACI Annexe 6, GSAC, RACAM-OPS...">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" style="font-size:.85rem">Libelle complet <span class="text-danger">*</span></label>
          <textarea class="form-control" id="reg_lib_input" rows="3"
            placeholder="Description complete du reglement applicable..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddRegAndContinue">
          <i class="bi bi-plus-circle me-1"></i>Ajouter et continuer
        </button>
        <button type="button" class="btn btn-sm" id="btnSaveReg"
          style="background:#23408F;color:#fff;font-weight:700">
          <i class="bi bi-check-lg me-1"></i>Ajouter et fermer
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Backdrop supplementaire pour modales imbriquees -->
<div id="sdBackdrop"  class="modal-backdrop fade" style="display:none;z-index:100055"></div>
<div id="regBackdrop" class="modal-backdrop fade" style="display:none;z-index:100055"></div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF   = '<?php echo Security::escape($csrf); ?>';
const API    = AGAI_BASE + '/api/nonconformites';
const BANER  = AGAI_BASE + '/public/images/banierenteanac.png';
const IS_CI  = <?php echo $isCI ? 'true' : 'false'; ?>;
const NOM_INSP_CONN = '<?php echo Security::escape($nomInspecteurConnecte); ?>';
let ALL_AUDITS_EL = [], CURRENT_AUDIT = null;
let DOMAINES_INSP = [], SOUSDOM_INSP = [], REGLEMENTS_AUDIT = [];
let blocCounter = 0, pendingBlocs = [];

function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF}, d), null, 'json'); }
function esc(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

const STATUT_LABELS = {1:'Accepte non verifie',2:'Rejete',3:'Ferme',4:'Ouvert'};
const STATUT_COLORS = {1:'#e8f0fe',2:'#fee2e2',3:'#d1fae5',4:'#fff3cd'};
const STATUT_TC     = {1:'#23408F',2:'#D32F2F',3:'#065f46',4:'#92400e'};
const CATEG_COLORS  = {critique:'#D32F2F',majeur:'#b58a00',mineur:'#23408F',observation:'#1E9C4B'};
const CATEG_BG      = {critique:'#fee2e2',majeur:'#fef3c7',mineur:'#dbeafe',observation:'#d1fae5'};
const TYPE_LABELS   = {audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};

/* ===== INITIALISATION ===== */
function loadAuditsEligibles(){
  apiPost({action:'audits_eligibles'}).done(function(res){
    if(!res.success) return;
    ALL_AUDITS_EL = res.data || [];
    let opts='<option value="">-- Selectionnez un audit --</option>';
    ALL_AUDITS_EL.forEach(function(a){
      opts+='<option value="'+a.idaudit+'">'+esc(a.num_audit)+' | '+esc(a.nomorga||'-')+' | NCNS:'+a.ncns+' (reste:'+a.reste_a_creer+')</option>';
    });
    $('#selectAudit').html(opts).trigger('change.select2');
  });
}
function loadFncList(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success){ $('#tbodyFnc').html('<tr><td colspan="8" style="padding:20px;text-align:center;color:#D32F2F">Erreur chargement</td></tr>'); return; }
    const data=res.data||[];
    if(!data.length){
      $('#tbodyFnc').html('<tr><td colspan="8" style="padding:30px;text-align:center;color:#9aa7bd"><i class="bi bi-inbox me-2"></i>Aucune FNC ouverte</td></tr>'); return;
    }
    let h='';
    data.forEach(function(f){
      const catBg=CATEG_BG[f.categorie]||'#f1f5f9', catTc=CATEG_COLORS[f.categorie]||'#555';
      const stBg=STATUT_COLORS[f.statut]||'#f1f5f9', stTc=STATUT_TC[f.statut]||'#555';
      const retard=f.date_reponse_exigee&&f.date_reponse_exigee<new Date().toISOString().substring(0,10)&&f.statut<3;
      h+='<tr style="border-bottom:1px solid #f1f4f9'+(retard?';background:#fff5f5':'')+'">'
        +'<td style="padding:9px 12px"><strong style="color:#D32F2F;font-family:monospace">'+esc(f.num_fnc)+'</strong></td>'
        +'<td style="padding:9px 12px;font-size:.82rem"><strong style="color:#23408F">'+esc(f.num_audit||'-')+'</strong></td>'
        +'<td style="padding:9px 12px;font-size:.82rem">'+esc(f.nomorga||'-')+'</td>'
        +'<td style="padding:9px 12px"><span style="background:'+catBg+';color:'+catTc+';padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700">'+esc(f.categorie||'-')+'</span></td>'
        +'<td style="padding:9px 12px"><span style="background:'+stBg+';color:'+stTc+';padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700">'+esc(STATUT_LABELS[f.statut]||'-')+'</span>'
          +(retard?'<br><span style="font-size:.68rem;color:#D32F2F"><i class="bi bi-clock me-1"></i>En retard</span>':'')+'</td>'
        +'<td style="padding:9px 12px;font-size:.82rem">'+fmtDate(f.date_emission)+'</td>'
        +'<td style="padding:9px 12px;font-size:.82rem">'+fmtDate(f.date_reponse_exigee)+'</td>'
        +'<td style="padding:9px 12px;white-space:nowrap">'
          +'<button class="btn btn-sm btn-outline-danger me-1 btn-print-fnc" data-id="'+f.idfnc+'" title="Imprimer"><i class="bi bi-printer"></i></button>'
          +(IS_CI?'<button class="btn btn-sm btn-outline-danger btn-del-fnc" data-id="'+f.idfnc+'" data-num="'+esc(f.num_fnc)+'" title="Supprimer"><i class="bi bi-trash"></i></button>':'')
        +'</td>'
        +'</tr>';
    });
    $('#tbodyFnc').html(h);
  });
}

/* ===== MODALE SELECTION AUDIT ===== */
$('#btnNewFnc').on('click',function(){ new bootstrap.Modal('#modalSelectAudit').show(); });
$('#selectAudit').select2({theme:'bootstrap-5',width:'100%',placeholder:'-- Selectionnez un audit --',dropdownParent:$('#modalSelectAudit')});
$('#selectAudit').on('change',function(){
  const id=$(this).val();
  if(!id){ $('#auditInfoBox').hide(); $('#btnContinueAudit').prop('disabled',true); return; }
  const a=ALL_AUDITS_EL.find(function(x){return String(x.idaudit)===String(id);});
  if(!a) return;
  CURRENT_AUDIT = a;
  $('#ai_num').text(a.num_audit); $('#ai_orga').text(a.nomorga||'-');
  $('#ai_type').text((TYPE_LABELS[a.type_activite]||a.type_activite)+' | '+(a.cadre||''));
  $('#ai_ncns').text(a.ncns); $('#ai_crees').text(a.nb_fnc_crees); $('#ai_reste').text(a.reste_a_creer);
  const pct=Math.round(a.nb_fnc_crees/a.ncns*100);
  $('#ai_quota_fill').css('width',pct+'%').css('background',pct>=100?'#D32F2F':'#1E9C4B');
  $('#auditInfoBox').show();
  $('#btnContinueAudit').prop('disabled',a.reste_a_creer<=0);
});
$('#btnContinueAudit').on('click',function(){
  bootstrap.Modal.getInstance('#modalSelectAudit').hide();
  openFncForm();
});

/* ===== FORMULAIRE FNC ===== */
function openFncForm(){
  blocCounter = 0; pendingBlocs = [];
  $('#fncBlocs').empty();
  // Infos quota
  $('#qb_num').text(CURRENT_AUDIT.num_audit);
  $('#qb_ncns').text(CURRENT_AUDIT.ncns);
  $('#qb_crees').text(CURRENT_AUDIT.nb_fnc_crees);
  $('#qb_reste').text(CURRENT_AUDIT.reste_a_creer);
  $('#fnc_num_display').text(CURRENT_AUDIT.num_audit);
  $('#btnSaveFnc').prop('disabled',true);
  // Charger habilitations inspecteur et reglements en parallele
  $.when(
    apiPost({action:'habilitations_insp'}),
    apiPost({action:'reglements_audit', idaudit:CURRENT_AUDIT.idaudit})
  ).done(function(r1,r2){
    DOMAINES_INSP   = (r1[0]||{}).domaines     || [];
    SOUSDOM_INSP    = (r1[0]||{}).sousdomaines || [];
    REGLEMENTS_AUDIT= (r2[0]||{}).data         || [];
    new bootstrap.Modal('#modalFnc').show();
  });
}

function addBloc(){
  const reste = CURRENT_AUDIT.reste_a_creer - blocCounter;
  if(blocCounter >= reste){
    Swal.fire({icon:'warning',title:'Quota atteint',
      html:'Vous ne pouvez creer que <strong>'+reste+'</strong> fiche(s) pour cet audit.<br>NCNS = '+CURRENT_AUDIT.ncns,
      confirmButtonColor:'#D32F2F'}); return;
  }
  blocCounter++;
  const idx = blocCounter;

  // Options domaines
  const domsOpts = DOMAINES_INSP.map(function(d){
    return '<option value="'+d.iddomaine+'">'+esc(d.nomdomaine)+' - '+esc(d.libel_domaine)+'</option>';
  }).join('');

  // Options sous-domaines du 1er domaine par defaut
  const domDefaut = DOMAINES_INSP.length===1 ? DOMAINES_INSP[0].iddomaine : null;
  const sdInitOpts = SOUSDOM_INSP
    .filter(function(sd){ return domDefaut ? String(sd.iddomaine)===String(domDefaut) : true; })
    .map(function(sd){ return '<option value="'+sd.idsousdomaine+'">'+esc(sd.nom_sousdomaine)+'</option>'; }).join('');

  // Options reglements
  const regsOpts = REGLEMENTS_AUDIT.map(function(r){
    return '<option value="'+r.idreglement+'">'+esc(r.code_reglement)+' - '+esc(r.libelle_reglement)+'</option>';
  }).join('');

  // Domaine select HTML
  const domSelectHtml = DOMAINES_INSP.length===1
    ? '<option value="'+DOMAINES_INSP[0].iddomaine+'">'+esc(DOMAINES_INSP[0].nomdomaine)+' - '+esc(DOMAINES_INSP[0].libel_domaine)+'</option>'
    : '<option value="">-- Choisir le domaine --</option>'+domsOpts;

  const bloc = $('<div class="fnc-bloc" id="bloc_'+idx+'">'+
    '<div class="fnc-bloc-header">'+
      '<div class="d-flex align-items-center gap-2">'+
        '<span style="font-size:.82rem;color:#7b8aa0">Fiche NC</span>'+
        '<span class="num-fnc-display" id="numFnc_'+idx+'" style="font-size:.92rem;background:#e8f0fe;color:#23408F;padding:4px 14px;border-radius:8px;font-weight:800;font-family:monospace">Generation en cours...</span>'+
      '</div>'+
      '<button type="button" class="btn-remove-bloc" onclick="removeBloc('+idx+')"><i class="bi bi-x-lg me-1"></i>Retirer cette fiche</button>'+
    '</div>'+

    // Section : Informations generales
    '<div class="section-divider"><i class="bi bi-person-badge"></i>Informations generales</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-5"><div class="field-label">Nom de l\'operateur</div>'+
        '<input type="text" class="form-control form-control-sm" value="'+esc(CURRENT_AUDIT.nomorga||'')+'" readonly style="background:#f5f7fa"></div>'+
      '<div class="col-md-4"><div class="field-label">Lieu (site d\'inspection)</div>'+
        '<textarea class="form-control form-control-sm" rows="2" readonly style="background:#f5f7fa;font-size:.82rem">'+
        esc([CURRENT_AUDIT.indicateur_oaci, CURRENT_AUDIT.site_inspection, CURRENT_AUDIT.ville].filter(Boolean).join(', '))+
        '</textarea></div>'+
      '<div class="col-md-3"><div class="field-label">Date d\'emission <span class="text-danger">*</span></div>'+
        '<input type="date" class="form-control form-control-sm date-emission-'+idx+'" value="'+new Date().toISOString().substring(0,10)+'"></div>'+
    '</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-4"><div class="field-label">Representant de l\'operateur</div>'+
        '<input type="text" class="form-control form-control-sm fnc-representant" placeholder="Nom du representant"></div>'+
      '<div class="col-md-4"><div class="field-label">Titre</div>'+
        '<input type="text" class="form-control form-control-sm fnc-titre" placeholder="Fonction / titre"></div>'+
      '<div class="col-md-4"><div class="field-label">Nom de l\'Inspecteur</div>'+
        '<input type="text" class="form-control form-control-sm fnc-nom-insp" id="nomInsp_'+idx+'" value="'+esc(NOM_INSP_CONN)+'" readonly style="background:#f5f7fa"></div>'+
    '</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-5"><div class="field-label">Nom de l\'Inspecteur (signataire)</div>'+
        '<input type="text" class="form-control form-control-sm fnc-nom-insp" id="nomInsp_'+idx+'" value="'+esc(NOM_INSP_CONN)+'" readonly style="background:#f5f7fa"></div>'+
      '<div class="col-md-4"><div class="field-label">Visa / Signature</div>'+
        '<input type="text" class="form-control form-control-sm fnc-visa" id="visa_'+idx+'" placeholder="Initiales ou visa..."></div>'+
      '<div class="col-md-3"><div class="field-label">Date de signature</div>'+
        '<input type="date" class="form-control form-control-sm fnc-date-sign" id="dateSign_'+idx+'" value="'+new Date().toISOString().substring(0,10)+'" readonly style="background:#f5f7fa"></div>'+
    '</div>'+

    // Section : Domaine et sous-domaine
    '<div class="section-divider"><i class="bi bi-diagram-3"></i>Domaine et sous-domaine</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-4"><div class="field-label">Domaine d\'inspection <span class="text-danger">*</span></div>'+
        '<select class="form-select form-select-sm fnc-domaine fnc-s2" id="domSelect_'+idx+'" style="width:100%">'+domSelectHtml+'</select></div>'+
      '<div class="col-md-5"><div class="field-label">Sous-domaine(s) <span class="text-danger">*</span></div>'+
        '<select class="fnc-sousdom fnc-s2" id="sdSelect_'+idx+'" multiple style="width:100%">'+sdInitOpts+'</select></div>'+
      '<div class="col-md-3 d-flex align-items-end"><button type="button" class="btn btn-outline-secondary btn-sm w-100 btn-add-sd" data-idx="'+idx+'" style="font-size:.78rem"><i class="bi bi-plus-circle me-1"></i>Nouveau sous-domaine</button></div>'+
    '</div>'+

    // Section : Categorisation
    '<div class="section-divider"><i class="bi bi-tag"></i>Categorisation</div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-4"><div class="field-label">Categorie <span class="text-danger">*</span></div>'+
        '<select class="form-select form-select-sm fnc-categorie fnc-s2" id="categSelect_'+idx+'" style="width:100%">'+
          '<option value="">-- Choisir --</option>'+
          '<option value="critique">Critique - reponse immediate</option>'+
          '<option value="majeur">Majeur - delai 3 mois</option>'+
          '<option value="mineur">Mineur - delai 6 mois</option>'+
          '<option value="observation">Observation - aucun delai</option>'+
        '</select></div>'+
      '<div class="col-md-4" id="dateRepZone_'+idx+'"><div class="field-label">Reponse exigee avant le</div>'+
        '<input type="date" class="form-control form-control-sm fnc-date-rep" id="dateRep_'+idx+'" readonly style="background:#f5f7fa"></div>'+
      '<div class="col-md-4" id="dateLimZone_'+idx+'"><div class="field-label">Delai de mise en conformite</div>'+
        '<input type="date" class="form-control form-control-sm fnc-date-lim" id="dateLim_'+idx+'" readonly style="background:#f5f7fa"></div>'+
    '</div>'+

    // Section : Constatation + Etat
    '<div class="section-divider"><i class="bi bi-clipboard-text"></i>Description de la constatation</div>'+
    '<div class="mb-3"><div class="field-label">Description de la constatation <span class="text-danger">*</span></div>'+
      '<textarea class="form-control fnc-description" id="descConst_'+idx+'" rows="3" placeholder="Decrire la constatation..."></textarea></div>'+
    '<div class="mb-3"><div class="field-label">Etat <span class="text-danger">*</span> <small style="color:#D32F2F;font-weight:400">(selection obligatoire)</small></div>'+
      '<div class="d-flex flex-wrap gap-2 mt-1" id="etatRadio_'+idx+'">'+
        '<label class="radio-opt" style="border:2px solid #e2e8f0;padding:10px 16px;border-radius:10px;min-width:180px">'+
          '<input type="radio" name="etat_'+idx+'" value="documente_non_implemente">'+
          '<span><i class="bi bi-file-text me-1" style="color:#23408F"></i>Documente, pas mis en oeuvre</span></label>'+
        '<label class="radio-opt" style="border:2px solid #e2e8f0;padding:10px 16px;border-radius:10px;min-width:180px">'+
          '<input type="radio" name="etat_'+idx+'" value="implemente_non_documente">'+
          '<span><i class="bi bi-tools me-1" style="color:#b58a00"></i>Mis en oeuvre, pas documente</span></label>'+
        '<label class="radio-opt" style="border:2px solid #e2e8f0;padding:10px 16px;border-radius:10px;min-width:180px">'+
          '<input type="radio" name="etat_'+idx+'" value="non_documente_non_implemente">'+
          '<span><i class="bi bi-x-circle me-1" style="color:#D32F2F"></i>Pas documente, pas mis en oeuvre</span></label>'+
      '</div></div>'+

    // Section : Referentiel
    '<div class="section-divider"><i class="bi bi-book"></i>Referentiel</div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-5"><div class="field-label">Reglement(s)</div>'+
        '<select class="fnc-reglements fnc-s2" id="regSelect_'+idx+'" multiple style="width:100%">'+regsOpts+'</select>'+
        '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-reg" data-idx="'+idx+'" style="font-size:.78rem"><i class="bi bi-plus-circle me-1"></i>Autre reglement</button>'+
      '</div>'+
      '<div class="col-md-4"><div class="field-label">Manuel</div>'+
        '<textarea class="form-control form-control-sm fnc-manuel" rows="2" placeholder="Reference manuel..."></textarea></div>'+
      '<div class="col-md-3"><div class="field-label">Autres references</div>'+
        '<textarea class="form-control form-control-sm fnc-autres" rows="2" placeholder="Autres..."></textarea></div>'+
    '</div>'+

    // Section : Textes riches
    '<div class="mb-3"><div class="field-label">Analyse des causes</div>'+
      '<textarea class="form-control fnc-causes" id="causes_'+idx+'" rows="2" placeholder="Analyser les causes..."></textarea></div>'+
    '<div class="mb-3"><div class="field-label">Action(s) correctrice(s)</div>'+
      '<textarea class="form-control fnc-actions" id="actions_'+idx+'" rows="3" placeholder="Decrire les actions correctives..."></textarea></div>'+
    '<div class="mb-3"><div class="field-label">Observation</div>'+
      '<textarea class="form-control fnc-observation" id="obs_'+idx+'" rows="2" placeholder="Observations..."></textarea></div>'+

    // Section : PAC
    '<div class="section-divider" style="background:#2C3E50"><i class="bi bi-check2-square"></i>Section reservee a l\'ANAC - Criteres d\'analyse du PAC</div>'+
    '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:10px">'+
      '<div style="font-size:.75rem;color:#7b8aa0;margin-bottom:10px">Pour chaque critere, cocher S (Satisfaisant) ou NS (Non satisfaisant)</div>'+
      '<div class="row g-2 mb-3">'+
        ['Pertinent','Exhaustif','Detaille','Specifique','Realiste','Coherent'].map(function(c,ci){
          const key=['pac_pertinent','pac_exhaustif','pac_detaille','pac_specifique','pac_realiste','pac_coherent'][ci];
          return '<div class="col-md-2 col-4">'+
            '<div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;padding:8px;text-align:center">'+
            '<div style="font-size:.78rem;font-weight:700;color:#2C3E50;margin-bottom:6px">'+c+'</div>'+
            '<div class="d-flex justify-content-center gap-2">'+
              '<label class="pac-btn" data-name="'+key+'_'+idx+'" data-val="S" style="background:#d1fae5;color:#065f46;border:1.5px solid #6ee7b7;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:.8rem;font-weight:800">'+
                '<input type="radio" name="'+key+'_'+idx+'" value="S" style="display:none"> S'+
              '</label>'+
              '<label class="pac-btn" data-name="'+key+'_'+idx+'" data-val="NS" style="background:#fee2e2;color:#991b1b;border:1.5px solid #fca5a5;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:.8rem;font-weight:800">'+
                '<input type="radio" name="'+key+'_'+idx+'" value="NS" style="display:none"> NS'+
              '</label>'+
            '</div></div></div>';
        }).join('')+
      '</div>'+
      '<div class="row g-3">'+
        '<div class="col-md-6">'+
          '<div style="font-size:.82rem;font-weight:700;color:#2C3E50;margin-bottom:8px"><i class="bi bi-clipboard-check me-1"></i>Acceptation du PAC</div>'+
          '<div class="d-flex gap-3">'+
            '<label class="pac-acc-btn" style="display:flex;align-items:center;gap:8px;background:#d1fae5;border:2px solid #6ee7b7;border-radius:10px;padding:10px 18px;cursor:pointer;transition:all .15s">'+
              '<input type="radio" name="pac_acc_'+idx+'" value="acceptee" style="display:none">'+
              '<i class="bi bi-check-circle-fill" style="color:#065f46;font-size:1.1rem"></i>'+
              '<span style="font-weight:700;color:#065f46">Acceptee</span>'+
            '</label>'+
            '<label class="pac-acc-btn" style="display:flex;align-items:center;gap:8px;background:#fee2e2;border:2px solid #fca5a5;border-radius:10px;padding:10px 18px;cursor:pointer;transition:all .15s">'+
              '<input type="radio" name="pac_acc_'+idx+'" value="refusee" style="display:none">'+
              '<i class="bi bi-x-circle-fill" style="color:#991b1b;font-size:1.1rem"></i>'+
              '<span style="font-weight:700;color:#991b1b">Refusee</span>'+
            '</label>'+
          '</div>'+
        '</div>'+
        '<div class="col-md-6">'+
          '<div style="font-size:.82rem;font-weight:700;color:#2C3E50;margin-bottom:8px"><i class="bi bi-eye-fill me-1"></i>Verification de mise en oeuvre</div>'+
          '<label style="display:flex;align-items:center;gap:10px;background:#f0f4ff;border:2px solid #c5d4f5;border-radius:10px;padding:12px 16px;cursor:pointer">'+
            '<input type="checkbox" class="fnc-meo" id="meo_'+idx+'" style="width:18px;height:18px;cursor:pointer;accent-color:#23408F">'+
            '<span style="font-size:.88rem;font-weight:600;color:#23408F">Mise en oeuvre effectuee et verifiee</span>'+
          '</label>'+
        '</div>'+
      '</div>'+
    '</div>'+
  '</div>');

  $('#fncBlocs').append(bloc);

  // Activer Select2 sur tous les selects du bloc
  $('#domSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',placeholder:'-- Choisir le domaine --',
    dropdownParent:$('#modalFnc')});
  $('#sdSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',
    placeholder:'Choisir sous-domaine(s)',allowClear:true,dropdownParent:$('#modalFnc')});
  $('#regSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',
    placeholder:'Choisir reglement(s)',allowClear:true,dropdownParent:$('#modalFnc')});
  $('#categSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',
    placeholder:'-- Choisir --',dropdownParent:$('#modalFnc')});

  // Si domaine unique : pre-selectionner et gen num FNC
  if(DOMAINES_INSP.length===1){
    const domIdUniq = DOMAINES_INSP[0].iddomaine;
    $('#domSelect_'+idx).val(domIdUniq).trigger('change.select2');
    // Filtrer sous-domaines pour ce domaine unique
    const sdOptsUniq=SOUSDOM_INSP
      .filter(function(sd){ return String(sd.iddomaine)===String(domIdUniq); })
      .map(function(sd){ return '<option value="'+sd.idsousdomaine+'">'+esc(sd.nom_sousdomaine)+'</option>'; }).join('');
    $('#sdSelect_'+idx).html(sdOptsUniq).trigger('change.select2');
    genNumFnc(idx, domIdUniq);
  }

  // Handler changement domaine -> filtrer sous-domaines + gen num
  $('#domSelect_'+idx).on('change',function(){
    const domId=$(this).val();
    if(!domId) return;
    genNumFnc(idx, domId);
    const sdOpts=SOUSDOM_INSP
      .filter(function(sd){ return String(sd.iddomaine)===String(domId); })
      .map(function(sd){ return '<option value="'+sd.idsousdomaine+'">'+esc(sd.nom_sousdomaine)+'</option>'; }).join('');
    $('#sdSelect_'+idx).html(sdOpts).trigger('change.select2');
  });

  // Handler changement categorie
  $('#categSelect_'+idx).on('change',function(){ onCategChange(idx); });

  // Style des radios opt
  bloc.find('.radio-opt input').on('change',function(){
    const n=$(this).attr('name');
    $('[name="'+n+'"]').closest('.radio-opt').removeClass('selected');
    $(this).closest('.radio-opt').addClass('selected');
  });

  updateBtnState();
}

function genNumFnc(idx, domId){
  apiPost({action:'next_num_fnc',idaudit:CURRENT_AUDIT.idaudit,iddomaine:domId}).done(function(r){
    if(r.success) $('#numFnc_'+idx).text(r.num_fnc);
  });
}

function onCategChange(idx){
  const cat=$('#categSelect_'+idx).val();
  const dateRap = CURRENT_AUDIT.date_delivrance_rapport;
  const dateEmission = $('.date-emission-'+idx).val()||new Date().toISOString().substring(0,10);
  function addDays(d,n){ const dt=new Date(d); dt.setDate(dt.getDate()+n); return dt.toISOString().substring(0,10); }
  function addMonths(d,n){ const dt=new Date(d); dt.setMonth(dt.getMonth()+n); return dt.toISOString().substring(0,10); }
  if(cat==='critique'){
    $('#dateRepZone_'+idx+',#dateLimZone_'+idx).show();
    $('#dateRep_'+idx).val(dateEmission); $('#dateLim_'+idx).val(dateEmission);
  } else if(cat==='majeur'){
    $('#dateRepZone_'+idx+',#dateLimZone_'+idx).show();
    $('#dateRep_'+idx).val(dateRap?addDays(dateRap,30):'');
    $('#dateLim_'+idx).val(dateRap?addMonths(dateRap,3):'');
  } else if(cat==='mineur'){
    $('#dateRepZone_'+idx+',#dateLimZone_'+idx).show();
    $('#dateRep_'+idx).val(dateRap?addDays(dateRap,30):'');
    $('#dateLim_'+idx).val(dateRap?addMonths(dateRap,6):'');
  } else if(cat==='observation'){
    $('#dateRepZone_'+idx+',#dateLimZone_'+idx).hide();
    $('#dateRep_'+idx).val(''); $('#dateLim_'+idx).val('');
  }
}

function removeBloc(idx){ $('#bloc_'+idx).remove(); blocCounter--; updateBtnState(); }
function updateBtnState(){
  const nb=$('#fncBlocs .fnc-bloc').length;
  $('#btnSaveFnc').prop('disabled',nb===0);
  const reste=CURRENT_AUDIT.reste_a_creer - nb;
  $('#addBlocHint').text(nb>0?nb+' fiche(s) preparee(s) | Peut encore ajouter '+(reste>0?reste:0):'Cliquer pour ajouter une premiere fiche');
  $('#qb_reste').text(CURRENT_AUDIT.reste_a_creer - nb);
}
$('#btnAddBloc').on('click',function(){ addBloc(); });

/* ===== ENREGISTREMENT ===== */
$('#btnSaveFnc').on('click',function(){
  const blocs=$('#fncBlocs .fnc-bloc');
  if(!blocs.length) return;
  let ok=true;
  blocs.each(function(){
    const id=this.id.replace('bloc_','');
    if(!$('#categSelect_'+id).val()){ ok=false; Swal.fire({icon:'warning',text:'Veuillez choisir une categorie pour la fiche #'+id,confirmButtonColor:'#D32F2F'}); return false; }
    if(!$('#descConst_'+id).val().trim()){ ok=false; Swal.fire({icon:'warning',text:'Description de la constatation manquante pour la fiche #'+id,confirmButtonColor:'#D32F2F'}); return false; }
    if(!$('[name="etat_'+id+'"]:checked').val()){ ok=false; Swal.fire({icon:'warning',text:'Veuillez choisir l\'etat pour la fiche #'+id,confirmButtonColor:'#D32F2F'}); return false; }
  });
  if(!ok) return;
  const btn=$(this); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...');
  // Sauvegarder chaque bloc sequentiellement
  const promises=[];
  blocs.each(function(){
    const idx=this.id.replace('bloc_','');
    const domId=$('#domSelect_'+idx).val()||( DOMAINES_INSP.length===1?DOMAINES_INSP[0].iddomaine:'');
    const sds=$('#sdSelect_'+idx).val()||[];
    const regs=$('#regSelect_'+idx).val()||[];
    const d={
      action:'create', idaudit:CURRENT_AUDIT.idaudit, iddomaine:domId,
      num_fnc:$('#numFnc_'+idx).text(),
      categorie:$('#categSelect_'+idx).val(),
      date_emission:$('.date-emission-'+idx).val(),
      representant_operateur:$('#bloc_'+idx+' .fnc-representant').val(),
      titre_representant:$('#bloc_'+idx+' .fnc-titre').val(),
      libelle:$('#descConst_'+idx).val(),
      description_constatation:$('#descConst_'+idx).val(),
      etat:$('[name="etat_'+idx+'"]:checked').val(),
      manuel:$('#bloc_'+idx+' .fnc-manuel').val(),
      autres:$('#bloc_'+idx+' .fnc-autres').val(),
      analyse_causes:$('#causes_'+idx).val(),
      actions_correctives:$('#actions_'+idx).val(),
      observation:$('#obs_'+idx).val(),
      pac_pertinent:$('[name="pac_pertinent_'+idx+'"]:checked').val()||'',
      pac_exhaustif:$('[name="pac_exhaustif_'+idx+'"]:checked').val()||'',
      pac_detaille:$('[name="pac_detaille_'+idx+'"]:checked').val()||'',
      pac_specifique:$('[name="pac_specifique_'+idx+'"]:checked').val()||'',
      pac_realiste:$('[name="pac_realiste_'+idx+'"]:checked').val()||'',
      pac_coherent:$('[name="pac_coherent_'+idx+'"]:checked').val()||'',
      pac_acceptation:$('[name="pac_acc_'+idx+'"]:checked').val()||'',
      verification_meo:$('#meo_'+idx).is(':checked')?1:0,
      nom_visa_date:$('#visa_'+idx).val(),
      source_audit:(TYPE_LABELS[CURRENT_AUDIT.type_activite]||CURRENT_AUDIT.type_activite)+' / '+(CURRENT_AUDIT.cadre||''),
    };
    sds.forEach(function(s,i){ d['sousdomaines['+i+']']=s; });
    regs.forEach(function(r,i){ d['reglements['+i+']']=r; });
    promises.push(apiPost(d));
  });
  $.when.apply($,promises).done(function(){
    const results=promises.length===1?[arguments]:[...arguments];
    const success=results.filter(function(r){return r[0]&&r[0].success;}).length;
    bootstrap.Modal.getInstance('#modalFnc').hide();
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer les fiches');
    Swal.fire({icon:'success',title:success+' fiche(s) NC cree(s)',timer:2500,showConfirmButton:false});
    CURRENT_AUDIT.nb_fnc_crees += success;
    CURRENT_AUDIT.reste_a_creer -= success;
    loadFncList(); loadAuditsEligibles();
  });
});

/* ===== IMPRESSION FNC ===== */
$(document).on('click','.btn-print-fnc',function(){
  const id=$(this).data('id');
  apiPost({action:'get',idfnc:id}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',text:res.message}); return; }
    const f=res.data, sds=res.sousdomaines||[], regs=res.reglements||[];
    $('#printTitle').text('FNC - '+f.num_fnc);
    $('#printPreview').html(buildFncPdf(f,sds,regs));
    new bootstrap.Modal('#modalPrint').show();
  });
});
$('#btnDoPrint').on('click',function(){
  const w=window.open('','_blank','width=900,height=750');
  w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>FNC</title>'+
    '<style>'+getFncPrintStyle()+'</style></head><body>'+$('#printPreview').html()+'</body></html>');
  w.document.close(); w.focus(); setTimeout(function(){ w.print(); },800);
});

function getFncPrintStyle(){
  return '@page{size:A4;margin:0}body{font-family:Candara,Arial,sans-serif;font-size:9pt;color:#2C3E50;margin:0}'+
    '.fnc-page{width:210mm;min-height:297mm;padding:10mm 12mm;box-sizing:border-box}'+
    '.ref-line{text-align:right;font-size:7pt;color:#666;margin-bottom:4px}'+
    '.hdr{text-align:center;border-bottom:2px solid #23408F;padding-bottom:8px;margin-bottom:10px}'+
    '.hdr img{max-height:70px;object-fit:contain}'+
    '.fnc-title{text-align:center;font-size:13pt;font-weight:900;text-transform:uppercase;color:#23408F;border:2px solid #23408F;padding:6px;margin:8px 0}'+
    'table{width:100%;border-collapse:collapse}td,th{border:1px solid #bbb;padding:4px 7px;font-size:8pt;vertical-align:top}'+
    '.th-blue{background:#23408F;color:#fff;font-weight:700;text-align:center;font-size:8pt;text-transform:uppercase}'+
    '.lbl{background:#e8edf8;font-weight:700;font-size:7.5pt;width:22%}'+
    '.section-hdr{background:#23408F;color:#fff;font-weight:700;text-align:center;padding:5px;font-size:8.5pt;text-transform:uppercase}'+
    '.pac-row td{text-align:center;width:16.6%}'+
    '.chk{display:inline-block;width:10px;height:10px;border:1.5px solid #555}'+
    '.chk-x{background:#23408F;border-color:#23408F}'+
    '.footer{margin-top:10px;border-top:1px solid #ccc;padding-top:6px;font-size:7pt;font-style:italic}'+
    '.addr{margin-top:8px;text-align:center;font-size:7pt;color:#666;border-top:1px solid #23408F;padding-top:4px}';
}

function chk(v){ return '<span class="chk'+(v?'  chk-x':'')+'" style="display:inline-block;width:11px;height:11px;border:1.5px solid '+(v?'#23408F':'#555')+';background:'+(v?'#23408F':'#fff')+'"></span>'; }
function snCol(v,target){ return v===target?chk(true):chk(false); }
function fmtD(s){ if(!s||s==='0000-00-00') return ''; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

function buildFncPdf(f,sds,regs){
  const sdNoms=sds.map(function(s){return esc(s.nom_sousdomaine);}).join(', ')||'-';
  const regCodes=regs.map(function(r){return esc(r.code_reglement);}).join(', ')||'-';
  return '<div class="fnc-page">'+
    '<div class="ref-line">IX-GEN-R3-F-I-010 &nbsp;&nbsp; Version 03</div>'+
    '<div class="hdr"><img src="'+BANER+'" alt="ANAC Gabon"></div>'+
    '<div class="fnc-title">Fiche de Non-Conformite N&deg; <strong>'+esc(f.num_fnc)+'</strong></div>'+
    '<table style="margin-bottom:0">'+
      '<tr><td class="lbl">Nom de l\'operateur :</td><td colspan="2"><strong>'+esc(f.nomorga||'-')+'</strong></td>'+
        '<td class="lbl" style="width:10%">Lieu :</td><td style="width:18%">'+esc(f.ville||f.indicateur_oaci||f.site_inspection||'-')+'</td>'+
        '<td class="lbl" style="width:8%">Date</td><td style="width:14%">'+fmtD(f.date_emission)+'</td></tr>'+
      '<tr><td class="lbl">Representant :</td><td colspan="2">'+esc(f.representant_operateur||'-')+'</td>'+
        '<td class="lbl" colspan="1">Titre :</td><td colspan="3">'+esc(f.titre_representant||'-')+'</td></tr>'+
      '<tr><td class="lbl">Nom de l\'Inspecteur :</td><td colspan="2"><strong>'+esc(f.nom_inspecteur||'-')+'</strong></td>'+
        '<td class="lbl">Visa :</td><td colspan="3"></td></tr>'+
      '<tr><td class="lbl" colspan="1">Domaine d\'Inspection :</td><td colspan="2">'+esc(f.nomdomaine||'-')+'</td>'+
        '<td class="lbl">Sous-domaine :</td><td colspan="3">'+sdNoms+'</td></tr>'+
      '<tr><td class="section-hdr" colspan="7">Description de la constatation</td></tr>'+
      '<tr><td colspan="7" style="min-height:60px;height:60px">'+esc(f.description_constatation||f.libelle||'-')+'</td></tr>'+
      '<tr>'+
        '<td style="width:30%">'+chk(f.etat==='documente_non_implemente')+' Documente, pas mis en oeuvre</td>'+
        '<td style="width:30%">'+chk(f.etat==='implemente_non_documente')+' Mis en oeuvre, pas documente</td>'+
        '<td colspan="5">'+chk(f.etat==='non_documente_non_implemente')+' Pas documente, pas mis en oeuvre</td>'+
      '</tr>'+
      '<tr><td class="section-hdr" colspan="7">Referentiel</td></tr>'+
      '<tr><td class="lbl">Reglement</td><td colspan="2">'+regCodes+'</td>'+
        '<td class="lbl">Manuel</td><td colspan="1">'+esc(f.manuel||'-')+'</td>'+
        '<td class="lbl">Autres</td><td>'+esc(f.autres||'-')+'</td></tr>'+
      '<tr><td class="lbl" colspan="2">Categorisation : <strong>'+esc(f.categorie||'-')+'</strong></td>'+
        '<td colspan="2" class="lbl">Reponse exigee avant le :</td>'+
        '<td>'+fmtD(f.date_reponse_exigee)+'</td>'+
        '<td class="lbl">Delai de mise en conformite :</td>'+
        '<td>'+fmtD(f.date_limite_mise_conformite)+'</td></tr>'+
      '<tr><td class="section-hdr" colspan="7">Plan d\'Actions Correctives (PAC) de l\'operateur</td></tr>'+
      '<tr><td class="lbl" colspan="7">Analyse des causes :</td></tr>'+
      '<tr><td colspan="7" style="height:50px">'+esc(f.analyse_causes||'-')+'</td></tr>'+
      '<tr><td class="lbl" colspan="7">Action(s) correctrice(s) :</td></tr>'+
      '<tr><td colspan="7" style="height:70px">'+esc(f.actions_correctives||'-')+'</td></tr>'+
      '<tr><td class="th-blue" colspan="7">Section reservee a l\'ANAC &ndash; Criteres d\'analyse du plan d\'actions correctives</td></tr>'+
      '<tr class="pac-row">'+
        '<td style="text-align:center;font-weight:700">Pertinent</td><td style="text-align:center;font-weight:700">Exhaustif</td>'+
        '<td style="text-align:center;font-weight:700">Detaille</td><td style="text-align:center;font-weight:700">Specifique</td>'+
        '<td style="text-align:center;font-weight:700">Realiste</td><td colspan="2" style="text-align:center;font-weight:700">Coherent</td>'+
      '</tr>'+
      '<tr class="pac-row">'+
        '<td>S '+snCol(f.pac_pertinent,'S')+' NS '+snCol(f.pac_pertinent,'NS')+'</td>'+
        '<td>S '+snCol(f.pac_exhaustif,'S')+' NS '+snCol(f.pac_exhaustif,'NS')+'</td>'+
        '<td>S '+snCol(f.pac_detaille,'S')+' NS '+snCol(f.pac_detaille,'NS')+'</td>'+
        '<td>S '+snCol(f.pac_specifique,'S')+' NS '+snCol(f.pac_specifique,'NS')+'</td>'+
        '<td>S '+snCol(f.pac_realiste,'S')+' NS '+snCol(f.pac_realiste,'NS')+'</td>'+
        '<td colspan="2">S '+snCol(f.pac_coherent,'S')+' NS '+snCol(f.pac_coherent,'NS')+'</td>'+
      '</tr>'+
      '<tr><td class="lbl" colspan="2"><strong>Acceptation du PAC</strong></td>'+
        '<td>acceptee '+chk(f.pac_acceptation==='acceptee')+'</td>'+
        '<td colspan="2">refusee '+chk(f.pac_acceptation==='refusee')+'</td>'+
        '<td colspan="2"></td></tr>'+
      '<tr><td class="lbl" colspan="7">Observation :</td></tr>'+
      '<tr><td colspan="7" style="height:50px">'+esc(f.observation||'-')+'</td></tr>'+
      '<tr>'+
        '<td colspan="3">Verification de mise en oeuvre effective '+chk(!!f.verification_meo)+'</td>'+
        '<td class="lbl" colspan="1">Nom, Visa &amp; Date :</td>'+
        '<td colspan="3">'+esc(f.nom_visa_date||'')+'</td>'+
      '</tr>'+
    '</table>'+
    '<div class="addr">BP 2212 Libreville (GABON) - Tel.: (241) 01 44 54 00 - Fax: (241) 01 44 54 01 - Email: anac@anac-gabon.com - www.anacgabon.org</div>'+
  '</div>';
}

/* ===== DELETE ===== */
$(document).on('click','.btn-del-fnc',function(){
  const id=$(this).data('id'), num=$(this).data('num');
  Swal.fire({icon:'warning',title:'Supprimer la FNC '+num+'?',text:'Cette action est irreversible.',
    showCancelButton:true,confirmButtonText:'Supprimer',confirmButtonColor:'#D32F2F',cancelButtonText:'Annuler'}).then(function(r){
    if(!r.isConfirmed) return;
    apiPost({action:'delete',idfnc:id}).done(function(res){
      if(res.success){ Swal.fire({icon:'success',title:'FNC supprimee',timer:1500,showConfirmButton:false}); loadFncList(); loadAuditsEligibles(); }
      else Swal.fire({icon:'error',text:res.message});
    });
  });
});

/* ===== STYLES PAC BUTTONS DYNAMIQUES ===== */
$(document).on('change','.pac-btn input[type=radio]',function(){
  const name=$(this).attr('name');
  $('[data-name="'+name+'"]').each(function(){
    const val=$(this).data('val');
    const checked=$('[name="'+name+'"][value="'+val+'"]').is(':checked');
    if(val==='S'){
      $(this).css(checked?{background:'#059669',color:'#fff',borderColor:'#059669'}:{background:'#d1fae5',color:'#065f46',borderColor:'#6ee7b7'});
    } else {
      $(this).css(checked?{background:'#dc2626',color:'#fff',borderColor:'#dc2626'}:{background:'#fee2e2',color:'#991b1b',borderColor:'#fca5a5'});
    }
  });
});
$(document).on('change','.pac-acc-btn input[type=radio]',function(){
  const name=$(this).attr('name');
  $('[name="'+name+'"]').each(function(){
    const lbl=$(this).closest('.pac-acc-btn');
    const isAcceptee=$(this).val()==='acceptee';
    if($(this).is(':checked')){
      lbl.css(isAcceptee?{background:'#059669',borderColor:'#059669'}:{background:'#dc2626',borderColor:'#dc2626'});
      lbl.find('i,span').css('color','#fff');
    } else {
      lbl.css(isAcceptee?{background:'#d1fae5',borderColor:'#6ee7b7'}:{background:'#fee2e2',borderColor:'#fca5a5'});
      lbl.find('i').css('color',isAcceptee?'#065f46':'#991b1b');
      lbl.find('span').css('color',isAcceptee?'#065f46':'#991b1b');
    }
  });
});

/* ===== BOUTON NOUVEAU SOUS-DOMAINE -> modale Bootstrap ===== */
$(document).on('click','.btn-add-sd',function(){
  const idx=$(this).data('idx');
  const domId=$('#domSelect_'+idx).val();
  if(!domId){
    Swal.fire({icon:'warning',text:'Choisissez d\'abord un domaine d\'inspection.',confirmButtonColor:'#23408F'}); return;
  }
  const domNom=$('#domSelect_'+idx+' option:selected').text();
  $('#sd_bloc_idx').val(idx);
  $('#sd_dom_id').val(domId);
  $('#sd_dom_nom').text(domNom);
  $('#sd_nom_input').val('');
  $('#sdBackdrop').addClass('show').show();
  const m=new bootstrap.Modal('#modalAddSD',{backdrop:false});
  m.show();
  setTimeout(function(){ $('#sd_nom_input').focus(); },400);
});
$('#btnSaveSD').on('click',function(){
  doAddSD(true);
});
function doAddSD(closeAfter){
  const idx=$('#sd_bloc_idx').val();
  const domId=$('#sd_dom_id').val();
  const rawText=$('#sd_nom_input').val();
  const noms=rawText.split('\n').map(function(s){return s.trim();}).filter(Boolean);
  if(!noms.length){ Swal.fire({icon:'warning',text:'Veuillez saisir au moins un nom de sous-domaine.',confirmButtonColor:'#23408F'}); return; }
  const btn=$('#btnSaveSD'); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Ajout...');
  let done=0, errors=0;
  noms.forEach(function(nom){
    // Utiliser l'endpoint nonconformites (accessible a l'inspecteur)
    $.post(API,{csrf_token:CSRF,action:'create_sousdomaine',nom_sousdomaine:nom,iddomaine:domId},null,'json')
    .always(function(res){
      done++;
      if(res&&res.success!==false&&res.idsousdomaine){
        const newId=res.idsousdomaine;
        // Eviter les doublons dans le select
        if(!$('#sdSelect_'+idx+' option[value="'+newId+'"]').length){
          const opt=new Option(nom,newId,true,true);
          $('#sdSelect_'+idx).append(opt).trigger('change');
        }
        if(!SOUSDOM_INSP.find(function(s){return String(s.idsousdomaine)===String(newId);})){
          SOUSDOM_INSP.push({idsousdomaine:newId,nom_sousdomaine:nom,iddomaine:domId});
        }
      } else {
        errors++;
      }
      if(done===noms.length){
        btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Ajouter');
        if(closeAfter){
          bootstrap.Modal.getInstance('#modalAddSD').hide();
          $('#sdBackdrop').removeClass('show').hide();
          Swal.fire({
            icon:errors===0?'success':'warning',
            title:(done-errors)+' sous-domaine(s) ajoute(s)',
            text:errors>0?errors+' erreur(s) ignoree(s).':'',
            timer:1800,showConfirmButton:false
          });
        } else {
          $('#sd_nom_input').val('').focus();
          const fb=$('<div style="background:#d1fae5;border-radius:6px;padding:6px 10px;font-size:.8rem;color:#065f46;margin-top:6px"><i class="bi bi-check-circle me-1"></i>'+(done-errors)+' sous-domaine(s) ajoute(s). Vous pouvez en saisir d\'autres.</div>');
          $('#sd_nom_input').after(fb);
          setTimeout(function(){fb.fadeOut(400,function(){$(this).remove();});},2500);
        }
      }
    });
  });
}
$('#modalAddSD').on('hidden.bs.modal',function(){
  $('#sdBackdrop').removeClass('show').hide();
});

/* ===== BOUTON AJOUTER REGLEMENT -> modale Bootstrap ===== */
$(document).on('click','.btn-add-reg',function(){
  const idx=$(this).data('idx');
  const domId=$('#domSelect_'+idx).val()||0;
  const domNom=domId?($('#domSelect_'+idx+' option:selected').text()||'Non selectionne'):'Non selectionne';
  $('#reg_bloc_idx').val(idx);
  $('#reg_dom_id').val(domId);
  $('#reg_dom_nom').text(domNom);
  $('#reg_code_input').val('');
  $('#reg_lib_input').val('');
  $('#regBackdrop').addClass('show').show();
  const m=new bootstrap.Modal('#modalAddReg',{backdrop:false});
  m.show();
  setTimeout(function(){ $('#reg_code_input').focus(); },400);
});
function doAddReg(closeAfter){
  const code=$('#reg_code_input').val().trim();
  const lib=$('#reg_lib_input').val().trim();
  const idx=$('#reg_bloc_idx').val();
  const domId=$('#reg_dom_id').val()||0;
  if(!code){ Swal.fire({icon:'warning',text:'Le code est obligatoire.',confirmButtonColor:'#23408F'}); return; }
  if(!lib){ Swal.fire({icon:'warning',text:'Le libelle est obligatoire.',confirmButtonColor:'#23408F'}); return; }
  const fakeId='reg_'+Date.now();
  const label=code+' - '+lib;
  const opt=new Option(label,fakeId,true,true);
  $('#regSelect_'+idx).append(opt).trigger('change');
  REGLEMENTS_AUDIT.push({idreglement:fakeId,code_reglement:code,libelle_reglement:lib,iddomaine:domId});
  if(closeAfter){
    bootstrap.Modal.getInstance('#modalAddReg').hide();
    $('#regBackdrop').removeClass('show').hide();
    Swal.fire({icon:'success',title:'Reglement ajoute !',text:label,timer:1500,showConfirmButton:false});
  } else {
    const fb=$('<div style="background:#d1fae5;border-radius:6px;padding:6px 10px;font-size:.8rem;color:#065f46;margin-top:8px"><i class="bi bi-check-circle me-1"></i>'+label+' ajoute !</div>');
    $('#modalAddReg .modal-body').append(fb);
    setTimeout(function(){ fb.fadeOut(400,function(){ $(this).remove(); }); },2000);
    $('#reg_code_input').val('').focus();
    $('#reg_lib_input').val('');
  }
}
$('#btnSaveReg').on('click',function(){ doAddReg(true); });
$('#btnAddRegAndContinue').on('click',function(){ doAddReg(false); });
$('#modalAddReg').on('hidden.bs.modal',function(){
  $('#regBackdrop').removeClass('show').hide();
});
loadAuditsEligibles();
loadFncList();
</script>