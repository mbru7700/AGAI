<?php
/**
 * Classe Rbac - Contrôle d'accès par rôle (RBAC)
 * ------------------------------------------------------------
 * Source de vérité : la colonne enum `users.role`.
 * Matrice rôle -> modules accessibles, réutilisable par toutes
 * les pages et tous les endpoints AJAX.
 *
 * @package AGAI
 * @author  ANAC Gabon
 */

class Rbac
{
    /** Modules accessibles par rôle (visibilité menu + accès page/API). */
    private const MATRIX = [
        'admin'           => ['dashboard','programme','audits','nonconformites','actions','documents','rapports','domaines','users','parametres'],
        'chef_inspecteur' => ['dashboard','programme','audits','nonconformites','actions','documents','rapports','domaines'],
        'inspecteur'      => ['dashboard','programme','audits','nonconformites','actions','documents','rapports','domaines'],
        'operateur'       => ['dashboard','audits','nonconformites','actions','documents','rapports'],
        'consultant'      => ['dashboard','rapports','domaines'],
    ];

    /** Libellés affichables des rôles. */
    public static function roles(): array
    {
        return [
            'admin'           => 'Administrateur',
            'chef_inspecteur' => 'Chef inspecteur',
            'inspecteur'      => 'Inspecteur',
            'operateur'       => 'Opérateur',
            'consultant'      => 'Consultant',
        ];
    }

    public static function role(): string
    {
        return $_SESSION['user']['role'] ?? '';
    }

    public static function roleLabel(?string $role = null): string
    {
        $role = $role ?? self::role();
        return self::roles()[$role] ?? $role;
    }

    public static function canAccess(string $module): bool
    {
        $role = self::role();
        return isset(self::MATRIX[$role]) && in_array($module, self::MATRIX[$role], true);
    }

    /** Garde de PAGE : redirige si non connecté / non autorisé. */
    public static function guardPage(string $module): void
    {
        if (!Auth::checkLogin()) {
            header('Location: ' . SITE_URL . '/index');
            exit;
        }
        if (!self::canAccess($module)) {
            Audit::log('access_denied', $module, 'Accès page refusé');
            header('Location: ' . SITE_URL . '/dashboard');
            exit;
        }
    }

    /**
     * Garde de PAGE pour les pages ouvertes a TOUT utilisateur connecte
     * (profil, parametres personnels...). Aucune verification de module,
     * juste une session valide.
     */
    public static function guardAuthPage(): void
    {
        if (!Auth::checkLogin()) {
            header('Location: ' . SITE_URL . '/index');
            exit;
        }
    }

    /**
     * Garde d'API pour les endpoints ouverts a tout utilisateur connecte.
     * Renvoie un JSON 401 si la session n'est pas valide.
     */
    public static function guardAuthApi(): void
    {
        header('Content-Type: application/json');
        if (!Auth::checkLogin()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }

    /** Garde d'API : renvoie un JSON 401/403 si non connecté / non autorisé. */
    public static function guardApi(string $module): void
    {
        header('Content-Type: application/json');
        if (!Auth::checkLogin()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
        if (!self::canAccess($module)) {
            http_response_code(403);
            Audit::log('access_denied', $module, 'Accès API refusé');
            echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
            exit;
        }
    }
}