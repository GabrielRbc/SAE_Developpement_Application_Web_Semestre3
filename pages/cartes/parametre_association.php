<?php
session_start();

require "../../fonctions/fonctionsConnexion.php";
require "../../fonctions/verifDroit.php";

try {
    $pdo = connexionBDDUser();

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

// Récupération des informations de l'association et des paramètres
$sqlAssoc = "SELECT * FROM associations LIMIT 1";
$sqlParamAssoc = "SELECT * FROM parametrage_association LIMIT 1";

$stmtAssoc = $pdo->prepare($sqlAssoc);
$stmtAssoc->execute();
$association = $stmtAssoc->fetch(PDO::FETCH_ASSOC);

$stmtParamAssoc = $pdo->prepare($sqlParamAssoc);
$stmtParamAssoc->execute();
$parametre_association = $stmtParamAssoc->fetch(PDO::FETCH_ASSOC);

// Récupération des informations de l'association
$nom_association = $association['nom'] ?? '';
$adresse = $association['adresse'] ?? '';
$code_postal = $association['code_postal'] ?? '';
$ville = $association['ville'] ?? '';
$telephone = $association['telephone'] ?? '';
$email = $association['email'] ?? '';
$site_web = $association['lien_site'] ?? '';
$logo = $association['logo'] ?? '';


// Récupération des couleurs depuis la base de données
$couleur_principale_1 = $parametre_association['couleur_principale_1'];
$couleur_principale_2 = $parametre_association['couleur_principale_2'];
$couleur_principale_3 = $parametre_association['couleur_principale_3'];

$couleur_secondaire_1 = $parametre_association['couleur_secondaire_1'];
$couleur_secondaire_2 = $parametre_association['couleur_secondaire_2'];
$couleur_secondaire_3 = $parametre_association['couleur_secondaire_3'];

// Stockage des couleurs dans la session pour un accès facile lors de l'enregistrement
$_SESSION['couleur_principale_1'] = $couleur_principale_1;
$_SESSION['couleur_principale_2'] = $couleur_principale_2;
$_SESSION['couleur_principale_3'] = $couleur_principale_3;
$_SESSION['couleur_secondaire_1'] = $couleur_secondaire_1;
$_SESSION['couleur_secondaire_2'] = $couleur_secondaire_2;
$_SESSION['couleur_secondaire_3'] = $couleur_secondaire_3;

require '../../fonctions/fonctionsGestionCouleur.php';

// Récupération des couleurs pour la barre de navigation
$couleurs = getCouleursAssociation($pdo, $_SESSION['id_utilisateur']);
$primaire = $couleurs['couleur_principale_1'];
$secondaire = $couleurs['couleur_principale_2'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramétrage de l'Association</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- CSS personnel -->
    <link rel="stylesheet" href="../../css/styleGlobal.css">
</head>
    <body>
        <!-- Barre de navigation -->
        <nav class="navbar shadow-sm py-3 px-4"
             style="background: linear-gradient(to right,
             <?= $couleur_principale_1 ?>,
             <?= $couleur_principale_2 ?>,
             <?= $couleur_principale_3 ?>);">
            <div class="container-fluid d-flex align-items-center">
                <div class="me-3">
                    <a href="../tableauDeBord.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                        <i class="fa-solid fa-arrow-left menu-item"></i>
                    </a>
                </div>

                <div class="flex-grow-1">
                    <h5 class="m-0 fw-bold">Paramétrage de l'Association</h5>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" form="creation_association" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i>&nbsp Enregistrer
                    </button>
                    <form id="supprimer_association" action="../../traitement/traitement_parametre_association.php" method="POST" onsubmit="return confirmDelete()">
                        <input type="hidden" name="action" value="supprimer_asso">
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-trash"></i>&nbsp Supprimer l'association
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <div class="container my-5">

            <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Association supprimée !</strong> Toutes les données ont été effacées.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Enregistrement réussi !</strong> Les paramètres de l'association ont été mis à jour.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <!-- Informations Générales -->
            <form id = "creation_association" action="../../traitement/traitement_parametre_association.php" method="POST" enctype="multipart/form-data">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Informations Générales</h5>

                        <?php if (isset($_SESSION['erreurs_validation'])): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($_SESSION['erreurs_validation'] as $erreur): ?>
                                        <li><?php echo htmlspecialchars($erreur); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php unset($_SESSION['erreurs_validation']); ?>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="nom_association" class="form-label">Nom de l'association<span class="text-danger"> *</span></label>
                            <input type="text" class="form-control" id="nom_association" name="nom_association" placeholder="Association Sportive du Quartier" value="<?php echo htmlspecialchars($nom_association) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <input type="text" class="form-control" id="adresse" name="adresse" placeholder="12 Rue de la République" value="<?php echo htmlspecialchars($adresse) ?>">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="code_postal" class="form-label">Code postal</label>
                                <input type="text" class="form-control" id="code_postal" name="code_postal" placeholder="12000" value="<?php echo htmlspecialchars($code_postal) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="ville" class="form-label">Ville</label>
                                <input type="text" class="form-control" id="ville" name="ville" placeholder="Rodez" value="<?php echo htmlspecialchars($ville) ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="01 23 45 67 89" value="<?php echo htmlspecialchars($telephone) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="contact@association.fr" value="<?php echo htmlspecialchars($email) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="site_web" class="form-label">Site web</label>
                            <input type="url" class="form-control" id="site_web" name="site_web" placeholder="www.association.fr" value="<?php echo htmlspecialchars($site_web) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="informations" class="form-label">Informations</label>
                            <textarea id="informations" name="informations" class="form-control" rows="4" placeholder="Informations supplémentaires sur l'association..."><?php echo htmlspecialchars($association['information'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="logo" class="form-label">Logo de l'Association</label>
                            <div id="logo-upload-box" class="border rounded p-3 d-flex align-items-center bg-light">
                                <div class="text-center me-3">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <div>
                                    <p class="mb-1 fw-bold">Télécharger un logo</p>
                                    <p class="small text-muted mb-2">Format demandé : PNG, JPG ou JPEG. Taille maximum 2 Mo</p>
                                    <input class="form-control visually-hidden" type="file" id="logo" name="logo" accept=".png, .jpg, .jpeg">
                                    <label for="logo" class="btn btn-outline-primary btn-sm">Parcourir...</label>
                                    <span id="logo-file-name" class="ms-2 small text-muted">Aucun fichier sélectionné.</span>
                                </div>
                            </div>
                            <?php if ($logo): ?>
                                <div class="mb-3">
                                    <p class="small text-muted">Logo actuel :</p>
                                    <img src="../../<?php echo htmlspecialchars($logo); ?>" alt="Logo Association" style="max-height: 100px; border: 1px solid #ddd;" class="rounded p-1">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Charte Graphique -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Charte Graphique</h5>

                        <!-- Couleur Principale -->
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <label for="couleur_principale_1" class="fw-bold mb-0">
                                Couleur Principale 1
                            </label>
                            <input type="color" id="couleur_principale_1" name="couleur_principale_1" value="<?php echo htmlspecialchars($couleur_principale_1); ?>">

                            <label for="couleur_principale_2" class="fw-bold mb-0">
                                Couleur Principale 2
                            </label>
                            <input type="color" id="couleur_principale_2" name="couleur_principale_2" value="<?php echo htmlspecialchars($couleur_principale_2); ?>">

                            <label for="couleur_principale_3" class="fw-bold mb-0">
                                Couleur Principale 3
                            </label>
                            <input type="color" id="couleur_principale_3" name="couleur_principale_3" value="<?php echo htmlspecialchars($couleur_principale_3); ?>">
                        </div>

                        <!-- Couleur Secondaire -->
                        <div class="d-flex align-items-center gap-2">
                            <label for="couleur_secondaire_1" class="fw-bold mb-0">
                                Couleur Secondaire 1
                            </label>
                            <input type="color" id="couleur_secondaire_1" name="couleur_secondaire_1" value="<?php echo htmlspecialchars($couleur_secondaire_1); ?>">

                            <label for="couleur_secondaire_2" class="fw-bold mb-0">
                                Couleur Secondaire 2
                            </label>
                            <input type="color" id="couleur_secondaire_2" name="couleur_secondaire_2" value="<?php echo htmlspecialchars($couleur_secondaire_2); ?>">

                            <label for="couleur_secondaire_3" class="fw-bold mb-0">
                                Couleur Secondaire 3
                            </label>
                            <input type="color" id="couleur_secondaire_3" name="couleur_secondaire_3" value="<?php echo htmlspecialchars($couleur_secondaire_3); ?>">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
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

        <script src="../../js/parametre_association.js"></script>
    </body>
</html>