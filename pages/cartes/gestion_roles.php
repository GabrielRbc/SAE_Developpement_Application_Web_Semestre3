<?php
session_start();


require "../../fonctions/fonctionsConnexion.php";
require "../../fonctions/fonctionsGestionRoles.php";

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

/* Sécurité */
if (
    !isset($_SESSION['nom'], $_SESSION['prenom'], $_SESSION['id_utilisateur'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'responsable' ||
    $_SESSION['idSession'] !== session_id()
) {
    header("Location: ../../index.php");
    exit();
}


$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$perPage = 8;

// Filtres
$filtre = trim($_GET['recherche'] ?? '');
$role_filtre = $_GET['role_filtre'] ?? '';
$ordre = ($_GET['ordre'] ?? 'alpha') === 'date' ? 'date' : 'alpha';
$page = max(1, (int)($_GET['page'] ?? 1));

$message = '';

/* Modification de rôle */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier') {
    if (!empty($_POST['id_utilisateur']) && !empty($_POST['role_utilisateur'])) {
        if (changerRole($pdo, $_POST['id_utilisateur'], $_POST['role_utilisateur'])) {
            $message = "Le rôle du compte a bien été modifié.";
        }
    }
}

/* Pagination */
$start = ($page - 1) * $perPage;

$totalMembres = compterMembresFiltres($pdo, $role_filtre, $filtre);
$totalPages = max(1, ceil($totalMembres / $perPage));

$membresPage = renvoieListeMembre(
        $pdo,
        $ordre,
        $role_filtre,
        $filtre,
        $perPage,
        $start
);

/* Statistiques */
$stats = compterRole($pdo);
$totalAdherent = $totalMembre = $totalResponsable = 0;

foreach ($stats as $r) {
    if ($r['role'] === 'responsable') $totalResponsable = $r['nombre'];
    if ($r['role'] === 'membre') $totalMembre = $r['nombre'];
    if ($r['role'] === 'adherent') $totalAdherent = $r['nombre'];
}

require '../../fonctions/fonctionsGestionCouleur.php';

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
    <meta charset="UTF-8">
    <title>Gestion des Membres</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="../../css/styleGlobal.css" rel="stylesheet">
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
</head>
<body>
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
            <h5 class="m-0 fw-bold">Gestion des Rôles</h5>
        </div>
    </div>
</nav>

<div class="container my-5">

    <div class="row g-4">
        <div class="col-md-4">
            <div class="info">
                <p class="text-muted m-0">Total Adhérent</p>
                <span class="info-valeur text-secondaire-1"><?= $totalAdherent ?></span>
            </div>
        </div>

        <div class="col-md-4">
            <div class="info">
                <p class="text-muted m-0">Total Membre</p>
                <span class="info-valeur text-secondaire-2"><?= $totalMembre ?></span>
            </div>
        </div>

        <div class="col-md-4">
            <div class="info">
                <p class="text-muted m-0">Total Responsable</p>
                <span class="info-valeur text-secondaire-3"><?= $totalResponsable ?></span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm my-4">
        <div class="card-body">
            <form method="get">
                <div class="row g-3 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                            </span>
                            <input type="text" name="recherche" class="form-control border-start-0" value="<?= htmlspecialchars($filtre) ?>" placeholder="Rechercher par nom, prénom ou email...">
                        </div>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="submit" class="btn btn-secondary w-100 d-flex align-items-center justify-content-center gap-2">
                            <i class="fa-solid fa-filter"></i> Filtrer
                        </button>
                    </div>
                    <div class="col-md-2 text-end">
                        <a href="gestion_roles.php" class="btn btn-danger w-100">
                            <i class="fa-solid fa-xmark"></i> Réinitialiser
                        </a>
                    </div>
                </div>

                <div class="row my-2 text-center">
                    <input type="hidden" name="ordre" id="ordre_input" value="<?= htmlspecialchars($ordre) ?>">
                    <div class="col-md-4">
                        <button type="submit" class="action-icon menu-item" onclick="document.getElementById('ordre_input').value='alpha'">Par ordre alphabétique</button>
                    </div>

                    <div class="col-md-4">
                        <button type="submit" class="action-icon menu-item" onclick="document.getElementById('ordre_input').value='date'">Par date de création</button>
                    </div>

                    <div class="col-md-4">
                        <select id="role_filtre" name="role_filtre" class="form-select" onchange="this.form.submit()">
                            <option value="">Tous les rôles</option>
                            <option value="responsable" <?= ($role_filtre==='responsable') ? 'selected' : '' ?>>Responsables</option>
                            <option value="membre" <?= ($role_filtre==='membre') ? 'selected' : '' ?>>Membres du bureau</option>
                            <option value="adherent" <?= ($role_filtre==='adherent') ? 'selected' : '' ?>>Adhérents</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive rounded shadow-sm overflow-auto">
        <table class="table mb-0">
            <thead>
            <tr class="table-secondary">
                <th>Nom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th class="text-center">Rôles</th>
                <th class="text-center">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($membresPage) === 0){ ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-3">Aucun membre trouvé</td>
                </tr>
            <?php } ?>
            <?php foreach ($membresPage as $adh) { ?>
                <tr>
                    <td><?= htmlspecialchars($adh['nom_complet']) ?></td>
                    <td><?= htmlspecialchars($adh['email']) ?></td>
                    <td><?= htmlspecialchars($adh['telephone'] ?? 'inconnu') ?></td>
                    <form method="post" onsubmit="return confirm('Confirmer le changement de rôle ?')">
                        <td>
                            <?php if ($adh['role'] != 'responsable') { ?>
                            <select name="role_utilisateur" class="form-select">
                                <option value="membre" <?= ($adh['role'] === 'membre') ? 'selected' : '' ?>>Membre du bureau</option>
                                <option value="adherent" <?= ($adh['role'] === 'adherent') ? 'selected' : '' ?>>Adhérent</option>
                            </select>
                            <?php } else {?>
                                <select name="role_utilisateur" class="form-select">
                                    <option value="responsable" <?= ($adh['role'] === 'responsable') ? 'selected' : '' ?>>Responsable</option>
                                </select>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <input type="hidden" name="id_utilisateur" value="<?= $adh['id_utilisateur'] ?>">
                            <input type="hidden" name="action" value="modifier">
                            <button type="submit" title="modifier"
                                    class="action-icon menu-item text-muted"
                                    <?= ($adh['role'] === 'responsable') ? 'disabled' : '' ?>>
                                Valider
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </td>
                    </form>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="my-4 d-flex justify-content-between align-items-center">
        <div>
            Affichage de <?= min($start + 1, $totalMembres) ?> à <?= min($start + count($membresPage), $totalMembres) ?> sur <?= $totalMembres ?> membres
        </div>
        <div class="d-flex gap-2">
            <?php
            $params = $_GET;
            unset($params['page']);
            $base_url = "?" . http_build_query($params);
            ?>
            <a href="<?= $base_url ?>&page=<?= max(1, $page - 1) ?>" class="btn btn-secondary <?= ($page <= 1) ? 'disabled' : '' ?>">Précédent</a>
            <a href="<?= $base_url ?>&page=<?= min($totalPages, $page + 1) ?>" class="btn btn-secondary <?= ($page >= $totalPages) ? 'disabled' : '' ?>">Suivant</a>
        </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>