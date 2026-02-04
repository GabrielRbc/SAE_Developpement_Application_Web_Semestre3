<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require "../../fonctions/fonctionsConnexion.php";
require "../../fonctions/fonctionValidation.php";

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
if (!isset($_SESSION['nom']) || !isset($_SESSION['prenom']) || !isset($_SESSION['id_utilisateur'])) {
    header("Location: ../../index.php");
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


// 🔹 Paramètres GET
$parPage = 8;
$page    = max(1, (int)($_GET['page'] ?? 1));
$start   = ($page - 1) * $parPage;
$ordre       = ($_GET['ordre'] ?? 'alpha') === 'date' ? 'date' : 'alpha';
$filtreFiche = $_GET['fichier'] ?? '';
$recherche   = $_GET['recherche'] ?? '';

// 🔹 Action valider/refuser
if (isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['valide','refuse'], true)) {
    updateFichierStatut($pdo, (int)$_GET['id'], $_GET['action']);

    // Redirection vers la même page en enlevant 'action' et 'id'
    $params = $_GET;
    unset($params['action'], $params['id']);
    header("Location: ?" . http_build_query($params));
    exit;
}

// 🔹 Comptages et listes
$counts        = getFichiersCounts($pdo);
$totalFiltered = countFichiers($pdo, $filtreFiche, $recherche);
$totalPages    = max(1, ceil($totalFiltered / $parPage));
$debut         = $start + 1;

$stmt = getFichiers($pdo, $filtreFiche, $recherche, $ordre, $start, $parPage);
$fin  = $start + ($stmt ? $stmt->rowCount() : 0);

// 🔹 Construire URL avec filtres
function buildUrl($params) {
    return '?' . http_build_query($params);
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
    <title>Validation des Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .fichier_valide {
            background: color-mix(in srgb, <?php echo $couleur_secondaire_1; ?> 15%, white);
            color: <?php echo $couleur_secondaire_1; ?>;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        .fichier-attente {
            background: color-mix(in srgb, <?php echo $couleur_secondaire_2; ?> 15%, white);
            color: <?php echo $couleur_secondaire_2; ?>;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        .fichier-refuse {
            background: color-mix(in srgb, <?php echo $couleur_secondaire_3; ?> 15%, white);
            color: <?php echo $couleur_secondaire_3; ?>;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
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
            <h5 class="m-0 fw-bold">Validation des Documents</h5>
        </div>

    </div>
</nav>

<div class="container my-5">

    <!-- Statistiques -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="info">
                <p class="text-muted m-0">En attente</p>
                <span class="info-valeur text-secondaire-1"><?= $counts['attente'] ?: 0; ?></span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info">
                <p class="text-muted m-0">Validés</p>
                <span class="info-valeur text-secondaire-2"><?= $counts['valide'] ?: 0; ?></span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info">
                <p class="text-muted m-0">Refusés</p>
                <span class="info-valeur text-secondaire-3"><?= $counts['refuse'] ?: 0; ?></span>
            </div>
        </div>
    </div>

    <!-- Formulaire filtres -->
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
                            <input type="text" name="recherche" class="form-control border-start-0" value="<?= htmlspecialchars($recherche) ?>" placeholder="Nom, prénom ou email...">
                        </div>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-filter"></i> Filtrer</button>
                    </div>
                    <div class="col-md-2 text-end">
                        <a href="validation_document.php" class="btn btn-danger w-100"><i class="fa-solid fa-xmark"></i> Réinitialiser</a>
                    </div>
                </div>

                <div class="row my-2 text-center">
                    <div class="col-md-4">
                        <button type="submit" class="action-icon menu-item" onclick="this.form.ordre.value='alpha'">Par ordre alphabétique</button>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="action-icon menu-item" onclick="this.form.ordre.value='date'">Par date d'envoi</button>
                    </div>
                    <div class="col-md-4">
                        <select name="fichier" class="form-select" onchange="this.form.submit()">
                            <option value="">Tous les fichiers</option>
                            <option value="valide" <?= ($filtreFiche==='valide')?'selected':'' ?>>Fichiers validés</option>
                            <option value="refuse" <?= ($filtreFiche==='refuse')?'selected':'' ?>>Fichiers refusés</option>
                            <option value="attente" <?= ($filtreFiche==='attente')?'selected':'' ?>>Fichiers en attente</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau -->
    <div class="table-responsive rounded shadow-sm overflow-auto">
        <table class="table mb-0">
            <tr class="table-secondary">
                <th>Adhérent</th>
                <th>Type</th>
                <th>Fichier</th>
                <th>Date d'envoi</th>
                <th>Statut</th>
                <th class="text-end">Actions</th>
            </tr>
            <tbody>
            <?php
            if (!$stmt || $stmt->rowCount() === 0) {
                echo '<tr><td colspan="6" class="text-center text-muted py-3">Aucun document trouvé</td></tr>';
            } else {
                while ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($doc['name']).'</td>';
                    echo '<td>'.htmlspecialchars($doc['type']).'</td>';
                    echo '<td>'.htmlspecialchars($doc['file']).'</td>';
                    echo '<td>'.$doc['date'].'</td>';
                    $classe = match ($doc['status']) {
                        'valide'  => 'fichier_valide',
                        'attente' => 'fichier-attente',
                        'refuse'  => 'fichier-refuse',
                    };

                    echo "<td><span class='$classe'>" . showStatus($doc['status']) . "</span></td>";
                    echo '<td class="text-end"><div class="d-inline-flex gap-2">';
                    echo '<a href="../../'.$doc['chemin_fichier'].'" target="_blank" class="text-muted"><i class="fa-regular fa-eye"></i></a>';
                    echo '<a href="../../'.$doc['chemin_fichier'].'" download class="text-muted"><i class="fa-solid fa-download"></i></a>';

                    if ($doc['status'] === 'attente') {
                        $paramsAction = $_GET;
                        $paramsAction['id'] = $doc['id'];
                        $paramsAction['action'] = 'valide';
                        echo '<a href="?'.buildUrl($paramsAction).'" class="text-success"><i class="fa-solid fa-check"></i></a>';

                        $paramsAction['action'] = 'refuse';
                        echo '<a href="?'.buildUrl($paramsAction).'" class="text-danger"><i class="fa-solid fa-xmark"></i></a>';
                    }

                    echo '</div></td></tr>';
                }
            }
            ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="my-4 d-flex justify-content-between align-items-center">
        <p class="mb-0">Affichage de <?= $debut ?> à <?= $fin ?> sur <?= $totalFiltered ?> documents</p>
        <div class="d-flex gap-2">
            <?php
            $paramsPage = $_GET;
            unset($paramsPage['page']);
            ?>
            <a href="?<?= buildUrl($paramsPage) ?>&page=<?= max(1,$page-1) ?>" class="btn btn-secondary <?= ($page <= 1)?'disabled':'' ?>">Précédent</a>
            <a href="?<?= buildUrl($paramsPage) ?>&page=<?= min($totalPages,$page+1) ?>" class="btn btn-secondary <?= ($page >= $totalPages)?'disabled':'' ?>">Suivant</a>
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