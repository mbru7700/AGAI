<?php
/**
 * Endpoint : Journal des evenements AGAI (audit_logs) - lecture seule
 * Route : /api/audit-logs
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('cybersecurite');
if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}
$db = Database::getInstance();
$action = $_POST['action'] ?? '';
$ok   = fn($x=[]) => print(json_encode(['success'=>true]+$x));
$fail = fn($m)     => print(json_encode(['success'=>false,'message'=>$m]));

try {
    switch ($action) {

        case 'stats':
            $s = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM audit_logs WHERE created_at >= NOW()-INTERVAL 1 DAY) AS total_24h,
                    (SELECT COUNT(*) FROM audit_logs WHERE action IN ('access_denied','error','login_attempt') AND created_at >= NOW()-INTERVAL 1 DAY) AS errors_24h,
                    (SELECT COUNT(*) FROM audit_logs WHERE action='login' AND created_at >= NOW()-INTERVAL 1 DAY) AS logins_24h,
                    (SELECT COUNT(DISTINCT iduser) FROM audit_logs WHERE iduser IS NOT NULL AND created_at >= NOW()-INTERVAL 1 DAY) AS users_24h"
            )->fetch();
            $ok(['total_24h'=>(int)$s['total_24h'],'errors_24h'=>(int)$s['errors_24h'],'logins_24h'=>(int)$s['logins_24h'],'users_24h'=>(int)$s['users_24h']]);
            break;

        case 'list':
            $page   = max(1, (int) ($_POST['page'] ?? 1));
            $per    = min(100, (int) ($_POST['per'] ?? 30));
            $offset = ($page - 1) * $per;
            $periode = (int) ($_POST['periode'] ?? 7);
            $act    = trim((string) ($_POST['action_filter'] ?? ''));
            $mod    = trim((string) ($_POST['module_filter'] ?? ''));
            $search = trim((string) ($_POST['search'] ?? ''));

            $where  = "WHERE 1=1";
            $params = [];
            if ($periode > 0) { $where .= " AND al.created_at >= NOW()-INTERVAL ? DAY"; $params[] = $periode; }
            if ($act !== '') { $where .= " AND al.action = ?"; $params[] = $act; }
            if ($mod !== '') { $where .= " AND al.module LIKE ?"; $params[] = "%$mod%"; }
            if ($search !== '') {
                $where .= " AND (al.description LIKE ? OR al.ip_address LIKE ?)";
                $params[] = "%$search%"; $params[] = "%$search%";
            }

            $total = (int) $db->execute(
                "SELECT COUNT(*) FROM audit_logs al $where", $params
            )->fetchColumn();

            $rows = $db->execute(
                "SELECT al.idlog, al.action, al.module, al.description, al.ip_address, al.created_at,
                        TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS nom_user,
                        u.email AS email_user
                 FROM audit_logs al
                 LEFT JOIN users u ON u.iduser = al.iduser
                 $where
                 ORDER BY al.created_at DESC
                 LIMIT $per OFFSET $offset",
                $params
            )->fetchAll();

            $ok(['data' => $rows, 'total' => $total, 'page' => $page]);
            break;

        default: $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('audit-logs endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}