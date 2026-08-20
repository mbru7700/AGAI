<?php
/**
 * Mes audits - Revues documentaires
 * - Design uniforme (KPI, en-tetes bleu ANAC, filtres Select2)
 * - Bouton Revue : actif si nb_revues < nb_equipe, grisse si complet (y/y)
 * - RA voit toutes les revues, y compris la sienne (impact y/y global)
 * - Chaque inspecteur voit ses propres revues uniquement
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('audits');

$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$uid       = (int) ($_SESSION['user_id'] ?? 0);
$pageTitle = 'Revue documentaire';
$active    = 'mes-audits';
$isCI      = in_array($role, ['admin','chef_inspecteur','consultant'], true);
$titre     = 'Revue documentaire';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00;} .ic-red{background:rgba(211,47,47,.10);color:#D32F2F;}
.ic-dark{background:rgba(44,62,80,.09);color:#2C3E50;}
.stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
.filter-bar{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:13px 16px;box-shadow:0 1px 2px rgba(16,30,54,.04);}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;}
table.tbl thead th{background:#23408F;color:#fff;font-size:.71rem;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border:none;font-weight:600;white-space:nowrap;}
table.tbl tbody td{padding:10px 12px;border-bottom:1px solid #f1f4f9;vertical-align:middle;font-size:.86rem;}
table.tbl tbody tr:hover{background:#fafcff;}
.empty{padding:36px;text-align:center;color:#9aa7bd;}
/* Statuts */
.s-badge{display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;}
.s1{background:#e8f0fe;color:#23408F;} .s2{background:#fff3cd;color:#856404;}
.s3{background:#d1e7dd;color:#0a5c36;} .s4{background:#f8d7da;color:#842029;}
.s5{background:#f0e6ff;color:#5a189a;} .s6{background:#e2e3e5;color:#383d41;}
.s7{background:#cfe2ff;color:#084298;}
/* Progression revue */
.prog-wrap{display:flex;align-items:center;gap:6px;white-space:nowrap;}
.prog-bar{width:55px;height:7px;background:#eef1f6;border-radius:4px;overflow:hidden;flex:0 0 auto;}
.prog-fill{height:100%;border-radius:4px;transition:width .3s;}
.prog-full{background:#1E9C4B;} .prog-part{background:#F3C300;} .prog-none{background:#dee2e6;}
.prog-lbl{font-size:.78rem;font-weight:700;}
.prog-lbl.complete{color:#1E9C4B;} .prog-lbl.partial{color:#b58a00;} .prog-lbl.empty2{color:#9aa7bd;}
/* Boutons revue */
.btn-revue-saisir{background:linear-gradient(135deg,#23408F,#1b3576);color:#fff !important;border:none;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s;cursor:pointer;}
.mode-card{background:#fff;border:2px solid #e6ebf3;border-radius:14px;padding:20px 18px;height:100%;cursor:pointer;transition:.16s;text-align:center;position:relative;}
.mode-card:hover{border-color:#23408F;transform:translateY(-3px);box-shadow:0 10px 24px rgba(35,64,143,.14);}
.mode-card-ic{width:58px;height:58px;margin:0 auto 12px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.7rem;color:#fff;}
.mc-blue{background:linear-gradient(135deg,#23408F,#1b3576);}
.mc-red{background:linear-gradient(135deg,#D32F2F,#b02525);}
.mode-card-t{font-weight:800;color:#2C3E50;font-size:1.05rem;margin-bottom:6px;}
.mode-card-s{font-size:.82rem;color:#6b7a90;line-height:1.45;min-height:52px;}
.mode-card-go{margin-top:12px;font-weight:700;font-size:.85rem;color:#23408F;}
.mode-card:hover .mode-card-go{text-decoration:underline;}
.mode-card-lock{display:none;margin-top:10px;font-size:.75rem;font-weight:700;color:#D32F2F;}
.mode-card.locked{opacity:.5;cursor:not-allowed;filter:grayscale(.4);}
.mode-card.locked:hover{transform:none;box-shadow:none;border-color:#e6ebf3;}
.mode-card.locked .mode-card-go{display:none;}
.mode-card.locked .mode-card-lock{display:block;}
.btn-revue-saisir:hover{background:linear-gradient(135deg,#1b3576,#13276a);transform:translateY(-1px);box-shadow:0 3px 8px rgba(35,64,143,.3);}
.btn-revue-continuer{background:linear-gradient(135deg,#b58a00,#9a7500);color:#fff !important;border:none;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s;}
.btn-revue-continuer:hover{background:linear-gradient(135deg,#9a7500,#7d5f00);transform:translateY(-1px);box-shadow:0 3px 8px rgba(181,138,0,.3);}
.btn-revue-consulter{background:#f5f7fa;color:#5b6b85 !important;border:1px solid #d0d7e3;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
.btn-revue-consulter:hover{background:#eef1f6;}
.ra-tag{background:#D32F2F;color:#fff;font-size:.68rem;padding:.1rem .4rem;border-radius:10px;margin-left:4px;font-weight:700;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-clipboard-check me-2" style="color:var(--anac-primary)"></i><?php echo Security::escape($titre); ?></h1>
    <div class="sub">Suivi des actes de supervision et gestion des revues documentaires.</div>
  </div>
</div>

<div class="fnc-card mb-3" style="border-left:4px solid #1E9C4B;background:#fff;border:1px solid #eef1f6;border-radius:12px">
  <div id="guideRevueToggle" style="cursor:pointer;display:flex;align-items:center;gap:8px;padding:11px 15px;user-select:none">
    <i class="bi bi-info-circle" style="color:#1E9C4B"></i>
    <b style="color:#1E9C4B">Comment fonctionne la revue documentaire ?</b>
    <i class="bi bi-chevron-down ms-auto" id="guideRevueChevron" style="color:#1E9C4B;transition:transform .2s"></i>
  </div>
  <div id="guideRevueBody" style="display:none;padding:0 15px 13px;font-size:.83rem;color:#1e3a5f;line-height:1.6">
    Cliquez sur <b>Traiter la revue</b> pour, au choix, <b>saisir</b> le formulaire (6 rubriques) ou <b>joindre</b> un PDF.
    Chaque inspecteur redige sa propre revue ; les autres la consultent seulement.
    <div class="mt-2 p-2" style="background:#fff8e6;border-left:4px solid #F3C300;border-radius:6px;color:#7a5c00">
      <i class="bi bi-exclamation-triangle me-1"></i><b>Important :</b> les membres de l'equipe doivent <b>s'accorder sur le mode de traitement</b> de la revue (saisie <b>ou</b> PDF joint).
      Les inspecteurs de l'equipe traitent leur revue <b>avant</b> le Responsable d'Audit (RA). Des qu'un mode est choisi, <b>toute l'equipe suit le meme mode</b>.
      Quand le RA traite sa revue, l'acte est <b>cloture</b> : plus aucune saisie n'est possible, uniquement la consultation et l'impression PDF.
    </div>
  </div>
</div>

<!-- KPI masquable -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsLbl">Afficher les statistiques</span>
  </button>
</div>
<div id="statsPanel" class="mb-3" style="display:none">
  <div class="row g-3">
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-clipboard-data-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Total audits</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-calendar-check-fill"></i></div><div><div class="stat-num" id="st_plan" style="color:#23408F">0</div><div class="stat-lbl">Planifies</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-num" id="st_eff" style="color:#1E9C4B">0</div><div class="stat-lbl">Effectues</div></div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card" style="cursor:pointer" id="cardAvecRevue" title="Voir les audits avec revue complete">
      <div class="stat-ic ic-green"><i class="bi bi-file-earmark-check-fill"></i></div>
      <div><div class="stat-num" id="st_rev_ok" style="color:#1E9C4B">0</div><div class="stat-lbl">Revues completes</div></div>
    </div></div>
    <div class="col-6 col-md-2"><div class="stat-card" style="cursor:pointer" id="cardSansRevue" title="Voir les audits sans revue">
      <div class="stat-ic ic-gold"><i class="bi bi-file-earmark-text"></i></div>
      <div><div class="stat-num" id="st_rev_part" style="color:#b58a00">0</div><div class="stat-lbl">Revues en cours</div></div>
    </div></div>
    <div class="col-6 col-md-2"><div class="stat-card" style="cursor:pointer" id="cardNonRevue" title="Voir les audits sans aucune revue">
      <div class="stat-ic ic-red"><i class="bi bi-file-earmark-x"></i></div>
      <div><div class="stat-num" id="st_rev_non" style="color:#D32F2F">0</div><div class="stat-lbl">Revues non saisies</div></div>
    </div></div>
  </div>
</div>

<!-- Filtres -->
<div class="filter-bar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Statut</label>
      <select id="fStatut" style="width:100%">
        <option value="">Tous les statuts</option>
        <option value="1">Planifie</option><option value="2">Reporte</option>
        <option value="3">Effectue</option><option value="4">Suspendu</option>
        <option value="5">A surveiller</option><option value="6">Annule</option><option value="7">Inopine</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Nature</label>
      <select id="fType" style="width:100%">
        <option value="">Toutes les natures</option>
        <option value="audit">Audit</option>
        <option value="inspection_programmee">Inspection programmee</option>
        <option value="inspection_non_programmee">Inspection non programmee</option>
        <option value="demonstration">Demonstration</option>
        <option value="test">Test</option>
        <option value="investigation">Investigation</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem;letter-spacing:.04em">Etat revue</label>
      <select id="fRevue" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="ok">Completes (y/y)</option>
        <option value="partial">En cours</option>
        <option value="none">Non saisies</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="visibility:hidden">-</label>
      <button class="btn btn-sm btn-outline-secondary w-100" id="btnReset"><i class="bi bi-x-lg me-1"></i>Reset filtres</button>
    </div>
  </div>
  <div class="mt-2 small text-muted" id="resCount"></div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead>
      <tr>
        <th>N Audit</th>
        <th>N Revue</th>
        <th>Nature / Cadre</th>
        <th>Operateur</th>
        <th>Responsable (RA)</th>
        <th>Membres</th>
        <th>Date prev.</th>
        <th>Statut</th>
        <th>Revue documentaire</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="tbody">
      <tr><td colspan="10" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- Modale PDF -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:92vw">
    <div class="modal-content" style="height:88vh;display:flex;flex-direction:column">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-file-pdf me-2 text-danger"></i>Revue documentaire</h5>
        <div class="ms-auto d-flex gap-2 me-2">
          <a id="pdfDl" href="#" download class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Telecharger</a>
          <button class="btn btn-sm btn-outline-secondary" id="pdfPrint"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="flex:1;overflow:hidden">
        <iframe id="pdfFrame" src="" style="width:100%;height:100%;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- Modale liste audits par etat de revue -->
<div class="modal fade" id="listModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="listModalTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="listModalBody"></div>
    </div>
  </div>
</div>

<!-- MODALE : choix du mode de traitement -->
<div class="modal fade" id="modalMode" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576);border:none">
        <h5 class="modal-title text-white"><i class="bi bi-clipboard2-check me-2" style="color:#F3C300"></i>Comment traiter la revue documentaire ?</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#f5f7fa;padding:22px">
        <input type="hidden" id="modeIdAudit"><input type="hidden" id="modeNumAudit">
        <div class="alert border py-2 small mb-3" style="background:#fff8e6;border-left:4px solid #F3C300 !important;color:#7a5c00">
          <i class="bi bi-exclamation-triangle me-1"></i><b>Important :</b> les inspecteurs de l'equipe doivent traiter leur revue <b>avant</b> le Responsable d'Audit (RA).
          Des qu'un mode est choisi (saisie ou PDF), <b>toute l'equipe suit le meme mode</b>. Quand le <b>RA</b> traite sa revue, l'acte est <b>cloture</b> : plus aucune saisie n'est possible.
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="mode-card" id="cardTexte" role="button" tabindex="0">
              <div class="mode-card-ic mc-blue"><i class="bi bi-pencil-square"></i></div>
              <div class="mode-card-t">Saisir en ligne</div>
              <div class="mode-card-s">Renseignez les <b>6 rubriques</b> de la revue directement dans l'application.</div>
              <div class="mode-card-go"><i class="bi bi-arrow-right-circle me-1"></i>Ouvrir le formulaire</div>
              <div class="mode-card-lock"><i class="bi bi-lock-fill me-1"></i>Indisponible : l'equipe a choisi le PDF</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mode-card" id="cardPdf" role="button" tabindex="0">
              <div class="mode-card-ic mc-red"><i class="bi bi-file-earmark-pdf"></i></div>
              <div class="mode-card-t">Joindre un PDF</div>
              <div class="mode-card-s">Vous avez deja redige la revue ? <b>Televersez le PDF</b> (10 Mo maximum).</div>
              <div class="mode-card-go"><i class="bi bi-arrow-right-circle me-1"></i>Choisir le fichier</div>
              <div class="mode-card-lock"><i class="bi bi-lock-fill me-1"></i>Indisponible : l'equipe a choisi la saisie</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : depot du PDF -->
<div class="modal fade" id="modalPdf" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <div class="modal-header" style="background:linear-gradient(135deg,#D32F2F,#b02525);border:none">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-pdf me-2" style="color:#fff"></i>Joindre le PDF de la revue</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pdfIdAudit">
        <div id="pdfDejaJoints" class="mb-3" style="display:none">
          <div style="font-size:.78rem;font-weight:700;color:#23408F;text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px">
            <i class="bi bi-collection me-1"></i>Documents deja joints par l'equipe
          </div>
          <div id="pdfDejaListe" class="d-flex flex-column gap-2"></div>
          <hr>
        </div>
        <label class="form-label fw-bold">Document PDF de la revue documentaire</label>
        <input type="file" class="form-control" id="pdfFile" accept="application/pdf">
        <div class="form-text mt-1">PDF uniquement, 10 Mo maximum. Ce document fera foi comme votre revue.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="btnUploadPdf"><i class="bi bi-upload me-1"></i>Joindre</button>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : consultation d'un PDF de revue -->
<div class="modal fade" id="modalPdfView" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576);border:none">
        <h5 class="modal-title text-white"><i class="bi bi-file-earmark-pdf me-2" style="color:#F3C300"></i><span id="pdfViewTitre">Revue documentaire (PDF)</span></h5>
        <div class="ms-auto d-flex gap-2 align-items-center">
          <a href="#" id="pdfViewDl" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-box-arrow-up-right me-1"></i>Ouvrir dans un onglet</a>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
      </div>
      <div class="modal-body" style="padding:0;background:#525659">
        <iframe id="pdfViewFrame" src="" style="width:100%;height:78vh;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF    = '<?php echo Security::escape($csrf); ?>';
const API_REV = AGAI_BASE + '/api/revue';
const IS_CI   = <?php echo $isCI ? 'true' : 'false'; ?>;

function apiPost(data){ data=Object.assign({csrf_token:CSRF},data); return $.post(API_REV,data,null,'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }

const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
const STATUT={1:{t:'Planifie',c:'s1'},2:{t:'Reporte',c:'s2'},3:{t:'Effectue',c:'s3'},4:{t:'Suspendu',c:'s4'},5:{t:'A surveiller',c:'s5'},6:{t:'Annule',c:'s6'},7:{t:'Inopine',c:'s7'}};

let ALL=[];

/* ===== KPI ===== */
function calcStats(list){
  const s={total:list.length,plan:0,eff:0,rev_ok:0,rev_part:0,rev_non:0};
  list.forEach(function(a){
    if(Number(a.statut)===1) s.plan++;
    if(Number(a.statut)===3) s.eff++;
    const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
    if(tot>0&&nb>=tot) s.rev_ok++;
    else if(nb>0) s.rev_part++;
    else s.rev_non++;
  });
  return s;
}
function updateStats(list){
  const s=calcStats(list);
  $('#st_total').text(s.total); $('#st_plan').text(s.plan); $('#st_eff').text(s.eff);
  $('#st_rev_ok').text(s.rev_ok); $('#st_rev_part').text(s.rev_part); $('#st_rev_non').text(s.rev_non);
}

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show);
  $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  try{localStorage.setItem('agai_stats_mesaudits',show?'1':'0');}catch(e){}
}
$('#btnToggleStats').on('click',function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== RENDU LIGNE ===== */
function progHtml(nb, tot){
  if(!tot) return '<span class="text-muted small">-</span>';
  const pct=Math.round((nb/tot)*100);
  const complete=pct>=100;
  const partial=pct>0&&pct<100;
  const cls=complete?'prog-full':partial?'prog-part':'prog-none';
  const lblCls=complete?'complete':partial?'partial':'empty2';
  return '<div class="prog-wrap">'
    +'<span class="prog-lbl '+lblCls+'">'+nb+'/'+tot+'</span>'
    +'<div class="prog-bar"><div class="prog-fill '+cls+'" style="width:'+pct+'%"></div></div>'
    +(complete?'<i class="bi bi-check-circle-fill text-success" style="font-size:.85rem"></i>':'')
    +'</div>';
}

function revueBtn(a){
  const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
  const complet=(tot>0&&nb>=tot);
  const url=AGAI_BASE+'/revue?audit='+esc(a.idaudit);
  const modeAudit=String(a.mode_audit||'');       // 'texte', 'pdf' ou ''
  const raTraite=Number(a.ra_a_traite||0)>0;       // le RA a cloture ?
  const estRA=String(a.est_ra)==='1';

  // Revue complete (le RA a traite, ou tous ont saisi) : consultation seule
  if(complet || raTraite){
    return '<a href="'+url+'" class="btn-revue-consulter">'
      +'<i class="bi bi-eye" style="font-size:.8rem"></i>Consulter'
      +'</a>'
      +'<div style="font-size:.67rem;color:#1E9C4B;font-weight:700;margin-top:3px"><i class="bi bi-check-circle me-1"></i>Revue complete</div>';
  }
  // En cours : au moins une revue existe -> bouton Poursuivre (mode deja fixe)
  if(nb>0){
    return '<button type="button" class="btn-revue-continuer btn-traiter" '
      + 'data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit||'')+'" data-mode="'+esc(modeAudit)+'" data-ra="'+(estRA?1:0)+'">'
      +'<i class="bi bi-pencil-fill" style="font-size:.75rem"></i>Poursuivre'
      +'</button>'
      +'<div style="font-size:.67rem;color:#b58a00;font-weight:700;margin-top:3px"><i class="bi bi-clock me-1"></i>En cours ('+(modeAudit==='pdf'?'PDF joint':'saisie')+')</div>';
  }
  // Rien fait : bouton Traiter la revue -> ouvre la modale de choix
  return '<button type="button" class="btn-revue-saisir btn-traiter" '
    + 'data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit||'')+'" data-mode="" data-ra="'+(estRA?1:0)+'" title="Saisir ou joindre la revue documentaire">'
    +'<i class="bi bi-pencil-square" style="font-size:.78rem"></i>Traiter la revue'
    +'</button>'
    +'<div style="font-size:.67rem;color:#9aa7bd;margin-top:3px"><i class="bi bi-dash-circle me-1"></i>Non saisie</div>';
}

function rowHtml(a){
  const st=STATUT[a.statut]||{t:a.statut||'-',c:'s1'};
  const type=TYPES[a.type_activite]||esc(a.type_activite||'');
  const raTag=String(a.est_ra)==='1'?'<span class="ra-tag">RA</span>':'';
  const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
  const hasPdf=a.mon_pdf&&String(a.mon_pdf).trim().length>0;
  const pdfInsp=a.pdf_idinspecteur||0;
  const modeAudit=String(a.mode_audit||'');
  const aContenu=Number(a.nb_revues||0)>0;

  var pdfBtn;
  if(hasPdf){
    // Mode PDF : consulter le document joint
    pdfBtn='<button class="btn btn-sm btn-outline-danger btn-pdf" data-audit="'+esc(a.idaudit)+'" data-insp="'+esc(pdfInsp)+'" title="Voir le PDF joint"><i class="bi bi-file-pdf me-1"></i>PDF</button>';
  } else if(modeAudit==='texte' && aContenu){
    // Mode saisie : imprimer le PDF genere depuis les 6 rubriques
    pdfBtn='<a class="btn btn-sm btn-outline-danger" href="'+AGAI_BASE+'/revue?audit='+esc(a.idaudit)+'&print=1" title="Imprimer la revue en PDF"><i class="bi bi-printer me-1"></i>PDF</a>';
  } else {
    pdfBtn='<button class="btn btn-sm btn-outline-secondary" disabled title="Revue non traitee"><i class="bi bi-file-pdf"></i></button>';
  }

  return '<tr>'
    +'<td><b style="color:#23408F;font-size:.88rem">'+esc(a.num_audit||'')+'</b></td>'
    +'<td style="font-size:.82rem;font-weight:600;color:#1E9C4B">'+(a.num_revue?esc(a.num_revue):'<span style="color:#c0c8d4">-</span>')+'</td>'
    +'<td><div style="font-weight:600;font-size:.86rem">'+type+'</div><div class="text-muted" style="font-size:.76rem">'+esc(a.cadre||'')+'</div></td>'
    +'<td style="font-size:.84rem">'+esc(a.operateur||'-')+'</td>'
    +'<td style="font-size:.84rem;color:#D32F2F;font-weight:600">'+esc(a.ra_nom||'-')+raTag+'</td>'
    +'<td style="font-size:.78rem;color:#5b6b85;max-width:180px">'+esc(a.membres||'-')+'</td>'
    +'<td style="font-size:.84rem">'+fmtDate(a.date_previsionnelle)+'</td>'
    +'<td><span class="s-badge '+st.c+'">'+esc(st.t)+'</span></td>'
    +'<td>'
    +'<div class="mb-1">'+progHtml(nb,tot)+'</div>'
    +'<div>'+revueBtn(a)+'</div>'
    +'</td>'
    +'<td style="text-align:right;white-space:nowrap">'+pdfBtn+'</td>'
    +'</tr>';
}

/* ===== FILTRE + RENDU ===== */
function getFiltered(){
  const st=$('#fStatut').val(), ty=$('#fType').val(), rev=$('#fRevue').val();
  return ALL.filter(function(a){
    if(st && String(a.statut)!==st) return false;
    if(ty && a.type_activite!==ty) return false;
    if(rev){
      const nb=Number(a.nb_revues||0), tot=Number(a.nb_equipe||0);
      if(rev==='ok'    && !(tot>0&&nb>=tot)) return false;
      if(rev==='partial' && !(nb>0&&nb<tot)) return false;
      if(rev==='none'  && nb>0)             return false;
    }
    return true;
  });
}
function render(){
  const list=getFiltered();
  updateStats(list);
  if(!list.length){
    $('#tbody').html('<tr><td colspan="10" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit.</td></tr>');
    $('#resCount').text(''); return;
  }
  $('#tbody').html(list.map(rowHtml).join(''));
  $('#resCount').html('<i class="bi bi-clipboard-check me-1"></i>'+list.length+' audit(s) affiches sur '+ALL.length);
}

/* ===== MODALES LISTES PAR ETAT REVUE ===== */
function showListModal(type){
  const TYPES_MAP={
    ok:{title:'Revues completes (y/y)',icon:'bi-file-earmark-check-fill text-success',filter:function(a){const nb=Number(a.nb_revues||0),tot=Number(a.nb_equipe||0);return tot>0&&nb>=tot;}},
    partial:{title:'Revues en cours',icon:'bi-file-earmark-text text-warning',filter:function(a){const nb=Number(a.nb_revues||0),tot=Number(a.nb_equipe||0);return nb>0&&nb<tot;}},
    none:{title:'Revues non saisies',icon:'bi-file-earmark-x text-danger',filter:function(a){return Number(a.nb_revues||0)===0;}}
  };
  const cfg=TYPES_MAP[type]; if(!cfg) return;
  const list=ALL.filter(cfg.filter);
  $('#listModalTitle').html('<i class="bi '+cfg.icon+' me-2"></i>'+cfg.title+' ('+list.length+')');
  if(!list.length){
    $('#listModalBody').html('<div class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Aucun audit dans cette categorie.</div>');
  } else {
    let h='<div class="table-responsive"><table class="table table-sm table-hover align-middle">'
      +'<thead style="background:#23408F;color:#fff"><tr>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">N Audit</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Nature</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Operateur</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Progression</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase">Statut</th>'
      +'<th style="padding:8px 12px;font-size:.72rem;text-transform:uppercase;text-align:right">Action</th>'
      +'</tr></thead><tbody>';
    list.forEach(function(a){
      const st=STATUT[a.statut]||{t:a.statut||'-',c:'s1'};
      const nb=Number(a.nb_revues||0),tot=Number(a.nb_equipe||0);
      h+='<tr>'
        +'<td style="font-family:monospace;font-weight:700;color:#23408F;font-size:.85rem">'+esc(a.num_audit||'')+'</td>'
        +'<td style="font-size:.82rem">'+esc(TYPES[a.type_activite]||a.type_activite||'')+'</td>'
        +'<td style="font-size:.82rem">'+esc(a.operateur||'-')+'</td>'
        +'<td>'+progHtml(nb,tot)+'</td>'
        +'<td><span class="s-badge '+st.c+'">'+esc(st.t)+'</span></td>'
        +'<td style="text-align:right">'
        +'<a href="'+AGAI_BASE+'/revue?audit='+esc(a.idaudit)+'" class="btn btn-xs btn-anac" style="padding:3px 8px;font-size:.78rem">'
        +(nb>0&&tot>0&&nb>=tot?'<i class="bi bi-eye me-1"></i>Consulter':'<i class="bi bi-pencil-square me-1"></i>Traiter la revue')
        +'</a></td></tr>';
    });
    h+='</tbody></table></div>';
    $('#listModalBody').html(h);
  }
  new bootstrap.Modal('#listModal').show();
}
$('#cardAvecRevue').on('click',function(){ showListModal('ok'); });
$('#cardSansRevue').on('click',function(){ showListModal('partial'); });
$('#cardNonRevue').on('click', function(){ showListModal('none'); });

/* ===== PDF ===== */
$(document).on('click','.btn-pdf',function(){
  const idaudit=$(this).data('audit'), idinsp=$(this).data('insp')||0;
  const url=AGAI_BASE+'/api/revue?serve=1&idaudit='+idaudit+'&idinsp='+idinsp;
  $('#pdfFrame').attr('src',url);
  $('#pdfDl').attr('href',url+'&dl=1');
  $('#pdfPrint').off('click').on('click',function(){ document.getElementById('pdfFrame').contentWindow.print(); });
  new bootstrap.Modal('#pdfModal').show();
});
$('#pdfModal').on('hidden.bs.modal',function(){ $('#pdfFrame').attr('src',''); });

/* ===== SELECTS ===== */
$('#fStatut,#fType').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
$('#fStatut,#fType,#fRevue').on('change',render);
$('#btnReset').on('click',function(){
  $('#fStatut,#fType').val('').trigger('change');
  $('#fRevue').val(''); render();
});

/* ===== DEMARRAGE ===== */
$('#guideRevueToggle').on('click',function(){
  $('#guideRevueBody').slideToggle(180);
  $('#guideRevueChevron').css('transform', $('#guideRevueBody').is(':visible')?'rotate(0deg)':'rotate(-90deg)');
});

function chargerAudits(){
  return apiPost({action:'mes_audits'}).done(function(res){
    if(!res.success){ $('#tbody').html('<tr><td colspan="10" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ALL=res.data||[]; render();
  }).fail(function(){ $('#tbody').html('<tr><td colspan="10" class="empty">Echec.</td></tr>'); });
}
chargerAudits();

/* Clic sur "Traiter la revue" / "Poursuivre" : ouvre la modale de choix.
   Le mode deja adopte par l'equipe grise l'option opposee. */
$(document).on('click','.btn-traiter',function(){
  const id=$(this).attr('data-id'), num=$(this).attr('data-num'), mode=String($(this).attr('data-mode')||'');
  $('#modeIdAudit').val(id); $('#modeNumAudit').val(num);
  // Reactiver les deux cartes puis verrouiller selon le mode
  $('#cardTexte,#cardPdf').removeClass('locked');
  if(mode==='pdf'){ $('#cardTexte').addClass('locked'); }
  else if(mode==='texte'){ $('#cardPdf').addClass('locked'); }
  new bootstrap.Modal('#modalMode').show();
});

/* Choix "Saisir en ligne" -> redirige vers le formulaire de revue */
$('#cardTexte').on('click',function(){
  if($(this).hasClass('locked')) return;
  const id=$('#modeIdAudit').val();
  window.location.href = AGAI_BASE + '/revue?audit=' + encodeURIComponent(id);
});

/* Choix "Joindre un PDF" -> petite modale de depot */
$('#cardPdf').on('click',function(){
  if($(this).hasClass('locked')) return;
  const id=$('#modeIdAudit').val();
  bootstrap.Modal.getInstance(document.getElementById('modalMode')).hide();
  $('#pdfIdAudit').val(id); $('#pdfFile').val('');
  chargerPdfJoints(id);
  new bootstrap.Modal('#modalPdf').show();
});

/* Liste des PDF deja joints par l'equipe (consultation) */
function chargerPdfJoints(idaudit){
  $('#pdfDejaJoints').hide(); $('#pdfDejaListe').html('');
  apiPost({action:'pdfs_audit',idaudit:idaudit}).done(function(res){
    if(!res.success || !res.pdfs || !res.pdfs.length) return;
    var h='';
    res.pdfs.forEach(function(p){
      var estRA=Number(p.est_ra)===1;
      var url=API_REV+'?serve=1&idaudit='+encodeURIComponent(idaudit)+'&idinsp='+encodeURIComponent(p.idinspecteur);
      h+='<div class="d-flex align-items-center gap-2 p-2" style="background:#f5f7fa;border:1px solid #eef1f6;border-radius:8px">'
        +'<i class="bi bi-file-earmark-pdf text-danger"></i>'
        +'<div style="flex:1;font-size:.83rem">'+esc(p.nom||'-')
        +(estRA?' <span style="background:#D32F2F;color:#fff;font-size:.62rem;font-weight:700;padding:.05rem .35rem;border-radius:8px">RA</span>':'')
        +(Number(p.est_consolide)===1?' <span style="background:#1E9C4B;color:#fff;font-size:.62rem;font-weight:700;padding:.05rem .35rem;border-radius:8px">Final</span>':'')
        +'</div>'
        +'<button type="button" class="btn btn-sm btn-outline-primary btn-voir-pdf-joint" data-url="'+esc(url)+'" data-nom="'+esc(p.nom||'')+'"><i class="bi bi-eye me-1"></i>Voir</button>'
        +'</div>';
    });
    $('#pdfDejaListe').html(h);
    $('#pdfDejaJoints').show();
  });
}

/* Ouvre un PDF joint dans la modale de visualisation */
$(document).on('click','.btn-voir-pdf-joint',function(){
  var url=$(this).attr('data-url'), nom=$(this).attr('data-nom')||'';
  $('#pdfViewFrame').attr('src',url);
  $('#pdfViewDl').attr('href',url);
  $('#pdfViewTitre').text('Revue documentaire'+(nom?' - '+nom:''));
  new bootstrap.Modal('#modalPdfView').show();
});
$(document).on('hidden.bs.modal','#modalPdfView',function(){ $('#pdfViewFrame').attr('src',''); });

/* Envoi du PDF */
$('#btnUploadPdf').on('click',function(){
  const id=$('#pdfIdAudit').val();
  const f=$('#pdfFile')[0].files[0];
  if(!f){ Swal.fire({icon:'info',title:'Aucun fichier',text:'Choisissez un PDF a joindre.',confirmButtonColor:'#23408F'}); return; }
  if(f.type!=='application/pdf'){ Swal.fire({icon:'error',title:'Format invalide',text:'Seuls les PDF sont acceptes.',confirmButtonColor:'#23408F'}); return; }
  if(f.size>10*1024*1024){ Swal.fire({icon:'error',title:'Fichier trop lourd',text:'10 Mo maximum.',confirmButtonColor:'#23408F'}); return; }
  const fd=new FormData();
  fd.append('csrf_token',CSRF); fd.append('action','upload_revue');
  fd.append('idaudit',id); fd.append('fichier',f);
  Swal.fire({title:'Envoi en cours',allowOutsideClick:false,didOpen:function(){Swal.showLoading();}});
  $.ajax({url:API_REV,method:'POST',data:fd,processData:false,contentType:false,dataType:'json'})
   .done(function(res){
      Swal.close();
      if(!res||!res.success){ Swal.fire({icon:'error',title:'Echec',text:(res&&res.message)||'Envoi impossible.',confirmButtonColor:'#23408F'}); return; }
      bootstrap.Modal.getInstance(document.getElementById('modalPdf')).hide();
      Swal.fire({icon:'success',title:'PDF joint',timer:1400,showConfirmButton:false});
      chargerAudits();
   })
   .fail(function(){ Swal.close(); Swal.fire({icon:'error',title:'Echec de la requete',confirmButtonColor:'#23408F'}); });
});

(function(){
  let v='0'; try{v=localStorage.getItem('agai_stats_mesaudits')||'0';}catch(e){}
  if(v==='1') setStatsVisible(true);
})();
</script>