// Fonction pour la confirmation de suppression
function confirmDelete() {
    return confirm("Êtes-vous sûr de vouloir supprimer définitivement l'association et toutes ses données ? Cette action est irréversible.");
}

// Fonctionnalité pour la confirmation visuelle de l'upload du logo
document.addEventListener('DOMContentLoaded', function() {
    const logoInput = document.getElementById('logo');
    const fileNameSpan = document.getElementById('logo-file-name');
    const uploadBox = document.getElementById('logo-upload-box');

    if (logoInput && fileNameSpan && uploadBox) {
        logoInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                // Un fichier a été sélectionné
                const fileName = this.files[0].name;
                fileNameSpan.textContent = fileName + ' (Sélectionné)';
                fileNameSpan.classList.remove('text-muted');
                uploadBox.classList.add('logo-uploaded');
            } else {
                // Aucun fichier ou sélection annulée
                fileNameSpan.textContent = 'Aucun fichier sélectionné.';
                fileNameSpan.classList.add('text-muted');
                uploadBox.classList.remove('logo-uploaded');
            }
        });
    }
});