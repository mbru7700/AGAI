<?php
/**
 * Classe Rbac - Controle d'acces par role et habilitations
 * ----------------------------------------------------------
 * Source de verite : table `user_modules` (habilitations individuelles).
 *   -> Cocher une case dans la modale = accorder l'acces.
 *   -> Decocher = retirer l'acces.
 * La MATRIX n'est plus un plafond : elle sert UNIQUEMENT de defaut
 * lorsque l'utilisateur n'a encore aucune habilitation en base.
 *
 * Securite (OWASP A01 - Broken Access Control) :
 *   - canAccess() pilote a la fois le menu, guardPage() et guardApi(),
 *     donc l'affichage et l'acces reel restent toujours coherents.
 *   - Les slugs lus en BDD sont valides contre la liste reelle des modules
 *     (allLeafModules) : tout slug inconnu/obsolete/injecte est ignore.
 *   - L'administrateur conserve un acces total garanti.
 */
class Rbac
{
    /** Matrice de DEFAUT role -> modules feuilles (utilisee si user_modules vide). */
    private const MATRIX = [
        'admin'           => ['dashboard','audits','revue_doc','notifications','rapports','qre','ouverture_nc','suivi_nc','alertes_fnc','inspecteurs','programme_psc','exploitants','domaines','sousdomaines','typesorganisme','reglements','sites','analyse_psc','archivage','analyse_fnc','profil_risque','mise_oeuvre','bilan_supervision','users','parametres','cybersecurite'],
        'chef_inspecteur' => ['dashboard','audits','revue_doc','notifications','rapports','qre','ouverture_nc','suivi_nc','alertes_fnc','inspecteurs','programme_psc','exploitants','domaines','sousdomaines','typesorganisme','reglements','sites','analyse_psc','archivage','analyse_fnc','profil_risque','mise_oeuvre','bilan_supervision','users','cybersecurite'],
        'inspecteur'      => ['dashboard','audits','revue_doc','notifications','rapports','qre','ouverture_nc','suivi_nc','alertes_fnc','inspecteurs','programme_psc','domaines','sousdomaines','reglements','archivage'],
        'operateur'       => ['dashboard','qre','notifications','rapports','audits'],
        'consultant'      => ['dashboard','audits','revue_doc','notifications','rapports','qre','ouverture_nc','suivi_nc','alertes_fnc','domaines','reglements'],
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
        'structures_group' => [
            'label'    => 'Donnees de structures',
            'icon'     => 'bi-diagram-3',
            'desc'     => 'Referentiels et donnees de base',
            'is_group' => true,
            'children' => [
                'exploitants'    => ['label'=>'Exploitants',         'icon'=>'bi-buildings',     'desc'=>'Operateurs et compagnies aeriennes'],
                'domaines'       => ['label'=>'Domaines',            'icon'=>'bi-grid-3x3-gap',  'desc'=>'Domaines de surveillance'],
                'sousdomaines'   => ['label'=>'Sous-domaines',       'icon'=>'bi-diagram-2',     'desc'=>'Sous-domaines par domaine'],
                'typesorganisme' => ['label'=>'Types d\'activite',   'icon'=>'bi-tags',          'desc'=>'Categories d\'operateurs'],
                'reglements'     => ['label'=>'Reglements',          'icon'=>'bi-journal-text',  'desc'=>'Textes reglementaires applicables'],
                'sites'          => ['label'=>'Sites d\'inspection', 'icon'=>'bi-geo-alt',       'desc'=>'Sites identifies par indicateur OACI'],
            ],
        ],
        'audits_group' => [
            'label'    => 'Gestion Supervision',
            'icon'     => 'bi-clipboard-check',
            'desc'     => 'Ensemble des fonctionnalites liees aux audits et supervision',
            'is_group' => true,
            'children' => [
                'inspecteurs'   => ['label'=>'Inspecteurs',           'icon'=>'bi-person-badge',      'desc'=>'Gestion des inspecteurs et habilitations'],
                'programme_psc' => ['label'=>'Programme PSC',         'icon'=>'bi-calendar3-week',    'desc'=>'Programme de surveillance continue (matrice)'],
                'audits'        => ['label'=>'Audits et inspections', 'icon'=>'bi-clipboard-check',   'desc'=>'Declenchement, suivi et cloture des audits'],
                'revue_doc'     => ['label'=>'Revue documentaire',    'icon'=>'bi-file-text',          'desc'=>'Formulaire IX-GEN-R3-F-I-017'],
                'notifications' => ['label'=>'Notifications',         'icon'=>'bi-bell',               'desc'=>'Lettres de notification aux operateurs'],
                'rapports'      => ['label'=>'Rapports',              'icon'=>'bi-file-earmark-text',  'desc'=>'Rapports d\'actes de supervision IX-GEN-R3-FI-009'],
                'qre'           => ['label'=>'Formulaire QRE',        'icon'=>'bi-ui-checks-grid',     'desc'=>'Questionnaire de Retour d\'Experience IX-GEN-R3-FI-011'],
            ],
        ],
        'nc_group' => [
            'label'    => 'Non-conformites',
            'icon'     => 'bi-exclamation-triangle',
            'desc'     => 'Ouverture et suivi des fiches de non-conformite',
            'is_group' => true,
            'children' => [
                'ouverture_nc' => ['label'=>'Fiches de non-conformite', 'icon'=>'bi-clipboard-check', 'desc'=>'Ouverture, suivi et cloture des FNC'],
                'alertes_fnc'  => ['label'=>'Alertes FNC',               'icon'=>'bi-bell',           'desc'=>'Echeances de reponse et de mise en conformite'],
                'suivi_nc'     => ['label'=>'Suivi NC',     'icon'=>'bi-clipboard-check', 'desc'=>'Tableau de suivi des non-conformites'],
            ],
        ],
        'analyse_group' => [
            'label'    => 'Analyse des donnees',
            'icon'     => 'bi-graph-up',
            'desc'     => 'Tableaux de bord analytiques',
            'is_group' => true,
            'children' => [
                'analyse_psc'  => ['label'=>'Analyse PSC',           'icon'=>'bi-bar-chart',    'desc'=>'Programme de surveillance continue'],
                'archivage'    => ['label'=>'Archivage',             'icon'=>'bi-archive',      'desc'=>'Inventaire documentaire des actes de supervision'],
                'analyse_fnc'  => ['label'=>'Analyse FNC',           'icon'=>'bi-pie-chart',    'desc'=>'Statistiques non-conformites'],
                'profil_risque'=> ['label'=>'Profil de risque',      'icon'=>'bi-shield-exclamation','desc'=>'Profil de risque des operateurs (surveillance basee sur les risques)'],
                'mise_oeuvre'  => ['label'=>'Taux de conformite reglementaire',  'icon'=>'bi-shield-check', 'desc'=>'Suivi mise en oeuvre reglementaire'],
                'bilan_supervision' => ['label'=>'Bilan supervision', 'icon'=>'bi-clipboard-data', 'desc'=>'Bilan global du programme de supervision'],
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

    /** Cache des modules de l'utilisateur courant (evite N requetes par page). */
    private static ?array $userModulesCache = null;

    /**
     * Retourne les modules accordes a l'utilisateur connecte.
     *
     * HABILITATIONS DYNAMIQUES :
     *   - Source de verite = table `user_modules` (cases cochees dans la modale).
     *     On valide chaque slug contre les modules REELS de l'application
     *     (allLeafModules) pour ignorer tout slug obsolete ou injecte.
     *   - `dashboard` est toujours accorde.
     *   - L'administrateur a toujours un acces total.
     *   - Si AUCUNE habilitation n'existe en BDD pour cet utilisateur,
     *     on applique le defaut du role (MATRIX) le temps qu'il soit habilite.
     */
    public static function userModules(): array
    {
        if (self::$userModulesCache !== null) { return self::$userModulesCache; }

        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) { return self::$userModulesCache = []; }

        $role       = self::role();
        $fromMatrix = self::MATRIX[$role] ?? ['dashboard'];

        // Administrateur : acces total garanti, independant de user_modules.
        if ($role === 'admin') {
            return self::$userModulesCache = self::MATRIX['admin'];
        }

        // Operateur : socle metier garanti (dashboard + ses modules de consultation).
        // Un operateur n'est pas gere via la modale d'habilitations par module
        // (il ne voit que SES propres donnees, filtrees par idorga cote endpoints).
        // On lui garantit donc toujours son menu complet, pour eviter un tableau
        // de bord et un menu vides si user_modules ne contient qu'une entree.
        if ($role === 'operateur') {
            return self::$userModulesCache = self::MATRIX['operateur'];
        }

        try {
            $db = Database::getInstance();
            $st = $db->prepare("SELECT module FROM user_modules WHERE iduser = ?");
            $st->execute([$uid]);
            $rows = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if (!empty($rows)) {
                // Ne garder que des modules reels de l'app (anti slugs obsoletes/injectes).
                $valid   = array_keys(self::allLeafModules());
                $allowed = array_values(array_intersect($rows, $valid));

                // Le tableau de bord est toujours accorde.
                if (!in_array('dashboard', $allowed, true)) { $allowed[] = 'dashboard'; }

                // Si rien d'utile (uniquement dashboard) => defaut du role.
                if (count($allowed) <= 1) {
                    return self::$userModulesCache = $fromMatrix;
                }
                return self::$userModulesCache = $allowed;
            }
        } catch (Throwable $e) {
            // Fallback silencieux en cas d'erreur BDD : on n'ouvre jamais plus que le defaut role.
            error_log('Rbac::userModules - ' . $e->getMessage());
        }

        // Aucune habilitation enregistree => defaut selon le role.
        return self::$userModulesCache = $fromMatrix;
    }

    /** Reinitialise le cache (a appeler apres modification des habilitations). */
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

    /** Vrai si l'utilisateur courant a acces au module donne. */
    public static function canAccess(string $module): bool
    {
        return in_array($module, self::userModules(), true);
    }

    /** Garde de page : exige une session valide ET l'acces au module. */
    public static function guardPage(string $module): void
    {
        if (!Auth::checkLogin()) { header('Location: ' . SITE_URL . '/index'); exit; }
        if (!self::canAccess($module)) {
            Audit::log('access_denied', $module, 'Acces page refuse');
            header('Location: ' . SITE_URL . '/dashboard'); exit;
        }
    }

    /** Garde de page : exige seulement une session valide (pas de module precis). */
    public static function guardAuthPage(): void
    {
        if (!Auth::checkLogin()) { header('Location: ' . SITE_URL . '/index'); exit; }
    }

    /** Garde API : exige seulement une session valide (reponse JSON). */
    public static function guardAuthApi(): void
    {
        header('Content-Type: application/json');
        if (!Auth::checkLogin()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expiree. Veuillez vous reconnecter.']);
            exit;
        }
    }

    /** Garde API : exige une session valide ET l'acces au module (reponse JSON). */
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