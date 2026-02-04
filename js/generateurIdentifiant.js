function genererIdentifiant(longueur = 8) {
    const caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let identifiant = '';
    for (let i = 0; i < longueur; i++) {
        identifiant += caracteres.charAt(Math.floor(Math.random() * caracteres.length));
    }
    return identifiant;
}
document.addEventListener('DOMContentLoaded', function() {
    const bouton = document.getElementById('generateId');
    const inputIdentifiant = document.getElementById('identifiant');

    if (bouton && inputIdentifiant) {
        bouton.addEventListener('click', function() {
            inputIdentifiant.value = genererIdentifiant();
        });
    }
});