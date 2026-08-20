<?php
/**
 * Module : Declenchement d'un acte de supervision (page) - Phase 1
 * ------------------------------------------------------------
 * Formulaire pleine page atteint depuis le tableau de bord (nature + cadre
 * deja choisis, affiches en grise et modifiables). On y choisit le
 * responsable (non stagiaire), l'operateur, le type d'organisme et le site
 * (listes deroulantes avec bouton + d'ajout rapide), le statut et la date
 * previsionnelle. Le numero d'audit est genere automatiquement.
 *
 * Reserve a l'administrateur et au chef inspecteur.
 * L'equipe d'inspecteurs et les notifications par mail viennent en tranche 1C.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardPage('audits');
if (!in_array(Rbac::role(), ['admin', 'chef_inspecteur'], true)) {
    header('Location: ' . SITE_URL . '/dashboard');
    exit;
}

$csrf = Security::generateCSRF();

$TYPES  = ['audit','inspection_programmee','inspection_non_programmee','demonstration','test','investigation'];
$CADRES = ['certification','homologation','reconnaissance','renouvellement','surveillance_continue','traitement_evenement','fermeture_provisoire','fermeture_definitive','delivrance_autorisation'];

$type  = $_GET['type'] ?? '';
$cadre = $_GET['cadre'] ?? '';
if (!in_array($type, $TYPES, true) || !in_array($cadre, $CADRES, true)) {
    // Nature/cadre manquants ou invalides : on repart du tableau de bord
    header('Location: ' . SITE_URL . '/dashboard');
    exit;
}

// Parametres de pre-remplissage transmis par le Programme PSC (facultatifs).
// Validation stricte : entiers positifs, date au format AAAA-MM-JJ.
$prefillOrga  = (isset($_GET['idorga'])     && ctype_digit((string)$_GET['idorga']))     ? (int)$_GET['idorga']     : 0;
$prefillSite  = (isset($_GET['idsite'])     && ctype_digit((string)$_GET['idsite']))     ? (int)$_GET['idsite']     : 0;
$prefillType  = (isset($_GET['idtypeorga']) && ctype_digit((string)$_GET['idtypeorga'])) ? (int)$_GET['idtypeorga'] : 0;
$prefillProg  = (isset($_GET['idprog'])     && ctype_digit((string)$_GET['idprog']))     ? (int)$_GET['idprog']     : 0;
$prefillDprev = (isset($_GET['dprev']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['dprev'])) ? $_GET['dprev'] : '';

$pageTitle = 'Declenchement';
$active    = 'audits';
$pageIcon  = 'bi-flag';

require_once INCLUDES_PATH . '/layout_head.php';
?>

<div class="page-head">
  <div>
    <h1><i class="bi bi-flag me-2" style="color:var(--anac-primary)"></i>Declenchement d'un acte de supervision</h1>
    <div class="sub">Phase 1 - Designation et planification.</div>
  </div>
  <a href="<?php echo SITE_URL; ?>/audits" class="btn btn-light"><i class="bi bi-list-ul me-1"></i>Liste des audits</a>
</div>

<style>
  .form-card{background:#fff;border:1px solid #eef1f6;border-radius:16px;padding:22px 24px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
  .form-section{font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:#23408F;font-weight:700;border-bottom:1px solid #eef1f6;padding-bottom:5px;margin:4px 0 14px;}
  .grey-box{background:#f5f7fa;border:1px dashed #cdd7e6;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;}
  .grey-box .gi{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#8a97ab;}
  .grey-box .gv{font-weight:700;color:#2C3E50;font-size:1.02rem;}
  .num-badge{font-family:monospace;font-weight:700;color:#23408F;background:rgba(35,64,143,.08);padding:.35rem .7rem;border-radius:8px;display:inline-block;}
  .add-btn{flex:0 0 auto;}
  .eq-card{border:1px solid #e8edf5;border-radius:12px;padding:14px 16px;margin-bottom:12px;background:#fcfdff;}
  .eq-card .eq-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
  .eq-card .eq-name{font-weight:700;color:#23408F;}
  .eq-dom{border:1px solid #eef1f6;border-radius:10px;padding:9px 12px;margin-bottom:8px;}
  .eq-dom.expired{background:#fdecec;border-color:#f4c9c9;}
  .eq-dom .dom-line{display:flex;align-items:center;gap:9px;}
  .eq-dom .dom-code{font-weight:600;color:#2C3E50;}
  .eq-dom .exp-tag{font-size:.75rem;color:#D32F2F;font-weight:600;margin-left:auto;}
  .eq-dom .ok-tag{font-size:.75rem;color:#1E9C4B;font-weight:600;margin-left:auto;}
  /* Radios statut style pastille */
  .statut-radio-group{display:flex;gap:8px;flex-wrap:wrap;}
  .statut-radio-group label{display:flex;align-items:center;gap:6px;cursor:pointer;padding:7px 14px;border:2px solid #eef1f6;border-radius:10px;font-size:.86rem;font-weight:600;transition:all .15s;background:#fff;}
  .statut-radio-group label:hover{border-color:#23408F;background:#f0f4ff;}
  .statut-radio-group input[type=radio]:checked + label,
  .statut-radio-group label:has(input[type=radio]:checked){border-color:#23408F;background:rgba(35,64,143,.08);color:#23408F;}
  .statut-radio-group input[type=radio]{display:none;}
  .statut-pill{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:3px;}
  .pill-plan{background:#23408F;} .pill-inop{background:#F3C300;} .pill-eff{background:#1E9C4B;}
  .pill-rep{background:#b58a00;} .pill-sus{background:#D32F2F;} .pill-surv{background:#6c757d;}
  .reg-list{margin-top:8px;padding-top:8px;border-top:1px dashed #e3e9f2;display:none;}
  .reg-list .form-check{margin-bottom:3px;}
  .reg-empty{font-size:.82rem;color:#9aa7bd;}
  .ra-badge{display:inline-block;font-size:.7rem;font-weight:700;color:#fff;background:#D32F2F;border-radius:20px;padding:.1rem .5rem;margin-left:8px;}
  .help-box{background:linear-gradient(135deg,#eef3fb,#f7faff);border:1px solid #dde7f5;border-radius:14px;padding:14px 18px;margin-bottom:16px;}
  .help-box .ht{display:flex;align-items:center;gap:8px;color:#23408F;font-weight:700;margin-bottom:10px;}
  .help-box .ht i{font-size:1.15rem;color:#23408F;}
  .help-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:0;}
  .help-step{display:flex;gap:10px;align-items:flex-start;font-size:.85rem;color:#3d4a5c;}
  .help-step .hn{flex:0 0 auto;width:24px;height:24px;border-radius:50%;background:#23408F;color:#fff;font-weight:700;font-size:.78rem;display:flex;align-items:center;justify-content:center;}
  .help-step i{color:#1E9C4B;}
  .reg-add{margin-top:6px;}
  .dom-chk:disabled{opacity:.45;}
</style>

<div class="form-card">
  <form id="decForm" autocomplete="off">

    <div class="help-box">
      <div class="ht"><i class="bi bi-info-circle-fill"></i>Comment remplir ce formulaire</div>
      <div class="help-steps">
        <div class="help-step"><span class="hn">1</span><div><i class="bi bi-flag me-1"></i>La <b>nature</b> et le <b>cadre</b> sont deja choisis (modifiables en haut). Le <b>numero d'audit</b> est genere automatiquement.</div></div>
        <div class="help-step"><span class="hn">2</span><div><i class="bi bi-person-badge me-1"></i>Designez le <b>responsable</b> (inspecteur non stagiaire) et l'<b>operateur</b> concerne.</div></div>
        <div class="help-step"><span class="hn">3</span><div><i class="bi bi-geo-alt me-1"></i>Precisez le <b>type d'activite</b>, le <b>site</b>, le <b>statut</b> et la <b>date previsionnelle</b>. Les boutons <i class="bi bi-plus-lg"></i> permettent d'ajouter au vol.</div></div>
        <div class="help-step"><span class="hn">4</span><div><i class="bi bi-people me-1"></i>Composez l'<b>equipe</b> : ajoutez les inspecteurs, cochez leurs <b>domaines habilites</b> (les expires sont en rouge) et les <b>reglements</b> vises.</div></div>
      </div>
    </div>

    <!-- Nature + cadre choisis (grises, modifiables) -->
    <div class="grey-box mb-4">
      <div>
        <div class="gi">Nature de la supervision</div>
        <div class="gv" id="natLabel">-</div>
      </div>
      <div style="border-left:1px solid #d7dfea;height:38px;"></div>
      <div>
        <div class="gi">Cadre</div>
        <div class="gv" id="cadreLabel">-</div>
      </div>
      <div style="border-left:1px solid #d7dfea;height:38px;"></div>
      <div>
        <div class="gi">Numero d'audit (auto)</div>
        <div class="gv"><span class="num-badge" id="numPreview">...</span></div>
      </div>
      <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-arrow-left me-1"></i>Changer la nature / le cadre</a>
    </div>

    <div class="form-section">Designation</div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Responsable de l'audit <span class="text-danger">*</span></label>
        <div class="d-flex gap-2 align-items-start">
          <div style="flex:1 1 auto"><select id="d_resp" style="width:100%"></select></div>
          <button type="button" class="btn btn-outline-primary add-btn" id="addInsp" title="Ajouter un inspecteur"><i class="bi bi-plus-lg"></i></button>
        </div>
        <div class="form-text">Seuls les inspecteurs non stagiaires peuvent etre responsables.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Statut <span class="text-danger">*</span></label>
        <div id="statutZone">
          <!-- Contenu genere dynamiquement selon la nature de la supervision -->
        </div>
        <input type="hidden" id="d_statut" value="1">
      </div>
    </div>

    <div class="form-section">Operateur concerne</div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Operateur <span class="text-danger">*</span></label>
        <div class="d-flex gap-2 align-items-start">
          <div style="flex:1 1 auto"><select id="d_orga" style="width:100%"></select></div>
          <button type="button" class="btn btn-outline-primary add-btn" id="addOrga" title="Ajouter un operateur"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Type d'activite de l'operateur
          <i class="bi bi-info-circle text-muted" title="Categorie de l'operateur (type d'organisme)"></i>
        </label>
        <div class="d-flex gap-2 align-items-start">
          <div style="flex:1 1 auto"><select id="d_typeorga" style="width:100%"></select></div>
          <button type="button" class="btn btn-outline-primary add-btn" id="addType" title="Ajouter un type d'organisme"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
    </div>

    <div class="form-section">Lieu et planification</div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Site d'inspection <span class="text-danger">*</span>
          <i class="bi bi-info-circle text-muted" title="Site identifie par son indicateur OACI (ex: FOOL)"></i>
        </label>
        <div class="d-flex gap-2 align-items-start">
          <div style="flex:1 1 auto"><select id="d_site" style="width:100%"></select></div>
          <button type="button" class="btn btn-outline-primary add-btn" id="addSite" title="Ajouter un site"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Date previsionnelle</label>
        <input type="date" class="form-control" id="d_dprev">
        <div class="form-text">Facultatif.</div>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="d_notif" checked>
          <label class="form-check-label small" for="d_notif">Notifier par mail</label>
        </div>
      </div>
    </div>

    <div class="form-section">Equipe d'audit</div>
    <div class="form-text mb-2">Ajoutez un ou plusieurs inspecteurs. Pour chacun, choisissez-le dans la liste : ses domaines habilites apparaissent aussitot. Cochez le ou les domaines, puis les reglements vises. Les habilitations expirees sont en rouge et non selectionnables.</div>
    <div id="eqList"></div>
    <button type="button" class="btn btn-outline-primary btn-sm mt-1" id="eqAddRow"><i class="bi bi-person-plus me-1"></i>Ajouter un inspecteur</button>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-light">Annuler</a>
      <button type="submit" class="btn btn-anac" id="decSubmit" disabled><i class="bi bi-check-lg me-1"></i>Enregistrer le declenchement</button>
    </div>
    <div id="mailProgress" style="display:none;margin-top:12px">
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="spinner-border spinner-border-sm text-primary"></span>
        <span class="small text-muted" id="mailProgressTxt">Enregistrement en cours...</span>
      </div>
      <div class="progress" style="height:6px;border-radius:3px">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:100%"></div>
      </div>
    </div>
  </form>
</div>

<!-- ===== MODALE : ajouter un operateur ===== -->
<div class="modal fade" id="orgaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="orgaForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Nouvel operateur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Raison sociale <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="o_nom" maxlength="255" required>
          <div class="form-text" id="o_dup" style="display:none;color:#D32F2F;">Cet operateur existe deja.</div></div>
        <div class="mb-1"><label class="form-label">Sigle</label><input type="text" class="form-control" id="o_sigle" maxlength="70"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="o_submit">Ajouter</button></div>
    </form>
  </div>
</div>

<!-- ===== MODALE : ajouter un type d'organisme ===== -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="typeForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Nouveau type d'organisme</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Nom du type <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="t_nom" maxlength="255" required placeholder="ex : Compagnie aerienne">
        <div class="form-text" id="t_dup" style="display:none;color:#D32F2F;">Ce type existe deja.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="t_submit">Ajouter</button></div>
    </form>
  </div>
</div>

<!-- ===== MODALE : ajouter un site ===== -->
<div class="modal fade" id="siteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="siteForm" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Nouveau site</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-5"><label class="form-label">Indicateur OACI <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="si_oaci" maxlength="10" placeholder="FOOL">
            <div class="form-text" id="si_dup" style="display:none;color:#D32F2F;">Existe deja.</div></div>
          <div class="col-7"><label class="form-label">Nom du site <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="si_nom" maxlength="150"></div>
          <div class="col-12"><label class="form-label">Ville</label><input type="text" class="form-control" id="si_ville" maxlength="150"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-anac" id="si_submit">Ajouter</button></div>
    </form>
  </div>
</div>

<!-- ===== MODALE : Nouveau(x) reglement(s) pour un domaine ===== -->
<div class="modal fade" id="regModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content" id="regForm" autocomplete="off">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white">
          <i class="bi bi-journal-plus me-2" style="color:#F3C300"></i>
          Nouveau(x) reglement(s) - domaine <span id="regDomName" style="color:#F3C300;font-weight:700"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="reg_dom">
        <div class="alert alert-info py-2 small mb-3">
          <i class="bi bi-info-circle me-1"></i>
          Saisissez un ou plusieurs reglements. Chaque ligne = un reglement. Ils seront crees et coches automatiquement dans la liste.
        </div>
        <div id="regRows"></div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="regAddRow">
          <i class="bi bi-plus-lg me-1"></i>Ajouter une ligne
        </button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="reg_submit">
          <i class="bi bi-check-lg me-1"></i>Enregistrer et cocher
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE NOTIFICATION DIRECTEUR (inspecteurs ANAC internes uniquement) -->
<div class="modal fade" id="dirModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-person-badge me-2" style="color:#F3C300"></i>Notification du directeur</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <input type="hidden" id="dir_idinspecteur" value="">
        <div class="alert alert-light py-2 small mb-3" style="border-left:4px solid #23408F">
          <i class="bi bi-info-circle me-1"></i>Vous pouvez informer par email le directeur fonctionnel de l'inspecteur <b id="dir_inspNom"></b> de son affectation a cet acte de supervision.
        </div>
        <div id="dir_zoneTrouve" style="display:none">
          <div class="mb-2">
            <label class="form-label mb-1">Directeur fonctionnel</label>
            <div class="p-2" style="background:#f5f7fa;border-radius:8px">
              <div style="font-weight:700;color:#23408F" id="dir_nom">-</div>
              <div class="small text-muted" id="dir_fonction">-</div>
              <div class="small text-muted" id="dir_direction">-</div>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1">Email du directeur</label>
            <input type="email" class="form-control" id="dir_email" maxlength="100" placeholder="prenom.nom@anac-gabon.com">
            <div class="form-text">Si l'email est vide ou a corriger, saisissez-le : il sera enregistre pour les prochaines fois.</div>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="dir_notifier" checked>
            <label class="form-check-label" for="dir_notifier">Notifier ce directeur par email au declenchement</label>
          </div>
          <div class="form-check" style="background:#fff8e6;border:1px solid #f3e2a8;border-radius:8px;padding:8px 8px 8px 34px">
            <input class="form-check-input" type="checkbox" id="dir_interim">
            <label class="form-check-label" for="dir_interim"><i class="bi bi-arrow-left-right me-1" style="color:#b58a00"></i><b>Directeur absent</b> : notifier plutot l'interimaire</label>
            <div class="form-text mb-0">Si le directeur est en conge, cochez pour choisir la personne qui assure l'interim.</div>
          </div>
        </div>
        <div id="dir_zoneFallback" style="display:none">
          <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Aucun directeur n'a ete trouve automatiquement pour cet inspecteur. Vous pouvez le choisir dans la liste du personnel ANAC.</div>
          <div class="mb-2">
            <label class="form-label mb-1">Choisir dans le personnel ANAC</label>
            <select id="dir_personnel" style="width:100%"></select>
          </div>
          <div id="dir_persDetail" class="p-2 mb-2" style="background:#f5f7fa;border-radius:8px;display:none">
            <div class="small text-muted" id="dir_persFonction">-</div>
            <div class="small text-muted" id="dir_persDirection">-</div>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1">Email</label>
            <input type="email" class="form-control" id="dir_persEmail" maxlength="100" placeholder="prenom.nom@anac-gabon.com">
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="dir_persNotifier">
            <label class="form-check-label" for="dir_persNotifier">Notifier cette personne par email au declenchement</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Passer</button>
        <button type="button" class="btn btn-anac" id="dir_valider"><i class="bi bi-check-lg me-1"></i>Valider</button>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>

<script>
const CSRF       = '<?php echo Security::escape($csrf); ?>';
const API_AUDITS = AGAI_BASE + '/api/audits';
const API_EXP    = AGAI_BASE + '/api/exploitants';
const API_TYPE   = AGAI_BASE + '/api/typesorganisme';
const API_SITE   = AGAI_BASE + '/api/sites';
const API_REG    = AGAI_BASE + '/api/reglements';
const SEL_TYPE   = '<?php echo Security::escape($type); ?>';
const SEL_CADRE  = '<?php echo Security::escape($cadre); ?>';

/* ===== Gestion du champ statut selon la nature de supervision ===== */
function buildStatutZone(){
  const zone = $('#statutZone');
  if(SEL_TYPE === 'inspection_non_programmee'){
    // Inspection non programmee : Planifiee OU Inopinee (2 radios)
    zone.html(
      '<div class="statut-radio-group">'
      + '<input type="radio" name="statut_radio" id="sr_1" value="1" checked>'
      + '<label for="sr_1"><span class="statut-pill pill-plan"></span>Planifiee</label>'
      + '<input type="radio" name="statut_radio" id="sr_inop" value="7">'
      + '<label for="sr_inop"><span class="statut-pill pill-inop"></span>Inopinee</label>'
      + '</div>'
      + '<div class="form-text">Planifiee = date prevue connue. Inopinee = sans preavis.</div>'
    );
    $('input[name="statut_radio"]').on('change', function(){
      $('#d_statut').val($(this).val());
    });
    // Initialiser apres le bind pour ne pas ecraser un clic precoce
    if(!$('input[name="statut_radio"]:checked').length){
      $('#d_statut').val(1);
    } else {
      $('#d_statut').val($('input[name="statut_radio"]:checked').val());
    }
  } else {
    // Tous les autres types (audit, inspection_programmee, demonstration, test, investigation)
    // Statut Planifiee par defaut, affiche en badge non modifiable
    zone.html(
      '<div class="statut-radio-group">'
      + '<input type="radio" name="statut_radio" id="sr_1" value="1" checked disabled>'
      + '<label for="sr_1" style="border-color:#23408F;background:rgba(35,64,143,.08);color:#23408F;cursor:default">'
      + '<span class="statut-pill pill-plan"></span>Planifiee</label>'
      + '</div>'
      + '<div class="form-text">Le statut initial est toujours Planifiee pour ce type de supervision.</div>'
    );
    $('#d_statut').val(1);
  }
}
buildStatutZone();

const TYPE_LABELS = {audit:'Audit', inspection_programmee:'Inspection programmee', inspection_non_programmee:'Inspection non programmee', demonstration:'Demonstration', test:'Test', investigation:'Investigation'};
// Valeurs de pre-remplissage transmises par le Programme PSC
const PREFILL = {
  idorga: <?= (int)$prefillOrga ?>,
  idsite: <?= (int)$prefillSite ?>,
  idtypeorga: <?= (int)$prefillType ?>,
  idprog: <?= (int)$prefillProg ?>,
  dprev: <?= json_encode($prefillDprev) ?>
};
const CADRE_LABELS = {certification:'Certification', homologation:'Homologation', reconnaissance:'Reconnaissance', renouvellement:'Renouvellement', surveillance_continue:'Surveillance continue', traitement_evenement:"Traitement d'un evenement", fermeture_provisoire:'Fermeture provisoire', fermeture_definitive:'Fermeture definitive', delivrance_autorisation:"Delivrance d'une autorisation"};

function post(url, data){ data = Object.assign({csrf_token: CSRF}, data); return $.post(url, data, null, 'json'); }

$('#natLabel').text(TYPE_LABELS[SEL_TYPE] || SEL_TYPE);
$('#cadreLabel').text(CADRE_LABELS[SEL_CADRE] || SEL_CADRE);

/* Apercu du numero d'audit auto */
post(API_AUDITS, {action:'next_num', type_activite:SEL_TYPE}).done(res => {
  if(res.success) $('#numPreview').text(res.num_audit);
});

/* ---------- Chargement des listes ---------- */
let RESP_READY = false;
function fillResp(inspecteurs){
  const list = (inspecteurs || []).filter(i => String(i.categorie) !== 'stagiaire');
  let opts = '<option value="">Choisir un responsable...</option>';
  list.forEach(i => { opts += '<option value="'+esc(i.idinspecteur)+'">'+esc(i.nom)+(i.trigr_inspecteur?' ('+esc(i.trigr_inspecteur)+')':'')+'</option>'; });
  $('#d_resp').html(opts);
}
function fillOrga(exploitants, keep){
  let opts = '<option value="">Choisir un operateur...</option>';
  (exploitants||[]).forEach(o => { opts += '<option value="'+esc(o.idorga)+'">'+esc(o.nomorga)+(o.trigrorganisme?' ('+esc(o.trigrorganisme)+')':'')+'</option>'; });
  $('#d_orga').html(opts); if(keep) $('#d_orga').val(String(keep));
}
function fillType(types, keep){
  let opts = '<option value="">Non precise</option>';
  (types||[]).forEach(t => { opts += '<option value="'+esc(t.idtypeorga)+'">'+esc(t.nomtypeorg)+'</option>'; });
  $('#d_typeorga').html(opts); if(keep) $('#d_typeorga').val(String(keep));
}
function fillSite(sites, keep){
  let opts = '<option value="">Choisir un site...</option>';
  (sites||[]).forEach(s => { opts += '<option value="'+esc(s.idsite)+'">'+esc(s.indicateur_oaci)+' - '+esc(s.nomsite)+'</option>'; });
  $('#d_site').html(opts); if(keep) $('#d_site').val(String(keep));
}
function s2(id){ $(id).select2({theme:'bootstrap-5', width:'100%'}); }

function loadLists(){
  return post(API_AUDITS, {action:'lists'}).done(res => {
    if(!res.success) return;
    fillResp(res.inspecteurs);
    fillOrga(res.exploitants);
    fillType(res.types_orga);
    fillSite(res.sites);
    s2('#d_resp'); s2('#d_orga'); s2('#d_typeorga'); s2('#d_site');
    $('#d_resp').on('change', function(){ refreshRaBadges(); validateForm(); });
    $('#d_orga, #d_site').on('change', validateForm);
    // Equipe (1C)
    INSP_ALL = res.inspecteurs || [];
    REG_BY_DOM = {};
    (res.reglements || []).forEach(r => { (REG_BY_DOM[r.iddomaine] = REG_BY_DOM[r.iddomaine] || []).push(r); });
    RESP_READY = true;
    validateForm();
    applyPrefill();
  });
}
loadLists();

// Au retour sur cet onglet (apres avoir cree un inspecteur ailleurs), on met a
// jour UNIQUEMENT la liste des inspecteurs, sans recharger le reste et sans
// perdre la moindre donnee deja saisie. On ajoute seulement les inspecteurs
// nouvellement crees aux listes deroulantes existantes (responsable + equipe).
function majInspecteurs(){
  if(!RESP_READY) return;
  post(API_AUDITS, {action:'lists'}).done(function(res){
    if(!res || !res.success || !Array.isArray(res.inspecteurs)) return;
    const avant = new Set((INSP_ALL||[]).map(function(i){ return String(i.idinspecteur); }));
    const nouveaux = res.inspecteurs.filter(function(i){ return !avant.has(String(i.idinspecteur)); });
    if(!nouveaux.length) return;              // rien de nouveau : on ne touche a rien
    INSP_ALL = res.inspecteurs;               // on garde la liste de reference a jour

    // Construit les <option> a ajouter (uniquement les nouveaux)
    const optsSup = nouveaux.map(function(i){
      return '<option value="'+esc(i.idinspecteur)+'">'+esc(i.nom)
           + (i.trigr_inspecteur?' ('+esc(i.trigr_inspecteur)+')':'')+'</option>';
    }).join('');

    // Responsable : on ajoute les nouveaux sans changer la valeur choisie
    const $resp = $('#d_resp');
    const valResp = $resp.val();
    $resp.append(optsSup);
    if(valResp){ $resp.val(valResp); }
    $resp.trigger('change.select2');

    // Chaque carte d'equipe : on ajoute les nouveaux a sa liste, choix preserve
    $('.insp-sel').each(function(){
      const $s = $(this);
      const v = $s.val();
      $s.append(optsSup);
      if(v){ $s.val(v); }
      $s.trigger('change.select2');
    });
  });
}
// visibilitychange est plus fiable que focus pour detecter le retour d'onglet
document.addEventListener('visibilitychange', function(){
  if(document.visibilityState === 'visible'){ majInspecteurs(); }
});
window.addEventListener('focus', majInspecteurs);

/* Pre-remplissage depuis le Programme PSC (operateur ou site + type + date). */
function applyPrefill(){
  let changed=false;
  // Date previsionnelle (approx. lundi de la semaine ISO transmise)
  if(PREFILL.dprev && !$('#d_dprev').val()){ $('#d_dprev').val(PREFILL.dprev); changed=true; }
  // Operateur (mode operateur)
  if(PREFILL.idorga>0){
    $('#d_orga').val(String(PREFILL.idorga)).trigger('change.select2');
    $('#d_orga').trigger('change');
    changed=true;
  }
  // Type d'activite (operateur)
  if(PREFILL.idtypeorga>0){
    $('#d_typeorga').val(String(PREFILL.idtypeorga)).trigger('change.select2');
    changed=true;
  }
  // Site (mode site)
  if(PREFILL.idsite>0){
    $('#d_site').val(String(PREFILL.idsite)).trigger('change.select2');
    $('#d_site').trigger('change');
    changed=true;
  }
  if(changed){ validateForm(); }
}

/* ===== Equipe d'audit (1C) : inspecteurs par domaine habilite ===== */
let REG_BY_DOM = {};
let INSP_ALL = [];
let eqSeq = 0;
let regTargetEl = null;

function fmtDate(s){ if(!s) return ''; const p = String(s).split('-'); return p.length === 3 ? (p[2]+'/'+p[1]+'/'+p[0]) : s; }

function inspOptions(){
  let opts = '<option value="">Choisir un inspecteur...</option>';
  INSP_ALL.forEach(i => { opts += '<option value="'+esc(i.idinspecteur)+'">'+esc(i.nom)+(i.trigr_inspecteur?' ('+esc(i.trigr_inspecteur)+')':'')+'</option>'; });
  return opts;
}

/* Ajout d'une carte inspecteur (chaque carte a sa propre liste deroulante) */
$('#eqAddRow').on('click', function(){
  const idx = ++eqSeq;
  const card = $(
    '<div class="eq-card" data-idx="'+idx+'" data-insp="">'
    + '<div class="eq-head" style="gap:10px">'
    +   '<div style="flex:1 1 auto"><select class="insp-sel" id="inspSel'+idx+'" style="width:100%"></select></div>'
    +   '<span class="ra-tag"></span>'
    +   '<button type="button" class="btn btn-sm btn-outline-danger eq-remove" title="Retirer cet inspecteur"><i class="bi bi-x-lg"></i></button>'
    + '</div>'
    + '<div class="eq-body"><div class="reg-empty">Choisissez un inspecteur pour afficher ses domaines habilites.</div></div>'
    + '</div>'
  );
  $('#eqList').append(card);
  const sel = card.find('.insp-sel');
  sel.html(inspOptions());
  sel.select2({theme:'bootstrap-5', width:'100%', placeholder:'Choisir un inspecteur...'});
  validateForm();
});

/* Choix de l'inspecteur dans une carte : ses domaines s'affichent aussitot */
$(document).on('change', '.insp-sel', function(){
  const card = $(this).closest('.eq-card');
  const id = $(this).val();
  if(id){
    let dup = false;
    $('#eqList .eq-card').each(function(){ if(!$(this).is(card) && String($(this).attr('data-insp')) === String(id)) dup = true; });
    if(dup){
      Swal.fire({icon:'info',title:'Deja dans l\'equipe',text:'Cet inspecteur est deja present dans une autre ligne.',confirmButtonColor:'#23408F'});
      $(this).val('').trigger('change.select2');
      card.attr('data-insp','');
      card.find('.eq-body').html('<div class="reg-empty">Choisissez un inspecteur pour afficher ses domaines habilites.</div>');
      refreshRaBadges(); validateForm();
      return;
    }
  }
  card.attr('data-insp', id || '');
  const body = card.find('.eq-body');
  if(!id){ body.html('<div class="reg-empty">Choisissez un inspecteur pour afficher ses domaines habilites.</div>'); refreshRaBadges(); validateForm(); return; }
  body.html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Chargement des domaines habilites...</div>');
  post(API_AUDITS, {action:'insp_domaines', idinspecteur:id}).done(res => {
    if(!res.success){ body.html('<div class="text-danger small">Erreur de chargement des domaines.</div>'); return; }
    const doms = res.domaines || res.data || [];
    if(!doms.length){ body.html('<div class="reg-empty">Aucun domaine habilite pour cet inspecteur.</div>'); validateForm(); return; }
    body.html(doms.map(domRow).join(''));
    validateForm();
    // Notification du directeur : uniquement pour les inspecteurs ANAC (internes).
    // Les inspecteurs externes (autres ANAC) n'ont pas de directeur fonctionnel ANAC.
    const insp = (INSP_ALL||[]).find(function(x){ return String(x.idinspecteur)===String(id); });
    const estExterne = insp && String(insp.categorie)==='externe';
    if(!estExterne){ openDirModal(id, insp ? insp.nom : ''); }
  }).fail(()=>{ body.html('<div class="text-danger small">Echec de chargement.</div>'); });
  refreshRaBadges();
});

/* ===== Notification du directeur (inspecteurs ANAC internes) ===== */
let DIR_NOTIFS = {}; // idinspecteur -> {idpersonnel, nom, email, notifier}
let DIR_CTX = {}; // idinspecteur -> {codedirec, direction} pour le fallback / interim
function openDirModal(idinspecteur, nomInsp){
  $('#dir_idinspecteur').val(idinspecteur);
  $('#dir_inspNom').text(nomInsp||'');
  $('#dir_zoneTrouve, #dir_zoneFallback').hide();
  $('#dir_email').val(''); $('#dir_notifier').prop('checked', true);
  $('#dir_persEmail').val(''); $('#dir_persNotifier').prop('checked', false);
  $('#dir_persDetail').hide();
  $('#dir_interim').prop('checked', false);
  // Recherche du directeur fonctionnel
  post(API_AUDITS, {action:'insp_directeur', idinspecteur:idinspecteur}).done(function(res){
    if(res && res.success){
      DIR_CTX[idinspecteur] = {codedirec: res.codedirec||0, direction: res.direction||''};
      if(res.trouve){
        $('#dir_idinspecteur').data('idpersonnel', res.idpersonnel||'');
        $('#dir_nom').text(res.nom||'-');
        $('#dir_fonction').text(res.fonction||'-');
        $('#dir_direction').text(res.direction||'-');
        $('#dir_email').val(res.email||'');
        $('#dir_zoneTrouve').show();
      } else {
        $('#dir_zoneFallback').show();
        loadDirPersonnel(idinspecteur);
      }
    } else {
      $('#dir_zoneFallback').show(); loadDirPersonnel(idinspecteur);
    }
    new bootstrap.Modal('#dirModal').show();
  }).fail(function(){
    $('#dir_zoneFallback').show(); loadDirPersonnel(idinspecteur);
    new bootstrap.Modal('#dirModal').show();
  });
}
function loadDirPersonnel(idinspecteur){
  const ctx = DIR_CTX[idinspecteur] || {};
  function build(list){
    let opts = '<option value="">Choisir une personne...</option>';
    (list||[]).forEach(function(p){
      opts += '<option value="'+esc(p.idpersonnel)+'" data-fonction="'+esc(p.fonction||'')+'" data-direction="'+esc(p.direction||'')+'" data-email="'+esc(p.email||'')+'">'
           + esc(p.nom||'') + (p.direction?(' - '+esc(p.direction)):'') + '</option>';
    });
    const $s = $('#dir_personnel');
    if($s.hasClass('select2-hidden-accessible')) $s.select2('destroy');
    $s.html(opts).select2({theme:'bootstrap-5', width:'100%', dropdownParent:$('#dirModal'), placeholder:'Choisir une personne...'});
  }
  post(API_AUDITS, {action:'personnel_direction', codedirec: ctx.codedirec||0}).done(function(res){
    build((res && res.success) ? (res.personnel||[]) : []);
  }).fail(function(){ build([]); });
}
$(document).on('change', '#dir_personnel', function(){
  const opt = this.options[this.selectedIndex];
  if(opt && opt.value){
    $('#dir_persFonction').text(opt.getAttribute('data-fonction')||'-');
    $('#dir_persDirection').text(opt.getAttribute('data-direction')||'-');
    $('#dir_persEmail').val(opt.getAttribute('data-email')||'');
    $('#dir_persDetail').show();
    $('#dir_persNotifier').prop('checked', true);
  } else { $('#dir_persDetail').hide(); }
});
/* Directeur absent -> choisir l'interimaire dans le personnel ANAC */
$(document).on('change', '#dir_interim', function(){
  const idinsp = $('#dir_idinspecteur').val();
  if(this.checked){
    // On masque le directeur trouve, on affiche la liste pour choisir l'interimaire
    $('#dir_zoneTrouve').hide();
    $('#dir_zoneFallback').show();
    $('#dir_zoneFallback .alert-warning').html('<i class="bi bi-arrow-left-right me-1"></i>Le directeur est absent : choisissez la personne qui assure l\'interim de la Direction.');
    loadDirPersonnel(idinsp);
  } else {
    $('#dir_zoneFallback').hide();
    $('#dir_zoneTrouve').show();
  }
});
$('#dir_valider').on('click', function(){
  const idinsp = $('#dir_idinspecteur').val();
  if(!idinsp){ bootstrap.Modal.getInstance(document.getElementById('dirModal')).hide(); return; }
  let idpersonnel='', nom='', email='', notifier=false;
  if($('#dir_zoneTrouve').is(':visible')){
    idpersonnel = $('#dir_idinspecteur').data('idpersonnel') || '';
    nom = $('#dir_nom').text();
    email = ($('#dir_email').val()||'').trim();
    notifier = $('#dir_notifier').is(':checked');
    // Sauvegarde de l'email s'il a ete saisi/modifie
    if(email && idpersonnel){ post(API_AUDITS, {action:'maj_email_directeur', idpersonnel:idpersonnel, email:email}); }
  } else {
    const opt = document.getElementById('dir_personnel');
    idpersonnel = opt ? opt.value : '';
    nom = opt && opt.selectedIndex>=0 ? opt.options[opt.selectedIndex].text : '';
    email = ($('#dir_persEmail').val()||'').trim();
    notifier = $('#dir_persNotifier').is(':checked');
    if(email && idpersonnel){ post(API_AUDITS, {action:'maj_email_directeur', idpersonnel:idpersonnel, email:email}); }
  }
  if(notifier && email){
    DIR_NOTIFS[idinsp] = {idpersonnel:idpersonnel, nom:nom, email:email, notifier:true};
  } else {
    delete DIR_NOTIFS[idinsp];
  }
  bootstrap.Modal.getInstance(document.getElementById('dirModal')).hide();
});

function domRow(d){
  const expired = Number(d.est_expire || d.expired) === 1;
  const code = esc(d.nomdomaine) + (d.libel_domaine ? ' - ' + esc(d.libel_domaine) : '');
  const tag = expired
    ? '<span class="exp-tag">Habilitation expiree le '+esc(fmtDate(d.date_expiration))+'</span>'
    : '<span class="ok-tag">Valide jusqu\'au '+esc(fmtDate(d.date_expiration))+'</span>';
  return '<div class="eq-dom'+(expired?' expired':'')+'" data-dom="'+esc(d.iddomaine)+'" data-domname="'+esc(d.nomdomaine)+'">'
    + '<div class="dom-line">'
    +   '<input class="form-check-input dom-chk" type="checkbox"'+(expired?' disabled':'')+'>'
    +   '<span class="dom-code">'+code+'</span>'+tag
    + '</div>'
    + '<div class="reg-list" style="display:none">'
    +   '<div class="reg-select-wrap mt-2">'
    +     '<label class="form-label small fw-bold mb-1" style="color:#23408F"><i class="bi bi-journal-text me-1"></i>Reglements vises (cochez un ou plusieurs)</label>'
    +     '<select class="reg-select2" data-dom="'+esc(d.iddomaine)+'" multiple="multiple" style="width:100%"></select>'
    +     '<div class="d-flex justify-content-between align-items-center mt-1">'
    +       '<span class="reg-count small text-muted">0 reglement(s) selectionne(s)</span>'
    +       '<button type="button" class="btn btn-xs btn-outline-secondary reg-add" style="font-size:.74rem;padding:2px 8px"><i class="bi bi-plus me-1"></i>Nouveau reglement</button>'
    +     '</div>'
    +   '</div>'
    + '</div>'
    + '</div>';
}

// Vide intentionnellement - remplace regListHtml et regCheck (plus utilises)
function regListHtml(dom){ return ''; }
function regCheck(dom, r){ return ''; }

// Charger les reglements dans le Select2 quand un domaine est coche
$(document).on('change', '.dom-chk', function(){
  const $dom = $(this).closest('.eq-dom');
  const checked = $(this).is(':checked');
  $dom.find('.reg-list').toggle(checked);
  if(checked){
    const domId = $dom.data('dom');
    const $sel = $dom.find('.reg-select2');
    // Initialiser Select2 si pas encore fait
    if(!$sel.hasClass('select2-hidden-accessible')){
      $sel.select2({
        theme:'bootstrap-5',
        placeholder:'Rechercher et selectionner des reglements...',
        allowClear:true,
        width:'100%',
        language:{
          noResults:function(){ return 'Aucun reglement trouve. Utilisez "+ Nouveau reglement".'; },
          searching:function(){ return 'Recherche en cours...'; },
          inputTooShort:function(){ return ''; }
        }
      });
      $sel.on('change',function(){
        const n=$(this).val()||[];
        $dom.find('.reg-count').text(n.length+' reglement(s) selectionne(s)');
        validateForm();
      });
    }
    // Peupler si vide
    if($sel.find('option').length === 0){
      populateRegSelect($sel, domId);
    }
  }
  validateForm();
});

function populateRegSelect($sel, domId){
  const regs = REG_BY_DOM[domId] || [];
  $sel.empty();
  if(regs.length){
    regs.forEach(function(r){
      const opt = new Option(r.code_reglement+' - '+r.libelle_reglement, r.idreglement, false, false);
      $sel.append(opt);
    });
  } else {
    // Message vide - le bouton "+ Nouveau reglement" permettra d'en creer
    const opt = new Option('Aucun reglement pour ce domaine', '', false, false);
    opt.disabled = true;
    $sel.append(opt);
  }
  $sel.trigger('change');
}

$(document).on('click', '.eq-remove', function(){
  const idInsp = $(this).closest('.eq-card').attr('data-insp');
  if(idInsp && DIR_NOTIFS[idInsp]){ delete DIR_NOTIFS[idInsp]; }
  $(this).closest('.eq-card').remove(); refreshRaBadges(); validateForm();
});

function refreshRaBadges(){
  const ra = $('#d_resp').val();
  $('#eqList .eq-card').each(function(){
    const isRa = ra && String($(this).attr('data-insp')) === String(ra);
    $(this).find('.ra-tag').html(isRa ? '<span class="ra-badge">Responsable</span>' : '');
  });
}

/* ----- Fonction template d'une ligne de saisie reglement ----- */
function regRowHtml(){
  return '<div class="reg-row mb-2" style="background:#f8fafc;border:1px solid #e8edf5;border-radius:10px;padding:10px 12px">'
    +'<div class="row g-2 align-items-center">'
    +'<div class="col-4">'
    +'<label class="form-label small fw-bold mb-1">Code <span class="text-danger">*</span></label>'
    +'<input type="text" class="form-control form-control-sm rr-code" placeholder="ex: RAG-OPS-1.1" style="font-family:monospace;font-weight:700">'
    +'</div>'
    +'<div class="col-7">'
    +'<label class="form-label small fw-bold mb-1">Libelle <span class="text-danger">*</span></label>'
    +'<input type="text" class="form-control form-control-sm rr-lib" placeholder="Description du reglement">'
    +'</div>'
    +'<div class="col-1 d-flex align-items-end pb-1">'
    +'<button type="button" class="btn btn-sm btn-outline-danger rr-del w-100" title="Supprimer cette ligne"><i class="bi bi-trash"></i></button>'
    +'</div>'
    +'</div>'
    +'</div>';
}

/* ----- Bouton + nouveau reglement : ouvre la modale ----- */
$(document).on('click', '.reg-add', function(){
  regTargetEl = $(this).closest('.eq-dom');
  const domId = regTargetEl.data('dom');
  const domNom = regTargetEl.data('domname') || '';
  $('#reg_dom').val(domId);
  $('#regDomName').text(domNom);
  $('#regRows').html(regRowHtml()); // Au moins une ligne vide
  new bootstrap.Modal('#regModal').show();
  setTimeout(function(){ $('#regRows .rr-code').first().focus(); }, 400);
});
$('#regAddRow').on('click', function(){ $('#regRows').append(regRowHtml()); });
$(document).on('click', '.rr-del', function(){ if($('#regRows .reg-row').length > 1){ $(this).closest('.reg-row').remove(); } });

$('#regForm').on('submit', function(e){
  e.preventDefault();
  const dom = $('#reg_dom').val();
  const codes = [], libs = [];
  $('#regRows .reg-row').each(function(){
    const code = $(this).find('.rr-code').val().trim();
    const lib  = $(this).find('.rr-lib').val().trim();
    if(code && lib){ codes.push(code); libs.push(lib); }
  });
  if(!codes.length){ Swal.fire({icon:'warning',title:'Rien a ajouter',text:'Saisissez au moins un code et un libelle.',confirmButtonColor:'#23408F'}); return; }
  const btn = $('#reg_submit'); btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  // Envoi en une seule requete (un seul token CSRF) via create_batch
  post(API_REG, {action:'create_batch', iddomaine:dom, 'codes[]':codes, 'libelles[]':libs}).done(resp => {
    btn.prop('disabled', false).html('Enregistrer');
    if(resp.success){
      bootstrap.Modal.getInstance(document.getElementById('regModal')).hide();
      finishReg(dom, resp.inserted || []);
      if(resp.errors && resp.errors.length){
        Swal.fire({icon:'warning',title:resp.inserted.length+' insere(s)',html:'Problemes : <b>'+esc(resp.errors.join(', '))+'</b>',confirmButtonColor:'#23408F'});
      }
    } else { Swal.fire({icon:'error',title:'Erreur',text:resp.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled', false).html('Enregistrer'); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});
function finishReg(dom, created){
  if(!created.length){ Swal.fire({icon:'error',title:'Echec',text:'Aucun reglement n\'a pu etre ajoute (doublon de code ?).',confirmButtonColor:'#23408F'}); return; }
  REG_BY_DOM[dom] = REG_BY_DOM[dom] || [];
  created.forEach(c => REG_BY_DOM[dom].push(c));
  if(regTargetEl){
    // Ajouter les nouveaux reglements au Select2 du domaine
    const $sel = regTargetEl.find('.reg-select2');
    if($sel.hasClass('select2-hidden-accessible')){
      created.forEach(function(c){
        const opt = new Option(c.code_reglement+' - '+c.libelle_reglement, c.idreglement, true, true);
        $sel.append(opt);
      });
      $sel.trigger('change');
    } else {
      // Select2 pas encore initialise : repeupler
      populateRegSelect($sel, dom);
      created.forEach(function(c){ $sel.find('option[value="'+c.idreglement+'"]').prop('selected', true); });
      $sel.trigger('change');
    }
    const n = $sel.val()||[];
    regTargetEl.find('.reg-count').text(n.length+' reglement(s) selectionne(s)');
  }
  validateForm();
}

function gatherTeam(){
  const eqInsp = [], eqDom = [], eqRegs = {}; let idx = 0;
  $('#eqList .eq-card').each(function(){
    const insp = $(this).attr('data-insp');
    if(!insp) return;
    $(this).find('.eq-dom').each(function(){
      const chk = $(this).find('.dom-chk');
      if(chk.is(':checked') && !chk.is(':disabled')){
        eqInsp.push(insp); eqDom.push($(this).data('dom'));
        // Lire le Select2 multi-select
        const $sel = $(this).find('.reg-select2');
        const rids = ($sel.hasClass('select2-hidden-accessible') ? ($sel.val()||[]) : []);
        eqRegs[idx] = rids; idx++;
      }
    });
  });
  return {eqInsp, eqDom, eqRegs};
}

/* Bouton Enregistrer grise dynamiquement tant que le formulaire n'est pas valide */
function hasExpiredSelected(){
  // Bloque si une carte a un inspecteur selectionne ET au moins un domaine expire visible
  let found = false;
  $('#eqList .eq-card').each(function(){
    if(!$(this).attr('data-insp')) return;
    // Un domaine expire dans cette carte = blocage
    if($(this).find('.eq-dom.expired').length){ found = true; }
  });
  return found;
}
function validateForm(){
  const ok = !!$('#d_resp').val() && !!$('#d_orga').val() && !!$('#d_site').val() && !hasExpiredSelected();
  $('#decSubmit').prop('disabled', !ok);
  if(hasExpiredSelected()){
    $('#decSubmit').attr('title', 'Un inspecteur a une habilitation expiree. Retirez-le ou choisissez un autre.');
  } else {
    $('#decSubmit').removeAttr('title');
  }
}

/* ---------- Bouton + : ajouter un operateur ---------- */
$('#addOrga').on('click', function(){ $('#o_nom').val(''); $('#o_sigle').val(''); $('#o_dup').hide(); new bootstrap.Modal('#orgaModal').show(); });

/* ---------- Bouton + : ajouter un inspecteur ---------- */
// La creation d'un inspecteur requiert des donnees reglementaires (categorie,
// direction, habilitations par domaine). On ouvre le module dedie Inspecteurs
// dans un nouvel onglet ; au retour, les listes se rechargent automatiquement.
$('#addInsp').on('click', function(){
  Swal.fire({
    icon:'info',
    title:'Ajouter un inspecteur',
    html:'La creation d\'un inspecteur (categorie, direction, habilitations par domaine) '
       + 'se fait dans le module <b>Inspecteurs</b>. Il s\'ouvre dans un nouvel onglet.<br><br>'
       + 'Une fois l\'inspecteur cree, revenez ici : la liste se mettra a jour automatiquement.',
    showCancelButton:true,
    confirmButtonText:'Ouvrir le module Inspecteurs',
    cancelButtonText:'Annuler',
    confirmButtonColor:'#23408F'
  }).then(function(r){
    if(r.isConfirmed){ window.open(AGAI_BASE + '/inspecteurs', '_blank'); }
  });
});
let oDup=null;
$('#o_nom').on('input', function(){
  clearTimeout(oDup); const nom=$(this).val().trim(); if(!nom){ $('#o_dup').hide(); return; }
  oDup=setTimeout(()=>{ post(API_EXP, {action:'check', nomorga:nom, idorga:0}).done(r=>{ $('#o_dup').toggle(!!(r.success&&r.exists)); }); }, 350);
});
$('#orgaForm').on('submit', function(e){
  e.preventDefault();
  const nom=$('#o_nom').val().trim(); if(!nom){ return; }
  const btn=$('#o_submit'); btn.prop('disabled',true);
  post(API_EXP, {action:'create', nomorga:nom, trigrorganisme:$('#o_sigle').val().trim()}).done(r=>{
    btn.prop('disabled',false);
    if(r.success){
      bootstrap.Modal.getInstance(document.getElementById('orgaModal')).hide();
      $('#d_orga').append('<option value="'+esc(r.idorga)+'">'+esc(nom)+'</option>').val(String(r.idorga)).trigger('change');
    } else { Swal.fire({icon:'error',title:'Erreur',text:r.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Bouton + : ajouter un type d'organisme ---------- */
$('#addType').on('click', function(){ $('#t_nom').val(''); $('#t_dup').hide(); new bootstrap.Modal('#typeModal').show(); });
let tDup=null;
$('#t_nom').on('input', function(){
  clearTimeout(tDup); const nom=$(this).val().trim(); if(!nom){ $('#t_dup').hide(); return; }
  tDup=setTimeout(()=>{ post(API_TYPE, {action:'check_nom', nomtypeorg:nom, idtypeorga:0}).done(r=>{ $('#t_dup').toggle(!!(r.success&&r.exists)); }); }, 350);
});
$('#typeForm').on('submit', function(e){
  e.preventDefault();
  const nom=$('#t_nom').val().trim(); if(!nom){ return; }
  const btn=$('#t_submit'); btn.prop('disabled',true);
  post(API_TYPE, {action:'create', nomtypeorg:nom}).done(r=>{
    btn.prop('disabled',false);
    if(r.success){
      bootstrap.Modal.getInstance(document.getElementById('typeModal')).hide();
      $('#d_typeorga').append('<option value="'+esc(r.idtypeorga)+'">'+esc(nom)+'</option>').val(String(r.idtypeorga)).trigger('change');
    } else { Swal.fire({icon:'error',title:'Erreur',text:r.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

/* ---------- Bouton + : ajouter un site ---------- */
$('#addSite').on('click', function(){ $('#si_oaci').val(''); $('#si_nom').val(''); $('#si_ville').val(''); $('#si_dup').hide(); new bootstrap.Modal('#siteModal').show(); });
let siDup=null;
$('#si_oaci').on('input', function(){
  clearTimeout(siDup); const oaci=$(this).val().trim(); if(!oaci){ $('#si_dup').hide(); return; }
  siDup=setTimeout(()=>{ post(API_SITE, {action:'check_oaci', indicateur_oaci:oaci, idsite:0}).done(r=>{ $('#si_dup').toggle(!!(r.success&&r.exists)); }); }, 350);
});
$('#siteForm').on('submit', function(e){
  e.preventDefault();
  const oaci=$('#si_oaci').val().trim(), nom=$('#si_nom').val().trim();
  if(!oaci || !nom){ Swal.fire({icon:'warning',title:'Champs requis',text:'Indicateur OACI et nom du site.',confirmButtonColor:'#23408F'}); return; }
  const btn=$('#si_submit'); btn.prop('disabled',true);
  post(API_SITE, {action:'create', indicateur_oaci:oaci, nomsite:nom, ville:$('#si_ville').val().trim(), idpays:0}).done(r=>{
    btn.prop('disabled',false);
    if(r.success){
      bootstrap.Modal.getInstance(document.getElementById('siteModal')).hide();
      $('#d_site').append('<option value="'+esc(r.idsite)+'">'+esc(oaci.toUpperCase())+' - '+esc(nom)+'</option>').val(String(r.idsite)).trigger('change');
    } else { Swal.fire({icon:'error',title:'Erreur',text:r.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ btn.prop('disabled',false); Swal.fire({icon:'error',title:'Erreur',text:'Echec.',confirmButtonColor:'#23408F'}); });
});

/* Collecte les notifications directeur pour les inspecteurs reellement dans l'equipe */
function gatherDirNotifs(eqInspList){
  const uniques = Array.from(new Set((eqInspList||[]).map(String)));
  const out = [];
  uniques.forEach(function(idInsp){
    const n = DIR_NOTIFS[idInsp];
    if(n && n.notifier && n.email){
      out.push({idinspecteur:idInsp, idpersonnel:n.idpersonnel||'', nom:n.nom||'', email:n.email});
    }
  });
  return out;
}

/* ---------- Enregistrement du declenchement ---------- */
$('#decForm').on('submit', function(e){
  e.preventDefault();
  if(!$('#d_resp').val()){ Swal.fire({icon:'warning',title:'Responsable requis',text:'Choisissez le responsable de l\'audit.',confirmButtonColor:'#23408F'}); return; }
  if(!$('#d_orga').val()){ Swal.fire({icon:'warning',title:'Operateur requis',text:'Choisissez l\'operateur concerne.',confirmButtonColor:'#23408F'}); return; }
  if(!$('#d_site').val()){ Swal.fire({icon:'warning',title:'Site requis',text:'Choisissez le site d\'inspection.',confirmButtonColor:'#23408F'}); return; }
  const team = gatherTeam();
  const data = {
    action:'create', auto_num:1,
    type_activite:SEL_TYPE, cadre:SEL_CADRE,
    idresponsable_audit:$('#d_resp').val(), idorga:$('#d_orga').val(),
    idtypeorga:$('#d_typeorga').val() || 0, idsite:$('#d_site').val(),
    statut:$('#d_statut').val() || 1, date_previsionnelle:$('#d_dprev').val(),
    notif_mail:$('#d_notif').is(':checked') ? 1 : 0,
    eq_inspecteur: team.eqInsp, eq_domaine: team.eqDom, eq_regs_json: JSON.stringify(team.eqRegs),
    dir_notif_json: JSON.stringify(gatherDirNotifs(team.eqInsp))
  };
  const notifActive = $('#d_notif').is(':checked');
  const btn=$('#decSubmit'); const html=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  if(notifActive){
    $('#mailProgressTxt').text('Enregistrement et envoi des notifications en cours...');
    $('#mailProgress').show();
  }
  post(API_AUDITS, data).done(res => {
    $('#mailProgress').hide();
    if(res.success){
      let htm = 'Numero : <b>'+esc(res.num_audit || '')+'</b>';
      if(res.equipe_msg){ htm += '<br><small style="color:#D32F2F">'+esc(res.equipe_msg)+'</small>'; }
      if(res.notif_msg){
        const col = res.notif_msg.indexOf('Erreur') >= 0 || res.notif_msg.indexOf('echec') >= 0 ? '#D32F2F' : '#1E9C4B';
        htm += '<br><small style="color:'+col+'"><i class="bi bi-envelope me-1"></i>'+esc(res.notif_msg)+'</small>';
      }
      const ico = (res.equipe_msg && res.equipe_msg.indexOf('Attention') >= 0) ? 'warning' : 'success';
      Swal.fire({icon:ico, title:'Declenchement enregistre', html:htm, confirmButtonColor:'#23408F'})
        .then(()=>{ window.location = AGAI_BASE + '/audits'; });
    } else { btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); }
  }).fail(()=>{ $('#mailProgress').hide(); btn.prop('disabled',false).html(html); Swal.fire({icon:'error',title:'Erreur',text:'Echec de la requete.',confirmButtonColor:'#23408F'}); });
});
</script>