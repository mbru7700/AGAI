<?php
/**
 * Classe Rbac - Controle d'acces par role et habilitations
 * ----------------------------------------------------------
 * Source de verite : table `user_modules` (habilitations individuelles).
 * Fallback : matrice de roles si la table est vide pour cet utilisateur.
 */
class Rbac
{
    /** Matrice de fallback role -> modules feuilles (si user_modules vide). */
    private const MATRIX = [
        'admin'           => ['dashboard','audits','revue_doc','notifications','rapports','qre','actes','inspecteurs','exploitants','domaines','sousdomaines','typesorganisme','reglements','sites','nonconformites','analyse_psc','analyse_fnc','mise_oeuvre','users','parametres','cybersecurite'],
        'chef_inspecteur' => ['dashboard','audits','revue_doc','notifications','rapports','qre','actes','inspecteurs','exploitants','domaines','sousdomaines','typesorganisme','reglements','sites','nonconformites','users','cybersecurite'],
        'inspecteur'      => ['dashboard','audits','revue_doc','notifications','rapports','qre','nonconformites','inspecteurs','domaines','reglements'],
        'operateur'       => ['dashboard','actes','qre','notifications','rapports','audits'],
        'consultant'      => ['dashboard','audits','revue_doc','notifications','rapports','qre','nonconformites','domaines','reglements'],
    ];

    /** Tous les modules disponibles dans AGAI avec leurs labels.
     *  Structure : module_key => [label, icon, desc, children?]
     *  Les modules avec 'children' sont des groupes (non stockes en BDD).
     *  Les enfants sont les vrais modules stockes dans user_modules.
     */
    public const MODULES = [
        'dashboard' => [
            'label' => 'Tableau de bord',
            'icon'  => 'bi-speedometer2',
            'desc'  => 'Acces au tableau de bord principal',
        ],
        'audits_group' => [
            'label'    => 'Gestion Supervision',
            'icon'     => 'bi-clipboard-check',
            'desc'     => 'Ensemble des fonctionnalites liees aux audits et supervision',
            'is_group' => true,
            'children' => [
                'audits'        => ['label'=>'Audits et inspections', 'icon'=>'bi-clipboard-check',       'desc'=>'Declenchement, suivi et cloture des audits'],
                'revue_doc'     => ['label'=>'Revue documentaire',    'icon'=>'bi-file-text',             'desc'=>'Formulaire IX-GEN-R3-F-I-017'],
                'notifications' => ['label'=>'Notifications',         'icon'=>'bi-bell',                  'desc'=>'Lettres de notification aux operateurs'],
                'rapports'      => ['label'=>'Rapports',              'icon'=>'bi-file-earmark-text',     'desc'=>'Rapports d\'actes de supervision IX-GEN-R3-FI-009'],
                'qre'           => ['label'=>'Formulaire QRE',        'icon'=>'bi-ui-checks-grid',        'desc'=>'Questionnaire de Retour d\'Experience IX-GEN-R3-FI-011'],
                'actes'         => ['label'=>'Mes actes de superv.',  'icon'=>'bi-eye',                   'desc'=>'Vue personnalisee par operateur'],
            ],
        ],
        'structures_group' => [
            'label'    => 'Donnees de structures',
            'icon'     => 'bi-diagram-3',
            'desc'     => 'Referentiels et donnees de base',
            'is_group' => true,
            'children' => [
                'inspecteurs'    => ['label'=>'Inspecteurs',         'icon'=>'bi-person-badge',  'desc'=>'Gestion des inspecteurs et habilitations'],
                'exploitants'    => ['label'=>'Exploitants',         'icon'=>'bi-buildings',     'desc'=>'Operateurs et compagnies aeriennes'],
                'domaines'       => ['label'=>'Domaines',            'icon'=>'bi-grid-3x3-gap',  'desc'=>'Domaines de surveillance'],
                'sousdomaines'   => ['label'=>'Sous-domaines',       'icon'=>'bi-diagram-2',     'desc'=>'Sous-domaines par domaine'],
                'typesorganisme' => ['label'=>'Types d\'activite',   'icon'=>'bi-tags',          'desc'=>'Categories d\'operateurs'],
                'reglements'     => ['label'=>'Reglements',          'icon'=>'bi-journal-text',  'desc'=>'Textes reglementaires applicables'],
                'sites'          => ['label'=>'Sites d\'inspection', 'icon'=>'bi-geo-alt',       'desc'=>'Sites identifies par indicateur OACI'],
            ],
        ],
        'nc_group' => [
            'label'    => 'Non-conformites',
            'icon'     => 'bi-exclamation-triangle',
            'desc'     => 'Suivi des non-conformites et FNC',
            'is_group' => true,
            'children' => [
                'nonconformites' => ['label'=>'Suivi FNC', 'icon'=>'bi-list-check', 'desc'=>'Fiches de non-conformite'],
            ],
        ],
        'analyse_group' => [
            'label'    => 'Analyse des donnees',
            'icon'     => 'bi-graph-up',
            'desc'     => 'Tableaux de bord analytiques',
            'is_group' => true,
            'children' => [
                'analyse_psc'  => ['label'=>'Analyse PSC',            'icon'=>'bi-bar-chart',   'desc'=>'Programme de surveillance continue'],
                'analyse_fnc'  => ['label'=>'Analyse FNC',            'icon'=>'bi-pie-chart',   'desc'=>'Statistiques non-conformites'],
                'mise_oeuvre'  => ['label'=>'Mise en oeuvre regl.',   'icon'=>'bi-shield-check','desc'=>'Suivi mise en oeuvre reglementaire'],
            ],
        ],
        'users' => [
            'label' => 'Gestion des utilisateurs',
            'icon'  => 'bi-people',
            'desc'  => 'Creer et gerer les comptes et habilitations',
        ],
        'parametres' => [
            'label' => 'Parametres',
            'icon'  => 'bi-gear',
            'desc'  => 'Configuration generale de l\'application',
        ],
        'cybersecurite' => [
            'label' => 'Cybersecurite AGAI',
            'icon'  => 'bi-shield-lock',
            'desc'  => 'Securite, journaux et tentatives de connexion',
        ],
    ];

    /** Retourne la liste PLATE de tous les sous-modules (ceux stockes en BDD). */
    public static function allLeafModules(): array
    {
        $leaves = [];
        foreach (self::MODULES as $key => $m) {
            if (!empty($m['is_group'])) {
                foreach ($m['children'] as $ck => $cm) {
                    $leaves[$ck] = $cm;
                }
            } else {
                $leaves[$key] = $m;
            }
        }
        return $leaves;
    }

    /** Cache des modules de l'utilisateur courant (evite N requetes). */
    private static ?array $userModulesCache = null;

    /** Retourne les modules accordes a l'utilisateur connecte. */
    public static function userModules(): array
    {
        if (self::$userModulesCache !== null) { return self::$userModulesCache; }
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) { self::$userModulesCache = []; return []; }
        try {
            $db = Database::getInstance();
            $st = $db->prepare("SELECT module FROM user_modules WHERE iduser = ?");
            $st->execute([$uid]);
            $rows = $st->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($rows)) {
                self::$userModulesCache = $rows;
                return $rows;
            }
        } catch (Throwable $e) {
            // Fallback silencieux si la table n'existe pas encore
        }
        // Fallback : matrice de role (utilise les feuilles)
        $role = self::role();
        self::$userModulesCache = self::MATRIX[$role] ?? ['dashboard'];
        return self::$userModulesCache;
    }

    /** Reinitialise le cache (apres changement d'habilitations). */
    public static function clearCache(): void { self::$userModulesCache = null; }

    public static function roles(): array
    {
        return [
            'admin'           => 'Administrateur',
            'chef_inspecteur' => 'Chef inspecteur',
            'inspecteur'      => 'Inspecteur',
            'operateur'       => 'Operateur',
            'consultant'      => 'Consultant',
        ];
    }

    public static function role(): string { return $_SESSION['user']['role'] ?? ''; }

    public static function roleLabel(?string $role = null): string
    {
        $role = $role ?? self::role();
        return self::roles()[$role] ?? $role;
    }

    public static function canAccess(string $module): bool
    {
        return in_array($module, self::userModules(), true);
    }

    public static function guardPage(string $module): void
    {
        if (!Auth::checkLogin()) { header('Location: ' . SITE_URL . '/index'); exit; }
        if (!self::canAccess($module)) {
            Audit::log('access_denied', $module, 'Acces page refuse');
            header('Location: ' . SITE_URL . '/dashboard'); exit;
        }
    }

    public static function guardAuthPage(): void
    {
        if (!Auth::checkLogin()) { header('Location: ' . SITE_URL . '/index'); exit; }
    }

    public static function guardAuthApi(): void
    {
        header('Content-Type: application/json');
        if (!Auth::checkLogin()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expiree. Veuillez vous reconnecter.']);
            exit;
        }
    }

    public static function guardApi(string $module): void
    {
        header('Content-Type: application/json');
        if (!Auth::checkLogin()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expiree. Veuillez vous reconnecter.']);
            exit;
        }
        if (!self::canAccess($module)) {
            http_response_code(403);
            Audit::log('access_denied', $module, 'Acces API refuse');
            echo json_encode(['success' => false, 'message' => 'Acces refuse.']);
            exit;
        }
    }
}