<?php
/**
 * Page : Notifications - Lettres de notification des audits
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('notifications');
$csrf      = Security::generateCSRF();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$isOper    = ($role === 'operateur');
$pageTitle = 'Notifications';
$active    = 'notifications';
$pageIcon  = 'bi-bell';
$sousTitre = $isOper
    ? 'Consultez les lettres de notification des audits vous concernant.'
    : 'Joignez et envoyez les lettres de notification aux operateurs dont la revue documentaire est complete.';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<style>
/* Stats */
.stat-card{background:#fff;border:1px solid #eef1f6;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 2px rgba(16,30,54,.04);height:100%;}
.stat-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ic-blue{background:rgba(35,64,143,.10);color:#23408F;} .ic-green{background:rgba(30,156,75,.12);color:#1E9C4B;}
.ic-gold{background:rgba(243,195,0,.18);color:#b58a00;} .ic-dark{background:rgba(44,62,80,.09);color:#2C3E50;}
.stat-num{font-size:1.5rem;font-weight:700;color:#2C3E50;line-height:1;} .stat-lbl{font-size:.78rem;color:#7b8aa0;}
/* Tableau */
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
.s6{background:#e2e3e5;color:#383d41;} .s7{background:#cfe2ff;color:#084298;}
/* Statuts notification */
.tag-joint{background:#d1e7dd;color:#0a5c36;border-radius:8px;padding:3px 9px;font-size:.76rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.tag-envoi{background:#e8f0fe;color:#23408F;border-radius:8px;padding:3px 9px;font-size:.76rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;margin-left:4px;}
.tag-attente{background:#fff3cd;color:#856404;border-radius:8px;padding:3px 9px;font-size:.76rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;}
.tag-ra{background:#f8d7da;color:#842029;border-radius:8px;padding:3px 9px;font-size:.76rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;}
/* Boutons action distincts */
.btn-joindre{background:linear-gradient(135deg,#1E9C4B,#157a3a);color:#fff;border:none;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:700;display:inline-flex;align-items:center;gap:5px;cursor:pointer;transition:all .15s;text-decoration:none;}
.btn-joindre:hover{background:linear-gradient(135deg,#157a3a,#0e5228);color:#fff;transform:translateY(-1px);box-shadow:0 3px 8px rgba(30,156,75,.35);}
.btn-remplacer{background:linear-gradient(135deg,#b58a00,#9a7500);color:#fff;border:none;border-radius:8px;padding:5px 12px;font-size:.79rem;font-weight:700;display:inline-flex;align-items:center;gap:5px;cursor:pointer;transition:all .15s;}
.btn-remplacer:hover{background:linear-gradient(135deg,#9a7500,#7d5f00);color:#fff;transform:translateY(-1px);box-shadow:0 3px 8px rgba(181,138,0,.35);}
.btn-voir-lettre{background:#eef1f6;color:#23408F;border:1px solid #d0d7e3;border-radius:8px;padding:5px 11px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;cursor:pointer;text-decoration:none;}
.btn-voir-lettre:hover{background:#e0e6f5;color:#23408F;}
.btn-dl{background:#fff;color:#2C3E50;border:1px solid #d0d7e3;border-radius:8px;padding:5px 9px;font-size:.78rem;cursor:pointer;display:inline-flex;align-items:center;}
.btn-dl:hover{background:#f5f7fa;}
/* Infobulles */
[data-tooltip]{position:relative;}
[data-tooltip]:hover::after{content:attr(data-tooltip);position:absolute;bottom:110%;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:5px 10px;border-radius:6px;font-size:.73rem;white-space:nowrap;z-index:99;pointer-events:none;}
[data-tooltip]:hover::before{content:'';position:absolute;bottom:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:#1e293b;z-index:99;}
/* Explication */
.howto-card{background:linear-gradient(135deg,rgba(35,64,143,.04),rgba(30,156,75,.03));border:1px solid rgba(35,64,143,.12);border-radius:14px;padding:18px 22px;}
.howto-step{display:flex;align-items:flex-start;gap:14px;padding:10px 0;border-bottom:1px solid rgba(35,64,143,.07);}
.howto-step:last-child{border-bottom:none;}
.step-num{width:32px;height:32px;border-radius:50%;background:#23408F;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;flex:0 0 auto;}
</style>

<div class="page-head">
  <div>
    <h1><i class="bi bi-bell me-2" style="color:var(--anac-primary)"></i>Notifications</h1>
    <div class="sub"><?php echo Security::escape($sousTitre); ?></div>
  </div>
</div>

<?php if ($isOper): ?>
<!-- Message contextuel pour l'operateur -->
<div class="alert mb-3" style="background:#e8f0fe;border:1px solid #c5d4f5;border-left:4px solid #23408F;border-radius:10px;padding:12px 16px">
  <i class="bi bi-info-circle-fill me-2" style="color:#23408F"></i>
  <strong style="color:#23408F">Espace operateur.</strong>
  Vous visualisez ici les lettres de notification des audits qui vous concernent. Pour telecharger ou visualiser une lettre, cliquez sur <strong>Voir</strong>.
</div>
<?php else: ?>
<!-- Guide pour RA/CI -->
<div class="howto-card mb-3">
  <div class="d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-question-circle-fill" style="color:#23408F;font-size:1.2rem"></i>
    <strong style="color:#23408F;font-size:.95rem">Comment fonctionne ce module ?</strong>
    <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnToggleHow" style="font-size:.78rem">
      <span id="howLbl">Masquer le guide</span>
    </button>
  </div>
  <div id="howtoBody">
    <div class="howto-step">
      <div class="step-num">1</div>
      <div><strong>Eligibilite.</strong> Seuls les audits dont la revue documentaire est <span class="tag-joint"><i class="bi bi-check-circle"></i>Complete (y/y)</span> apparaissent ici. Si un audit n'est pas visible, sa revue documentaire n'est pas encore finalisee.</div>
    </div>
    <div class="howto-step">
      <div class="step-num">2</div>
      <div><strong>Joindre la lettre.</strong> Le Responsable d'Audit (RA), le Chef Inspecteur ou l'Administrateur peut cliquer sur <span class="btn-joindre" style="pointer-events:none"><i class="bi bi-paperclip"></i>Joindre</span> pour attacher le PDF ou Word de la lettre officielle. La date de notification est mise a jour automatiquement.</div>
    </div>
    <div class="howto-step">
      <div class="step-num">3</div>
      <div><strong>Envoi par mail.</strong> En cochant "Envoyer par mail", la lettre est transmise par email a l'operateur avec le fichier en piece jointe. Si l'operateur n'a pas d'email, une fenetre s'ouvre pour le saisir et l'enregistrer.</div>
    </div>
    <div class="howto-step">
      <div class="step-num">4</div>
      <div><strong>Remplacer.</strong> Vous pouvez remplacer une lettre deja jointe en cliquant sur <span class="btn-remplacer" style="pointer-events:none"><i class="bi bi-arrow-repeat"></i>Remplacer</span>. L'ancien fichier est supprime automatiquement.</div>
    </div>
    <div class="howto-step">
      <div class="step-num">5</div>
      <div><strong>Inspecteurs.</strong> Les inspecteurs (non RA) voient le tableau mais n'ont pas de bouton d'action. La colonne indique <span class="tag-ra"><i class="bi bi-lock"></i>RA uniquement</span> pour les audits sans lettre, et <span class="tag-joint"><i class="bi bi-check-circle"></i>Lettre jointe</span> si la lettre est disponible.</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Toggle stats -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleStats">
    <i class="bi bi-bar-chart-line-fill me-1"></i><span id="statsLbl">Afficher les statistiques</span>
  </button>
  <span class="small text-muted" id="resCount"></span>
</div>

<!-- KPI masquables -->
<div id="statsPanel" class="row g-3 mb-3" style="display:none">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-blue"><i class="bi bi-clipboard-check-fill"></i></div><div><div class="stat-num" id="st_total">0</div><div class="stat-lbl">Audits a notifier</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-green"><i class="bi bi-envelope-check-fill"></i></div><div><div class="stat-num" id="st_joint">0</div><div class="stat-lbl">Lettres jointes</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-gold"><i class="bi bi-send-fill"></i></div><div><div class="stat-num" id="st_envoye">0</div><div class="stat-lbl">Envoyees par mail</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-ic ic-dark"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-num" id="st_attente">0</div><div class="stat-lbl">En attente</div></div></div></div>
</div>

<!-- Filtres masquables -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleFiltre">
    <i class="bi bi-funnel me-1"></i><span id="filtreLbl">Afficher les filtres</span>
  </button>
</div>
<div id="filtrePanel" class="filter-bar mb-3" style="display:none">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">Statut audit</label>
      <select id="fStatut" style="width:100%">
        <option value="">Tous les statuts</option>
        <option value="1">Planifie</option><option value="2">Reporte</option>
        <option value="3">Effectue</option><option value="4">Suspendu</option>
        <option value="6">Annule</option><option value="7">Inopine</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">Operateur</label>
      <select id="fOrga" style="width:100%"><option value="">Tous les operateurs</option></select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">Etat lettre</label>
      <select id="fEtat" class="form-select form-select-sm">
        <option value="">Tous</option>
        <option value="1">Lettre jointe</option>
        <option value="2">Envoye par mail</option>
        <option value="0">Sans lettre</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="color:#5b6b85;text-transform:uppercase;font-size:.72rem">Recherche</label>
      <input type="text" id="fSearch" class="form-control form-control-sm" placeholder="N audit, responsable...">
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold mb-1" style="visibility:hidden">-</label>
      <button class="btn btn-sm btn-outline-secondary w-100" id="btnReset"><i class="bi bi-x-lg me-1"></i>Reset</button>
    </div>
  </div>
</div>

<!-- Tableau -->
<div style="background:#fff;border:1px solid #eef1f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,30,54,.04)">
  <table class="tbl">
    <thead>
      <tr>
        <th>N Audit</th>
        <th>Nature</th>
        <th>Operateur</th>
        <th>Responsable (RA)</th>
        <th>Date prev.</th>
        <th>Statut</th>
        <th>Lettre notification</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody id="tbody">
      <tr><td colspan="8" class="empty"><span class="spinner-border spinner-border-sm me-2"></span>Chargement...</td></tr>
    </tbody>
  </table>
</div>

<!-- MODALE : Joindre / Remplacer la lettre -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="uploadForm" enctype="multipart/form-data">
      <div class="modal-header" style="background:linear-gradient(135deg,#23408F,#1b3576)">
        <h5 class="modal-title text-white"><i class="bi bi-paperclip me-2" style="color:#F3C300"></i><span id="upModalTitle">Joindre la lettre de notification</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="up_idaudit">
        <input type="hidden" id="up_idorga">
        <!-- Info audit -->
        <div class="mb-3 p-3" style="background:#f0f4ff;border-radius:10px;border-left:4px solid #23408F">
          <div id="up_audit_info" style="font-weight:700;color:#23408F;font-size:.92rem"></div>
          <div id="up_orga_info" style="font-size:.84rem;color:#555;margin-top:3px"></div>
        </div>
        <!-- Fichier -->
        <div class="mb-3">
          <label class="form-label fw-bold">Fichier lettre (PDF ou Word) <span class="text-danger">*</span></label>
          <input type="file" class="form-control" id="up_fichier" name="fichier_notif" accept=".pdf,.doc,.docx" required>
          <div class="form-text"><i class="bi bi-info-circle me-1 text-primary"></i>Formats acceptes : PDF, DOC, DOCX. <strong>Pas de limite de taille.</strong></div>
        </div>
        <!-- Case mail -->
        <div class="p-3" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="up_envoyer_mail" style="width:2.5rem;height:1.3rem">
            <label class="form-check-label fw-bold" for="up_envoyer_mail">
              <i class="bi bi-envelope me-1"></i>Envoyer aussi par mail a l'operateur
            </label>
          </div>
          <div id="up_mail_preview" style="display:none;margin-top:10px">
            <div class="small text-muted mb-1">Destinataire :</div>
            <div id="up_email_display" style="color:#23408F;font-weight:700;font-size:.9rem"></div>
            <div class="small text-muted mt-1"><i class="bi bi-paperclip me-1"></i>La lettre sera jointe en piece jointe.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-anac" id="up_submit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE : Saisir email manquant -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Email operateur requis</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><strong id="emailModalOrga"></strong> n'a pas d'email enregistre. Saisissez-le pour continuer.</div>
        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
        <input type="email" class="form-control" id="new_email" placeholder="ex: contact@compagnie.ga">
        <div class="form-text">Cet email sera enregistre dans la fiche operateur.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-anac" id="btnConfirmEmail"><i class="bi bi-check-lg me-1"></i>Valider et envoyer</button>
      </div>
    </div>
  </div>
</div>

<!-- MODALE : Visualiser la lettre PDF -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:90vw">
    <div class="modal-content" style="height:88vh;display:flex;flex-direction:column">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-file-pdf me-2 text-danger"></i><span id="pdfTitle"></span></h5>
        <div class="ms-auto d-flex gap-2 me-2">
          <a id="pdfDl" href="#" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Telecharger</a>
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

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF   = '<?php echo Security::escape($csrf); ?>';
const API    = AGAI_BASE + '/api/notifications';
const IS_CI  = <?php echo $isCI ? 'true' : 'false'; ?>;
let ALL = [], pendingEmail = {};

function apiPost(data){ return $.post(API, Object.assign({csrf_token:CSRF}, data), null, 'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
const STATUT={1:{t:'Planifie',c:'s1'},2:{t:'Reporte',c:'s2'},3:{t:'Effectue',c:'s3'},4:{t:'Suspendu',c:'s4'},6:{t:'Annule',c:'s6'},7:{t:'Inopine',c:'s7'}};

/* ===== TOGGLE GUIDE ===== */
let howVisible = true;
$('#btnToggleHow').on('click',function(){
  howVisible=!howVisible;
  $('#howtoBody').toggle(howVisible);
  $('#howLbl').text(howVisible?'Masquer le guide':'Afficher le guide');
});

/* ===== TOGGLE STATS ===== */
function setStatsVisible(show){
  $('#statsPanel').toggle(show); $('#statsLbl').text(show?'Masquer les statistiques':'Afficher les statistiques');
  try{localStorage.setItem('agai_stats_notif',show?'1':'0');}catch(e){}
}
$('#btnToggleStats').on('click',function(){ setStatsVisible($('#statsPanel').is(':hidden')); });

/* ===== TOGGLE FILTRES ===== */
$('#btnToggleFiltre').on('click',function(){
  const show=$('#filtrePanel').is(':hidden');
  $('#filtrePanel').toggle(show); $('#filtreLbl').text(show?'Masquer les filtres':'Afficher les filtres');
});

/* ===== STATS ===== */
function updateKpi(list){
  const joint  = list.filter(function(a){return a.lettre_notification&&String(a.lettre_notification).trim();}).length;
  const envoye = list.filter(function(a){return Number(a.lettre_notif_envoi_mail)===1;}).length;
  $('#st_total').text(list.length); $('#st_joint').text(joint);
  $('#st_envoye').text(envoye); $('#st_attente').text(list.length-joint);
}

/* ===== RENDU ===== */
function lettreTag(a){
  const has = a.lettre_notification && String(a.lettre_notification).trim();
  const env = Number(a.lettre_notif_envoi_mail)===1;
  if(!has) return '<span class="tag-attente"><i class="bi bi-hourglass-split"></i>En attente</span>';
  return '<span class="tag-joint"><i class="bi bi-paperclip"></i>Jointe</span>'
    +(env?'<span class="tag-envoi ms-1"><i class="bi bi-envelope-check"></i>Mail envoye</span>':'')
    +'<div class="text-muted small mt-1">'+fmtDate(a.date_notification)+'</div>';
}

function actionsHtml(a){
  const has   = a.lettre_notification && String(a.lettre_notification).trim();
  const estRA = String(a.est_ra)==='1';
  const peutJoindre = IS_CI || estRA;
  let html='';
  if(has){
    const url=AGAI_BASE+'/api/notifications?serve=1&idaudit='+esc(a.idaudit);
    html+='<a href="javascript:void(0)" class="btn-voir-lettre me-1 btn-pdf" data-audit="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" data-tooltip="Voir la lettre"><i class="bi bi-eye"></i></a>';
    html+='<a href="'+url+'&dl=1" class="btn-dl me-1" data-tooltip="Telecharger"><i class="bi bi-download"></i></a>';
  }
  if(peutJoindre){
    if(has){
      html+='<button class="btn-remplacer btn-upload" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" data-orga="'+esc(a.nomorga)+'" data-idorga="'+esc(a.idorga)+'" data-tooltip="Remplacer la lettre existante">'
        +'<i class="bi bi-arrow-repeat"></i>Remplacer</button>';
    } else {
      html+='<button class="btn-joindre btn-upload" data-id="'+esc(a.idaudit)+'" data-num="'+esc(a.num_audit)+'" data-orga="'+esc(a.nomorga)+'" data-idorga="'+esc(a.idorga)+'" data-tooltip="Joindre la lettre de notification">'
        +'<i class="bi bi-paperclip"></i>Joindre</button>';
    }
  } else {
    if(!has) html+='<span class="tag-ra"><i class="bi bi-lock"></i>RA uniquement</span>';
  }
  return '<div style="display:flex;align-items:center;gap:4px;justify-content:flex-end;flex-wrap:wrap">'+html+'</div>';
}

function rowHtml(a){
  const st=STATUT[a.statut]||{t:'-',c:'s1'};
  return '<tr>'
    +'<td><b style="color:#23408F;font-size:.88rem">'+esc(a.num_audit||'')+'</b></td>'
    +'<td style="font-size:.84rem">'+esc(TYPES[a.type_activite]||a.type_activite||'')+'</td>'
    +'<td style="font-weight:600;font-size:.84rem">'+esc(a.nomorga||'-')+'</td>'
    +'<td style="font-size:.84rem;color:#D32F2F;font-weight:600">'+esc(a.ra_nom||'-')+'</td>'
    +'<td style="font-size:.84rem">'+fmtDate(a.date_previsionnelle)+'</td>'
    +'<td><span class="s-badge '+st.c+'">'+esc(st.t)+'</span></td>'
    +'<td>'+lettreTag(a)+'</td>'
    +'<td>'+actionsHtml(a)+'</td>'
    +'</tr>';
}

function getFiltered(){
  const st=$('#fStatut').val(), org=$('#fOrga').val(), et=$('#fEtat').val(), q=$('#fSearch').val().toLowerCase().trim();
  return ALL.filter(function(a){
    if(st && String(a.statut)!==st) return false;
    if(org && String(a.idorga)!==String(org)) return false;
    const has=a.lettre_notification&&String(a.lettre_notification).trim();
    const env=Number(a.lettre_notif_envoi_mail)===1;
    if(et==='1'&&!has) return false;
    if(et==='2'&&!env) return false;
    if(et==='0'&&has)  return false;
    if(q&&!(
      (a.num_audit||'').toLowerCase().includes(q)||
      (a.nomorga||'').toLowerCase().includes(q)||
      (a.ra_nom||'').toLowerCase().includes(q)
    )) return false;
    return true;
  });
}

function render(){
  const list=getFiltered();
  updateKpi(list);
  if(!list.length){
    $('#tbody').html('<tr><td colspan="8" class="empty"><i class="bi bi-inbox me-2"></i>Aucun audit.'+(ALL.length?'<br><small class="text-muted">Modifiez les filtres pour afficher plus de resultats.</small>':'<br><small class="text-muted">Les audits apparaissent ici quand leur revue documentaire est complete (y/y).</small>')+'</td></tr>');
  } else {
    $('#tbody').html(list.map(rowHtml).join(''));
  }
  $('#resCount').html('<i class="bi bi-clipboard-check me-1"></i>'+list.length+' audit(s) affiches sur '+ALL.length);
}

function loadList(){
  apiPost({action:'list'}).done(function(res){
    if(!res.success){ $('#tbody').html('<tr><td colspan="8" class="empty">'+esc(res.message||'Erreur')+'</td></tr>'); return; }
    ALL=res.data||[]; fillOrgaFilter(); render();
  }).fail(function(){ $('#tbody').html('<tr><td colspan="8" class="empty">Echec du chargement.</td></tr>'); });
}

$('#fStatut').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous les statuts'});
$('#fOrga').select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous les operateurs'});
$('#fStatut,#fOrga,#fEtat').on('change',render);
$('#fSearch').on('input',render);
$('#btnReset').on('click',function(){
  $('#fStatut,#fOrga').val('').trigger('change');
  $('#fEtat').val(''); $('#fSearch').val(''); render();
});

function fillOrgaFilter(){
  const seen={}, cur=$('#fOrga').val();
  let opts='<option value="">Tous les operateurs</option>';
  ALL.forEach(function(a){
    if(a.idorga&&a.nomorga&&!seen[a.idorga]){
      seen[a.idorga]=1;
      opts+='<option value="'+esc(a.idorga)+'">'+esc(a.nomorga)+'</option>';
    }
  });
  $('#fOrga').html(opts);
  if(cur&&ALL.some(function(a){return String(a.idorga)===String(cur);})) $('#fOrga').val(cur);
  $('#fOrga').trigger('change.select2');
}

/* ===== MODALE UPLOAD ===== */
$(document).on('click','.btn-upload',function(){
  const id=$(this).data('id'), num=$(this).data('num');
  const orga=$(this).data('orga'), idorga=$(this).data('idorga');
  const remplace=$(this).hasClass('btn-remplacer');
  $('#upModalTitle').html((remplace?'<i class="bi bi-arrow-repeat me-1"></i>Remplacer':'<i class="bi bi-paperclip me-1"></i>Joindre')+' la lettre de notification');
  $('#up_idaudit').val(id); $('#up_idorga').val(idorga);
  $('#up_audit_info').text('Audit : '+num);
  $('#up_orga_info').text('Operateur : '+orga);
  $('#up_fichier').val('');
  $('#up_envoyer_mail').prop('checked',false);
  $('#up_mail_preview').hide();
  pendingEmail = {};
  new bootstrap.Modal('#uploadModal').show();
  // Pre-charger email
  apiPost({action:'check_email',idorga:idorga}).done(function(res){
    if(res.success){ pendingEmail={has_email:res.has_email,emailorga:res.emailorga,nomorga:res.nomorga,idorga:idorga}; }
  });
});

$('#up_envoyer_mail').on('change',function(){
  if($(this).is(':checked')){
    const email=pendingEmail.emailorga||'';
    $('#up_email_display').html(email
      ?('<i class="bi bi-envelope-fill me-1"></i>'+esc(email))
      :'<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Email non renseigne - saisissez-le dans la fenetre</span>');
    $('#up_mail_preview').show();
    // Si l'operateur n'a pas d'email, on ouvre tout de suite la fenetre de saisie
    // (au lieu d'attendre la soumission), pour que l'utilisateur le renseigne.
    if(!email){
      const idorga=$('#up_idorga').val();
      $('#emailModalOrga').text(pendingEmail.nomorga||$('#up_orga_info').text().replace('Operateur : ','')||'Cet operateur');
      $('#new_email').val('');
      const em=new bootstrap.Modal('#emailModal'); em.show();
      $('#btnConfirmEmail').off('click').on('click',function(){
        const val=$('#new_email').val().trim();
        if(!val||!val.includes('@')){ Swal.fire({icon:'warning',title:'Email invalide',confirmButtonColor:'#23408F'}); return; }
        apiPost({action:'update_email',idorga:idorga,emailorga:val}).done(function(r){
          if(r.success){
            pendingEmail.has_email=true; pendingEmail.emailorga=val;
            $('#up_email_display').html('<i class="bi bi-envelope-fill me-1"></i>'+esc(val));
            em.hide();
          } else { Swal.fire({icon:'error',text:r.message,confirmButtonColor:'#23408F'}); }
        });
      });
      // Si l'utilisateur ferme sans saisir, on decoche la case
      $('#emailModal').off('hidden.bs.modal.autodecoche').on('hidden.bs.modal.autodecoche',function(){
        if(!pendingEmail.emailorga){ $('#up_envoyer_mail').prop('checked',false); $('#up_mail_preview').hide(); }
      });
    }
  } else {
    $('#up_mail_preview').hide();
  }
});

$('#uploadForm').on('submit',function(e){
  e.preventDefault();
  const idaudit=$('#up_idaudit').val(), idorga=$('#up_idorga').val();
  const viamail=$('#up_envoyer_mail').is(':checked');
  if(!$('#up_fichier')[0].files.length){ Swal.fire({icon:'warning',title:'Fichier requis',text:'Selectionnez un fichier PDF ou Word.',confirmButtonColor:'#23408F'}); return; }
  const btn=$('#up_submit'), btnHtml=btn.html();
  btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...');
  // Upload via FormData
  const fd=new FormData();
  fd.append('csrf_token',CSRF); fd.append('action','upload'); fd.append('idaudit',idaudit);
  fd.append('fichier_notif',$('#up_fichier')[0].files[0]);
  $.ajax({url:API,type:'POST',data:fd,processData:false,contentType:false,dataType:'json'})
  .done(function(res){
    btn.prop('disabled',false).html(btnHtml);
    if(!res.success){ Swal.fire({icon:'error',title:'Erreur',text:res.message,confirmButtonColor:'#23408F'}); return; }
    bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
    if(!viamail){
      Swal.fire({icon:'success',title:'Lettre enregistree',text:'Date de notification mise a jour automatiquement.',timer:2200,showConfirmButton:false,timerProgressBar:true});
      loadList(); return;
    }
    // Envoi mail
    if(pendingEmail.has_email && pendingEmail.emailorga){
      doSendMail(idaudit, pendingEmail.emailorga);
    } else {
      $('#emailModalOrga').text(pendingEmail.nomorga||'Cet operateur');
      $('#new_email').val('');
      const em=new bootstrap.Modal('#emailModal'); em.show();
      $('#btnConfirmEmail').off('click').on('click',function(){
        const email=$('#new_email').val().trim();
        if(!email||!email.includes('@')){ Swal.fire({icon:'warning',title:'Email invalide',confirmButtonColor:'#23408F'}); return; }
        apiPost({action:'update_email',idorga:idorga,emailorga:email}).done(function(r){
          if(r.success){ em.hide(); doSendMail(idaudit,email); }
          else Swal.fire({icon:'error',text:r.message,confirmButtonColor:'#23408F'});
        });
      });
    }
    loadList();
  })
  .fail(function(jqXHR){
    btn.prop('disabled',false).html(btnHtml);
    const msg = jqXHR.responseJSON ? (jqXHR.responseJSON.message||'Erreur serveur') : 'Echec du chargement du fichier (verifiez la taille ou le format).';
    Swal.fire({icon:'error',title:'Erreur',text:msg,confirmButtonColor:'#23408F'});
  });
});

/* ===== ENVOI MAIL ===== */
function doSendMail(idaudit, email){
  Swal.fire({title:'Envoi en cours...', allowOutsideClick:false, didOpen:function(){ Swal.showLoading(); }});
  apiPost({action:'send_mail',idaudit:idaudit,email_dest:email}).done(function(res){
    Swal.close();
    if(res.success){
      Swal.fire({icon:'success',title:'Mail envoye',text:res.message,confirmButtonColor:'#1E9C4B',timer:3000,timerProgressBar:true});
      loadList();
    } else {
      Swal.fire({icon:'error',title:'Echec envoi',text:res.message,confirmButtonColor:'#23408F'});
    }
  }).fail(function(jqXHR){
    Swal.close();
    const msg=jqXHR.responseJSON?(jqXHR.responseJSON.message||'Erreur serveur'):'Erreur reseau - verifiez la configuration SMTP.';
    Swal.fire({icon:'error',title:'Erreur',text:msg,confirmButtonColor:'#23408F'});
  });
}

/* ===== PDF ===== */
$(document).on('click','.btn-pdf',function(){
  const idaudit=$(this).data('audit'), num=$(this).data('num')||'';
  const url=AGAI_BASE+'/api/notifications?serve=1&idaudit='+idaudit;
  $('#pdfTitle').text('Lettre - '+num);
  $('#pdfFrame').attr('src',url);
  $('#pdfDl').attr('href',url+'&dl=1');
  $('#pdfPrint').off('click').on('click',function(){ document.getElementById('pdfFrame').contentWindow.print(); });
  new bootstrap.Modal('#pdfModal').show();
});
$('#pdfModal').on('hidden.bs.modal',function(){ $('#pdfFrame').attr('src',''); });

/* ===== DEMARRAGE ===== */
loadList();
(function(){
  let v='0'; try{v=localStorage.getItem('agai_stats_notif')||'0';}catch(e){}
  if(v==='1') setStatsVisible(true);
})();
</script>