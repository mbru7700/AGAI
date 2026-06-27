<?php
/**
 * Endpoint AJAX : Journal des evenements AGAI - Power BI
 * Route : /api/audit-logs
 * Actions : stats, dashboard, list, users_detail
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('cybersecurite');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}

$db     = Database::getInstance();
$action = trim((string)($_POST['action'] ?? ''));
$ok   = function($x=[]) { echo json_encode(['success'=>true]+$x); };
$fail = function($m)     { echo json_encode(['success'=>false,'message'=>$m]); };

// Construire le filtre de periode selon les POST
function buildPeriodWhere(array $post, string $alias='al'): array {
    $where = []; $params = [];
    $annee = trim((string)($post['f_annee'] ?? ''));
    $mois  = trim((string)($post['f_mois']  ?? ''));
    $date  = trim((string)($post['f_date']  ?? ''));
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $where[] = "DATE({$alias}.created_at) = ?"; $params[] = $date;
    } elseif ($annee !== '' && $mois !== '') {
        $where[] = "YEAR({$alias}.created_at) = ? AND MONTH({$alias}.created_at) = ?";
        $params[] = (int)$annee; $params[] = (int)$mois;
    } elseif ($annee !== '') {
        $where[] = "YEAR({$alias}.created_at) = ?"; $params[] = (int)$annee;
    } elseif ($mois !== '') {
        $where[] = "MONTH({$alias}.created_at) = ?"; $params[] = (int)$mois;
    } else {
        // Defaut : mois courant (pour avoir des donnees visibles meme si peu d'activite aujourd'hui)
        $where[] = "YEAR({$alias}.created_at) = YEAR(CURDATE()) AND MONTH({$alias}.created_at) = MONTH(CURDATE())";
    }
    return [$where, $params];
}

// Periode etendue pour la courbe (mois courant si aucun filtre, sinon la periode choisie)
function buildCourbeWhere(array $post, string $alias='al'): array {
    $where = []; $params = [];
    $annee = trim((string)($post['f_annee'] ?? ''));
    $mois  = trim((string)($post['f_mois']  ?? ''));
    $date  = trim((string)($post['f_date']  ?? ''));
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        // Pour une date precise : montrer les 7 jours autour
        $where[] = "{$alias}.created_at >= DATE_SUB(?, INTERVAL 3 DAY) AND {$alias}.created_at < DATE_ADD(?, INTERVAL 4 DAY)";
        $params[] = $date; $params[] = $date;
    } elseif ($annee !== '' && $mois !== '') {
        $where[] = "YEAR({$alias}.created_at) = ? AND MONTH({$alias}.created_at) = ?";
        $params[] = (int)$annee; $params[] = (int)$mois;
    } elseif ($annee !== '') {
        $where[] = "YEAR({$alias}.created_at) = ?"; $params[] = (int)$annee;
    } elseif ($mois !== '') {
        $where[] = "YEAR({$alias}.created_at) = YEAR(CURDATE()) AND MONTH({$alias}.created_at) = ?";
        $params[] = (int)$mois;
    } else {
        // Defaut : mois courant complet pour avoir une courbe lisible
        $where[] = "YEAR({$alias}.created_at) = YEAR(CURDATE()) AND MONTH({$alias}.created_at) = MONTH(CURDATE())";
    }
    return [$where, $params];
}

try { switch ($action) {

// ================================================================
// DASHBOARD POWER BI : toutes les stats en un seul appel
// ================================================================
case 'dashboard':
    [$whArr, $params0] = buildPeriodWhere($_POST);
    $wh = count($whArr) ? 'WHERE '.implode(' AND ', $whArr) : 'WHERE 1=1';

    // -- KPI principaux dans la periode --
    $kpi = $db->execute(
        "SELECT
            COUNT(*)                                                   AS total_events,
            SUM(action='login')                                        AS logins_ok,
            SUM(action='login_attempt')                                AS login_fail,
            SUM(action='logout')                                       AS logouts,
            SUM(action='access_denied')                                AS access_denied,
            SUM(action IN ('create','update','delete'))                 AS crud_ops,
            SUM(action IN ('upload','mail'))                            AS uploads,
            COUNT(DISTINCT iduser)                                     AS users_actifs,
            COUNT(DISTINCT ip_address)                                 AS ips_uniques
         FROM audit_logs al $wh",
        $params0
    )->fetch();

    // -- KPI hier vs aujourd'hui (comparaison fixe, pas filtree) --
    $today     = $db->execute("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $yesterday = $db->execute("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()-INTERVAL 1 DAY")->fetchColumn();
    $loginToday= $db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='login' AND DATE(created_at)=CURDATE()")->fetchColumn();
    $loginYest = $db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='login' AND DATE(created_at)=CURDATE()-INTERVAL 1 DAY")->fetchColumn();
    $failToday = $db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()")->fetchColumn();
    $failYest  = $db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()-INTERVAL 1 DAY")->fetchColumn();

    // -- Courbe par jour (mois courant ou periode choisie) --
    [$whArr2, $params2] = buildCourbeWhere($_POST);
    $wh2 = 'WHERE '.implode(' AND ', $whArr2);
    $courbe = $db->execute(
        "SELECT DATE(al.created_at) AS jour,
                COUNT(*) AS total,
                SUM(al.action='login') AS logins,
                SUM(al.action='login_attempt') AS echecs,
                SUM(al.action='access_denied') AS refuses,
                COUNT(DISTINCT al.iduser) AS utilisateurs
         FROM audit_logs al $wh2
         GROUP BY DATE(al.created_at)
         ORDER BY jour ASC",
        $params2
    )->fetchAll();

    // -- Repartition par action --
    $byAction = $db->execute(
        "SELECT action, COUNT(*) AS nb
         FROM audit_logs al $wh
         GROUP BY action ORDER BY nb DESC LIMIT 15",
        $params0
    )->fetchAll();

    // -- Repartition par module --
    $whMod = $wh . ' AND module IS NOT NULL AND TRIM(module)<>\'\'';
    $byModule = $db->execute(
        "SELECT module, COUNT(*) AS nb
         FROM audit_logs al $whMod
         GROUP BY module ORDER BY nb DESC LIMIT 12",
        $params0
    )->fetchAll();

    // -- Top utilisateurs actifs --
    $whUsers  = count($whArr) ? 'WHERE '.implode(' AND ', $whArr).' AND al.iduser IS NOT NULL' : 'WHERE al.iduser IS NOT NULL';
    $topUsers = $db->execute(
        "SELECT u.prenom, u.nom, u.email, u.role, COUNT(*) AS nb,
                MAX(al.created_at) AS derniere_action
         FROM audit_logs al
         LEFT JOIN users u ON u.iduser = al.iduser
         $whUsers
         GROUP BY al.iduser ORDER BY nb DESC LIMIT 10",
        $params0
    )->fetchAll();

    // -- Top IPs suspectes (echecs de connexion) --
    $topIps = $db->execute(
        "SELECT ip_address, COUNT(*) AS nb_echecs,
                MIN(created_at) AS premier, MAX(created_at) AS dernier
         FROM audit_logs
         WHERE action='login_attempt'
         GROUP BY ip_address HAVING nb_echecs >= 2
         ORDER BY nb_echecs DESC LIMIT 10"
    )->fetchAll();

    // -- Connexions reussies par heure (pour heatmap) --
    $whHeure  = count($whArr) ? 'WHERE '.implode(' AND ', $whArr)." AND al.action='login'" : "WHERE al.action='login'";
    $parHeure = $db->execute(
        "SELECT HOUR(al.created_at) AS heure, COUNT(*) AS nb
         FROM audit_logs al $whHeure
         GROUP BY HOUR(al.created_at) ORDER BY heure",
        $params0
    )->fetchAll();

    // -- Annees disponibles pour les filtres --
    $annees = $db->query(
        "SELECT DISTINCT YEAR(created_at) AS y FROM audit_logs ORDER BY y DESC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $ok([
        'kpi'         => $kpi,
        'today'       => (int)$today,    'yesterday'  => (int)$yesterday,
        'loginToday'  => (int)$loginToday,'loginYest' => (int)$loginYest,
        'failToday'   => (int)$failToday, 'failYest'  => (int)$failYest,
        'courbe'      => $courbe,
        'by_action'   => $byAction,
        'by_module'   => $byModule,
        'top_users'   => $topUsers,
        'top_ips'     => $topIps,
        'par_heure'   => $parHeure,
        'annees'      => $annees,
    ]);
    break;

// ================================================================
// LISTE paginee du journal
// ================================================================
case 'list':
    $page   = max(1, (int)($_POST['page'] ?? 1));
    $per    = min(100, (int)($_POST['per'] ?? 30));
    $offset = ($page - 1) * $per;
    $act    = trim((string)($_POST['action_filter'] ?? ''));
    $mod    = trim((string)($_POST['module_filter'] ?? ''));
    $search = trim((string)($_POST['search'] ?? ''));
    $user   = trim((string)($_POST['user_filter'] ?? ''));

    [$whArr, $params] = buildPeriodWhere($_POST);
    if ($act    !== '') { $whArr[] = "al.action = ?";           $params[] = $act; }
    if ($mod    !== '') { $whArr[] = "al.module LIKE ?";        $params[] = "%$mod%"; }
    if ($search !== '') { $whArr[] = "(al.description LIKE ? OR al.ip_address LIKE ? OR u.email LIKE ?)";
                          $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($user   !== '') { $whArr[] = "al.iduser = ?";           $params[] = (int)$user; }

    $wh = count($whArr) ? 'WHERE '.implode(' AND ', $whArr) : 'WHERE 1=1';

    $total = (int)$db->execute(
        "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.iduser=al.iduser $wh",
        $params
    )->fetchColumn();

    $paramsPage = array_merge($params, [$per, $offset]);
    $rows = $db->execute(
        "SELECT al.idlog, al.action, al.module, al.description, al.ip_address, al.created_at,
                TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS nom_user,
                u.email AS email_user, u.role AS role_user
         FROM audit_logs al
         LEFT JOIN users u ON u.iduser = al.iduser
         $wh ORDER BY al.created_at DESC LIMIT ? OFFSET ?",
        $paramsPage
    )->fetchAll();
    $ok(['data'=>$rows,'total'=>$total,'page'=>$page,'pages'=>ceil($total/max(1,$per))]);
    break;

// ================================================================
// DETAIL connexions d'un jour ou d'une periode (pour modale)
// ================================================================
case 'logins_detail':
    $date_filter = trim((string)($_POST['date_filter'] ?? ''));
    $type_filter = trim((string)($_POST['type_filter'] ?? 'login'));
    $wh = "WHERE al.action = ?";
    $params = [$type_filter];
    if ($date_filter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
        $wh .= " AND DATE(al.created_at) = ?"; $params[] = $date_filter;
    } else {
        $wh .= " AND al.created_at >= NOW()-INTERVAL 7 DAY";
    }
    $rows = $db->execute(
        "SELECT al.created_at, al.ip_address, al.description,
                TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS nom_user,
                u.email AS email_user, u.role AS role_user
         FROM audit_logs al
         LEFT JOIN users u ON u.iduser = al.iduser
         $wh ORDER BY al.created_at DESC LIMIT 200",
        $params
    )->fetchAll();
    $ok(['data'=>$rows]);
    break;

// ================================================================
// LISTE des users pour filtre Select2
// ================================================================
case 'users_list':
    $rows = $db->query(
        "SELECT DISTINCT al.iduser, TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS nom,
                u.email
         FROM audit_logs al JOIN users u ON u.iduser=al.iduser
         WHERE al.iduser IS NOT NULL ORDER BY nom LIMIT 100"
    )->fetchAll();
    $ok(['data'=>$rows]);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('audit-logs: '.$e->getMessage());
    $fail('Erreur technique : '.$e->getMessage());
}