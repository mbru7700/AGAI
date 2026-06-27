<?php
/**
 * Endpoint AJAX : Tentatives de connexion - Power BI
 * Route : /api/login-attempts
 * Actions : dashboard, list, ip_detail, users_list
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

function buildLAWhere(array $post, string $alias='al'): array {
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
        // Defaut : mois courant
        $where[] = "YEAR({$alias}.created_at) = YEAR(CURDATE()) AND MONTH({$alias}.created_at) = MONTH(CURDATE())";
    }
    return [$where, $params];
}

function buildCourbeLA(array $post, string $alias='al'): array {
    $where = []; $params = [];
    $annee = trim((string)($post['f_annee'] ?? ''));
    $mois  = trim((string)($post['f_mois']  ?? ''));
    $date  = trim((string)($post['f_date']  ?? ''));
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $where[] = "{$alias}.created_at >= DATE_SUB(?, INTERVAL 3 DAY) AND {$alias}.created_at < DATE_ADD(?, INTERVAL 4 DAY)";
        $params[] = $date; $params[] = $date;
    } elseif ($annee !== '' && $mois !== '') {
        $where[] = "YEAR({$alias}.created_at) = ? AND MONTH({$alias}.created_at) = ?";
        $params[] = (int)$annee; $params[] = (int)$mois;
    } elseif ($annee !== '') {
        $where[] = "YEAR({$alias}.created_at) = ?"; $params[] = (int)$annee;
    } else {
        $where[] = "YEAR({$alias}.created_at) = YEAR(CURDATE()) AND MONTH({$alias}.created_at) = MONTH(CURDATE())";
    }
    return [$where, $params];
}

try { switch ($action) {

// ================================================================
case 'dashboard':
    [$whArr, $p0] = buildLAWhere($_POST);
    // Toujours filtrer sur login_attempt
    $whArr[] = "al.action = 'login_attempt'";
    $wh = 'WHERE '.implode(' AND ', $whArr);

    // KPI periode
    $kpi = $db->execute(
        "SELECT COUNT(*) AS total_echecs,
                COUNT(DISTINCT al.ip_address) AS ips_uniques,
                COUNT(DISTINCT al.description) AS emails_cibles,
                SUM(CASE WHEN al.created_at >= NOW()-INTERVAL 1 HOUR THEN 1 ELSE 0 END) AS derniere_heure
         FROM audit_logs al $wh",
        $p0
    )->fetch();

    // Comparaison aujourd'hui / hier (fixe)
    $echToday = $db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()")->fetchColumn();
    $echYest  = $db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()-INTERVAL 1 DAY")->fetchColumn();
    $ipToday  = $db->execute("SELECT COUNT(DISTINCT ip_address) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()")->fetchColumn();
    $ipYest   = $db->execute("SELECT COUNT(DISTINCT ip_address) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()-INTERVAL 1 DAY")->fetchColumn();
    // Comptes bloques en ce moment
    $bloques  = $db->query("SELECT COUNT(*) FROM users WHERE login_attempts >= 3 AND locked_until > NOW()")->fetchColumn();
    $blocList = $db->query(
        "SELECT email, nom, prenom, login_attempts, locked_until FROM users
         WHERE locked_until > NOW() ORDER BY locked_until DESC LIMIT 20"
    )->fetchAll();

    // Courbe par jour
    [$whArr2, $p2] = buildCourbeLA($_POST);
    $whArr2[] = "al.action = 'login_attempt'";
    $wh2 = 'WHERE '.implode(' AND ', $whArr2);
    $courbe = $db->execute(
        "SELECT DATE(al.created_at) AS jour,
                COUNT(*) AS nb_echecs,
                COUNT(DISTINCT al.ip_address) AS nb_ips
         FROM audit_logs al $wh2
         GROUP BY DATE(al.created_at) ORDER BY jour ASC",
        $p2
    )->fetchAll();

    // Top IPs attaquantes - sans GROUP_CONCAT qui peut planter
    $topIps = $db->execute(
        "SELECT ip_address, COUNT(*) AS nb,
                MIN(created_at) AS premier, MAX(created_at) AS dernier
         FROM audit_logs al $wh
         GROUP BY ip_address ORDER BY nb DESC LIMIT 15",
        $p0
    )->fetchAll();

    // Top emails cibles - extraire depuis la description
    $topEmails = $db->execute(
        "SELECT al.description AS desc_brut,
                COUNT(*) AS nb,
                MAX(al.created_at) AS dernier,
                MAX(al.ip_address) AS derniere_ip
         FROM audit_logs al $wh
         GROUP BY al.description ORDER BY nb DESC LIMIT 10",
        $p0
    )->fetchAll();

    // Repartition par heure
    $parHeure = $db->execute(
        "SELECT HOUR(al.created_at) AS heure, COUNT(*) AS nb
         FROM audit_logs al $wh
         GROUP BY HOUR(al.created_at) ORDER BY heure",
        $p0
    )->fetchAll();

    // Annees disponibles
    $annees = $db->query(
        "SELECT DISTINCT YEAR(created_at) AS y FROM audit_logs WHERE action='login_attempt' ORDER BY y DESC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $ok([
        'kpi'        => $kpi,
        'echToday'   => (int)$echToday, 'echYest'  => (int)$echYest,
        'ipToday'    => (int)$ipToday,  'ipYest'   => (int)$ipYest,
        'bloques'    => (int)$bloques,  'bloc_list'=> $blocList,
        'courbe'     => $courbe,
        'top_ips'    => $topIps,
        'top_emails' => $topEmails,
        'par_heure'  => $parHeure,
        'annees'     => $annees,
    ]);
    break;

// ================================================================
case 'list':
    $page   = max(1, (int)($_POST['page'] ?? 1));
    $per    = min(100, (int)($_POST['per'] ?? 30));
    $offset = ($page - 1) * $per;
    $search = trim((string)($_POST['search'] ?? ''));
    $ip     = trim((string)($_POST['ip_filter'] ?? ''));

    [$whArr, $params] = buildLAWhere($_POST);
    $whArr[] = "al.action = 'login_attempt'";
    if ($search !== '') { $whArr[] = "(al.description LIKE ? OR al.ip_address LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($ip !== '')     { $whArr[] = "al.ip_address = ?"; $params[] = $ip; }
    $wh = 'WHERE '.implode(' AND ', $whArr);

    $total = (int)$db->execute("SELECT COUNT(*) FROM audit_logs al $wh", $params)->fetchColumn();
    $paramsPage = array_merge($params, [$per, $offset]);
    $rows = $db->execute(
        "SELECT al.idlog, al.description, al.ip_address, al.created_at,
                al.user_agent
         FROM audit_logs al $wh
         ORDER BY al.created_at DESC LIMIT ? OFFSET ?",
        $paramsPage
    )->fetchAll();
    $ok(['data'=>$rows,'total'=>$total,'page'=>$page,'pages'=>ceil($total/max(1,$per))]);
    break;

// ================================================================
case 'ip_detail':
    $ip = trim((string)($_POST['ip'] ?? ''));
    if (!$ip) { $fail('IP manquante.'); break; }
    $rows = $db->execute(
        "SELECT al.created_at, al.description, al.ip_address
         FROM audit_logs al
         WHERE al.action='login_attempt' AND al.ip_address=?
         ORDER BY al.created_at DESC LIMIT 100",
        [$ip]
    )->fetchAll();
    $ok(['data'=>$rows,'ip'=>$ip]);
    break;

// ================================================================
case 'debloquer':
    $email = trim((string)($_POST['email'] ?? ''));
    if (!$email) { $fail('Email manquant.'); break; }
    $db->prepare("UPDATE users SET login_attempts=0, locked_until=NULL WHERE email=?")->execute([$email]);
    Audit::log('update','cybersecurite',"Deblocage manuel compte : $email");
    $ok(['message'=>'Compte debloque : '.$email]);
    break;

// ================================================================
case 'stats_only':
    $s = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()) AS total_24h,
            (SELECT COUNT(DISTINCT ip_address) FROM audit_logs WHERE action='login_attempt' AND DATE(created_at)=CURDATE()) AS uniq_ip_24h,
            (SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())) AS total_mois,
            (SELECT COUNT(*) FROM users WHERE login_attempts >= 3 AND locked_until > NOW()) AS comptes_bloques"
    )->fetch();
    $ok($s);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('login-attempts: '.$e->getMessage());
    $fail('Erreur technique : '.$e->getMessage());
}