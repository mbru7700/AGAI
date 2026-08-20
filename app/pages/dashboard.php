<?php
/**
 * Tableau de bord AGAI - Design Modern Amélioré
 * Version avec esthétique premium, animations subtiles et meilleure hiérarchie visuelle
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardPage('dashboard');
$user      = $_SESSION['user'];
$csrf      = Security::generateCSRF();
$db        = Database::getInstance();
$role      = Rbac::role();
$isCI      = in_array($role, ['admin','chef_inspecteur'], true);
$pageTitle = 'Tableau de bord';
$active    = 'dashboard';
$pageIcon  = 'bi-speedometer2';
require_once INCLUDES_PATH . '/layout_head.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ============================================
   VARIABLES & RESET
   ============================================ */
:root {
    --dash-primary: #23408F;
    --dash-primary-light: #e8f0fe;
    --dash-secondary: #1E9C4B;
    --dash-gold: #F3C300;
    --dash-danger: #D32F2F;
    --dash-purple: #5a189a;
    --dash-dark: #2C3E50;
    --dash-muted: #7b8aa0;
    --dash-bg: #f5f7fa;
    --dash-card-shadow: 0 2px 12px rgba(35,64,143,.06);
    --dash-card-shadow-hover: 0 8px 30px rgba(35,64,143,.12);
    --dash-radius: 14px;
    --dash-transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ============================================
   HEADER
   ============================================ */
.dash-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.dash-header-left h1 {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--dash-dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.dash-header-left h1 i {
    color: var(--dash-primary);
}
.dash-header-left .sub {
    color: var(--dash-muted);
    font-size: .88rem;
    margin-top: 2px;
}
.dash-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.dash-badge-time {
    background: var(--dash-primary-light);
    color: var(--dash-primary);
    padding: 6px 14px;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ============================================
   LAUNCHER (pour CI)
   ============================================ */
.launcher-modern {
    background: linear-gradient(135deg, #23408F 0%, #1a3270 50%, #14264f 100%);
    border-radius: var(--dash-radius);
    padding: 20px 24px;
    margin-bottom: 22px;
    box-shadow: 0 6px 24px rgba(35,64,143,.25);
    position: relative;
    overflow: hidden;
}
.launcher-modern::before {
    content: '';
    position: absolute;
    top: -60%;
    right: -20%;
    width: 400px;
    height: 400px;
    border-radius: 50%;
    background: rgba(243,195,0,.05);
    pointer-events: none;
}
.launcher-modern .lh {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
    position: relative;
    z-index: 1;
}
.launcher-modern .lh i {
    font-size: 1.6rem;
    color: var(--dash-gold);
}
.launcher-modern .lh .lt {
    font-weight: 700;
    font-size: 1.05rem;
    color: #fff;
}
.launcher-modern .lh .ls {
    font-size: .82rem;
    color: rgba(255,255,255,.7);
}
.launcher-modern .nat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    position: relative;
    z-index: 1;
}
.launcher-modern .nat-tile {
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 12px;
    padding: 14px 10px;
    text-align: center;
    cursor: pointer;
    color: #fff;
    transition: var(--dash-transition);
}
.launcher-modern .nat-tile:hover {
    background: #fff;
    color: var(--dash-primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,.2);
}
.launcher-modern .nat-tile i {
    font-size: 1.4rem;
    display: block;
    margin-bottom: 5px;
    color: var(--dash-gold);
}
.launcher-modern .nat-tile:hover i {
    color: var(--dash-primary);
}
.launcher-modern .nat-tile .nt {
    font-size: .78rem;
    font-weight: 600;
    line-height: 1.2;
}

/* ============================================
   FILTER SECTION
   ============================================ */
.filter-modern {
    background: #fff;
    border: 1px solid #eef1f6;
    border-radius: var(--dash-radius);
    padding: 16px 20px;
    margin-bottom: 20px;
    box-shadow: var(--dash-card-shadow);
    transition: var(--dash-transition);
}
.filter-modern:hover {
    box-shadow: var(--dash-card-shadow-hover);
}
.filter-modern .fh {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.filter-modern .fh-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.filter-modern .fh-left i {
    font-size: 1.1rem;
    color: var(--dash-primary);
}
.filter-modern .fh-left strong {
    font-size: .85rem;
    color: var(--dash-dark);
}
.filter-modern .fh-left .badge-filter {
    font-size: .7rem;
    font-weight: 700;
    padding: 2px 12px;
    border-radius: 50px;
    background: var(--dash-primary-light);
    color: var(--dash-primary);
}
.filter-modern .fh-right .btn-reset {
    font-size: .75rem;
    padding: 4px 14px;
    border-radius: 50px;
    border: 1px solid #e8edf5;
    background: #f8f9fa;
    color: var(--dash-muted);
    transition: var(--dash-transition);
}
.filter-modern .fh-right .btn-reset:hover {
    background: #fff;
    border-color: var(--dash-primary);
    color: var(--dash-primary);
}
.filter-modern .filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}
.filter-modern .filter-item .fl {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--dash-muted);
    margin-bottom: 3px;
}
.filter-modern .filter-item select,
.filter-modern .filter-item .select2-container {
    width: 100% !important;
}
.filter-modern .select2-container--bootstrap-5 .select2-selection {
    min-height: 34px;
    border-color: #e8edf5;
    border-radius: 8px;
    font-size: .82rem;
}
.filter-modern .select2-container--bootstrap-5 .select2-selection:focus {
    border-color: var(--dash-primary);
}

/* ============================================
   KPI CARDS
   ============================================ */
.kpi-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
.kpi-modern .kpi {
    background: #fff;
    border: 1px solid #eef1f6;
    border-radius: var(--dash-radius);
    padding: 14px 16px;
    box-shadow: var(--dash-card-shadow);
    transition: var(--dash-transition);
    position: relative;
    overflow: hidden;
}
.kpi-modern .kpi:hover {
    transform: translateY(-3px);
    box-shadow: var(--dash-card-shadow-hover);
}
.kpi-modern .kpi::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    border-radius: 0 2px 2px 0;
}
.kpi-modern .kpi .kpi-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.kpi-modern .kpi .kpi-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex: 0 0 40px;
}
.kpi-modern .kpi .kpi-num {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1;
    color: var(--dash-dark);
}
.kpi-modern .kpi .kpi-lbl {
    font-size: .7rem;
    color: var(--dash-muted);
    margin-top: 2px;
}
.kpi-modern .kpi .kpi-pct {
    font-size: .7rem;
    font-weight: 700;
}
.kpi-modern .kpi.kpi-blue::before { background: var(--dash-primary); }
.kpi-modern .kpi.kpi-green::before { background: var(--dash-secondary); }
.kpi-modern .kpi.kpi-gold::before { background: var(--dash-gold); }
.kpi-modern .kpi.kpi-red::before { background: var(--dash-danger); }
.kpi-modern .kpi.kpi-dark::before { background: var(--dash-dark); }
.kpi-modern .kpi.kpi-purple::before { background: var(--dash-purple); }
.kpi-modern .kpi .kpi-icon.blue { background: rgba(35,64,143,.10); color: var(--dash-primary); }
.kpi-modern .kpi .kpi-icon.green { background: rgba(30,156,75,.10); color: var(--dash-secondary); }
.kpi-modern .kpi .kpi-icon.gold { background: rgba(243,195,0,.15); color: #b58a00; }
.kpi-modern .kpi .kpi-icon.red { background: rgba(211,47,47,.10); color: var(--dash-danger); }
.kpi-modern .kpi .kpi-icon.dark { background: rgba(44,62,80,.08); color: var(--dash-dark); }
.kpi-modern .kpi .kpi-icon.purple { background: rgba(90,24,154,.10); color: var(--dash-purple); }

/* ============================================
   CHART CARDS
   ============================================ */
.chart-modern {
    background: #fff;
    border: 1px solid #eef1f6;
    border-radius: var(--dash-radius);
    padding: 16px 18px;
    box-shadow: var(--dash-card-shadow);
    transition: var(--dash-transition);
}
.chart-modern:hover {
    box-shadow: var(--dash-card-shadow-hover);
}
.chart-modern .ch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.chart-modern .ch h6 {
    font-size: .82rem;
    font-weight: 700;
    color: var(--dash-dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.chart-modern .ch h6 i {
    color: var(--dash-primary);
}
.chart-modern canvas {
    max-height: 180px;
    width: 100% !important;
}

/* ============================================
   TAUX BARS
   ============================================ */
.taux-modern .tb {
    margin-bottom: 8px;
}
.taux-modern .tb-label {
    display: flex;
    justify-content: space-between;
    font-size: .78rem;
    font-weight: 600;
    margin-bottom: 2px;
}
.taux-modern .tb-bar {
    height: 8px;
    background: #eef1f6;
    border-radius: 4px;
    overflow: hidden;
}
.taux-modern .tb-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .8s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ============================================
   RANKING
   ============================================ */
.rank-modern .ri {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border-radius: 8px;
    margin-bottom: 4px;
    transition: var(--dash-transition);
}
.rank-modern .ri:hover {
    background: var(--dash-primary-light);
}
.rank-modern .ri .rank-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    font-weight: 800;
    flex: 0 0 24px;
}
.rank-modern .ri .rank-bar-wrap {
    flex: 1;
    height: 7px;
    background: #eef1f6;
    border-radius: 4px;
    overflow: hidden;
}
.rank-modern .ri .rank-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .6s ease;
}
.rank-modern .ri .rank-pct {
    font-size: .8rem;
    font-weight: 700;
    flex: 0 0 44px;
    text-align: right;
}

/* ============================================
   ALERTS & ACTIVITIES
   ============================================ */
.alert-modern {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 10px;
    margin-bottom: 5px;
    font-size: .82rem;
    transition: var(--dash-transition);
}
.alert-modern:hover {
    transform: translateX(4px);
}
.alert-modern.danger { background: #fff5f5; border-left: 3px solid var(--dash-danger); }
.alert-modern.warn { background: #fffbeb; border-left: 3px solid var(--dash-gold); }
.alert-modern.info { background: var(--dash-primary-light); border-left: 3px solid var(--dash-primary); }
.alert-modern.success { background: #f0fdf4; border-left: 3px solid var(--dash-secondary); }

.act-modern {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 10px;
    border-bottom: 1px solid #f5f7fa;
    font-size: .82rem;
    transition: var(--dash-transition);
}
.act-modern:hover {
    background: #f8f9fa;
}
.act-modern:last-child { border-bottom: none; }
.act-modern .act-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex: 0 0 8px;
    margin-top: 5px;
}

/* ============================================
   DOMAINES
   ============================================ */
.domaine-tag {
    display: inline-block;
    padding: .35em .9em;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 600;
    margin: 3px;
    background: var(--dash-primary-light);
    color: var(--dash-primary);
    transition: var(--dash-transition);
}
.domaine-tag:hover {
    background: var(--dash-primary);
    color: #fff;
    transform: scale(1.04);
}

/* ============================================
   PROFILE CARD
   ============================================ */
.profile-modern .avatar-lg {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(243,195,0,.18);
    color: #9a7d00;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.2rem;
    flex: 0 0 52px;
}
.profile-modern .avatar-lg img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 992px) {
    .kpi-modern { grid-template-columns: repeat(3, 1fr); }
    .filter-modern .filter-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .kpi-modern { grid-template-columns: repeat(2, 1fr); }
    .filter-modern .filter-grid { grid-template-columns: repeat(2, 1fr); }
    .dash-header-left h1 { font-size: 1.3rem; }
    .launcher-modern .nat-grid { grid-template-columns: repeat(3, 1fr); }
    .launcher-modern { padding: 16px; }
}
@media (max-width: 576px) {
    .kpi-modern { grid-template-columns: 1fr 1fr; gap: 8px; }
    .kpi-modern .kpi { padding: 10px 12px; }
    .kpi-modern .kpi .kpi-num { font-size: 1.2rem; }
    .kpi-modern .kpi .kpi-icon { width: 32px; height: 32px; font-size: .9rem; }
    .filter-modern .filter-grid { grid-template-columns: 1fr; }
    .launcher-modern .nat-grid { grid-template-columns: repeat(2, 1fr); }
    .launcher-modern .lh .ls { display: none; }
}

/* ============================================
   SCROLLBAR
   ============================================ */
.chart-modern::-webkit-scrollbar,
.rank-modern::-webkit-scrollbar,
.act-scroll::-webkit-scrollbar {
    width: 4px;
}
.chart-modern::-webkit-scrollbar-track,
.rank-modern::-webkit-scrollbar-track,
.act-scroll::-webkit-scrollbar-track {
    background: #f0f2f5;
    border-radius: 4px;
}
.chart-modern::-webkit-scrollbar-thumb,
.rank-modern::-webkit-scrollbar-thumb,
.act-scroll::-webkit-scrollbar-thumb {
    background: var(--dash-primary);
    border-radius: 4px;
}
.act-scroll {
    max-height: 240px;
    overflow-y: auto;
}
.rank-scroll {
    max-height: 220px;
    overflow-y: auto;
}

/* ============================================
   TOOLTIP
   ============================================ */
.kpi-tooltip {
    cursor: help;
    position: relative;
}

/* ============================================
   CADRE MODAL
   ============================================ */
.cadre-opt {
    display: block;
    border: 1px solid #e8edf5;
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 7px;
    cursor: pointer;
    transition: all .13s;
}
.cadre-opt:hover,
.cadre-opt.sel {
    border-color: var(--dash-primary);
    background: rgba(35,64,143,.06);
}
.cadre-opt input {
    margin-right: 8px;
}
</style>

<!-- ============================================
HEADER
============================================ -->
<div class="dash-header">
    <div class="dash-header-left">
        <h1>
            <i class="bi bi-speedometer2"></i>
            Tableau de bord
        </h1>
        <div class="sub">
            <i class="bi bi-graph-up-arrow me-1" style="color:var(--dash-secondary);"></i>
            Intelligence décisionnelle · Surveillance Continue de la Sécurité Aérienne
        </div>
    </div>
    <div class="dash-header-right">
        <span class="dash-badge-time">
            <i class="bi bi-clock"></i>
            <?php echo date('d/m/Y H:i'); ?>
        </span>
        <span class="badge" style="background:var(--dash-primary-light);color:var(--dash-primary);font-size:.72rem;padding:6px 14px;border-radius:50px;">
            <i class="bi bi-shield-check me-1"></i>
            <?php echo Security::escape(Rbac::roleLabel($user['role']??'')); ?>
        </span>
    </div>
</div>

<!-- ============================================
LAUNCHER (CI seulement)
============================================ -->
<?php if ($isCI): ?>
<div class="launcher-modern">
    <div class="lh">
        <i class="bi bi-plus-circle-fill"></i>
        <div>
            <div class="lt">Programmer un acte de supervision</div>
            <div class="ls">Choisissez la nature de la supervision pour démarrer la planification</div>
        </div>
    </div>
    <div class="nat-grid">
        <div class="nat-tile" data-type="audit">
            <i class="bi bi-clipboard-check"></i>
            <div class="nt">Audit</div>
        </div>
        <div class="nat-tile" data-type="inspection_programmee">
            <i class="bi bi-calendar-check"></i>
            <div class="nt">Inspection<br>programmée</div>
        </div>
        <div class="nat-tile" data-type="inspection_non_programmee">
            <i class="bi bi-calendar-x"></i>
            <div class="nt">Inspection<br>non programmée</div>
        </div>
        <div class="nat-tile" data-type="demonstration">
            <i class="bi bi-easel"></i>
            <div class="nt">Démonstration</div>
        </div>
        <div class="nat-tile" data-type="test">
            <i class="bi bi-bullseye"></i>
            <div class="nt">Test</div>
        </div>
        <div class="nat-tile" data-type="investigation">
            <i class="bi bi-search"></i>
            <div class="nt">Investigation</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
FILTRES
============================================ -->
<div class="filter-modern">
    <div class="fh">
        <div class="fh-left">
            <i class="bi bi-funnel-fill"></i>
            <strong>Filtres du tableau de bord</strong>
            <span class="badge-filter" id="filterBadge">Tous</span>
        </div>
        <div class="fh-right">
            <button class="btn-reset" id="btnResetAll">
                <i class="bi bi-x-lg me-1"></i>Réinitialiser
            </button>
        </div>
    </div>
    <div class="filter-grid">
        <div class="filter-item">
            <div class="fl">Année</div>
            <select id="f_annee" style="width:100%"><option value="">Toutes</option></select>
        </div>
        <div class="filter-item">
            <div class="fl">Mois</div>
            <select id="f_mois" class="form-select form-select-sm" style="font-size:.82rem">
                <option value="">Tous</option>
                <?php $mn=['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];
                foreach($mn as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <div class="fl">Opérateur</div>
            <select id="f_orga" style="width:100%"><option value="">Tous</option></select>
        </div>
        <div class="filter-item">
            <div class="fl">Domaine</div>
            <select id="f_domaine" style="width:100%"><option value="">Tous</option></select>
        </div>
        <div class="filter-item">
            <div class="fl">Inspecteur</div>
            <select id="f_insp" style="width:100%"><option value="">Tous</option></select>
        </div>
        <div class="filter-item">
            <div class="fl">Statut</div>
            <select id="f_statut" style="width:100%">
                <option value="">Tous</option>
                <option value="1">Planifié</option>
                <option value="2">Reporté</option>
                <option value="3">Effectué</option>
                <option value="4">Suspendu</option>
                <option value="6">Annulé</option>
                <option value="7">Inopiné</option>
            </select>
        </div>
        <div class="filter-item">
            <div class="fl">Nature</div>
            <select id="f_nature" style="width:100%">
                <option value="">Toutes</option>
                <option value="audit">Audit</option>
                <option value="inspection_programmee">Inspection programmée</option>
                <option value="inspection_non_programmee">Inspection non programmée</option>
                <option value="demonstration">Démonstration</option>
                <option value="test">Test</option>
                <option value="investigation">Investigation</option>
            </select>
        </div>
    </div>
</div>

<!-- ============================================
KPI ROW 1
============================================ -->
<div class="kpi-modern" id="kpiRow">
    <div class="kpi kpi-blue kpi-tooltip" title="Total des actes de supervision dans la sélection courante">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_total">-</div>
                <div class="kpi-lbl">Total actes</div>
            </div>
            <div class="kpi-icon blue"><i class="bi bi-clipboard-data-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-blue kpi-tooltip" title="Audits en statut Planifié">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_plan">-</div>
                <div class="kpi-lbl">Planifiés</div>
                <div class="kpi-pct" id="p_plan" style="color:var(--dash-primary)"></div>
            </div>
            <div class="kpi-icon blue"><i class="bi bi-calendar-check-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-green kpi-tooltip" title="Audits effectivement réalisés (statut Effectué)">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_eff">-</div>
                <div class="kpi-lbl">Effectués</div>
                <div class="kpi-pct" id="p_eff" style="color:var(--dash-secondary)"></div>
            </div>
            <div class="kpi-icon green"><i class="bi bi-check-circle-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-gold kpi-tooltip" title="Audits reportés">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_rep">-</div>
                <div class="kpi-lbl">Reportés</div>
                <div class="kpi-pct" id="p_rep" style="color:#b58a00"></div>
            </div>
            <div class="kpi-icon gold"><i class="bi bi-arrow-clockwise"></i></div>
        </div>
    </div>
    <div class="kpi kpi-red kpi-tooltip" title="Audits suspendus">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_sus">-</div>
                <div class="kpi-lbl">Suspendus</div>
                <div class="kpi-pct" id="p_sus" style="color:var(--dash-danger)"></div>
            </div>
            <div class="kpi-icon red"><i class="bi bi-pause-circle-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-dark kpi-tooltip" title="Audits annulés">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_ann">-</div>
                <div class="kpi-lbl">Annulés</div>
                <div class="kpi-pct" id="p_ann" style="color:var(--dash-dark)"></div>
            </div>
            <div class="kpi-icon dark"><i class="bi bi-x-circle-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-purple kpi-tooltip" title="Audits inopinés (non programmés)">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_inop">-</div>
                <div class="kpi-lbl">Inopinés</div>
                <div class="kpi-pct" id="p_inop" style="color:var(--dash-purple)"></div>
            </div>
            <div class="kpi-icon purple"><i class="bi bi-lightning-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-green kpi-tooltip" title="Taux d'exécution = Effectués / Total × 100">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_texec" style="color:var(--dash-secondary)">- %</div>
                <div class="kpi-lbl">Taux exécution</div>
            </div>
            <div class="kpi-icon green"><i class="bi bi-speedometer2"></i></div>
        </div>
    </div>
</div>

<!-- ============================================
KPI ROW 2 : Notifications + Rapports + QRE + Conformité
============================================ -->
<div class="kpi-modern" id="kpiRow2" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <div class="kpi kpi-blue kpi-tooltip" title="Audits avec lettre de notification jointe">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_notif">-</div>
                <div class="kpi-lbl">Lettres notification</div>
                <div class="kpi-pct" id="p_notif" style="color:var(--dash-primary)"></div>
            </div>
            <div class="kpi-icon blue"><i class="bi bi-envelope-paper-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-green kpi-tooltip" title="Audits avec rapport d'acte joint">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_rapport">-</div>
                <div class="kpi-lbl">Rapports joints</div>
                <div class="kpi-pct" id="p_rapport" style="color:var(--dash-secondary)"></div>
            </div>
            <div class="kpi-icon green"><i class="bi bi-file-earmark-pdf-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-gold kpi-tooltip" title="Questionnaires QRE remplis">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_qre">-</div>
                <div class="kpi-lbl">QRE remplis</div>
                <div class="kpi-pct" id="p_qre" style="color:#b58a00"></div>
            </div>
            <div class="kpi-icon gold"><i class="bi bi-clipboard2-check-fill"></i></div>
        </div>
    </div>
    <div class="kpi kpi-green kpi-tooltip" title="Taux de conformité moyen = NCS/(NCS+NCNS)×100">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_tconf" style="color:var(--dash-secondary)">- %</div>
                <div class="kpi-lbl">Taux conformité</div>
            </div>
            <div class="kpi-icon green"><i class="bi bi-graph-up-arrow"></i></div>
        </div>
    </div>
    <div class="kpi kpi-red kpi-tooltip" title="Taux de non-conformité moyen">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_tnconf" style="color:var(--dash-danger)">- %</div>
                <div class="kpi-lbl">Taux non-conf.</div>
            </div>
            <div class="kpi-icon red"><i class="bi bi-graph-down-arrow"></i></div>
        </div>
    </div>
    <div class="kpi kpi-blue kpi-tooltip" title="Total NCR cumulé sur tous les rapports">
        <div class="kpi-inner">
            <div>
                <div class="kpi-num" id="k_ncr">-</div>
                <div class="kpi-lbl">Total critères NCR</div>
            </div>
            <div class="kpi-icon blue"><i class="bi bi-list-ul"></i></div>
        </div>
    </div>
</div>

<!-- ============================================
LIGNE 1 : Barres années + Donut statuts + Taux bars
============================================ -->
<div class="row g-3 mb-3">
    <div class="col-md-5">
        <div class="chart-modern" style="height:260px;">
            <div class="ch">
                <h6><i class="bi bi-bar-chart-fill"></i>Actes par année et statut</h6>
            </div>
            <canvas id="chartAnnee"></canvas>
        </div>
    </div>
    <div class="col-md-3">
        <div class="chart-modern" style="height:260px;">
            <div class="ch">
                <h6><i class="bi bi-pie-chart-fill"></i>Répartition statuts</h6>
            </div>
            <canvas id="chartStatut"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-modern" style="height:260px;overflow-y:auto;">
            <div class="ch">
                <h6><i class="bi bi-graph-up"></i>Taux d'exécution par année</h6>
            </div>
            <div class="taux-modern" id="tauxBars">
                <div class="text-muted text-center py-3 small">Chargement...</div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
LIGNE 2 : Par mois + Par nature + Taux conformité
============================================ -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="chart-modern" style="height:230px;">
            <div class="ch">
                <h6><i class="bi bi-calendar3"></i>Répartition par mois</h6>
            </div>
            <canvas id="chartMois"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-modern" style="height:230px;">
            <div class="ch">
                <h6><i class="bi bi-clipboard-data"></i>Répartition par nature</h6>
            </div>
            <canvas id="chartNature"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-modern" style="height:230px;">
            <div class="ch">
                <h6><i class="bi bi-speedometer"></i>Conformité par opérateur</h6>
            </div>
            <canvas id="chartConf"></canvas>
        </div>
    </div>
</div>

<!-- ============================================
LIGNE 3 : Classement + Alertes + Activités
============================================ -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="chart-modern" style="height:290px;display:flex;flex-direction:column;">
            <div class="ch">
                <h6><i class="bi bi-trophy-fill" style="color:#F3C300;"></i>Classement opérateurs (conformité)</h6>
            </div>
            <div class="rank-modern rank-scroll" id="rankOperateurs" style="flex:1;overflow-y:auto;">
                <div class="text-muted text-center py-3 small">Chargement...</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-modern" style="height:290px;display:flex;flex-direction:column;">
            <div class="ch">
                <h6><i class="bi bi-exclamation-triangle-fill" style="color:#F3C300;"></i>Points d'attention</h6>
            </div>
            <div id="alertesBox" style="flex:1;overflow-y:auto;">
                <div class="text-muted text-center py-3 small">Chargement...</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-modern" style="height:290px;display:flex;flex-direction:column;">
            <div class="ch">
                <h6><i class="bi bi-clock-history"></i>Activités récentes</h6>
            </div>
            <div class="act-scroll" id="activitesBox" style="flex:1;overflow-y:auto;">
                <div class="text-muted text-center py-3 small">Chargement...</div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
LIGNE 4 : Indicateurs de decision + Profil
============================================ -->
<div class="row g-3">
    <div class="col-md-8">
        <div class="chart-modern">
            <div class="ch">
                <h6><i class="bi bi-speedometer2"></i>Indicateurs cles de decision</h6>
                <span class="ms-auto text-muted" style="font-size:.72rem">Mis a jour en direct</span>
            </div>
            <div class="row g-2" id="kpiDecision">
                <div class="col-12 text-muted small text-center py-3">Chargement...</div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-7">
                    <div style="font-size:.76rem;font-weight:700;color:#23408F;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">
                        <i class="bi bi-people me-1"></i>Charge par inspecteur (responsable d'audit)</div>
                    <div id="chargeInsp" style="max-height:190px;overflow-y:auto"></div>
                </div>
                <div class="col-md-5">
                    <div style="font-size:.76rem;font-weight:700;color:#D32F2F;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">
                        <i class="bi bi-exclamation-octagon me-1"></i>Operateurs a risque (non-conformites)</div>
                    <div id="orgaRisque" style="max-height:190px;overflow-y:auto"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-modern profile-modern">
            <div class="ch">
                <h6><i class="bi bi-person-circle"></i>Mon profil</h6>
            </div>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="avatar-lg">
                    <?php echo strtoupper(mb_substr($user['prenom']??'U',0,1).mb_substr($user['nom']??'',0,1)); ?>
                </div>
                <div>
                    <div class="fw-bold" style="font-size:.92rem;color:var(--dash-dark);">
                        <?php echo Security::escape(trim(($user['prenom']??'').' '.($user['nom']??''))); ?>
                    </div>
                    <div class="text-muted small"><?php echo Security::escape($user['email']??''); ?></div>
                    <span class="badge" style="background:var(--dash-primary-light);color:var(--dash-primary);font-size:.7rem;padding:3px 12px;border-radius:50px;margin-top:3px;">
                        <?php echo Security::escape(Rbac::roleLabel($user['role']??'')); ?>
                    </span>
                </div>
            </div>
            <div class="row g-2 small">
                <div class="col-6">
                    <div class="text-muted" style="font-size:.7rem;">Matricule</div>
                    <div class="fw-bold" style="font-size:.82rem;"><?php echo Security::escape($user['matricule']??'N/A'); ?></div>
                </div>
                <div class="col-6">
                    <div class="text-muted" style="font-size:.7rem;">Dernière connexion</div>
                    <div class="fw-bold" style="font-size:.78rem;">
                        <?php echo !empty($user['last_login']) ? date('d/m H:i',strtotime($user['last_login'])) : 'N/A'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
MODALE CADRE
============================================ -->
<div class="modal fade" id="cadreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-question-circle me-2" style="color:var(--dash-primary);"></i>Dans quel cadre ?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Nature : <b id="cadreNature" style="color:var(--dash-primary);"></b>. Sélectionnez le cadre.</p>
                <div id="cadreList">
                    <?php foreach(['certification'=>'Certification','homologation'=>'Homologation','reconnaissance'=>'Reconnaissance','renouvellement'=>'Renouvellement','surveillance_continue'=>'Surveillance continue','traitement_evenement'=>"Traitement d'un événement",'fermeture_provisoire'=>'Fermeture provisoire','fermeture_definitive'=>'Fermeture définitive','delivrance_autorisation'=>"Délivrance d'une autorisation"] as $val=>$lbl): ?>
                    <label class="cadre-opt"><input type="radio" name="cadre" value="<?php echo $val; ?>"><?php echo $lbl; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-anac" id="cadreContinue" disabled>
                    <i class="bi bi-arrow-right-circle me-1"></i>Continuer
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/layout_foot.php'; ?>
<script>
const CSRF = '<?php echo Security::escape($csrf); ?>';
const API  = AGAI_BASE + '/api/audits';
let ALL_AUDITS = [], chartAnnee=null, chartStatut=null, chartMois=null, chartNature=null, chartConf=null;

function apiPost(d){ return $.post(API, Object.assign({csrf_token:CSRF},d), null, 'json'); }
function fmtDate(s){ if(!s||s==='0000-00-00') return '-'; const p=String(s).substring(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

const TYPES={audit:'Audit',inspection_programmee:'Insp. prog.',inspection_non_programmee:'Insp. non prog.',demonstration:'Demo',test:'Test',investigation:'Investigation'};
const STATUT_LABEL={1:'Planifié',2:'Reporté',3:'Effectué',4:'Suspendu',6:'Annulé',7:'Inopiné'};
const MOIS=['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];

// ====== PIE LABEL PLUGIN ======
Chart.register({id:'pieLbls',afterDatasetDraw(chart){
    const {ctx,data}=chart; if(chart.config.type!=='doughnut'&&chart.config.type!=='pie') return;
    const total=data.datasets[0].data.reduce(function(a,b){return a+b;},0); if(!total) return;
    chart.getDatasetMeta(0).data.forEach(function(arc,i){
        const val=data.datasets[0].data[i]; if(!val) return;
        const pct=Math.round(val/total*100); if(pct<3) return;
        const ang=arc.startAngle+(arc.endAngle-arc.startAngle)/2;
        const r=(arc.outerRadius-(arc.outerRadius-(arc.innerRadius||0))*0.5)*0.75;
        const x=arc.x+Math.cos(ang)*r, y=arc.y+Math.sin(ang)*r;
        ctx.save(); ctx.fillStyle='#fff'; ctx.font='bold 8px Candara,Arial,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText(val+' ('+pct+'%)',x,y); ctx.restore();
    });
}});

// ====== BAR LABEL PLUGIN ======
Chart.register({id:'barLbls',afterDatasetDraw(chart){
    if(chart.config.type!=='bar') return;
    const {ctx}=chart;
    chart.data.datasets.forEach(function(ds,dsIdx){
        const meta=chart.getDatasetMeta(dsIdx); if(meta.hidden) return;
        meta.data.forEach(function(bar,i){
            const val=ds.data[i]; if(!val) return;
            const barH=bar.base-bar.y;
            if(barH<14) return;
            ctx.save(); ctx.fillStyle='rgba(255,255,255,.9)'; ctx.font='bold 8px Candara,Arial,sans-serif';
            ctx.textAlign='center'; ctx.textBaseline='middle';
            ctx.fillText(val, bar.x, bar.y+barH*0.5); ctx.restore();
        });
    });
}});

// ====== FILTRES ======
['#f_annee','#f_orga','#f_domaine','#f_insp','#f_statut','#f_nature'].forEach(function(id){
    $(id).select2({theme:'bootstrap-5',width:'100%',allowClear:true,placeholder:'Tous'});
});
$('#f_annee,#f_orga,#f_domaine,#f_insp,#f_statut,#f_nature,#f_mois').on('change',function(){ applyFilters(); });
$('#btnResetAll').on('click',function(){
    ['#f_annee','#f_orga','#f_domaine','#f_insp','#f_statut','#f_nature'].forEach(function(id){$(id).val('').trigger('change');});
    $('#f_mois').val(''); applyFilters();
});

function getFiltered(){
    const fY=$('#f_annee').val(), fM=$('#f_mois').val(), fO=$('#f_orga').val();
    const fD=$('#f_domaine').val(), fI=$('#f_insp').val(), fS=$('#f_statut').val(), fN=$('#f_nature').val();
    return ALL_AUDITS.filter(function(a){
        const yr=(a.date_previsionnelle||'').substring(0,4);
        const mo=(a.date_previsionnelle||'').substring(5,7);
        if(fY && yr!==fY) return false;
        if(fM && mo!==fM) return false;
        if(fO && String(a.idorga)!==String(fO)) return false;
        if(fS && String(a.statut)!==String(fS)) return false;
        if(fN && a.type_activite!==fN) return false;
        if(fD){
            const hasDom=(a.inspecteurs||[]).some(function(i){return String(i.iddomaine)===String(fD);});
            if(!hasDom) return false;
        }
        if(fI){
            const hasInsp=(a.inspecteurs||[]).some(function(i){return String(i.idinspecteur)===String(fI);});
            if(!hasInsp) return false;
        }
        return true;
    });
}

function fillFilters(){
    const seenY={}, seenO={}, seenD={}, seenI={};
    let oY='<option value="">Toutes</option>', oO='<option value="">Tous</option>';
    let oD='<option value="">Tous</option>', oI='<option value="">Tous</option>';
    ALL_AUDITS.forEach(function(a){
        const yr=(a.date_previsionnelle||'').substring(0,4);
        if(yr&&yr>='2020'&&!seenY[yr]){ seenY[yr]=1; oY+='<option value="'+yr+'">'+yr+'</option>'; }
        if(!seenO[a.idorga]&&a.nomorga){ seenO[a.idorga]=1; oO+='<option value="'+a.idorga+'">'+esc(a.nomorga)+'</option>'; }
        (a.inspecteurs||[]).forEach(function(i){
            if(!seenD[i.iddomaine]&&i.nomdomaine){ seenD[i.iddomaine]=1; oD+='<option value="'+i.iddomaine+'">'+esc(i.nomdomaine)+'</option>'; }
            if(!seenI[i.idinspecteur]&&i.nom){ seenI[i.idinspecteur]=1; oI+='<option value="'+i.idinspecteur+'">'+esc(i.nom)+'</option>'; }
        });
    });
    $('#f_annee').html(oY).trigger('change.select2');
    $('#f_orga').html(oO).trigger('change.select2');
    $('#f_domaine').html(oD).trigger('change.select2');
    $('#f_insp').html(oI).trigger('change.select2');
}

// ====== CALCULS ======
function calcKpi(list){
    const s={total:list.length,planifies:0,effectues:0,reportes:0,suspendus:0,annules:0,inopines:0,
        notif:0,rapport:0,qre_count:0,sumTC:0,sumTNC:0,cntTC:0,sumNCR:0,sumNCS:0,sumNCNS:0};
    list.forEach(function(a){
        const st=Number(a.statut);
        if(st===1)s.planifies++;else if(st===3)s.effectues++;else if(st===2)s.reportes++;
        else if(st===4)s.suspendus++;else if(st===6)s.annules++;else if(st===7)s.inopines++;
        if(a.lettre_notification&&String(a.lettre_notification).trim()) s.notif++;
        if(a.rapport_audit&&String(a.rapport_audit).trim()) s.rapport++;
        s.qre_count += (parseInt(a.nb_qre)||0);
        const ncr=parseInt(a.ncr)||0;
        if(ncr>0){
            s.sumNCR+=ncr;
            s.sumNCS +=(parseInt(a.ncs) ||0);
            s.sumNCNS+=(parseInt(a.ncns)||0);
            const base=(parseInt(a.ncs)||0)+(parseInt(a.ncns)||0);
            if(base>0&&a.taux_conformite!==null&&a.taux_conformite!==''){
                s.sumTC +=parseFloat(a.taux_conformite||0);
                s.sumTNC+=parseFloat(a.taux_non_conformite||0);
                s.cntTC++;
            }
        }
    });
    s.taux_exec=s.total?Math.round(s.effectues/s.total*100):0;
    s.avg_tc=s.cntTC?Math.round(s.sumTC/s.cntTC*10)/10:null;
    s.avg_tnc=s.cntTC?Math.round(s.sumTNC/s.cntTC*10)/10:null;
    return s;
}

function pct(v,t){ return t>0?Math.round(v/t*100):0; }
function destroyC(id){ const el=document.getElementById(id); if(!el) return; const c=Chart.getChart(el); if(c) c.destroy(); }

// ====== RENDU ======
function applyFilters(){
    const list=getFiltered();
    renderKpi(list);
    renderCharts(list);
    renderAlertes(list);
    updateFilterBadge();
}

function updateFilterBadge(){
    const active=[$('#f_annee').val(),$('#f_mois').val(),$('#f_orga').val(),$('#f_domaine').val(),$('#f_insp').val(),$('#f_statut').val(),$('#f_nature').val()].filter(Boolean).length;
    $('#filterBadge').text(active?active+' filtre(s) actif':'Tous').css('background',active?'#23408F':'#e8f0fe').css('color',active?'#fff':'#23408F');
}

function renderKpi(list){
    const s=calcKpi(list);
    const t=s.total||1;
    $('#k_total').text(s.total); $('#k_texec').text(s.taux_exec+'%');
    $('#k_plan').text(s.planifies); $('#p_plan').text(s.planifies?pct(s.planifies,t)+'%':'');
    $('#k_eff').text(s.effectues);  $('#p_eff').text(s.effectues?pct(s.effectues,t)+'%':'');
    $('#k_rep').text(s.reportes);   $('#p_rep').text(s.reportes?pct(s.reportes,t)+'%':'');
    $('#k_sus').text(s.suspendus);  $('#p_sus').text(s.suspendus?pct(s.suspendus,t)+'%':'');
    $('#k_ann').text(s.annules);    $('#p_ann').text(s.annules?pct(s.annules,t)+'%':'');
    $('#k_inop').text(s.inopines);  $('#p_inop').text(s.inopines?pct(s.inopines,t)+'%':'');
    $('#k_notif').text(s.notif);    $('#p_notif').text(s.notif?pct(s.notif,t)+'%':'');
    $('#k_rapport').text(s.rapport);$('#p_rapport').text(s.rapport?pct(s.rapport,t)+'%':'');
    $('#k_qre').text(s.qre_count>0?s.qre_count:'-');
    $('#p_qre').text(s.qre_count>0?pct(s.qre_count,t)+'%':'');
    $('#k_tconf').text(s.avg_tc!==null?s.avg_tc+'%':'-');
    $('#k_tnconf').text(s.avg_tnc!==null?s.avg_tnc+'%':'-');
    $('#k_ncr').text(s.sumNCR>0?s.sumNCR:'-');
}

function renderCharts(list){
    // 1. Par année (barres empilées)
    destroyC('chartAnnee');
    const byAn={};
    list.forEach(function(a){
        const yr=(a.date_previsionnelle||'').substring(0,4); if(!yr||yr<'2020') return;
        if(!byAn[yr]) byAn[yr]={planifies:0,effectues:0,reportes:0,autres:0};
        const st=Number(a.statut);
        if(st===1)byAn[yr].planifies++;else if(st===3)byAn[yr].effectues++;else if(st===2)byAn[yr].reportes++;else byAn[yr].autres++;
    });
    const anKeys=Object.keys(byAn).sort();
    if(anKeys.length){
        new Chart(document.getElementById('chartAnnee'),{type:'bar',data:{
            labels:anKeys,
            datasets:[
                {label:'Plan.',data:anKeys.map(function(y){return byAn[y].planifies;}),backgroundColor:'rgba(35,64,143,.8)',borderRadius:4},
                {label:'Eff.',data:anKeys.map(function(y){return byAn[y].effectues;}),backgroundColor:'rgba(30,156,75,.85)',borderRadius:4},
                {label:'Rep.',data:anKeys.map(function(y){return byAn[y].reportes;}),backgroundColor:'rgba(243,195,0,.8)',borderRadius:4},
                {label:'Autres',data:anKeys.map(function(y){return byAn[y].autres;}),backgroundColor:'rgba(211,47,47,.7)',borderRadius:4},
            ]
        },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:8},boxWidth:9}}},
            scales:{x:{ticks:{font:{size:8}}},y:{beginAtZero:true,ticks:{font:{size:9}}}}}});
        // Taux d'exécution par année
        let tauxHtml='';
        anKeys.forEach(function(yr){
            const tot=byAn[yr].planifies+byAn[yr].effectues+byAn[yr].reportes+byAn[yr].autres;
            const tx=tot?Math.round(byAn[yr].effectues/tot*100):0;
            const col=tx>=80?'#1E9C4B':tx>=60?'#23408F':tx>=40?'#F3C300':'#D32F2F';
            tauxHtml+='<div class="tb"><div class="tb-label"><span>'+yr+'</span><span style="color:'+col+';font-weight:800">'+tx+'%</span></div>'
                +'<div class="tb-bar"><div class="tb-fill" style="width:'+tx+'%;background:'+col+'"></div></div></div>';
        });
        $('#tauxBars').html(tauxHtml||'<div class="text-muted text-center py-3">Aucune donnée</div>');
    } else {
        $('#tauxBars').html('<div class="text-muted text-center py-3">Aucune donnée</div>');
    }

    // 2. Donut statuts
    destroyC('chartStatut');
    const stCnt={1:0,2:0,3:0,4:0,6:0,7:0};
    list.forEach(function(a){ if(stCnt[a.statut]!==undefined) stCnt[a.statut]++; });
    const stVals=Object.values(stCnt);
    if(stVals.some(function(v){return v>0;})){
        new Chart(document.getElementById('chartStatut'),{type:'doughnut',data:{
            labels:Object.keys(stCnt).map(function(k){return STATUT_LABEL[k]||k;}),
            datasets:[{data:stVals,backgroundColor:['rgba(35,64,143,.85)','rgba(243,195,0,.85)','rgba(30,156,75,.85)','rgba(211,47,47,.85)','rgba(56,61,65,.75)','rgba(90,24,154,.75)'],borderColor:'#fff',borderWidth:2}]
        },options:{responsive:true,maintainAspectRatio:false,cutout:'52%',
            plugins:{legend:{position:'bottom',labels:{font:{size:8},boxWidth:9,padding:4}},
            tooltip:{callbacks:{label:function(c){const t=c.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);return ' '+c.parsed+' ('+Math.round(c.parsed/t*100)+'%)';}}}}
        }});
    }

    // 3. Par mois
    destroyC('chartMois');
    const byMo={}; list.forEach(function(a){ const mo=(a.date_previsionnelle||'').substring(5,7); if(!mo) return; byMo[mo]=(byMo[mo]||0)+1; });
    const moKeys=Object.keys(byMo).sort();
    if(moKeys.length){
        new Chart(document.getElementById('chartMois'),{type:'bar',data:{
            labels:moKeys.map(function(m){return MOIS[parseInt(m)]||m;}),
            datasets:[{data:moKeys.map(function(m){return byMo[m];}),backgroundColor:'rgba(35,64,143,.75)',borderRadius:4}]
        },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.parsed.y+' acte(s)';}}},},
            scales:{x:{ticks:{font:{size:8}}},y:{beginAtZero:true,ticks:{stepSize:1,font:{size:9}}}}}});
    }

    // 4. Par nature
    destroyC('chartNature');
    const byNat={}; list.forEach(function(a){ const n=a.type_activite||'autre'; byNat[n]=(byNat[n]||0)+1; });
    const natVals=Object.values(byNat), natLabs=Object.keys(byNat).map(function(k){return TYPES[k]||k;});
    if(natVals.some(function(v){return v>0;})){
        new Chart(document.getElementById('chartNature'),{type:'doughnut',data:{
            labels:natLabs,
            datasets:[{data:natVals,backgroundColor:['rgba(35,64,143,.85)','rgba(30,156,75,.85)','rgba(243,195,0,.85)','rgba(211,47,47,.85)','rgba(90,24,154,.75)','rgba(56,61,65,.7)'],borderColor:'#fff',borderWidth:2}]
        },options:{responsive:true,maintainAspectRatio:false,cutout:'50%',
            plugins:{legend:{position:'bottom',labels:{font:{size:8},boxWidth:9,padding:4}},
            tooltip:{callbacks:{label:function(c){const t=c.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);return ' '+c.parsed+' ('+Math.round(c.parsed/t*100)+'%)';}}}}
        }});
    }

    // 5. Conformité par opérateur
    destroyC('chartConf');
    const byOrgaConf={};
    list.forEach(function(a){
        const ncr=parseInt(a.ncr)||0;
        if(!ncr||!a.nomorga) return;
        const tc=parseFloat(a.taux_conformite);
        if(isNaN(tc)) return;
        const k=a.nomorga;
        if(!byOrgaConf[k]) byOrgaConf[k]={nb:0,sum:0,sumNCS:0,sumNCNS:0};
        byOrgaConf[k].nb++;
        byOrgaConf[k].sum+=tc;
        byOrgaConf[k].sumNCS +=(parseInt(a.ncs) ||0);
        byOrgaConf[k].sumNCNS+=(parseInt(a.ncns)||0);
    });
    const confArr=Object.entries(byOrgaConf).map(function(e){
        return {nomorga:e[0],tc:Math.round(e[1].sum/e[1].nb*10)/10,nb:e[1].nb,sumNCS:e[1].sumNCS,sumNCNS:e[1].sumNCNS};
    }).sort(function(a,b){return b.tc-a.tc;});
    const top8=confArr.slice(0,8);
    if(top8.length){
        new Chart(document.getElementById('chartConf'),{type:'bar',data:{
            labels:top8.map(function(g){return g.nomorga.length>18?g.nomorga.substring(0,16)+'..':g.nomorga;}),
            datasets:[{data:top8.map(function(g){return g.tc;}),
                backgroundColor:top8.map(function(g){return g.tc>=80?'rgba(30,156,75,.85)':g.tc>=60?'rgba(35,64,143,.85)':'rgba(211,47,47,.8)';}),borderRadius:4}]
        },options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false},
            tooltip:{callbacks:{label:function(c){return ' '+c.parsed.x+'% ('+top8[c.dataIndex].nb+' rapp.)';}}}},
            scales:{x:{beginAtZero:true,max:100,ticks:{font:{size:8},callback:function(v){return v+'%';}}},y:{ticks:{font:{size:8}}}}}});
    } else {
        document.getElementById('chartConf').getContext('2d').clearRect(0,0,999,999);
        $(document.getElementById('chartConf')).parent().append('<div class="text-muted text-center small py-3" id="noConfMsg">Aucun rapport avec critères NCR dans la sélection</div>');
    }

    // 6. Classement opérateurs
    let rkHtml='';
    confArr.forEach(function(g,i){
        const medal=i===0?'<i class="bi bi-trophy-fill" style="color:#F3C300;"></i>':i===1?'<span style="color:#aaa;font-weight:800">2</span>':i===2?'<span style="color:#cd7f32;font-weight:800">3</span>':'<span class="text-muted" style="font-size:.76rem">'+(i+1)+'</span>';
        const col=g.tc>=80?'#1E9C4B':g.tc>=60?'#23408F':'#D32F2F';
        rkHtml+='<div class="ri" title="NCS:'+g.sumNCS+' NCNS:'+g.sumNCNS+' sur '+g.nb+' rapport(s)">'
            +'<span class="rank-num" style="background:rgba(35,64,143,.08);color:var(--dash-dark);">'+medal+'</span>'
            +'<div style="flex:1;min-width:0"><div style="font-size:.76rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(g.nomorga)+'</div>'
            +'<div class="rank-bar-wrap"><div class="rank-bar-fill" style="width:'+g.tc+'%;background:'+col+'"></div></div></div>'
            +'<span class="rank-pct" style="color:'+col+'">'+g.tc+'%</span></div>';
    });
    $('#rankOperateurs').html(rkHtml||'<div class="text-muted text-center small py-3"><i class="bi bi-info-circle me-1"></i>Aucun rapport avec critères NCR dans la sélection.<br><small>Joignez des rapports via le module Rapports.</small></div>');
}

function renderAlertes(list){
    const alertes=[];
    const enCours=list.filter(function(a){return Number(a.statut)===1;}).length;
    const reportes=list.filter(function(a){return Number(a.statut)===2;}).length;
    const sansNotif=list.filter(function(a){return !a.lettre_notification||!String(a.lettre_notification).trim();}).length;
    const sansRapport=list.filter(function(a){return !a.rapport_audit||!String(a.rapport_audit).trim();}).length;
    if(reportes>0) alertes.push({type:'warn',icon:'bi-arrow-clockwise',msg:reportes+' audit(s) en statut Reporté - Suivi requis'});
    if(sansNotif>0) alertes.push({type:'info',icon:'bi-bell',msg:sansNotif+' audit(s) sans lettre de notification jointe'});
    if(sansRapport>0) alertes.push({type:'info',icon:'bi-file-earmark-x',msg:sansRapport+' audit(s) sans rapport joint'});
    if(enCours>0) alertes.push({type:'success',icon:'bi-calendar-check',msg:enCours+' audit(s) planifié(s) en attente de réalisation'});
    if(!alertes.length){ $('#alertesBox').html('<div class="text-center text-success small py-3"><i class="bi bi-check-circle-fill me-1"></i>Aucun point d\'attention</div>'); return; }
    $('#alertesBox').html(alertes.map(function(a){
        return '<div class="alert-modern '+a.type+'"><i class="bi '+a.icon+' mt-1"></i><span>'+esc(a.msg)+'</span></div>';
    }).join(''));
}

// ====== CHARGEMENT ======
function loadDashboard(){
    apiPost({action:'list'}).done(function(res){
        if(!res.success) return;
        ALL_AUDITS=res.audits||res.data||[];
        fillFilters();
        applyFilters();
    });
    // Activités récentes
    $.post(AGAI_BASE+'/api/audit-logs',{csrf_token:CSRF,action:'list',limit:8},null,'json').done(function(res){
        if(!res.success||!res.data) return;
        const colors={login:'#23408F',logout:'#9aa7bd',create:'#1E9C4B',update:'#b58a00',delete:'#D32F2F',default:'#9aa7bd'};
        const h=res.data.map(function(a){
            const col=colors[a.action]||colors.default;
            return '<div class="act-modern"><div class="act-dot" style="background:'+col+'"></div><div style="flex:1"><div style="font-size:.81rem;color:var(--dash-dark)">'+esc(a.description||a.action)+'</div><div style="font-size:.72rem;color:#9aa7bd">'+fmtDate(a.created_at)+'</div></div></div>';
        }).join('');
        $('#activitesBox').html(h||'<div class="text-muted text-center small py-3">Aucune activité</div>');
    });
    // Indicateurs cles de decision
    renderDecision();
}

/* ============================================================
 *  INDICATEURS CLES DE DECISION
 *  Calcules a partir des audits deja charges (ALL_AUDITS)
 *  et completes par les statistiques de non-conformites.
 * ============================================================ */
function kpiCard(couleur, icone, valeur, libelle, sous){
    return '<div class="col-6 col-lg-3">'
      + '<div style="background:#fff;border:1px solid #eef1f6;border-left:4px solid '+couleur+';border-radius:12px;padding:11px 13px;height:100%">'
      +   '<div style="display:flex;align-items:center;gap:7px">'
      +     '<i class="bi '+icone+'" style="color:'+couleur+';font-size:1.05rem"></i>'
      +     '<span style="font-size:1.32rem;font-weight:800;color:#2C3E50;line-height:1">'+valeur+'</span>'
      +   '</div>'
      +   '<div style="font-size:.72rem;color:#6b7a90;text-transform:uppercase;letter-spacing:.3px;font-weight:600;margin-top:3px">'+libelle+'</div>'
      +   (sous ? '<div style="font-size:.68rem;color:#93a1b5;margin-top:1px">'+sous+'</div>' : '')
      + '</div></div>';
}

function renderDecision(){
    const A = ALL_AUDITS || [];
    const auj = new Date(); auj.setHours(0,0,0,0);

    let planifies=0, effectues=0, enRetard=0, inopines=0;
    let sumDelaiRapport=0, nbDelaiRapport=0;
    let sumTC=0, nbTC=0, sumNCNS=0;
    const parInsp = {}, parOrga = {};

    A.forEach(function(a){
        const st = parseInt(a.statut||0,10);
        if(st===1) planifies++;
        if(st===3) effectues++;
        if(st===7) inopines++;

        // Retard : prevu dans le passe et toujours non effectue
        const dp = a.date_previsionnelle ? new Date(String(a.date_previsionnelle).substring(0,10)) : null;
        if(dp && !isNaN(dp) && dp < auj && st!==3 && st!==6) enRetard++;

        // Delai de remise du rapport (jours entre realisation et delivrance)
        if(a.date_realisation && a.date_delivrance_rapport){
            const d1=new Date(String(a.date_realisation).substring(0,10));
            const d2=new Date(String(a.date_delivrance_rapport).substring(0,10));
            if(!isNaN(d1)&&!isNaN(d2)&&d2>=d1){ sumDelaiRapport += Math.round((d2-d1)/86400000); nbDelaiRapport++; }
        }

        // Taux de conformite moyen
        if(a.taux_conformite!==null && a.taux_conformite!=='' && !isNaN(parseFloat(a.taux_conformite))){
            sumTC += parseFloat(a.taux_conformite); nbTC++;
        }
        sumNCNS += parseInt(a.ncns||0,10);

        // Charge par inspecteur responsable
        const ri = (a.responsable || a.responsable_nom || '').trim() || 'Non affecte';
        if(!parInsp[ri]) parInsp[ri]={nom:ri, total:0, encours:0};
        parInsp[ri].total++;
        if(st!==3 && st!==6) parInsp[ri].encours++;

        // Operateurs a risque : cumul des criteres non satisfaisants
        const no = (a.nomorga||'Inconnu').trim();
        if(!parOrga[no]) parOrga[no]={nom:no, ncns:0, nb:0};
        parOrga[no].ncns += parseInt(a.ncns||0,10);
        parOrga[no].nb++;
    });

    const totalProg = planifies + effectues;
    const tauxReal  = totalProg ? Math.round(effectues*1000/totalProg)/10 : 0;
    const delaiMoy  = nbDelaiRapport ? Math.round(sumDelaiRapport/nbDelaiRapport) : null;
    const tcMoy     = nbTC ? Math.round(sumTC*10/nbTC)/10 : null;

    // Cartes : rendu immediat, la partie FNC est completee ensuite
    function paint(fnc){
        let h = '';
        h += kpiCard('#1E9C4B','bi-check2-circle', tauxReal+' %', 'Taux de realisation', effectues+' effectue(s) sur '+totalProg);
        h += kpiCard(enRetard>0?'#D32F2F':'#7A8798','bi-clock-history', enRetard, 'Actes en retard', 'Date prevue depassee, non effectues');
        h += kpiCard('#23408F','bi-hourglass-split', (delaiMoy===null?'-':delaiMoy+' j'), 'Delai moyen de rapport', 'Entre realisation et remise');
        h += kpiCard((tcMoy!==null&&tcMoy<70)?'#E8890C':'#1E9C4B','bi-percent', (tcMoy===null?'-':tcMoy+' %'), 'Conformite moyenne', nbTC+' rapport(s) avec criteres');
        if(fnc){
            h += kpiCard(fnc.ouvertes>0?'#E8890C':'#1E9C4B','bi-folder2-open', fnc.ouvertes, 'FNC ouvertes', fnc.total+' fiche(s) au total');
            h += kpiCard(fnc.critiques>0?'#D32F2F':'#7A8798','bi-exclamation-octagon', fnc.critiques, 'FNC critiques', 'Reponse immediate exigee');
            h += kpiCard(fnc.retard>0?'#D32F2F':'#1E9C4B','bi-calendar-x', fnc.retard, 'FNC hors delai', 'Date de conformite depassee');
            h += kpiCard('#7A8798','bi-x-octagon', sumNCNS, 'Criteres non satisfaisants', 'Cumul sur tous les rapports');
        } else {
            h += kpiCard('#7A8798','bi-x-octagon', sumNCNS, 'Criteres non satisfaisants', 'Cumul sur tous les rapports');
            h += kpiCard('#7A8798','bi-lightning', inopines, 'Actes inopines', 'Hors programmation');
        }
        $('#kpiDecision').html(h);
    }
    paint(null);

    // Complement : statistiques des non-conformites
    $.post(AGAI_BASE+'/api/nonconformites',{csrf_token:CSRF,action:'stats'},null,'json')
     .done(function(res){
        if(!res || !res.success) return;
        const st = res.stats || res;
        paint({
            total:     Number(st.total     || 0),
            ouvertes:  Number(st.ouvertes  || st.ouvert || 0),
            critiques: Number(st.critiques || st.critique || 0),
            retard:    Number(st.en_retard || st.retard || 0)
        });
     });

    // Charge par inspecteur
    const insp = Object.values(parInsp).sort(function(a,b){ return b.total-a.total; }).slice(0,8);
    const maxI = insp.length ? insp[0].total : 1;
    $('#chargeInsp').html(insp.length ? insp.map(function(i){
        const pc = Math.round(i.total*100/maxI);
        return '<div style="margin-bottom:7px">'
          + '<div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:2px">'
          +   '<span style="font-weight:600;color:#2C3E50">'+esc(i.nom)+'</span>'
          +   '<span style="color:#6b7a90">'+i.total+' acte(s)'+(i.encours?(' &middot; <strong style="color:#E8890C">'+i.encours+' en cours</strong>'):'')+'</span>'
          + '</div>'
          + '<div style="background:#eef2f7;border-radius:50px;height:7px;overflow:hidden">'
          +   '<div style="width:'+pc+'%;height:100%;background:linear-gradient(90deg,#23408F,#1b3576)"></div></div>'
          + '</div>';
    }).join('') : '<div class="text-muted small">Aucun acte affecte</div>');

    // Operateurs a risque
    const orgs = Object.values(parOrga).filter(function(o){ return o.ncns>0; })
                 .sort(function(a,b){ return b.ncns-a.ncns; }).slice(0,6);
    $('#orgaRisque').html(orgs.length ? orgs.map(function(o,i){
        const col = i===0 ? '#D32F2F' : (i<3 ? '#E8890C' : '#7A8798');
        return '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f0f3f8">'
          + '<span style="background:'+col+';color:#fff;border-radius:6px;padding:1px 7px;font-size:.72rem;font-weight:800">'+o.ncns+'</span>'
          + '<span style="font-size:.79rem;font-weight:600;color:#2C3E50;flex:1">'+esc(o.nom)+'</span>'
          + '<span style="font-size:.7rem;color:#93a1b5">'+o.nb+' acte(s)</span>'
          + '</div>';
    }).join('') : '<div class="text-muted small">Aucune non-conformite relevee</div>');
}

// ====== LAUNCHER ======
<?php if($isCI): ?>
const NAT={audit:'Audit',inspection_programmee:'Inspection programmée',inspection_non_programmee:'Inspection non programmée',demonstration:'Démonstration',test:'Test',investigation:'Investigation'};
let selType='';
const cadreModal=new bootstrap.Modal('#cadreModal');
$('.nat-tile').on('click',function(){
    selType=$(this).data('type'); $('#cadreNature').text(NAT[selType]||selType);
    $('input[name="cadre"]').prop('checked',false); $('.cadre-opt').removeClass('sel');
    $('#cadreContinue').prop('disabled',true); cadreModal.show();
});
$(document).on('change','input[name="cadre"]',function(){ $('.cadre-opt').removeClass('sel'); $(this).closest('.cadre-opt').addClass('sel'); $('#cadreContinue').prop('disabled',false); });
$('#cadreContinue').on('click',function(){
    const cadre=$('input[name="cadre"]:checked').val(); if(!selType||!cadre) return;
    window.location=AGAI_BASE+'/declenchement?type='+encodeURIComponent(selType)+'&cadre='+encodeURIComponent(cadre);
});
<?php endif; ?>

loadDashboard();
</script>