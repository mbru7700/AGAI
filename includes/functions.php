<?php
/**
 * Fonctions utilitaires AGAI
 */

use Config\Database;
use Config\Security;

/**
 * Récupérer la configuration
 */
function getConfig($key) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT valeur_param FROM parametres WHERE nom_param = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['valeur_param'] : null;
}

/**
 * Formater une date
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Formater une date pour l'affichage
 */
function displayDate($date) {
    return formatDate($date, 'd/m/Y');
}

/**
 * Formater une date avec heure
 */
function displayDateTime($date) {
    return formatDate($date, 'd/m/Y H:i');
}

/**
 * Calculer l'âge d'un enregistrement
 */
function timeAgo($date) {
    $timestamp = strtotime($date);
    $difference = time() - $timestamp;
    
    if ($difference < 60) return 'À l\'instant';
    if ($difference < 3600) return floor($difference / 60) . ' min';
    if ($difference < 86400) return floor($difference / 3600) . 'h';
    if ($difference < 604800) return floor($difference / 86400) . 'j';
    return date('d/m/Y', $timestamp);
}

/**
 * Générer un numéro unique pour les FNC
 * Format: 001/FOOL/AGA/2026
 */
function generateFNCNumber($domain, $location, $year) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT COUNT(*) + 1 as next FROM fiche_non_conformite WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $next = $stmt->fetch()['next'] ?? 1;
    
    return sprintf("%03d/%s/%s/%s", $next, $location, $domain, $year);
}

/**
 * Générer un numéro unique pour les audits
 */
function generateAuditNumber($year) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT COUNT(*) + 1 as next FROM audit WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $next = $stmt->fetch()['next'] ?? 1;
    
    return sprintf("AUD-%s-%04d", $year, $next);
}

/**
 * Obtenir le statut d'une FNC en texte
 */
function getFNCStatusText($status) {
    $statuses = [
        1 => 'Accepté non vérifié',
        2 => 'Rejeté',
        3 => 'Fermé',
        4 => 'Ouvert'
    ];
    return $statuses[$status] ?? 'Inconnu';
}

/**
 * Obtenir la classe CSS pour un statut
 */
function getFNCStatusClass($status) {
    $classes = [
        1 => 'warning',
        2 => 'danger',
        3 => 'success',
        4 => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

/**
 * Obtenir le statut d'un audit en texte
 */
function getAuditStatusText($status) {
    $statuses = [
        1 => 'Planifié',
        2 => 'Reporté',
        3 => 'Effectué',
        4 => 'Suspendu',
        5 => 'À surveiller'
    ];
    return $statuses[$status] ?? 'Inconnu';
}

/**
 * Obtenir la classe CSS pour un statut d'audit
 */
function getAuditStatusClass($status) {
    $classes = [
        1 => 'warning',
        2 => 'danger',
        3 => 'success',
        4 => 'secondary',
        5 => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

/**
 * Vérifier les permissions
 */
function hasPermission($permission) {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return false;
    }
    
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT COUNT(*) as has 
        FROM users u
        JOIN role_permissions rp ON u.role = rp.idrole
        JOIN permissions p ON rp.idpermission = p.idpermission
        WHERE u.iduser = ? AND p.nom_permission = ?
    ");
    $stmt->execute([$_SESSION['user']['id'], $permission]);
    $result = $stmt->fetch();
    
    return ($result['has'] ?? 0) > 0;
}

/**
 * Vérifier si l'utilisateur est administrateur
 */
function isAdmin() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

/**
 * Vérifier si l'utilisateur est chef inspecteur
 */
function isChefInspecteur() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'chef_inspecteur';
}

/**
 * Vérifier si l'utilisateur est inspecteur
 */
function isInspecteur() {
    return isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['chef_inspecteur', 'inspecteur']);
}

/**
 * Récupérer les domaines d'un inspecteur
 */
function getInspecteurDomaines($inspecteurId) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT d.* 
        FROM domaine d
        JOIN habilitation h ON h.iddomaine = d.iddomaine
        WHERE h.idinspecteur = ? AND h.date_expiration >= CURDATE()
    ");
    $stmt->execute([$inspecteurId]);
    return $stmt->fetchAll();
}

/**
 * Récupérer les règlements d'un domaine
 */
function getReglementByDomaine($domaineId) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT * FROM reglement WHERE iddomaine = ?
    ");
    $stmt->execute([$domaineId]);
    return $stmt->fetchAll();
}

/**
 * Vérifier si une habilitation est valide
 */
function isHabilitationValide($inspecteurId, $domaineId) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT COUNT(*) as valide 
        FROM habilitation 
        WHERE idinspecteur = ? AND iddomaine = ? AND date_expiration >= CURDATE()
    ");
    $stmt->execute([$inspecteurId, $domaineId]);
    $result = $stmt->fetch();
    return ($result['valide'] ?? 0) > 0;
}

/**
 * Générer un slug
 */
function generateSlug($text) {
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    $text = trim($text);
    $text = strtolower($text);
    $text = str_replace(' ', '-', $text);
    return $text;
}

/**
 * Tronquer un texte
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Échapper les données pour JSON
 */
function jsonSafe($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Valider un numéro de téléphone
 */
function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]{8,20}$/', $phone);
}

/**
 * Valider un matricule ANAC (4 chiffres)
 */
function validateMatricule($matricule) {
    return preg_match('/^[0-9]{4}$/', $matricule);
}