<?php
session_start();

require "../../fonctions/fonctionsConnexion.php";

$pdo = null;

try {
    $pdo = connexionBDDUser();

    require "../../fonctions/verifDroit.php";
    // On passe la connexion active à la fonction de vérification
    verifierAccesDynamique($pdo);

    // Récupération des infos de l'asso après vérification
    $logoData = isset($_SESSION['id_association']) ? getLogoAssoc($pdo) : null;
    $nomData = isset($_SESSION['id_association']) ? getNomAssoc($pdo) : null;

    $logo = $logoData["logo"] ?? '';
    $nomAssoc = $nomData["nom"] ?? 'Mon Association';

} catch (Exception $e) {
    // si la base de données est inaccessible, on déconnecte par sécurité
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$titre = $_SESSION['titre_formulaire'] ?? '';
$rgpd = $_SESSION['rgpd'] ?? '';
$liste_champ = $_SESSION['liste_champ'] ?? [];
$toutesLesSections = $_SESSION['sections'] ?? [['label' => '']];

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
    <title>Aperçu du formulaire</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="../../css/styleGlobal.css" rel="stylesheet">
    <link href="../../css/styleIndex.css" rel="stylesheet">
</head>
<body>

<nav class="navbar shadow-sm py-3 px-4"
     style="background: linear-gradient(to right,
     <?= $couleur_principale_1 ?>,
     <?= $couleur_principale_2 ?>,
     <?= $couleur_principale_3 ?>);">
    <div class="container-fluid d-flex align-items-center">
        <div class="me-3">
            <a href="personnaliser_formulaire.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                <i class="fa-solid fa-arrow-left menu-item"></i>
            </a>
        </div>

        <div class="flex-grow-1">
            <h5 class="m-0 fw-bold">Aperçu du formulaire</h5>
        </div>
    </div>
</nav>

<div class="container my-5">
    <div class="info p-5">

        <div class="text-center mb-4">
            <?php if($logo): ?>
                <img src="../../<?php echo $logo; ?>" alt="Logo" style="max-height: 100px;" class="mb-3">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($nomAssoc); ?></h1>
        </div>

        <hr>

        <?php if ($titre): ?>
            <div class="text-center my-4">
                <h2 class="display-6"><?php echo htmlspecialchars($titre); ?></h2>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <?php if (!empty($liste_champ)): ?>
                <?php
                foreach($toutesLesSections as $section):
                    $labelSec = $section['label'];

                    $champsDeLaSection = array_filter($liste_champ, function($c) use ($labelSec) {
                        return (string)$c['Section'] === (string)$labelSec;
                    });

                    if (!empty($champsDeLaSection)):
                        if (empty($labelSec)): ?>
                            <div class="col-12"><hr class="my-4"></div>
                        <?php else: ?>
                            <div class="col-12">
                                <h5 class="mt-4 mb-3 text-primary border-bottom pb-2">
                                    <?php echo htmlspecialchars($labelSec); ?>
                                </h5>
                            </div>
                        <?php endif; ?>

                        <?php foreach($champsDeLaSection as $index => $champ): ?>
                        <?php
                        $taille = $champ['Taille'] ?? 6;
                        $colClass = ($taille == 12) ? 'col-12' : 'col-md-6';
                        ?>

                        <div class="<?php echo $colClass; ?> mb-3">
                            <label class="form-label fw-bold">
                                <?php echo htmlspecialchars($champ["Label"]); ?>
                                <?php echo !empty($champ["Obligatoire"]) ? '<span class="text-danger">*</span>' : ''; ?>
                            </label>

                            <?php if ($champ["Type"] === "textarea"): ?>
                                <textarea class="form-control" placeholder="<?php echo htmlspecialchars($champ["Placeholder"]); ?>" rows="3"></textarea>
                            <?php elseif ($champ["Type"] === "select"): ?>
                                <select class="form-select">
                                    <option><?php echo htmlspecialchars($champ["Placeholder"] ?: "Sélectionner..."); ?></option>
                                    <?php if (!empty($champ['Options'])): ?>
                                        <?php foreach ($champ['Options'] as $opt): ?>
                                            <option><?php echo htmlspecialchars($opt); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            <?php elseif ($champ["Type"] === "checkbox" || $champ["Type"] === "radio"): ?>
                                <div class="ms-2">
                                    <?php if (!empty($champ['Options'])): ?>
                                        <?php foreach ($champ['Options'] as $optIndex => $optLabel): ?>
                                            <div class="form-check mb-1">
                                                <input class="form-check-input"
                                                       type="<?php echo $champ["Type"]; ?>"
                                                       name="champ_<?php echo $index; ?>"
                                                       id="opt_<?php echo $index . '_' . $optIndex; ?>">
                                                <label class="form-check-label" for="opt_<?php echo $index . '_' . $optIndex; ?>">
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted small italic">Aucune option définie.</div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                            <input type="<?php echo $champ["Type"]; ?>"
                                   class="form-control"
                                   placeholder="<?php echo htmlspecialchars($champ["Placeholder"]); ?>"
                                   disabled>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                        <?php

                        if (empty($labelSec)): ?>
                            <div class="col-12"><hr class="my-4"></div>
                        <?php endif; ?>

                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="col-12 mt-4 small text-muted italic">
                    Les champs marqués d'une astérisque (*) sont obligatoires.
                </div>

                <?php if ($rgpd): ?>
                    <div class="col-12 mt-5 p-4 border rounded" style="background-color: #f8f9fa;">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-shield-halved me-2"></i>Mentions légales / RGPD</h5>
                        <div class="text-muted" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($rgpd); ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">Aucun champ à afficher.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<br><br>
</body>
</html>