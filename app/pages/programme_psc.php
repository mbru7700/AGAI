<?php
/**
 * Page : Programme de Surveillance Continue (PSC) - v2
 * Module : programme_psc
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('programme_psc');
$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$pageTitle = 'Programme PSC';
$active    = 'programme_psc';
$pageIcon  = 'bi-calendar3-week';

/* ------------------------------------------------------------------
 * Statuts des actes de supervision (mise en forme conditionnelle).
 * Calcules cote serveur au chargement : aucune dependance AJAX.
 * Cle : "ANNEE_ISO|CODE|SEMAINE_ISO"  (CODE = indicateur OACI ou trigramme)
 * ------------------------------------------------------------------ */
$PSC_STATUS = [];
$PSC_DIAG   = ['nodate' => 0, 'total' => 0, 'exemples' => []];
try {
    $dbp = Database::getInstance();
    // Audits sans date previsionnelle : impossibles a placer sur une semaine du programme
    $PSC_DIAG['nodate'] = (int)$dbp->execute(
        "SELECT COUNT(*) AS n FROM audit WHERE date_previsionnelle IS NULL OR date_previsionnelle = '0000-00-00'"
    )->fetch()['n'];
    $sitesByName = [];
    foreach ($dbp->execute("SELECT indicateur_oaci, nomsite FROM site WHERE indicateur_oaci<>''")->fetchAll() as $sx) {
        $sitesByName[mb_strtoupper(trim((string)$sx['nomsite']))] = $sx['indicateur_oaci'];
    }
    $auditRows = $dbp->execute(
        "SELECT a.num_audit, a.statut, a.date_previsionnelle, a.idsite, a.site_inspection,
                s.indicateur_oaci, o.trigrorganisme
         FROM audit a
         LEFT JOIN site s      ON s.idsite = a.idsite
         LEFT JOIN organisme o ON o.idorga = a.idorga
         WHERE a.date_previsionnelle IS NOT NULL AND a.date_previsionnelle <> '0000-00-00'"
    )->fetchAll();
    foreach ($auditRows as $ar) {
        try { $dt = new DateTime(substr((string)$ar['date_previsionnelle'], 0, 10)); }
        catch (Throwable $eDt) { continue; }
        $isoY = (int)$dt->format('o');      // annee ISO
        $isoW = (int)$dt->format('W');      // semaine ISO
        if ($isoW < 1 || $isoW > 53) continue;
        $info = ['statut' => (int)($ar['statut'] ?? 1), 'num' => (string)$ar['num_audit']];

        $codes = [];
        // Code site : via idsite, sinon via le nom stocke, sinon premier mot
        $oaci = trim((string)$ar['indicateur_oaci']);
        if ($oaci === '') {
            $si = mb_strtoupper(trim((string)$ar['site_inspection']));
            if ($si !== '') { $oaci = $sitesByName[$si] ?? trim(explode(' ', $si)[0]); }
        }
        if ($oaci !== '') { $codes[] = $oaci; }
        // Code operateur : trigramme
        $trg = trim((string)$ar['trigrorganisme']);
        if ($trg !== '') { $codes[] = $trg; }

        foreach ($codes as $cd) {
            $PSC_STATUS[$isoY . '|' . mb_strtoupper($cd) . '|' . $isoW] = $info;
        }
        $PSC_DIAG['total']++;
        if (count($PSC_DIAG['exemples']) < 30) {
            $PSC_DIAG['exemples'][] = [
                'num'    => (string)$ar['num_audit'],
                'date'   => substr((string)$ar['date_previsionnelle'], 0, 10),
                'sem'    => $isoY . '-S' . $isoW,
                'codes'  => array_map('mb_strtoupper', $codes),
                'statut' => (int)($ar['statut'] ?? 1),
            ];
        }
    }
} catch (Throwable $ePsc) {
    error_log('psc statut map: ' . $ePsc->getMessage());
    $PSC_STATUS = [];
}
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.psc-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.psc-title-band{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff;border-radius:12px 12px 0 0;padding:12px 18px;font-weight:800;letter-spacing:.3px;text-transform:uppercase;font-size:.96rem;display:flex;align-items:center;gap:10px}
.psc-meta{background:#eef3fb;border:1px solid #d6e0f2;border-top:none;border-radius:0 0 12px 12px;padding:8px 16px;font-size:.82rem;color:#33507f;display:flex;flex-wrap:wrap;gap:18px}
.matrix-wrap{overflow:auto;max-height:72vh;border:1px solid #c7d2e6;border-radius:10px;background:#fff}
table.psc-matrix{border-collapse:separate;border-spacing:0;font-size:.76rem}
table.psc-matrix th,table.psc-matrix td{border:1px solid #c7d2e6;padding:2px 4px;white-space:nowrap;vertical-align:middle}
table.psc-matrix thead th{position:sticky;background:#23408F;color:#fff;text-align:center;font-weight:700;text-transform:uppercase;font-size:.72rem;z-index:5}
table.psc-matrix thead tr.r1 th{top:0}
table.psc-matrix thead tr.r2 th{top:26px}
table.psc-matrix thead tr.r1 th.mois{background:#1E9C4B}
.col-sd{min-width:300px;max-width:340px;text-align:left}
.col-rag{min-width:110px}
table.psc-matrix .cfix{position:sticky;left:0;z-index:4;background:#f4f7fb}
table.psc-matrix .cfix-rag{position:sticky;left:300px;z-index:4;background:#f4f7fb}
table.psc-matrix thead .cfix,table.psc-matrix thead .cfix-rag{background:#23408F;z-index:6}
tbody.grp-block tr.grp-row td{background:#5b6b80;color:#fff}
tbody.grp-block tr.grp-row td.cfix{background:#5b6b80}
tbody.grp-block tr.grp-row td.cfix-rag{background:#5b6b80}
tr.grp-row .grp-rub{border:1px solid #8595a8;border-radius:4px;padding:2px 6px;font-size:.8rem;font-weight:700;width:190px;background:#fff;color:#2C3E50}
td.wk{min-width:64px;text-align:center}
td.wk.grpcell{background:#6b7a90}
.it-sd{border:1px solid #dbe3f0;border-radius:4px;padding:2px 5px;font-size:.76rem;width:200px}
.it-rag{border:1px solid #dbe3f0;border-radius:4px;padding:2px 3px;font-size:.72rem;width:82px}
.it-wk{border:1px solid #dbe3f0;border-radius:4px;padding:1px 2px;font-size:.72rem;width:100%;background:#fff}
.it-wk.filled{background:#e8f5ec;font-weight:700;color:#0f5132}
.mini{border:none;border-radius:5px;padding:1px 6px;font-size:.72rem;cursor:pointer;line-height:1.3}
.mini-add{background:#1E9C4B;color:#fff}.mini-add:hover{background:#157a3a}
.mini-item{background:#F3C300;color:#3a2f00}.mini-item:hover{background:#d9ad00}
.mini-del{background:#fee2e2;color:#D32F2F}.mini-del:hover{background:#D32F2F;color:#fff}
.cell-actions{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.psc-legend{font-size:.75rem;color:#6b7a90;margin-top:6px}
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%}
.stat-card[data-stat]{cursor:pointer;transition:.15s}
.stat-card[data-stat]:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(35,64,143,.14);border-color:#c9d6f0}
.stat-card.stat-on{border-color:#23408F;box-shadow:0 0 0 2px rgba(35,64,143,.28)}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto}
.stat-num{font-size:1.5rem;font-weight:700;line-height:1;color:#2C3E50}
.stat-lbl{font-size:.78rem;color:#6b7a90;margin-top:3px}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F}
.ic-green{background:rgba(30,156,75,.12);color:#1E9C4B}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00}
.ic-red{background:rgba(211,47,47,.10);color:#D32F2F}
.filter-bar .form-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#6b7a90;margin-bottom:4px;font-weight:700}
#progTable thead th{background:var(--anac-primary)!important;color:#fff!important;text-transform:uppercase;letter-spacing:.4px;font-size:.76rem}
.trig-cell{cursor:pointer;text-align:center;font-weight:700;color:#23408F;background:#eef3fb}
.trig-cell:hover{outline:2px solid #23408F;outline-offset:-2px}
.tc-1{background:#23408F;color:#fff}.tc-2{background:#E8890C;color:#fff}.tc-3{background:#1E9C4B;color:#fff}
.tc-4{background:#D32F2F;color:#fff}.tc-5{background:#D32F2F;color:#fff}.tc-6{background:#D32F2F;color:#fff}.tc-7{background:#7A8798;color:#fff}
.it-wk.st-1{background:#23408F;color:#fff;font-weight:700}.it-wk.st-2{background:#E8890C;color:#fff;font-weight:700}
.it-wk.st-3{background:#1E9C4B;color:#fff;font-weight:700}.it-wk.st-4,.it-wk.st-5,.it-wk.st-6{background:#D32F2F;color:#fff;font-weight:700}
.it-wk.st-7{background:#7A8798;color:#fff;font-weight:700}
.cell-error{border:2px solid #D32F2F !important;background:#fde8e8 !important;box-shadow:0 0 0 3px rgba(211,47,47,.25);animation:cellPulse 1s ease-in-out 2}
/* Mise en forme conditionnelle des actes de supervision dans le programme */
.it-wk.acte-bleu{background:#23408F !important;color:#fff !important;font-weight:700}
.it-wk.acte-jaune{background:#F3C300 !important;color:#000 !important;font-weight:800}
/* Couleur d'acte sur le container Select2 (cellule semaine) */
.select2-container.acte-bleu-s2 .select2-selection{background:#23408F !important;border-color:#1b3576 !important}
.select2-container.acte-bleu-s2 .select2-selection__rendered{color:#fff !important;font-weight:700 !important}
.select2-container.acte-jaune-s2 .select2-selection{background:#F3C300 !important;border-color:#c9a200 !important}
.select2-container.acte-jaune-s2 .select2-selection__rendered{color:#000 !important;font-weight:800 !important}
.acte-bleu-cell{background:#23408F;color:#fff;font-weight:700}
.acte-jaune-cell{background:#F3C300;color:#000;font-weight:800}
.acte-tile{border:2px solid #dbe3f0;border-radius:12px;padding:14px 8px;text-align:center;cursor:pointer;transition:.15s;background:#f7f9fc}
.acte-tile:hover{border-color:#23408F;background:#eef3fb;transform:translateY(-2px)}
.acte-tile i{font-size:1.5rem;color:#23408F}
.acte-tile.jaune i{color:#b58a00}
.acte-tile .at{font-size:.78rem;font-weight:600;margin-top:5px;color:#2C3E50}
@keyframes cellPulse{0%,100%{box-shadow:0 0 0 3px rgba(211,47,47,.25)}50%{box-shadow:0 0 0 6px rgba(211,47,47,.45)}}
.cell-error+.select2-container .select2-selection{border:2px solid #D32F2F !important;background:#fde8e8 !important;box-shadow:0 0 0 3px rgba(211,47,47,.25) !important}
.nat-tile{border:1px solid #e0e7f2;border-radius:12px;padding:14px 6px;text-align:center;cursor:pointer;transition:.15s;background:#fff;height:100%}
.nat-tile:hover{background:#23408F;color:#fff;transform:translateY(-2px);box-shadow:0 6px 16px rgba(35,64,143,.2)}
.nat-tile i{font-size:1.5rem;display:block;margin-bottom:4px;color:#23408F}.nat-tile:hover i{color:#fff}
.nat-tile .nt{font-size:.76rem;font-weight:600;line-height:1.15}
.nat-tile.prevu{border:2px solid #1E9C4B !important;background:#eafaf0 !important;box-shadow:0 0 0 3px rgba(30,156,75,.2);position:relative}
.nat-tile.prevu::after{content:"\F26B";font-family:"bootstrap-icons";position:absolute;top:4px;right:6px;color:#1E9C4B;font-size:1rem;font-weight:700}
.nat-tile.prevu i,.nat-tile.prevu .nt{color:#177a3a !important}
.cadre-opt{display:block;border:1px solid #e0e7f2;border-radius:8px;padding:9px 12px;margin-bottom:6px;cursor:pointer}
.cadre-opt:hover,.cadre-opt.sel{border-color:#23408F;background:#eef3fb}.cadre-opt input{margin-right:8px}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-calendar3-week me-2" style="color:var(--anac-primary)"></i>Programme de Surveillance Continue</h1>
    <div class="sub">Matrice PSC par annee, type d'activite et domaine.</div>
  </div>
  <button class="btn btn-anac" id="btnNewProg"><i class="bi bi-plus-lg me-1"></i>Nouveau programme</button>
</div>

<!-- VUE LISTE -->
<div id="viewList">
  <div class="d-flex justify-content-end mb-2">
    <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats"><i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsLbl">Masquer les statistiques</span></button>
  </div>
  <div id="statsPanel" class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><div class="stat-card" data-stat="tous" title="Afficher tous les programmes"><div class="stat-ic ic-blue"><i class="bi bi-calendar3-week"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Programmes</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="stat-card" data-stat="valide" title="Filtrer les programmes valides"><div class="stat-ic ic-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-num" id="st_valides">0</div><div class="stat-lbl">Valides</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="stat-card" data-stat="brouillon" title="Filtrer les brouillons"><div class="stat-ic ic-gold"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-num" id="st_brouillons">0</div><div class="stat-lbl">Brouillons</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="stat-card" data-stat="annee" title="Choisir une annee"><div class="stat-ic ic-blue"><i class="bi bi-calendar-range"></i></div><div><div class="stat-num" id="st_annees">0</div><div class="stat-lbl">Annees couvertes</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="stat-card" data-stat="domaine" title="Choisir un domaine"><div class="stat-ic ic-blue"><i class="bi bi-grid-3x3-gap"></i></div><div><div class="stat-num" id="st_domaines">0</div><div class="stat-lbl">Domaines</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="stat-card" data-stat="site" title="Choisir un site"><div class="stat-ic ic-green"><i class="bi bi-geo-alt-fill"></i></div><div><div class="stat-num" id="st_sites">0</div><div class="stat-lbl">Sites planifies</div></div></div></div>
  </div>

  <div class="card-anac p-3 mb-3 filter-bar">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2"><label class="form-label" for="f_annee">Annee</label><select id="f_annee" class="form-select form-select-sm"><option value="">Toutes</option></select></div>
      <div class="col-6 col-md-3"><label class="form-label" for="f_type">Type d'activite</label><select id="f_type" class="form-select form-select-sm"><option value="">Tous</option></select></div>
      <div class="col-6 col-md-3"><label class="form-label" for="f_dom">Domaine</label><select id="f_dom" class="form-select form-select-sm"><option value="">Tous</option></select></div>
      <div class="col-6 col-md-2"><label class="form-label" for="f_site">Site</label><select id="f_site" class="form-select form-select-sm"><option value="">Tous</option></select></div>
      <div class="col-6 col-md-2"><label class="form-label" for="f_statut">Statut</label><select id="f_statut" class="form-select form-select-sm"><option value="">Tous</option><option value="valide">Valide</option><option value="brouillon">Brouillon</option></select></div>
      <div class="col-12 mt-1"><button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetFilters"><i class="bi bi-arrow-counterclockwise me-1"></i>Reinitialiser les filtres</button></div>
    </div>
  </div>

  <div class="card-anac p-3 p-md-4">
    <div class="table-responsive">
      <table id="progTable" class="table table-hover align-middle" style="width:100%">
        <thead><tr>
          <th>Id</th><th>Annee</th><th>Type d'activite</th><th>Domaine</th><th>Titre</th>
          <th>Semaines</th><th>Items</th><th>Statut</th><th>Maj</th><th class="text-end">Actions</th>
          <th>_type</th><th>_dom</th><th>_statut</th><th>_annee</th><th>_sites</th><th>_upd</th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- VUE EDITEUR -->
<div id="viewEditor" style="display:none">
  <div class="psc-toolbar">
    <button class="btn btn-sm btn-outline-secondary" id="btnBackList"><i class="bi bi-arrow-left me-1"></i>Retour</button>
    <button class="btn btn-sm btn-outline-primary" id="btnAddGroup"><i class="bi bi-plus-square me-1"></i>Ajouter un grand titre</button>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-sm btn-outline-danger" id="btnPdf"><i class="bi bi-printer me-1"></i>PDF</button>
      <button class="btn btn-sm btn-outline-success" id="btnXls"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
      <button class="btn btn-sm btn-outline-success" id="btnToggleStatut"><i class="bi bi-patch-check me-1"></i><span id="lblStatut">Valider</span></button>
      <button class="btn btn-sm btn-anac" id="btnSaveProg"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
    </div>
  </div>
  <div class="psc-title-band"><i class="bi bi-calendar3-week"></i><span id="progTitre">-</span></div>
  <div class="psc-meta">
    <span><i class="bi bi-hash me-1"></i>Revision : <strong id="progRev">00</strong></span>
    <span><i class="bi bi-calendar-check me-1"></i>Date : <strong id="progDate">-</strong></span>
    <span><i class="bi bi-grid-3x3 me-1"></i>Semaines : <strong id="progNbSem">-</strong></span>
    <span><i class="bi bi-patch-check me-1"></i>Etat : <strong id="progStatut">brouillon</strong></span>
  </div>
  <div class="matrix-wrap mt-2">
    <table class="psc-matrix" id="matrix"><thead id="matrixHead"></thead></table>
  </div>
  <div class="psc-legend"><i class="bi bi-lightbulb me-1"></i>REFERENTIEL = grand titre (ex: A- Renseignements sur l'aerodrome). Sous chaque titre, ajoutez des SOUS-DOMAINES (items) avec le bouton <span class="mini mini-item">+ item</span>. Les boutons <span class="mini mini-add">+</span> ajoutent une valeur absente (item / RAG / site) sans recharger la page.</div>
</div>

<!-- VUE DECLENCHEMENT (lecture seule, cellules cliquables) -->
<style>
#viewDeclench .dcell{display:inline-block;min-width:50px;border:1px solid #23408F;border-radius:6px;padding:2px 6px;font-size:.72rem;font-weight:700;background:#fff;color:#23408F;cursor:pointer;line-height:1.1}
#viewDeclench .dcell.trig:hover{background:#23408F;color:#fff}
#viewDeclench .dcell.done{cursor:default}
#dmatrix{border-collapse:separate;border-spacing:0;font-size:.76rem}
#dmatrix th,#dmatrix td{border:1px solid #c7d2e6;padding:2px 4px;white-space:nowrap}
#dmatrix thead th{position:sticky;background:#23408F;color:#fff;text-align:center;font-weight:700;text-transform:uppercase;font-size:.72rem;z-index:5}
#dmatrix thead tr.r1 th{top:0}#dmatrix thead tr.r2 th{top:26px}#dmatrix thead tr.r1 th.mois{background:#1E9C4B}
#dmatrix .cfix{position:sticky;left:0;z-index:4;background:#f4f7fb;text-align:left}#dmatrix .cfix-rag{position:sticky;left:300px;z-index:4;background:#f4f7fb}
#dmatrix thead .cfix,#dmatrix thead .cfix-rag{background:#23408F;z-index:6}
#dmatrix .col-sd{min-width:300px;max-width:340px}#dmatrix .col-rag{min-width:110px}#dmatrix td.wk{min-width:64px}
.nat-tile{border:1px solid #dbe3f0;border-radius:12px;padding:14px 8px;text-align:center;cursor:pointer;transition:all .15s;background:#fff;height:100%}
.nat-tile:hover{border-color:#23408F;background:#eef3fb;transform:translateY(-2px)}
.nat-tile i{font-size:1.6rem;color:#23408F}.nat-tile .nt{font-size:.78rem;font-weight:600;margin-top:6px;color:#2C3E50}
.cadre-opt{display:block;border:1px solid #dbe3f0;border-radius:8px;padding:9px 12px;margin-bottom:7px;cursor:pointer;font-size:.9rem}
.cadre-opt:hover,.cadre-opt.sel{border-color:#23408F;background:#eef3fb}
.cadre-opt input{margin-right:8px}
</style>
<div id="viewDeclench" style="display:none">
  <div class="psc-toolbar">
    <button class="btn btn-sm btn-outline-secondary" id="btnBackListD"><i class="bi bi-arrow-left me-1"></i>Retour</button>
    <span class="badge-soft b-blue"><i class="bi bi-flag me-1"></i>Mode declenchement</span>
    <span class="ms-2 small text-muted">Cliquez sur une cellule (site/operateur) pour declencher un acte de supervision.</span>
  </div>
  <div class="psc-title-band"><i class="bi bi-flag"></i><span id="dTitre">-</span></div>
  <div class="psc-meta">
    <span><i class="bi bi-grid-3x3 me-1"></i>Semaines : <strong id="dNbSem">-</strong></span>
    <span class="ms-3"><span class="dcell" style="pointer-events:none">FOOL</span> = a declencher</span>
    <span class="ms-2"><span class="dcell done" style="pointer-events:none;border-color:#1E9C4B;color:#1E9C4B">FOOL<br><small>Effectue</small></span> = deja declenche</span>
  </div>
  <div class="matrix-wrap mt-2"><table class="psc-matrix" id="dmatrix"></table></div>
  <div id="dLegendes" class="mt-3"></div>
</div>

<!-- MODALE ACTE DE SUPERVISION (editeur du programme) -->
<div class="modal fade" id="modalActeCell" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
      <h5 class="modal-title text-white"><i class="bi bi-flag-fill me-2" style="color:#F3C300"></i>Acte de supervision</h5>
      <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="alert py-2 mb-3" style="background:#e8f0fe;border-left:4px solid #23408F;border-radius:8px;font-size:.83rem">
        <i class="bi bi-info-circle-fill me-1" style="color:#23408F"></i>
        Choisissez l'acte pour la cible <b id="acteCellInfo" style="color:#23408F"></b>.
        La cellule se colorera automatiquement : <span class="acte-jaune-cell" style="padding:1px 8px;border-radius:4px">jaune</span> pour une inspection non programmee, <span class="acte-bleu-cell" style="padding:1px 8px;border-radius:4px">bleu</span> pour les autres actes.
      </div>
      <div class="row g-2" id="acteTiles">
        <div class="col-4"><div class="acte-tile" data-acte="audit"><i class="bi bi-clipboard-check"></i><div class="at">Audit</div></div></div>
        <div class="col-4"><div class="acte-tile" data-acte="inspection_programmee"><i class="bi bi-calendar-check"></i><div class="at">Inspection programmee</div></div></div>
        <div class="col-4"><div class="acte-tile jaune" data-acte="inspection_non_programmee"><i class="bi bi-calendar-x"></i><div class="at">Inspection non programmee</div></div></div>
        <div class="col-4"><div class="acte-tile" data-acte="demonstration"><i class="bi bi-easel"></i><div class="at">Demonstration</div></div></div>
        <div class="col-4"><div class="acte-tile" data-acte="test"><i class="bi bi-bullseye"></i><div class="at">Test</div></div></div>
        <div class="col-4"><div class="acte-tile" data-acte="investigation"><i class="bi bi-search"></i><div class="at">Investigation</div></div></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" id="acteCellSkip" data-bs-dismiss="modal">Sans acte pour l'instant</button>
    </div>
  </div></div>
</div>

<!-- MODALE NATURE (6 actes) -->
<div class="modal fade" id="modalNature" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)"><h5 class="modal-title text-white"><i class="bi bi-flag me-2" style="color:#F3C300"></i>Programmer un acte de supervision</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="text-muted small mb-1">Choisissez la nature de la supervision pour demarrer la planification.</p>
      <div class="small mb-3" id="natCibleInfo"></div>
      <div class="row g-2">
        <div class="col-4"><div class="nat-tile" data-type="audit"><i class="bi bi-clipboard-check"></i><div class="nt">Audit</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="inspection_programmee"><i class="bi bi-calendar-check"></i><div class="nt">Inspection programmee</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="inspection_non_programmee"><i class="bi bi-calendar-x"></i><div class="nt">Inspection non programmee</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="demonstration"><i class="bi bi-easel"></i><div class="nt">Demonstration</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="test"><i class="bi bi-bullseye"></i><div class="nt">Test</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="investigation"><i class="bi bi-search"></i><div class="nt">Investigation</div></div></div>
      </div>
    </div>
  </div></div>
</div>

<!-- MODALE CADRE -->
<div class="modal fade" id="modalCadre" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)"><h5 class="modal-title text-white">Selectionnez le cadre</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="text-muted small mb-3">Nature : <b id="cadreNature" style="color:#23408F"></b>. Choisissez le cadre de supervision.</p>
      <div>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="certification">Certification</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="homologation">Homologation</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="reconnaissance">Reconnaissance</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="renouvellement">Renouvellement</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="surveillance_continue">Surveillance continue</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="traitement_evenement">Traitement d'un evenement</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="fermeture_provisoire">Fermeture provisoire</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="fermeture_definitive">Fermeture definitive</label>
        <label class="cadre-opt"><input type="radio" name="psc_cadre" value="delivrance_autorisation">Delivrance d'une autorisation</label>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="pscCadreContinue" disabled><i class="bi bi-arrow-right-circle me-1"></i>Continuer</button></div>
  </div></div>
</div>

<!-- VUE DECLENCHEMENT (depuis le programme) -->
<div id="viewTrigger" style="display:none">
  <div class="psc-toolbar">
    <button class="btn btn-sm btn-outline-secondary" id="btnBackList2"><i class="bi bi-arrow-left me-1"></i>Retour</button>
    <div class="ms-2 small text-muted"><i class="bi bi-info-circle me-1"></i>Cliquez sur une cellule renseignee (site / operateur) pour declencher un acte de supervision.</div>
  </div>
  <div class="psc-title-band"><i class="bi bi-rocket-takeoff"></i><span id="trgTitre">-</span></div>
  <div class="psc-meta">
    <span style="background:#23408F;color:#fff;padding:.3em .7em;border-radius:50px;font-size:.74rem;font-weight:600">Planifie</span>
    <span style="background:#E8890C;color:#fff;padding:.3em .7em;border-radius:50px;font-size:.74rem;font-weight:600">Reporte</span>
    <span style="background:#1E9C4B;color:#fff;padding:.3em .7em;border-radius:50px;font-size:.74rem;font-weight:600">Effectue</span>
    <span style="background:#D32F2F;color:#fff;padding:.3em .7em;border-radius:50px;font-size:.74rem;font-weight:600">Suspendu / A surveiller / Annule</span>
    <span style="background:#7A8798;color:#fff;padding:.3em .7em;border-radius:50px;font-size:.74rem;font-weight:600">Inopine</span>
    <span class="badge-soft b-grey">cellule cliquable = libre</span>
    <span id="trgDiag" class="badge-soft b-grey" style="margin-left:auto"></span>
    <button class="btn btn-sm btn-outline-primary" id="btnRefreshStatuts" style="padding:1px 8px;font-size:.74rem"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser les statuts</button>
  </div>
  <div class="matrix-wrap mt-2"><table class="psc-matrix" id="matrixT"><thead id="matrixHeadT"></thead></table></div>
  <div id="trgLegendes" class="mt-3"></div>
</div>

<!-- VUE ANALYSE PSC vs AUDITS -->
<div id="viewAnalyse" style="display:none">
  <div class="psc-toolbar">
    <button class="btn btn-sm btn-outline-secondary" id="btnBackListA"><i class="bi bi-arrow-left me-1"></i>Retour</button>
    <div class="ms-auto"><button class="btn btn-sm btn-outline-danger" id="btnAnaPdf"><i class="bi bi-printer me-1"></i>Imprimer</button></div>
  </div>
  <div class="psc-title-band"><i class="bi bi-graph-up-arrow"></i><span id="anaTitre">-</span></div>
  <div class="psc-meta"><span id="anaMeta"></span></div>
  <div id="anaBody" class="mt-3"></div>
</div>

<!-- MODALE ACTES -->
<div class="modal fade" id="actModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)"><h5 class="modal-title text-white"><i class="bi bi-plus-circle-fill me-2" style="color:#F3C300"></i>Programmer un acte de supervision</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="text-muted small mb-2">Choisissez la nature de la supervision pour demarrer la planification.</p>
      <div class="mb-3"><span class="badge-soft b-blue" style="font-size:.83rem;padding:.45em .8em"><i class="bi bi-geo-alt me-1"></i>Cible : <b id="actNature"></b></span></div>
      <div class="row g-2">
        <div class="col-4"><div class="nat-tile" data-type="audit"><i class="bi bi-clipboard-check"></i><div class="nt">Audit</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="inspection_programmee"><i class="bi bi-calendar-check"></i><div class="nt">Insp.<br>programmee</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="inspection_non_programmee"><i class="bi bi-calendar-x"></i><div class="nt">Insp. non<br>programmee</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="demonstration"><i class="bi bi-easel"></i><div class="nt">Demonstration</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="test"><i class="bi bi-bullseye"></i><div class="nt">Test</div></div></div>
        <div class="col-4"><div class="nat-tile" data-type="investigation"><i class="bi bi-search"></i><div class="nt">Investigation</div></div></div>
      </div>
    </div>
  </div></div>
</div>

<!-- MODALE CADRE -->
<div class="modal fade" id="cadreModalPsc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header" style="background:#23408F"><h5 class="modal-title text-white"><i class="bi bi-question-circle me-2" style="color:#F3C300"></i>Selectionnez le cadre</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="text-muted small mb-3">Nature : <b id="cadreNaturePsc" style="color:#23408F"></b>. Choisissez le cadre de supervision.</p>
      <?php foreach(['certification'=>'Certification','homologation'=>'Homologation','reconnaissance'=>'Reconnaissance','renouvellement'=>'Renouvellement','surveillance_continue'=>'Surveillance continue','traitement_evenement'=>"Traitement d'un evenement",'fermeture_provisoire'=>'Fermeture provisoire','fermeture_definitive'=>'Fermeture definitive','delivrance_autorisation'=>"Delivrance d'une autorisation"] as $val=>$lbl): ?>
      <label class="cadre-opt"><input type="radio" name="cadre_psc" value="<?php echo $val; ?>"><?php echo $lbl; ?></label>
      <?php endforeach; ?>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="cadreContinuePsc" disabled><i class="bi bi-arrow-right-circle me-1"></i>Continuer</button></div>
  </div></div>
</div>

<!-- MODALE CREATION -->
<div class="modal fade" id="modalNewProg" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)"><h5 class="modal-title text-white"><i class="bi bi-calendar-plus me-2" style="color:#F3C300"></i>Nouveau programme PSC</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label fw-bold">Annee <span class="text-danger">*</span></label><input type="number" class="form-control" id="np_annee" min="2000" max="2100" placeholder="Ex: 2026"></div>
      <div class="mb-3"><label class="form-label fw-bold">Type d'activite <span class="text-danger">*</span></label>
        <div class="input-group"><select class="form-select" id="np_type"></select><button class="btn btn-outline-success" type="button" id="btnAddType" title="Ajouter un type d'activite"><i class="bi bi-plus-lg"></i></button></div>
      </div>
      <div class="mb-3"><label class="form-label fw-bold">Domaine <span class="text-danger">*</span></label>
        <div class="input-group"><select class="form-select" id="np_domaine"></select><button class="btn btn-outline-success" type="button" id="btnAddDomaine" title="Ajouter un domaine"><i class="bi bi-plus-lg"></i></button></div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold d-block">Cible du programme</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="np_mode" id="np_mode_site" value="site" checked>
          <label class="btn btn-outline-primary" for="np_mode_site"><i class="bi bi-geo-alt me-1"></i>PSC Site</label>
          <input type="radio" class="btn-check" name="np_mode" id="np_mode_op" value="operateur">
          <label class="btn btn-outline-primary" for="np_mode_op"><i class="bi bi-buildings me-1"></i>PSC Operateur</label>
        </div>
      </div>
      <div class="alert" style="background:#e8f0fe;border-left:4px solid #23408F;border-radius:8px;font-size:.83rem"><i class="bi bi-info-circle-fill me-1" style="color:#23408F"></i>52 ou 53 semaines selon l'annee. En mode Operateur, chaque cellule prend un operateur (trigramme) au lieu d'un site.</div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="btnCreateProg"><i class="bi bi-arrow-right-circle me-1"></i>Generer la matrice</button></div>
  </div></div>
</div>

<!-- MODALE SITE (avec pays) -->
<div class="modal fade" id="modalSite" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:#1E9C4B"><h5 class="modal-title text-white">Nouveau site</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label fw-bold">Indicateur OACI <span class="text-danger">*</span></label><input type="text" class="form-control" id="s_oaci" maxlength="10" placeholder="Ex: FOOL"></div>
      <div class="mb-2"><label class="form-label fw-bold">Nom du site</label><input type="text" class="form-control" id="s_nom" maxlength="150"></div>
      <div class="mb-2"><label class="form-label fw-bold">Ville</label><input type="text" class="form-control" id="s_ville" maxlength="150"></div>
      <div class="mb-2"><label class="form-label fw-bold">Pays</label>
        <div class="input-group">
          <select class="form-select" id="s_pays" style="width:100%"></select>
          <button class="btn btn-outline-success" type="button" id="btnAddPays" title="Ajouter un pays"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="btnSaveSite">Ajouter</button></div>
  </div></div>
</div>

<!-- MODALE RAG -->
<div class="modal fade" id="modalRag" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:#1E9C4B"><h5 class="modal-title text-white">Nouveau reglement (RAG)</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label fw-bold">Code RAG <span class="text-danger">*</span></label><input type="text" class="form-control" id="r_code" maxlength="50" placeholder="Ex: 8.1.2.1"></div>
      <div class="mb-2"><label class="form-label fw-bold">Libelle</label><input type="text" class="form-control" id="r_lib" maxlength="255"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="btnSaveRag">Ajouter</button></div>
  </div></div>
</div>

<!-- MODALE TYPE D'ACTIVITE -->
<div class="modal fade" id="modalType" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:#1E9C4B"><h5 class="modal-title text-white">Nouveau type d'activite</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="mb-2"><label class="form-label fw-bold">Nom du type <span class="text-danger">*</span></label><input type="text" class="form-control" id="t_nom" maxlength="255" placeholder="Ex: Exploitants d'aerodromes"></div></div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="btnSaveType">Ajouter</button></div>
  </div></div>
</div>
<!-- MODALE DOMAINE -->
<div class="modal fade" id="modalDomaine" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:#1E9C4B"><h5 class="modal-title text-white">Nouveau domaine</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label fw-bold">Code <span class="text-danger">*</span></label><input type="text" class="form-control" id="d_code" maxlength="10" placeholder="Ex: AGA"></div>
      <div class="mb-2"><label class="form-label fw-bold">Libelle</label><input type="text" class="form-control" id="d_lib" maxlength="100" placeholder="Ex: Aerodromes"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="btnSaveDomaine">Ajouter</button></div>
  </div></div>
</div>
<!-- MODALE OPERATEUR -->
<div class="modal fade" id="modalOperateur" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:#1E9C4B"><h5 class="modal-title text-white">Nouvel operateur</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label fw-bold">Nom de l'operateur <span class="text-danger">*</span></label><input type="text" class="form-control" id="o_nom" maxlength="255" placeholder="Ex: AAMAC RPAS"></div>
      <div class="mb-2"><label class="form-label fw-bold">Trigramme</label><input type="text" class="form-control" id="o_trig" maxlength="70" placeholder="Ex: AAMAC"></div>
      <div class="mb-2"><label class="form-label fw-bold">Ville</label><input type="text" class="form-control" id="o_ville" maxlength="150"></div>
      <div class="mb-2"><label class="form-label fw-bold">Pays</label><select class="form-select" id="o_pays" style="width:100%"></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-anac" id="btnSaveOperateur">Ajouter</button></div>
  </div></div>
</div>

<datalist id="dlRub"></datalist>
<datalist id="dlItem"></datalist>

<!-- MODALE : programme signe par le Directeur General -->
<div class="modal fade" id="modalSigne" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
      <h5 class="modal-title text-white"><i class="bi bi-file-earmark-check me-2" style="color:#F3C300"></i>Programme signe par le Directeur General</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="alert mb-3" style="background:#eef3fb;border-left:4px solid #23408F;border-radius:8px;font-size:.83rem">
        <i class="bi bi-info-circle-fill me-1" style="color:#23408F"></i>
        <span id="sgTitre" style="font-weight:600"></span>
      </div>

      <div id="sgActuel" class="mb-3" style="display:none">
        <div class="d-flex align-items-center gap-2 flex-wrap"
             style="background:#e8f5ec;border-left:4px solid #1E9C4B;border-radius:8px;padding:10px 14px">
          <i class="bi bi-check-circle-fill" style="color:#1E9C4B;font-size:1.1rem"></i>
          <div style="flex:1">
            <div style="font-weight:700;font-size:.85rem;color:#157a3a">Programme signe deja joint</div>
            <div style="font-size:.76rem;color:#5b6b85" id="sgDateTxt"></div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnVoirSigne"><i class="bi bi-eye me-1"></i>Consulter</button>
          <button type="button" class="btn btn-sm btn-outline-danger" id="btnRetirerSigne"><i class="bi bi-trash me-1"></i>Retirer</button>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">Document signe (PDF) <span class="text-danger">*</span></label>
        <input type="file" class="form-control" id="sg_fichier" accept="application/pdf">
        <div class="form-text">Format PDF uniquement. Le depot d'un nouveau document remplace le precedent, qui est supprime du serveur.</div>
      </div>
      <div class="mb-2">
        <label class="form-label fw-bold">Date de signature</label>
        <input type="date" class="form-control" id="sg_date">
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
      <button type="button" class="btn btn-anac" id="btnSaveSigne"><i class="bi bi-upload me-1"></i>Enregistrer</button>
    </div>
  </div></div>
</div>

<!-- MODALE : lecture du programme signe -->
<div class="modal fade" id="modalPdfPsc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="height:90vh">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i>Programme signe</h5>
        <div class="ms-auto d-flex gap-2 me-3">
          <a id="pscPdfDl" class="btn btn-sm btn-light" download><i class="bi bi-download me-1"></i>Telecharger</a>
          <button id="pscPdfPrint" type="button" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="background:#525659">
        <iframe id="pscPdfFrame" src="" style="width:100%;height:100%;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/psc';
const IS_CI = <?php echo $isCI ? 'true':'false'; ?>;
const BANER = '<?php echo ASSETS_URL; ?>/images/banierenteanac.png';
/* Signataires du programme (modifiables ici) */
const SIGNATAIRES = [
  {nom:'Pascal TRUFFAULT IGOUWE', titre:'Chef Inspecteur'},
  {nom:'Eric Tristan Franck MOUSSAVOU', titre:'Directeur General'}
];
const NB_TEXT = '<div style="margin-top:12px;color:#D32F2F;font-weight:700;font-size:11px">N.B: Des inspections inopinées seront réalisées par les inspecteurs de l\'ANAC si nécessaire</div>';
function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF}, d), null, 'json'); }
function esc(s){ const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }

/* Statuts calcules cote serveur (aucun AJAX requis) */
const PSC_STATUS = <?php echo json_encode($PSC_STATUS, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const PSC_DIAG   = <?php echo json_encode($PSC_DIAG,   JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
let CURRENT=null, WEEKS=[], NBSEM=52, OPTS={rags:[],sites:[],sousdomaines:[],pays:[],operateurs:[]};

/* Actes de supervision (mise en forme conditionnelle des cellules du programme).
   "Inspection non programmee" -> fond jaune, texte gras noir. Les autres -> fond bleu. */
const ACTES = [
  {v:'audit',                     t:'Audit',                     icon:'bi-clipboard-check'},
  {v:'inspection_programmee',     t:'Inspection programmee',     icon:'bi-calendar-check'},
  {v:'inspection_non_programmee', t:'Inspection non programmee', icon:'bi-calendar-x'},
  {v:'demonstration',             t:'Demonstration',             icon:'bi-easel'},
  {v:'test',                      t:'Test',                      icon:'bi-bullseye'},
  {v:'investigation',             t:'Investigation',             icon:'bi-search'}
];
const ACTE_TXT = {}; ACTES.forEach(function(a){ ACTE_TXT[a.v]=a.t; });
// Classe CSS de couleur de fond selon l'acte
function acteCellClass(acte){
  if(!acte) return '';
  return (acte==='inspection_non_programmee') ? 'acte-jaune' : 'acte-bleu';
}
// Extraction robuste code / acte quel que soit le format (string ancien, objet nouveau)
function cellCode(v){ if(v&&typeof v==='object') return v.code||''; return v||''; }
function cellActe(v){ if(v&&typeof v==='object') return v.acte||''; return ''; }
function cellCouleur(v){ if(v&&typeof v==='object') return v.couleur||''; return ''; }
let progTable=null, ragTargetSel=null, RUBS=[], ITEMS=[], MODE='site', OPERATEURS=[];

/* ---------- LISTE ---------- */
function fillSel(id, arr, ph){ const cur=$(id).val(); $(id).html('<option value="">'+ph+'</option>'+arr.map(function(v){return '<option value="'+esc(v)+'">'+esc(v)+'</option>';}).join('')); if(cur) $(id).val(cur); }
function loadList(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success) return;
    const data=res.data||[];
    // ----- Statistiques -----
    const total=data.length;
    const valides=data.filter(function(p){return p.statut==='valide';}).length;
    const annees=new Set(), domaines=new Set(), sites=new Set();
    data.forEach(function(p){
      annees.add(p.annee);
      const dom=p.nomdomaine||(p.libel_domaine||'').trim(); if(dom) domaines.add(dom);
      (p.sites_used||'').split(',').forEach(function(sx){ if(sx) sites.add(sx); });
    });
    $('#st_total').text(total); $('#st_valides').text(valides); $('#st_brouillons').text(total-valides);
    $('#st_annees').text(annees.size); $('#st_domaines').text(domaines.size); $('#st_sites').text(sites.size);
    // ----- Options de filtres -----
    fillSel('#f_annee',[...annees].sort(function(a,b){return b-a;}),'Toutes');
    fillSel('#f_type',[...new Set(data.map(function(p){return p.nomtypeorg;}).filter(Boolean))].sort(),'Tous');
    fillSel('#f_dom',[...domaines].sort(),'Tous');
    fillSel('#f_site',[...sites].sort(),'Tous');
    // ----- Lignes (avec colonnes cachees pour filtres/tri) -----
    const rows=data.map(function(p){
      const dom=p.nomdomaine||(p.libel_domaine||'').trim()||'-';
      const st=p.statut==='valide'?'<span class="badge-soft b-green">Valide</span>':'<span class="badge-soft b-gold">Brouillon</span>';
      const estValide = (p.statut === 'valide');
      const act='<div class="btn-group btn-group-sm">'
        +(estValide?'':'<button class="btn btn-outline-primary act-open" data-id="'+p.idprogramme+'" title="Ouvrir et modifier"><i class="bi bi-pencil-square"></i></button>')
        +(estValide?'<button class="btn btn-outline-success act-trig" data-id="'+p.idprogramme+'" title="Declencher un acte de supervision"><i class="bi bi-rocket-takeoff"></i></button>':'')
        +'<button class="btn btn-outline-info act-ana" data-id="'+p.idprogramme+'" title="Analyse PSC vs Audits"><i class="bi bi-graph-up-arrow"></i></button>'
        +'<button class="btn btn-outline-danger act-pdf" data-id="'+p.idprogramme+'" title="PDF"><i class="bi bi-printer"></i></button>'
        +'<button class="btn btn-outline-success act-xls" data-id="'+p.idprogramme+'" title="Excel"><i class="bi bi-file-earmark-excel"></i></button>'
        +(p.fichier_signe?'<button class="btn btn-success act-voir-signe" data-id="'+p.idprogramme+'" title="Consulter le programme signe par le DG"><i class="bi bi-file-earmark-check"></i></button>':'')
        +((IS_CI && !estValide)?'<button class="btn btn-outline-dark act-del" data-id="'+p.idprogramme+'" data-t="'+esc(p.titre)+'" title="Supprimer"><i class="bi bi-trash"></i></button>':'')+'</div>';
      return [p.idprogramme, p.annee, esc(p.nomtypeorg||'-'), esc(dom), esc(p.titre), p.nb_semaines, (p.nb_items||0), st, (p.updated_at||'').substring(0,16), act,
        (p.nomtypeorg||''), dom, p.statut, String(p.annee), (p.sites_used||''), (p.updated_at||'')];
    });
    if(progTable){ progTable.clear(); progTable.rows.add(rows); progTable.draw(false); }
    else progTable=$('#progTable').DataTable({data:rows, order:[[15,'desc']], pageLength:10,
      columnDefs:[{targets:[0,10,11,12,13,14,15],visible:false},{targets:9,orderable:false,className:'text-end'}],
      language:{search:'Rechercher :',lengthMenu:'Afficher _MENU_ lignes',info:'_START_ a _END_ sur _TOTAL_',infoEmpty:'0 programme',zeroRecords:'Aucun programme',emptyTable:'Aucun programme',paginate:{first:'Premier',previous:'Precedent',next:'Suivant',last:'Dernier'}}});
  });
}

/* ---------- CREATION ---------- */
$('#btnNewProg').on('click',function(){
  $('#np_annee').val(new Date().getFullYear());
  $('#np_mode_site').prop('checked',true);
  apiPost({action:'refs'}).done(function(res){
    if(!res.success) return;
    $('#np_type').html('<option value="">-- Choisir --</option>'+(res.types||[]).map(function(t){return '<option value="'+t.idtypeorga+'">'+esc(t.nomtypeorg)+'</option>';}).join(''));
    $('#np_domaine').html('<option value="">-- Choisir --</option>'+(res.domaines||[]).map(function(d){const lib=(d.libel_domaine||'').trim();return '<option value="'+d.iddomaine+'">'+esc((d.nomdomaine||'').trim()||lib)+(lib?(' - '+esc(lib)):'')+'</option>';}).join(''));
    if($.fn.select2){
      ['#np_type','#np_domaine'].forEach(function(sel){
        if($(sel).hasClass('select2-hidden-accessible')) $(sel).select2('destroy');
        $(sel).select2({theme:'bootstrap-5', width:'100%', dropdownParent:$('#modalNewProg')});
      });
    }
    new bootstrap.Modal('#modalNewProg').show();
  });
});
$('#btnCreateProg').on('click',function(){
  const annee=parseInt($('#np_annee').val(),10),idt=$('#np_type').val(),idd=$('#np_domaine').val();
  const mode=$('input[name="np_mode"]:checked').val()||'site';
  if(!annee||annee<2000||annee>2100){ Swal.fire({icon:'warning',text:'Annee invalide (ex: 2026).',confirmButtonColor:'#23408F'}); return; }
  if(!idt||!idd){ Swal.fire({icon:'warning',text:'Type et domaine obligatoires.',confirmButtonColor:'#23408F'}); return; }
  const btn=$(this); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost({action:'create',annee:annee,idtypeorga:idt,iddomaine:idd,mode:mode}).done(function(res){
    btn.prop('disabled',false).html('<i class="bi bi-arrow-right-circle me-1"></i>Generer la matrice');
    if(!res.success){ Swal.fire({icon:'error',text:res.message||'Echec',confirmButtonColor:'#23408F'}); return; }
    bootstrap.Modal.getInstance('#modalNewProg').hide();
    openProgramme(res.idprogramme, !res.existing);
  });
});

/* ---------- OUVRIR ---------- */
/* Charge les actes deja declenches (couleurs + blocage). Partage editeur / declenchement / exports. */
function loadTriggered(idprog, cb){
  apiPost({action:'triggered',idprogramme:idprog}).done(function(tr){
    TRIG = (tr && tr.success && tr.map) ? tr.map : {};
    const n = Object.keys(TRIG).length;
    console.log('[PSC] statuts charges :', n, TRIG, tr);
    const nPhp = Object.keys(PSC_STATUS||{}).length;
    const noDate = (PSC_DIAG && PSC_DIAG.nodate) ? PSC_DIAG.nodate : 0;
    let txt = nPhp+' acte(s) rattachable(s)';
    if(noDate > 0){ txt += ' - '+noDate+' sans date prevue'; }
    $('#trgDiag').text(txt).css({background:(nPhp>0?'#e8f5ec':'#fdf4d0'), color:(nPhp>0?'#157a3a':'#9a7d00')});
    // Diagnostic detaille : cles calculees et cellules non rattachees
    const cellules = [];
    $('#matrixT .trig-cell').each(function(){
      const v=String($(this).data('val')||'').toUpperCase(), sm=$(this).data('sem');
      const k=(CURRENT?parseInt(CURRENT.annee,10):0)+'|'+v+'|'+sm;
      cellules.push({cle:k, rattache: !!(PSC_STATUS && PSC_STATUS[k])});
    });
    console.log('[PSC] mode:', MODE, '| cles connues:', PSC_STATUS);
    console.log('[PSC] audits pris en compte:', PSC_DIAG);
    console.log('[PSC] cellules du programme:', cellules);
    if(typeof cb==='function') cb();
  }).fail(function(xhr){
    TRIG={};
    console.error('[PSC] appel triggered en echec', xhr && xhr.status, xhr && xhr.responseText);
    $('#trgDiag').text('Appel des statuts en echec (voir console)').css({background:'#fde2e2',color:'#b02525'});
    if(typeof cb==='function') cb();
  });
}
function trigOf(val, sem){
  const code=String(val||'').trim().toUpperCase();
  if(!code) return null;
  const an=CURRENT? parseInt(CURRENT.annee,10) : 0;
  // 1) Carte rechargee par AJAX (source la plus a jour : reflete les declenchements recents)
  const t = TRIG[code+'|'+sem];
  if(t) return t;
  // 2) Repli : carte calculee cote serveur au chargement initial de la page
  const k = an+'|'+code+'|'+sem;
  if(PSC_STATUS && PSC_STATUS[k]) return PSC_STATUS[k];
  return null;
}
/* Applique les couleurs de statut sur les cellules de l'editeur */
function paintEditorCells(){
  $('#matrixBody, #matrix').find('.it-wk').each(function(){
    const $c=$(this); $c.removeClass('st-1 st-2 st-3 st-4 st-5 st-6 st-7');
    const t=trigOf($c.val(), $c.data('sem'));
    if(t){ $c.addClass('st-'+t.statut).attr('title','Acte '+(STAT_LBL[t.statut]||'')+(t.num?(' - '+t.num):'')); }
  });
}
function openProgramme(idprog, estNouveau){
  apiPost({action:'get',idprogramme:idprog}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',text:res.message||'Introuvable',confirmButtonColor:'#23408F'}); return; }
    CURRENT=res.programme; WEEKS=res.weeks||[]; NBSEM=res.nb_semaines||52; OPTS=res.options||{rags:[],sites:[],sousdomaines:[],pays:[],operateurs:[]};
    MODE=(CURRENT.mode_cible==='operateur')?'operateur':'site'; OPERATEURS=OPTS.operateurs||[];
    $('#progTitre').text(CURRENT.titre);
    $('#progRev').text(CURRENT.revision||'00');
    $('#progDate').text((CURRENT.date_etablissement||'').substring(0,10));
    $('#progNbSem').text(NBSEM);
    buildRefLists(); buildHead();
    buildBody((res.matrice&&res.matrice.groupes)?res.matrice.groupes:[]);
    // A la creation, le programme doit d'abord etre saisi puis enregistre :
    // le bouton de validation n'apparait qu'a la reouverture pour modification.
    $('#btnToggleStatut').toggle(!estNouveau);
    refreshStatutUI();
    paintEditorCells();                    // couleurs immediates (carte PHP)
    loadTriggered(idprog, paintEditorCells); // complement eventuel
    $('#viewList').hide(); $('#viewEditor').show(); window.scrollTo(0,0);
  });
}

/* ---------- Options helpers ---------- */
function buildRefLists(){
  RUBS = (OPTS.rubriques && OPTS.rubriques.length) ? OPTS.rubriques.filter(Boolean)
        : [...new Set((OPTS.sousdomaines||[]).map(function(s){return s.nom_sousdomaine;}).filter(Boolean))];
  ITEMS = (OPTS.items && OPTS.items.length) ? OPTS.items.filter(Boolean)
        : [...new Set((OPTS.sousdomaines||[]).map(function(s){return s.item_sous_domaine;}).filter(Boolean))];
}
function ragOptions(sel){
  let o='<option value="">--</option>';
  (OPTS.rags||[]).forEach(function(r){ o+='<option value="'+esc(r.code_reglement)+'" '+(String(sel)===String(r.code_reglement)?'selected':'')+'>'+esc(r.code_reglement)+'</option>'; });
  if(sel&&!(OPTS.rags||[]).some(function(r){return String(r.code_reglement)===String(sel);})) o+='<option value="'+esc(sel)+'" selected>'+esc(sel)+'</option>';
  return o;
}
function siteOptions(sel){
  let o='<option value="">--</option>';
  (OPTS.sites||[]).forEach(function(s){ o+='<option value="'+esc(s.indicateur_oaci)+'" '+(String(sel)===String(s.indicateur_oaci)?'selected':'')+'>'+esc(s.indicateur_oaci)+'</option>'; });
  if(sel&&!(OPTS.sites||[]).some(function(s){return String(s.indicateur_oaci)===String(sel);})) o+='<option value="'+esc(sel)+'" selected>'+esc(sel)+'</option>';
  return o;
}
function operateurOptions(sel){
  let o='<option value="">--</option>';
  (OPERATEURS||[]).forEach(function(op){
    const trig=(op.trigrorganisme||'').trim();
    if(trig){ o+='<option value="'+esc(trig)+'" data-id="'+op.idorga+'" data-nom="'+esc(op.nomorga)+'" '+(String(sel)===String(trig)?'selected':'')+'>'+esc(trig+' · '+op.nomorga)+'</option>'; }
    else { o+='<option value="__NT__'+op.idorga+'" data-id="'+op.idorga+'" data-nom="'+esc(op.nomorga)+'" data-notrig="1">'+esc(op.nomorga+' (sans trigramme)')+'</option>'; }
  });
  if(sel&&!(OPERATEURS||[]).some(function(op){return String((op.trigrorganisme||'').trim())===String(sel);})) o+='<option value="'+esc(sel)+'" selected>'+esc(sel)+'</option>';
  return o;
}
function cellOptions(sel){ return (MODE==='operateur') ? operateurOptions(sel) : siteOptions(sel); }
function rubOptions(sel){
  let o='<option value="">-- Grand titre --</option>';
  RUBS.forEach(function(r){ o+='<option value="'+esc(r)+'" '+(String(sel)===String(r)?'selected':'')+'>'+esc(r)+'</option>'; });
  if(sel&&!RUBS.some(function(r){return String(r)===String(sel);})) o+='<option value="'+esc(sel)+'" selected>'+esc(sel)+'</option>';
  return o;
}
function itemOptions(sel){
  let o='<option value="">-- Item --</option>';
  ITEMS.forEach(function(r){ o+='<option value="'+esc(r)+'" '+(String(sel)===String(r)?'selected':'')+'>'+esc(r)+'</option>'; });
  if(sel&&!ITEMS.some(function(r){return String(r)===String(sel);})) o+='<option value="'+esc(sel)+'" selected>'+esc(sel)+'</option>';
  return o;
}
function addOptionToAll(selector, val){
  $(selector).each(function(){
    let found=false; $(this).find('option').each(function(){ if(this.value===val) found=true; });
    if(!found) $(this).append(new Option(val,val));
  });
}

/* ---------- Entete (REFERENTIEL / SOUS-DOMAINES / RAG + mois/semaines) ---------- */
function buildHead(){
  const groups=[]; let cur=null;
  WEEKS.forEach(function(w){ if(!cur||cur.mi!==w.mois_index){ cur={mi:w.mois_index,mois:w.mois,count:0}; groups.push(cur);} cur.count++; });
  let r1='<tr class="r1">'
    +'<th class="cfix col-sd" rowspan="1">REFERENTIEL</th>'
    +'<th class="cfix-rag col-rag" rowspan="2">RAG</th>';
  groups.forEach(function(g){ r1+='<th class="mois" colspan="'+g.count+'">'+esc(g.mois)+'</th>'; });
  r1+='</tr>';
  let r2='<tr class="r2"><th class="cfix col-sd">SOUS-DOMAINES</th>';
  WEEKS.forEach(function(w){ r2+='<th>S'+w.sem+'</th>'; });
  r2+='</tr>';
  $('#matrixHead').html(r1+r2);
}

/* ---------- Corps : un <tbody> par grand titre ---------- */
function groupTbody(g){
  g=g||{rubrique:'',items:[]};
  let head='<tr class="grp-row">'
    +'<td class="cfix col-sd"><div class="cell-actions">'
      +'<select class="grp-rub" title="Grand titre (REFERENTIEL)">'+rubOptions(g.rubrique||'')+'</select>'
      +'<button type="button" class="mini mini-add add-rub" title="Enregistrer la rubrique">+</button>'
      +'<button type="button" class="mini mini-item add-item" title="Ajouter un item"><i class="bi bi-plus-lg"></i> item</button>'
      +'<button type="button" class="mini mini-del del-grp" title="Supprimer le grand titre"><i class="bi bi-x"></i></button>'
    +'</div></td>'
    +'<td class="cfix-rag col-rag"></td>';
  WEEKS.forEach(function(){ head+='<td class="wk grpcell"></td>'; });
  head+='</tr>';
  const items=(g.items&&g.items.length)?g.items:[{sous_domaine:'',rag:'',cellules:{}}];
  const body=items.map(itemRow).join('');
  return '<tbody class="grp-block">'+head+body+'</tbody>';
}
function itemRow(it){
  it=it||{}; const cells=it.cellules||{};
  let h='<tr class="item-row">'
    +'<td class="cfix col-sd"><div class="cell-actions">'
      +'<select class="it-sd" title="Item (SOUS-DOMAINES)">'+itemOptions(it.sous_domaine||'')+'</select>'
      +'<button type="button" class="mini mini-add add-itemref" title="Enregistrer cet item">+</button>'
      +'<button type="button" class="mini mini-add add-site" title="Ajouter un site"><i class="bi bi-geo-alt"></i></button>'
      +'<button type="button" class="mini mini-del del-item" title="Supprimer la ligne"><i class="bi bi-x"></i></button>'
    +'</div></td>'
    +'<td class="cfix-rag col-rag"><div class="cell-actions">'
      +'<select class="it-rag">'+ragOptions(it.rag||'')+'</select>'
      +'<button type="button" class="mini mini-add add-rag" title="Ajouter un RAG">+</button>'
    +'</div></td>';
  WEEKS.forEach(function(w){
    const raw=cells[String(w.sem)]||cells[w.sem]||'';
    // Retrocompat : ancien format string, nouveau format {code,acte,couleur}
    let code='', acte='', couleur='';
    if(raw && typeof raw==='object'){ code=raw.code||''; acte=raw.acte||''; couleur=raw.couleur||''; }
    else { code=raw||''; }
    // Couleur : celle stockee si presente, sinon deduite de l'acte
    let acteClass='';
    if(couleur==='jaune') acteClass='acte-jaune';
    else if(couleur==='bleu') acteClass='acte-bleu';
    else acteClass=acteCellClass(acte);
    const couStored = acteClass==='acte-jaune'?'jaune':(acteClass==='acte-bleu'?'bleu':'');
    let attrs='';
    if(acte) attrs+=' data-acte="'+esc(acte)+'"';
    if(couStored) attrs+=' data-couleur="'+couStored+'"';
    h+='<td class="wk"><select class="it-wk'+(code?' filled':'')+(acteClass?' '+acteClass:'')+'" data-sem="'+w.sem+'" data-loaded="0"'+attrs+'>'
      +'<option value="'+esc(code)+'"'+(code?' selected':'')+'>'+esc(code||'--')+'</option></select></td>';
  });
  h+='</tr>';
  return h;
}
function buildBody(groupes){
  $('#matrix tbody').remove();
  if(!groupes||!groupes.length){ groupes=[{rubrique:'',items:[{sous_domaine:'',rag:'',cellules:{}}]}]; }
  $('#matrix').append(groupes.map(groupTbody).join(''));
  initItemSelect2();
}
/* Select2 avec recherche sur les listes Item et RAG (plus pratique quand il y a
   beaucoup de lignes). Les cellules semaine gardent leur chargement paresseux. */
function initItemSelect2(){
  if(!$.fn.select2) return;
  $('#matrix .grp-rub, #matrix .it-sd, #matrix .it-rag').each(function(){
    if($(this).hasClass('select2-hidden-accessible')) return;
    let ph='Item...';
    if($(this).hasClass('it-rag')) ph='RAG...';
    else if($(this).hasClass('grp-rub')) ph='Grand titre...';
    $(this).select2({theme:'bootstrap-5', width:'100%', dropdownParent:$('#viewEditor'), placeholder:ph, allowClear:true});
  });
}

/* ---------- Actions structure ---------- */
$('#btnAddGroup').on('click',function(){ $('#matrix').append(groupTbody({rubrique:'',items:[{}]})); initItemSelect2(); });
$(document).on('click','.add-item',function(){ $(this).closest('tbody').append(itemRow({})); initItemSelect2(); });
$(document).on('click','.del-item',function(){
  const tb=$(this).closest('tbody'); if(tb.find('.item-row').length>1){ $(this).closest('tr').remove(); }
  else Swal.fire({icon:'info',text:'Au moins un item par grand titre.',confirmButtonColor:'#23408F'});
});
$(document).on('click','.del-grp',function(){
  if($('#matrix tbody.grp-block').length>1){ $(this).closest('tbody').remove(); }
  else Swal.fire({icon:'info',text:'Au moins un grand titre est requis.',confirmButtonColor:'#23408F'});
});
/* Perf : la liste complete (sites/operateurs) et Select2 ne sont construits
   qu'au 1er clic sur la cellule, pour ne pas alourdir la grille. */
$(document).on('mousedown','.it-wk[data-loaded="0"]',function(e){
  const $s=$(this); const cur=$s.val();
  $s.attr('data-loaded','1').html(cellOptions(cur));
  if($.fn.select2){
    $s.select2({
      theme:'bootstrap-5', width:'100%', dropdownParent:$('#viewEditor'),
      placeholder: (MODE==='operateur'?'Operateur...':'Site...'), allowClear:true,
      // Dans la cellule : afficher uniquement le code court (trigramme / OACI),
      // pas le nom complet (souvent long). La liste garde le libelle complet.
      templateSelection: function(opt){
        if(!opt.id) return opt.text;
        // Le code court est la valeur de l'option (trigramme operateur ou OACI site)
        return $('<span title="'+ (opt.text||'').replace(/"/g,'&quot;') +'">'+ (opt.id||'') +'</span>');
      }
    });
    // Reporter la couleur d'acte deja posee sur le nouveau container Select2
    const coul=$s.attr('data-couleur')||'';
    if(coul){ $s.next('.select2-container').addClass(coul==='jaune'?'acte-jaune-s2':'acte-bleu-s2'); }
    // Ouvre directement la liste puisque l'utilisateur venait de cliquer
    e.preventDefault();
    setTimeout(function(){ $s.select2('open'); }, 0);
  }
});
$(document).on('change','.it-wk',function(){
  const opt=this.options[this.selectedIndex]; const $sel=$(this);
  if(MODE==='operateur' && opt && opt.getAttribute('data-notrig')==='1'){
    const idorga=opt.getAttribute('data-id'), nom=opt.getAttribute('data-nom');
    Swal.fire({title:'Trigramme requis',html:'L\'operateur <strong>'+esc(nom)+'</strong> n\'a pas de trigramme.<br>Saisissez-le :',input:'text',inputPlaceholder:'Ex: AAMAC',showCancelButton:true,confirmButtonColor:'#1E9C4B',cancelButtonText:'Annuler'}).then(function(r){
      if(!r.isConfirmed || !(r.value||'').trim()){ $sel.val('').toggleClass('filled',false); return; }
      const trig=r.value.trim().toUpperCase();
      apiPost({action:'set_trigram',idorga:idorga,trigrorganisme:trig}).done(function(res){
        if(!res.success){ Swal.fire({icon:'error',text:res.message||'Echec'}); $sel.val('').toggleClass('filled',false); return; }
        (OPERATEURS||[]).forEach(function(op){ if(String(op.idorga)===String(idorga)) op.trigrorganisme=trig; });
        $('.it-wk option[data-id="'+idorga+'"]').each(function(){ this.value=trig; this.removeAttribute('data-notrig'); this.text=trig+' · '+nom; });
        $sel.val(trig).toggleClass('filled',true);
        if($sel.hasClass('select2-hidden-accessible')){ $sel.trigger('change.select2'); }
        Swal.fire({icon:'success',title:'Trigramme enregistre',text:trig,timer:900,showConfirmButton:false});
        // Chainage : la cible est maintenant valide -> demander l'acte de supervision
        setTimeout(function(){ openActeModal($sel); }, 950);
      });
    });
    return;
  }
  $sel.toggleClass('filled', !!this.value);
  $sel.removeClass('st-1 st-2 st-3 st-4 st-5 st-6 st-7');
  const tt=trigOf($sel.val(), $sel.data('sem'));
  if(tt) $sel.addClass('st-'+tt.statut);
  // Une cible vient d'etre choisie -> demander l'acte de supervision (couleur de fond)
  if(this.value){
    openActeModal($sel);
  } else {
    // Cellule videe : on retire l'acte et sa couleur
    $sel.removeAttr('data-acte').removeClass('acte-bleu acte-jaune');
  }
});

/* ---------- Choix de l'acte de supervision (mise en forme conditionnelle) ---------- */
let ACTE_TARGET=null; // cellule <select.it-wk> en cours
function openActeModal($cell){
  ACTE_TARGET=$cell;
  const code=$cell.val()||'';
  let libelle=code;
  if(MODE==='operateur'){ const op=(OPERATEURS||[]).find(function(x){return String(x.trigrorganisme||'')===String(code);}); if(op) libelle=code+' - '+(op.nomorga||''); }
  else { const s=(OPTS.sites||[]).find(function(x){return String(x.indicateur_oaci)===String(code);}); if(s) libelle=code+' - '+(s.nomsite||''); }
  $('#acteCellInfo').text(libelle);
  // Pre-selection visuelle de l'acte deja pose
  const cur=$cell.attr('data-acte')||'';
  $('#acteTiles .acte-tile').removeClass('sel').css('border-color','');
  if(cur){ $('#acteTiles .acte-tile[data-acte="'+cur+'"]').addClass('sel').css('border-color','#23408F'); }
  new bootstrap.Modal('#modalActeCell').show();
}
function applyActeToCell($cell, acte, couleurForcee){
  $cell.removeClass('acte-bleu acte-jaune');
  // Container Select2 associe (si la cellule est deja passee en Select2)
  const $cont=$cell.next('.select2-container');
  $cont.removeClass('acte-bleu-s2 acte-jaune-s2');
  if(acte){
    $cell.attr('data-acte', acte);
    // Couleur : soit forcee par le CI, soit deduite de l'acte
    let cls;
    if(couleurForcee==='jaune') cls='acte-jaune';
    else if(couleurForcee==='bleu') cls='acte-bleu';
    else cls=acteCellClass(acte);
    if(cls){
      $cell.addClass(cls);
      $cell.attr('data-couleur', cls==='acte-jaune'?'jaune':'bleu');
      // Colorer le container Select2 visible
      $cont.addClass(cls==='acte-jaune'?'acte-jaune-s2':'acte-bleu-s2');
    }
  } else {
    $cell.removeAttr('data-acte').removeAttr('data-couleur');
  }
}
$(document).on('click','#acteTiles .acte-tile',function(){
  const acte=$(this).data('acte');
  if(ACTE_TARGET){ applyActeToCell(ACTE_TARGET, acte); }
  bootstrap.Modal.getInstance(document.getElementById('modalActeCell')).hide();
});
$('#acteCellSkip').on('click',function(){
  if(ACTE_TARGET){ applyActeToCell(ACTE_TARGET, ''); }
});

/* ---------- + Rubrique / + Item (persistance referentiel) ---------- */
$(document).on('click','.add-rub',function(){
  const sel=$(this).closest('.cell-actions').find('.grp-rub');
  Swal.fire({title:'Nouveau grand titre',input:'text',inputPlaceholder:"Ex: A- Renseignements sur l'aerodrome",showCancelButton:true,confirmButtonColor:'#1E9C4B',cancelButtonText:'Annuler'}).then(function(r){
    if(!r.isConfirmed || !(r.value||'').trim()) return;
    const val=r.value.trim();
    // Ajout TEMPORAIRE : aucune ecriture en BDD ici. La persistance se fait au clic sur Enregistrer.
    if(RUBS.indexOf(val)<0) RUBS.push(val);
    addOptionToAll('.grp-rub', val); sel.val(val);
    Swal.fire({icon:'success',title:'Grand titre ajoute',text:'Il sera enregistre avec le programme.',timer:1400,showConfirmButton:false});
  });
});
$(document).on('click','.add-itemref',function(){
  const sel=$(this).closest('.cell-actions').find('.it-sd');
  Swal.fire({title:'Nouvel item',input:'text',inputPlaceholder:'Ex: Donnees aeronautiques',showCancelButton:true,confirmButtonColor:'#1E9C4B',cancelButtonText:'Annuler'}).then(function(r){
    if(!r.isConfirmed || !(r.value||'').trim()) return;
    const val=r.value.trim();
    // Ajout TEMPORAIRE : persiste au clic sur Enregistrer (rattache au grand titre de la ligne).
    if(ITEMS.indexOf(val)<0) ITEMS.push(val);
    addOptionToAll('.it-sd', val); sel.val(val);
    Swal.fire({icon:'success',title:'Item ajoute',text:'Il sera enregistre avec le programme.',timer:1400,showConfirmButton:false});
  });
});

/* ---------- + RAG ---------- */
$(document).on('click','.add-rag',function(){ ragTargetSel=$(this).closest('.cell-actions').find('.it-rag'); $('#r_code,#r_lib').val(''); new bootstrap.Modal('#modalRag').show(); });
$('#btnSaveRag').on('click',function(){
  const code=$('#r_code').val().trim();
  if(!code){ Swal.fire({icon:'warning',text:'Code RAG requis.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}}); return; }
  apiPost({action:'create_reglement',code_reglement:code,libelle_reglement:$('#r_lib').val(),iddomaine:CURRENT.iddomaine}).done(function(res){
    if(!res.success||!res.code_reglement){ Swal.fire({icon:'error',text:res.message||'Echec',customClass:{container:'swal-over-modal'}}); return; }
    if(!OPTS.rags.some(function(r){return r.code_reglement===res.code_reglement;})) OPTS.rags.push({idreglement:res.idreglement,code_reglement:res.code_reglement,libelle_reglement:res.libelle_reglement});
    $('.it-rag').each(function(){ if(!$(this).find('option[value="'+res.code_reglement+'"]').length) $(this).append(new Option(res.code_reglement,res.code_reglement)); });
    if(ragTargetSel){ ragTargetSel.val(res.code_reglement); ragTargetSel=null; }
    bootstrap.Modal.getInstance('#modalRag').hide();
    Swal.fire({icon:'success',title:'RAG ajoute',text:res.code_reglement,timer:1200,showConfirmButton:false});
  });
});

/* ---------- + Site (avec pays) ---------- */
$(document).on('click','.add-site',function(){
  if(MODE==='operateur'){
    $('#o_nom,#o_trig,#o_ville').val('');
    $('#o_pays').html('<option value="">-- Aucun --</option>'+(OPTS.pays||[]).map(function(p){return '<option value="'+p.idpays+'">'+esc(p.nompays)+'</option>';}).join(''));
    if($.fn.select2){ if($('#o_pays').hasClass('select2-hidden-accessible')) $('#o_pays').select2('destroy'); $('#o_pays').select2({theme:'bootstrap-5',width:'100%',dropdownParent:$('#modalOperateur')}); }
    new bootstrap.Modal('#modalOperateur').show(); return;
  }
  $('#s_oaci,#s_nom,#s_ville').val('');
  $('#s_pays').html('<option value="">-- Aucun --</option>'+(OPTS.pays||[]).map(function(p){return '<option value="'+p.idpays+'">'+esc(p.nompays)+'</option>';}).join(''));
  if($.fn.select2){ if($('#s_pays').hasClass('select2-hidden-accessible')) $('#s_pays').select2('destroy'); $('#s_pays').select2({theme:'bootstrap-5',width:'100%',dropdownParent:$('#modalSite')}); }
  new bootstrap.Modal('#modalSite').show();
});
/* + Operateur */
$('#btnSaveOperateur').on('click',function(){
  const nom=$('#o_nom').val().trim();
  if(!nom){ Swal.fire({icon:'warning',text:'Nom de l\'operateur requis.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}}); return; }
  const trig=$('#o_trig').val().trim().toUpperCase();
  apiPost({action:'create_operateur',nomorga:nom,trigrorganisme:trig,ville_org:$('#o_ville').val(),idpays:$('#o_pays').val()||0}).done(function(res){
    if(!res.success||!res.idorga){ Swal.fire({icon:'error',text:res.message||'Echec',customClass:{container:'swal-over-modal'}}); return; }
    const t=(res.trigrorganisme||'').trim();
    if(!OPERATEURS.some(function(op){return String(op.idorga)===String(res.idorga);})) OPERATEURS.push({idorga:res.idorga,nomorga:res.nomorga,trigrorganisme:t});
    $('.it-wk').each(function(){
      if(t){ if(!$(this).find('option[value="'+t+'"]').length){ const op=new Option(t+' · '+res.nomorga,t); op.setAttribute('data-id',res.idorga); op.setAttribute('data-nom',res.nomorga); this.add(op); } }
      else { const op=new Option(res.nomorga+' (sans trigramme)','__NT__'+res.idorga); op.setAttribute('data-id',res.idorga); op.setAttribute('data-nom',res.nomorga); op.setAttribute('data-notrig','1'); this.add(op); }
    });
    bootstrap.Modal.getInstance('#modalOperateur').hide();
    Swal.fire({icon:'success',title:'Operateur ajoute',text:res.nomorga,timer:1300,showConfirmButton:false});
  });
});
/* + Type d'activite */
$('#btnAddType').on('click',function(){ $('#t_nom').val(''); new bootstrap.Modal('#modalType').show(); });
$('#btnSaveType').on('click',function(){
  const nom=$('#t_nom').val().trim();
  if(!nom){ Swal.fire({icon:'warning',text:'Nom du type requis.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}}); return; }
  apiPost({action:'create_type',nomtypeorg:nom}).done(function(res){
    if(!res.success||!res.idtypeorga){ Swal.fire({icon:'error',text:res.message||'Echec',customClass:{container:'swal-over-modal'}}); return; }
    if(!$('#np_type option[value="'+res.idtypeorga+'"]').length) $('#np_type').append(new Option(res.nomtypeorg,res.idtypeorga));
    $('#np_type').val(res.idtypeorga);
    bootstrap.Modal.getInstance('#modalType').hide();
    Swal.fire({icon:'success',title:'Type ajoute',timer:1100,showConfirmButton:false});
  });
});
/* + Domaine */
$('#btnAddDomaine').on('click',function(){ $('#d_code,#d_lib').val(''); new bootstrap.Modal('#modalDomaine').show(); });
$('#btnSaveDomaine').on('click',function(){
  const code=$('#d_code').val().trim();
  if(!code){ Swal.fire({icon:'warning',text:'Code domaine requis.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}}); return; }
  apiPost({action:'create_domaine',nomdomaine:code,libel_domaine:$('#d_lib').val()}).done(function(res){
    if(!res.success||!res.iddomaine){ Swal.fire({icon:'error',text:res.message||'Echec',customClass:{container:'swal-over-modal'}}); return; }
    const lib=(res.libel_domaine||'').trim(); const txt=(res.nomdomaine||'')+(lib?(' - '+lib):'');
    if(!$('#np_domaine option[value="'+res.iddomaine+'"]').length) $('#np_domaine').append(new Option(txt,res.iddomaine));
    $('#np_domaine').val(res.iddomaine);
    bootstrap.Modal.getInstance('#modalDomaine').hide();
    Swal.fire({icon:'success',title:'Domaine ajoute',timer:1100,showConfirmButton:false});
  });
});
$('#btnAddPays').on('click',function(){
  Swal.fire({title:'Nouveau pays',input:'text',inputPlaceholder:'Nom du pays',showCancelButton:true,confirmButtonColor:'#1E9C4B',cancelButtonText:'Annuler',customClass:{container:'swal-over-modal'}}).then(function(r){
    if(!r.isConfirmed||!r.value) return;
    apiPost({action:'create_pays',nompays:r.value}).done(function(res){
      if(!res.success){ Swal.fire({icon:'error',text:res.message||'Echec',customClass:{container:'swal-over-modal'}}); return; }
      OPTS.pays.push({idpays:res.idpays,nompays:res.nompays});
      $('#s_pays').append(new Option(res.nompays,res.idpays,true,true));
    });
  });
});
$('#btnSaveSite').on('click',function(){
  const oaci=$('#s_oaci').val().trim().toUpperCase();
  if(!oaci){ Swal.fire({icon:'warning',text:'Indicateur OACI requis.',confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}}); return; }
  apiPost({action:'create_site',indicateur_oaci:oaci,nomsite:$('#s_nom').val(),ville:$('#s_ville').val(),idpays:$('#s_pays').val()||0}).done(function(res){
    if(!res.success||!res.indicateur_oaci){ Swal.fire({icon:'error',text:res.message||'Echec',customClass:{container:'swal-over-modal'}}); return; }
    if(!OPTS.sites.some(function(s){return s.indicateur_oaci===res.indicateur_oaci;})) OPTS.sites.push({idsite:res.idsite,indicateur_oaci:res.indicateur_oaci,nomsite:res.nomsite});
    $('.it-wk').each(function(){ if(!$(this).find('option[value="'+res.indicateur_oaci+'"]').length) $(this).append(new Option(res.indicateur_oaci,res.indicateur_oaci)); });
    bootstrap.Modal.getInstance('#modalSite').hide();
    Swal.fire({icon:'success',title:'Site ajoute',text:res.indicateur_oaci,timer:1200,showConfirmButton:false});
  });
});

/* ---------- Lire la matrice (groupes) ---------- */
function getMatrixData(){
  const groupes=[];
  $('#matrix tbody.grp-block').each(function(){
    const rub=$(this).find('.grp-rub').val().trim();
    const items=[];
    $(this).find('.item-row').each(function(){
      const sd=$(this).find('.it-sd').val().trim();
      const rag=$(this).find('.it-rag').val()||'';
      const cellules={};
      $(this).find('.it-wk').each(function(){
        const v=$(this).val();
        if(v && v.indexOf('__NT__')!==0){
          // On stocke le code, l'acte ET la couleur (mise en forme conditionnelle)
          const acte=$(this).attr('data-acte')||'';
          const couleur=$(this).attr('data-couleur')||'';
          cellules[$(this).data('sem')]={code:v, acte:acte, couleur:couleur};
        }
      });
      if(sd||rag||Object.keys(cellules).length) items.push({sous_domaine:sd,rag:rag,cellules:cellules});
    });
    if(rub||items.length) groupes.push({rubrique:rub,items:items});
  });
  return {groupes:groupes};
}

/* ---------- Enregistrer ---------- */
// Retire tout marquage d'erreur precedent sur les items
function clearItemErrors(){
  $('#matrix .it-rag, #matrix .it-sd, #matrix .grp-rub').removeClass('cell-error');
}
// Des que l'utilisateur corrige un champ signale, on retire le marquage rouge
$(document).on('change input','.it-rag, .it-sd, .grp-rub',function(){ $(this).removeClass('cell-error'); });
$(document).on('change','.it-wk',function(){ if(this.value){ $(this).removeClass('cell-error'); } });
$('#btnSaveProg').on('click',function(){
  clearItemErrors();
  // Validation stricte : rien ne s'enregistre si un champ requis est vide.
  // Regles : chaque grand titre doit avoir un libelle ; chaque ligne doit avoir
  // item + RAG + au moins une cible (site/operateur) ; au moins un item complet
  // au total. Toute ligne totalement vide est ignoree (elle sera nettoyee).
  let invalid=null;
  let nbItemsComplets=0;
  const cible=(MODE==='operateur'?'operateur':'site');
  $('#matrix tbody.grp-block').each(function(){
    const $grp=$(this);
    const $rub=$grp.find('.grp-rub');
    const rub=($rub.val()||'').trim();
    // Le grand titre est toujours obligatoire
    if(!rub){ invalid={el:$rub.get(0), msg:'Le grand titre (referentiel) est obligatoire.'}; return false; }
    let nbCompletsGroupe=0;
    $grp.find('.item-row').each(function(){
      const $sd=$(this).find('.it-sd'), $rag=$(this).find('.it-rag');
      const sd=($sd.val()||'').trim();
      const rag=($rag.val()||'').trim();
      const nbWk=$(this).find('.it-wk').filter(function(){return !!this.value;}).length;
      const ligneVide = (!sd && !rag && nbWk===0);
      if(ligneVide){ return; } // ligne totalement vide : ignoree
      // Ligne partiellement remplie -> tous les champs deviennent obligatoires
      if(!sd){ invalid={el:$sd.get(0), msg:'Le referentiel / item est obligatoire pour cette ligne du titre "'+rub+'".'}; return false; }
      if(!rag){ invalid={el:$rag.get(0), msg:'Le RAG est obligatoire pour l\'item "'+sd+'".'}; return false; }
      if(nbWk===0){
        const $firstWk=$(this).find('.it-wk').first();
        invalid={el:$firstWk.get(0), msg:'Aucun '+cible+' n\'est encore choisi pour l\'item "'+sd+'". Placez le curseur sur la semaine voulue (a partir de S1) et selectionnez un '+cible+'.'};
        return false;
      }
      nbCompletsGroupe++; nbItemsComplets++;
    });
    if(invalid) return false;
    // Un grand titre nomme doit avoir au moins un item complet
    if(nbCompletsGroupe===0){
      const $firstSd=$grp.find('.item-row:first .it-sd');
      invalid={el:$firstSd.get(0), msg:'Le grand titre "'+rub+'" doit contenir au moins un item complet (item, RAG et un '+cible+').'};
      return false;
    }
    return !invalid;
  });
  // Aucun item complet dans tout le programme -> on refuse
  if(!invalid && nbItemsComplets===0){
    const $first=$('#matrix .item-row:first .it-sd');
    invalid={el:$first.get(0), msg:'Le programme est vide. Renseignez au moins un item complet (grand titre, item, RAG et un '+cible+').'};
  }
  if(invalid){
    if(invalid.el){
      const $el=$(invalid.el);
      $el.addClass('cell-error');
      // Si Select2 est actif, on cible le container visible pour le scroll
      const $target = $el.hasClass('select2-hidden-accessible') ? $el.next('.select2-container') : $el;
      const domTarget = $target.get(0) || invalid.el;
      try{ domTarget.scrollIntoView({block:'center', inline:'center', behavior:'smooth'}); }catch(e){ if(domTarget.scrollIntoView) domTarget.scrollIntoView(); }
      setTimeout(function(){
        if($el.hasClass('select2-hidden-accessible')){ $el.select2('open'); }
        else if($el.hasClass('it-wk')){
          // Cellule semaine pas encore chargee : on la prepare et on ouvre la liste
          $el.trigger('mousedown');
        }
        else { try{ invalid.el.focus({preventScroll:true}); }catch(e){ try{invalid.el.focus();}catch(_){}} }
      }, 350);
    }
    Swal.fire({icon:'warning',title:'Validation',text:invalid.msg,confirmButtonColor:'#D32F2F'});
    return;
  }
  const btn=$(this); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
  apiPost({action:'save',idprogramme:CURRENT.idprogramme,matrice:JSON.stringify(getMatrixData()),revision:$('#progRev').text(),statut:(CURRENT.statut||'brouillon')}).done(function(res){
    btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer');
    if(res.success) Swal.fire({icon:'success',title:'Programme enregistre',text:res.nb_groupes+' grand(s) titre(s), '+res.nb_items+' item(s)',timer:1700,showConfirmButton:false});
    else Swal.fire({icon:'error',text:res.message||'Echec',confirmButtonColor:'#23408F'});
  }).fail(function(){ btn.prop('disabled',false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer'); Swal.fire({icon:'error',text:'Echec de la requete.'}); });
});
$('#btnBackList').on('click',function(){ $('#viewEditor').hide(); $('#viewList').show(); loadList(); });

/* ---------- Export statique (PDF / Excel) ---------- */
function buildLegendHTML(){
  const data=getMatrixData();
  const used=new Set();
  data.groupes.forEach(function(g){ g.items.forEach(function(it){ Object.keys(it.cellules).forEach(function(k){ const c=it.cellules[k]; const code=(c&&typeof c==='object')?c.code:c; if(code) used.add(code); }); }); });
  if(!used.size) return '';
  const map={};
  if(MODE==='operateur'){ (OPERATEURS||[]).forEach(function(op){ const t=(op.trigrorganisme||'').trim(); if(t) map[t]=op.nomorga||''; }); }
  else { (OPTS.sites||[]).forEach(function(s){ map[s.indicateur_oaci]=s.nomsite||''; }); }
  const arr=[...used].sort(); const perRow=5;
  let html='<table style="border-collapse:collapse;font-size:9px;margin-top:4px"><tr>';
  arr.forEach(function(o,i){
    html+='<td style="border:1px solid #999;padding:2px 8px"><strong style="color:#D32F2F">'+esc(o)+'</strong> : '+esc(map[o]||'-')+'</td>';
    if((i+1)%perRow===0 && i!==arr.length-1) html+='</tr><tr>';
  });
  html+='</tr></table>';
  return '<div style="margin-top:10px"><div style="font-weight:700;color:#23408F;margin-bottom:2px">'+(MODE==='operateur'?'Legende des operateurs':'Legende des sites')+'</div>'+html+'</div>';
}
/* Legende des actes de supervision (couleurs du programme : bleu / jaune) */
function buildActeLegendHTML(){
  const data=getMatrixData();
  const used=new Set();
  data.groupes.forEach(function(g){ g.items.forEach(function(it){ Object.keys(it.cellules).forEach(function(k){ const c=it.cellules[k]; const acte=(c&&typeof c==='object')?c.acte:''; if(acte) used.add(acte); }); }); });
  if(!used.size) return '';
  let cells='';
  ACTES.forEach(function(a){
    if(!used.has(a.v)) return;
    const jaune=(a.v==='inspection_non_programmee');
    const bg=jaune?'#F3C300':'#23408F'; const col=jaune?'#000':'#fff'; const fw=jaune?'800':'700';
    cells+='<td style="border:1px solid #999;padding:2px 8px;background:'+bg+';color:'+col+';font-weight:'+fw+';font-size:9px">'+esc(a.t)+'</td>';
  });
  if(!cells) return '';
  return '<div style="margin-top:8px"><div style="font-weight:700;color:#23408F;margin-bottom:2px">Legende des actes de supervision</div>'
    +'<table style="border-collapse:collapse"><tr>'+cells+'</tr></table></div>';
}
function buildSignHTML(){
  const rows=SIGNATAIRES.map(function(sg){
    return '<tr><td style="border:1px solid #666;padding:8px 10px"><strong>'+esc(sg.nom)+'</strong><br><em style="font-size:9px">'+esc(sg.titre)+'</em></td>'
      +'<td style="border:1px solid #666;width:170px;height:44px"></td><td style="border:1px solid #666;width:130px"></td></tr>';
  }).join('');
  return '<table style="border-collapse:collapse;font-size:10px;margin-top:6px;margin-left:auto"><thead><tr>'
    +'<th style="border:1px solid #666;background:#23408F;color:#fff;padding:4px 12px">Noms &amp; Titres</th>'
    +'<th style="border:1px solid #666;background:#23408F;color:#fff;padding:4px 12px">Visas</th>'
    +'<th style="border:1px solid #666;background:#23408F;color:#fff;padding:4px 12px">Dates</th>'
    +'</tr></thead><tbody>'+rows+'</tbody></table>';
}
function staticTableHTML(){
  const data=getMatrixData();
  const groups=[]; let cur=null;
  WEEKS.forEach(function(w){ if(!cur||cur.mi!==w.mois_index){ cur={mi:w.mois_index,mois:w.mois,count:0}; groups.push(cur);} cur.count++; });
  let th1='<tr><th colspan="2" style="width:22%">REFERENTIEL</th>';
  groups.forEach(function(g){ th1+='<th colspan="'+g.count+'">'+esc(g.mois)+'</th>'; }); th1+='</tr>';
  let th2='<tr><th style="width:17%">SOUS-DOMAINES</th><th style="width:5%">RAG</th>';
  WEEKS.forEach(function(w){ th2+='<th>S'+w.sem+'</th>'; }); th2+='</tr>';
  let body='';
  data.groupes.forEach(function(g){
    body+='<tr><td colspan="'+(WEEKS.length+2)+'" style="background:#5b6b80;color:#fff;font-weight:700">'+esc(g.rubrique)+'</td></tr>';
    g.items.forEach(function(it){
      body+='<tr><td>'+esc(it.sous_domaine)+'</td><td style="text-align:center">'+esc(it.rag)+'</td>';
      WEEKS.forEach(function(w){
        const raw=it.cellules[w.sem]||it.cellules[String(w.sem)]||'';
        const v=cellCode(raw); const acte=cellActe(raw); const coul=cellCouleur(raw);
        // Couleur : stockee si presente, sinon deduite de l'acte
        let st='';
        if(v && (acte||coul)){
          let jaune = (coul==='jaune') || (!coul && acte==='inspection_non_programmee');
          st = jaune ? 'background:#F3C300;color:#000;font-weight:800;' : 'background:#23408F;color:#fff;font-weight:700;';
        }
        body+='<td style="text-align:center;'+st+'" title="'+esc(acte?ACTE_TXT[acte]||'':'')+'">'+esc(v)+'</td>';
      });
      body+='</tr>';
    });
  });
  return '<table border="1" cellspacing="0" cellpadding="3" style="border-collapse:collapse;font-family:Candara,Arial;font-size:9px;width:100%;table-layout:fixed"><thead style="background:#23408F;color:#fff">'+th1+th2+'</thead><tbody>'+body+'</tbody></table>';
}
const STAT_BG={1:'#23408F',2:'#E8890C',3:'#1E9C4B',4:'#D32F2F',5:'#D32F2F',6:'#D32F2F',7:'#7A8798'};
const STAT_TXT={1:'Inspection planifiee',2:'Inspection reportee',3:'Inspection effectuee',4:'Inspection suspendue',5:'A surveiller',6:'Inspection annulee',7:'Inspection inopinee'};
function buildStatLegendHTML(){
  let cells='';
  [1,2,3,4,5,6,7].forEach(function(k){
    cells+='<td style="border:1px solid #999;padding:2px 8px;background:'+STAT_BG[k]+';color:#fff;font-weight:700;font-size:9px">'+esc(STAT_TXT[k])+'</td>';
  });
  return '<div style="margin-top:8px"><div style="font-weight:700;color:#23408F;margin-bottom:2px">Legende des statuts</div>'
    +'<table style="border-collapse:collapse"><tr>'+cells+'</tr></table></div>';
}
const DOC_CODE='IX-GEN-R3-F-E-001';
function frDateLong(sd){ if(!sd) return ''; const p=String(sd).substring(0,10).split('-'); if(p.length!==3) return sd; const m=['','Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre']; return p[2]+' '+(m[parseInt(p[1],10)]||p[1])+' '+p[0]; }
$('#btnPdf').on('click',function(){
  const w=window.open('','_blank','width=1300,height=850');
  const doc='<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'+esc(CURRENT.titre)+'</title>'
   +'<style>@page{size:A3 landscape;margin:6mm}body{font-family:Candara,Arial;font-size:10px;color:#2C3E50}'
   +'table{width:100%}'
   +'h2{color:#23408F;text-align:center;margin:6px 0;text-transform:uppercase}</style></head><body>'
   +'<table style="width:100%;border:none;border-collapse:collapse"><tr>'
     +'<td style="border:none;vertical-align:top;width:74%"><img src="'+BANER+'" alt="ANAC" style="width:100%;max-height:120px;object-fit:contain;display:block"></td>'
     +'<td style="border:none;vertical-align:top;text-align:right;font-size:10px;line-height:1.7;color:#2C3E50">'
       +'<div><strong>'+DOC_CODE+'</strong></div>'
       +'<div>Revision : '+esc($('#progRev').text())+'</div>'
       +'<div>Date : '+esc(frDateLong($('#progDate').text()))+'</div>'
     +'</td></tr></table>'
   +'<h2 style="text-align:center;color:#23408F;text-transform:uppercase;margin:6px 0">'+esc(CURRENT.titre)+'</h2>'
   +staticTableHTML()
   +'<table style="width:100%;margin-top:10px;border:none"><tr>'
     +'<td style="vertical-align:top;border:none">'+buildLegendHTML()+buildActeLegendHTML()+NB_TEXT+'</td>'
     +'<td style="vertical-align:top;border:none;text-align:right;width:430px">'+buildSignHTML()+'</td>'
   +'</tr></table>'
   +'<script>window.onload=function(){setTimeout(function(){window.print();},300);};<\/script>'
   +'</body></html>';
  w.document.write(doc); w.document.close(); w.focus();
});
$('#btnXls').on('click',function(){
  const html='<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>'
   +'<h3>'+esc(CURRENT.titre)+'</h3>'+staticTableHTML()+buildLegendHTML()+buildActeLegendHTML()+NB_TEXT+buildSignHTML()+'</body></html>';
  const blob=new Blob(['\ufeff'+html],{type:'application/vnd.ms-excel'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='Programme_PSC_'+CURRENT.annee+'.xls'; document.body.appendChild(a); a.click(); a.remove();
});

/* ---------- Actions liste ---------- */
$(document).on('click','.act-open',function(){ openProgramme($(this).data('id')); });
$(document).on('click','.act-del',function(){
  const id=$(this).data('id'), t=$(this).data('t');
  Swal.fire({title:'Supprimer ce programme ?',html:'<strong>'+esc(t)+'</strong><br>Action definitive.',icon:'warning',showCancelButton:true,confirmButtonColor:'#D32F2F',cancelButtonText:'Annuler',confirmButtonText:'Supprimer'}).then(function(r){
    if(!r.isConfirmed) return;
    apiPost({action:'delete',idprogramme:id}).done(function(res){ if(res.success){ Swal.fire({icon:'success',title:'Supprime',timer:1100,showConfirmButton:false}); loadList(); } else Swal.fire({icon:'error',text:res.message||'Echec',confirmButtonColor:'#23408F'}); });
  });
});
function openThenExport(id,mode){
  apiPost({action:'get',idprogramme:id}).done(function(res){
    if(!res.success) return;
    CURRENT=res.programme; WEEKS=res.weeks||[]; NBSEM=res.nb_semaines||52; OPTS=res.options||{rags:[],sites:[],sousdomaines:[],pays:[],operateurs:[]};
    MODE=(CURRENT.mode_cible==='operateur')?'operateur':'site'; OPERATEURS=OPTS.operateurs||[];
    buildHead(); buildBody((res.matrice&&res.matrice.groupes)?res.matrice.groupes:[]);
    $('#progRev').text(CURRENT.revision||'00'); $('#progDate').text((CURRENT.date_etablissement||'').substring(0,10));
    loadTriggered(id, function(){ if(mode==='pdf') $('#btnPdf').click(); else $('#btnXls').click(); });
  });
}
$(document).on('click','.act-pdf',function(){ openThenExport($(this).data('id'),'pdf'); });
$(document).on('click','.act-xls',function(){ openThenExport($(this).data('id'),'xls'); });

/* Filtres + statistiques (lies une seule fois) */
function _exact(idx,val){ if(!progTable)return; progTable.column(idx).search(val===''?'':'^'+$.fn.dataTable.util.escapeRegex(val)+'$', true, false); progTable.draw(); }
function _contains(idx,val){ if(!progTable)return; progTable.column(idx).search(val===''?'':'(^|,)'+$.fn.dataTable.util.escapeRegex(val)+'(,|$)', true, false); progTable.draw(); }
$('#f_annee').on('change',function(){ _exact(13,this.value); });
$('#f_type').on('change',function(){ _exact(10,this.value); });
$('#f_dom').on('change',function(){ _exact(11,this.value); });
$('#f_site').on('change',function(){ _contains(14,this.value); });
$('#f_statut').on('change',function(){ _exact(12,this.value); });
/* ============================================================
 *  Les cartes de statistiques servent de filtres rapides.
 *  Un clic filtre le tableau, un second clic annule le filtre.
 * ============================================================ */
function statChoisir(titre, icone, valeurs, selecteurFiltre){
  if(!valeurs.length){
    Swal.fire({icon:'info',title:titre,text:'Aucune valeur disponible.',confirmButtonColor:'#23408F'});
    return;
  }
  const opts = {};
  valeurs.forEach(function(v){ opts[v] = v; });
  Swal.fire({
    title: titre,
    input: 'select',
    inputOptions: opts,
    inputPlaceholder: 'Choisir...',
    icon: icone,
    showCancelButton: true,
    cancelButtonText: 'Annuler',
    confirmButtonText: 'Filtrer',
    confirmButtonColor: '#23408F'
  }).then(function(r){
    if(!r.isConfirmed || !r.value) return;
    $(selecteurFiltre).val(r.value).trigger('change');
    $('.stat-card').removeClass('stat-on');
    $('.stat-card[data-stat]').filter(function(){
      return $(this).data('stat') === (selecteurFiltre === '#f_annee' ? 'annee' : (selecteurFiltre === '#f_dom' ? 'domaine' : 'site'));
    }).addClass('stat-on');
  });
}

$(document).on('click','.stat-card[data-stat]',function(){
  const type = String($(this).data('stat'));
  const dejaActif = $(this).hasClass('stat-on');
  $('.stat-card').removeClass('stat-on');

  if(type === 'tous' || dejaActif){
    $('#f_annee,#f_type,#f_dom,#f_site,#f_statut').val('');
    if(progTable){ progTable.columns([10,11,12,13,14]).search(''); progTable.search(''); progTable.draw(); }
    return;
  }

  if(type === 'valide' || type === 'brouillon'){
    $('#f_statut').val(type).trigger('change');
    $(this).addClass('stat-on');
    return;
  }

  // Annee, domaine et site : la valeur precise est demandee a l'utilisateur
  const listeDistincte = function(sel){
    return $(sel+' option').map(function(){ return $(this).val(); }).get().filter(Boolean).sort();
  };
  if(type === 'annee')   { statChoisir('Filtrer par annee',   'question', listeDistincte('#f_annee'), '#f_annee'); }
  if(type === 'domaine') { statChoisir('Filtrer par domaine', 'question', listeDistincte('#f_dom'),   '#f_dom');   }
  if(type === 'site')    { statChoisir('Filtrer par site',    'question', listeDistincte('#f_site'),  '#f_site');  }
});

$('#btnResetFilters').on('click',function(){ $('#f_annee,#f_type,#f_dom,#f_site,#f_statut').val(''); $('.stat-card').removeClass('stat-on'); if(progTable){ progTable.columns([10,11,12,13,14]).search(''); progTable.search(''); progTable.draw(); } });
$('#btnToggleStats').on('click',function(){ const show=$('#statsPanel').is(':hidden'); $('#statsPanel').toggle(show); $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques'); });

/* ============ MODE DECLENCHEMENT (programme -> audits) ============ */
let DMAT=[], DAUDITS=[], selCode='', selSem=0, selType='';
const ST={1:{l:'Inspection planifiee',c:'#23408F'},2:{l:'Inspection reportee',c:'#b58a00'},3:{l:'Inspection effectuee',c:'#1E9C4B'},4:{l:'Inspection suspendue',c:'#6b7a90'},5:{l:'A surveiller',c:'#b58a00'},6:{l:'Inspection annulee',c:'#D32F2F'},7:{l:'Inspection inopinee',c:'#F3C300'}};
function isoWeekDate(year, week){ const d=new Date(Date.UTC(year,0,4)); const day=(d.getUTCDay()+6)%7; d.setUTCDate(d.getUTCDate()-day+(week-1)*7+3); return d.toISOString().slice(0,10); }
function isoWeekOf(dateStr){ if(!dateStr) return 0; const t=new Date(dateStr+'T00:00:00Z'); if(isNaN(t)) return 0; const d=new Date(Date.UTC(t.getUTCFullYear(),t.getUTCMonth(),t.getUTCDate())); const day=(d.getUTCDay()+6)%7; d.setUTCDate(d.getUTCDate()-day+3); const f=new Date(Date.UTC(d.getUTCFullYear(),0,4)); const fd=(f.getUTCDay()+6)%7; f.setUTCDate(f.getUTCDate()-fd+3); return 1+Math.round((d-f)/(7*24*3600*1000)); }
function statusOf(code, sem){ for(const a of DAUDITS){ if(isoWeekOf(a.date_previsionnelle)!==sem) continue; if(MODE==='operateur'){ if(String(a.trigrorganisme||'')===String(code)) return a; } else { if(String(a.site_inspection||'')===String(code)) return a; } } return null; }

function openDeclench(idprog){
  apiPost({action:'get',idprogramme:idprog}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',text:res.message||'Introuvable',confirmButtonColor:'#23408F'}); return; }
    CURRENT=res.programme; WEEKS=res.weeks||[]; NBSEM=res.nb_semaines||52; OPTS=res.options||{rags:[],sites:[],sousdomaines:[],pays:[],operateurs:[]};
    MODE=(CURRENT.mode_cible==='operateur')?'operateur':'site'; OPERATEURS=OPTS.operateurs||[];
    DMAT=(res.matrice&&res.matrice.groupes)?res.matrice.groupes:[];
    $('#dTitre').text(CURRENT.titre); $('#dNbSem').text(NBSEM);
    apiPost({action:'cell_status',idprogramme:idprog}).done(function(r2){
      DAUDITS=(r2&&r2.audits)?r2.audits:[]; renderDeclench();
      $('#viewList').hide(); $('#viewEditor').hide(); $('#viewDeclench').show(); window.scrollTo(0,0);
    }).fail(function(){ DAUDITS=[]; renderDeclench(); $('#viewList').hide(); $('#viewEditor').hide(); $('#viewDeclench').show(); });
  });
}
function renderDeclench(){
  const groups=[]; let cur=null;
  WEEKS.forEach(function(w){ if(!cur||cur.mi!==w.mois_index){ cur={mi:w.mois_index,mois:w.mois,count:0}; groups.push(cur);} cur.count++; });
  let r1='<tr class="r1"><th class="cfix col-sd">REFERENTIEL</th><th class="cfix-rag col-rag" rowspan="2">RAG</th>';
  groups.forEach(function(g){ r1+='<th class="mois" colspan="'+g.count+'">'+esc(g.mois)+'</th>'; }); r1+='</tr>';
  let r2='<tr class="r2"><th class="cfix col-sd">SOUS-DOMAINES</th>'; WEEKS.forEach(function(w){ r2+='<th>S'+w.sem+'</th>'; }); r2+='</tr>';
  let body='';
  (DMAT||[]).forEach(function(g){
    body+='<tr class="grp-row"><td class="cfix col-sd" style="background:#5b6b80;color:#fff;font-weight:700">'+esc(g.rubrique||'')+'</td><td class="cfix-rag" style="background:#5b6b80"></td>';
    WEEKS.forEach(function(){ body+='<td class="wk" style="background:#6b7a90"></td>'; }); body+='</tr>';
    (g.items||[]).forEach(function(it){
      body+='<tr><td class="cfix col-sd">'+esc(it.sous_domaine||'')+'</td><td class="cfix-rag col-rag" style="text-align:center">'+esc(it.rag||'')+'</td>';
      WEEKS.forEach(function(w){
        const _raw=(it.cellules&&(it.cellules[String(w.sem)]||it.cellules[w.sem]))||''; const v=cellCode(_raw); const _acte=cellActe(_raw); const _coul=cellCouleur(_raw);
        if(v){ const au=statusOf(v,w.sem);
          if(au){ const st=ST[au.statut]||{l:'-',c:'#6b7a90'}; body+='<td class="wk" style="text-align:center;background:'+st.c+'22"><span class="dcell done" title="'+esc(st.l)+'" style="border-color:'+st.c+';color:'+st.c+'">'+esc(v)+'<br><small>'+esc(st.l)+'</small></span></td>'; }
          else {
            // Cellule non declenchee : couleur de l'acte du programme.
            // Jaune = inspection non programmee ; bleu = tout autre acte OU par defaut
            // (compatibilite avec les programmes crees avant la gestion des actes).
            let jaune = (_coul==='jaune') || (_acte==='inspection_non_programmee');
            let cellStyle='text-align:center', btnStyle='', acteTitle=_acte?(' - '+(ACTE_TXT[_acte]||'')):'';
            if(jaune){ cellStyle+=';background:#F3C300'; btnStyle='background:#F3C300;color:#000;font-weight:800;border-color:#c9a200'; }
            else { cellStyle+=';background:#23408F'; btnStyle='background:#23408F;color:#fff;font-weight:700;border-color:#1b3576'; }
            body+='<td class="wk" style="'+cellStyle+'"><button type="button" class="dcell trig" data-code="'+esc(v)+'" data-sem="'+w.sem+'" data-acte="'+esc(_acte||'')+'"'+(btnStyle?(' style="'+btnStyle+'"'):'')+' title="Declencher un acte'+esc(acteTitle)+'">'+esc(v)+'</button></td>';
          }
        } else { body+='<td class="wk"></td>'; }
      }); body+='</tr>';
    });
  });
  if(!body) body='<tr><td colspan="'+(WEEKS.length+2)+'" class="text-center text-muted p-3">Ce programme ne contient pas encore de cible planifiee.</td></tr>';
  $('#dmatrix').html('<thead>'+r1+r2+'</thead><tbody>'+body+'</tbody>');
  renderDeclenchLegendes();
}
/* Legendes de la vue declenchement :
   1) operateurs/sites utilises  2) actes (bleu/jaune)  3) statuts (declenchement) */
function renderDeclenchLegendes(){
  // Codes et actes reellement utilises
  const usedCodes=new Set(), usedActes=new Set();
  (DMAT||[]).forEach(function(g){ (g.items||[]).forEach(function(it){
    Object.keys(it.cellules||{}).forEach(function(k){
      const raw=it.cellules[k]; const code=cellCode(raw); const acte=cellActe(raw);
      if(code) usedCodes.add(code); if(acte) usedActes.add(acte);
    });
  }); });
  let html='';
  // 1) Legende operateurs / sites
  if(usedCodes.size){
    const map={};
    if(MODE==='operateur'){ (OPERATEURS||[]).forEach(function(op){ const t=(op.trigrorganisme||'').trim(); if(t) map[t]=op.nomorga||''; }); }
    else { (OPTS.sites||[]).forEach(function(s){ map[s.indicateur_oaci]=s.nomsite||''; }); }
    let items=[...usedCodes].sort().map(function(c){
      return '<span class="me-3"><strong style="color:#D32F2F">'+esc(c)+'</strong> : '+esc(map[c]||'-')+'</span>';
    }).join('');
    html+='<div class="mb-2"><div style="font-weight:700;color:#23408F;font-size:.8rem;margin-bottom:2px">Legende des '+(MODE==='operateur'?'operateurs':'sites')+'</div><div style="font-size:.78rem">'+items+'</div></div>';
  }
  // 2) Legende des actes de supervision (bleu / jaune)
  let acteCells='';
  const ordre=['audit','inspection_programmee','inspection_non_programmee','demonstration','test','investigation'];
  // Si aucun acte defini, on montre au moins le code par defaut (bleu) + non programmee (jaune)
  const actesToShow = usedActes.size ? ordre.filter(function(a){return usedActes.has(a);}) : [];
  if(actesToShow.length){
    actesToShow.forEach(function(a){
      const jaune=(a==='inspection_non_programmee');
      const bg=jaune?'#F3C300':'#23408F', col=jaune?'#000':'#fff', fw=jaune?'800':'700';
      acteCells+='<span class="me-2" style="display:inline-block;padding:2px 10px;border-radius:4px;background:'+bg+';color:'+col+';font-weight:'+fw+';font-size:.76rem">'+esc(ACTE_TXT[a]||a)+'</span>';
    });
  } else {
    // Legende generique (couleurs par defaut) quand les actes ne sont pas encore renseignes
    acteCells+='<span class="me-2" style="display:inline-block;padding:2px 10px;border-radius:4px;background:#23408F;color:#fff;font-weight:700;font-size:.76rem">Acte de supervision (defaut)</span>';
    acteCells+='<span class="me-2" style="display:inline-block;padding:2px 10px;border-radius:4px;background:#F3C300;color:#000;font-weight:800;font-size:.76rem">Inspection non programmee</span>';
  }
  html+='<div class="mb-2"><div style="font-weight:700;color:#23408F;font-size:.8rem;margin-bottom:2px">Legende des actes de supervision</div><div>'+acteCells+'</div></div>';
  // 3) Legende des statuts (declenchement)
  let statCells='';
  Object.keys(ST).forEach(function(k){
    const s=ST[k];
    statCells+='<span class="me-2" style="display:inline-block;padding:2px 10px;border-radius:4px;background:'+s.c+';color:#fff;font-weight:700;font-size:.76rem">'+esc(s.l)+'</span>';
  });
  html+='<div><div style="font-weight:700;color:#23408F;font-size:.8rem;margin-bottom:2px">Legende des statuts</div><div>'+statCells+'</div></div>';
  $('#dLegendes').html(html);
}
$(document).on('click','.act-trigger',function(){ openDeclench($(this).data('id')); });
$('#btnBackListD').on('click',function(){ $('#viewDeclench').hide(); $('#viewList').show(); loadList(); });
$(document).on('click','.dcell.trig',function(){
  selCode=$(this).data('code'); selSem=parseInt($(this).data('sem'),10); selType='';
  const acteProgramme=$(this).data('acte')||'';
  $('#natCibleInfo').html('Cible : <strong>'+esc(selCode)+'</strong> &middot; Semaine S'+selSem+' &middot; '+(MODE==='operateur'?'Operateur':'Site'));
  // Mise en evidence de l'acte prevu au programme (le CI peut garder ou changer)
  $('#modalNature .nat-tile').removeClass('prevu');
  if(acteProgramme){
    const $t=$('#modalNature .nat-tile[data-type="'+acteProgramme+'"]');
    $t.addClass('prevu');
    // Petit rappel visuel en haut de la modale
    $('#natCibleInfo').append('<div class="mt-2 small" style="color:#1E9C4B"><i class="bi bi-check-circle-fill me-1"></i>Acte prevu au programme : <strong>'+esc(ACTE_TXT[acteProgramme]||acteProgramme)+'</strong> (mis en evidence ci-dessous, modifiable).</div>');
  }
  new bootstrap.Modal('#modalNature').show();
});
$(document).on('click','#modalNature .nat-tile',function(){
  selType=$(this).data('type');
  $('#cadreNature').text($(this).find('.nt').text().replace(/\s+/g,' ').trim());
  $('input[name="psc_cadre"]').prop('checked',false); $('#modalCadre .cadre-opt').removeClass('sel'); $('#pscCadreContinue').prop('disabled',true);
  bootstrap.Modal.getInstance('#modalNature').hide(); new bootstrap.Modal('#modalCadre').show();
});
$(document).on('change','input[name="psc_cadre"]',function(){ $('#modalCadre .cadre-opt').removeClass('sel'); $(this).closest('.cadre-opt').addClass('sel'); $('#pscCadreContinue').prop('disabled',false); });
$('#pscCadreContinue').on('click',function(){
  const cadre=$('input[name="psc_cadre"]:checked').val(); if(!selType||!cadre) return;
  const dprev=isoWeekDate(parseInt(CURRENT.annee,10), selSem);
  let url=AGAI_BASE+'/declenchement?type='+encodeURIComponent(selType)+'&cadre='+encodeURIComponent(cadre)
    +'&psc_prog='+encodeURIComponent(CURRENT.idprogramme)+'&psc_sem='+selSem+'&psc_mode='+MODE
    +'&psc_idtypeorga='+encodeURIComponent(CURRENT.idtypeorga||'')+'&psc_dprev='+encodeURIComponent(dprev);
  if(MODE==='operateur'){ const op=(OPERATEURS||[]).find(function(x){return String(x.trigrorganisme||'')===String(selCode);}); url+='&psc_idorga='+encodeURIComponent(op?op.idorga:''); }
  else { const site=(OPTS.sites||[]).find(function(x){return String(x.indicateur_oaci)===String(selCode);}); url+='&psc_oaci='+encodeURIComponent(selCode)+'&psc_idsite='+encodeURIComponent(site?site.idsite:''); }
  window.location=url;
});

/* ============ VUE DECLENCHEMENT ============ */
const STAT_LBL={1:'Planifie',2:'Reporte',3:'Effectue',4:'Suspendu',5:'A surveiller',6:'Annule',7:'Inopine'};
const STAT_ABBR={1:'P',2:'R',3:'E',4:'S',5:'AS',6:'A',7:'I'};
const NATLBL={audit:'Audit',inspection_programmee:'Inspection programmee',inspection_non_programmee:'Inspection non programmee',demonstration:'Demonstration',test:'Test',investigation:'Investigation'};
let TRIG={}, TRIGCTX=null;
function actModal(){ return bootstrap.Modal.getOrCreateInstance('#actModal'); }
function cadreModalP(){ return bootstrap.Modal.getOrCreateInstance('#cadreModalPsc'); }

function headHtml(){
  const groups=[]; let cur=null;
  WEEKS.forEach(function(w){ if(!cur||cur.mi!==w.mois_index){ cur={mi:w.mois_index,mois:w.mois,count:0}; groups.push(cur);} cur.count++; });
  let r1='<tr class="r1"><th class="cfix col-sd" rowspan="1">REFERENTIEL</th><th class="cfix-rag col-rag" rowspan="2">RAG</th>';
  groups.forEach(function(g){ r1+='<th class="mois" colspan="'+g.count+'">'+esc(g.mois)+'</th>'; });
  r1+='</tr><tr class="r2"><th class="cfix col-sd">SOUS-DOMAINES</th>';
  WEEKS.forEach(function(w){ r1+='<th>S'+w.sem+'</th>'; });
  return r1+'</tr>';
}
function isoWeekMondayStr(year, week){
  const jan4=new Date(Date.UTC(year,0,4));
  const dow=(jan4.getUTCDay()+6)%7;
  const wk1=new Date(jan4); wk1.setUTCDate(jan4.getUTCDate()-dow);
  const d=new Date(wk1); d.setUTCDate(wk1.getUTCDate()+(week-1)*7+3); // jeudi : toujours dans l'annee ISO
  return d.getUTCFullYear()+'-'+String(d.getUTCMonth()+1).padStart(2,'0')+'-'+String(d.getUTCDate()).padStart(2,'0');
}
function isoYearOf(dateStr){
  if(!dateStr) return 0;
  const d=new Date(String(dateStr).substring(0,10)+'T00:00:00Z'); if(isNaN(d)) return 0;
  const day=(d.getUTCDay()+6)%7; d.setUTCDate(d.getUTCDate()-day+3); // jeudi de la semaine ISO
  return d.getUTCFullYear();
}
function isoWeekOf(dateStr){
  if(!dateStr) return 0;
  const d=new Date(String(dateStr).substring(0,10)+'T00:00:00Z'); if(isNaN(d)) return 0;
  const day=(d.getUTCDay()+6)%7; d.setUTCDate(d.getUTCDate()-day+3);
  const firstThu=new Date(Date.UTC(d.getUTCFullYear(),0,4));
  return 1+Math.round(((d-firstThu)/86400000-3+((firstThu.getUTCDay()+6)%7))/7);
}
function openTrigger(idprog){
  apiPost({action:'get',idprogramme:idprog}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',text:res.message||'Introuvable',confirmButtonColor:'#23408F'}); return; }
    // Un acte de supervision ne peut etre declenche que sur un programme
    // officiellement valide (document signe par le Directeur General).
    if((res.programme||{}).statut !== 'valide'){
      Swal.fire({icon:'info',title:'Programme non valide',
        html:'Ce programme est encore en <strong>brouillon</strong>.<br>'
            +'Validez-le en joignant le programme signe par le Directeur General avant de declencher un acte de supervision.',
        confirmButtonColor:'#23408F'});
      return;
    }
    CURRENT=res.programme; WEEKS=res.weeks||[]; NBSEM=res.nb_semaines||52; OPTS=res.options||{rags:[],sites:[],sousdomaines:[],pays:[],operateurs:[]};
    MODE=(CURRENT.mode_cible==='operateur')?'operateur':'site'; OPERATEURS=OPTS.operateurs||[];
    $('#trgTitre').text(CURRENT.titre);
    $('#matrixHeadT').html(headHtml());
    loadTriggered(idprog, function(){
      renderTrigger((res.matrice&&res.matrice.groupes)?res.matrice.groupes:[]);
      $('#viewList').hide(); $('#viewEditor').hide(); $('#viewTrigger').show(); window.scrollTo(0,0);
    });
  });
}
function renderTrigger(groupes){
  $('#matrixT tbody').remove();
  if(!groupes||!groupes.length) groupes=[{rubrique:'',items:[]}];
  const html=groupes.map(function(g){
    let head='<tr class="grp-row"><td class="cfix col-sd" style="font-weight:700">'+esc(g.rubrique||'')+'</td><td class="cfix-rag col-rag"></td>';
    WEEKS.forEach(function(){ head+='<td class="wk grpcell"></td>'; });
    head+='</tr>';
    const items=(g.items&&g.items.length)?g.items:[];
    const body=items.map(function(it){
      let h='<tr><td class="cfix col-sd">'+esc(it.sous_domaine||'')+'</td><td class="cfix-rag col-rag" style="text-align:center">'+esc(it.rag||'')+'</td>';
      WEEKS.forEach(function(w){
        const _raw=(it.cellules&&(it.cellules[String(w.sem)]||it.cellules[w.sem]))||''; const v=cellCode(_raw); const _acte=cellActe(_raw); const _coul=cellCouleur(_raw);
        if(v){
          const t=trigOf(v,w.sem); const cls=t?(' tc-'+t.statut):'';
          if(t){
            // Deja declenche : la couleur de statut prime sur la couleur d'acte
            const ttl='Acte declenche - Statut : '+(STAT_LBL[t.statut]||'')+(t.num?(' (N '+String(t.num)+')'):'')+'. Non replanifiable.';
            h+='<td class="wk trig-cell'+cls+'" style="cursor:not-allowed" data-sem="'+w.sem+'" data-val="'+esc(v)+'" title="'+esc(ttl)+'">'+esc(v)+'</td>';
          } else {
            // Non declenche : on applique la couleur de l'acte du programme (bleu par defaut, jaune si non programmee)
            const jaune = (_coul==='jaune') || (_acte==='inspection_non_programmee');
            const bg = jaune ? '#F3C300' : '#23408F';
            const fg = jaune ? '#000' : '#fff';
            const fw = jaune ? '800' : '700';
            const acteTitle = _acte ? (ACTE_TXT[_acte]||'') : 'Acte a definir';
            h+='<td class="wk trig-cell'+cls+'" data-sem="'+w.sem+'" data-val="'+esc(v)+'" data-acte="'+esc(_acte||'')+'" '
              +'style="cursor:pointer;background:'+bg+';color:'+fg+';font-weight:'+fw+'" '
              +'title="'+esc(acteTitle)+' - Cliquer pour declencher">'+esc(v)+'</td>';
          }
        } else { h+='<td class="wk" style="background:#f7f9fc"></td>'; }
      });
      return h+'</tr>';
    }).join('');
    return '<tbody>'+head+body+'</tbody>';
  }).join('');
  $('#matrixT').append(html);
  renderTriggerLegendes(groupes);
}
/* Legendes de la vue declenchement : operateurs/sites + actes (bleu/jaune).
   La legende des statuts est deja affichee en haut de la vue. */
function renderTriggerLegendes(groupes){
  const usedCodes=new Set(), usedActes=new Set();
  (groupes||[]).forEach(function(g){ (g.items||[]).forEach(function(it){
    Object.keys(it.cellules||{}).forEach(function(k){
      const raw=it.cellules[k]; const code=cellCode(raw); const acte=cellActe(raw);
      if(code) usedCodes.add(code); if(acte) usedActes.add(acte);
    });
  }); });
  let html='';
  // Legende operateurs / sites
  if(usedCodes.size){
    const map={};
    if(MODE==='operateur'){ (OPERATEURS||[]).forEach(function(op){ const t=(op.trigrorganisme||'').trim(); if(t) map[t]=op.nomorga||''; }); }
    else { (OPTS.sites||[]).forEach(function(s){ map[s.indicateur_oaci]=s.nomsite||''; }); }
    let items=[...usedCodes].sort().map(function(c){
      return '<span class="me-3" style="white-space:nowrap"><strong style="color:#D32F2F">'+esc(c)+'</strong> : '+esc(map[c]||'-')+'</span>';
    }).join('');
    html+='<div class="mb-2"><div style="font-weight:700;color:#23408F;font-size:.8rem;margin-bottom:3px">Legende des '+(MODE==='operateur'?'operateurs':'sites')+'</div><div style="font-size:.78rem">'+items+'</div></div>';
  }
  // Legende des actes de supervision (bleu / jaune)
  const ordre=['audit','inspection_programmee','inspection_non_programmee','demonstration','test','investigation'];
  const shown=ordre.filter(function(a){return usedActes.has(a);});
  let acteCells='';
  if(shown.length){
    shown.forEach(function(a){
      const jaune=(a==='inspection_non_programmee');
      const bg=jaune?'#F3C300':'#23408F', col=jaune?'#000':'#fff', fw=jaune?'800':'700';
      acteCells+='<span class="me-2" style="display:inline-block;padding:3px 12px;border-radius:4px;background:'+bg+';color:'+col+';font-weight:'+fw+';font-size:.76rem;margin-bottom:3px">'+esc(ACTE_TXT[a]||a)+'</span>';
    });
  } else {
    acteCells+='<span class="me-2" style="display:inline-block;padding:3px 12px;border-radius:4px;background:#23408F;color:#fff;font-weight:700;font-size:.76rem">Acte de supervision (bleu par defaut)</span>';
    acteCells+='<span class="me-2" style="display:inline-block;padding:3px 12px;border-radius:4px;background:#F3C300;color:#000;font-weight:800;font-size:.76rem">Inspection non programmee (jaune)</span>';
  }
  html+='<div><div style="font-weight:700;color:#23408F;font-size:.8rem;margin-bottom:3px">Legende des actes de supervision</div><div>'+acteCells+'</div></div>';
  $('#trgLegendes').html(html);
}
$(document).on('click','.act-trig',function(){ openTrigger($(this).data('id')); });
$('#btnRefreshStatuts').on('click',function(){ if(CURRENT&&CURRENT.idprogramme) openTrigger(CURRENT.idprogramme); });
$('#btnBackList2').on('click',function(){ $('#viewTrigger').hide(); $('#viewList').show(); loadList(); });
$(document).on('click','.trig-cell',function(){
  const v=$(this).data('val'), sem=$(this).data('sem');
  const done=trigOf(v,sem);
  if(done){
    Swal.fire({icon:'info',title:'Acte deja programme',
      html:'La cible <strong>'+esc(v)+'</strong> (semaine S'+sem+') possede deja un acte : <strong>'+esc(STAT_LBL[done.statut]||'')+'</strong>'+(done.num?(' (N&deg; '+esc(done.num)+')'):'')+'.<br>Il n\'est plus possible de la declencher a nouveau.',
      confirmButtonColor:'#23408F'});
    return;
  }
  let idsite='', idorga='';
  if(MODE==='operateur'){ const op=(OPERATEURS||[]).find(function(o){return String((o.trigrorganisme||'').trim())===String(v);}); idorga=op?op.idorga:''; }
  else { const sx=(OPTS.sites||[]).find(function(x){return String(x.indicateur_oaci)===String(v);}); idsite=sx?sx.idsite:''; }
  TRIGCTX={value:v, sem:sem, idsite:idsite, idorga:idorga, type:''};
  $('#actNature').html(esc(v)+' &middot; Semaine S'+sem+' &middot; '+(MODE==='operateur'?'Operateur':'Site'));
  // Mise en evidence de l'acte prevu au programme (le CI peut garder ou changer)
  const acteProgramme=$(this).data('acte')||'';
  $('#actModal .nat-tile').removeClass('prevu');
  if(acteProgramme){
    $('#actModal .nat-tile[data-type="'+acteProgramme+'"]').addClass('prevu');
    $('#actNature').append('<div class="mt-2 small" style="color:#1E9C4B"><i class="bi bi-check-circle-fill me-1"></i>Acte prevu au programme : <strong>'+esc(ACTE_TXT[acteProgramme]||acteProgramme)+'</strong> (mis en evidence, modifiable).</div>');
  }
  actModal().show();
});
$(document).on('click','#actModal .nat-tile',function(){
  if(!TRIGCTX) return;
  TRIGCTX.type=$(this).data('type');
  actModal().hide();
  $('#cadreNaturePsc').text(NATLBL[TRIGCTX.type]||TRIGCTX.type);
  $('input[name="cadre_psc"]').prop('checked',false); $('#cadreModalPsc .cadre-opt').removeClass('sel');
  $('#cadreContinuePsc').prop('disabled',true);
  cadreModalP().show();
});
$(document).on('change','input[name="cadre_psc"]',function(){ $('#cadreModalPsc .cadre-opt').removeClass('sel'); $(this).closest('.cadre-opt').addClass('sel'); $('#cadreContinuePsc').prop('disabled',false); });
$('#cadreContinuePsc').on('click',function(){
  const cadre=$('input[name="cadre_psc"]:checked').val(); if(!TRIGCTX||!TRIGCTX.type||!cadre) return;
  const dprev=isoWeekMondayStr(parseInt(CURRENT.annee,10), parseInt(TRIGCTX.sem,10));
  let url=AGAI_BASE+'/declenchement?type='+encodeURIComponent(TRIGCTX.type)+'&cadre='+encodeURIComponent(cadre)
    +'&idprog='+encodeURIComponent(CURRENT.idprogramme)+'&dprev='+encodeURIComponent(dprev)+'&idtypeorga='+encodeURIComponent(CURRENT.idtypeorga||'');
  if(MODE==='operateur'){ if(TRIGCTX.idorga) url+='&idorga='+encodeURIComponent(TRIGCTX.idorga); }
  else { if(TRIGCTX.idsite) url+='&idsite='+encodeURIComponent(TRIGCTX.idsite); }
  window.location=url;
});

/* ============ ETAT DU PROGRAMME (brouillon / valide) ============ */
function refreshStatutUI(){
  const v=(CURRENT&&CURRENT.statut==='valide');
  $('#progStatut').text(v?'valide':'brouillon').css('color', v?'#157a3a':'#9a7d00');
  $('#lblStatut').text(v?'Repasser en brouillon':'Valider');
  $('#btnToggleStatut').toggleClass('btn-outline-success', !v).toggleClass('btn-outline-warning', v);
}
$('#btnToggleStatut').on('click',function(){
  if(!CURRENT) return;
  if(!IS_CI){ Swal.fire({icon:'info',text:"Seuls l'administrateur et le chef inspecteur peuvent valider un programme.",confirmButtonColor:'#23408F'}); return; }

  // Validation : elle passe obligatoirement par le depot du programme signe par le DG.
  if(CURRENT.statut !== 'valide'){
    SIGNE_CTX = { id: CURRENT.idprogramme, titre: CURRENT.titre || '', aFic: !!CURRENT.fichier_signe, ds: String(CURRENT.date_signature||'') };
    $('#sgTitre').text(SIGNE_CTX.titre);
    $('#sg_fichier').val('');
    $('#sg_date').val(SIGNE_CTX.ds ? SIGNE_CTX.ds.substring(0,10) : '');
    if(SIGNE_CTX.aFic){
      $('#sgActuel').show();
      $('#sgDateTxt').text(SIGNE_CTX.ds ? ('Signe le '+fmtDateFr(SIGNE_CTX.ds)) : 'Date de signature non renseignee');
    } else { $('#sgActuel').hide(); }
    new bootstrap.Modal('#modalSigne').show();
    return;
  }

  const next='brouillon';
  Swal.fire({
    title:(next==='valide')?'Valider ce programme ?':'Repasser en brouillon ?',
    text:(next==='valide')?'Le programme sera marque comme valide (version officielle).':'Le programme redeviendra modifiable en brouillon.',
    icon:'question', showCancelButton:true, cancelButtonText:'Annuler',
    confirmButtonColor:(next==='valide')?'#1E9C4B':'#E8890C'
  }).then(function(r){
    if(!r.isConfirmed) return;
    apiPost({action:'save',idprogramme:CURRENT.idprogramme,matrice:JSON.stringify(getMatrixData()),revision:$('#progRev').text(),statut:next}).done(function(res){
      if(!res.success){ Swal.fire({icon:'error',text:res.message||'Echec',confirmButtonColor:'#23408F'}); return; }
      CURRENT.statut=next; refreshStatutUI();
      Swal.fire({icon:'success',title:(next==='valide')?'Programme valide':'Repasse en brouillon',timer:1300,showConfirmButton:false});
    });
  });
});

/* ============ ANALYSE : PROGRAMME PSC vs AUDITS ============ */
function computeAnalyse(groupes){
  const an=parseInt(CURRENT.annee,10);
  const semMois={}; WEEKS.forEach(function(w){ semMois[w.sem]=w.mois; });
  const res={prog:0, decl:0, parStatut:{1:0,2:0,3:0,4:0,5:0,6:0,7:0},
             parCible:{}, parMois:{}, horsProg:0, cellules:[]};
  (groupes||[]).forEach(function(g){
    (g.items||[]).forEach(function(it){
      WEEKS.forEach(function(w){
        const _raw=(it.cellules&&(it.cellules[String(w.sem)]||it.cellules[w.sem]))||''; const v=cellCode(_raw); const _acte=cellActe(_raw);
        if(!v) return;
        const code=String(v).trim().toUpperCase();
        const t=trigOf(v,w.sem);
        res.prog++;
        const mois=semMois[w.sem]||'-';
        if(!res.parCible[code]) res.parCible[code]={prog:0,decl:0,eff:0};
        if(!res.parMois[mois])  res.parMois[mois]={prog:0,decl:0,eff:0};
        res.parCible[code].prog++; res.parMois[mois].prog++;
        if(t){
          res.decl++; res.parStatut[t.statut]=(res.parStatut[t.statut]||0)+1;
          res.parCible[code].decl++; res.parMois[mois].decl++;
          if(t.statut===3){ res.parCible[code].eff++; res.parMois[mois].eff++; }
        }
        res.cellules.push({code:code, sem:w.sem, statut:t?t.statut:0});
      });
    });
  });
  // Actes de l'annee non rattaches a une cellule du programme (ex: inopines)
  const vus={}; res.cellules.forEach(function(c){ if(c.statut) vus[c.code+'|'+c.sem]=1; });
  Object.keys(PSC_STATUS||{}).forEach(function(k){
    const p=k.split('|'); if(parseInt(p[0],10)!==an) return;
    if(!vus[p[1]+'|'+p[2]]) res.horsProg++;
  });
  res.eff=res.parStatut[3]||0;
  res.tauxCouv=res.prog? Math.round(res.decl*1000/res.prog)/10 : 0;
  res.tauxReal=res.prog? Math.round(res.eff*1000/res.prog)/10 : 0;
  return res;
}
function kpiCard(ic,cls,val,lbl){
  return '<div class="col-6 col-md-4 col-lg-2"><div class="stat-card d-flex align-items-center gap-2 p-3">'
    +'<div class="stat-ic '+cls+'"><i class="bi '+ic+'"></i></div>'
    +'<div><div style="font-size:1.35rem;font-weight:800;line-height:1">'+val+'</div>'
    +'<div class="text-muted" style="font-size:.76rem">'+lbl+'</div></div></div></div>';
}
function barRow(lbl,color,n,total){
  const pc=total? Math.round(n*100/total):0;
  return '<tr><td style="width:210px">'+esc(lbl)+'</td>'
    +'<td><div style="background:#eef2f7;border-radius:50px;height:16px;overflow:hidden">'
    +'<div style="background:'+color+';width:'+pc+'%;height:100%"></div></div></td>'
    +'<td style="width:90px;text-align:right;font-weight:700">'+n+' ('+pc+'%)</td></tr>';
}
function renderAnalyse(a){
  let h='<div class="row g-3 mb-3">'
    +kpiCard('bi-calendar3-week','ic-blue',a.prog,'Actes programmes')
    +kpiCard('bi-rocket-takeoff','ic-gold',a.decl,'Actes declenches')
    +kpiCard('bi-check2-circle','ic-green',a.eff,'Actes effectues')
    +kpiCard('bi-hourglass-split','ic-red',Math.max(a.prog-a.decl,0),'Non declenches')
    +kpiCard('bi-percent','ic-blue',a.tauxCouv+' %','Taux de couverture')
    +kpiCard('bi-graph-up','ic-green',a.tauxReal+' %','Taux de realisation')
    +'</div>';

  h+='<div class="row g-3"><div class="col-lg-6"><div class="card-anac p-3">'
    +'<h6 class="fw-bold mb-3" style="color:#23408F">Repartition par statut</h6><table class="table table-sm align-middle mb-0">';
  [[1,'Planifie','#23408F'],[2,'Reporte','#E8890C'],[3,'Effectue','#1E9C4B'],[4,'Suspendu','#D32F2F'],[5,'A surveiller','#D32F2F'],[6,'Annule','#D32F2F'],[7,'Inopine','#7A8798']]
    .forEach(function(x){ h+=barRow(x[1],x[2],a.parStatut[x[0]]||0,a.prog||1); });
  h+='</table><div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>'+a.horsProg+' acte(s) de l\'annee hors programme (inopines ou hors matrice).</div></div></div>';

  h+='<div class="col-lg-6"><div class="card-anac p-3">'
    +'<h6 class="fw-bold mb-3" style="color:#23408F">Execution par '+(MODE==='operateur'?'operateur':'site')+'</h6>'
    +'<div class="table-responsive" style="max-height:320px;overflow:auto"><table class="table table-sm align-middle mb-0">'
    +'<thead><tr><th>Cible</th><th class="text-center">Prog.</th><th class="text-center">Decl.</th><th class="text-center">Eff.</th><th class="text-center">Taux</th></tr></thead><tbody>';
  Object.keys(a.parCible).sort().forEach(function(k){
    const c=a.parCible[k]; const tx=c.prog?Math.round(c.eff*100/c.prog):0;
    h+='<tr><td class="fw-bold">'+esc(k)+'</td><td class="text-center">'+c.prog+'</td><td class="text-center">'+c.decl+'</td>'
      +'<td class="text-center">'+c.eff+'</td><td class="text-center"><span class="badge-soft '+(tx>=75?'b-green':(tx>=40?'b-gold':'b-red'))+'">'+tx+' %</span></td></tr>';
  });
  if(!Object.keys(a.parCible).length) h+='<tr><td colspan="5" class="text-center text-muted">Aucune cible programmee</td></tr>';
  h+='</tbody></table></div></div></div></div>';

  h+='<div class="card-anac p-3 mt-3"><h6 class="fw-bold mb-3" style="color:#23408F">Execution par mois</h6>'
    +'<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Mois</th>';
  const mois=Object.keys(a.parMois);
  mois.forEach(function(m){ h+='<th class="text-center">'+esc(m)+'</th>'; });
  h+='</tr></thead><tbody><tr><td class="fw-bold">Programmes</td>';
  mois.forEach(function(m){ h+='<td class="text-center">'+a.parMois[m].prog+'</td>'; });
  h+='</tr><tr><td class="fw-bold">Declenches</td>';
  mois.forEach(function(m){ h+='<td class="text-center">'+a.parMois[m].decl+'</td>'; });
  h+='</tr><tr><td class="fw-bold">Effectues</td>';
  mois.forEach(function(m){ h+='<td class="text-center">'+a.parMois[m].eff+'</td>'; });
  h+='</tr></tbody></table></div></div>';
  $('#anaBody').html(h);
}
function openAnalyse(idprog){
  apiPost({action:'get',idprogramme:idprog}).done(function(res){
    if(!res.success){ Swal.fire({icon:'error',text:res.message||'Introuvable',confirmButtonColor:'#23408F'}); return; }
    CURRENT=res.programme; WEEKS=res.weeks||[]; NBSEM=res.nb_semaines||52; OPTS=res.options||{rags:[],sites:[],sousdomaines:[],pays:[],operateurs:[]};
    MODE=(CURRENT.mode_cible==='operateur')?'operateur':'site'; OPERATEURS=OPTS.operateurs||[];
    $('#anaTitre').text('ANALYSE : '+CURRENT.titre);
    $('#anaMeta').html('<i class="bi bi-calendar3 me-1"></i>Annee '+esc(CURRENT.annee)
      +' &nbsp;|&nbsp; <i class="bi bi-diagram-3 me-1"></i>Mode : '+(MODE==='operateur'?'Operateur':'Site')
      +' &nbsp;|&nbsp; <i class="bi bi-patch-check me-1"></i>Etat : '+esc(CURRENT.statut||'brouillon'));
    renderAnalyse(computeAnalyse((res.matrice&&res.matrice.groupes)?res.matrice.groupes:[]));
    $('#viewList').hide(); $('#viewEditor').hide(); $('#viewTrigger').hide(); $('#viewAnalyse').show(); window.scrollTo(0,0);
  });
}
$(document).on('click','.act-ana',function(){ openAnalyse($(this).data('id')); });
$(document).on('click','.act-voir-signe',function(){
  const id=$(this).data('id');
  const url=AGAI_BASE+'/api/psc?action=serve_signe&idprogramme='+encodeURIComponent(id);
  $('#pscPdfFrame').attr('src', url);
  $('#pscPdfDl').attr('href', url).attr('download','Programme_PSC_signe.pdf');
  $('#pscPdfPrint').data('url', url);
  new bootstrap.Modal('#modalPdfPsc').show();
});

/* ============================================================
 *  PROGRAMME SIGNE PAR LE DIRECTEUR GENERAL
 *  Depot, consultation et retrait du document officiel (PDF).
 *  Le fichier est stocke hors zone publique et servi par l'endpoint.
 * ============================================================ */
let SIGNE_CTX = null;

$(document).on('click','.act-signe',function(){
  SIGNE_CTX = {
    id:    $(this).data('id'),
    titre: $(this).data('t') || '',
    aFic:  String($(this).data('fic')) === '1',
    ds:    String($(this).data('ds') || '')
  };
  $('#sgTitre').text(SIGNE_CTX.titre);
  $('#sg_fichier').val('');
  $('#sg_date').val(SIGNE_CTX.ds ? SIGNE_CTX.ds.substring(0,10) : '');
  if(SIGNE_CTX.aFic){
    $('#sgActuel').show();
    $('#sgDateTxt').text(SIGNE_CTX.ds ? ('Signe le '+fmtDateFr(SIGNE_CTX.ds)) : 'Date de signature non renseignee');
  } else {
    $('#sgActuel').hide();
  }
  new bootstrap.Modal('#modalSigne').show();
});

function fmtDateFr(d){
  const s=String(d||'').substring(0,10);
  if(!/^\d{4}-\d{2}-\d{2}$/.test(s)) return s||'-';
  return s.substring(8,10)+'/'+s.substring(5,7)+'/'+s.substring(0,4);
}

/* Depot : envoi multipart (le fichier ne peut pas transiter en simple POST) */
$('#btnSaveSigne').on('click',function(){
  if(!SIGNE_CTX) return;
  const inp = document.getElementById('sg_fichier');
  const fic = (inp && inp.files && inp.files[0]) ? inp.files[0] : null;
  if(!fic){
    Swal.fire({icon:'warning',title:'Document requis',text:'Choisissez le programme signe au format PDF.',
      confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}});
    return;
  }
  if(!/\.pdf$/i.test(fic.name)){
    Swal.fire({icon:'error',title:'Format invalide',text:'Seuls les fichiers PDF sont acceptes.',
      confirmButtonColor:'#D32F2F',customClass:{container:'swal-over-modal'}});
    return;
  }
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('action', 'upload_signe');
  fd.append('idprogramme', SIGNE_CTX.id);
  fd.append('date_signature', $('#sg_date').val() || '');
  fd.append('fichier_signe', fic);

  const btn=$(this); btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Envoi...');
  $.ajax({url:API, type:'POST', data:fd, processData:false, contentType:false, dataType:'json'})
   .always(function(){ btn.prop('disabled',false).html('<i class="bi bi-upload me-1"></i>Enregistrer'); })
   .done(function(res){
      if(!res || !res.success){
        Swal.fire({icon:'error',title:'Echec',text:(res&&res.message)||'Enregistrement impossible.',
          confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}});
        return;
      }
      bootstrap.Modal.getInstance('#modalSigne').hide();
      // Le depot du document signe vaut validation du programme
      if(CURRENT && String(CURRENT.idprogramme) === String(SIGNE_CTX.id)){
        CURRENT.statut = 'valide';
        CURRENT.fichier_signe = res.fichier || '1';
        CURRENT.date_signature = res.date_signature || '';
        refreshStatutUI();
      }
      Swal.fire({icon:'success',title:'Programme valide',
        html:'Le programme signe par le Directeur General a ete enregistre.<br>Le programme passe a l\'etat <strong>valide</strong>.',
        timer:2400,showConfirmButton:false});
      loadList();
   })
   .fail(function(){
      Swal.fire({icon:'error',title:'Echec de la requete',
        text:'Verifiez la taille du fichier et les limites upload_max_filesize / post_max_size.',
        confirmButtonColor:'#23408F',customClass:{container:'swal-over-modal'}});
   });
});

/* Consultation dans une fenetre dediee */
$('#btnVoirSigne').on('click',function(){
  if(!SIGNE_CTX) return;
  const url = AGAI_BASE+'/api/psc?action=serve_signe&idprogramme='+encodeURIComponent(SIGNE_CTX.id);
  $('#pscPdfFrame').attr('src', url);
  $('#pscPdfDl').attr('href', url).attr('download','Programme_PSC_signe.pdf');
  $('#pscPdfPrint').data('url', url);
  new bootstrap.Modal('#modalPdfPsc').show();
});
$('#pscPdfPrint').on('click',function(){
  const w=window.open($(this).data('url'), '_blank');
  if(w){ w.addEventListener('load', function(){ try{ w.print(); }catch(e){} }); }
});
$('#modalPdfPsc').on('hidden.bs.modal', function(){ $('#pscPdfFrame').attr('src',''); });

/* Retrait du document */
$('#btnRetirerSigne').on('click',function(){
  if(!SIGNE_CTX) return;
  Swal.fire({
    title:'Retirer le programme signe ?',
    text:'Le document sera supprime du serveur. Cette action est definitive.',
    icon:'warning', showCancelButton:true, cancelButtonText:'Annuler',
    confirmButtonColor:'#D32F2F', confirmButtonText:'Retirer',
    customClass:{container:'swal-over-modal'}
  }).then(function(r){
    if(!r.isConfirmed) return;
    apiPost({action:'delete_signe', idprogramme:SIGNE_CTX.id}).done(function(res){
      if(!res.success){ Swal.fire({icon:'error',text:res.message||'Echec',confirmButtonColor:'#23408F'}); return; }
      bootstrap.Modal.getInstance('#modalSigne').hide();
      Swal.fire({icon:'success',title:'Document retire',timer:1300,showConfirmButton:false});
      loadList();
    });
  });
});

/* Les fenetres imbriquees passent au premier plan */
$('#modalPdfPsc').on('show.bs.modal', function(){
  const nb=$('.modal.show').length;
  $(this).css('z-index', 1060+(nb+1)*20);
  setTimeout(function(){ $('.modal-backdrop').last().css('z-index', 1060+(nb+1)*20-10); },0);
});
$('#btnBackListA').on('click',function(){ $('#viewAnalyse').hide(); $('#viewList').show(); loadList(); });
$('#btnAnaPdf').on('click',function(){
  const w=window.open('','_blank','width=1200,height=800');
  w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Analyse PSC</title>'
    +'<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
    +'<style>@page{size:A4 landscape;margin:8mm}body{font-family:Candara,Arial;padding:10px}'
    +'h2{color:#23408F;text-align:center}.stat-card{border:1px solid #e6ebf3;border-radius:10px}</style></head><body>'
    +'<h2>'+esc($('#anaTitre').text())+'</h2><div style="text-align:center;margin-bottom:10px">'+$('#anaMeta').html()+'</div>'
    +$('#anaBody').html()+'</body></html>');
  w.document.close(); w.focus(); setTimeout(function(){ w.print(); }, 900);
});

loadList();
</script>