document.addEventListener('DOMContentLoaded', () => {

    // Récupération des éléments de la zone de dépôt principale
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('inputFichier');
    const uploadText = document.getElementById('uploadText');

    // Si les éléments n'existent pas sur la page, on arrête le script
    if (!uploadZone || !fileInput || !uploadText) return;

    // Feedback visuel à la sélection
    fileInput.addEventListener('change', () => {
        // Si un fichier a bien été choisi
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            // On ajoute une classe CSS pour changer le style de la zone
            uploadZone.classList.add('file-selected');
            uploadText.innerHTML = `
                <strong>Fichier sélectionné :</strong><br>
                ${file.name}<br>
                <small>(Cliquez pour changer)</small>
            `;
        }
    });
});

// Sert à mettre à jour le texte "Cliquez pour choisir..." quand on upload un document
function updateFileName(input, textId) {
    // On récupère la zone de texte associée à l'input via son ID
    const textElement = document.getElementById(textId);
    // Si un fichier est sélectionné, on affiche son nom en vert
    if (input.files.length > 0) {
        textElement.innerHTML = `<strong class="text-success">Sélectionné :</strong> ${input.files[0].name}`;
    }
}