<?php
/**
 * Endpoint AJAX : Inspecteurs. Route : /api/inspecteurs
 * ------------------------------------------------------------
 * Tranche 1 : lecture avec filtres dynamiques (list, filters, get).
 * Creation / edition / habilitations / uploads : tranche 2.
 *
 * Securite : session + role autorise (guardApi 'inspecteurs') + CSRF.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

/*
 * Service de fichiers prives (photo, decision) en GET.
 * Lecture seule, protege par session + role (pas de CSRF car affichage img/iframe).
 * basename() empeche tout parcours de repertoire (directory traversal).
 */
$serve = $_GET['serve'] ?? '';
if ($serve === 'photo' || $serve === 'decision') {
    if (!Auth::checkLogin() || !Rbac::canAccess('inspecteurs')) { http_response_code(403); exit('Acces refuse'); }
    $db = Database::getInstance();
    if ($serve === 'photo') {
        $id = (int) ($_GET['idinspecteur'] ?? 0);
        $st = $db->prepare("SELECT photoinspecter FROM inspecteur WHERE idinspecteur = ?");
        $st->execute([$id]);
        $f    = (string) ($st->fetchColumn() ?: '');
        $path = dir_photos() . '/' . basename($f);
        if ($f === '' || !is_file($path)) { http_response_code(404); exit('Introuvable'); }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }
    // decision (PDF)
    $idh = (int) ($_GET['idhabilitation'] ?? 0);
    $dl  = isset($_GET['dl']);
    $st  = $db->prepare("SELECT decision FROM habilitation WHERE idhabilitation = ?");
    $st->execute([$idh]);
    $f    = (string) ($st->fetchColumn() ?: '');
    $path = dir_decisions() . '/' . basename($f);
    if ($f === '' || !is_file($path)) { http_response_code(404); exit('Introuvable'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($dl ? 'attachment' : 'inline') . '; filename="decision.pdf"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

Rbac::guardApi('inspecteurs');

if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

$ok   = function ($extra = []) { echo json_encode(['success' => true] + $extra); };
$fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); };

/* Nettoie un libelle (espaces, retours a la ligne, tabulations parasites). */
function clean_label($s): string
{
    return trim(preg_replace('/\s+/', ' ', (string) $s));
}

/* Construit une liste d'entiers surs a partir d'un tableau POST (anti-injection). */
function int_list($arr): array
{
    if (!is_array($arr)) { return []; }
    $out = [];
    foreach ($arr as $v) { $v = (int) $v; if ($v > 0) { $out[] = $v; } }
    return array_values(array_unique($out));
}

/* Recupere un fichier d'un champ multiple ($_FILES['x'][...] indexe par $i). */
function file_at(string $field, int $i): ?array
{
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]['name'][$i])) { return null; }
    if ((int) $_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) { return null; }
    return [
        'name'     => $_FILES[$field]['name'][$i],
        'tmp_name' => $_FILES[$field]['tmp_name'][$i],
        'error'    => $_FILES[$field]['error'][$i],
        'size'     => $_FILES[$field]['size'][$i],
    ];
}

/**
 * Enregistrement securise d'un fichier televerse.
 * Verifie : upload reel, taille, extension ET type MIME reel (finfo),
 * renomme le fichier de facon aleatoire, stocke hors dossier public.
 * Retourne le nom de fichier stocke, ou false en cas d'echec.
 */
function save_upload(?array $file, array $allowedExt, array $allowedMime, int $maxBytes, string $destDir)
{
    if (!$file || (int) $file['error'] !== UPLOAD_ERR_OK) { return false; }
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) { return false; }
    if (!is_uploaded_file($file['tmp_name'])) { return false; }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) { return false; }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) { return false; }

    if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rtrim($destDir, '/') . '/' . $name)) { return false; }
    return $name;
}

/* Dossiers de stockage prives (hors /public). */
function dir_photos(): string   { return BASE_PATH . '/storage/inspecteurs'; }
function dir_decisions(): string { return BASE_PATH . '/storage/decisions'; }

try {
    switch ($action) {

        case 'filters':
            // Options pour les listes deroulantes de filtre
            $inspecteurs = $db->query(
                "SELECT idinspecteur, TRIM(CONCAT(preninspect,' ',nominspecteur)) AS libelle
                 FROM inspecteur ORDER BY nominspecteur, preninspect"
            )->fetchAll();

            $directions = $db->query(
                "SELECT codedirec, libdirec FROM direction_anac ORDER BY libdirec"
            )->fetchAll();

            $domainesRaw = $db->query(
                "SELECT iddomaine, nomdomaine FROM domaine ORDER BY nomdomaine"
            )->fetchAll();
            $domaines = [];
            foreach ($domainesRaw as $d) {
                $domaines[] = ['iddomaine' => (int) $d['iddomaine'], 'nomdomaine' => clean_label($d['nomdomaine'])];
            }
            foreach ($directions as &$dir) { $dir['libdirec'] = clean_label($dir['libdirec']); }
            unset($dir);

            $ok(['inspecteurs' => $inspecteurs, 'directions' => $directions, 'domaines' => $domaines]);
            break;

        case 'stats':
            $row = $db->query(
                "SELECT
                    COUNT(*)                       AS total,
                    SUM(categorie = 'stagiaire')   AS stagiaires,
                    SUM(categorie = 'titulaire')   AS titulaires,
                    SUM(categorie = 'exceptionnel') AS exceptionnels,
                    (SELECT COUNT(*) FROM habilitation)                  AS habilitations,
                    (SELECT COUNT(DISTINCT iddomaine) FROM habilitation) AS domaines_couverts
                 FROM inspecteur"
            )->fetch();
            foreach ($row as $k => $v) { $row[$k] = (int) $v; }
            $ok(['stats' => $row]);
            break;

        case 'check_domaine':
            $nom = clean_label($_POST['nomdomaine'] ?? '');
            if ($nom === '') { $ok(['exists' => false]); break; }
            $existsList = $db->query("SELECT nomdomaine FROM domaine")->fetchAll(PDO::FETCH_COLUMN);
            $exists = false;
            foreach ($existsList as $r) { if (mb_strtolower(clean_label($r)) === mb_strtolower($nom)) { $exists = true; break; } }
            $ok(['exists' => $exists]);
            break;

        case 'create_domaine':
            $nom = clean_label($_POST['nomdomaine'] ?? '');
            $lib = clean_label($_POST['libel_domaine'] ?? '');
            if ($nom === '' || mb_strlen($nom) > 255) { $fail('Nom de domaine invalide.'); break; }
            if ($lib === '') { $lib = $nom; }
            $existsList = $db->query("SELECT nomdomaine FROM domaine")->fetchAll(PDO::FETCH_COLUMN);
            $dup = false;
            foreach ($existsList as $r) { if (mb_strtolower(clean_label($r)) === mb_strtolower($nom)) { $dup = true; break; } }
            if ($dup) { $fail('Ce domaine existe deja.'); break; }
            $st = $db->prepare("INSERT INTO domaine (nomdomaine, libel_domaine) VALUES (?, ?)");
            $st->execute([$nom, $lib]);
            $newDomId = (int) $db->lastInsertId();   // a capturer AVANT toute autre insertion (ex : journal d'audit)
            Audit::log('create', 'inspecteurs', 'Creation domaine ' . $nom);
            $ok(['iddomaine' => $newDomId, 'nomdomaine' => $nom]);
            break;

        case 'stats':
            $a = $db->query(
                "SELECT COUNT(*) AS total,
                        SUM(categorie='stagiaire')    AS stagiaires,
                        SUM(categorie='titulaire')    AS titulaires,
                        SUM(categorie='exceptionnel') AS exceptionnels
                 FROM inspecteur"
            )->fetch();
            $b = $db->query(
                "SELECT COUNT(*) AS habilitations, COUNT(DISTINCT iddomaine) AS domaines_couverts FROM habilitation"
            )->fetch();
            $stats = [
                'total'           => (int) $a['total'],
                'stagiaires'      => (int) $a['stagiaires'],
                'titulaires'      => (int) $a['titulaires'],
                'exceptionnels'   => (int) $a['exceptionnels'],
                'habilitations'   => (int) $b['habilitations'],
                'domaines_couverts' => (int) $b['domaines_couverts'],
            ];
            $ok(['stats' => $stats]);
            break;

        // ----------------------------------------------------------------
        // Stats d'expiration des habilitations (par ligne d'habilitation)
        case 'exp_stats':
            $es = $db->query(
                "SELECT
                    SUM(date_expiration < CURDATE())                                             AS expired,
                    SUM(date_expiration >= CURDATE() AND date_expiration < DATE_ADD(CURDATE(), INTERVAL 3 MONTH)) AS exp_3m,
                    SUM(date_expiration >= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                     AND date_expiration < DATE_ADD(CURDATE(), INTERVAL 6 MONTH))               AS exp_6m,
                    SUM(date_expiration >= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))                AS valid
                 FROM habilitation WHERE date_expiration IS NOT NULL AND date_expiration != '0000-00-00'"
            )->fetch();
            $ok(['stats' => [
                'expired' => (int) ($es['expired'] ?? 0),
                'exp_3m'  => (int) ($es['exp_3m']  ?? 0),
                'exp_6m'  => (int) ($es['exp_6m']  ?? 0),
                'valid'   => (int) ($es['valid']   ?? 0),
            ]]);
            break;

        // ----------------------------------------------------------------
        // Liste des habilitations par etat d'expiration
        case 'exp_list':
            $type = trim((string) ($_POST['type'] ?? 'expired'));
            switch ($type) {
                case 'expired':
                    $cond = "h.date_expiration IS NOT NULL AND h.date_expiration != '0000-00-00' AND h.date_expiration < CURDATE()";
                    break;
                case '3m':
                    $cond = "h.date_expiration >= CURDATE() AND h.date_expiration < DATE_ADD(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case '6m':
                    $cond = "h.date_expiration >= DATE_ADD(CURDATE(), INTERVAL 3 MONTH) AND h.date_expiration < DATE_ADD(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'valid':
                    $cond = "h.date_expiration >= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                default:
                    $fail('Type inconnu.');
                    break 2;
            }
            $stEl = $db->query(
                "SELECT h.idhabilitation, h.idinspecteur, h.iddomaine, h.numero_habilitation,
                        h.date_habilitation, h.date_expiration, h.decision, h.observation,
                        i.nominspecteur, i.preninspect, i.numatinspecteur,
                        d.nomdomaine
                 FROM habilitation h
                 JOIN inspecteur i ON i.idinspecteur = h.idinspecteur
                 JOIN domaine   d ON d.iddomaine    = h.iddomaine
                 WHERE $cond
                 ORDER BY h.date_expiration ASC, i.nominspecteur"
            );
            $ok(['data' => $stEl->fetchAll()]);
            break;

        case 'domaine_check':
            // Verifie si un nom de domaine existe deja (insensible a la casse et aux espaces)
            $nom = clean_label($_POST['nomdomaine'] ?? '');
            if ($nom === '') { $ok(['exists' => false]); break; }
            $st = $db->prepare("SELECT iddomaine FROM domaine WHERE LOWER(TRIM(nomdomaine)) = LOWER(?) LIMIT 1");
            $st->execute([$nom]);
            $row = $st->fetch();
            $ok(['exists' => (bool) $row, 'iddomaine' => $row ? (int) $row['iddomaine'] : null]);
            break;

        case 'domaine_create':
            $nom = clean_label($_POST['nomdomaine'] ?? '');
            $lib = clean_label($_POST['libel_domaine'] ?? '');
            if ($nom === '' || mb_strlen($nom) > 255) { $fail('Nom de domaine invalide.'); break; }
            if (mb_strlen($lib) > 255) { $fail('Libelle trop long.'); break; }
            // Anti-doublon serveur
            $st = $db->prepare("SELECT iddomaine FROM domaine WHERE LOWER(TRIM(nomdomaine)) = LOWER(?) LIMIT 1");
            $st->execute([$nom]);
            if ($st->fetch()) { $fail('Ce domaine existe deja.'); break; }
            $ins = $db->prepare("INSERT INTO domaine (nomdomaine, libel_domaine) VALUES (?, ?)");
            $ins->execute([$nom, $lib]);
            $id = (int) $db->lastInsertId();
            Audit::log('create', 'inspecteurs', "Creation domaine $nom (#$id)");
            $ok(['message' => 'Domaine ajoute.', 'iddomaine' => $id, 'nomdomaine' => $nom]);
            break;

        case 'list':
            $fInsp = int_list($_POST['inspecteurs'] ?? []);
            $fDir  = int_list($_POST['directions'] ?? []);
            $fDom  = int_list($_POST['domaines'] ?? []);

            $where  = [];
            $params = [];

            if ($fInsp) {
                $where[] = 'i.idinspecteur IN (' . implode(',', array_fill(0, count($fInsp), '?')) . ')';
                $params  = array_merge($params, $fInsp);
            }
            if ($fDir) {
                $where[] = 'i.codedirec IN (' . implode(',', array_fill(0, count($fDir), '?')) . ')';
                $params  = array_merge($params, $fDir);
            }
            if ($fDom) {
                $where[] = 'i.idinspecteur IN (SELECT idinspecteur FROM habilitation WHERE iddomaine IN ('
                         . implode(',', array_fill(0, count($fDom), '?')) . '))';
                $params  = array_merge($params, $fDom);
            }

            $sql = "SELECT i.idinspecteur, i.nominspecteur, i.preninspect, i.numatinspecteur,
                           i.categorie, i.teleinspecter, i.mailinspect, i.photoinspecter,
                           i.codedirec, i.datenomine, i.created_at,
                           d.libdirec,
                           GROUP_CONCAT(DISTINCT NULLIF(TRIM(dom.nomdomaine),'') ORDER BY dom.nomdomaine SEPARATOR '|') AS domaines
                    FROM inspecteur i
                    LEFT JOIN direction_anac d ON d.codedirec    = i.codedirec
                    LEFT JOIN habilitation   h ON h.idinspecteur = i.idinspecteur
                    LEFT JOIN domaine      dom ON dom.iddomaine  = h.iddomaine";
            if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
            $sql .= " GROUP BY i.idinspecteur
                      ORDER BY i.created_at DESC, i.idinspecteur DESC";

            $st = $db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();

            // Nettoyage des libelles de direction et des domaines pour l'affichage
            foreach ($rows as &$r) {
                $r['libdirec'] = clean_label($r['libdirec'] ?? '');
                $doms = array_filter(array_map('clean_label', explode('|', (string) ($r['domaines'] ?? ''))));
                $r['domaines_list'] = array_values($doms);
                unset($r['domaines']);
            }
            unset($r);

            // Enrichir chaque inspecteur avec ses habilitations (domaine + dates) pour les chips colorees
            if (!empty($rows)) {
                $ids   = array_column($rows, 'idinspecteur');
                $inIds = implode(',', array_map('intval', $ids));
                $stHab = $db->query(
                    "SELECT h.idinspecteur, h.idhabilitation, h.date_habilitation, h.date_expiration, h.decision,
                            d.nomdomaine
                     FROM habilitation h
                     JOIN domaine d ON d.iddomaine = h.iddomaine
                     WHERE h.idinspecteur IN ($inIds)
                     ORDER BY d.nomdomaine"
                );
                $habMap = [];
                foreach ($stHab->fetchAll() as $hab) {
                    $habMap[(int) $hab['idinspecteur']][] = $hab;
                }
                foreach ($rows as &$r) {
                    $r['habilitations'] = $habMap[(int) $r['idinspecteur']] ?? [];
                }
                unset($r);
            }

            $ok(['data' => $rows, 'count' => count($rows)]);
            break;

        case 'users_available':
            // Utilisateurs de role inspecteur/chef_inspecteur pas encore enregistres comme inspecteurs
            $rows = $db->query(
                "SELECT u.iduser, u.nom, u.prenom, u.matricule, u.email, u.role
                 FROM users u
                 WHERE u.role IN ('inspecteur','chef_inspecteur') AND u.is_active = 1
                   AND u.iduser NOT IN (SELECT iduser FROM inspecteur)
                 ORDER BY u.nom, u.prenom"
            )->fetchAll();
            $ok(['data' => $rows]);
            break;

        case 'get':
            $id = (int) ($_POST['idinspecteur'] ?? 0);
            $st = $db->prepare(
                "SELECT i.*, d.libdirec
                 FROM inspecteur i
                 LEFT JOIN direction_anac d ON d.codedirec = i.codedirec
                 WHERE i.idinspecteur = ?"
            );
            $st->execute([$id]);
            $i = $st->fetch();
            if (!$i) { $fail('Inspecteur introuvable.'); break; }
            $hb = $db->prepare(
                "SELECT h.idhabilitation, h.iddomaine, h.numero_habilitation,
                        h.date_habilitation, h.date_expiration, h.decision, h.observation,
                        d.nomdomaine, d.libel_domaine
                 FROM habilitation h
                 JOIN domaine d ON d.iddomaine = h.iddomaine
                 WHERE h.idinspecteur = ?
                 ORDER BY h.date_expiration ASC"
            );
            $hb->execute([$id]);
            $ok(['data' => $i, 'habilitations' => $hb->fetchAll()]);
            break;

        case 'create':
        case 'update':
            $isUpdate   = ($action === 'update');
            $idinsp     = (int) ($_POST['idinspecteur'] ?? 0);
            $categorie  = trim($_POST['categorie'] ?? '');
            $trigr      = trim($_POST['trigr_inspecteur'] ?? '');
            $codedirec  = (int) ($_POST['codedirec'] ?? 0);
            $datenomine = trim($_POST['datenomine'] ?? '');
            $tele       = trim($_POST['teleinspecter'] ?? '');
            $cats       = ['stagiaire', 'titulaire', 'exceptionnel'];

            if (!in_array($categorie, $cats, true)) { $fail('Categorie invalide.'); break; }
            if ($trigr === '' || mb_strlen($trigr) > 41) { $fail('Trigramme invalide.'); break; }
            if ($codedirec <= 0) { $fail('Veuillez choisir une direction.'); break; }
            $stD = $db->prepare("SELECT codedirec FROM direction_anac WHERE codedirec = ?");
            $stD->execute([$codedirec]);
            if (!$stD->fetch()) { $fail('Direction inconnue.'); break; }
            // Date de nomination : facultative. NULL si vide, validee si fournie.
            if ($datenomine === '') {
                $datenomine = null;
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datenomine)) {
                $fail('Date de nomination invalide.'); break;
            }
            if ($tele === '' || mb_strlen($tele) > 100) { $fail('Telephone invalide.'); break; }

            // Lignes d'habilitation (tableaux paralleles, alignes par index)
            $hDom = $_POST['hab_domaine'] ?? [];
            $hNum = $_POST['hab_numero']  ?? [];
            $hDeb = $_POST['hab_debut']   ?? [];
            $hFin = $_POST['hab_fin']     ?? [];
            $hObs = $_POST['hab_obs']     ?? [];
            if (!is_array($hDom)) { $hDom = []; }

            $isStagiaire = ($categorie === 'stagiaire');
            $habRows     = [];
            $habError    = '';

            foreach ($hDom as $k => $dRaw) {
                $idd = (int) $dRaw;
                if ($idd <= 0) { continue; }
                $num = trim((string) ($hNum[$k] ?? ''));
                $deb = trim((string) ($hDeb[$k] ?? ''));
                $fin = trim((string) ($hFin[$k] ?? ''));
                $obs = trim((string) ($hObs[$k] ?? ''));

                if ($isStagiaire) {
                    // Stagiaire : domaine sans habilitation formelle -> numero vide
                    $num = '';
                } elseif ($num === '' || mb_strlen($num) > 50) {
                    $habError = 'Le numero d\'habilitation est requis pour chaque domaine.'; break;
                }
                // Dates d'habilitation facultatives : NULL si vides, validees si fournies
                if ($deb !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deb)) { $habError = 'Date de debut d\'habilitation invalide.'; break; }
                if ($fin !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) { $habError = 'Date d\'expiration invalide.'; break; }
                $habRows[] = ['idd' => $idd, 'num' => $num, 'deb' => ($deb === '' ? null : $deb), 'fin' => ($fin === '' ? null : $fin), 'obs' => $obs];
            }
            if ($habError !== '') { $fail($habError); break; }
            if (empty($habRows)) { $fail('Veuillez ajouter au moins un domaine.'); break; }

            // Securite : tous les domaines choisis doivent exister reellement
            $validDom = array_map('intval', $db->query("SELECT iddomaine FROM domaine")->fetchAll(PDO::FETCH_COLUMN));
            foreach ($habRows as $h) {
                if (!in_array($h['idd'], $validDom, true)) { $fail('Un domaine selectionne est invalide. Merci de le re-selectionner.'); break 2; }
            }

            $db->beginTransaction();
            try {
                $oldDec = [];   // decisions deja attachees (par domaine), a preserver en cas d'edition
                if (!$isUpdate) {
                    $iduser = (int) ($_POST['iduser'] ?? 0);
                    if ($iduser <= 0) { $db->rollBack(); $fail('Veuillez choisir l\'utilisateur inspecteur.'); break; }
                    $stU = $db->prepare(
                        "SELECT iduser, nom, prenom, matricule, email FROM users
                         WHERE iduser = ? AND role IN ('inspecteur','chef_inspecteur') AND is_active = 1"
                    );
                    $stU->execute([$iduser]);
                    $u = $stU->fetch();
                    if (!$u) { $db->rollBack(); $fail('Utilisateur invalide (doit etre un inspecteur ou chef inspecteur actif).'); break; }

                    $stX = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE iduser = ?");
                    $stX->execute([$iduser]);
                    if ($stX->fetch()) { $db->rollBack(); $fail('Cet utilisateur est deja enregistre comme inspecteur.'); break; }

                    $numat = (int) $u['matricule'];
                    $ins = $db->prepare(
                        "INSERT INTO inspecteur
                            (iduser, nominspecteur, trigr_inspecteur, numatinspecteur, codedirec, preninspect, categorie, datenomine, teleinspecter, mailinspect, photoinspecter)
                         VALUES (?,?,?,?,?,?,?,?,?,?,NULL)"
                    );
                    $ins->execute([$iduser, $u['nom'], $trigr, $numat, $codedirec, $u['prenom'], $categorie, $datenomine, $tele, $u['email']]);
                    $idinsp = (int) $db->lastInsertId();
                } else {
                    if ($idinsp <= 0) { $db->rollBack(); $fail('Inspecteur introuvable.'); break; }
                    $stG = $db->prepare("SELECT idinspecteur FROM inspecteur WHERE idinspecteur = ?");
                    $stG->execute([$idinsp]);
                    if (!$stG->fetch()) { $db->rollBack(); $fail('Inspecteur introuvable.'); break; }
                    // On ne modifie pas l'utilisateur lie ni ses infos (nom/prenom/matricule/email)
                    $upd = $db->prepare(
                        "UPDATE inspecteur SET trigr_inspecteur=?, codedirec=?, categorie=?, datenomine=?, teleinspecter=? WHERE idinspecteur=?"
                    );
                    $upd->execute([$trigr, $codedirec, $categorie, $datenomine, $tele, $idinsp]);
                    // Preserver les decisions deja attachees (matchees par domaine) avant reconstruction
                    $stOld = $db->prepare("SELECT iddomaine, decision FROM habilitation WHERE idinspecteur = ?");
                    $stOld->execute([$idinsp]);
                    foreach ($stOld->fetchAll() as $od) {
                        if (!empty($od['decision'])) { $oldDec[(int) $od['iddomaine']] = $od['decision']; }
                    }
                    $db->prepare("DELETE FROM habilitation WHERE idinspecteur = ?")->execute([$idinsp]);
                }

                $insH = $db->prepare(
                    "INSERT INTO habilitation
                        (idinspecteur, iddomaine, numero_habilitation, date_habilitation, date_expiration, decision, observation)
                     VALUES (?,?,?,?,?,?,?)"
                );
                $habOut = [];
                foreach ($habRows as $h) {
                    $dec = $oldDec[$h['idd']] ?? null;   // on reporte la decision deja attachee a ce domaine
                    $insH->execute([$idinsp, $h['idd'], $h['num'], $h['deb'], $h['fin'], $dec, ($h['obs'] !== '' ? $h['obs'] : null)]);
                    $habOut[] = ['idhabilitation' => (int) $db->lastInsertId(), 'iddomaine' => $h['idd']];
                }

                $db->commit();
                Audit::log($isUpdate ? 'update' : 'create', 'inspecteurs', ($isUpdate ? 'Modification' : 'Creation') . ' inspecteur #' . $idinsp);
                $ok([
                    'message'       => $isUpdate ? 'Inspecteur mis a jour.' : 'Inspecteur enregistre.',
                    'idinspecteur'  => $idinsp,
                    'habilitations' => $habOut
                ]);
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('inspecteur save : ' . $e->getMessage());
                $fail('Enregistrement impossible (donnees liees ou erreur technique).');
            }
            break;

        case 'delete':
            $id = (int) ($_POST['idinspecteur'] ?? 0);
            if ($id <= 0) { $fail('Inspecteur introuvable.'); break; }
            try {
                $db->beginTransaction();
                $db->prepare("DELETE FROM habilitation WHERE idinspecteur = ?")->execute([$id]);
                $db->prepare("DELETE FROM inspecteur WHERE idinspecteur = ?")->execute([$id]);
                $db->commit();
                Audit::log('delete', 'inspecteurs', 'Suppression inspecteur #' . $id);
                $ok(['message' => 'Inspecteur supprime.']);
            } catch (Throwable $e) {
                $db->rollBack();
                $fail('Suppression impossible : cet inspecteur est rattache a des audits ou des fiches. Detachez-le d\'abord.');
            }
            break;

        case 'upload_photo':
            $id = (int) ($_POST['idinspecteur'] ?? 0);
            if ($id <= 0) { $fail('Inspecteur introuvable.'); break; }
            $stc = $db->prepare("SELECT photoinspecter FROM inspecteur WHERE idinspecteur = ?");
            $stc->execute([$id]);
            $cur = $stc->fetch();
            if (!$cur) { $fail('Inspecteur introuvable.'); break; }
            $name = save_upload($_FILES['photo'] ?? null, ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp'], 2 * 1024 * 1024, dir_photos());
            if (!$name) { $fail('Photo invalide. Formats acceptes : JPG, PNG, WEBP (2 Mo maximum).'); break; }
            $db->prepare("UPDATE inspecteur SET photoinspecter = ? WHERE idinspecteur = ?")->execute([$name, $id]);
            if (!empty($cur['photoinspecter'])) { @unlink(dir_photos() . '/' . basename($cur['photoinspecter'])); }
            Audit::log('update', 'inspecteurs', 'Mise a jour photo inspecteur #' . $id);
            $ok(['message' => 'Photo enregistree.']);
            break;

        case 'upload_decision':
            $idh = (int) ($_POST['idhabilitation'] ?? 0);
            if ($idh <= 0) { $fail('Habilitation introuvable.'); break; }
            $stc = $db->prepare("SELECT decision FROM habilitation WHERE idhabilitation = ?");
            $stc->execute([$idh]);
            $cur = $stc->fetch();
            if (!$cur) { $fail('Habilitation introuvable.'); break; }
            $name = save_upload($_FILES['decision'] ?? null, ['pdf'], ['application/pdf'], 10 * 1024 * 1024, dir_decisions());
            if (!$name) { $fail('Decision invalide. Format accepte : PDF (10 Mo maximum).'); break; }
            $db->prepare("UPDATE habilitation SET decision = ? WHERE idhabilitation = ?")->execute([$name, $idh]);
            if (!empty($cur['decision'])) { @unlink(dir_decisions() . '/' . basename($cur['decision'])); }
            Audit::log('update', 'inspecteurs', 'Mise a jour decision habilitation #' . $idh);
            $ok(['message' => 'Decision enregistree.']);
            break;

        default:
            $fail('Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('inspecteurs endpoint : ' . $e->getMessage());
    $fail('Erreur technique.');
}