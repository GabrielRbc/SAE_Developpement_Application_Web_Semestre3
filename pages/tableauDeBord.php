<?php
session_start();

/* Sécurité */

if (!isset($_SESSION['nom']) || !isset($_SESSION['prenom']) || !isset($_SESSION['role'])) {
    // L'utilisateur n'est pas connecté, retour à l'accueil
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['idSession'] != session_id()) {
    // L'id de session n'est pas le même
    header("Location: ../index.php");
    exit();
}

/* Paramètres de la page */

require "../fonctions/fonctionsConnexion.php";
$pdo = connexionBDDUser();

/* Test si le rôle n'a pas été modifié entre temps */
require "../fonctions/verifDroit.php";
try {
    verifierAccesDynamique($pdo);
} catch (Exception $e) {
    // Si probleme de connexion à la base de données on déconnecte par sécurité
    session_destroy();
    header("Location: ../index.php");
    exit();

}

require "../fonctions/fonctionsGestionAdherents.php";

$adherents = renvoieListeAdherent($pdo, 'alpha');
$totalAdherent = count($adherents);

$totalFicheComplete = 0;
$fichesIncompletes = 0;
$documentValide = 0;

foreach ($adherents as $adh) {
    if ($adh['fiche'] === 'complet') $totalFicheComplete++;
    elseif ($adh['fiche'] === 'incomplet') $fichesIncompletes++;
    if ($adh['documents'] === 'attente') $documentValide++;
}

$infoUtilisateur = $_SESSION['prenom'].' '. $_SESSION['nom'];
$roles = $_SESSION['role'];

/* Affichage correct du rôle */
$infoRoles = '';
if ($roles == 'responsable') {
    $infoRoles = 'Responsable';
} else if ($roles == 'membre') {
    $infoRoles = 'Membre du bureau';
} else {
    $infoRoles = 'Adhérent';
}

require '../fonctions/fonctionsGestionCouleur.php';

$couleurs = getCouleursAssociation($pdo, $_SESSION['id_utilisateur']);
$couleur_principale_1 = $couleurs['couleur_principale_1'];
$couleur_principale_2 = $couleurs['couleur_principale_2'];
$couleur_principale_3 = $couleurs['couleur_principale_3'];
$couleur_secondaire_1 = $couleurs['couleur_secondaire_1'];
$couleur_secondaire_2 = $couleurs['couleur_secondaire_2'];
$couleur_secondaire_3 = $couleurs['couleur_secondaire_3'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Tableau de Bord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="../css/styleGlobal.css" rel="stylesheet">
    <style>
        .text-secondaire-1 {
            color: <?php echo $couleur_secondaire_1; ?> !important;
        }

        .text-secondaire-2 {
            color: <?php echo $couleur_secondaire_2; ?> !important;
        }

        .text-secondaire-3 {
            color: <?php echo $couleur_secondaire_3; ?> !important;
        }
    </style>
    <script src="../js/scriptDeconnexion.js"></script>
</head>
<body>

<nav class="navbar shadow-sm py-3 px-4"
     style="background: linear-gradient(to right,
     <?= $couleur_principale_1 ?>,
     <?= $couleur_principale_2 ?>,
     <?= $couleur_principale_3 ?>);"><div class="d-flex align-items-center gap-3">
        <div class="dashboard-icon">
            <img src="https://cdn-icons-png.flaticon.com/512/1828/1828765.png" alt="Logo de l'association" width="32">
        </div>
        <div>
            <h5 class="m-0 fw-bold">Tableau de Bord</h5>
            <small class="text-muted"><?php echo $infoRoles . ' : ' . $infoUtilisateur; ?></small>
        </div>
    </div>

    <div class="ms-auto d-flex align-items-center gap-4">
        <a href="../deconnexion.php" class="text-decoration-none text-dark fw-semibold menu-item" id="lienDeconnexion">
            Déconnexion
        </a>
    </div>
</nav>

<div class="container mt-4">

    <?php if ($roles == 'responsable' || $roles == 'membre') {?>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="info">
                <p class="text-muted m-0">Total Adhérents &nbsp;</p>
                <span class="info-valeur text-primary"><?php echo $totalAdherent; ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info">
                <p class="text-muted m-0">Fiches Complètes</p>
                <span class="info-valeur text-secondaire-1"><?php echo $totalFicheComplete; ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info">
                <p class="text-muted m-0">Documents à Valider</p>
                <span class="info-valeur text-secondaire-2"><?php echo $documentValide; ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info">
                <p class="text-muted m-0">Fiches Incomplètes</p>
                <span class="info-valeur text-secondaire-3"><?php echo $fichesIncompletes; ?></span>
            </div>
        </div>
    </div>

    <h4 class="mt-5 mb-3 fw-semibold">Actions Rapides</h4>
    <div class="row g-4">
        <?php if ($roles == 'responsable') {?>
        <div class="col-lg-4 col-md-6">
            <a href="cartes/parametre_association.php" class="text-decoration-none text-dark fw-semibold">
                <div class="action-card">
                    <div class="row">
                        <div class="col-2">
                            <div class="icon-box icon-blue">
                                <i class="fa-solid fa-gear"></i>
                            </div>
                        </div>
                        <div class="col-10">
                            <h5 class="fw-bold">Paramétrage Association</h5>
                            <p>Configurer le nom, logo, couleurs de l'association</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php }?>

        <?php if ($roles == 'responsable') {?>
        <div class="col-lg-4 col-md-6">
            <a href="cartes/personnaliser_formulaire.php" class="text-decoration-none text-dark fw-semibold">
                <div class="action-card">
                    <div class="row">
                        <div class="col-2">
                            <div class="icon-box icon-orange">
                                <i class="fa-solid fa-clipboard-list"></i>
                            </div>
                        </div>
                        <div class="col-10">
                            <h5 class="fw-bold">Formulaire d'Inscription</h5>
                            <p>Personnaliser les champs du formulaire adhérent</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php }?>

        <?php if ($roles == 'responsable' || $roles == 'membre') {?>
            <div class="col-lg-4 col-md-6">
                <a href="cartes/validation_document.php" class="text-decoration-none text-dark fw-semibold">
                    <div class="action-card">
                        <div class="row">
                            <div class="col-2">
                                <div class="icon-box icon-purple">
                                    <i class="fa-solid fa-file-circle-check"></i>
                                </div>
                            </div>
                            <div class="col-10">
                                <h5 class="fw-bold">Validation Documents</h5>
                                <p>Valider les documents téléchargés par les adhérents</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php }?>

        <?php if ($roles == 'responsable' || $roles == 'membre') {?>
        <div class="col-lg-4 col-md-6">
            <a href="cartes/gestion_adherents.php" class="text-decoration-none text-dark fw-semibold">
                <div class="action-card">
                    <div class="row">
                        <div class="col-2">
                            <div class="icon-box icon-green">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        </div>
                        <div class="col-10">
                            <h5 class="fw-bold">Gestion des Adhérents</h5>
                            <p>Créer, modifier, rechercher les adhérents </p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php }?>

        <?php if ($roles == 'responsable') {?>
            <div class="col-lg-4 col-md-6">
                <a href="cartes/gestion_membres.php" class="text-decoration-none text-dark fw-semibold">
                    <div class="action-card">
                        <div class="row">
                            <div class="col-2">
                                <div class="icon-box icon-teal">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                            </div>
                            <div class="col-10">
                                <h5 class="fw-bold">Gestion des Membres du bureau</h5>
                                <p>Ajouter, filtrer les membres du bureau </p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php }?>

        <?php if ($roles == 'responsable') {?>
            <div class="col-lg-4 col-md-6">
                <a href="cartes/gestion_roles.php" class="text-decoration-none text-dark fw-semibold">
                    <div class="action-card">
                        <div class="row">
                            <div class="col-2">
                                <div class="icon-box icon-red">
                                    <i class="fa-solid fa-key"></i>
                                </div>
                            </div>
                            <div class="col-10">
                                <h5 class="fw-bold">Gestion des rôles</h5>
                                <p>Modifier, ajouter un rôle à un utilisateur</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php }?>

        <?php if ($roles == 'responsable') {?>
        <div class="col-lg-4 col-md-6">
            <a href="cartes/impExp.php" class="text-decoration-none text-dark fw-semibold">
                <div class="action-card">
                    <div class="row">
                        <div class="col-2">
                            <div class="icon-box icon-gray">
                                <i class="fa-solid fa-download"></i>
                            </div>
                        </div>
                        <div class="col-10">
                            <h5 class="fw-bold">Import / Export</h5>
                            <p>Importer ou exporter les données et paramétrages</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php }?>
    <?php } else {?>
        <?php if ($roles == 'adherent') {?>
        <h4 class="mt-5 mb-3 fw-semibold">Mon espace</h4>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <a href="cartes/fiche_adherent.php" class="text-decoration-none text-dark fw-semibold">
                    <div class="action-card">
                        <div class="row">
                            <div class="col-2">
                                <div class="icon-box icon-orange">
                                    <i class="fa-solid fa-clipboard-list"></i>
                                </div>
                            </div>
                            <div class="col-10">
                                <h5 class="fw-bold">Ma fiche Adhérent</h5>
                                <p>Remplir et mettre à jour mes informations</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <?php }?>
    <?php }?>
    </div>
</div>

<div class="mt-4">
    <footer class="bg-dark text-white text-center py-3">
        <p>
            <a href="mentions_legales.php" class="text-white">Mentions Légales</a> -
            <a href="confidentialite.php" class="text-white">Politique de confidentialité</a> -
            <a href="contact.php" class="text-white">Nous contacter</a>
        </p>
        <p>© Adhésium - 2026</p>
    </footer>
</div>

</body>
</html>