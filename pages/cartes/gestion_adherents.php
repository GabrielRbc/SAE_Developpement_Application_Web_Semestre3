<?php
session_start();

require "../../fonctions/fonctionsConnexion.php";
$pdo = null;


require "../../fonctions/verifDroit.php";
require "../../fonctions/fonctionsGestionAdherents.php";

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


$perPage = 8;

$filtre = trim($_GET['recherche'] ?? '');
$filtreFiche = $_GET['fiche'] ?? '';
$filtreDocument = $_GET['document'] ?? '';
$ordre = ($_GET['ordre'] ?? 'alpha') === 'date' ? 'date' : 'alpha';
$page = max(1, (int)($_GET['page'] ?? 1));

$message = '';

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Le nouvel adhérent a été créé avec succès.";
}

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $emailASupprimer = $_POST['email'] ?? '';
    if ($emailASupprimer !== '') {
        suppressionAdherent($pdo, $emailASupprimer);
        $message = "L'adhérent $emailASupprimer a été supprimé.";
    }
}

// Récupération des adhérents
$adherents = renvoieListeAdherent($pdo, $ordre);

// Filtrage
$adherentsFiltres = [];

foreach ($adherents as $adh) {

    $matchRecherche = true;
    if ($filtre !== '') {
        $matchRecherche =
                stripos($adh['nom'], $filtre) !== false ||
                stripos($adh['email'], $filtre) !== false ||
                stripos($adh['telephone'], $filtre) !== false;
    }

    $matchFiche = true;
    if ($filtreFiche !== '') {
        if ($filtreFiche === 'complete') $matchFiche = $adh['fiche'] === 'complet';
        elseif ($filtreFiche === 'incomplet') $matchFiche = $adh['fiche'] === 'incomplet';
        elseif ($filtreFiche === 'attente') $matchFiche = $adh['fiche'] === 'attente';
    }

    $matchDocument = true;
    if ($filtreDocument !== '') {
        if ($filtreDocument === 'valide') $matchDocument = $adh['documents'] === 'valide';
        elseif ($filtreDocument === 'manquant') $matchDocument = $adh['documents'] === 'manquant';
        elseif ($filtreDocument === 'valider') $matchDocument = $adh['documents'] === 'attente';
    }

    if ($matchRecherche && $matchFiche && $matchDocument) {
        $adherentsFiltres[] = $adh;
    }
}

// Pagination
$totalAdherents = count($adherentsFiltres);
$totalPages = max(1, ceil($totalAdherents / $perPage));
$start = ($page - 1) * $perPage;
$adherentsPage = array_slice($adherentsFiltres, $start, $perPage);

// Statistiques
$total = count($adherentsFiltres);
$totalFicheComplete = 0;
$totalFicheAttente = 0;
$FichesIncompletes = 0;
$docAValider = 0;
$document = true;

foreach ($adherentsFiltres as $adh) {
    if ($adh['fiche'] === 'complet') $totalFicheComplete++;
    elseif ($adh['fiche'] === 'incomplet') $FichesIncompletes++;
    elseif ($adh['fiche'] === 'attente') $totalFicheAttente++;

    if ($adh['documents'] === 'attente') $docAValider++;
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
    <title>Gestion des Adhérents</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="../../css/styleGlobal.css" rel="stylesheet">
    <style>
        .fiche-complete {
            background: color-mix(in srgb, <?php echo $couleur_secondaire_1; ?> 15%, white);
            color: <?php echo $couleur_secondaire_1; ?>;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        .fiche-attente {
            background: color-mix(in srgb, <?php echo $couleur_secondaire_2; ?> 15%, white);
            color: <?php echo $couleur_secondaire_2; ?>;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        .fiche-incomplete {
            background: color-mix(in srgb, <?php echo $couleur_secondaire_3; ?> 15%, white);
            color: <?php echo $couleur_secondaire_3; ?>;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
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
            <h5 class="m-0 fw-bold">Gestion des Adhérents</h5>
        </div>

        <div class="d-flex gap-2">
            <a href="impExp.php" class="btn btn-secondary d-flex align-items-center gap-2">
                <i class="fa-solid fa-download"></i>Exporter
            </a>
            <a href="nouvel_adherent.php" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="fa-solid fa-plus"></i>Nouvel Adhérent
            </a>
        </div>
    </div>
</nav>

<div class="container my-5">

    <div class="row g-4">
        <div class="<?= $document ? 'col-md-3' : 'col-md-4'; ?>">
            <div class="info">
                <p class="text-muted m-0">Total</p>
                <span class="info-valeur text-primary"><?= $total ?></span>
            </div>
        </div>
        <div class="<?= $document ? 'col-md-3' : 'col-md-4'; ?>">
            <div class="info">
                <p class="text-muted m-0">En attente</p>
                <span class="info-valeur text-secondaire-2"><?= $totalFicheAttente ?></span>
            </div>
        </div>
        <div class="<?= $document ? 'col-md-3' : 'col-md-4'; ?>">
            <div class="info">
                <p class="text-muted m-0">Incomplètes</p>
                <span class="info-valeur text-secondaire-3"><?= $FichesIncompletes ?></span>
            </div>
        </div>
        <?php if ($document) { ?>
            <div class="col-md-3">
                <div class="info">
                    <p class="text-muted m-0">Docs à valider</p>
                    <span class="info-valeur text-secondaire-1"><?= $docAValider ?></span>
                </div>
            </div>
        <?php }?>
    </div>

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
                        <a href="gestion_adherents.php" class="btn btn-danger w-100">
                            <i class="fa-solid fa-xmark"></i> Réinitialiser
                        </a>
                    </div>

                </div>

                <div class="row my-2 text-center">
                    <div class="<?= $document ? 'col-md-3' : 'col-md-4'; ?>">
                        <button type="submit" class="action-icon menu-item" onclick="this.form.ordre.value='alpha'">Par ordre alphabétique</button>
                    </div>

                    <div class="<?= $document ? 'col-md-3' : 'col-md-4'; ?>">
                        <button type="submit" class="action-icon menu-item" onclick="this.form.ordre.value='date'">Par date de création</button>
                    </div>

                    <div class="<?= $document ? 'col-md-3' : 'col-md-4'; ?>">
                        <select id="fiche" name="fiche" class="form-select" onchange="this.form.submit()">
                            <option value="">Toutes les fiches</option>
                            <option value="complete" <?= ($filtreFiche==='complete') ? 'selected' : '' ?>>Fiches complètes</option>
                            <option value="incomplet" <?= ($filtreFiche==='incomplet') ? 'selected' : '' ?>>Fiches incomplètes</option>
                            <option value="attente" <?= ($filtreFiche==='attente') ? 'selected' : '' ?>>Fiches en attente</option>
                        </select>
                    </div>

                    <?php if ($document) { ?>
                        <div class="<?= $document ? 'col-md-3' : 'col-md-4'; ?>">
                            <select id="document" name="document" class="form-select" onchange="this.form.submit()">
                                <option value="">Tous les documents</option>
                                <option value="valide" <?= ($filtreDocument==='valide') ? 'selected' : '' ?>>Documents validés</option>
                                <option value="manquant" <?= ($filtreDocument==='manquant') ? 'selected' : '' ?>>Documents manquants</option>
                                <option value="valider" <?= ($filtreDocument==='valider') ? 'selected' : '' ?>>Documents à valider</option>
                            </select>
                        </div>
                    <?php } ?>
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
                <th>Fiche</th>
                <?php if ($document) { ?><th>Documents</th><?php } ?>
                <th class="text-end">Actions</th>
            </tr>
            <?php if (count($adherentsPage) === 0){ ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">Aucun adhérent trouvé</td>
                </tr>
            <?php } ?>
            <?php foreach ($adherentsPage as $adh) { ?>
                <tr>
                    <td><?= htmlspecialchars($adh['nom']) ?></td>
                    <td><?= htmlspecialchars($adh['email']) ?></td>
                    <td><?= $adh['telephone'] ?? 'inconnu' ?></td>
                    <td>
                        <?php
                        if ($adh['fiche'] === 'complet') echo '<span class="fiche-complete">Complète</span>';
                        elseif ($adh['fiche'] === 'incomplet') echo '<span class="fiche-incomplete">Incomplète</span>';
                        else echo '<span class="fiche-attente">En attente</span>';
                        ?>
                    </td>
                    <?php if ($document) { ?>
                        <td>
                            <?php
                            if ($adh['documents'] === 'valide') echo '<span class="fiche-complete">Validés</span>';
                            elseif ($adh['documents'] === 'attente') echo '<span class="fiche-attente">À valider</span>';
                            else echo '<span class="fiche-incomplete">Manquants</span>';
                            ?>
                        </td>
                    <?php } ?>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            <a href="voir.php?id=<?= $adh['email'] ?>" class="action-icon menu-item text-muted" title="Voir"><i class="fa-solid fa-eye"></i></a>
                            <a href="modifier.php?id=<?= $adh['email'] ?>" class="action-icon menu-item text-muted" title="Modifier"><i class="fa-regular fa-pen-to-square"></i></a>
                            <?php if ($adh['documents'] === 'attente' && $document) { ?>
                                <a href="validation_document.php?id=<?= $adh['email'] ?>" class="action-icon menu-item text-muted" title="Valider"><i class="fa-solid fa-file-circle-check"></i></a>
                            <?php } ?>
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
            Affichage de <?= min($start + 1, $totalAdherents) ?>
            à <?= min($start + count($adherentsPage), $totalAdherents) ?>
            sur <?= $totalAdherents ?> adhérents
        </div>

        <div class="d-flex gap-2">
            <?php
            $params = $_GET;
            unset($params['page']);
            $baseUrl = '?' . http_build_query($params);
            ?>
            <a href="<?= $baseUrl ?>&page=<?= max(1, $page - 1) ?>"
               class="btn btn-secondary <?= ($page <= 1) ? 'disabled' : '' ?>">
                Précédent
            </a>
            <a href="<?= $baseUrl ?>&page=<?= min($totalPages, $page + 1) ?>"
               class="btn btn-secondary <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                Suivant
            </a>
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