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
$monIdInspecteur = 0;
$db = Database::getInstance();
$stInsp = $db->prepare("SELECT idinspecteur, CONCAT(COALESCE(preninspect,''),' ',COALESCE(nominspecteur,'')) AS n FROM inspecteur WHERE iduser=? LIMIT 1");
$stInsp->execute([$uid]); $rowInsp = $stInsp->fetch();
if ($rowInsp && trim($rowInsp['n'])) {
    $nomInspecteurConnecte = trim($rowInsp['n']);
    $monIdInspecteur = (int)$rowInsp['idinspecteur'];
} else {
    $nomInspecteurConnecte = trim(($_SESSION['user']['prenom'] ?? '') . ' ' . ($_SESSION['user']['nom'] ?? ''));
}

require_once INCLUDES_PATH . '/layout_head.php';
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<?php require_once INCLUDES_PATH . '/qrcode_inline.php'; ?>
<style>
.fnc-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;box-shadow:0 1px 3px rgba(16,30,54,.05);}
.desc-quill .ql-container{min-height:120px;font-size:.9rem;}
.desc-quill .ql-editor{min-height:120px;}
.fnc-card-header{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;padding:10px 16px;border-radius:13px 13px 0 0;font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:8px;}
.fnc-card-header i{color:#F3C300;}
.fnc-card-body{padding:16px;}
.howto-step{display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid rgba(30,156,75,.08);font-size:.86rem;color:#334155;}
.howto-step:last-child{border-bottom:none;}
.step-num{width:28px;height:28px;border-radius:50%;background:#1E9C4B;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.82rem;flex:0 0 auto;}
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
.d-sec{background:#fff;border:1px solid #e6ebf3;border-radius:12px;padding:14px 16px;margin-bottom:14px}
.d-sec-h{font-size:.8rem;font-weight:800;color:#23408F;text-transform:uppercase;letter-spacing:.4px;
         border-bottom:2px solid #eef3fb;padding-bottom:7px;margin-bottom:11px;display:flex;align-items:center;gap:7px}
.d-lbl{font-size:.68rem;font-weight:700;color:#7b8aa0;text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px}
.d-val{background:#f5f7fa;border:1px solid #eef1f6;border-radius:7px;padding:6px 10px;font-size:.84rem;
       color:#2C3E50;min-height:32px;word-break:break-word}
.kpi-b{background:#fff;border:1px solid #eef1f6;border-left:4px solid #23408F;border-radius:12px;padding:8px 10px;cursor:pointer;transition:.15s;height:100%}
.kpi-b:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(35,64,143,.12)}
.kpi-b.active{box-shadow:0 0 0 2px rgba(35,64,143,.35)}
.kpi-n{font-size:1.35rem;font-weight:800;line-height:1;color:#2C3E50}
.kpi-l{font-size:.66rem;color:#6b7a90;text-transform:uppercase;letter-spacing:.3px;font-weight:600;margin-top:3px;white-space:nowrap}
.flbl{font-size:.72rem;font-weight:700;color:#5b6b85;text-transform:uppercase;margin-bottom:3px}
.btn-reset-agai{background:linear-gradient(135deg,#D32F2F,#b02525);color:#fff;border:none;border-radius:9px;font-size:.8rem;font-weight:600;padding:7px 12px;white-space:nowrap;box-shadow:0 2px 6px rgba(211,47,47,.25);transition:.15s;}
.btn-reset-agai:hover{filter:brightness(1.06);transform:translateY(-1px);color:#fff;box-shadow:0 4px 10px rgba(211,47,47,.35);}
.synthese-legend{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;}
.synthese-item{display:flex;align-items:flex-start;gap:10px;padding:6px 0;border-bottom:1px solid #f0f4ff;}
.synthese-item:last-child{border-bottom:none;}
.synth-badge{padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:800;flex:0 0 auto;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-exclamation-triangle me-2" style="color:#D32F2F"></i>Non-conformites</h1>
    <div class="sub">Ouverture, suivi et cloture des fiches de non-conformite.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-success" id="btnExportXls" title="Exporter le tableau filtre vers Excel">
      <i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
    <button class="btn btn-anac" id="btnNewFnc"><i class="bi bi-plus-lg me-1"></i>Ouverture FNC</button>
  </div>
</div>

<!-- Indicateurs -->
<div class="d-flex flex-wrap gap-2 mb-3" id="kpiFnc">
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" data-f="" style="border-left-color:#23408F"><div class="kpi-n" id="k_total">-</div><div class="kpi-l">Total FNC</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" style="border-left-color:#6b7a90;cursor:default" title="Nombre de criteres non satisfaisants (fiches attendues) sur les audits affiches"><div class="kpi-n" id="k_ncns" style="color:#6b7a90">-</div><div class="kpi-l">NCNS attendus</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" style="border-left-color:#E8890C;cursor:default" title="Fiches restant a saisir (NCNS - FNC)"><div class="kpi-n" id="k_reste" style="color:#E8890C">-</div><div class="kpi-l">Reste a saisir</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" style="border-left-color:#1E9C4B;cursor:default" title="Taux de saisie des fiches attendues (FNC / NCNS)"><div class="kpi-n" id="k_taux" style="color:#1E9C4B">-</div><div class="kpi-l">Taux de saisie</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" data-f="4" style="border-left-color:#E8890C"><div class="kpi-n" id="k_ouv" style="color:#E8890C">-</div><div class="kpi-l">Ouvertes</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" data-f="retard" style="border-left-color:#D32F2F"><div class="kpi-n" id="k_retard" style="color:#D32F2F">-</div><div class="kpi-l">En retard</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" data-f="c-critique" style="border-left-color:#D32F2F"><div class="kpi-n" id="k_crit" style="color:#D32F2F">-</div><div class="kpi-l">Critiques</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" data-f="c-majeur" style="border-left-color:#F3C300"><div class="kpi-n" id="k_maj" style="color:#b58a00">-</div><div class="kpi-l">Majeures</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" data-f="c-mineur" style="border-left-color:#3b82f6"><div class="kpi-n" id="k_min" style="color:#1e40af">-</div><div class="kpi-l">Mineures</div></div></div>
  <div style="flex:1 1 0;min-width:92px"><div class="kpi-b" data-f="3" style="border-left-color:#1E9C4B"><div class="kpi-n" id="k_ferm" style="color:#1E9C4B">-</div><div class="kpi-l">Fermees</div></div></div>
</div>
<div class="alert border mb-3" style="font-size:.82rem;color:#1e3a5f;background:#eef4ff;border-left:4px solid #23408F !important">
  <i class="bi bi-people me-1"></i><b>Perimetre de ces indicateurs :</b> <b>toute l'equipe d'audit</b> (toutes les fiches des audits ou vous etes planifie, quel que soit l'inspecteur qui les a saisies).
  Pour ne voir que vos propres fiches et faire votre suivi, utilisez la page <b>Suivi NC</b>.
</div>

<!-- Volet : Comment fonctionne ce module ? (repliable) -->
<div class="fnc-card mb-3" style="border-left:4px solid #1E9C4B">
  <div class="fnc-card-body" style="padding:12px 16px">
    <div class="d-flex align-items-center justify-content-between" style="cursor:pointer" id="guideToggle">
      <div class="d-flex align-items-center gap-2">
        <span style="width:32px;height:32px;border-radius:50%;background:#1E9C4B;color:#fff;display:flex;align-items:center;justify-content:center"><i class="bi bi-info-lg"></i></span>
        <strong style="color:#1E9C4B;font-size:.95rem">Comment fonctionne ce module ?</strong>
      </div>
      <button type="button" class="btn btn-sm" style="background:#eef7f1;color:#1E9C4B;font-weight:600">
        <span id="guideLbl">Afficher</span> <i class="bi bi-chevron-down" id="guideChevron" style="transition:transform .2s"></i>
      </button>
    </div>
    <div id="guideBody" style="display:none;margin-top:12px">
      <div class="howto-step"><div class="step-num">1</div><div><strong>Origine des fiches.</strong> Une fiche de non-conformite (FNC) est ouverte pour chaque ecart constate lors d'un acte de supervision. Les criteres non satisfaisants (NCNS) saisis dans le rapport determinent le nombre de fiches a ouvrir.</div></div>
      <div class="howto-step"><div class="step-num">2</div><div><strong>Ouverture d'une FNC.</strong> Cliquez sur <strong>Ouverture FNC</strong>, choisissez l'audit puis renseignez la constatation. <strong>Chaque inspecteur ouvre les fiches de son propre domaine</strong> (OPS, AIR, ATS...). Le nombre total de fiches attendues diminue au fur et a mesure des saisies de l'equipe.</div></div>
      <div class="howto-step"><div class="step-num">3</div><div><strong>Evaluation des risques (OACI Doc 9859, 4e ed. 2018).</strong> Choisissez la <strong>probabilite</strong> puis la <strong>gravite</strong> : une fenetre propose le motif OACI, que vous validez ou ajustez. L'indice, la tolerabilite et la <strong>categorie</strong> (Critique / Majeur / Mineur) sont calcules automatiquement.</div></div>
      <div class="howto-step"><div class="step-num">4</div><div><strong>Transparence et droits.</strong> Tous les inspecteurs de l'equipe <strong>voient</strong> les fiches des autres, mais ne peuvent <strong>modifier</strong> que les leurs. Le bouton <strong>Suivi</strong> est actif sur vos fiches et grise sur celles des collegues (Voir et Imprimer restent disponibles). Le chef inspecteur peut agir sur toutes.</div></div>
      <div class="howto-step"><div class="step-num">5</div><div><strong>Suivi de la mise en conformite.</strong> Via le bouton <strong>Suivi</strong>, enregistrez le plan d'actions de l'operateur, evaluez son acceptation, verifiez la mise en oeuvre effective et cloturez la fiche. Les dates (reponse exigee, delai) sont calculees selon la categorie.</div></div>
      <div class="howto-step"><div class="step-num">6</div><div><strong>Consultation et impression.</strong> Le bouton <strong>Voir</strong> affiche la fiche complete en lecture seule ; le bouton <strong>Imprimer</strong> lance directement l'apercu PDF, avec QR code d'authentification en bas de la fiche.</div></div>
    </div>
  </div>
</div>

<!-- Filtres -->
<div class="fnc-card mb-3"><div class="fnc-card-body">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><div class="flbl">N FNC</div>
      <select id="fFnc" style="width:100%"><option value="">Toutes</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl">N Audit</div>
      <select id="fAudit" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl">Operateur</div>
      <select id="fOrga" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl">Inspecteur</div>
      <select id="fInsp" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl">Domaine</div>
      <select id="fDom" style="width:100%"><option value="">Tous</option></select></div>
    <div class="col-6 col-md-2"><div class="flbl">Categorie</div>
      <select id="fCat" style="width:100%">
        <option value="">Toutes</option><option value="critique">Critique</option>
        <option value="majeur">Majeur</option><option value="mineur">Mineur</option>
      </select></div>
    <div class="col-6 col-md-2"><div class="flbl">Statut</div>
      <select id="fStatut" style="width:100%">
        <option value="">Tous</option><option value="4">Ouvert</option>
        <option value="1">Accepte non verifie</option><option value="2">Rejete</option><option value="3">Ferme</option>
      </select></div>
    <div class="col-6 col-md-2"><button class="btn btn-reset-agai w-100" id="btnResetFiltres" title="Reinitialiser tous les filtres"><i class="bi bi-arrow-counterclockwise me-1"></i>Reinitialiser</button></div>
  </div>
</div></div>

<!-- Tableau des FNC -->
<div class="fnc-card">
  <div class="fnc-card-header"><i class="bi bi-list-check"></i>Fiches de non-conformite
    <span style="margin-left:auto;font-weight:400;font-size:.72rem;display:flex;gap:12px;flex-wrap:wrap">
      <span><i class="bi bi-eye me-1" style="color:#6c757d"></i>Detail</span>
      <span><i class="bi bi-file-earmark-pdf me-1" style="color:#0dcaf0"></i>Fiche signee</span>
      <span><i class="bi bi-shield-check me-1" style="color:#1E9C4B"></i>Preuve de suivi</span>
      <span><i class="bi bi-folder2-open me-1" style="color:#b58a00"></i>Autres documents</span>
      <span><i class="bi bi-clipboard-check me-1" style="color:#23408F"></i>Suivi</span>
      <span><i class="bi bi-printer me-1" style="color:#D32F2F"></i>Imprimer</span>
    </span>
  </div>
  <div class="fnc-card-body p-0">
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:separate;border-spacing:0">
        <thead><tr style="background:#f5f7fa">
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">N FNC</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">N Audit</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">Operateur</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">Domaine</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">Categorie</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">Statut</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">Date emission</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">Reponse exigee</th>
          <th style="padding:9px 12px;font-size:.72rem;text-transform:uppercase;color:#fff;font-weight:700;letter-spacing:.4px;background:#23408F;border-bottom:none">Actions</th>
        </tr></thead>
        <tbody id="tbodyFnc">
          <tr><td colspan="9" style="padding:30px;text-align:center;color:#9aa7bd"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
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
          Fiche de Non-Conformite - <span id="fnc_num_display">N/A</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Bandeau compact : quota + rappel des regles accessible a la demande -->
        <div class="d-flex align-items-center flex-wrap gap-2 mb-3" style="background:#eef3fb;border-left:4px solid #23408F;border-radius:8px;padding:8px 14px;font-size:.82rem">
          <span><i class="bi bi-clipboard-check me-1" style="color:#23408F"></i>Audit <strong id="qb_num">-</strong></span>
          <span class="text-muted">|</span>
          <span>NCNS <strong id="qb_ncns" style="color:#D32F2F">-</strong></span>
          <span class="text-muted">|</span>
          <span>Creees <strong id="qb_crees">0</strong></span>
          <span class="text-muted">|</span>
          <span>Reste <strong id="qb_reste" style="color:#D32F2F">-</strong></span>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3" style="font-size:.74rem">
          <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:6px"><strong>Critique</strong> : reponse immediate, delai = date d'emission</span>
          <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:6px"><strong>Majeur</strong> : PAC sous 1 mois, correction sous 3 mois</span>
          <span style="background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:6px"><strong>Mineur</strong> : reponse argumentee, correction sous 6 mois</span>
          <span style="background:#eef2f7;color:#5b6b85;padding:3px 10px;border-radius:6px"><strong>Observation</strong> : ne genere pas de fiche</span>
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

<style>
#editFncPaper .epf-page{background:#fff;max-width:840px;margin:0 auto;padding:18px 22px;color:#2C3E50;box-shadow:0 2px 14px rgba(0,0,0,.08)}
#editFncPaper .epf-ref{text-align:right;font-size:.7rem;color:#666}
#editFncPaper .epf-hdr{text-align:center;border-bottom:2px solid #23408F;padding-bottom:8px;margin-bottom:8px}
#editFncPaper .epf-hdr img{max-height:64px}
#editFncPaper .epf-title{text-align:center;font-weight:900;text-transform:uppercase;color:#23408F;border:2px solid #23408F;padding:6px;margin:8px 0;font-size:1rem}
#editFncPaper .epf-table{width:100%;border-collapse:collapse}
#editFncPaper .epf-table td{border:1px solid #bbb;padding:4px 7px;vertical-align:top;font-size:.8rem}
#editFncPaper .lbl{background:#e8edf8;font-weight:700;font-size:.74rem;width:22%}
#editFncPaper .section-hdr{background:#23408F;color:#fff;font-weight:700;text-align:center;text-transform:uppercase;font-size:.8rem}
#editFncPaper .th-blue{background:#23408F;color:#fff;font-weight:700;text-align:center;text-transform:uppercase;font-size:.78rem}
#editFncPaper .pac-row td{text-align:center;font-weight:700}
#editFncPaper .epf-in{width:100%;border:1px solid #cfd6e2;border-radius:4px;padding:3px 5px;font-size:.8rem;background:#fff}
#editFncPaper .epf-ta{width:100%;border:1px solid #cfd6e2;border-radius:4px;padding:5px;font-size:.8rem;resize:vertical;font-family:inherit}
#editFncPaper .epf-addr{margin-top:8px;text-align:center;font-size:.7rem;color:#666;border-top:1px solid #23408F;padding-top:4px}
#editFncPaper .select2-container{width:100%!important}
</style>

<!-- ===== MODALE MODIFICATION FNC (calquee sur le PDF) ===== -->
<div class="modal fade" id="modalEditFnc" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-pencil-square me-2" style="color:#F3C300"></i>Suivi de la FNC - <span id="editFncNum">-</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#f5f7fa">
        <div id="editFncPaper"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="btnSaveEditFnc"><i class="bi bi-check-lg me-1"></i>Enregistrer les modifications</button>
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
          <label class="form-label fw-bold" style="font-size:.85rem">Sous-domaine(s) <span class="text-danger">*</span></label>
          <div id="sd_rows"></div>
          <button type="button" class="btn btn-outline-primary btn-sm mt-1" id="btnAddSdRow">
            <i class="bi bi-plus-circle me-1"></i>Ajouter une autre ligne
          </button>
          <div style="font-size:.75rem;color:#7b8aa0;margin-top:6px"><i class="bi bi-lightbulb me-1"></i>Un sous-domaine par ligne. Cliquez sur + pour en ajouter d'autres.</div>
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

<!-- MODALE : lecture de la fiche signee (PDF) -->
<div class="modal fade" id="modalPdfViewer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="height:90vh">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i>Fiche de non-conformite signee</h5>
        <div class="ms-auto d-flex gap-2 me-3">
          <a id="pdfDownload" class="btn btn-sm btn-light" download><i class="bi bi-download me-1"></i>Telecharger</a>
          <button id="pdfPrint" type="button" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="background:#525659">
        <iframe id="pdfFrame" src="" style="width:100%;height:100%;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : consultation de la fiche (lecture seule) -->
<div class="modal fade" id="modalDetailFnc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-eye me-2" style="color:#F3C300"></i>Fiche de non-conformite - <span id="detailFncNum">-</span></h5>
        <div class="ms-auto me-3">
          <button type="button" class="btn btn-sm btn-light" id="btnPrintDetail"><i class="bi bi-printer me-1"></i>Imprimer la fiche</button>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#f5f7fa">
        <div id="detailFncBody"></div>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : saisie/validation du motif OACI (probabilite ou gravite) -->
<div class="modal fade" id="modalMotif" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#23408F;color:#fff">
        <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i><span id="motifTitre">Justification</span></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert border mb-2" style="font-size:.78rem;color:#1e3a5f;background:#eef4ff;border-left:4px solid #23408F !important">
          <i class="bi bi-info-circle me-1"></i>Le motif propose ci-dessous est la signification <b>OACI (Doc 9859, 4e edition, 2018)</b>.
          Vous pouvez le <b>conserver tel quel</b> ou l'<b>adapter</b> au contexte de la constatation.
        </div>
        <div class="field-label" id="motifChoixLbl">Choix : <strong id="motifChoixVal">-</strong></div>
        <textarea id="motifTexte" class="form-control" rows="5" style="font-size:.9rem" placeholder="Saisissez ou ajustez le motif..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm" id="motifValider" style="background:#1E9C4B;color:#fff;font-weight:600">
          <i class="bi bi-check-circle me-1"></i><span id="motifBtnLbl">Valider</span>
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF   = '<?php echo Security::escape($csrf); ?>';
const API    = AGAI_BASE + '/api/nonconformites';
const BANER  = AGAI_BASE + '/public/images/banierenteanac.png';
const IS_CI  = <?php echo $isCI ? 'true' : 'false'; ?>;
const MY_INSP = <?php echo (int)$monIdInspecteur; ?>;  // idinspecteur du user connecte (0 si non inspecteur)
const NOM_INSP_CONN = '<?php echo Security::escape($nomInspecteurConnecte); ?>';
let ALL_AUDITS_EL = [], CURRENT_AUDIT = null;
const DESC_QUILLS = {};
let DOMAINES_INSP = [], SOUSDOM_INSP = [], REGLEMENTS_AUDIT = [];
let blocCounter = 0, pendingBlocs = [];

function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF}, d), null, 'json'); }

/* Normalisation pour comparer sans tenir compte de la casse ni des espaces */
function normTxt(v){ return String(v||'').trim().replace(/\s+/g,' ').toLowerCase(); }

/* Le sous-domaine existe-t-il deja pour ce domaine ? -> renvoie l'element ou null */
function sousDomaineExistant(nom, idDom){
  const n=normTxt(nom);
  return (SOUSDOM_INSP||[]).find(function(s){
    return String(s.iddomaine)===String(idDom) && normTxt(s.nom_sousdomaine)===n;
  }) || null;
}
/* Le reglement existe-t-il deja (par code) ? -> renvoie l'element ou null */
function reglementExistant(code){
  const n=normTxt(code);
  return (REGLEMENTS_AUDIT||[]).find(function(r){ return normTxt(r.code_reglement)===n; }) || null;
}

/* Envoi multipart (pieces jointes). Les cles tableau sont transmises telles quelles. */
function apiPostFile(d, file, preuve, autres){
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  Object.keys(d).forEach(function(k){ fd.append(k, d[k] == null ? '' : d[k]); });
  if(file){   fd.append('fichier_fnc',      file); }
  if(preuve){ fd.append('preuve_suivi',     preuve); }
  if(autres){ fd.append('autres_documents', autres); }
  return $.ajax({url:API, type:'POST', data:fd, processData:false, contentType:false, dataType:'json'});
}

/* Referentiel : recharge la liste des reglements rattaches au domaine choisi */
function chargerReglementsDomaine(idx, domId){
  const $sel = $('#regSelect_'+idx);
  if(!$sel.length) return;
  apiPost({action:'reglements_audit', idaudit:CURRENT_AUDIT.idaudit, iddomaine:domId||0}).done(function(res){
    if(!res || !res.success) return;
    REGLEMENTS_AUDIT = res.data || [];
    const opts = REGLEMENTS_AUDIT.map(function(r){
      const lib = (r.libelle_reglement||'').substring(0,90);
      return '<option value="'+r.idreglement+'">'+esc(r.code_reglement)+(lib?(' - '+esc(lib)):'')+'</option>';
    }).join('');
    $sel.html(opts).val(null).trigger('change.select2');
  });
}
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
let ALL_FNC = [];
let FILTRE_RETARD = false;   // filtre KPI "En retard" actif ou non

/* Indicateurs calcules sur l'ensemble des fiches (non filtrees) */
// Fiches filtrees par la BARRE de filtres uniquement (hors clic sur les cartes KPI).
// Sert de base au calcul des indicateurs, qui deviennent ainsi dynamiques.
function fncFiltreesBarre(){
  const fn=$('#fFnc').val()||'', fa=$('#fAudit').val()||'', fo=$('#fOrga').val()||'';
  const fi=$('#fInsp').val()||'', fd=$('#fDom').val()||'';
  const fc=$('#fCat').val()||'', fs=$('#fStatut').val()||'';
  return (ALL_FNC||[]).filter(function(f){
    if(fn && String(f.num_fnc)!==fn) return false;
    if(fa && String(f.num_audit||'')!==fa) return false;
    if(fi && String(f.nom_inspecteur||'')!==fi) return false;
    if(fd && String(f.nomdomaine||'')!==fd) return false;
    if(fo && String(f.nomorga||'')!==fo) return false;
    if(fc && f.categorie!==fc) return false;
    if(fs && String(f.statut)!==String(fs)) return false;
    return true;
  });
}

function majKpiFnc(){
  // Les indicateurs refletent les filtres actifs de la barre (dynamiques).
  const A=fncFiltreesBarre();
  const n=function(fn){ return A.filter(fn).length; };
  const today=new Date().toISOString().substring(0,10);
  // En retard : date de reponse exigee depassee ET fiche non fermee (statut != 3)
  const estRetard=function(f){
    return f.date_reponse_exigee && String(f.date_reponse_exigee).substring(0,10) < today && String(f.statut)!=='3';
  };
  $('#k_total').text(A.length);
  $('#k_ouv').text( n(function(f){ return String(f.statut)==='4'; }) );
  $('#k_retard').text( n(estRetard) );
  $('#k_crit').text(n(function(f){ return f.categorie==='critique'; }) );
  $('#k_maj').text( n(function(f){ return f.categorie==='majeur';   }) );
  $('#k_min').text( n(function(f){ return f.categorie==='mineur';   }) );
  $('#k_ferm').text(n(function(f){ return String(f.statut)==='3'; }) );

  // NCNS attendus : somme des criteres non satisfaisants, comptes UNE fois par audit
  // (le NCNS est une donnee de l'audit, pas de la fiche). On evite ainsi de le
  // multiplier par le nombre de fiches. Base : les fiches actuellement filtrees.
  const parAudit={};
  A.forEach(function(f){ if(f.idaudit!=null){ parAudit[f.idaudit]=parseInt(f.audit_ncns||0,10)||0; } });
  let ncns=0; for(const k in parAudit){ ncns+=parAudit[k]; }
  const fnc=A.length;
  const reste=Math.max(0, ncns-fnc);
  const taux = ncns>0 ? Math.round((fnc/ncns)*1000)/10 : 0;
  $('#k_ncns').text(ncns);
  $('#k_reste').text(reste);
  $('#k_taux').text(ncns>0 ? (taux+'%') : '-');
}

/* Alimentation des listes deroulantes de filtre a partir des fiches chargees */
function majListesFiltres(){
  const uniq=function(arr){ return [...new Set(arr.filter(Boolean))].sort(); };
  const fill=function(sel, vals, libelle){
    const cur=$(sel).val();
    $(sel).html('<option value="">'+libelle+'</option>'
      + vals.map(function(v){ return '<option value="'+esc(v)+'">'+esc(v)+'</option>'; }).join(''));
    if(cur && vals.indexOf(cur)>=0) $(sel).val(cur);
    $(sel).trigger('change.select2');
  };
  fill('#fFnc',   uniq((ALL_FNC||[]).map(function(f){ return f.num_fnc; })),  'Toutes');
  fill('#fAudit', uniq((ALL_FNC||[]).map(function(f){ return f.num_audit; })),'Tous');
  fill('#fOrga',  uniq((ALL_FNC||[]).map(function(f){ return f.nomorga; })),  'Tous');
  fill('#fInsp',  uniq((ALL_FNC||[]).map(function(f){ return f.nom_inspecteur; })), 'Tous');
  fill('#fDom',   uniq((ALL_FNC||[]).map(function(f){ return f.nomdomaine; })), 'Tous');
}

/* Application des filtres */
function fncFiltrees(){
  const fn=$('#fFnc').val()||'', fa=$('#fAudit').val()||'', fo=$('#fOrga').val()||'';
  const fi=$('#fInsp').val()||'', fd=$('#fDom').val()||'';
  const fc=$('#fCat').val()||'', fs=$('#fStatut').val()||'';
  const today=new Date().toISOString().substring(0,10);
  return (ALL_FNC||[]).filter(function(f){
    if(fn && String(f.num_fnc)!==fn) return false;
    if(fa && String(f.num_audit||'')!==fa) return false;
    if(fi && String(f.nom_inspecteur||'')!==fi) return false;
    if(fd && String(f.nomdomaine||'')!==fd) return false;
    if(fo && String(f.nomorga||'')!==fo) return false;
    if(fc && f.categorie!==fc) return false;
    if(fs && String(f.statut)!==String(fs)) return false;
    // Filtre "En retard" : date de reponse exigee depassee ET fiche non fermee
    if(FILTRE_RETARD){
      const enRetard = f.date_reponse_exigee && String(f.date_reponse_exigee).substring(0,10) < today && String(f.statut)!=='3';
      if(!enRetard) return false;
    }
    return true;
  });
}

function loadFncList(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success){ $('#tbodyFnc').html('<tr><td colspan="9" style="padding:20px;text-align:center;color:#D32F2F">Erreur : '+esc(res.message||'chargement')+'</td></tr>'); return; }
    ALL_FNC = res.data||[];
    majKpiFnc();
    majListesFiltres();
    renderFncTable();
  }).fail(function(jq){
    $('#tbodyFnc').html('<tr><td colspan="9" style="padding:20px;text-align:center;color:#D32F2F">Erreur serveur ('+jq.status+') : '+esc((jq.responseText||'').substring(0,300))+'</td></tr>');
  });
}

function renderFncTable(){
    majKpiFnc();  // indicateurs dynamiques selon les filtres actifs
    const data = fncFiltrees();
    if(!data.length){
      $('#tbodyFnc').html('<tr><td colspan="9" style="padding:30px;text-align:center;color:#9aa7bd"><i class="bi bi-inbox me-2"></i>Aucune fiche pour ces criteres</td></tr>'); return;
    }
    let h='';
    data.forEach(function(f){
      const estFermee = String(f.statut)==='3';   // fiche fermee : plus de suivi ni de suppression
      const catBg=CATEG_BG[f.categorie]||'#f1f5f9', catTc=CATEG_COLORS[f.categorie]||'#555';
      const stBg=STATUT_COLORS[f.statut]||'#f1f5f9', stTc=STATUT_TC[f.statut]||'#555';
      const retard=f.date_reponse_exigee&&f.date_reponse_exigee<new Date().toISOString().substring(0,10)&&f.statut<3;
      h+='<tr style="border-bottom:1px solid #f1f4f9'+(retard?';background:#fff5f5':'')+'">'
        +'<td style="padding:9px 12px"><strong style="color:#D32F2F;font-family:monospace">'+esc(f.num_fnc)+'</strong></td>'
        +'<td style="padding:9px 12px;font-size:.82rem"><strong style="color:#23408F">'+esc(f.num_audit||'-')+'</strong></td>'
        +'<td style="padding:9px 12px;font-size:.82rem">'+esc(f.nomorga||'-')+'</td>'
        +'<td style="padding:9px 12px;font-size:.82rem"><span style="background:#eef3fb;color:#23408F;padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700">'+esc(f.nomdomaine||'-')+'</span></td>'
        +'<td style="padding:9px 12px"><span style="background:'+catBg+';color:'+catTc+';padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700">'+esc(f.categorie||'-')+'</span></td>'
        +'<td style="padding:9px 12px"><span style="background:'+stBg+';color:'+stTc+';padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700">'+esc(STATUT_LABELS[f.statut]||'-')+'</span>'
          +(retard?'<br><span style="font-size:.68rem;color:#D32F2F"><i class="bi bi-clock me-1"></i>En retard</span>':'')+'</td>'
        +'<td style="padding:9px 12px;font-size:.82rem">'+fmtDate(f.date_emission)+'</td>'
        +'<td style="padding:9px 12px;font-size:.82rem">'+fmtDate(f.date_reponse_exigee)+'</td>'
        +'<td style="padding:9px 12px;white-space:nowrap">'
          +'<button class="btn btn-sm btn-outline-secondary me-1 btn-view-fnc" data-id="'+f.idfnc+'" title="Voir le detail (lecture seule)"><i class="bi bi-eye"></i></button>'
          +(f.fichier_fnc?'<button class="btn btn-sm btn-outline-info me-1 btn-doc-fnc" data-id="'+f.idfnc+'" data-num="'+esc(f.num_fnc)+'" data-doc="fiche" title="Fiche de non-conformite signee"><i class="bi bi-file-earmark-pdf"></i></button>':'')
          +(f.preuve_suivi?'<button class="btn btn-sm btn-outline-success me-1 btn-doc-fnc" data-id="'+f.idfnc+'" data-num="'+esc(f.num_fnc)+'" data-doc="preuve" title="Preuve de suivi et verification de l\'efficacite"><i class="bi bi-shield-check"></i></button>':'')
          +(f.autres_documents?'<button class="btn btn-sm btn-outline-warning me-1 btn-doc-fnc" data-id="'+f.idfnc+'" data-num="'+esc(f.num_fnc)+'" data-doc="autres" title="Autres documents du dossier"><i class="bi bi-folder2-open"></i></button>':'')
          +(function(){
             // Bouton Suivi : actif uniquement pour le CI ou l'inspecteur createur.
             // Les autres inspecteurs voient la fiche (Voir/Imprimer) mais ne la modifient pas.
             if(estFermee) return '';
             const estMien = IS_CI || (MY_INSP>0 && String(f.idinspecteur_createur)===String(MY_INSP));
             if(estMien){
               return '<button class="btn btn-sm btn-outline-primary me-1 btn-edit-fnc" data-id="'+f.idfnc+'" title="Suivi de la fiche"><i class="bi bi-clipboard-check me-1"></i>Suivi</button>';
             }
             return '<span class="d-inline-block me-1" title="Fiche saisie par un autre inspecteur : consultation seule (Voir / Imprimer)"><button class="btn btn-sm btn-outline-secondary" disabled style="opacity:.45;cursor:not-allowed;pointer-events:none"><i class="bi bi-lock me-1"></i>Suivi</button></span>';
           })()
          +'<button class="btn btn-sm btn-outline-danger me-1 btn-print-fnc" data-id="'+f.idfnc+'" title="Imprimer"><i class="bi bi-printer"></i></button>'
          +((IS_CI && !estFermee)?'<button class="btn btn-sm btn-outline-danger btn-del-fnc" data-id="'+f.idfnc+'" data-num="'+esc(f.num_fnc)+'" title="Supprimer"><i class="bi bi-trash"></i></button>':'')
        +'</td>'
        +'</tr>';
    });
    $('#tbodyFnc').html(h);
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
  // Avertissement si la date de delivrance manque. Le bouton reste ACTIF :
  // il ouvrira une modale de saisie sur place (date + statut=3).
  const ddOk = a.date_delivrance_rapport && a.date_delivrance_rapport!=='0000-00-00';
  if(!ddOk){
    if(!$('#ai_warn_dd').length){
      $('#auditInfoBox').append('<div id="ai_warn_dd" class="mt-2 p-2" style="background:#fff8e6;border:1px solid #f3e2a8;border-radius:8px;color:#8a6d00;font-size:.82rem"><i class="bi bi-info-circle me-1"></i>Date de delivrance du rapport a renseigner : elle vous sera demandee a l\'etape suivante.</div>');
    }
    $('#ai_warn_dd').show();
  } else {
    $('#ai_warn_dd').hide();
  }
  $('#btnContinueAudit').prop('disabled', a.reste_a_creer<=0);
});
$('#btnContinueAudit').on('click',function(){
  // Blocage reglementaire : les delais se calculent sur la date de delivrance.
  // Si elle manque, on propose de la saisir sur place (modale), sans quitter la page.
  const dd = CURRENT_AUDIT && CURRENT_AUDIT.date_delivrance_rapport;
  if(!dd || dd==='0000-00-00'){
    ouvrirModaleDateDelivrance();
    return;
  }
  bootstrap.Modal.getInstance('#modalSelectAudit').hide();
  openFncForm();
});

/* Modale : saisie de la date de delivrance du rapport + passage statut=3 */
function ouvrirModaleDateDelivrance(){
  const today=new Date().toISOString().substring(0,10);
  Swal.fire({
    title:'Finaliser l\'audit '+esc(CURRENT_AUDIT.num_audit||''),
    html:
      '<div style="text-align:left;font-size:.9rem;color:#2C3E50">'+
        '<p style="margin-bottom:12px">Avant d\'ouvrir les fiches, renseignez la <b>date de delivrance du rapport</b>. '+
        'Le statut de l\'audit passera automatiquement a <b style="color:#1E9C4B">Effectue (statut 3)</b>.</p>'+
        '<label style="font-weight:600;display:block;margin-bottom:4px">Date de delivrance du rapport</label>'+
        '<input type="date" id="swalDateDeliv" class="swal2-input" style="margin:0;width:100%" value="'+today+'" max="'+today+'">'+
        '<div style="margin-top:10px;padding:8px 10px;background:#eaf7ef;border:1px solid #bfe6cc;border-radius:8px;color:#1E7A3E;font-size:.82rem">'+
          '<i class="bi bi-check-circle me-1"></i>Statut cible : <b>Effectue (3)</b></div>'+
      '</div>',
    showCancelButton:true,
    confirmButtonText:'<i class="bi bi-save me-1"></i>Enregistrer et continuer',
    cancelButtonText:'Annuler',
    confirmButtonColor:'#1E9C4B',
    cancelButtonColor:'#6b7a90',
    focusConfirm:false,
    preConfirm:function(){
      const v=document.getElementById('swalDateDeliv').value;
      if(!v){ Swal.showValidationMessage('Veuillez saisir la date de delivrance.'); return false; }
      if(v>today){ Swal.showValidationMessage('La date ne peut pas etre dans le futur.'); return false; }
      return v;
    }
  }).then(function(res){
    if(!res.isConfirmed) return;
    const dateDeliv=res.value;
    apiPost({action:'set_date_delivrance', idaudit:CURRENT_AUDIT.idaudit, date_delivrance_rapport:dateDeliv})
      .done(function(r){
        if(r && r.success){
          // Mettre a jour la copie locale de l'audit
          CURRENT_AUDIT.date_delivrance_rapport = r.date_delivrance_rapport;
          CURRENT_AUDIT.statut = r.statut;
          // Mettre a jour aussi dans la liste en memoire
          const a=ALL_AUDITS_EL.find(function(x){return String(x.idaudit)===String(CURRENT_AUDIT.idaudit);});
          if(a){ a.date_delivrance_rapport=r.date_delivrance_rapport; a.statut=r.statut; }
          $('#ai_warn_dd').hide();
          Swal.fire({
            icon:'success',
            title:'Audit finalise',
            html:'Date de delivrance enregistree.<br>Statut passe a <b style="color:#1E9C4B">Effectue (3)</b>.',
            timer:1600, showConfirmButton:false
          }).then(function(){
            bootstrap.Modal.getInstance('#modalSelectAudit').hide();
            openFncForm();
          });
        } else {
          Swal.fire({icon:'error',title:'Erreur',text:(r&&r.message)||'Echec de l\'enregistrement.',confirmButtonColor:'#D32F2F'});
        }
      })
      .fail(function(){ Swal.fire({icon:'error',title:'Erreur',text:'Echec de l\'enregistrement.',confirmButtonColor:'#D32F2F'}); });
  });
}

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
  // 1) Habilitations d'abord : elles determinent le domaine d'inspection.
  // 2) Puis le referentiel (reglements) de CE domaine, sinon la liste arriverait vide.
  apiPost({action:'habilitations_insp'}).done(function(r1){
    DOMAINES_INSP = (r1||{}).domaines     || [];
    SOUSDOM_INSP  = (r1||{}).sousdomaines || [];
    const dom0 = DOMAINES_INSP.length ? DOMAINES_INSP[0].iddomaine : 0;
    apiPost({action:'reglements_audit', idaudit:CURRENT_AUDIT.idaudit, iddomaine:dom0}).always(function(r2){
      REGLEMENTS_AUDIT = (r2||{}).data || [];
      new bootstrap.Modal('#modalFnc').show();
      // On bascule directement sur la saisie : le premier bloc est cree d'office
      $('#fncBlocs').empty(); blocCounter = 0;
      addBloc();
    });
  });
}

function addBloc(){
  const current = $('#fncBlocs .fnc-bloc').length;
  if(current >= CURRENT_AUDIT.reste_a_creer){
    Swal.fire({icon:'warning',title:'Quota atteint',
      html:'Vous ne pouvez creer que <strong>'+CURRENT_AUDIT.reste_a_creer+'</strong> fiche(s) pour cet audit.<br>NCNS = '+CURRENT_AUDIT.ncns,
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
        '<input type="date" class="form-control form-control-sm fnc-date-sign" id="dateSign_'+idx+'" value="'+new Date().toISOString().substring(0,10)+'"  style="background:#f5f7fa"></div>'+
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

    // Section : Evaluation des risques de securite (OACI Doc 9859)
    '<div class="section-divider"><i class="bi bi-shield-exclamation"></i>Evaluation des risques de securite</div>'+
    '<div class="alert border" style="font-size:.82rem;color:#1e3a5f;background:linear-gradient(135deg,#eef4ff,#f5f9ff);border-color:#23408F !important;border-left:4px solid #23408F !important">'+
      '<div class="d-flex align-items-center gap-2 mb-1" style="font-weight:700;color:#23408F">'+
        '<i class="bi bi-shield-check"></i>Evaluation des risques de securite &mdash; methode OACI</div>'+
      '<div style="margin-bottom:6px">La categorie de la fiche est determinee <b>automatiquement</b> par le croisement '+
      '<b>Probabilite &times; Gravite</b> (matrice de risque). Choisissez la probabilite puis la gravite : '+
      'l\'indice de risque, le niveau de tolerabilite et la categorie s\'affichent automatiquement.</div>'+
      '<div style="font-size:.75rem;color:#475569;border-top:1px dashed #b9cbe8;padding-top:5px">'+
        '<i class="bi bi-book me-1"></i><b>Reference :</b> OACI &mdash; <b>Doc 9859</b>, Manuel de gestion de la securite, 4<sup>e</sup> edition, 2018.</div>'+
      '</div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-6"><div class="field-label">Probabilite <span class="text-danger">*</span></div>'+
        '<select class="form-select form-select-sm fnc-proba fnc-s2" id="probaSelect_'+idx+'" style="width:100%">'+
          '<option value="">-- Choisir la probabilite --</option>'+
          '<option value="5">Frequent (5) - susceptible de se produire de nombreuses fois</option>'+
          '<option value="4">Occasionnel (4) - susceptible de se produire parfois</option>'+
          '<option value="3">Faible (3) - peu susceptible, mais possible</option>'+
          '<option value="2">Improbable (2) - tres peu susceptible de se produire</option>'+
          '<option value="1">Extremement improbable (1) - presque inconcevable</option>'+
        '</select>'+
        '<div class="form-text fnc-proba-help" id="probaHelp_'+idx+'" style="font-size:.75rem"></div></div>'+
      '<div class="col-md-6"><div class="field-label">Gravite <span class="text-danger">*</span></div>'+
        '<select class="form-select form-select-sm fnc-gravite fnc-s2" id="graviteSelect_'+idx+'" style="width:100%">'+
          '<option value="">-- Choisir la gravite --</option>'+
          '<option value="A">Catastrophique (A) - aeronef/equipement detruit, multiples deces</option>'+
          '<option value="B">Dangereux (B) - importante reduction des marges de securite</option>'+
          '<option value="C">Majeur (C) - reduction des marges, incident grave, blesses</option>'+
          '<option value="D">Mineur (D) - nuisance, limites de fonctionnement, incident mineur</option>'+
          '<option value="E">Negligeable (E) - peu de consequences</option>'+
        '</select>'+
        '<div class="form-text fnc-gravite-help" id="graviteHelp_'+idx+'" style="font-size:.75rem"></div></div>'+
    '</div>'+
    // Resultat de l'evaluation : indice + tolerabilite + categorie
    '<div class="row g-3 mb-3" id="risqueResultZone_'+idx+'" style="display:none">'+
      '<div class="col-md-4"><div class="field-label">Indice de risque</div>'+
        '<div id="indiceRisque_'+idx+'" class="risque-indice" style="font-weight:800;font-size:1.4rem;text-align:center;padding:8px;border-radius:10px;border:2px solid #e2e8f0">-</div></div>'+
      '<div class="col-md-4"><div class="field-label">Tolerabilite</div>'+
        '<div id="tolerabilite_'+idx+'" class="risque-tol" style="font-weight:700;text-align:center;padding:12px 8px;border-radius:10px;border:2px solid #e2e8f0">-</div></div>'+
      '<div class="col-md-4"><div class="field-label">Categorie (automatique)</div>'+
        '<div id="categAuto_'+idx+'" class="risque-cat" style="font-weight:700;text-align:center;padding:12px 8px;border-radius:10px;border:2px solid #e2e8f0">-</div>'+
        // Champ cache qui conserve la categorie calculee (compatible avec l'existant)
        '<input type="hidden" class="fnc-categorie" id="categSelect_'+idx+'" value=""></div>'+
    '</div>'+
    // Dates calculees (comme avant, dependent de la categorie auto)
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-6" id="dateRepZone_'+idx+'"><div class="field-label">Reponse exigee avant le</div>'+
        '<input type="date" class="form-control form-control-sm fnc-date-rep" id="dateRep_'+idx+'" readonly style="background:#f5f7fa"></div>'+
      '<div class="col-md-6" id="dateLimZone_'+idx+'"><div class="field-label">Delai de mise en conformite</div>'+
        '<input type="date" class="form-control form-control-sm fnc-date-lim" id="dateLim_'+idx+'" readonly style="background:#f5f7fa"></div>'+
    '</div>'+

    // Section : Constatation + Etat
    '<div class="section-divider"><i class="bi bi-clipboard-text"></i>Description de la constatation</div>'+
    '<div class="mb-3"><div class="field-label">Description de la constatation <span class="text-danger">*</span></div>'+
      '<div id="descQuill_'+idx+'" class="desc-quill" style="background:#fff"></div>'+
      '<textarea class="fnc-description d-none" id="descConst_'+idx+'"></textarea></div>'+
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

    // Zone PAC + Section ANAC : renseignee lors du SUIVI (modification), pas a la creation
    '<div class="section-divider pac-toggle" style="background:#2C3E50;cursor:pointer" data-idx="'+idx+'">'+
      '<i class="bi bi-chevron-right pac-chev"></i>Plan d\'actions correctives (PAC) et section ANAC'+
      '<span style="margin-left:auto;font-size:.7rem;font-weight:600;background:rgba(255,255,255,.18);padding:2px 10px;border-radius:20px">Renseigne au suivi - cliquer pour afficher</span></div>'+
    '<div class="pac-zone" data-idx="'+idx+'" style="display:none">'+
    '<div class="alert mb-3" style="background:#eef3fb;border-left:4px solid #23408F;border-radius:8px;font-size:.8rem">'+
      '<i class="bi bi-info-circle-fill me-1" style="color:#23408F"></i>'+
      'Ces sections se remplissent apres reception du plan d\'actions de l\'operateur. Enregistrez d\'abord la fiche, puis ouvrez-la avec le bouton <strong>Suivi</strong> pour les completer.</div>'+
    '<div class="mb-3"><div class="field-label">Analyse des causes</div>'+
      '<textarea class="form-control fnc-causes" id="causes_'+idx+'" rows="2" placeholder="Analyser les causes..."></textarea></div>'+
    '<div class="mb-3"><div class="field-label">Action(s) correctrice(s)</div>'+
      '<textarea class="form-control fnc-actions" id="actions_'+idx+'" rows="3" placeholder="Decrire les actions correctives..."></textarea></div>'+
    '<div class="mb-3"><div class="field-label">Observation</div>'+
      '<textarea class="form-control fnc-observation" id="obs_'+idx+'" rows="2" placeholder="Observations..."></textarea></div>'+

    // Section : PAC
    '<div class="section-divider"><i class="bi bi-check2-square"></i>Section reservee a l\'ANAC - Criteres d\'analyse du PAC</div>'+
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
    '</div>'+   /* fin .pac-zone */

    // Section : piece jointe (fiche signee)
    '<div class="section-divider"><i class="bi bi-paperclip"></i>Fiche signee (PDF)</div>'+
    '<div class="mb-2">'+
      '<div class="field-label">Joindre la fiche signee <small style="font-weight:400;color:#6b7a90">(facultatif)</small></div>'+
      '<input type="file" class="form-control form-control-sm fnc-fichier" id="fic_'+idx+'" accept="application/pdf">'+
      '<div class="form-text">Format PDF uniquement. Le document est stocke hors zone publique.</div>'+
    '</div>'+
  '</div>');

  $('#fncBlocs').append(bloc);

  // A la CREATION : la zone PAC / ANAC est repliee et desactivee (elle se remplit au suivi)
  $('#bloc_'+idx+' .pac-zone').css({opacity:.6})
    .find('input,textarea,select,button').prop('disabled', true);

  // Activer Select2 sur tous les selects du bloc
  $('#domSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',placeholder:'-- Choisir le domaine --',
    dropdownParent:$('#modalFnc')});
  $('#sdSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',
    placeholder:'Choisir sous-domaine(s)',allowClear:true,dropdownParent:$('#modalFnc')});
  $('#regSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',
    placeholder:'Choisir reglement(s)',allowClear:true,dropdownParent:$('#modalFnc')});
  $('#probaSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',
    placeholder:'-- Choisir la probabilite --',dropdownParent:$('#modalFnc')});
  $('#graviteSelect_'+idx).select2({theme:'bootstrap-5',width:'100%',
    placeholder:'-- Choisir la gravite --',dropdownParent:$('#modalFnc')});

  // Editeur Quill pour la description de la constatation
  if(typeof Quill!=='undefined'){
    const qd=new Quill('#descQuill_'+idx,{theme:'snow',placeholder:'Decrire la constatation...',
      modules:{toolbar:[['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['clean']]}});
    qd.on('text-change',function(){ $('#descConst_'+idx).val(qd.root.innerHTML); });
    DESC_QUILLS[idx]=qd;
  }

  // Si domaine unique : pre-selectionner et gen num FNC
  if(DOMAINES_INSP.length===1){
    const domIdUniq = DOMAINES_INSP[0].iddomaine;
    $('#domSelect_'+idx).val(domIdUniq).trigger('change.select2');
    // Filtrer sous-domaines pour ce domaine unique
    const sdOptsUniq=SOUSDOM_INSP
      .filter(function(sd){ return String(sd.iddomaine)===String(domIdUniq); })
      .map(function(sd){ return '<option value="'+sd.idsousdomaine+'">'+esc(sd.nom_sousdomaine)+'</option>'; }).join('');
    $('#sdSelect_'+idx).html(sdOptsUniq).trigger('change.select2');
    /* numerotation geree globalement par regenAllNums() */
  }

  // Handler changement domaine -> filtrer sous-domaines + gen num
  $('#domSelect_'+idx).on('change',function(){
    const domId=$(this).val();
    if(!domId) return;
    regenAllNums();
    const sdOpts=SOUSDOM_INSP
      .filter(function(sd){ return String(sd.iddomaine)===String(domId); })
      .map(function(sd){ return '<option value="'+sd.idsousdomaine+'">'+esc(sd.nom_sousdomaine)+'</option>'; }).join('');
    $('#sdSelect_'+idx).html(sdOpts).trigger('change.select2');
    // Le referentiel suit le domaine : on recharge tous les reglements rattaches a ce domaine
    chargerReglementsDomaine(idx, domId);
  });

  // Handlers evaluation des risques : proba + gravite -> indice, tolerabilite, categorie auto
  $('#probaSelect_'+idx).on('change',function(){
    const v=$(this).val();
    if(v){ ouvrirModaleMotif(idx,'proba',v); } else { onRisqueChange(idx); }
  });
  $('#graviteSelect_'+idx).on('change',function(){
    const v=$(this).val();
    if(v){ ouvrirModaleMotif(idx,'gravite',v); } else { onRisqueChange(idx); }
  });

  // Style des radios opt
  bloc.find('.radio-opt input').on('change',function(){
    const n=$(this).attr('name');
    $('[name="'+n+'"]').closest('.radio-opt').removeClass('selected');
    $(this).closest('.radio-opt').addClass('selected');
  });

  regenAllNums();
  updateBtnState();
}

function genNumFnc(idx, domId, offset){
  apiPost({action:'next_num_fnc',idaudit:CURRENT_AUDIT.idaudit,iddomaine:domId,offset:(offset||0)}).done(function(r){
    if(r.success) $('#numFnc_'+idx).text(r.num_fnc);
  });
}
/* Renumerote toutes les fiches ouvertes selon leur position : numeros uniques et contigus */
function regenAllNums(){
  $('#fncBlocs .fnc-bloc').each(function(i){
    const bid=this.id.replace('bloc_','');
    const domId=$('#domSelect_'+bid).val();
    if(domId) genNumFnc(bid, domId, i);
  });
}

// Matrice de tolerabilite OACI (Doc 9859) : indice -> tolerabilite -> categorie
const RISK_MATRIX = {
  INTOLERABLE: {indices:['5A','5B','5C','4A','4B','3A'], categorie:'critique', label:'INTOLERABLE', color:'#D32F2F', bg:'#fee2e2'},
  TOLERABLE:   {indices:['5D','5E','4C','4D','4E','3B','3C','3D','2A','2B','2C','1A'], categorie:'majeur', label:'TOLERABLE', color:'#b58a00', bg:'#fef3c7'},
  ACCEPTABLE:  {indices:['3E','2D','2E','1B','1C','1D','1E'], categorie:'mineur', label:'ACCEPTABLE', color:'#1E9C4B', bg:'#dcfce7'}
};
const PROBA_LABELS = {'5':'Frequent','4':'Occasionnel','3':'Faible','2':'Improbable','1':'Extremement improbable'};
const PROBA_HELP = {
  '5':'Susceptible de se produire de nombreuses fois (s\'est produit frequemment).',
  '4':'Susceptible de se produire parfois (ne s\'est pas produit frequemment).',
  '3':'Peu susceptible de se produire, mais possible (s\'est produit rarement).',
  '2':'Tres peu susceptible de se produire (on n\'a pas connaissance que cela se soit produit).',
  '1':'Il est presque inconcevable que l\'evenement se produise.'
};
const GRAVITE_LABELS = {'A':'Catastrophique','B':'Dangereux','C':'Majeur','D':'Mineur','E':'Negligeable'};
const GRAVITE_HELP = {
  'A':'Aeronef/equipement detruit ; multiples deces.',
  'B':'Importante reduction des marges de securite, detresse physique ou charge de travail telle que les operateurs ne peuvent accomplir leurs taches avec exactitude.',
  'C':'Reduction des marges de securite, reduction de la capacite des operateurs, incident grave, personnes blessees.',
  'D':'Nuisance, limites de fonctionnement, recours a des procedures d\'urgence, incident mineur.',
  'E':'Peu de consequences.'
};
const CAT_LABELS = {critique:'CRITIQUE', majeur:'MAJEUR', mineur:'MINEUR'};

// Determine la tolerabilite (et donc la categorie) a partir d'un indice comme "5A"
function tolerabiliteDe(indice){
  for(const k in RISK_MATRIX){ if(RISK_MATRIX[k].indices.indexOf(indice)>=0) return RISK_MATRIX[k]; }
  return null;
}

// Motifs (justifications) saisis/valides par l'inspecteur, par bloc.
// Pre-remplis avec le texte OACI, mais modifiables librement.
const JUSTIF_PROBA = {};   // JUSTIF_PROBA[idx] = texte
const JUSTIF_GRAVITE = {}; // JUSTIF_GRAVITE[idx] = texte
let MOTIF_CTX = null;      // {idx, type:'proba'|'gravite', valeur}

// Ouvre la modale de saisie du motif, pre-remplie avec le texte OACI par defaut
// (ou le texte deja saisi pour ce bloc si l'inspecteur revient dessus).
function ouvrirModaleMotif(idx, type, valeur){
  MOTIF_CTX = {idx:idx, type:type, valeur:valeur};
  let dejaSaisi, defautOaci, titre, choixLbl, btnLbl;
  if(type==='proba'){
    defautOaci = PROBA_HELP[valeur] || '';
    dejaSaisi  = JUSTIF_PROBA[idx];
    titre      = 'Motif de la probabilite';
    choixLbl   = (PROBA_LABELS[valeur]||'') + ' (' + valeur + ')';
    btnLbl     = 'Valider et choisir la gravite';
  } else {
    defautOaci = GRAVITE_HELP[valeur] || '';
    dejaSaisi  = JUSTIF_GRAVITE[idx];
    titre      = 'Motif de la gravite';
    choixLbl   = (GRAVITE_LABELS[valeur]||'') + ' (' + valeur + ')';
    btnLbl     = 'Valider';
  }
  $('#motifTitre').text(titre);
  $('#motifChoixVal').text(choixLbl);
  $('#motifBtnLbl').text(btnLbl);
  // Pre-remplissage : texte deja saisi s'il existe, sinon le texte OACI par defaut
  $('#motifTexte').val((dejaSaisi!=null && dejaSaisi!=='') ? dejaSaisi : defautOaci);
  new bootstrap.Modal('#modalMotif').show();
  setTimeout(function(){ $('#motifTexte').focus(); }, 300);
}

// Validation du motif : on enregistre le texte (edite) et on enchaine.
$(document).on('click','#motifValider',function(){
  if($('#modalMotif').attr('data-mode')==='edit') return; // gere par le handler edition
  if(!MOTIF_CTX) return;
  const idx=MOTIF_CTX.idx, type=MOTIF_CTX.type;
  const txt=$('#motifTexte').val().trim();
  if(!txt){ Swal.fire({icon:'warning',text:'Veuillez saisir un motif.',confirmButtonColor:'#D32F2F'}); return; }
  if(type==='proba'){
    JUSTIF_PROBA[idx]=txt;
    bootstrap.Modal.getInstance(document.getElementById('modalMotif')).hide();
    onRisqueChange(idx);
    // Enchaine : on invite a choisir la gravite (ouvre la liste deroulante)
    setTimeout(function(){ $('#graviteSelect_'+idx).select2('open'); }, 400);
  } else {
    JUSTIF_GRAVITE[idx]=txt;
    bootstrap.Modal.getInstance(document.getElementById('modalMotif')).hide();
    onRisqueChange(idx);
  }
});

// Si l'inspecteur ferme la modale sans valider : on annule le choix correspondant
$(document).on('hidden.bs.modal','#modalMotif',function(){
  if(!MOTIF_CTX) return;
  const idx=MOTIF_CTX.idx, type=MOTIF_CTX.type;
  if(type==='proba' && (JUSTIF_PROBA[idx]==null || JUSTIF_PROBA[idx]==='')){
    $('#probaSelect_'+idx).val('').trigger('change.select2');
    onRisqueChange(idx);
  }
  if(type==='gravite' && (JUSTIF_GRAVITE[idx]==null || JUSTIF_GRAVITE[idx]==='')){
    $('#graviteSelect_'+idx).val('').trigger('change.select2');
    onRisqueChange(idx);
  }
  MOTIF_CTX=null;
});

function onRisqueChange(idx){
  const proba=$('#probaSelect_'+idx).val();     // '5','4',...
  const gravite=$('#graviteSelect_'+idx).val(); // 'A','B',...
  // Aides contextuelles a chaque choix (le "pourquoi")
  $('#probaHelp_'+idx).text(proba?PROBA_HELP[proba]:'');
  $('#graviteHelp_'+idx).text(gravite?GRAVITE_HELP[gravite]:'');

  if(!proba || !gravite){
    // Pas encore complet : on reinitialise l'affichage et la categorie
    $('#risqueResultZone_'+idx).hide();
    $('#categSelect_'+idx).val('');
    $('#dateRep_'+idx).val(''); $('#dateLim_'+idx).val('');
    return;
  }
  const indice = proba + gravite;               // ex "5A"
  const tol = tolerabiliteDe(indice);
  $('#risqueResultZone_'+idx).show();

  // Indice
  $('#indiceRisque_'+idx).text(indice)
    .css({'border-color':tol?tol.color:'#e2e8f0','color':tol?tol.color:'#2C3E50','background':tol?tol.bg:'#fff'});
  // Tolerabilite
  $('#tolerabilite_'+idx).text(tol?tol.label:'-')
    .css({'border-color':tol?tol.color:'#e2e8f0','color':tol?tol.color:'#2C3E50','background':tol?tol.bg:'#fff'});
  // Categorie automatique (verrouillee)
  const cat = tol?tol.categorie:'';
  $('#categAuto_'+idx).text(cat?CAT_LABELS[cat]:'-')
    .css({'border-color':tol?tol.color:'#e2e8f0','color':tol?tol.color:'#2C3E50','background':tol?tol.bg:'#fff'});
  $('#categSelect_'+idx).val(cat);

  // Recalcule les dates selon la categorie determinee
  onCategChange(idx);
  updateBtnState();
}

function onCategChange(idx){
  const cat=$('#categSelect_'+idx).val();
  const today=new Date().toISOString().substring(0,10);
  const dateEmission = $('.date-emission-'+idx).val()||today;
  // Date officielle de reference = date de delivrance du rapport (garantie non nulle
  // car l'ouverture est bloquee sinon). Les delais partent de cette date.
  const dateRap = (CURRENT_AUDIT && CURRENT_AUDIT.date_delivrance_rapport) || dateEmission;
  function addDays(d,n){ const dt=new Date(d); if(isNaN(dt)) return ''; dt.setDate(dt.getDate()+n); return dt.toISOString().substring(0,10); }
  function addMonths(d,n){ const dt=new Date(d); if(isNaN(dt)) return ''; dt.setMonth(dt.getMonth()+n); return dt.toISOString().substring(0,10); }
  if(cat==='critique'){
    $('#dateRepZone_'+idx+',#dateLimZone_'+idx).show();
    $('#dateRep_'+idx).val(dateEmission); $('#dateLim_'+idx).val(dateEmission);
  } else if(cat==='majeur'){
    $('#dateRepZone_'+idx+',#dateLimZone_'+idx).show();
    $('#dateRep_'+idx).val(addDays(dateRap,30));
    $('#dateLim_'+idx).val(addMonths(dateRap,3));
  } else if(cat==='mineur'){
    $('#dateRepZone_'+idx+',#dateLimZone_'+idx).show();
    $('#dateRep_'+idx).val(addDays(dateRap,30));
    $('#dateLim_'+idx).val(addMonths(dateRap,6));
  } else {
    $('#dateRep_'+idx).val(''); $('#dateLim_'+idx).val('');
  }
}

function removeBloc(idx){ $('#bloc_'+idx).remove(); regenAllNums(); updateBtnState(); }
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
    if(!$('#probaSelect_'+id).val() || !$('#graviteSelect_'+id).val() || !$('#categSelect_'+id).val()){ ok=false; Swal.fire({icon:'warning',text:'Veuillez completer l\'evaluation des risques (probabilite et gravite) pour la fiche #'+id,confirmButtonColor:'#D32F2F'}); return false; }
    const descTxt = DESC_QUILLS[id] ? DESC_QUILLS[id].getText().trim() : $('#descConst_'+id).val().replace(/<[^>]*>/g,'').trim();
    if(!descTxt){ ok=false; Swal.fire({icon:'warning',text:'Description de la constatation manquante pour la fiche #'+id,confirmButtonColor:'#D32F2F'}); return false; }
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
      probabilite:$('#probaSelect_'+idx).val()||'',
      gravite:$('#graviteSelect_'+idx).val()||'',
      indice_risque:($('#probaSelect_'+idx).val()||'')+($('#graviteSelect_'+idx).val()||''),
      justif_probabilite:(JUSTIF_PROBA[idx]!=null&&JUSTIF_PROBA[idx]!=='')?JUSTIF_PROBA[idx]:(PROBA_HELP[$('#probaSelect_'+idx).val()]||''),
      justif_gravite:(JUSTIF_GRAVITE[idx]!=null&&JUSTIF_GRAVITE[idx]!=='')?JUSTIF_GRAVITE[idx]:(GRAVITE_HELP[$('#graviteSelect_'+idx).val()]||''),
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
    // Fiche signee eventuelle : envoi multipart
    const inpFic = document.getElementById('fic_'+idx);
    const fic    = (inpFic && inpFic.files && inpFic.files[0]) ? inpFic.files[0] : null;
    if(fic){
      if(!/\.pdf$/i.test(fic.name)){
        Swal.fire({icon:'warning',title:'Fichier invalide',text:'La fiche signee doit etre un PDF (fiche #'+idx+').',confirmButtonColor:'#D32F2F'});
        return;
      }
      promises.push(apiPostFile(d, fic));
    } else {
      promises.push(apiPost(d));
    }
  });
  $.when.apply($,promises).done(function(){
    const results=promises.length===1?[arguments]:[...arguments];
    const success=results.filter(function(r){return r[0]&&r[0].success;}).length;
    const failMsg=(results.find(function(r){return r[0]&&r[0].success===false;})||[{}])[0].message||'';
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer les fiches');
    if(success===0){
      Swal.fire({icon:'error',title:'Aucune fiche enregistree',text:failMsg||'Verifiez les champs et reessayez.',confirmButtonColor:'#D32F2F'});
      return;
    }
    bootstrap.Modal.getInstance('#modalFnc').hide();
    Swal.fire({icon:'success',title:success+' fiche(s) NC cree(s)',timer:2500,showConfirmButton:false});
    CURRENT_AUDIT.nb_fnc_crees += success;
    CURRENT_AUDIT.reste_a_creer -= success;
    loadFncList(); loadAuditsEligibles();
  });
});

/* ===== IMPRESSION FNC ===== */
let FNC_PRINT_NUM = '';   // numero de la fiche actuellement previsualisee (impression)
$(document).on('click','.btn-print-fnc',function(){
  const id=$(this).data('id');
  apiPost({action:'get',idfnc:id}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',text:res.message}); return; }
    const f=res.data, sds=res.sousdomaines||[], regs=res.reglements||[];
    FNC_PRINT_NUM = f.num_fnc || '';
    $('#printTitle').text('FNC - '+f.num_fnc);
    // On construit l'apercu dans le conteneur (cache) puis on lance directement
    // l'impression, sans afficher la modale intermediaire.
    $('#printPreview').html(buildFncPdf(f,sds,regs));
    genererQrFnc(f);
    // Laisser le QR se generer (canvas) avant de lancer l'impression
    setTimeout(function(){ lancerImpressionFnc(); }, 500);
  });
});

// Lance la fenetre d'impression a partir du contenu de #printPreview
function lancerImpressionFnc(){
  // Le QR est un canvas : on le convertit en image pour qu'il soit copie dans
  // la fenetre d'impression (un canvas ne se copie pas en HTML).
  const qbox=document.getElementById('fncQrBox');
  if(qbox){
    const cv=qbox.querySelector('canvas');
    if(cv){ try{ const url=cv.toDataURL('image/png'); qbox.innerHTML='<img src="'+url+'" style="width:110px;height:110px">'; }catch(e){} }
  }
  const w=window.open('','_blank','width=900,height=750');
  const numImpr = (FNC_PRINT_NUM || $('#editFncNum').text() || $('#detailFncNum').text() || '').trim();
  const numFichier = numImpr.replace(/[\/\\:*?"<>|]/g,'-');
  const titreImpr = numImpr ? ('Fiche de Non-Conformite N ' + numFichier) : 'Fiche de Non-Conformite';
  w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'+titreImpr+'</title>'+
    '<style>'+getFncPrintStyle()+'</style></head><body>'+$('#printPreview').html()+'</body></html>');
  w.document.close(); w.focus(); setTimeout(function(){ w.print(); },600);
}

// Genere le QR code de la fiche de non-conformite (dans le preview)
function genererQrFnc(f){
  const box=document.getElementById('fncQrBox'); if(!box) return;
  const QR = window.QRCode || (typeof QRCode!=='undefined' ? QRCode : null);
  if(!QR){ box.style.display='none'; return; }
  const stripTags = function(v){ return String(v||'').replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim(); };
  const lignes=[
    'ANAC GABON - AGAI (Systeme securise)',
    'FICHE DE NON-CONFORMITE',
    'Ref : IX-GEN-R3-F-I-010',
    'N FNC : '+(f.num_fnc||'-'),
    'Audit : '+(f.num_audit||'-'),
    'Operateur : '+(f.nomorga||'-'),
    'Domaine : '+(f.nomdomaine||f.libel_domaine||'-'),
    'Categorie : '+((f.categorie||'-').toUpperCase()),
    'Indice risque : '+(f.indice_risque||'-'),
    'Date emission : '+(f.date_emission?String(f.date_emission).substring(0,10):'-'),
    'Reponse exigee avant le : '+(f.date_reponse_exigee?String(f.date_reponse_exigee).substring(0,10):'-'),
    'Delai mise en conformite : '+(f.date_limite_mise_conformite?String(f.date_limite_mise_conformite).substring(0,10):'-'),
    'Document authentifie AGAI'
  ].filter(Boolean);
  const texte=lignes.join('\n');
  let ok=false;
  for(let tn=6; tn<=20 && !ok; tn++){
    try{ box.innerHTML=''; new QR(box,{text:texte,typeNumber:tn,width:110,height:110,colorDark:'#000',colorLight:'#fff',correctLevel:QR.CorrectLevel.L}); ok=true; }
    catch(e){}
  }
  if(!ok){ box.style.display='none'; }
}
$('#btnDoPrint').on('click',function(){ lancerImpressionFnc(); });

function getFncPrintStyle(){
  return '@page{size:A4;margin:0}body{font-family:Candara,Arial,sans-serif;font-size:9pt;color:#2C3E50;margin:0}'+
    '.fnc-page{width:210mm;min-height:297mm;padding:8mm 9mm;box-sizing:border-box;border:3px solid #23408F;border-radius:2px}'+
    '.ref-line{text-align:right;font-size:7pt;color:#666;margin-bottom:4px;font-weight:700}'+
    '.hdr{text-align:center;padding-bottom:6px;margin-bottom:8px}'+
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
      '<tr><td colspan="7" style="min-height:60px;height:60px">'+((f.description_constatation||f.libelle)||'-')+'</td></tr>'+
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
    '<div style="text-align:center;margin-top:14px"><div id="fncQrBox" style="display:inline-block"></div>'+
      '<div style="font-size:7.5pt;color:#6b7a90;margin-top:3px">ANAC Gabon - Fiche authentifiee par le systeme AGAI</div></div>'+
    '<div class="addr">BP 2212 Libreville (GABON) - Tel.: (241) 01 44 54 00 - Fax: (241) 01 44 54 01 - Email: anac@anac-gabon.com - www.anacgabon.org</div>'+
  '</div>';
}

/* ===== MODIFICATION FNC (modale calquee sur le PDF) ===== */
function buildFncEditForm(f, sds, regs, sdOptions, regOptions){
  /* Formulaire de SUIVI : reprend exactement la mise en page du formulaire
     d'ouverture (memes sections, memes composants, memes modales d'ajout).
     Toutes les sections sont actives, y compris PAC et section ANAC.        */
  const sdSel  = (sds ||[]).map(function(s){ return String(s.idsousdomaine); });
  const regSel = (regs||[]).map(function(r){ return String(r.idreglement);   });

  const sdOpts = (sdOptions||[]).map(function(s){
    return '<option value="'+s.idsousdomaine+'" '+(sdSel.indexOf(String(s.idsousdomaine))>=0?'selected':'')+'>'+esc(s.nom_sousdomaine)+'</option>';
  }).join('');
  const regOpts = (regOptions||[]).map(function(r){
    const lib=(r.libelle_reglement||'').substring(0,90);
    return '<option value="'+r.idreglement+'" '+(regSel.indexOf(String(r.idreglement))>=0?'selected':'')+'>'+esc(r.code_reglement)+(lib?(' - '+esc(lib)):'')+'</option>';
  }).join('');

  const cat = f.categorie || '';
  const et  = f.etat || '';
  // Decision unique : la verification effective prime sur l'acceptation
  const decision = (Number(f.verification_meo) === 1) ? 'meo'
                 : (f.pac_acceptation === 'acceptee' ? 'acceptee'
                 : (f.pac_acceptation === 'refusee'  ? 'refusee' : ''));
  const rd  = function(name, val){ return '<input type="radio" name="'+name+'" value="'+val+'"'+((f[name.replace('e_','')]===val)?' checked':'')+'>'; };
  const pacCell = function(key){
    const v = f[key] || '';
    return '<div class="col-md-2"><div class="field-label">'+key.replace('pac_','').replace(/^./,function(c){return c.toUpperCase();})+'</div>'+
      '<div class="d-flex gap-2">'+
        '<label class="radio-opt'+(v==='S'?' selected':'')+'" style="border:2px solid #e2e8f0;padding:5px 12px;border-radius:8px;font-size:.8rem">'+
          '<input type="radio" name="e_'+key+'" value="S"'+(v==='S'?' checked':'')+'> S</label>'+
        '<label class="radio-opt'+(v==='NS'?' selected':'')+'" style="border:2px solid #e2e8f0;padding:5px 12px;border-radius:8px;font-size:.8rem">'+
          '<input type="radio" name="e_'+key+'" value="NS"'+(v==='NS'?' checked':'')+'> NS</label>'+
      '</div></div>';
  };

  return '<div class="fnc-bloc" id="bloc_E">'+
    '<input type="hidden" id="e_idfnc" value="'+esc(f.idfnc)+'">'+

    // Bandeau numero
    '<div class="bloc-head"><span class="bloc-num"><i class="bi bi-clipboard-check me-1"></i>'+esc(f.num_fnc||'')+'</span>'+
      '<span style="margin-left:16px;font-size:.78rem;color:#5b6b85">Statut : <span id="e_statut_apercu"></span></span>'+
      '<input type="hidden" id="e_statut_calcule" value="'+esc(f.statut||4)+'">'+
      '<input type="hidden" id="e_decision_val" value="'+esc(decision)+'">'+
      '<span style="margin-left:auto;font-size:.78rem;color:#5b6b85">Audit <strong>'+esc(f.num_audit||'-')+'</strong></span></div>'+

    // Informations generales
    '<div class="section-divider"><i class="bi bi-person-badge"></i>Informations generales</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-4"><div class="field-label">Nom de l\'operateur</div>'+
        '<input type="text" class="form-control form-control-sm" value="'+esc(f.nomorga||'-')+'" readonly style="background:#f5f7fa"></div>'+
      '<div class="col-md-4"><div class="field-label">Lieu</div>'+
        '<input type="text" class="form-control form-control-sm" value="'+esc(f.ville||f.indicateur_oaci||f.site_inspection||'-')+'" readonly style="background:#f5f7fa"></div>'+
      '<div class="col-md-4"><div class="field-label">Date</div>'+
        '<input type="date" class="form-control form-control-sm" id="e_date_emission" value="'+String(f.date_emission||'').substring(0,10)+'"></div>'+
    '</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-4"><div class="field-label">Representant de l\'operateur</div>'+
        '<input type="text" class="form-control form-control-sm" id="e_representant" value="'+esc(f.representant_operateur||'')+'"></div>'+
      '<div class="col-md-4"><div class="field-label">Titre</div>'+
        '<input type="text" class="form-control form-control-sm" id="e_titre" value="'+esc(f.titre_representant||'')+'"></div>'+
      '<div class="col-md-4"><div class="field-label">Nom de l\'inspecteur</div>'+
        '<input type="text" class="form-control form-control-sm" value="'+esc(f.nom_inspecteur||'-')+'" readonly style="background:#f5f7fa"></div>'+
    '</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-4"><div class="field-label">Visa</div>'+
        '<input type="text" class="form-control form-control-sm" id="e_visa" value="'+esc(f.nom_visa_date||'')+'" placeholder="Nom, visa et date..."></div>'+
    '</div>'+

    // Domaine et sous-domaine (memes composants que l'ouverture)
    '<div class="section-divider"><i class="bi bi-diagram-3"></i>Domaine et sous-domaine</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-4"><div class="field-label">Domaine d\'inspection</div>'+
        '<select class="form-select form-select-sm" id="domSelect_E" disabled>'+
          '<option value="'+esc(f.iddomaine)+'" selected>'+esc(f.nomdomaine||'-')+'</option></select></div>'+
      '<div class="col-md-5"><div class="field-label">Sous-domaine(s)</div>'+
        '<select id="sdSelect_E" multiple style="width:100%">'+sdOpts+'</select></div>'+
      '<div class="col-md-3 d-flex align-items-end">'+
        '<button type="button" class="btn btn-outline-secondary btn-sm w-100 btn-add-sd" data-idx="E" style="font-size:.78rem"><i class="bi bi-plus-circle me-1"></i>Nouveau sous-domaine</button></div>'+
    '</div>'+

    // Categorisation par evaluation des risques de securite (OACI Doc 9859)
    '<div class="section-divider"><i class="bi bi-shield-exclamation"></i>Evaluation des risques de securite</div>'+
    '<div class="alert border" style="font-size:.8rem;color:#1e3a5f;background:linear-gradient(135deg,#eef4ff,#f5f9ff);border-color:#23408F !important;border-left:4px solid #23408F !important">'+
      '<div style="font-weight:700;color:#23408F;margin-bottom:3px"><i class="bi bi-shield-check me-1"></i>Categorie determinee automatiquement par Probabilite &times; Gravite (matrice OACI).</div>'+
      '<div style="font-size:.74rem;color:#475569"><i class="bi bi-book me-1"></i><b>Reference :</b> OACI &mdash; <b>Doc 9859</b>, Manuel de gestion de la securite, 4<sup>e</sup> edition, 2018.</div></div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-6"><div class="field-label">Probabilite <span class="text-danger">*</span></div>'+
        '<select class="form-select form-select-sm" id="e_proba">'+
          '<option value="">-- Choisir --</option>'+
          '<option value="5" '+(String(f.probabilite)==='5'?'selected':'')+'>Frequent (5)</option>'+
          '<option value="4" '+(String(f.probabilite)==='4'?'selected':'')+'>Occasionnel (4)</option>'+
          '<option value="3" '+(String(f.probabilite)==='3'?'selected':'')+'>Faible (3)</option>'+
          '<option value="2" '+(String(f.probabilite)==='2'?'selected':'')+'>Improbable (2)</option>'+
          '<option value="1" '+(String(f.probabilite)==='1'?'selected':'')+'>Extremement improbable (1)</option>'+
        '</select>'+
        '<div class="form-text" id="e_probaHelp" style="font-size:.74rem"></div></div>'+
      '<div class="col-md-6"><div class="field-label">Gravite <span class="text-danger">*</span></div>'+
        '<select class="form-select form-select-sm" id="e_gravite">'+
          '<option value="">-- Choisir --</option>'+
          '<option value="A" '+(f.gravite==='A'?'selected':'')+'>Catastrophique (A)</option>'+
          '<option value="B" '+(f.gravite==='B'?'selected':'')+'>Dangereux (B)</option>'+
          '<option value="C" '+(f.gravite==='C'?'selected':'')+'>Majeur (C)</option>'+
          '<option value="D" '+(f.gravite==='D'?'selected':'')+'>Mineur (D)</option>'+
          '<option value="E" '+(f.gravite==='E'?'selected':'')+'>Negligeable (E)</option>'+
        '</select>'+
        '<div class="form-text" id="e_graviteHelp" style="font-size:.74rem"></div></div>'+
    '</div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-4"><div class="field-label">Indice de risque</div>'+
        '<div id="e_indice" style="font-weight:800;font-size:1.2rem;text-align:center;padding:7px;border-radius:8px;border:2px solid #e2e8f0">-</div></div>'+
      '<div class="col-md-4"><div class="field-label">Tolerabilite</div>'+
        '<div id="e_tol" style="font-weight:700;text-align:center;padding:10px;border-radius:8px;border:2px solid #e2e8f0">-</div></div>'+
      '<div class="col-md-4"><div class="field-label">Categorie (automatique)</div>'+
        '<div id="e_catAuto" style="font-weight:700;text-align:center;padding:10px;border-radius:8px;border:2px solid #e2e8f0">-</div>'+
        '<input type="hidden" id="e_categorie" value="'+esc(cat)+'"></div>'+
    '</div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-6"><div class="field-label">Reponse exigee avant le</div>'+
        '<input type="date" class="form-control form-control-sm" id="e_date_rep" value="'+String(f.date_reponse_exigee||'').substring(0,10)+'"></div>'+
      '<div class="col-md-6"><div class="field-label">Delai de mise en conformite</div>'+
        '<input type="date" class="form-control form-control-sm" id="e_date_lim" value="'+String(f.date_limite_mise_conformite||'').substring(0,10)+'"></div>'+
    '</div>'+

    // Constatation + etat
    '<div class="section-divider"><i class="bi bi-clipboard-text"></i>Description de la constatation</div>'+
    '<div class="mb-3"><div class="field-label">Description de la constatation <span class="text-danger">*</span></div>'+
      '<div id="e_descQuill" class="desc-quill" style="background:#fff"></div>'+
      '<textarea class="d-none" id="e_description"></textarea></div>'+
    '<div class="mb-3"><div class="field-label">Etat <span class="text-danger">*</span></div>'+
      '<div class="d-flex flex-wrap gap-2 mt-1">'+
        '<label class="radio-opt'+(et==='documente_non_implemente'?' selected':'')+'" style="border:2px solid #e2e8f0;padding:10px 16px;border-radius:10px;min-width:180px">'+
          '<input type="radio" name="e_etat" value="documente_non_implemente"'+(et==='documente_non_implemente'?' checked':'')+'>'+
          '<span><i class="bi bi-file-text me-1" style="color:#23408F"></i>Documente, pas mis en oeuvre</span></label>'+
        '<label class="radio-opt'+(et==='implemente_non_documente'?' selected':'')+'" style="border:2px solid #e2e8f0;padding:10px 16px;border-radius:10px;min-width:180px">'+
          '<input type="radio" name="e_etat" value="implemente_non_documente"'+(et==='implemente_non_documente'?' checked':'')+'>'+
          '<span><i class="bi bi-tools me-1" style="color:#b58a00"></i>Mis en oeuvre, pas documente</span></label>'+
        '<label class="radio-opt'+(et==='non_documente_non_implemente'?' selected':'')+'" style="border:2px solid #e2e8f0;padding:10px 16px;border-radius:10px;min-width:180px">'+
          '<input type="radio" name="e_etat" value="non_documente_non_implemente"'+(et==='non_documente_non_implemente'?' checked':'')+'>'+
          '<span><i class="bi bi-x-circle me-1" style="color:#D32F2F"></i>Pas documente, pas mis en oeuvre</span></label>'+
      '</div></div>'+

    // Referentiel
    '<div class="section-divider"><i class="bi bi-book"></i>Referentiel</div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-md-5"><div class="field-label">Reglement(s)</div>'+
        '<select id="regSelect_E" multiple style="width:100%">'+regOpts+'</select>'+
        '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-reg" data-idx="E" style="font-size:.78rem"><i class="bi bi-plus-circle me-1"></i>Autre reglement</button>'+
      '</div>'+
      '<div class="col-md-4"><div class="field-label">Manuel</div>'+
        '<textarea class="form-control form-control-sm" id="e_manuel" rows="2">'+esc(f.manuel||'')+'</textarea></div>'+
      '<div class="col-md-3"><div class="field-label">Autres references</div>'+
        '<textarea class="form-control form-control-sm" id="e_autres" rows="2">'+esc(f.autres||'')+'</textarea></div>'+
    '</div>'+

    // Fiche signee
    '<div class="section-divider"><i class="bi bi-paperclip"></i>Fiche signee (PDF)</div>'+
    '<div class="mb-3"><div class="field-label">Document joint</div>'+
      '<div class="d-flex gap-2 align-items-center flex-wrap">'+
        '<input type="file" class="form-control form-control-sm" id="e_fichier" accept="application/pdf" style="max-width:360px">'+
        (f.fichier_fnc
          ? '<button type="button" class="btn btn-sm btn-outline-primary" id="btnVoirFicheJointe"><i class="bi bi-file-earmark-pdf me-1"></i>Voir la fiche jointe</button>'
            +'<span style="font-size:.76rem;color:#6b7a90">Choisir un fichier remplace le document actuel.</span>'
          : '<span style="font-size:.76rem;color:#6b7a90">Aucun document joint pour le moment.</span>')+
      '</div></div>'+

    // Autres documents du dossier
    '<div class="section-divider"><i class="bi bi-folder2-open"></i>Autres documents du dossier</div>'+
    '<div class="mb-3"><div class="field-label">Pieces complementaires (PDF)</div>'+
      '<div class="d-flex gap-2 align-items-center flex-wrap">'+
        '<input type="file" class="form-control form-control-sm" id="e_autres_docs" accept="application/pdf" style="max-width:360px">'+
        (f.autres_documents
          ? '<button type="button" class="btn btn-sm btn-outline-warning" id="btnVoirAutresDetail" data-id="'+esc(f.idfnc)+'"><i class="bi bi-folder2-open me-1"></i>Consulter</button>'
          : '<span style="font-size:.76rem;color:#6b7a90">Aucun document complementaire.</span>')+
      '</div>'+
      '<div class="form-text" style="font-size:.7rem">Courriers, preuves, annexes... Chaque nouveau PDF s\'ajoute a la suite des precedents dans un document unique.</div></div>'+

    // PAC de l'operateur (actif au suivi)
    '<div class="section-divider" style="background:#2C3E50"><i class="bi bi-check2-square"></i>Plan d\'actions correctives (PAC) de l\'operateur</div>'+
    '<div class="mb-3"><div class="field-label">Analyse des causes</div>'+
      '<textarea class="form-control" id="e_causes" rows="3">'+esc(f.analyse_causes||'')+'</textarea></div>'+
    '<div class="mb-3"><div class="field-label">Action(s) correctrice(s)</div>'+
      '<textarea class="form-control" id="e_actions" rows="4">'+esc(f.actions_correctives||'')+'</textarea></div>'+

    // Section ANAC
    '<div class="section-divider" style="background:#2C3E50"><i class="bi bi-shield-check"></i>Section reservee a l\'ANAC - Criteres d\'analyse du PAC</div>'+
    '<div class="row g-2 mb-3">'+
      pacCell('pac_pertinent')+pacCell('pac_exhaustif')+pacCell('pac_detaille')+
      pacCell('pac_specifique')+pacCell('pac_realiste')+pacCell('pac_coherent')+
    '</div>'+
    '<div class="row g-3 mb-3">'+
      '<div class="col-12">'+
        '<div style="font-size:.82rem;font-weight:700;color:#2C3E50;margin-bottom:8px"><i class="bi bi-clipboard-check me-1"></i>Decision sur le plan d\'actions correctives</div>'+
        '<div class="d-flex gap-2 flex-wrap">'+
          '<label class="radio-opt'+(decision==='acceptee'?' selected':'')+'" style="border:2px solid #e2e8f0;padding:10px 18px;border-radius:10px">'+
            '<input type="radio" name="e_decision" value="acceptee"'+(decision==='acceptee'?' checked':'')+'>'+
            '<span><i class="bi bi-check-circle me-1" style="color:#E8890C"></i>PAC accepte, non verifie</span></label>'+
          '<label class="radio-opt'+(decision==='refusee'?' selected':'')+'" style="border:2px solid #e2e8f0;padding:10px 18px;border-radius:10px">'+
            '<input type="radio" name="e_decision" value="refusee"'+(decision==='refusee'?' checked':'')+'>'+
            '<span><i class="bi bi-x-circle me-1" style="color:#D32F2F"></i>PAC refuse</span></label>'+
          '<label class="radio-opt'+(decision==='meo'?' selected':'')+'" style="border:2px solid #c5d4f5;padding:10px 18px;border-radius:10px;background:#f0f4ff">'+
            '<input type="radio" name="e_decision" value="meo"'+(decision==='meo'?' checked':'')+'>'+
            '<span><i class="bi bi-shield-check me-1" style="color:#1E9C4B"></i>Mise en oeuvre effectuee et verifiee</span></label>'+
        '</div>'+
        '<div class="form-text" style="font-size:.72rem">Un seul choix possible. La verification effective cloture la fiche.</div>'+
      '</div>'+
    '</div>'+
    '<div class="mb-3"><div class="field-label">Observation</div>'+
      '<textarea class="form-control" id="e_observation" rows="3">'+esc(f.observation||'')+'</textarea></div>'+

    // ---- Cloture et efficacite (visible seulement si la mise en oeuvre est verifiee) ----
    '<div id="e_zone_cloture" style="display:'+(decision==='meo'?'block':'none')+'">'+
      '<div class="section-divider" style="background:#1E9C4B"><i class="bi bi-flag-fill"></i>Cloture et efficacite de la mise en conformite</div>'+
      '<div class="row g-3 mb-3">'+
        '<div class="col-md-3"><div class="field-label">Date effective de cloture</div>'+
          '<input type="date" class="form-control form-control-sm" id="e_date_cloture" value="'+String(f.date_effective_cloture||'').substring(0,10)+'">'+
          '<div class="form-text" style="font-size:.7rem">Prerenseignee a la date du jour, modifiable.</div></div>'+
        '<div class="col-md-3"><div class="field-label">Delai de mise en conformite exige</div>'+
          '<input type="date" class="form-control form-control-sm" id="e_delai_exige" value="'+String(f.delais_mise_conformite_exige||f.date_limite_mise_conformite||'').substring(0,10)+'" readonly style="background:#f5f7fa">'+
          '<div class="form-text" style="font-size:.7rem">Repris de la date limite de mise en conformite.</div></div>'+
        '<div class="col-md-3"><div class="field-label">Delai de mise en conformite reel</div>'+
          '<input type="date" class="form-control form-control-sm" id="e_delai_reel" value="'+String(f.delais_mise_conformite_reel||'').substring(0,10)+'"></div>'+
        '<div class="col-md-3"><div class="field-label">Statut du delai</div>'+
          '<div id="e_statut_delai" style="padding-top:4px"></div></div>'+
      '</div>'+
      '<div class="mb-3"><div class="field-label">Efficacite de la mise en conformite</div>'+
        '<input type="text" class="form-control form-control-sm" id="e_efficacite" value="'+esc(f.efficacite_mise_conformite||'')+'" readonly style="background:#f5f7fa">'+
        '<div class="form-text" style="font-size:.7rem">Calcul automatique : delai exige moins delai reel.</div></div>'+
      '<div class="mb-3"><div class="field-label">Preuve de suivi et verification de l\'efficacite de l\'action corrective</div>'+
        '<div class="d-flex gap-2 align-items-center flex-wrap">'+
          '<input type="file" class="form-control form-control-sm" id="e_preuve" accept="application/pdf" style="max-width:360px">'+
          (f.preuve_suivi
            ? '<button type="button" class="btn btn-sm btn-outline-primary" id="btnVoirPreuve"><i class="bi bi-file-earmark-pdf me-1"></i>Consulter</button>'
            : '<span style="font-size:.76rem;color:#6b7a90">Aucun document joint.</span>')+
        '</div>'+
        '<div class="form-text" style="font-size:.7rem">PDF uniquement, scanne en un seul fichier.</div></div>'+
    '</div>'+

    // ---- Echanges avec l'operateur ----
    '<div class="section-divider"><i class="bi bi-envelope"></i>Echanges avec l\'operateur</div>'+
    '<div class="row g-3 mb-2">'+
      '<div class="col-md-6"><div class="field-label">Observations / courriers</div>'+
        '<textarea class="form-control" id="e_obs_courriers" rows="3">'+esc(f.observations_courriers||'')+'</textarea></div>'+
      '<div class="col-md-6"><div class="field-label">Relance</div>'+
        '<textarea class="form-control" id="e_relance" rows="3">'+esc(f.relance||'')+'</textarea></div>'+
    '</div>'+
  '</div>';
}

/* ============================================================
 *  SUIVI : statut de la fiche pilote par les boutons du PAC
 *   Acceptee                        -> statut 1 (accepte non verifie)
 *   Refusee                         -> statut 2 (rejete)
 *   Mise en oeuvre verifiee (coche) -> statut 3 (ferme)
 * ============================================================ */
function majStatutSuivi(){
  const dec = $('[name="e_decision"]:checked').val() || '';
  const meo = (dec === 'meo');
  let st = 4;                       // ouvert par defaut
  if(dec === 'acceptee') st = 1;
  if(dec === 'refusee')  st = 2;
  if(meo)                st = 3;    // la verification effective ferme la fiche
  $('#e_statut_calcule').val(st);
  $('#e_decision_val').val(dec);

  const LBL={1:'Accepte non verifie',2:'Rejete',3:'Ferme',4:'Ouvert'};
  const COL={1:'#E8890C',2:'#D32F2F',3:'#1E9C4B',4:'#23408F'};
  $('#e_statut_apercu').html('<span style="background:'+COL[st]+';color:#fff;padding:2px 12px;border-radius:20px;font-size:.78rem;font-weight:700">'
    + LBL[st] + '</span>');

  // La zone de cloture n'apparait qu'une fois la mise en oeuvre verifiee
  $('#e_zone_cloture').toggle(meo);
  if(meo && !$('#e_date_cloture').val()){
    $('#e_date_cloture').val(new Date().toISOString().substring(0,10));
  }
  majEfficacite();
}

/* Efficacite = delai exige - delai reel (en jours), et statut D / ND */
function majEfficacite(){
  const ex = $('#e_delai_exige').val(), re = $('#e_delai_reel').val();
  if(!ex || !re){
    $('#e_efficacite').val('');
    $('#e_statut_delai').html('<span style="color:#adb5bd;font-size:.8rem">-</span>');
    return;
  }
  const d1 = new Date(ex), d2 = new Date(re);
  if(isNaN(d1) || isNaN(d2)){ return; }
  const jours = Math.round((d1 - d2) / 86400000);   // positif = avance
  let txt, badge;
  if(jours >= 0){
    txt   = jours + ' jour(s) avant l\'echeance';
    badge = '<span style="background:#1E9C4B;color:#fff;padding:2px 12px;border-radius:20px;font-size:.78rem;font-weight:700" title="Non depasse">ND</span>';
  } else {
    txt   = Math.abs(jours) + ' jour(s) apres l\'echeance';
    badge = '<span style="background:#D32F2F;color:#fff;padding:2px 12px;border-radius:20px;font-size:.78rem;font-weight:700" title="Depasse">D</span>';
  }
  $('#e_efficacite').val(txt);
  $('#e_statut_delai').html(badge);
}

// Motifs edites dans le formulaire Modifier (pre-remplis avec ceux de la fiche)
let E_JUSTIF_PROBA = '';
let E_JUSTIF_GRAVITE = '';
let E_MOTIF_CTX = null;
let E_INIT_LOCK = false;  // true pendant l'init du formulaire Modifier (protege les dates chargees)

function ouvrirModaleMotifEdit(type, valeur){
  E_MOTIF_CTX = {type:type, valeur:valeur};
  let dejaSaisi, defautOaci, titre, choixLbl, btnLbl;
  if(type==='proba'){
    defautOaci = PROBA_HELP[valeur] || '';
    dejaSaisi  = E_JUSTIF_PROBA;
    titre      = 'Motif de la probabilite';
    choixLbl   = (PROBA_LABELS[valeur]||'') + ' (' + valeur + ')';
    btnLbl     = 'Valider et choisir la gravite';
  } else {
    defautOaci = GRAVITE_HELP[valeur] || '';
    dejaSaisi  = E_JUSTIF_GRAVITE;
    titre      = 'Motif de la gravite';
    choixLbl   = (GRAVITE_LABELS[valeur]||'') + ' (' + valeur + ')';
    btnLbl     = 'Valider';
  }
  $('#motifTitre').text(titre);
  $('#motifChoixVal').text(choixLbl);
  $('#motifBtnLbl').text(btnLbl);
  $('#motifTexte').val((dejaSaisi!=null && dejaSaisi!=='') ? dejaSaisi : defautOaci);
  $('#modalMotif').attr('data-mode','edit');
  new bootstrap.Modal('#modalMotif').show();
  setTimeout(function(){ $('#motifTexte').focus(); }, 300);
}

// Validation du motif en mode edition
$(document).on('click','#motifValider',function(){
  if($('#modalMotif').attr('data-mode')!=='edit' || !E_MOTIF_CTX) return;
  const type=E_MOTIF_CTX.type;
  const txt=$('#motifTexte').val().trim();
  if(!txt){ Swal.fire({icon:'warning',text:'Veuillez saisir un motif.',confirmButtonColor:'#D32F2F'}); return; }
  if(type==='proba'){
    E_JUSTIF_PROBA=txt;
    bootstrap.Modal.getInstance(document.getElementById('modalMotif')).hide();
    onRisqueEdit();
    setTimeout(function(){ $('#e_gravite').focus(); }, 350);
  } else {
    E_JUSTIF_GRAVITE=txt;
    bootstrap.Modal.getInstance(document.getElementById('modalMotif')).hide();
    onRisqueEdit();
  }
  $('#modalMotif').attr('data-mode','');
  E_MOTIF_CTX=null;
});

function onRisqueEdit(){
  const proba=$('#e_proba').val();
  const gravite=$('#e_gravite').val();
  $('#e_probaHelp').text(proba?(PROBA_HELP[proba]||''):'');
  $('#e_graviteHelp').text(gravite?(GRAVITE_HELP[gravite]||''):'');
  if(!proba || !gravite){
    $('#e_indice').text('-').css({'border-color':'#e2e8f0','color':'#2C3E50','background':'#fff'});
    $('#e_tol').text('-').css({'border-color':'#e2e8f0','color':'#2C3E50','background':'#fff'});
    $('#e_catAuto').text('-').css({'border-color':'#e2e8f0','color':'#2C3E50','background':'#fff'});
    return;
  }
  const indice=proba+gravite;
  const tol=tolerabiliteDe(indice);
  $('#e_indice').text(indice).css({'border-color':tol?tol.color:'#e2e8f0','color':tol?tol.color:'#2C3E50','background':tol?tol.bg:'#fff'});
  $('#e_tol').text(tol?tol.label:'-').css({'border-color':tol?tol.color:'#e2e8f0','color':tol?tol.color:'#2C3E50','background':tol?tol.bg:'#fff'});
  const cat=tol?tol.categorie:'';
  $('#e_catAuto').text(cat?CAT_LABELS[cat]:'-').css({'border-color':tol?tol.color:'#e2e8f0','color':tol?tol.color:'#2C3E50','background':tol?tol.bg:'#fff'});
  $('#e_categorie').val(cat);
  recomputeEditDates();
}

function recomputeEditDates(){
  // A l'initialisation du formulaire, on conserve les dates enregistrees de la
  // fiche (ne pas ecraser). On ne recalcule que sur action de l'utilisateur.
  if(E_INIT_LOCK) return;
  const cat=$('#e_categorie').val();
  const dr=$('#e_date_rapport').val();
  const de=$('#e_date_emission').val()||new Date().toISOString().substring(0,10);
  function addDays(d,n){const t=new Date(d);t.setDate(t.getDate()+n);return t.toISOString().substring(0,10);}
  function addMonths(d,n){const t=new Date(d);t.setMonth(t.getMonth()+n);return t.toISOString().substring(0,10);}
  if(cat==='critique'){ $('#e_date_rep').val(de); $('#e_date_lim').val(de); }
  else if(cat==='majeur'){ $('#e_date_rep').val(dr?addDays(dr,30):$('#e_date_rep').val()); $('#e_date_lim').val(dr?addMonths(dr,3):$('#e_date_lim').val()); }
  else if(cat==='mineur'){ $('#e_date_rep').val(dr?addDays(dr,30):$('#e_date_rep').val()); $('#e_date_lim').val(dr?addMonths(dr,6):$('#e_date_lim').val()); }
  else if(cat==='observation'){ $('#e_date_rep').val(''); $('#e_date_lim').val(''); }
}

/* ============================================================
 *  CONSULTATION : rendu complet de la fiche (tous les champs)
 *  Presentation en sections, lecture seule, valeurs sur fond gris.
 * ============================================================ */
const MOIS_FR=['','Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre'];
const ETAT_LBL={documente_non_implemente:'Documente, pas mis en oeuvre',
                implemente_non_documente:'Mis en oeuvre, pas documente',
                non_documente_non_implemente:'Pas documente, pas mis en oeuvre'};
const STATUT_FNC={1:'Accepte mais non verifie',2:'Rejetee',3:'Fermee',4:'Ouverte'};
const SD_LBL={'D':'Depasse','ND':'Non depasse'};

function dRow(lbl, val, large){
  const v=(val===null||val===undefined||String(val).trim()==='')?'<span style="color:#adb5bd">-</span>':val;
  return '<div class="'+(large?'col-12':'col-md-4')+'">'
    +'<div class="d-lbl">'+esc(lbl)+'</div>'
    +'<div class="d-val">'+v+'</div></div>';
}
function dSection(titre, icone, contenu){
  return '<div class="d-sec"><div class="d-sec-h"><i class="bi '+icone+'"></i>'+esc(titre)+'</div>'
    +'<div class="row g-2">'+contenu+'</div></div>';
}
function dBadge(txt, bg, tc){
  return '<span style="background:'+bg+';color:'+tc+';padding:2px 10px;border-radius:20px;font-size:.76rem;font-weight:700">'+esc(txt)+'</span>';
}
function joursEntre(d1,d2){
  if(!d1||!d2) return null;
  const a=new Date(String(d1).substring(0,10)), b=new Date(String(d2).substring(0,10));
  if(isNaN(a)||isNaN(b)) return null;
  return Math.round((b-a)/86400000);
}

function buildFncDetail(f, sds, regs){
  const dEm = String(f.date_emission||'').substring(0,10);
  const mois = dEm ? MOIS_FR[parseInt(dEm.substring(5,7),10)] : '';
  const annee= dEm ? dEm.substring(0,4) : '';
  const sdTxt = (sds||[]).map(function(x){ return esc(x.nom_sousdomaine); }).join(', ');
  const regTxt= (regs||[]).map(function(x){ return esc(x.code_reglement)+(x.libelle_reglement?(' - '+esc(x.libelle_reglement)):''); }).join('<br>');
  const catBg=CATEG_BG[f.categorie]||'#f1f5f9', catTc=CATEG_COLORS[f.categorie]||'#555';
  const stBg =STATUT_COLORS[f.statut]||'#f1f5f9', stTc=STATUT_TC[f.statut]||'#555';
  const pacS = function(v){ return v==='S' ? dBadge('S','#d1fae5','#065f46') : (v==='NS' ? dBadge('NS','#fee2e2','#991b1b') : '-'); };
  const retard = f.date_reponse_exigee && f.date_reponse_exigee < new Date().toISOString().substring(0,10) && Number(f.statut)!==3;

  let h='';

  // 1. Identification
  h+=dSection('Identification de la fiche','bi-hash',
      dRow('N FNC','<strong style="color:#D32F2F;font-family:monospace;font-size:.95rem">'+esc(f.num_fnc||'')+'</strong>')
    + dRow('Source', esc(f.source_audit||''))
    + dRow('N Audit','<strong style="color:#23408F">'+esc(f.num_audit||'-')+'</strong>')
    + dRow('Nature de l\'acte', esc(TYPE_LABELS?TYPE_LABELS[f.type_activite]||f.type_activite||'':(f.type_activite||'')))
    + dRow('Cadre', esc(f.cadre||''))
    + dRow('Statut de la fiche', dBadge(STATUT_FNC[f.statut]||'-',stBg,stTc) + (retard?' <span style="color:#D32F2F;font-size:.72rem"><i class="bi bi-clock"></i> en retard</span>':''))
  );

  // 2. Dates et delais
  // Delai de transmission = Date de transmission du rapport - Date de l'audit / inspection.
  // Le calcul prime sur la valeur stockee, qui peut etre erronee.
  const dateActe = f.date_realisation || f.date_previsionnelle;
  let delaiTr = joursEntre(dateActe, f.date_transmission_rapport);
  if(delaiTr === null && f.delais_transmission !== null && f.delais_transmission !== ''){
    delaiTr = parseInt(f.delais_transmission,10);
  }
  h+=dSection('Dates et delais','bi-calendar3',
      dRow('Date de l\'audit / inspection', fmtDate(f.date_realisation||f.date_previsionnelle))
    + dRow('Date d\'emission de la FNC', fmtDate(f.date_emission))
    + dRow('Mois / Annee', (mois?esc(mois):'-')+(annee?(' '+esc(annee)):''))
    + dRow('Date de transmission du rapport', fmtDate(f.date_transmission_rapport))
    + dRow('Delai de transmission', (delaiTr!==null&&delaiTr!==''?(delaiTr+' jour(s)'):'-'))
    + dRow('Date de reponse exigee', '<span style="'+(retard?'color:#D32F2F;font-weight:700':'')+'">'+fmtDate(f.date_reponse_exigee)+'</span>')
    + dRow('Date limite de mise en conformite', fmtDate(f.date_limite_mise_conformite))
    + dRow('Date effective de cloture', fmtDate(f.date_effective_cloture))
  );

  // 3. Operateur et perimetre
  h+=dSection('Operateur et perimetre','bi-buildings',
      dRow('Operateur','<strong>'+esc(f.nomorga||'-')+'</strong>'+(f.trigrorganisme?(' <span style="color:#6b7a90">('+esc(f.trigrorganisme)+')</span>'):''))
    + dRow('Activites de l\'operateur', esc(f.type_activite_operateur||''))
    + dRow('Lieu', esc(f.ville||f.nomsite||f.indicateur_oaci||f.site_inspection||''))
    + dRow('Domaine', esc((f.nomdomaine||'')+(f.libel_domaine?(' - '+f.libel_domaine):'')))
    + dRow('Sous-domaine(s)', sdTxt, true)
    + dRow('Representant de l\'operateur', esc(f.representant_operateur||''))
    + dRow('Titre du representant', esc(f.titre_representant||''))
  );

  // 4. Constatation
  // Retire les balises HTML. Decode d'abord les entites (&lt;p&gt; -> <p>)
  // pour gerer les libelles stockes en HTML echappe, puis strippe les balises.
  const stripTags = function(v){
    let s=String(v||'');
    const ta=document.createElement('textarea'); ta.innerHTML=s; s=ta.value;
    return s.replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim();
  };
  h+=dSection('Constatation','bi-clipboard-text',
      dRow('Libelle', esc(stripTags(f.libelle)), true)
    + dRow('Description de la constatation','<div style="white-space:normal">'+(f.description_constatation||'')+'</div>', true)
    + dRow('Etat', esc(ETAT_LBL[f.etat]||f.etat||''))
    + dRow('Categorie', dBadge((f.categorie||'-').toUpperCase(),catBg,catTc))
    + (f.indice_risque ? (
        dRow('Indice de risque (OACI)', '<strong style="font-size:1rem;color:'+catTc+'">'+esc(f.indice_risque)+'</strong>'
          + (f.probabilite&&f.gravite ? ' <span style="color:#6b7a90;font-size:.8rem">(Probabilite '+esc(f.probabilite)+' &times; Gravite '+esc(f.gravite)+')</span>' : ''))
      + (f.justif_probabilite ? dRow('Justification - Probabilite', esc(f.justif_probabilite), true) : '')
      + (f.justif_gravite ? dRow('Justification - Gravite', esc(f.justif_gravite), true) : '')
      ) : '')
  );

  // 5. Referentiel
  h+=dSection('Referentiel','bi-book',
      dRow('Reglement(s) vise(s)', regTxt || esc(f.ref_reglement||''), true)
    + dRow('Ref. reglement IEM, MP ou autres', esc(f.ref_reglement_iem||''), true)
    + dRow('Manuel', esc(f.manuel||''))
    + dRow('Autres references', esc(f.autres||''))
  );

  // 6. Plan d'actions correctives
  h+=dSection('Plan d\'actions correctives (PAC) de l\'operateur','bi-check2-square',
      dRow('Analyse des causes','<div style="white-space:pre-wrap">'+esc(f.analyse_causes||'')+'</div>', true)
    + dRow('Action(s) correctrice(s)','<div style="white-space:pre-wrap">'+esc(f.actions_correctives||'')+'</div>', true)
  );

  // 7. Section ANAC
  h+=dSection('Section reservee a l\'ANAC','bi-shield-check',
      dRow('Pertinent', pacS(f.pac_pertinent))
    + dRow('Exhaustif', pacS(f.pac_exhaustif))
    + dRow('Detaille',  pacS(f.pac_detaille))
    + dRow('Specifique',pacS(f.pac_specifique))
    + dRow('Realiste',  pacS(f.pac_realiste))
    + dRow('Coherent',  pacS(f.pac_coherent))
    + dRow('Acceptation du PAC', f.pac_acceptation
        ? (f.pac_acceptation==='acceptee' ? dBadge('Acceptee','#d1fae5','#065f46') : dBadge('Refusee','#fee2e2','#991b1b'))
        : '')
    + dRow('Verification de mise en oeuvre', Number(f.verification_meo)===1
        ? dBadge('Effectuee et verifiee','#d1fae5','#065f46') : dBadge('Non verifiee','#f1f5f9','#5b6b85'))
    + dRow('Nom, visa et date', esc(f.nom_visa_date||''))
    + dRow('Observation','<div style="white-space:pre-wrap">'+esc(f.observation||'')+'</div>', true)
  );

  // 8. Suivi et efficacite
  h+=dSection('Suivi de la mise en conformite','bi-graph-up',
      dRow('Agent ANAC ayant etabli la fiche', esc(f.nom_inspecteur||''))
    + dRow('Agent en charge du suivi', esc(f.nom_agent_suivi||f.nom_inspecteur||''))
    + dRow('Statut delais efficacite', f.statut_delais_efficacite
        ? (f.statut_delais_efficacite==='D' ? dBadge('Depasse','#fee2e2','#991b1b') : dBadge('Non depasse','#d1fae5','#065f46'))
        : '')
    + dRow('Delai de mise en conformite exige', f.delais_mise_conformite_exige ? (esc(f.delais_mise_conformite_exige)+' jour(s)') : '')
    + dRow('Delai de mise en conformite reel',  f.delais_mise_conformite_reel  ? (esc(f.delais_mise_conformite_reel)+' jour(s)')  : '')
    + dRow('Efficacite de mise en conformite','<div style="white-space:pre-wrap">'+esc(f.efficacite_mise_conformite||'')+'</div>', true)
    + dRow('Autres documents du dossier', f.autres_documents
        ? '<button type="button" class="btn btn-sm btn-outline-warning" id="btnVoirAutresDetail" data-id="'+esc(f.idfnc)+'"><i class="bi bi-folder2-open me-1"></i>Consulter le document</button>'
        : '<span style="color:#adb5bd">Aucun document complementaire</span>', true)
    + dRow('Preuve de suivi et verification de l\'efficacite', f.preuve_suivi
        ? '<button type="button" class="btn btn-sm btn-outline-success" id="btnVoirPreuveDetail" data-id="'+esc(f.idfnc)+'"><i class="bi bi-shield-check me-1"></i>Consulter le document</button>'
        : '<span style="color:#adb5bd">Aucun document joint</span>', true)
    + dRow('Observations / courriers','<div style="white-space:pre-wrap">'+esc(f.observations_courriers||'')+'</div>', true)
    + dRow('Relance','<div style="white-space:pre-wrap">'+esc(f.relance||'')+'</div>', true)
  );

  // 9. Piece jointe et tracabilite
  h+=dSection('Piece jointe et tracabilite','bi-paperclip',
      dRow('Fiche signee (PDF)', f.fichier_fnc
        ? '<button type="button" class="btn btn-sm btn-outline-primary" id="btnVoirFicheJointeDetail" data-id="'+esc(f.idfnc)+'"><i class="bi bi-file-earmark-pdf me-1"></i>Consulter le document</button>'
        : '<span style="color:#adb5bd">Aucun document joint</span>')
    + dRow('Creee le', fmtDate(f.created_at)+(String(f.created_at||'').length>10?(' a '+String(f.created_at).substring(11,16)):''))
    + dRow('Derniere modification', fmtDate(f.updated_at)+(String(f.updated_at||'').length>10?(' a '+String(f.updated_at).substring(11,16)):''))
  );

  return h;
}

/* Consultation du PDF depuis la fiche detaillee */
$(document).on('click','#btnVoirFicheJointeDetail',function(){
  const id=$(this).data('id'); if(!id) return;
  const url=AGAI_BASE+'/api/nonconformites?action=serve_fiche&idfnc='+encodeURIComponent(id);
  $('#pdfFrame').attr('src', url);
  $('#pdfDownload').attr('href', url).attr('download','FNC_'+String($('#detailFncNum').text()||'fiche').replace(/[^A-Za-z0-9._-]/g,'_')+'.pdf');
  $('#pdfPrint').data('url', url);
  new bootstrap.Modal('#modalPdfViewer').show();
});

/* ===== CONSULTATION : fiche complete en lecture seule ===== */
$(document).on('click','.btn-view-fnc',function(){
  const id=$(this).data('id');
  apiPost({action:'get',idfnc:id}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',title:'Acces',text:res.message||'Acces refuse a cette fiche.',confirmButtonColor:'#23408F'}); return; }
    const f=res.data;
    FNC_VUE = f;
    $('#detailFncNum').text(f.num_fnc);
    $('#detailFncBody').html(buildFncDetail(f, res.sousdomaines||[], res.reglements||[]));
    new bootstrap.Modal('#modalDetailFnc').show();
  });
});
let FNC_VUE = null;
$(document).on('click','#btnPrintDetail',function(){
  if(FNC_VUE && FNC_VUE.idfnc){ $('.btn-print-fnc[data-id="'+FNC_VUE.idfnc+'"]').first().trigger('click'); }
});

$(document).on('click','.btn-edit-fnc',function(){
  const id=$(this).data('id');
  apiPost({action:'get',idfnc:id}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',title:'Acces',text:res.message||'Acces refuse a cette fiche.',confirmButtonColor:'#23408F'}); return; }
    const f=res.data;
    E_INIT_LOCK = true;   // protege les dates chargees pendant toute l'initialisation
    $('#editFncNum').text(f.num_fnc);
    $('#editFncPaper').html(buildFncEditForm(f, res.sousdomaines||[], res.reglements||[], res.sd_options||[], res.reg_options||[]));
    const m=new bootstrap.Modal('#modalEditFnc'); m.show();
    document.getElementById('modalEditFnc').addEventListener('shown.bs.modal', function handler(){
      this.removeEventListener('shown.bs.modal', handler);
      $('#sdSelect_E').select2({theme:'bootstrap-5',width:'100%',placeholder:'Choisir sous-domaine(s)',allowClear:true,dropdownParent:$('#modalEditFnc')});
      $('#regSelect_E').select2({theme:'bootstrap-5',width:'100%',placeholder:'Choisir reglement(s)',allowClear:true,dropdownParent:$('#modalEditFnc')});
      // Editeur Quill pour la description (charge le HTML existant)
      if(typeof Quill!=='undefined' && document.getElementById('e_descQuill')){
        const qe=new Quill('#e_descQuill',{theme:'snow',placeholder:'Decrire la constatation...',
          modules:{toolbar:[['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['clean']]}});
        const val=(f.description_constatation||f.libelle||'');
        if(val){ if(String(val).trim().charAt(0)==='<'){ qe.root.innerHTML=val; } else { qe.setText(val); } }
        $('#e_description').val(qe.root.innerHTML);
        qe.on('text-change',function(){ $('#e_description').val(qe.root.innerHTML); });
        window.E_DESC_QUILL=qe;
      }
      // Evaluation des risques : proba + gravite -> indice, tolerabilite, categorie
      // Motifs existants de la fiche (modifiables) recuperes au chargement
      E_JUSTIF_PROBA   = f.justif_probabilite || '';
      E_JUSTIF_GRAVITE = f.justif_gravite || '';
      $('#e_proba').on('change',function(){
        const v=$(this).val();
        if(v){ ouvrirModaleMotifEdit('proba', v); } else { onRisqueEdit(); }
      });
      $('#e_gravite').on('change',function(){
        const v=$(this).val();
        if(v){ ouvrirModaleMotifEdit('gravite', v); } else { onRisqueEdit(); }
      });
      onRisqueEdit(); // initialiser l'affichage depuis les valeurs existantes
      // Statut, cloture et efficacite : recalcul a chaque changement
      $('[name="e_decision"]').on('change', majStatutSuivi);
      $('#e_delai_reel, #e_delai_exige').on('change', majEfficacite);
      // Le delai exige suit toujours la date limite de mise en conformite
      $('#e_date_lim').on('change', function(){
        $('#e_delai_exige').val($(this).val());
        majEfficacite();
      });
      majStatutSuivi();
      // Style des options radio (identique au formulaire d'ouverture)
      $('#modalEditFnc .radio-opt input').on('change',function(){
        const n=$(this).attr('name');
        $('[name="'+n+'"]').closest('.radio-opt').removeClass('selected');
        $(this).closest('.radio-opt').addClass('selected');
      });
      // Init terminee : on autorise a nouveau le recalcul des dates sur action utilisateur
      E_INIT_LOCK = false;
    });
  });
});

$('#btnSaveEditFnc').on('click',function(){
  const sds=$('#sdSelect_E').val()||[];
  const regs=$('#regSelect_E').val()||[];
  const eDescTxt = window.E_DESC_QUILL ? window.E_DESC_QUILL.getText().trim() : $('#e_description').val().replace(/<[^>]*>/g,'').trim();
  if(!eDescTxt){ Swal.fire({icon:'warning',text:'La description de la constatation est obligatoire.',confirmButtonColor:'#D32F2F'}); return; }
  if(!$('[name="e_etat"]:checked').val()){ Swal.fire({icon:'warning',text:'Veuillez choisir l\'etat.',confirmButtonColor:'#D32F2F'}); return; }
  if(!$('#e_proba').val() || !$('#e_gravite').val()){ Swal.fire({icon:'warning',text:'Veuillez completer l\'evaluation des risques (probabilite et gravite).',confirmButtonColor:'#D32F2F'}); return; }
  const d={
    action:'update', idfnc:$('#e_idfnc').val(),
    categorie:$('#e_categorie').val(),
    probabilite:$('#e_proba').val()||'',
    gravite:$('#e_gravite').val()||'',
    indice_risque:($('#e_proba').val()||'')+($('#e_gravite').val()||''),
    justif_probabilite:(E_JUSTIF_PROBA!=null&&E_JUSTIF_PROBA!=='')?E_JUSTIF_PROBA:(PROBA_HELP[$('#e_proba').val()]||''),
    justif_gravite:(E_JUSTIF_GRAVITE!=null&&E_JUSTIF_GRAVITE!=='')?E_JUSTIF_GRAVITE:(GRAVITE_HELP[$('#e_gravite').val()]||''),
    date_emission:$('#e_date_emission').val(),
    representant_operateur:$('#e_representant').val(),
    titre_representant:$('#e_titre').val(),
    description_constatation:$('#e_description').val(),
    libelle:$('#e_description').val(),
    etat:$('[name="e_etat"]:checked').val()||'',
    manuel:$('#e_manuel').val(),
    autres:$('#e_autres').val(),
    date_reponse_exigee:$('#e_date_rep').val(),
    date_limite_mise_conformite:$('#e_date_lim').val(),
    analyse_causes:$('#e_causes').val(),
    actions_correctives:$('#e_actions').val(),
    observation:$('#e_observation').val(),
    pac_pertinent:$('[name="e_pac_pertinent"]:checked').val()||'',
    pac_exhaustif:$('[name="e_pac_exhaustif"]:checked').val()||'',
    pac_detaille:$('[name="e_pac_detaille"]:checked').val()||'',
    pac_specifique:$('[name="e_pac_specifique"]:checked').val()||'',
    pac_realiste:$('[name="e_pac_realiste"]:checked').val()||'',
    pac_coherent:$('[name="e_pac_coherent"]:checked').val()||'',
    pac_acceptation:(function(){ const d=$('#e_decision_val').val()||''; return (d==='acceptee'||d==='refusee')?d:''; })(),
    verification_meo:($('#e_decision_val').val()==='meo')?1:0,
    nom_visa_date:$('#e_visa').val(),
    statut:$('#e_statut_calcule').val()||4,
    date_effective_cloture:$('#e_date_cloture').val()||'',
    delais_mise_conformite_exige:$('#e_delai_exige').val()||'',
    delais_mise_conformite_reel:$('#e_delai_reel').val()||'',
    efficacite_mise_conformite:$('#e_efficacite').val()||'',
    observations_courriers:$('#e_obs_courriers').val()||'',
    relance:$('#e_relance').val()||''
  };
  sds.forEach(function(s,i){ d['sousdomaines['+i+']']=s; });
  regs.forEach(function(r,i){ d['reglements['+i+']']=r; });
  const btn=$(this); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  // Fiche signee : si un nouveau PDF est choisi, il remplace l'ancien (supprime du disque)
  const inpEF = document.getElementById('e_fichier');
  const ficE  = (inpEF && inpEF.files && inpEF.files[0]) ? inpEF.files[0] : null;
  const inpPR = document.getElementById('e_preuve');
  const ficPR = (inpPR && inpPR.files && inpPR.files[0]) ? inpPR.files[0] : null;
  const inpAU = document.getElementById('e_autres_docs');
  const ficAU = (inpAU && inpAU.files && inpAU.files[0]) ? inpAU.files[0] : null;
  if(ficAU && !/\.pdf$/i.test(ficAU.name)){
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer le suivi');
    Swal.fire({icon:'warning',title:'Fichier invalide',text:'Les autres documents doivent etre au format PDF.',confirmButtonColor:'#D32F2F'});
    return;
  }
  if(ficPR && !/\.pdf$/i.test(ficPR.name)){
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer le suivi');
    Swal.fire({icon:'warning',title:'Fichier invalide',text:'La preuve de suivi doit etre un PDF.',confirmButtonColor:'#D32F2F'});
    return;
  }
  if(ficE && !/\.pdf$/i.test(ficE.name)){
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer les modifications');
    Swal.fire({icon:'warning',title:'Fichier invalide',text:'La fiche signee doit etre un PDF.',confirmButtonColor:'#D32F2F'});
    return;
  }
  // Message d'attente : l'assemblage des PDF cote serveur prend quelques secondes
  const avecFichiers = !!(ficE || ficPR || ficAU);
  if(avecFichiers){
    Swal.fire({
      title:'Traitement des documents',
      html:'<div style="font-size:.88rem;color:#5b6b85">Les fichiers PDF sont assembles en un document unique.<br>'
          +'Cette operation peut prendre quelques secondes.</div>',
      allowOutsideClick:false, allowEscapeKey:false,
      didOpen:function(){ Swal.showLoading(); }
    });
  }
  (avecFichiers ? apiPostFile(d, ficE, ficPR, ficAU) : apiPost(d)).done(function(res){
    if(avecFichiers){ Swal.close(); }
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer les modifications');
    if(res.success){
      bootstrap.Modal.getInstance('#modalEditFnc').hide();
      Swal.fire({icon:'success',title:'FNC mise a jour',timer:1600,showConfirmButton:false});
      loadFncList();
    } else { Swal.fire({icon:'error',title:'Erreur',text:res.message||'Echec de la mise a jour.',confirmButtonColor:'#23408F'}); }
  }).fail(function(){ btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer les modifications'); Swal.fire({icon:'error',text:'Echec de la requete.'}); });
});

/* Fenetres modales imbriquees : la derniere ouverte doit rester au premier plan.
   Sans cela, le lecteur PDF et l'apercu d'impression apparaissent derriere la fiche. */
function modaleAuPremierPlan($modal){
  $modal.off('show.bs.modal.zfix').on('show.bs.modal.zfix', function(){
    const base = 1060;
    const nb   = $('.modal.show').length;   // nombre de modales deja ouvertes
    $(this).css('z-index', base + (nb+1)*20);
    setTimeout(function(){
      $('.modal-backdrop').last().css('z-index', base + (nb+1)*20 - 10);
    }, 0);
  });
}
$(function(){
  modaleAuPremierPlan($('#modalPdfViewer'));
  modaleAuPremierPlan($('#modalPrint'));
});

/* Consultation d'un document de la fiche (fiche signee ou preuve de suivi) */
function ouvrirDocFnc(idfnc, num, doc){
  const url = AGAI_BASE+'/api/nonconformites?action=serve_fiche&doc='+encodeURIComponent(doc)+'&idfnc='+encodeURIComponent(idfnc);
  const LBLS = {preuve:'Preuve de suivi et verification de l\'efficacite',
                autres:'Autres documents du dossier',
                fiche :'Fiche de non-conformite signee'};
  const COLS = {preuve:'#1E9C4B', autres:'#b58a00', fiche:'#23408F'};
  const lbl = LBLS[doc] || LBLS.fiche;
  const col = COLS[doc] || COLS.fiche;
  $('#modalPdfViewer .modal-title').html('<i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i>'
    + '<span style="color:'+col+';background:#fff;padding:1px 8px;border-radius:6px">'+esc(lbl)+'</span> - '+esc(num||''));
  $('#pdfFrame').attr('src', url);
  const PREF = {preuve:'Preuve_', autres:'Documents_', fiche:'FNC_'};
  $('#pdfDownload').attr('href', url).attr('download', (PREF[doc]||'FNC_')+String(num||'doc').replace(/[^A-Za-z0-9._-]/g,'_')+'.pdf');
  $('#pdfPrint').data('url', url);
  new bootstrap.Modal('#modalPdfViewer').show();
}
$(document).on('click','.btn-doc-fnc',function(){
  ouvrirDocFnc($(this).data('id'), $(this).data('num'), String($(this).data('doc')||'fiche'));
});
/* Depuis la fiche detaillee */
$(document).on('click','#btnVoirPreuveDetail',function(){
  ouvrirDocFnc($(this).data('id'), $('#detailFncNum').text(), 'preuve');
});
$(document).on('click','#btnVoirAutresDetail',function(){
  ouvrirDocFnc($(this).data('id'), $('#detailFncNum').text(), 'autres');
});

/* Consultation de la fiche signee : fenetre modale avec telechargement et impression */
$(document).on('click','#btnVoirFicheJointe',function(){
  const id=$('#e_idfnc').val();
  if(!id) return;
  const url=AGAI_BASE+'/api/nonconformites?action=serve_fiche&idfnc='+encodeURIComponent(id);
  const numFnc = ($('#editFncNum').text() || '').trim().replace(/[^A-Za-z0-9._-]/g,'_');
  $('#pdfFrame').attr('src', url);
  $('#pdfDownload').attr('href', url).attr('download', 'FNC_'+(numFnc||'fiche')+'.pdf');
  $('#modalPdfViewer .modal-title').html('<i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i>Fiche signee - '+esc($('#editFncNum').text()||''));
  $('#pdfPrint').data('url', url);
  new bootstrap.Modal('#modalPdfViewer').show();
});
$(document).on('click','#pdfPrint',function(){
  const w=window.open($(this).data('url'), '_blank');
  if(w){ w.addEventListener('load', function(){ try{ w.print(); }catch(e){} }); }
});
$('#modalPdfViewer').on('hidden.bs.modal', function(){ $('#pdfFrame').attr('src',''); });

/* Zone PAC / ANAC : afficher ou masquer au clic sur le bandeau */
$(document).on('click','.pac-toggle',function(){
  const idx=$(this).data('idx');
  const $z=$('.pac-zone[data-idx="'+idx+'"]');
  const visible=$z.is(':visible');
  $z.slideToggle(160);
  $(this).find('.pac-chev').toggleClass('bi-chevron-right', visible).toggleClass('bi-chevron-down', !visible);
});

/* ============================================================
 *  EXPORT EXCEL : registre de suivi des non-conformites
 *  Exporte exactement les fiches visibles (filtres appliques).
 * ============================================================ */
const XLS_TITRE = "SUIVI DES NON-CONFORMITES DES OPERATEURS DU SECTEUR AERIEN";
const XLS_COLS = [
  'N FNC','Source','Audit','Date de l\'audit / inspection','Date d\'emission de la FNC',
  'Mois','Annee','Date de transmission du rapport','Delais de transmission des rapports / FNC',
  'Operateur','Activites de l\'operateur','Domaine','Lieu','Sous-domaine','Referentiel',
  'Libelle','Etat','Categorie','Ref. Reglem.','Ref. Reglem. IEM, MP ou autres',
  'Date de reponse exigee','Date limite de mise en conformite',
  'Agent ANAC ayant etabli la FNC et en charge de son suivi','Statut',
  'Date effective de cloture','Delais de mise en conformite exige','Delais de mise en conformite reel',
  'Efficacite de mise en conformite','Statut delais efficacite (D / ND)',
  'Preuve de suivi et verification de l\'efficacite de l\'action corrective',
  'Observations / Courriers','Relance'
];

function xlsLigne(f){
  // Retire les balises HTML (le libelle est saisi via Quill et stocke en HTML)
  const xlsStrip=function(v){
    let s=String(v||'');
    const ta=document.createElement('textarea'); ta.innerHTML=s; s=ta.value;
    return s.replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').replace(/\s+/g,' ').trim();
  };
  const dEm  = String(f.date_emission||'').substring(0,10);
  const mois = dEm ? MOIS_FR[parseInt(dEm.substring(5,7),10)] : '';
  const annee= dEm ? dEm.substring(0,4) : '';
  const dActe= f.date_realisation || f.date_previsionnelle;
  let delai  = joursEntre(dActe, f.date_transmission_rapport);
  if(delai===null && f.delais_transmission!==null && f.delais_transmission!=='') delai=parseInt(f.delais_transmission,10);

  return [
    f.num_fnc||'', f.source_audit||'', f.num_audit||'',
    fmtDate(dActe), fmtDate(f.date_emission), mois, annee,
    fmtDate(f.date_transmission_rapport), (delai===null?'':delai),
    f.nomorga||'', f.type_activite_operateur||'',
    (f.nomdomaine||''), (f.ville||f.nomsite||f.indicateur_oaci||f.site_inspection||''),
    f.sousdomaines_noms||'', f.reglements_codes||'',
    xlsStrip(f.libelle), (ETAT_LBL[f.etat]||f.etat||''), (f.categorie||''),
    f.ref_reglement||'', f.ref_reglement_iem||'',
    fmtDate(f.date_reponse_exigee), fmtDate(f.date_limite_mise_conformite),
    (f.nom_agent_suivi||f.nom_inspecteur||''),
    (STATUT_FNC[f.statut]||''),
    fmtDate(f.date_effective_cloture),
    (f.delais_mise_conformite_exige||''), (f.delais_mise_conformite_reel||''),
    (f.efficacite_mise_conformite||''),
    (f.statut_delais_efficacite ? (f.statut_delais_efficacite==='D'?'D - Depasse':'ND - Non depasse') : ''),
    (f.preuve_suivi||''), (f.observations_courriers||''), (f.relance||'')
  ];
}

$('#btnExportXls').on('click', function(){
  const data = fncFiltrees();
  if(!data.length){
    Swal.fire({icon:'info',title:'Aucune donnee',text:'Le tableau ne contient aucune fiche pour ces criteres.',confirmButtonColor:'#23408F'});
    return;
  }
  const nbCol = XLS_COLS.length;
  const dateJour = new Date().toLocaleDateString('fr-FR');

  let html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">'
    + '<style>'
    + 'body,table,td,th{font-family:Candara,"Segoe UI",Arial,sans-serif}'
    + 'table{border-collapse:collapse}'
    + 'td,th{border:1px solid #b8c4d9;padding:4px 6px;font-size:10pt;vertical-align:top}'
    + 'th{background:#23408F;color:#ffffff;font-weight:bold;text-align:center;font-size:9pt}'
    + '.tt{font-size:15pt;font-weight:bold;color:#23408F;text-align:center}'
    + '.st{font-size:9pt;color:#5b6b85;text-align:center}'
    + '.cc{text-align:center}'
    + '</style></head><body>'
    + '<table>'
    + '<tr><td colspan="'+nbCol+'" bgcolor="#FFFFFF" style="font-family:Candara;font-size:15pt;font-weight:bold;'
    +   'color:#23408F;text-align:center;border:none">'+XLS_TITRE+'</td></tr>'
    + '<tr><td colspan="'+nbCol+'" bgcolor="#FFFFFF" style="font-family:Candara;font-size:9pt;color:#5b6b85;'
    +   'text-align:center;border:none">Agence Nationale de l\'Aviation Civile du Gabon &middot; Edite le '+dateJour+' &middot; '+data.length+' fiche(s)</td></tr>'
    + '<tr><td colspan="'+nbCol+'"></td></tr>'
    + '<tr>' + XLS_COLS.map(function(c){
        return '<td bgcolor="#23408F" style="background-color:#23408F;color:#FFFFFF;font-weight:bold;'
             + 'font-family:Candara;font-size:9pt;text-align:center;border:1px solid #1b3576">'
             + esc(c) + '</td>';
      }).join('') + '</tr>';

  data.forEach(function(f){
    html += '<tr>' + xlsLigne(f).map(function(v, i){
      const centre = [3,4,5,6,7,8,20,21,23,24,25,26].indexOf(i) >= 0 ? ' class="cc"' : '';
      return '<td'+centre+'>' + esc(v===null||v===undefined?'':String(v)) + '</td>';
    }).join('') + '</tr>';
  });

  html += '</table></body></html>';

  const blob = new Blob(['\ufeff'+html], {type:'application/vnd.ms-excel'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'Suivi_non_conformites_' + new Date().toISOString().substring(0,10) + '.xls';
  document.body.appendChild(a); a.click(); a.remove();
});

/* ===== FILTRES ET INDICATEURS ===== */
/* Toutes les listes de filtre sont en Select2, avec recherche integree */
$('#fFnc,#fAudit,#fOrga,#fInsp,#fDom,#fCat,#fStatut').select2({theme:'bootstrap-5',width:'100%',allowClear:false});
$('#fFnc,#fAudit,#fOrga,#fInsp,#fDom,#fCat,#fStatut').on('change', renderFncTable);
$('#btnResetFiltres').on('click', function(){
  $('#fFnc,#fAudit,#fOrga,#fInsp,#fDom,#fCat,#fStatut').val('').trigger('change.select2');
  $('#kpiFnc .kpi-b').removeClass('active');
  FILTRE_RETARD=false;
  renderFncTable();
});
/* Les cartes d'indicateurs servent de filtres rapides */
$(document).on('click','#kpiFnc .kpi-b',function(){
  const f=String($(this).data('f')||'');
  const dejaActif=$(this).hasClass('active');
  $('#kpiFnc .kpi-b').removeClass('active');
  $('#fCat,#fStatut').val('');
  FILTRE_RETARD=false;
  if(!dejaActif && f){
    $(this).addClass('active');
    if(f==='retard'){ FILTRE_RETARD=true; }
    else if(f.indexOf('c-')===0){ $('#fCat').val(f.substring(2)); }
    else { $('#fStatut').val(f); }
  }
  $('#fCat,#fStatut').trigger('change.select2');
  renderFncTable();
});

/* Rappel des regles de categorisation, a la demande */
$('#btnVoirRegles').on('click',function(){
  Swal.fire({
    title:'Regles de categorisation',
    width:640,
    html:'<div style="text-align:left;font-size:.86rem">'
      +'<div style="margin-bottom:9px"><span style="background:#fee2e2;color:#991b1b;padding:2px 10px;border-radius:20px;font-weight:700;font-size:.76rem">Critique</span>'
      +'<div style="margin-top:3px">Reponse et action <strong>immediate</strong>. Date exigee = date d\'emission. Delai = date d\'emission.</div></div>'
      +'<div style="margin-bottom:9px"><span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:20px;font-weight:700;font-size:.76rem">Majeur</span>'
      +'<div style="margin-top:3px">Correction sous <strong>3 mois</strong>. Plan d\'actions fourni au plus tard 1 mois apres reception du rapport. Date exigee = rapport + 30 j.</div></div>'
      +'<div style="margin-bottom:9px"><span style="background:#dbeafe;color:#1e40af;padding:2px 10px;border-radius:20px;font-weight:700;font-size:.76rem">Mineur</span>'
      +'<div style="margin-top:3px">Correction sous <strong>6 mois</strong>. Reponse argumentee attendue. Date exigee = rapport + 30 j.</div></div>'
      +'<div><span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:20px;font-weight:700;font-size:.76rem">Observation</span>'
      +'<div style="margin-top:3px">Aucun delai : <strong>ne genere pas de fiche</strong> de non-conformite.</div></div>'
      +'</div>',
    confirmButtonColor:'#23408F', confirmButtonText:'Compris'
  });
});

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
  sdResetRows();
  $('#sdBackdrop').addClass('show').show();
  const m=new bootstrap.Modal('#modalAddSD',{backdrop:false});
  m.show();
  setTimeout(function(){ $('#sd_rows .sd-nom').first().focus(); },400);
});
/* Lignes dynamiques de sous-domaines */
function sdRowHtml(){
  return '<div class="sd-row d-flex gap-2 mb-2">'+
           '<input type="text" class="form-control form-control-sm sd-nom" maxlength="255" placeholder="Nom du sous-domaine">'+
           '<button type="button" class="btn btn-sm btn-outline-danger sd-row-del" title="Retirer"><i class="bi bi-x-lg"></i></button>'+
         '</div>';
}
function sdResetRows(){ $('#sd_rows').html(sdRowHtml()); }
$('#btnAddSdRow').on('click',function(){
  $('#sd_rows').append(sdRowHtml());
  $('#sd_rows .sd-nom').last().focus();
});
$(document).on('click','.sd-row-del',function(){
  if($('#sd_rows .sd-row').length>1){ $(this).closest('.sd-row').remove(); }
  else { $(this).closest('.sd-row').find('.sd-nom').val('').focus(); }
});
$('#btnSaveSD').on('click',function(){
  doAddSD(true);
});
function doAddSD(closeAfter){
  const idx=$('#sd_bloc_idx').val();
  const domId=$('#sd_dom_id').val();
  const noms=[];
  $('#sd_rows .sd-nom').each(function(){ const v=$(this).val().trim(); if(v) noms.push(v); });
  if(!noms.length){ Swal.fire({icon:'warning',text:'Veuillez saisir au moins un nom de sous-domaine.',confirmButtonColor:'#23408F'}); return; }
  const btn=$('#btnSaveSD'); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Ajout...');
  let done=0, errors=0; let dejaExistants = 0;
  noms.forEach(function(nom){
    // Utiliser l'endpoint nonconformites (accessible a l'inspecteur)
    // Aucun doublon : un nom deja present est simplement selectionne
    const dejaSd = sousDomaineExistant(nom, domId);
    if(dejaSd){
      done++; dejaExistants++;
      const $ss=$('#sdSelect_'+idx);
      const cur=($ss.val()||[]).map(String);
      if(cur.indexOf(String(dejaSd.idsousdomaine))<0){ $ss.val(cur.concat([String(dejaSd.idsousdomaine)])).trigger('change'); }
      if(done===noms.length){
        btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Ajouter');
        if(closeAfter){
          bootstrap.Modal.getInstance('#modalAddSD').hide();
          $('#sdBackdrop').removeClass('show').hide();
          Swal.fire({icon:'info',title:'Deja existant',
            html:'<strong>'+dejaExistants+'</strong> sous-domaine(s) existaient deja : selectionne(s) sans creer de doublon.',
            timer:2600,showConfirmButton:false});
        } else { sdResetRows(); $('#sd_rows .sd-nom').first().focus(); }
      }
      return;
    }
    $.post(API,{csrf_token:CSRF,action:'create_sousdomaine',nom_sousdomaine:nom,iddomaine:domId},null,'json')
    .always(function(res){
      done++;
      if(res&&res.success!==false&&res.idsousdomaine){
        if(/existant/i.test(String(res.message||''))) { dejaExistants++; }
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
            icon: errors===0 ? (dejaExistants>0 ? 'info' : 'success') : 'warning',
            title:(done-errors)+' sous-domaine(s) ajoute(s)',
            html: (dejaExistants>0 ? '<strong>'+dejaExistants+'</strong> existai(en)t deja : ajoute(s) a la selection sans doublon.<br>' : '')
                  + (errors>0 ? errors+' erreur(s) ignoree(s).' : ''),
            timer: dejaExistants>0 ? 2800 : 1800, showConfirmButton:false
          });
        } else {
          sdResetRows(); $('#sd_rows .sd-nom').first().focus();
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
  if(!lib){ Swal.fire({icon:'warning',text:'Le libelle est obligatoire.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}}); return; }
  if(!domId||domId==0){ Swal.fire({icon:'warning',text:'Choisissez d\'abord un domaine pour rattacher le reglement.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}}); return; }
  const btnS=$('#btnSaveReg'), btnC=$('#btnAddRegAndContinue');
  btnS.prop('disabled',true); btnC.prop('disabled',true);
  // Aucun doublon : si le code existe deja, on le selectionne sans rien inserer
  const dejaReg = reglementExistant(code);
  if(dejaReg){
    btnS.prop('disabled',false); btnC.prop('disabled',false);
    const $rs=$('#regSelect_'+idx);
    const cur=($rs.val()||[]).map(String);
    if(cur.indexOf(String(dejaReg.idreglement))<0){ $rs.val(cur.concat([String(dejaReg.idreglement)])).trigger('change'); }
    Swal.fire({icon:'info',title:'Reglement deja existant',
      html:'<strong>'+esc(code)+'</strong> figure deja dans le referentiel de ce domaine.<br>Il a ete selectionne, aucun doublon n\'a ete cree.',
      confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}});
    $('#reg_code_input').val('').focus(); $('#reg_lib_input').val('');
    return;
  }
  $.post(API,{csrf_token:CSRF,action:'create_reglement',code_reglement:code,libelle_reglement:lib,iddomaine:domId},null,'json')
  .always(function(res){
    btnS.prop('disabled',false); btnC.prop('disabled',false);
    if(!res||res.success===false||!res.idreglement){
      Swal.fire({icon:'error',title:'Echec',text:(res&&res.message)||'Le reglement n\'a pas pu etre enregistre.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}});
      return;
    }
    const realId=res.idreglement, label=code+' - '+lib;
    if(!$('#regSelect_'+idx+' option[value="'+realId+'"]').length){
      $('#regSelect_'+idx).append(new Option(label,realId,true,true)).trigger('change');
    } else {
      $('#regSelect_'+idx).val(($('#regSelect_'+idx).val()||[]).concat([String(realId)])).trigger('change');
    }
    if(!REGLEMENTS_AUDIT.find(function(r){return String(r.idreglement)===String(realId);})){
      REGLEMENTS_AUDIT.push({idreglement:realId,code_reglement:code,libelle_reglement:lib,iddomaine:domId});
    }
    const dejaLa = /existant/i.test(String(res.message||''));
    if(closeAfter){
      bootstrap.Modal.getInstance('#modalAddReg').hide();
      $('#regBackdrop').removeClass('show').hide();
      Swal.fire({
        icon: dejaLa ? 'info' : 'success',
        title: dejaLa ? 'Reglement deja existant' : 'Reglement enregistre',
        text:  dejaLa ? (label + ' figurait deja dans le referentiel : il a ete ajoute a la selection.') : label,
        timer: dejaLa ? 2600 : 1500, showConfirmButton:false});
    } else {
      const fb=$('<div style="background:#d1fae5;border-radius:6px;padding:6px 10px;font-size:.8rem;color:#065f46;margin-top:8px"><i class="bi bi-check-circle me-1"></i>'+label+' enregistre !</div>');
      $('#modalAddReg .modal-body').append(fb);
      setTimeout(function(){ fb.fadeOut(400,function(){ $(this).remove(); }); },2000);
      $('#reg_code_input').val('').focus(); $('#reg_lib_input').val('');
    }
  });
}
$('#btnSaveReg').on('click',function(){ doAddReg(true); });
$('#btnAddRegAndContinue').on('click',function(){ doAddReg(false); });
$('#modalAddReg').on('hidden.bs.modal',function(){
  $('#regBackdrop').removeClass('show').hide();
});
// Volet "Comment fonctionne ce module" : repliable (masque par defaut)
$('#guideToggle').on('click', function(){
  const body=$('#guideBody');
  const visible=body.is(':visible');
  body.slideToggle(180);
  $('#guideLbl').text(visible?'Afficher':'Masquer');
  $('#guideChevron').css('transform', visible?'rotate(0deg)':'rotate(180deg)');
});

loadAuditsEligibles();
loadFncList();

// Ouverture automatique du modal "Suivi de la FNC" si on arrive depuis la page
// Suivi NC (lien ouverture-nc?suivi=IDFNC).
(function(){
  const params=new URLSearchParams(window.location.search);
  const idSuivi=params.get('suivi');
  if(!idSuivi) return;
  // On attend que la liste soit chargee, puis on declenche le bouton Suivi.
  let essais=0;
  const timer=setInterval(function(){
    essais++;
    const btn=$('.btn-edit-fnc[data-id="'+idSuivi+'"]').first();
    if(btn.length){ clearInterval(timer); btn.trigger('click'); }
    else if(essais>40){ clearInterval(timer); } // abandon apres ~8s
  }, 200);
})();

// Impression directe d'une fiche (lien ouverture-nc?print=IDFNC), depuis
// l'archivage : ouvre l'apercu imprimable en lecture seule, sans modale de suivi.
(function(){
  const params=new URLSearchParams(window.location.search);
  const idPrint=params.get('print');
  if(!idPrint) return;
  let essais=0;
  const timer=setInterval(function(){
    essais++;
    // Le bouton d'impression de la fiche existe des que la liste est rendue
    const btn=$('.btn-print-fnc[data-id="'+idPrint+'"]').first();
    if(btn.length){ clearInterval(timer); btn.trigger('click'); }
    else if(essais>40){ clearInterval(timer); }
  }, 200);
})();
</script>