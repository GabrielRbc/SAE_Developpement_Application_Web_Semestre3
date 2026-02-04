<?php
session_start();
require "../fonctions/fonctionsConnexion.php";
require "../fonctions/fonctionsParametreAssociation.php";
require '../fonctions/verifDroit.php';

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

// Vérification du rôle responsable
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'responsable') {
    header("Location: ../index.php");
    exit();
}

$pdo = connexionBDDUser();

// Gestion de la suppression de l'association
if (isset($_POST['action']) && $_POST['action'] === 'supprimer_asso') {
    supprimerAssociation($pdo);
    header("Location: ../pages/cartes/parametre_association.php?status=deleted");
    exit();
}

// Nettoyage des champs
$nom_association = htmlspecialchars($_POST['nom_association'] ?? '');
$adresse = htmlspecialchars($_POST['adresse'] ?? '');
$code_postal = htmlspecialchars($_POST['code_postal'] ?? '');
$ville = htmlspecialchars($_POST['ville'] ?? '');
$telephone = htmlspecialchars($_POST['telephone'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$site_web = htmlspecialchars($_POST['site_web'] ?? '');
$couleurPrincipale_1 = htmlspecialchars($_POST['couleur_principale_1'] ?? '');
$couleurPrincipale_2 = htmlspecialchars($_POST['couleur_principale_2'] ?? '');
$couleurPrincipale_3 = htmlspecialchars($_POST['couleur_principale_3'] ?? '');
$couleurSecondaire_1 = htmlspecialchars($_POST['couleur_secondaire_1'] ?? '');
$couleurSecondaire_2 = htmlspecialchars($_POST['couleur_secondaire_2'] ?? '');
$couleurSecondaire_3 = htmlspecialchars($_POST['couleur_secondaire_3'] ?? '');
$informations = htmlspecialchars($_POST['informations'] ?? '');

// Vérifier la validité des champs
$erreurs = [];

// Validation du nom de l'association
if (empty($nom_association)) {
    $erreurs[] = "Le nom de l'association est obligatoire.";
} elseif (strlen($nom_association) > 100) {
    $erreurs[] = "Le nom de l'association ne doit pas dépasser 100 caractères.";
}

// Validation de l'adresse
if (strlen($adresse) > 50) {
    $erreurs[] = "L'adresse ne doit pas dépasser 50 caractères.";
}

// Validation du Code Postal
if (!empty($code_postal) && !preg_match('/^\d{5}$/', $code_postal)) {
    $erreurs[] = "Le code postal doit contenir exactement 5 chiffres.";
}

// Validation de la ville
if (strlen($ville) > 50) {
    $erreurs[] = "Le nom de la ville ne doit pas dépasser 50 caractères.";
}

// Validation du téléphone
if (strlen($telephone) > 20) {
    $erreurs[] = "Le numéro de téléphone est trop long (max 20 caractères).";
}

// Validation de l'email
if (!empty($email)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "Le format de l'adresse email est invalide.";
    } elseif (strlen($email) > 100) {
        $erreurs[] = "L'email ne doit pas dépasser 100 caractères.";
    }
}

// Validation du site web
if (strlen($site_web) > 100) {
    $erreurs[] = "L'URL du site web ne doit pas dépasser 100 caractères.";
}

// Validation des informations
if (strlen($informations) > 255) {
    $erreurs[] = "Le champ informations ne doit pas dépasser 255 caractères.";
}

// Gestion des erreurs
if (!empty($erreurs)) {
    // Enregistrer les erreurs en session et redirige
    $_SESSION['erreurs_validation'] = $erreurs;
    header("Location: ../pages/cartes/parametre_association.php?status=error");
    exit();
}

// Récupération logo existant
$assoc = getAssociation($pdo);
$target_file_db = $assoc['logo'] ?? null;

// Gestion de l'upload du logo
if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {

        // Vérification de la taille (2Mo max)
        $max_size = 2 * 1024 * 1024;
        if ($_FILES['logo']['size'] > $max_size) {
            $erreurs[] = "Le logo est trop lourd (2 Mo maximum).";
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['logo']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

        if (array_key_exists($mime, $allowed)) {
            // On ne procède à l'upload physique QUE s'il n'y a pas déjà d'erreurs
            if (empty($erreurs)) {
                $upload_dir = __DIR__ . '/../uploads/';
                if ($target_file_db && file_exists(__DIR__ . '/appWeb/' . $target_file_db)) {
                    unlink(__DIR__ . '/appWeb/' . $target_file_db);
                }

                $file_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $file_name)) {
                    $target_file_db = 'uploads/' . $file_name;
                }
            }
        } else {
            $erreurs[] = "Format de logo invalide (Seuls JPG et PNG sont acceptés).";
        }
    } else {
        $erreurs[] = "Une erreur est survenue lors du téléchargement du logo.";
    }
}

// Si on a ajouté des erreurs liées au logo, on redirige comme pour les autres erreurs
if (!empty($erreurs)) {
    $_SESSION['erreurs_validation'] = $erreurs;
    header("Location: ../pages/cartes/parametre_association.php?status=error");
    exit();
}

enregistrerAssociation(
    $pdo,
    $nom_association,
    $target_file_db,
    $adresse,
    $code_postal,
    $ville,
    $telephone,
    $email,
    $site_web,
    $informations,
    $couleurPrincipale_1,
    $couleurPrincipale_2,
    $couleurPrincipale_3,
    $couleurSecondaire_1,
    $couleurSecondaire_2,
    $couleurSecondaire_3
);

header("Location: ../pages/cartes/parametre_association.php?status=success");
exit();