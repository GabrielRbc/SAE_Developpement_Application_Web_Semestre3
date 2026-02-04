document.addEventListener('DOMContentLoaded', function() {

    const lienDeconnexion = document.getElementById('lienDeconnexion');

    if (lienDeconnexion) {
        // Ajouter un event listener pour le clic
        lienDeconnexion.addEventListener('click', function(event) {

            // Empeche l'action par défaut du lien (la navigation)
            event.preventDefault();

            // Affiche une boîte de confirmation
            const confirmation = confirm('Êtes-vous sûr(e) de vouloir vous déconnecter ?');

            // Si l'utilisateur confirme (clique sur OK)
            if (confirmation) {
                // Redirige manuellement vers l'URL du lien (deconnexion.php)
                window.location.href = lienDeconnexion.href;
            }
            // Si l'utilisateur annule, le code s'arrête ici et rien ne se passe.
        });
    }
});