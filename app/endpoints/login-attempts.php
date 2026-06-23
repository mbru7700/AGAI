<?php
/**
 * Endpoint : Tentatives de connexion (lecture seule)
 * Route : /api/login-attempts
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }
Rbac::guardApi('parametres');
if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}
$db=$db=Database::getInstance();
$action=$_POST['action']??'';
$ok=fn($x=[])=>print(json_encode(['success'=>true]+$x));
$fail=fn($m)=>print(json_encode(['success'=>false,'message'=>$m]));

try {
    switch($action) {
        case 'stats':
            $s=$db->query("SELECT
                (SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND created_at >= NOW()-INTERVAL 1 DAY) AS total_24h,
                (SELECT COUNT(DISTINCT ip_address) FROM audit_logs WHERE action='login_attempt' AND created_at >= NOW()-INTERVAL 1 DAY) AS uniq_ip_24h,
                (SELECT COUNT(*) FROM audit_logs WHERE action='login_attempt' AND created_at >= NOW()-INTERVAL 7 DAY) AS total_7j,
                (SELECT COUNT(*) FROM users WHERE login_attempts >= 3 AND locked_until > NOW()) AS comptes_bloques
            ")->fetch();
            $locked=$db->query("SELECT email,nom,prenom,login_attempts,locked_until FROM users WHERE locked_until > NOW() ORDER BY locked_until DESC")->fetchAll();
            $ok(['total_24h'=>(int)$s['total_24h'],'uniq_ip_24h'=>(int)$s['uniq_ip_24h'],'total_7j'=>(int)$s['total_7j'],'comptes_bloques'=>(int)$s['comptes_bloques'],'locked'=>$locked]);
            break;
        case 'list':
            $page=max(1,(int)($_POST['page']??1));
            $per=min(100,(int)($_POST['per']??25));
            $offset=($page-1)*$per;
            $periode=(int)($_POST['periode']??7);
            $search=trim((string)($_POST['search']??''));
            $where="WHERE al.action='login_attempt'";
            $params=[];
            if($periode>0){ $where.=" AND al.created_at >= NOW()-INTERVAL ? DAY"; $params[]=$periode; }
            if($search!==''){
                $where.=" AND (al.description LIKE ? OR al.ip_address LIKE ?)";
                $params[]="%$search%"; $params[]="%$search%";
            }
            $total=(int)$db->execute("SELECT COUNT(*) FROM audit_logs al $where",$params)->fetchColumn();
            $rows=$db->execute("SELECT al.*, u.email AS email_user, CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,'')) AS nom_user FROM audit_logs al LEFT JOIN users u ON u.iduser=al.iduser $where ORDER BY al.created_at DESC LIMIT $per OFFSET $offset",$params)->fetchAll();
            // Extraire email depuis la description si pas de user
            foreach($rows as &$r){ if(empty($r['email_user'])){ preg_match('/Email:\s*([^\s,]+)/i',$r['description']??'',$m); $r['email']=trim($m[1]??''); } }
            $ok(['data'=>$rows,'total'=>$total,'page'=>$page,'pages'=>ceil($total/$per)]);
            break;
        default: $fail('Action inconnue.');
    }
} catch(Throwable $e){ error_log('login-attempts: '.$e->getMessage()); $fail('Erreur technique.'); }