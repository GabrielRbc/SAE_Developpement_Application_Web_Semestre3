<?php
session_start();
// Si déjà connecté, on redirige vers le tableau de bord
if (isset($_SESSION['nom'])) {
    header('Location: pages/tableauDeBord.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adhesium | Gestion d'Association</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="css/styleGlobal.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" >
            <i class="fa-solid fa-users-gear me-2"></i>ADHESIUM
        </a>
        <a href="login.php" class="btn btn-primary px-4 shadow-sm">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Espace de connexion
        </a>
    </div>
</nav>

<header class="bg-light py-5 border-bottom">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold text-dark mb-3">Gérez votre association en toute simplicité.</h1>
                <p class="lead text-muted mb-4">Adhesium est la plateforme tout-en-un pour centraliser vos adhérents, valider les documents et piloter votre bureau au quotidien.</p>
                <div class="d-grid gap-3 d-md-flex">
                    <a href="login.php" class="btn btn-primary btn-lg px-5">Commencer maintenant</a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block text-center">
                <i class="fa-solid fa-laptop-code fa-10x text-primary opacity-25"></i>
            </div>
        </div>
    </div>
</header>

<section class="container py-5">
    <div class="row g-4 text-center">
        <div class="col-md-4">
            <div class="p-4 bg-white shadow-sm rounded">
                <i class="fa-solid fa-user-check fa-3x text-success mb-3"></i>
                <h2 class="h5 fw-bold">Gestion Simplifiée</h2>
                <p class="text-muted">Suivez l'état des fiches de vos adhérents en temps réel.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 bg-white shadow-sm rounded">
                <i class="fa-solid fa-file-shield fa-3x text-warning mb-3"></i>
                <h2 class="h5 fw-bold">Documents Sécurisés</h2>
                <p class="text-muted">Validez les certificats et pièces justificatives en un clic.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 bg-white shadow-sm rounded">
                <i class="fa-solid fa-lock fa-3x text-primary mb-3"></i>
                <h2 class="h5 fw-bold">Accès Contrôlé</h2>
                <p class="text-muted">Des rôles précis (Membre, Responsable) pour une sécurité maximale.</p>
            </div>
        </div>
    </div>
</section>
<div class="mt-4">
    <footer class="bg-dark text-white text-center py-3">
        <p>
            <a href="pages/mentions_legales.php" class="text-white">Mentions Légales</a> -
            <a href="pages/confidentialite.php" class="text-white">Politique de confidentialité</a> -
            <a href="pages/contact.php" class="text-white">Nous contacter</a>
        </p>
        <p>© Adhésium - 2026</p>
    </footer>
</div>
</body>
</html>