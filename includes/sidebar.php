<!-- Sidebar AGAI -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="public/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC" class="logo">
        <span class="brand-text">AGAI</span>
    </div>
    
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-house-door"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="programme_supervision" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'programme_supervision.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-range"></i>
                    <span>Programme de surveillance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="audits" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'audits.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Audits et inspections</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="non_conformites" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'non_conformites.php' ? 'active' : ''; ?>">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Non-conformités</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="actions_correctives" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'actions_correctives.php' ? 'active' : ''; ?>">
                    <i class="bi bi-check2-square"></i>
                    <span>Actions correctives</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="documentation" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'documentation.php' ? 'active' : ''; ?>">
                    <i class="bi bi-folder"></i>
                    <span>Gestion documentaire</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="rapports" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'rapports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Rapports et statistiques</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="domaines_oaci" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'domaines_oaci.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield"></i>
                    <span>Domaines critiques OACI</span>
                </a>
            </li>
            <?php if ($_SESSION['user']['role'] === 'admin' || $_SESSION['user']['role'] === 'chef_inspecteur'): ?>
            <li class="nav-item">
                <a href="utilisateurs" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'utilisateurs.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Gestion des utilisateurs</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <li class="nav-item">
                <a href="parametres" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'parametres.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                $initial = strtoupper(substr($_SESSION['user']['prenom'] ?? 'U', 0, 1));
                echo $initial;
                ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($_SESSION['user']['role']); ?></span>
            </div>
        </div>
        <a href="includes/auth.php?action=logout" class="btn btn-logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</nav>