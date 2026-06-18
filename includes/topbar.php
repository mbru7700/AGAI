<!-- Topbar -->
<header class="topbar">
    <div class="topbar-left">
        <button class="btn btn-toggle-sidebar" id="toggleSidebar">
            <i class="bi bi-list"></i>
        </button>
        <h4 class="page-title"><?php echo $page_title ?? 'Tableau de bord'; ?></h4>
    </div>
    
    <div class="topbar-right">
        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn btn-notification" data-bs-toggle="dropdown">
                <i class="bi bi-bell"></i>
                <span class="notification-badge">3</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                <h6 class="dropdown-header">Notifications</h6>
                <div class="notification-item">
                    <i class="bi bi-clipboard-check text-primary"></i>
                    <div>
                        <p>Audit programmé pour ABC Airlines</p>
                        <small>Il y a 2 heures</small>
                    </div>
                </div>
                <div class="notification-item">
                    <i class="bi bi-exclamation-triangle text-danger"></i>
                    <div>
                        <p>FNC critique ouverte - Réponse exigée</p>
                        <small>Il y a 5 heures</small>
                    </div>
                </div>
                <div class="notification-item">
                    <i class="bi bi-check-circle text-success"></i>
                    <div>
                        <p>FNC #45 clôturée avec succès</p>
                        <small>Il y a 1 jour</small>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="notifications" class="dropdown-item text-center">Voir toutes les notifications</a>
            </div>
        </div>
        
        <!-- Profil -->
        <div class="dropdown">
            <button class="btn btn-profile" data-bs-toggle="dropdown">
                <div class="profile-avatar">
                    <?php 
                    $initial = strtoupper(substr($_SESSION['user']['prenom'] ?? 'U', 0, 1));
                    echo $initial;
                    ?>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                <li>
                    <a class="dropdown-item" href="profil">
                        <i class="bi bi-person-circle me-2"></i> Mon profil
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="parametres">
                        <i class="bi bi-gear me-2"></i> Paramètres
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="securite">
                        <i class="bi bi-shield-lock me-2"></i> Sécurité
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="includes/auth.php?action=logout">
                        <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>