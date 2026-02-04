<?php
session_start();


require "../../fonctions/fonctionsConnexion.php";
require "../../fonctions/fonctionsGestionAdherents.php";

$pdo = null;

require "../../fonctions/verifDroit.php";
try {
    $pdo = connexionBDDUser(); // On initialise la connexion

    // On passe la connexion active à la fonction de vérification
    verifierAccesDynamique($pdo);

} catch (Exception $e) {
    // Si la base de données est inaccessible, on déconnecte par sécurité
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

/* Vérification de session */
if (!isset($_SESSION['nom']) || !isset($_SESSION['prenom']) || !isset($_SESSION['id_utilisateur'])) {
    header('Location: ../../index.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] == 'adherent') {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['idSession'] != session_id()) {
    header("Location: ../../index.php");
    exit();
}


/* Si formulaire soumis */
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Récupération des champs avec trim()
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $identifiant = trim($_POST['identifiant']);
    $motdepasse = $_POST['motdepasse'];
    $id_utilisateur = $_SESSION['id_utilisateur'];

    $erreurs = [];

    // ----- Validation obligatoire -----
    if (empty($nom)) $erreurs[] = "Le nom est obligatoire.";
    if (empty($prenom)) $erreurs[] = "Le prénom est obligatoire.";
    if (empty($identifiant)) $erreurs[] = "L'identifiant est obligatoire.";
    if (empty($motdepasse)) $erreurs[] = "Le mot de passe est obligatoire.";
    if (empty($email)) $erreurs[] = "L'adresse email est obligatoire.";

    // ----- Validation des longueurs -----
    if (strlen($nom) > 50) $erreurs[] = "Le nom ne doit pas dépasser 50 caractères.";
    if (strlen($prenom) > 50) $erreurs[] = "Le prénom ne doit pas dépasser 50 caractères.";
    if (strlen($email) > 100) $erreurs[] = "L'email ne doit pas dépasser 100 caractères.";
    if (strlen($identifiant) > 20) $erreurs[] = "L'identifiant ne doit pas dépasser 20 caractères.";
    if (!empty($telephone) && strlen($telephone) > 10) $erreurs[] = "Le téléphone ne doit pas dépasser 10 chiffres.";

    // ----- Validation des caractères -----
    if (!preg_match('/^[a-zA-ZÀ-ÿ \-]+$/u', $nom)) $erreurs[] = "Le nom contient des caractères invalides.";
    if (!preg_match('/^[a-zA-ZÀ-ÿ \-]+$/u', $prenom)) $erreurs[] = "Le prénom contient des caractères invalides.";
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $identifiant)) $erreurs[] = "L'identifiant contient des caractères invalides.";

    // ----- Validation email -----
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse email n'est pas valide.";
    }

    // ----- Validation téléphone -----
    if (!empty($telephone) && !preg_match('/^\d{10}$/', $telephone)) {
        $erreurs[] = "Le numéro de téléphone doit contenir exactement 10 chiffres.";
    }

    // ----- Si aucune erreur, on met les champs vides à null -----
    $telephone   = $telephone !== '' ? $telephone : null;

    // ----- Si aucune erreur, on ajoute l'adhérent -----
    if (empty($erreurs)) {
        $retour = ajouterAdherent(
                $pdo,
                $nom,
                $prenom,
                $email,
                $telephone,
                $identifiant,
                $motdepasse,
                $id_utilisateur
        );

        if ($retour === true) {
            header("Location: gestion_adherents.php?success=1");
            exit();
        } else {
            $erreurs[] = $retour;
        }
    }
}

require '../../fonctions/fonctionsGestionCouleur.php';

$couleurs = getCouleursAssociation($pdo, $_SESSION['id_utilisateur']);
$couleur_principale_1 = $couleurs['couleur_principale_1'];
$couleur_principale_2 = $couleurs['couleur_principale_2'];
$couleur_principale_3 = $couleurs['couleur_principale_3'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvel Adhérent</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="../../css/styleGlobal.css" rel="stylesheet">
</head>

<body>

<nav class="navbar shadow-sm py-3 px-4"
     style="background: linear-gradient(to right,
     <?= $couleur_principale_1 ?>,
     <?= $couleur_principale_2 ?>,
     <?= $couleur_principale_3 ?>);">
    <div class="container-fluid d-flex align-items-center">
        <div class="me-3">
            <a href="gestion_adherents.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                <i class="fa-solid fa-arrow-left menu-item"></i>
            </a>
        </div>

        <div class="flex-grow-1">
            <h5 class="m-0 fw-bold">Nouvel Adhérent</h5>
        </div>
    </div>
</nav>
<div class="container my-5">
    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach($erreurs as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="card-body">
        <form method="post" class="row g-4" id="formNouvelAdherent">

            <div class="card shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3">Informations Personnelles</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="nom" class="form-control"
                               placeholder="Ex : Dupont" maxlength="50" required
                                <?php if (isset($_POST['nom'])) echo 'value="' . htmlspecialchars($_POST['nom']) . '"'; ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input type="text" name="prenom" class="form-control"
                               placeholder="Ex : Jean" maxlength="50" required
                                <?php if (isset($_POST['prenom'])) echo 'value="' . htmlspecialchars($_POST['prenom']) . '"'; ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control"
                               placeholder="Ex : monemail@domaine.com" maxlength="100" required
                                <?php if (isset($_POST['email'])) echo 'value="' . htmlspecialchars($_POST['email']) . '"'; ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" name="telephone" class="form-control"
                               placeholder="Ex : 0601020304" maxlength="10" inputmode="numeric"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                <?php if (isset($_POST['telephone'])) echo 'value="' . htmlspecialchars($_POST['telephone']) . '"'; ?>>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3">Identifiant & Mot de Passe</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Identifiant <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="identifiant" id="identifiant" class="form-control"
                                   placeholder="Choisissez un identifiant" maxlength="20" required
                                    <?php if (isset($_POST['identifiant'])) echo 'value="' . htmlspecialchars($_POST['identifiant']) . '"'; ?>>
                            <button type="button" class="btn btn-outline-secondary" id="generateId" title="Générer un identifiant">
                                <i class="fa-solid fa-arrow-rotate-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="form-label">Mot de passe <span class="text-danger">*</span></label>
                        <input type="password" name="motdepasse" class="form-control" id="motdepasse" placeholder="Votre mot de passe" required>
                        <input type="checkbox" class="form-check-input" id="togglePassword">
                        <label class="form-check-label" for="togglePassword">
                            Afficher les mots de passe
                        </label>
                    </div>

                    <ul class="small" id="passwordRules" style="display:none;">
                        <li id="rule-length" class="text-danger">❌ Au moins 8 caractères</li>
                        <li id="rule-upper" class="text-danger">❌ Une lettre majuscule</li>
                        <li id="rule-lower" class="text-danger">❌ Une lettre minuscule</li>
                        <li id="rule-number" class="text-danger">❌ Un chiffre</li>
                        <li id="rule-special" class="text-danger">❌ Un caractère spécial</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary d-flex align-items-center gap-2"
                        form="formNouvelAdherent">
                    <i class="fa-solid fa-check"></i> Créer l’adhérent
                </button>
            </div>
        </form>
    </div>
</div>

<div class="mt-4">
    <footer class="bg-dark text-white text-center py-3">
        <p>
            <a href="../mentions_legales.php" class="text-white">Mentions Légales</a> -
            <a href="../confidentialite.php" class="text-white">Politique de confidentialité</a> -
            <a href="../contact.php" class="text-white">Nous contacter</a>
        </p>
        <p>© Adhésium - 2026</p>
    </footer>
</div>

<script src="../../js/generateurIdentifiant.js"></script>
<script src="../../js/password.js"></script>
</body>
</html>