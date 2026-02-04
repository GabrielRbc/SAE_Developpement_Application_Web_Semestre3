<?php
session_start();

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

require "../../fonctions/fonctionsConnexion.php";
require "../../fonctions/fonctionsImpExp.php";

$pdo = null;

require "../../fonctions/verifDroit.php";
try {
    $pdo = connexionBDDUser();
    verifierAccesDynamique($pdo);
} catch (Exception $e) {
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

if (!isset($_SESSION['nom'], $_SESSION['prenom'], $_SESSION['id_utilisateur'])) {
    header("Location: ../../index.php"); exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'responsable') {
    header("Location: ../../index.php"); exit();
}
if ($_SESSION['idSession'] != session_id()) {
    header("Location: ../../index.php"); exit();
}

$id_utilisateur = $_SESSION['id_utilisateur'];
$assocId = getAssociationIdByUser($pdo, $id_utilisateur);

$importMessage = '';

/* ===================== EXPORT ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
    $exportType = $_POST['type_export'] ?? 'complet';
    $fileType   = $_POST['type_fichier'] ?? 'json';
    $selectedChamps = $_POST['champs'] ?? null;

    // Nettoyage des labels pour éviter les problèmes d'espaces/accent
    if ($selectedChamps) {
        $selectedChamps = array_map('trim', $selectedChamps);
    }

    $type = ($exportType === 'formulaire') ? 'formulaire' : 'adherents';
    $data = exportData($pdo, $assocId, $type, $exportType === 'personnalise' ? $selectedChamps : null);

    $filename = ($type === 'formulaire') ? "formulaire_export.$fileType" : "adherents_export.$fileType";

    if ($fileType === 'csv') {
        arrayToCSV($data, $filename);
    } else {
        arrayToJSON($data, $filename);
    }
    exit();
}

/* ===================== IMPORT ===================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    $importType = $_POST['type_import'] ?? 'adherents';
    $fileType   = $_POST['type_fichier'] ?? null;

    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['fichier']['tmp_name'];
        $fileName = $_FILES['fichier']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $data = [];
        if ($ext === 'json') {
            $json = file_get_contents($tmpName);
            $data = json_decode($json, true);
        } elseif ($ext === 'csv') {
            if (($handle = fopen($tmpName, 'r')) !== false) {
                $headers = fgetcsv($handle, 1000, ",");
                while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                    $data[] = array_combine($headers, $row);
                }
                fclose($handle);
            }
        } else {
            $importMessage = "Format de fichier non supporté ($ext).";
        }

        if (!empty($data)) {
            try {
                if ($importType === 'adherents') {
                    importAdherents($pdo, $assocId, $data);
                    $importMessage = "Importation des adhérents réussie !";
                } elseif ($importType === 'formulaire') {
                    importFormulaire($pdo, $assocId, $data);
                    $importMessage = "Importation du formulaire réussie !";
                } else {
                    $importMessage = "Type d'import inconnu.";
                }
            } catch (Exception $e) {
                $importMessage = "Erreur lors de l'import : " . htmlspecialchars($e->getMessage());
            }
        } elseif (!$importMessage) {
            $importMessage = "Fichier vide ou invalide.";
        }
    } else {
        $importMessage = "Aucun fichier n’a été envoyé ou une erreur est survenue.";
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
    <title>Import et export</title>
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
            <a href="../tableauDeBord.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                <i class="fa-solid fa-arrow-left menu-item"></i>
            </a>
        </div>

        <div class="flex-grow-1">
            <h5 class="m-0 fw-bold">Import / Export</h5>
        </div>

    </div>
</nav>

<div class="container mt-4">

    <div class="card shadow-sm p-4">

        <?php if($importMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($importMessage) ?></div>
        <?php endif; ?>

        <div class="btn-group mb-4 d-flex" role="group">
            <button id="btn-export" class="btn-switch active">
                <i class="fa-solid fa-upload"></i> Exporter</button>
            <button id="btn-import" class="btn-switch">
                <i class="fa-solid fa-download"></i> Importer</button>
        </div>

        <div id="section-export">
            <form method="post">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="type_export" id="hiddenTypeExport" value="complet">
                <input type="hidden" name="type_fichier" id="hiddenFileType" value="json">
                <input type="hidden" name="champs" id="hiddenChamps" value="">

                <h6 class="fw-bold mb-3">Type d'Export</h6>
                <div class="export-options d-flex flex-wrap gap-3 mb-4">
                    <div class="option-card active" data-export-type="complet">
                        <div class="icon-box icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <strong>Adhérents Complets</strong>
                            <p>Exporter tous les adhérents avec toutes leurs données</p>
                        </div>
                    </div>
                    <div class="option-card" data-export-type="formulaire">
                        <div class="icon-box icon-purple">
                            <i class="fa-solid fa-gear"></i>
                        </div>
                        <div>
                            <strong>Formulaire</strong>
                            <p>Exporter le paramétrage du formulaire d'inscription</p>
                        </div>
                    </div>
                    <div class="option-card" data-export-type="personnalise">
                        <div class="icon-box icon-orange">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                        <div>
                            <strong>Personnalisé</strong>
                            <p>Choisir les champs à exporter</p>
                        </div>
                    </div>
                </div>

                <div id="export-personnalise" class="mt-3 d-none">
                    <h6 class="fw-bold mb-2">Sélection des Champs du Formulaire</h6>
                    <div class="row">
                        <?php
                        $elements = getChampsFormulaire($pdo, $assocId);
                        foreach ($elements as $el): ?>
                            <div class="col-md-3 mb-2">
                                <label>
                                    <input type="checkbox" class="form-check-input me-2" name="champs[]" value="<?= $el['id_element'] ?>">
                                    <?= htmlspecialchars($el['label']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr>
                <h6 class="fw-bold mb-3">Format de Fichier</h6>
                <div class="export-options d-flex flex-wrap gap-3 mb-4">
                    <div class="option-card active" data-file-type="json">
                        <div class="icon-box icon-teal">
                            <i class="fa-solid fa-file-code"></i>
                        </div>
                        <div>
                            <strong>JSON</strong>
                            <p>Format structuré, idéal pour les sauvegardes complètes</p>
                        </div>
                    </div>
                    <div class="option-card" data-file-type="csv">
                        <div class="icon-box icon-green">
                            <i class="fa-solid fa-file-csv"></i>
                        </div>
                        <div>
                            <strong>CSV</strong>
                            <p>Compatible Excel, idéal pour les listes simples</p>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-download me-2"></i>Télécharger l'export</button>
                </div>
            </form>
        </div>

        <div id="section-import" class="d-none">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="type_import" id="hiddenTypeImport" value="adherents">

                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    L'import de données peut écraser les données existantes. Assurez-vous d'avoir effectué une sauvegarde avant de continuer.
                </div>

                <h6 class="fw-bold mb-3">Type d'Import</h6>
                <div class="export-options d-flex flex-wrap gap-3 mb-4">
                    <div class="option-card active" data-import-type="adherents">
                        <div class="icon-box icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <strong>Adhérents</strong>
                            <p>Importer une liste d'adhérents</p>
                        </div>
                    </div>
                    <div class="option-card" data-import-type="formulaire">
                        <div class="icon-box icon-purple">
                            <i class="fa-solid fa-gear"></i>
                        </div>
                        <div>
                            <strong>Formulaire</strong>
                            <p>Importer un paramétrage de formulaire</p>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3">Format Attendu</h6>
                <div class="export-options d-flex flex-wrap gap-3 mb-4">
                    <div class="option-card active" data-import-file="json">
                        <div class="icon-box icon-teal">
                            <i class="fa-solid fa-file-code"></i>
                        </div>
                        <div>
                            <strong>JSON</strong>
                            <p>Format structuré complet</p>
                        </div>
                    </div>
                    <div class="option-card" data-import-file="csv">
                        <div class="icon-box icon-green">
                            <i class="fa-solid fa-file-csv"></i>
                        </div>
                        <div>
                            <strong>CSV</strong>
                            <p>Format compatible Excel</p>
                        </div>
                    </div>
                </div>

                <div class="import-instructions p-3 rounded mb-3">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-circle-info me-2"></i>Instructions</h6>
                    <ul class="mb-0">
                        <li>Le fichier doit être au format choisi</li>
                        <li>Les champs doivent correspondre au paramétrage du formulaire</li>
                    </ul>
                </div>

                <input type="file" name="fichier" class="form-control mb-2" required>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-2"></i>Importer le fichier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-3">
    <p>
        <a href="../mentions_legales.php" class="text-white">Mentions Légales</a> -
        <a href="../confidentialite.php" class="text-white">Politique de confidentialité</a> -
        <a href="../contact.php" class="text-white">Nous contacter</a>
    </p>
    <p>© Adhésium - 2026</p>
</footer>

<script src="../../js/importExport.js"></script>

</body>
</html>