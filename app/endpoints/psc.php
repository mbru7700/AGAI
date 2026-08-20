<?php
/**
 * Endpoint AJAX : Programme de Surveillance Continue (PSC) - v2
 * Route : /api/psc
 * Structure matrice : { groupes:[ { rubrique, items:[ {sous_domaine, rag, cellules{} } ] } ] }
 * Securite : PDO prepare partout, CSRF, guard 'programme_psc', pas de fuite d'exception.
 */
if (!defined('SITE_URL')) { require_once dirname(__DIR__, 2) . '/config/config.php'; }

Rbac::guardApi('programme_psc');
// La consultation du programme signe s'ouvre dans une iframe (requete GET sans corps POST).
// Elle reste protegee par la session, l'habilitation au module et un identifiant numerique.
$actionBrute = (string)($_POST['action'] ?? $_GET['action'] ?? '');
if ($actionBrute !== 'serve_signe' && !Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalide.']); exit;
}

$db     = Database::getInstance();
$role   = Rbac::role();
$uid    = (int)($_SESSION['user_id'] ?? 0);
$isCI   = in_array($role, ['admin','chef_inspecteur'], true);
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$ok   = function($x=[]) { echo json_encode(['success'=>true]+$x, JSON_UNESCAPED_UNICODE); };
$fail = function($m)     { echo json_encode(['success'=>false,'message'=>$m], JSON_UNESCAPED_UNICODE); };

/** Nombre de semaines ISO (52/53) + libelle mois par semaine. */
function psc_weeks(int $year): array {
    $nb = (int)date('W', mktime(0,0,0,12,28,$year));
    $mc = [1=>'janv',2=>'févr',3=>'mars',4=>'avr',5=>'mai',6=>'juin',7=>'juil',8=>'août',9=>'sept',10=>'oct',11=>'nov',12=>'déc'];
    $yy = substr((string)$year, 2);
    $weeks = [];
    for ($w=1; $w<=$nb; $w++) {
        $dt = new DateTime(); $dt->setISODate($year, $w, 4);
        $m = (int)$dt->format('n');
        $weeks[] = ['sem'=>$w, 'mois_index'=>$m, 'mois'=>$mc[$m].'-'.$yy];
    }
    return ['nb'=>$nb, 'weeks'=>$weeks];
}

/** Options d'un domaine : RAG, sites, sous-domaines (rubriques+items), pays. */
function psc_options(Database $db, int $iddomaine): array {
    $rags = $db->execute("SELECT idreglement, code_reglement, libelle_reglement FROM reglement WHERE iddomaine=? ORDER BY code_reglement", [$iddomaine])->fetchAll();
    if (!$rags) { $rags = $db->execute("SELECT idreglement, code_reglement, libelle_reglement FROM reglement ORDER BY code_reglement LIMIT 400")->fetchAll(); }
    $sites = $db->execute("SELECT idsite, indicateur_oaci, nomsite, ville FROM site WHERE indicateur_oaci<>'' ORDER BY indicateur_oaci")->fetchAll();
    $pays  = $db->execute("SELECT idpays, nompays FROM pays_adna WHERE nompays<>'' ORDER BY nompays")->fetchAll();
    // La colonne item_sous_domaine peut ne pas exister -> on s'adapte.
    $hasItem = (bool)$db->execute("SHOW COLUMNS FROM sous_domaine LIKE 'item_sous_domaine'")->fetch();
    // Grands titres (distinct) et items (distinct) pour les listes deroulantes.
    $rubriques = $db->execute("SELECT nom_sousdomaine FROM sous_domaine WHERE nom_sousdomaine<>'' GROUP BY nom_sousdomaine ORDER BY nom_sousdomaine")->fetchAll(PDO::FETCH_COLUMN);
    $items = $hasItem
        ? $db->execute("SELECT item_sous_domaine FROM sous_domaine WHERE item_sous_domaine IS NOT NULL AND item_sous_domaine<>'' GROUP BY item_sous_domaine ORDER BY item_sous_domaine")->fetchAll(PDO::FETCH_COLUMN)
        : [];
    $sousdom = $hasItem
        ? $db->execute("SELECT idsousdomaine, nom_sousdomaine, item_sous_domaine FROM sous_domaine WHERE iddomaine=? ORDER BY nom_sousdomaine", [$iddomaine])->fetchAll()
        : $db->execute("SELECT idsousdomaine, nom_sousdomaine FROM sous_domaine WHERE iddomaine=? ORDER BY nom_sousdomaine", [$iddomaine])->fetchAll();
    // Operateurs (mode PSC Operateur) : uniquement le perimetre AGAI
    // (gere_agai='AGAI' OU ayant au moins un audit). nomorga affiche, trigramme = code cellule.
    $operateurs = $db->execute(
        "SELECT o.idorga, o.nomorga, o.trigrorganisme
         FROM organisme o
         WHERE o.nomorga <> ''
           AND (o.gere_agai = 'AGAI' OR EXISTS (SELECT 1 FROM audit ax WHERE ax.idorga = o.idorga))
         GROUP BY o.nomorga
         ORDER BY o.nomorga"
    )->fetchAll();
    return ['rags'=>$rags, 'sites'=>$sites, 'sousdomaines'=>$sousdom, 'pays'=>$pays, 'rubriques'=>$rubriques, 'items'=>$items, 'operateurs'=>$operateurs];
}

/** Normalise la matrice recue (structure en groupes) avec bornage/nettoyage. */
function psc_clean_matrice(array $decoded): array {
    $clean = function($v,$n){ return mb_substr(trim(strip_tags((string)$v)), 0, $n); };
    $groupes = [];
    $src = $decoded['groupes'] ?? [];
    if (!is_array($src)) $src = [];
    foreach ($src as $g) {
        if (!is_array($g)) continue;
        $items = [];
        $srcItems = (isset($g['items']) && is_array($g['items'])) ? $g['items'] : [];
        foreach ($srcItems as $it) {
            if (!is_array($it)) continue;
            $cell = [];
            if (isset($it['cellules']) && is_array($it['cellules'])) {
                // Liste blanche des actes de supervision autorises (mise en forme conditionnelle).
                $actesOk = ['audit','inspection_programmee','inspection_non_programmee','demonstration','test','investigation'];
                foreach ($it['cellules'] as $sem=>$val) {
                    $sem = (int)$sem;
                    if ($sem < 1 || $sem > 53) { continue; }
                    // Nouveau format : {code:"XXX", acte:"..."} ; ancien format : "XXX" (string)
                    if (is_array($val)) {
                        $code = mb_substr(trim(strip_tags((string)($val['code'] ?? ''))), 0, 50);
                        $acte = (string)($val['acte'] ?? '');
                        $acte = in_array($acte, $actesOk, true) ? $acte : '';
                        $coul = (string)($val['couleur'] ?? '');
                        $coul = in_array($coul, ['bleu','jaune'], true) ? $coul : '';
                        if ($code !== '') { $cell[(string)$sem] = ['code'=>$code, 'acte'=>$acte, 'couleur'=>$coul]; }
                    } else {
                        $code = mb_substr(trim(strip_tags((string)$val)), 0, 50);
                        if ($code !== '') { $cell[(string)$sem] = ['code'=>$code, 'acte'=>'', 'couleur'=>'']; }
                    }
                }
            }
            $items[] = [
                'sous_domaine' => $clean($it['sous_domaine'] ?? '', 255),
                'rag'          => $clean($it['rag'] ?? '', 100),
                'cellules'     => $cell,
            ];
            if (count($items) > 300) break;
        }
        $groupes[] = ['rubrique'=>$clean($g['rubrique'] ?? '', 255), 'items'=>$items];
        if (count($groupes) > 100) break;
    }
    return ['groupes'=>$groupes];
}

/* ------------------------------------------------------------------
 * Programme signe par le DG : stockage hors zone publique,
 * nom aleatoire, type MIME reel verifie (protection upload de shell).
 * ------------------------------------------------------------------ */
function psc_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/uploads/psc';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir;
}

/** Enregistre le PDF recu. Retourne le nom stocke, ou null si invalide/absent. */
function psc_save_pdf(?array $file): ?string
{
    if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    if ((int)($file['size'] ?? 0) <= 0) { return null; }
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) { return null; }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { return null; }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($finfo->file($file['tmp_name']) !== 'application/pdf') { return null; }

    $nom = 'psc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], psc_dir() . '/' . $nom)) { return null; }
    return $nom;
}

/** Supprime physiquement un programme signe devenu inutile. */
function psc_delete_pdf(?string $nom): void
{
    $nom = trim((string)$nom);
    if ($nom === '') { return; }
    $chemin = psc_dir() . '/' . basename($nom);
    if (is_file($chemin)) { @unlink($chemin); }
}

try { switch ($action) {

// ----------------------------------------------------------------
case 'refs':
    $types = $db->execute("SELECT idtypeorga, nomtypeorg FROM type_organisme WHERE nomtypeorg<>'' ORDER BY nomtypeorg")->fetchAll();
    $doms  = $db->execute("SELECT iddomaine, nomdomaine, libel_domaine FROM domaine ORDER BY nomdomaine")->fetchAll();
    $ok(['types'=>$types, 'domaines'=>$doms]);
    break;

// ----------------------------------------------------------------
case 'create':
    $annee = (int)($_POST['annee'] ?? 0);
    $idt   = (int)($_POST['idtypeorga'] ?? 0);
    $idd   = (int)($_POST['iddomaine'] ?? 0);
    if ($annee < 2000 || $annee > 2100) { $fail('Annee invalide.'); break; }
    if ($idt<=0 || $idd<=0) { $fail('Type d\'activite et domaine obligatoires.'); break; }
    $t=$db->prepare("SELECT nomtypeorg FROM type_organisme WHERE idtypeorga=?"); $t->execute([$idt]); $tr=$t->fetch();
    $d=$db->prepare("SELECT nomdomaine, libel_domaine FROM domaine WHERE iddomaine=?"); $d->execute([$idd]); $dr=$d->fetch();
    if (!$tr || !$dr) { $fail('Type ou domaine introuvable.'); break; }
    $ex=$db->prepare("SELECT idprogramme FROM psc_programme WHERE annee=? AND idtypeorga=? AND iddomaine=?");
    $ex->execute([$annee,$idt,$idd]); $exist=$ex->fetch();
    if ($exist) { $ok(['idprogramme'=>(int)$exist['idprogramme'],'existing'=>true,'message'=>'Programme deja existant, ouverture.']); break; }
    $mode = in_array(($_POST['mode'] ?? 'site'), ['site','operateur'], true) ? $_POST['mode'] : 'site';
    $wk = psc_weeks($annee);
    $libDom = trim(preg_replace('/\s+/', ' ', (string)$dr['libel_domaine'])) ?: $dr['nomdomaine'];
    $titre  = 'PROGRAMME DE SURVEILLANCE CONTINUE DES '.trim($tr['nomtypeorg']).' - '.$libDom.' '.$annee;
    $hasMode = (bool)$db->execute("SHOW COLUMNS FROM psc_programme LIKE 'mode_cible'")->fetch();
    if ($hasMode) {
        $db->prepare("INSERT INTO psc_programme (annee, idtypeorga, iddomaine, mode_cible, titre, nb_semaines, date_etablissement, iduser_createur) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$annee,$idt,$idd,$mode,mb_substr($titre,0,255),$wk['nb'],date('Y-m-d'),$uid]);
    } else {
        $db->prepare("INSERT INTO psc_programme (annee, idtypeorga, iddomaine, titre, nb_semaines, date_etablissement, iduser_createur) VALUES (?,?,?,?,?,?,?)")
           ->execute([$annee,$idt,$idd,mb_substr($titre,0,255),$wk['nb'],date('Y-m-d'),$uid]);
    }
    $idprog=(int)$db->lastInsertId();
    Audit::log('create','programme_psc',"Creation programme PSC #$idprog ($annee/$idt/$idd)");
    $ok(['idprogramme'=>$idprog,'existing'=>false]);
    break;

// ----------------------------------------------------------------
case 'get':
    $idprog = (int)($_POST['idprogramme'] ?? 0);
    $p = $db->execute(
        "SELECT p.*, t.nomtypeorg, d.nomdomaine, d.libel_domaine
         FROM psc_programme p
         LEFT JOIN type_organisme t ON t.idtypeorga=p.idtypeorga
         LEFT JOIN domaine d ON d.iddomaine=p.iddomaine
         WHERE p.idprogramme=? LIMIT 1", [$idprog]
    )->fetch();
    if (!$p) { $fail('Programme introuvable.'); break; }
    $p['mode_cible'] = $p['mode_cible'] ?? 'site';
    $wk = psc_weeks((int)$p['annee']);
    $matrice = $p['matrice'] ? json_decode($p['matrice'], true) : null;
    if (!is_array($matrice)) $matrice = ['groupes'=>[]];
    // Compatibilite ancien format {lignes:[...]} -> un groupe unique
    if (!isset($matrice['groupes']) && isset($matrice['lignes']) && is_array($matrice['lignes'])) {
        $items = [];
        foreach ($matrice['lignes'] as $l) {
            $items[] = ['sous_domaine'=>$l['sous_domaine'] ?? '', 'rag'=>$l['rag'] ?? '', 'cellules'=>$l['cellules'] ?? []];
        }
        $matrice = ['groupes'=>[['rubrique'=>'', 'items'=>$items]]];
    }
    if (!isset($matrice['groupes'])) $matrice['groupes'] = [];
    $ok(['programme'=>$p, 'weeks'=>$wk['weeks'], 'nb_semaines'=>$wk['nb'], 'matrice'=>$matrice, 'options'=>psc_options($db,(int)$p['iddomaine'])]);
    break;

// ----------------------------------------------------------------
case 'save':
    $idprog = (int)($_POST['idprogramme'] ?? 0);
    $pr = $db->prepare("SELECT iddomaine FROM psc_programme WHERE idprogramme=?"); $pr->execute([$idprog]); $prow=$pr->fetch();
    if (!$prow) { $fail('Programme introuvable.'); break; }
    $iddomaine = (int)$prow['iddomaine'];
    $decoded = json_decode((string)($_POST['matrice'] ?? ''), true);
    if (!is_array($decoded)) { $fail('Donnees de matrice invalides.'); break; }
    $matrice = psc_clean_matrice($decoded);
    // Validation serveur (ne jamais faire confiance au client) : au moins un item
    // complet requis = grand titre non vide + item non vide + RAG + au moins une cible.
    $nbComplets = 0;
    foreach ($matrice['groupes'] as $g) {
        $rub = trim((string)($g['rubrique'] ?? ''));
        $nbCompletsGroupe = 0;
        $aDuContenu = ($rub !== '');
        foreach ($g['items'] as $it) {
            $sd  = trim((string)($it['sous_domaine'] ?? ''));
            $rag = trim((string)($it['rag'] ?? ''));
            $nbCell = is_array($it['cellules'] ?? null) ? count($it['cellules']) : 0;
            // Ligne totalement vide : ignoree
            if ($sd === '' && $rag === '' && $nbCell === 0) { continue; }
            $aDuContenu = true;
            // Ligne partielle : incomplete -> on refuse
            if ($rub === '' || $sd === '' || $rag === '' || $nbCell === 0) {
                $fail('Enregistrement refuse : chaque ligne renseignee doit avoir un grand titre, un item, un RAG et au moins une cible.');
                break 2;
            }
            $nbComplets++; $nbCompletsGroupe++;
        }
        // Un grand titre avec du contenu (libelle ou items) doit avoir au moins un item complet
        if ($aDuContenu && $nbCompletsGroupe === 0) {
            $fail('Enregistrement refuse : le grand titre "' . mb_substr($rub, 0, 60) . '" doit contenir au moins un item complet (item, RAG et une cible).');
            break;
        }
    }
    if ($nbComplets === 0) { $fail('Enregistrement refuse : le programme ne contient aucun item complet.'); break; }
    $revision = mb_substr(trim(strip_tags((string)($_POST['revision'] ?? '00'))),0,20);
    $statut   = in_array(($_POST['statut'] ?? ''), ['brouillon','valide'], true) ? $_POST['statut'] : 'brouillon';
    $nbItems = 0; foreach ($matrice['groupes'] as $g) { $nbItems += count($g['items']); }
    $db->prepare("UPDATE psc_programme SET matrice=?, revision=?, statut=?, updated_at=NOW() WHERE idprogramme=?")
       ->execute([json_encode($matrice, JSON_UNESCAPED_UNICODE), $revision, $statut, $idprog]);

    // Persistance du referentiel (grands titres + items) dans sous_domaine, dedoublonnee.
    // item_sous_domaine est insere en '' (jamais NULL) pour rester compatible NOT NULL.
    $nbRefs = 0;
    $hasItem = (bool)$db->execute("SHOW COLUMNS FROM sous_domaine LIKE 'item_sous_domaine'")->fetch();
    if ($hasItem && $iddomaine > 0) {
        foreach ($matrice['groupes'] as $g) {
            $rub = $g['rubrique'];
            if ($rub === '') continue;
            $its = [];
            foreach ($g['items'] as $it) { if (($it['sous_domaine'] ?? '') !== '') $its[] = $it['sous_domaine']; }
            if (empty($its)) { $its = ['']; }
            foreach (array_unique($its) as $item) {
                if ($item === '') {
                    $c = $db->prepare("SELECT 1 FROM sous_domaine WHERE iddomaine=? AND nom_sousdomaine=? AND (item_sous_domaine IS NULL OR item_sous_domaine='') LIMIT 1");
                    $c->execute([$iddomaine, $rub]);
                } else {
                    $c = $db->prepare("SELECT 1 FROM sous_domaine WHERE iddomaine=? AND nom_sousdomaine=? AND item_sous_domaine=? LIMIT 1");
                    $c->execute([$iddomaine, $rub, $item]);
                }
                if (!$c->fetch()) {
                    $db->prepare("INSERT INTO sous_domaine (iddomaine, nom_sousdomaine, item_sous_domaine) VALUES (?,?,?)")
                       ->execute([$iddomaine, $rub, $item]);
                    $nbRefs++;
                }
            }
        }
    }

    Audit::log('update','programme_psc',"Sauvegarde programme PSC #$idprog (".count($matrice['groupes'])." groupes, $nbItems items, $nbRefs refs)");
    $ok(['message'=>'Programme enregistre.', 'nb_groupes'=>count($matrice['groupes']), 'nb_items'=>$nbItems, 'nb_refs'=>$nbRefs]);
    break;

// ----------------------------------------------------------------
case 'list':
    $rows = $db->execute(
        "SELECT p.idprogramme, p.annee, p.titre, p.revision, p.statut, p.nb_semaines, p.updated_at, p.matrice,
                p.fichier_signe, p.date_signature,
                t.nomtypeorg, d.nomdomaine, d.libel_domaine
         FROM psc_programme p
         LEFT JOIN type_organisme t ON t.idtypeorga=p.idtypeorga
         LEFT JOIN domaine d ON d.iddomaine=p.iddomaine
         ORDER BY p.updated_at DESC, p.annee DESC"
    )->fetchAll();
    // Enrichissement : sites utilises + nb items (parse de la matrice), sans renvoyer la matrice complete
    foreach ($rows as &$r) {
        $sites = []; $nbItems = 0;
        $m = $r['matrice'] ? json_decode($r['matrice'], true) : null;
        if (is_array($m) && !empty($m['groupes'])) {
            foreach ($m['groupes'] as $g) {
                foreach (($g['items'] ?? []) as $it) {
                    $nbItems++;
                    foreach (($it['cellules'] ?? []) as $v) {
                        // Nouveau format {code,acte} ou ancien format string
                        $code = is_array($v) ? (string)($v['code'] ?? '') : (string)$v;
                        if ($code !== '') { $sites[$code] = true; }
                    }
                }
            }
        }
        $r['sites_used'] = implode(',', array_keys($sites));
        $r['nb_items']   = $nbItems;
        unset($r['matrice']);
    }
    unset($r);
    $ok(['data'=>$rows]);
    break;

// ----------------------------------------------------------------
case 'delete':
    if (!$isCI) { $fail('Action reservee au CI et Admin.'); break; }
    $idprog = (int)($_POST['idprogramme'] ?? 0);
    $st=$db->prepare("SELECT titre, fichier_signe FROM psc_programme WHERE idprogramme=?"); $st->execute([$idprog]); $r=$st->fetch();
    if (!$r) { $fail('Programme introuvable.'); break; }
    $db->prepare("DELETE FROM psc_programme WHERE idprogramme=?")->execute([$idprog]);
    // Liberation de l'espace disque : le document signe suit le programme
    psc_delete_pdf($r['fichier_signe'] ?? null);
    Audit::log('delete','programme_psc',"Suppression programme PSC #$idprog");
    $ok(['message'=>'Programme supprime.']);
    break;

// ----------------------------------------------------------------
// Ajout dynamique : site (avec pays)
// ----------------------------------------------------------------
case 'create_site':
    $oaci = strtoupper(trim(strip_tags($_POST['indicateur_oaci'] ?? '')));
    $nom  = trim(strip_tags($_POST['nomsite'] ?? ''));
    $ville= trim(strip_tags($_POST['ville'] ?? ''));
    $idp  = (int)($_POST['idpays'] ?? 0);
    if ($oaci==='' || mb_strlen($oaci)>10) { $fail('Indicateur OACI requis (10 car. max).'); break; }
    if ($nom==='') { $nom = $oaci; }
    $c=$db->prepare("SELECT idsite FROM site WHERE UPPER(indicateur_oaci)=? LIMIT 1"); $c->execute([$oaci]); $e=$c->fetch();
    if ($e) { $ok(['idsite'=>(int)$e['idsite'],'indicateur_oaci'=>$oaci,'nomsite'=>$nom,'message'=>'Site deja existant.']); break; }
    $db->prepare("INSERT INTO site (indicateur_oaci, nomsite, idpays, ville) VALUES (?,?,?,?)")
       ->execute([$oaci, mb_substr($nom,0,150), ($idp>0?$idp:null), mb_substr($ville,0,150)]);
    $id=(int)$db->lastInsertId();
    Audit::log('create','programme_psc',"Nouveau site #$id ($oaci) via PSC");
    $ok(['idsite'=>$id,'indicateur_oaci'=>$oaci,'nomsite'=>$nom,'message'=>'Site cree.']);
    break;

// ----------------------------------------------------------------
// Ajout dynamique : pays
// ----------------------------------------------------------------
case 'create_pays':
    $nom = trim(strip_tags($_POST['nompays'] ?? ''));
    if ($nom==='' || mb_strlen($nom)>50) { $fail('Nom du pays requis (50 car. max).'); break; }
    $c=$db->prepare("SELECT idpays FROM pays_adna WHERE LOWER(nompays)=LOWER(?) LIMIT 1"); $c->execute([$nom]); $e=$c->fetch();
    if ($e) { $ok(['idpays'=>(int)$e['idpays'],'nompays'=>$nom,'message'=>'Pays deja existant.']); break; }
    $nid = (int)$db->execute("SELECT COALESCE(MAX(idpays),0)+1 AS n FROM pays_adna")->fetch()['n'];
    $db->prepare("INSERT INTO pays_adna (idpays, nompays) VALUES (?,?)")->execute([$nid,$nom]);
    Audit::log('create','programme_psc',"Nouveau pays #$nid ($nom) via PSC");
    $ok(['idpays'=>$nid,'nompays'=>$nom,'message'=>'Pays cree.']);
    break;

// ----------------------------------------------------------------
// Ajout dynamique : reglement (RAG)
// ----------------------------------------------------------------
case 'create_reglement':
    $code = trim(strip_tags($_POST['code_reglement'] ?? ''));
    $lib  = trim(strip_tags($_POST['libelle_reglement'] ?? ''));
    $idd  = (int)($_POST['iddomaine'] ?? 0);
    if ($code==='' || mb_strlen($code)>50) { $fail('Code RAG requis (50 car. max).'); break; }
    if ($idd<=0) { $fail('Domaine requis.'); break; }
    if ($lib==='') { $lib = $code; }
    $c=$db->prepare("SELECT idreglement FROM reglement WHERE iddomaine=? AND LOWER(code_reglement)=LOWER(?) LIMIT 1"); $c->execute([$idd,$code]); $e=$c->fetch();
    if ($e) { $ok(['idreglement'=>(int)$e['idreglement'],'code_reglement'=>$code,'libelle_reglement'=>$lib,'message'=>'RAG deja existant.']); break; }
    $db->prepare("INSERT INTO reglement (iddomaine, code_reglement, libelle_reglement, description) VALUES (?,?,?,?)")
       ->execute([$idd,$code,mb_substr($lib,0,255),$lib]);
    $id=(int)$db->lastInsertId();
    Audit::log('create','programme_psc',"Nouveau reglement #$id ($code) via PSC");
    $ok(['idreglement'=>$id,'code_reglement'=>$code,'libelle_reglement'=>$lib,'message'=>'RAG cree.']);
    break;

// ----------------------------------------------------------------
// Ajout dynamique : sous-domaine (rubrique + item)
// ----------------------------------------------------------------
case 'create_sousdomaine':
    $rub  = trim(strip_tags($_POST['nom_sousdomaine'] ?? ''));
    $item = mb_substr(trim(strip_tags($_POST['item_sous_domaine'] ?? '')), 0, 255);
    $idd  = (int)($_POST['iddomaine'] ?? 0);
    if ($rub==='' || mb_strlen($rub)>255) { $fail('Rubrique (REFERENTIEL) requise (255 car. max).'); break; }
    if ($idd<=0) { $fail('Domaine requis.'); break; }
    // La colonne item_sous_domaine peut ne pas exister si la migration n'a pas ete lancee.
    $hasItem = (bool)$db->execute("SHOW COLUMNS FROM sous_domaine LIKE 'item_sous_domaine'")->fetch();
    if ($hasItem) {
        // Dedoublonnage : meme iddomaine + nom_sousdomaine + item_sous_domaine -> on renvoie l'existant
        if ($item === '') {
            $c=$db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE iddomaine=? AND nom_sousdomaine=? AND (item_sous_domaine IS NULL OR item_sous_domaine='') LIMIT 1");
            $c->execute([$idd,$rub]);
        } else {
            $c=$db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE iddomaine=? AND nom_sousdomaine=? AND item_sous_domaine=? LIMIT 1");
            $c->execute([$idd,$rub,$item]);
        }
        $e=$c->fetch();
        if ($e) { $ok(['idsousdomaine'=>(int)$e['idsousdomaine'],'nom_sousdomaine'=>$rub,'item_sous_domaine'=>$item,'message'=>'Deja existant.']); break; }
        $db->prepare("INSERT INTO sous_domaine (iddomaine, nom_sousdomaine, item_sous_domaine) VALUES (?,?,?)")
           ->execute([$idd,$rub,$item]); // '' plutot que NULL (compat NOT NULL)
    } else {
        // Colonne absente : dedoublonnage sur le nom seul, insertion sans item.
        $c=$db->prepare("SELECT idsousdomaine FROM sous_domaine WHERE iddomaine=? AND nom_sousdomaine=? LIMIT 1");
        $c->execute([$idd,$rub]); $e=$c->fetch();
        if ($e) { $ok(['idsousdomaine'=>(int)$e['idsousdomaine'],'nom_sousdomaine'=>$rub,'item_sous_domaine'=>'','message'=>'Deja existant. (Astuce : lancez la migration item_sous_domaine.)']); break; }
        $db->prepare("INSERT INTO sous_domaine (iddomaine, nom_sousdomaine) VALUES (?,?)")->execute([$idd,$rub]);
    }
    $id=(int)$db->lastInsertId();
    Audit::log('create','programme_psc',"Nouveau sous-domaine #$id via PSC");
    $ok(['idsousdomaine'=>$id,'nom_sousdomaine'=>$rub,'item_sous_domaine'=>$item,'message'=>'Sous-domaine cree.']);
    break;

// ----------------------------------------------------------------
// Ajout dynamique : type d'activite
// ----------------------------------------------------------------
case 'create_type':
    $nom = trim(strip_tags($_POST['nomtypeorg'] ?? ''));
    if ($nom==='' || mb_strlen($nom)>255) { $fail('Nom du type requis (255 car. max).'); break; }
    $c=$db->prepare("SELECT idtypeorga FROM type_organisme WHERE LOWER(nomtypeorg)=LOWER(?) LIMIT 1"); $c->execute([$nom]); $e=$c->fetch();
    if ($e) { $ok(['idtypeorga'=>(int)$e['idtypeorga'],'nomtypeorg'=>$nom,'message'=>'Deja existant.']); break; }
    $db->prepare("INSERT INTO type_organisme (nomtypeorg, datesaizi, numat) VALUES (?,?,?)")->execute([$nom, date('Y-m-d H:i:s'), $uid]);
    $id=(int)$db->lastInsertId();
    Audit::log('create','programme_psc',"Nouveau type activite #$id via PSC");
    $ok(['idtypeorga'=>$id,'nomtypeorg'=>$nom,'message'=>'Type cree.']);
    break;

// ----------------------------------------------------------------
// Ajout dynamique : domaine
// ----------------------------------------------------------------
case 'create_domaine':
    $nomd = trim(strip_tags($_POST['nomdomaine'] ?? ''));
    $lib  = trim(strip_tags($_POST['libel_domaine'] ?? ''));
    if ($nomd==='' || mb_strlen($nomd)>10) { $fail('Code domaine requis (10 car. max).'); break; }
    if ($lib==='') { $lib = $nomd; }
    $c=$db->prepare("SELECT iddomaine FROM domaine WHERE LOWER(nomdomaine)=LOWER(?) LIMIT 1"); $c->execute([$nomd]); $e=$c->fetch();
    if ($e) { $ok(['iddomaine'=>(int)$e['iddomaine'],'nomdomaine'=>$nomd,'libel_domaine'=>$lib,'message'=>'Deja existant.']); break; }
    $db->prepare("INSERT INTO domaine (nomdomaine, libel_domaine) VALUES (?,?)")->execute([$nomd, mb_substr($lib,0,100)]);
    $id=(int)$db->lastInsertId();
    Audit::log('create','programme_psc',"Nouveau domaine #$id via PSC");
    $ok(['iddomaine'=>$id,'nomdomaine'=>$nomd,'libel_domaine'=>$lib,'message'=>'Domaine cree.']);
    break;

// ----------------------------------------------------------------
// Ajout dynamique : operateur (organisme)
// ----------------------------------------------------------------
case 'create_operateur':
    $nom  = trim(strip_tags($_POST['nomorga'] ?? ''));
    $trig = strtoupper(trim(strip_tags($_POST['trigrorganisme'] ?? '')));
    $ville= trim(strip_tags($_POST['ville_org'] ?? ''));
    $idp  = (int)($_POST['idpays'] ?? 0);
    if ($nom==='' || mb_strlen($nom)>255) { $fail('Nom de l\'operateur requis (255 car. max).'); break; }
    $c=$db->prepare("SELECT idorga, trigrorganisme FROM organisme WHERE LOWER(nomorga)=LOWER(?) LIMIT 1"); $c->execute([$nom]); $e=$c->fetch();
    if ($e) { $ok(['idorga'=>(int)$e['idorga'],'nomorga'=>$nom,'trigrorganisme'=>$e['trigrorganisme'],'message'=>'Operateur deja existant.']); break; }
    $db->prepare("INSERT INTO organisme (nomorga, typeorga, lieuorga, adresorga, telorga, emailorga, faxorga, statutorga, trigrorganisme, createur, datexpire, siteactivite, cateoperater, nom_commercial_org, ville_org, idpays, boite_postal_org)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([mb_substr($nom,0,255), 0, '', '', '', '', '', '', mb_substr($trig,0,70), $uid, '2099-12-31', '', '', '', mb_substr($ville,0,150), ($idp>0?$idp:0), '']);
    $id=(int)$db->lastInsertId();
    Audit::log('create','programme_psc',"Nouvel operateur #$id ($nom) via PSC");
    $ok(['idorga'=>$id,'nomorga'=>$nom,'trigrorganisme'=>$trig,'message'=>'Operateur cree.']);
    break;

// ----------------------------------------------------------------
// Renseigner le trigramme d'un operateur (si vide)
// ----------------------------------------------------------------
case 'set_trigram':
    $idorga = (int)($_POST['idorga'] ?? 0);
    $trig   = strtoupper(trim(strip_tags($_POST['trigrorganisme'] ?? '')));
    if ($idorga<=0) { $fail('Operateur invalide.'); break; }
    if ($trig==='' || mb_strlen($trig)>70) { $fail('Trigramme requis (70 car. max).'); break; }
    $c=$db->prepare("SELECT idorga FROM organisme WHERE idorga=?"); $c->execute([$idorga]);
    if (!$c->fetch()) { $fail('Operateur introuvable.'); break; }
    $db->prepare("UPDATE organisme SET trigrorganisme=? WHERE idorga=?")->execute([$trig,$idorga]);
    Audit::log('update','programme_psc',"Trigramme operateur #$idorga = $trig via PSC");
    $ok(['idorga'=>$idorga,'trigrorganisme'=>$trig,'message'=>'Trigramme enregistre.']);
    break;

// ----------------------------------------------------------------
// Statut des cellules : audits deja declenches pour ce programme
// (match par type d'activite + annee ; le rapprochement fin site/operateur
//  + semaine ISO est fait cote client).
// ----------------------------------------------------------------
case 'cell_status':
    $idprog = (int)($_POST['idprogramme'] ?? 0);
    $p = $db->execute("SELECT annee, idtypeorga FROM psc_programme WHERE idprogramme=? LIMIT 1", [$idprog])->fetch();
    if (!$p) { $fail('Programme introuvable.'); break; }
    $annee = (int)$p['annee']; $idt = (int)$p['idtypeorga'];
    $rows = $db->execute(
        "SELECT a.idaudit, a.idsite, a.idorga, a.site_inspection, a.date_previsionnelle, a.statut, a.type_activite, o.trigrorganisme
         FROM audit a LEFT JOIN organisme o ON o.idorga = a.idorga
         WHERE a.idtypeorga = ? AND a.date_previsionnelle IS NOT NULL AND YEAR(a.date_previsionnelle) = ?",
        [$idt, $annee]
    )->fetchAll();
    $ok(['audits'=>$rows]);
    break;

// ----------------------------------------------------------------
// Actes deja declenches (pour colorer la vue Declenchement du programme)
// ----------------------------------------------------------------
case 'triggered':
    $idprog = (int)($_POST['idprogramme'] ?? 0);
    $pp = $db->prepare("SELECT annee, iddomaine, mode_cible FROM psc_programme WHERE idprogramme=?");
    try { $pp->execute([$idprog]); $prow=$pp->fetch(); }
    catch (Throwable $e2) { // colonne mode_cible absente
        $pp = $db->prepare("SELECT annee, iddomaine FROM psc_programme WHERE idprogramme=?");
        $pp->execute([$idprog]); $prow=$pp->fetch(); if ($prow) $prow['mode_cible']='site';
    }
    if (!$prow) { $fail('Programme introuvable.'); break; }
    $annee = (int)$prow['annee'];
    $modeP = (($prow['mode_cible'] ?? 'site') === 'operateur') ? 'operateur' : 'site';

    // Fenetre elargie : une semaine ISO peut deborder sur l'annee civile
    // (ex: S1 2026 commence le 29/12/2025). Filtrage exact par annee ISO ci-dessous.
    $dmin = ($annee - 1) . '-12-20';
    $dmax = ($annee + 1) . '-01-10';
    $rows = $db->execute(
        "SELECT a.idaudit, a.num_audit, a.idsite, a.site_inspection, a.idorga, a.date_previsionnelle,
                a.statut, a.type_activite, o.trigrorganisme, o.nomorga, s.indicateur_oaci, s.nomsite
         FROM audit a
         LEFT JOIN organisme o ON o.idorga = a.idorga
         LEFT JOIN site s      ON s.idsite = a.idsite
         WHERE a.date_previsionnelle IS NOT NULL AND a.date_previsionnelle BETWEEN ? AND ?
         ORDER BY a.date_previsionnelle", [$dmin, $dmax]
    )->fetchAll();

    // Table de correspondance nom de site -> indicateur OACI (repli si idsite absent)
    $siteByName = [];
    foreach ($db->execute("SELECT indicateur_oaci, nomsite FROM site WHERE indicateur_oaci<>''")->fetchAll() as $sx) {
        $siteByName[mb_strtoupper(trim((string)$sx['nomsite']))] = $sx['indicateur_oaci'];
    }

    // Construction de la carte : "CODE|SEMAINE" => {statut, num}
    $map = []; $diag = [];
    foreach ($rows as $a) {
        $dp = (string)$a['date_previsionnelle'];
        if ($dp === '' || $dp === '0000-00-00') continue;
        try { $dt = new DateTime(substr($dp, 0, 10)); } catch (Throwable $e3) { continue; }
        if ((int)$dt->format('o') !== $annee) continue;   // 'o' = annee ISO
        $sem = (int)$dt->format('W');                      // 'W' = semaine ISO
        if ($sem < 1 || $sem > 53) continue;

        $code = '';
        if ($modeP === 'operateur') {
            $code = trim((string)$a['trigrorganisme']);
        } else {
            $code = trim((string)$a['indicateur_oaci']);
            if ($code === '') {
                $si = mb_strtoupper(trim((string)$a['site_inspection']));
                if ($si !== '') {
                    if (isset($siteByName[$si])) { $code = $siteByName[$si]; }
                    else { $code = trim(explode(' ', $si)[0]); } // ex: "FOOL - Libreville" -> FOOL
                }
            }
        }
        if ($code === '') { $diag[] = ['num'=>(string)$a['num_audit'],'dprev'=>$dp,'sem'=>$sem,'code'=>'(introuvable)']; continue; }
        $map[mb_strtoupper($code) . '|' . $sem] = [
            'statut' => (int)($a['statut'] ?? 1),
            'num'    => (string)$a['num_audit'],
        ];
        $diag[] = ['num'=>(string)$a['num_audit'],'dprev'=>$dp,'sem'=>$sem,'code'=>mb_strtoupper($code),'statut'=>(int)($a['statut'] ?? 1)];
    }
    $ok([
        'map'        => $map,
        'nb'         => count($map),
        'nb_audits'  => count($rows),   // audits trouves dans la fenetre de dates
        'annee'      => $annee,
        'mode'       => $modeP,
        'detail'     => array_slice($diag, 0, 20)  // aide au diagnostic
    ]);
    break;

// ----------------------------------------------------------------
// Programme signe par le DG : depot (remplace l'ancien fichier)
// ----------------------------------------------------------------
case 'upload_signe':
    if (!$isCI) { $fail('Action reservee au chef inspecteur et a l\'administrateur.'); break; }
    $idprog = (int)($_POST['idprogramme'] ?? 0);
    $dateSig = trim((string)($_POST['date_signature'] ?? ''));
    if ($dateSig !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateSig)) { $dateSig = ''; }

    $stP = $db->prepare("SELECT fichier_signe FROM psc_programme WHERE idprogramme=? LIMIT 1");
    $stP->execute([$idprog]);
    $rowP = $stP->fetch();
    if (!$rowP) { $fail('Programme introuvable.'); break; }

    $nomFic = psc_save_pdf($_FILES['fichier_signe'] ?? null);
    if ($nomFic === null) {
        $fail('Document invalide. Format accepte : PDF. Si le fichier est volumineux, verifiez upload_max_filesize et post_max_size dans php.ini.');
        break;
    }
    $ancien = trim((string)($rowP['fichier_signe'] ?? ''));
    // Le depot du document signe par le DG vaut VALIDATION du programme :
    // le statut bascule automatiquement de brouillon a valide.
    $db->prepare("UPDATE psc_programme SET fichier_signe=?, date_signature=?, statut='valide' WHERE idprogramme=?")
       ->execute([$nomFic, ($dateSig !== '' ? $dateSig : null), $idprog]);
    // L'ancien exemplaire est efface du disque : un seul programme signe par version
    if ($ancien !== '' && $ancien !== $nomFic) { psc_delete_pdf($ancien); }

    Audit::log('validate','programme_psc',"Validation du PSC #$idprog par depot du programme signe");
    $ok([
        'message'        => 'Programme signe enregistre. Le programme est desormais valide.',
        'fichier'        => $nomFic,
        'date_signature' => $dateSig,
        'statut'         => 'valide'
    ]);
    break;

// ----------------------------------------------------------------
// Programme signe : consultation (lecture seule, fichier hors zone publique)
// ----------------------------------------------------------------
case 'serve_signe':
    $idprog = (int)($_POST['idprogramme'] ?? $_GET['idprogramme'] ?? 0);
    if ($idprog <= 0) { $fail('Programme introuvable.'); break; }
    // Le programme signe n'est consultable que s'il a ete valide : un brouillon
    // n'a pas de document officiel a diffuser.
    $stS = $db->prepare("SELECT fichier_signe, annee, statut FROM psc_programme WHERE idprogramme=? LIMIT 1");
    $stS->execute([$idprog]); $rowS = $stS->fetch();
    if (!$rowS || trim((string)$rowS['fichier_signe']) === '' || ($rowS['statut'] ?? '') !== 'valide') {
        Audit::log('access_denied','programme_psc',"Tentative de consultation du programme signe #$idprog");
        $fail('Document indisponible.'); break;
    }
    $chemin = psc_dir() . '/' . basename((string)$rowS['fichier_signe']);
    if (!is_file($chemin)) { $fail('Fichier introuvable sur le serveur.'); break; }
    Audit::log('download','programme_psc',"Consultation du programme signe PSC #$idprog");
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Programme_PSC_' . (int)$rowS['annee'] . '_signe.pdf"');
    header('Content-Length: ' . filesize($chemin));
    header('X-Content-Type-Options: nosniff');
    readfile($chemin);
    exit;

// ----------------------------------------------------------------
// Programme signe : retrait
// ----------------------------------------------------------------
case 'delete_signe':
    if (!$isCI) { $fail('Action reservee au chef inspecteur et a l\'administrateur.'); break; }
    $idprog = (int)($_POST['idprogramme'] ?? 0);
    $stD = $db->prepare("SELECT fichier_signe FROM psc_programme WHERE idprogramme=? LIMIT 1");
    $stD->execute([$idprog]); $rowD = $stD->fetch();
    if (!$rowD) { $fail('Programme introuvable.'); break; }
    $db->prepare("UPDATE psc_programme SET fichier_signe=NULL, date_signature=NULL WHERE idprogramme=?")->execute([$idprog]);
    psc_delete_pdf($rowD['fichier_signe'] ?? null);
    Audit::log('delete','programme_psc',"Retrait du programme signe PSC #$idprog");
    $ok(['message'=>'Programme signe retire.']);
    break;

default: $fail('Action inconnue.');
}} catch (Throwable $e) {
    error_log('psc: '.$e->getMessage());
    $fail('Erreur technique. Operation non realisee.');
}