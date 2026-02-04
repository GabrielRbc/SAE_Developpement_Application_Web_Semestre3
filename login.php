<?php
session_start();

if (isset($_SESSION['nom'])) {
    header('location: pages/tableauDeBord.php');
    exit();
}

$connexionError = false;
$connexionBDD = null;

try {
    require "fonctions/fonctionsConnexion.php";
    $connexionBDD = connexionBDDUser();
} catch (Exception $e) {
    $connexionError = true;
}

$isIncorrect = false;

if (!$connexionError && isset($_POST['login'], $_POST['mdp'])) {
    if (verifUtilisateur($connexionBDD, $_POST['login'], $_POST['mdp'])) {
        header('Location: pages/tableauDeBord.php');
        exit();
    } else {
        $isIncorrect = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Adhesium | Connexion</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="css/styleGlobal.css" rel="stylesheet">
</head>
<body class="bg-light d-flex flex-column vh-100">

<div class="container flex-grow-1 d-flex justify-content-center align-items-center">
    <div class="col-12 col-md-8 col-lg-5">

        <div class="text-center mb-4">
            <a href="index.php" class="text-decoration-none text-muted small">
                <i class="fa-solid fa-arrow-left me-1"></i> Retour à l'accueil
            </a>
        </div>

        <?php if ($connexionError): ?>
            <div class="card shadow border-0">
                <div class="card-body p-5 text-center">
                    <i class="fa-solid fa-database fa-4x text-danger mb-4"></i>
                    <h2 class="fw-bold h4">Maintenance en cours</h2>
                    <p class="text-muted">La connexion à la base de données est impossible pour le moment.</p>
                    <a href="login.php" class="btn btn-primary w-100">Réessayer</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-circle-user fa-3x text-primary mb-3"></i>
                        <h1 class="h3 fw-bold">Espace de connexion</h1>
                    </div>

                    <?php if ($isIncorrect): ?>
                        <div class="alert alert-danger text-center py-2 mb-4">
                            Identifiant ou mot de passe incorrect
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="post">
                        <div class="mb-3">
                            <label class="form-label">Identifiant</label>
                            <input type="text" name="login" class="form-control form-control-lg"
                                   value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="mdp" class="form-control form-control-lg" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm">Se connecter</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>