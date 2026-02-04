<?php
session_start();


require "../../fonctions/fonctionsConnexion.php";
$pdo = null;

require "../../fonctions/fonctionsGestionMembres.php";
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
if (!isset($_SESSION['nom']) || !isset($_SESSION['prenom']) || !isset($_SESSION['id_utilisateur'])) {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'responsable') {
    header("Location: ../../index.php");
    exit();
}

if ($_SESSION['idSession'] != session_id()) {
    header("Location: ../../index.php");
    exit();
}


$perPage = 8;

// Récupération des filtres
$filtre = trim($_GET['recherche'] ?? '');
$ordre = ($_GET['ordre'] ?? 'alpha') === 'date' ? 'date' : 'alpha';
$page = max(1, (int)($_GET['page'] ?? 1));

$message = '';

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Le nouveau membre a été créé avec succès.";
}

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $emailASupprimer = $_POST['email'] ?? '';
    if ($emailASupprimer !== '') {
        suppressionMembre($pdo, $emailASupprimer);
        $message = "Le membre $emailASupprimer a été supprimé.";
    }
}

// Récupération des membres
$membres = renvoieListeMembre($pdo, $ordre);

// Filtrage
$membresFiltres = [];

foreach ($membres as $adh) {
    $matchRecherche = true;
    if ($filtre !== '') {
        $filtreMin = strtolower($filtre);
        $matchRecherche = stripos($adh['nom'], $filtreMin) !== false ||
                stripos($adh['email'], $filtreMin) !== false ||
                stripos($adh['telephone'], $filtreMin) !== false;
    }

    if ($matchRecherche) {
        $membresFiltres[] = $adh;
    }
}

// Pagination
$totalMembres = count($membresFiltres);
$totalPages = max(1, ceil($totalMembres / $perPage));
$start = ($page - 1) * $perPage;
$membresPage = array_slice($membresFiltres, $start, $perPage);

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
    <title>Gestion des Membres</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" >
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
            <a href="../tableauDeBord.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                <i class="fa-solid fa-arrow-left menu-item"></i>
            </a>
        </div>

        <div class="flex-grow-1">
            <h5 class="m-0 fw-bold">Gestion des Membres du bureau</h5>
        </div>

        <div class="d-flex gap-2">
            <a href="impExp.php" class="btn btn-secondary d-flex align-items-center gap-2">
                <i class="fa-solid fa-download"></i>Exporter
            </a>
            <a href="nouveau_membre.php" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="fa-solid fa-plus"></i>Nouveau Membre
            </a>
        </div>
    </div>
</nav>

<div class="container my-5">

    <div class="card shadow-sm my-4">
        <div class="card-body">
            <form method="get">
                <input type="hidden" name="ordre" value="<?= htmlspecialchars($ordre) ?>">

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
                        <a href="gestion_membres.php" class="btn btn-danger w-100">
                            <i class="fa-solid fa-xmark"></i> Réinitialiser
                        </a>
                    </div>
                </div>

                <div class="row my-2 text-center">
                    <div class="col-md-6">
                        <button type="submit" class="action-icon menu-item" onclick="this.form.ordre.value='alpha'">Par ordre alphabétique</button>
                    </div>

                    <div class="col-md-6">
                        <button type="submit" class="action-icon menu-item" onclick="this.form.ordre.value='date'">Par date de création</button>
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
            <tr class="table-secondary">
                <th>Nom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th class="text-end">Actions</th>
            </tr>
            <?php if (count($membresPage) === 0){ ?>
                <tr>
                    <td colspan="4" class="text-center text-muted py-3">Aucun membre trouvé</td>
                </tr>
            <?php } ?>
                <?php foreach ($membresPage as $adh) { ?>
                <tr>
                    <td><?= htmlspecialchars($adh['nom']) ?></td>
                    <td><?= htmlspecialchars($adh['email']) ?></td>
                    <td><?= $adh['telephone'] ?? 'inconnu' ?></td>

                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            <form method="post" style="display:inline-flex; align-items:center; margin:0;" onsubmit="return confirm('Confirmer la suppression de <?= addslashes($adh['nom']); ?> ?');">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="email" value="<?= $adh['email'] ?>">
                                <button type="submit" class="action-icon menu-item text-muted" title="Supprimer">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php } ?>
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
            $baseUrl = '?' . http_build_query($params);
            ?>
            <a href="<?= $baseUrl ?>&page=<?= max(1, $page - 1) ?>" class="btn btn-secondary <?= ($page <= 1) ? 'disabled' : '' ?>">Précédent</a>
            <a href="<?= $baseUrl ?>&page=<?= min($totalPages, $page + 1) ?>" class="btn btn-secondary <?= ($page >= $totalPages) ? 'disabled' : '' ?>">Suivant</a>
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

</body>
</html>