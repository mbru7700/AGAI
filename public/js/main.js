/**
 * AGAI - Scripts principaux
 * Gestion de l'interface, sécurité, AJAX
 */

$(document).ready(function() {
    // Toggle sidebar
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('show');
    });
    
    // Toggle password
    $('#togglePassword').on('click', function() {
        const input = $('#password');
        const icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye-fill').addClass('bi-eye-slash-fill');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash-fill').addClass('bi-eye-fill');
        }
    });
    
    // Gestion du formulaire de connexion
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        const button = $('#loginButton');
        const originalText = button.html();
        
        button.prop('disabled', true);
        button.html('<span class="spinner-border spinner-border-sm me-2"></span>Connexion...');
        
        $.ajax({
            url: 'includes/auth.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.requires_2fa) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Connexion réussie',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false,
                            willClose: function() {
                                window.location.href = response.redirect;
                            }
                        });
                    } else {
                        window.location.href = response.redirect;
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur de connexion',
                        text: response.message,
                        confirmButtonColor: '#23408F'
                    });
                    button.prop('disabled', false);
                    button.html(originalText);
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue. Veuillez réessayer.',
                    confirmButtonColor: '#23408F'
                });
                button.prop('disabled', false);
                button.html(originalText);
            }
        });
    });
    
    // Gestion du formulaire 2FA
    $('#verifyForm').on('submit', function(e) {
        e.preventDefault();
        
        const button = $('#verifyButton');
        const originalText = button.html();
        
        button.prop('disabled', true);
        button.html('<span class="spinner-border spinner-border-sm me-2"></span>Vérification...');
        
        $.ajax({
            url: 'includes/auth.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Vérification réussie',
                        text: 'Redirection en cours...',
                        timer: 1500,
                        showConfirmButton: false,
                        willClose: function() {
                            window.location.href = response.redirect;
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Code invalide',
                        text: response.message || 'Le code est incorrect ou a expiré.',
                        confirmButtonColor: '#23408F'
                    });
                    button.prop('disabled', false);
                    button.html(originalText);
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue.',
                    confirmButtonColor: '#23408F'
                });
                button.prop('disabled', false);
                button.html(originalText);
            }
        });
    });
    
    // Gestion des notifications
    $('.notification-item').on('click', function() {
        // Marquer comme lu et rediriger
        $(this).addClass('read');
    });
    
    // Tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Popovers
    $('[data-bs-toggle="popover"]').popover();
});

// Fonction générique pour les requêtes AJAX sécurisées
function ajaxSecure(url, data, method = 'POST') {
    return new Promise(function(resolve, reject) {
        data.csrf_token = $('input[name="csrf_token"]').val() || '';
        
        $.ajax({
            url: url,
            method: method,
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    resolve(response);
                } else {
                    reject(response.message || 'Erreur');
                }
            },
            error: function(xhr) {
                reject(xhr.responseJSON?.message || 'Erreur serveur');
            }
        });
    });
}

// Fonction de confirmation avec SweetAlert2
function confirmAction(title, text, icon = 'warning') {
    return new Promise(function(resolve) {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: '#23408F',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler'
        }).then(function(result) {
            resolve(result.isConfirmed);
        });
    });
}

// Fonction de notification
function showNotification(type, title, message) {
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    Swal.fire({
        icon: type,
        title: title,
        text: message,
        confirmButtonColor: '#23408F'
    });
}

// Gestion du débordement de la sidebar sur mobile
$(window).on('resize', function() {
    if ($(window).width() > 992) {
        $('#sidebar').removeClass('show');
    }
});

// Fermer la sidebar en cliquant à l'extérieur
$(document).on('click', function(e) {
    if ($(window).width() <= 992) {
        const sidebar = $('#sidebar');
        const toggleBtn = $('#toggleSidebar');
        
        if (!sidebar.is(e.target) && 
            !sidebar.has(e.target).length && 
            !toggleBtn.is(e.target) && 
            !toggleBtn.has(e.target).length) {
            sidebar.removeClass('show');
        }
    }
});