<?php
session_start();

require "../../fonctions/fonctionsConnexion.php";
require '../../fonctions/fonctionsGestionCouleur.php';
require '../../fonctions/verifDroit.php';

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

// On vérifie que la personne connectée est bien un administrateur
if (!isset($_SESSION['role']) || $_SESSION['role'] == 'adherent') {
    header("Location: ../../index.php");
    exit();
}

// On vérifie qu'on a bien reçu l'email de l'adhérent à modifier dans l'URL
if (empty($_GET['id'])) {
    header("Location: gestion_adherents.php");
    exit();
}

$email_adherent = $_GET['id'];

// On cherche les infos générales de l'adhérent dans la base de données
$stmtUser = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
$stmtUser->execute([$email_adherent]);
$adherent = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$adherent) {
    header("Location: gestion_adherents.php");
    exit();
}

$id_user_cible = $adherent['id_utilisateur'];

// On récupère l'ID du formulaire actif de l'association
$stmtForm = $pdo->prepare("SELECT id_formulaire, nom FROM formulaires WHERE id_association = 1 LIMIT 1");
$stmtForm->execute();
$formulaire = $stmtForm->fetch(PDO::FETCH_ASSOC);
$id_formulaire = $formulaire['id_formulaire'];

// Cette requête récupère TOUT ce qui compose le formulaire :
// - Les questions (champs)
// - Les titres (sections)
// - Les demandes de fichiers
// - Les options de réponses (pour les cases à cocher), regroupées avec GROUP_CONCAT
$stmtElements = $pdo->prepare("
    SELECT 
        fe.id_element, fe.type_element, fe.ordre, fe.obligatoire,
        c.id_champ, c.label AS champ_label, c.type AS champ_type, c.taille_champ,
        s.label AS section_label,
        f.id_fichier, f.nom AS fichier_nom, f.format AS fichier_format, f.taille_fichier,
        GROUP_CONCAT(o.label ORDER BY o.id_options SEPARATOR '||') as options_labels
    FROM formulaires_elements fe
    LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
    LEFT JOIN champs c ON fec.id_champ = c.id_champ
    LEFT JOIN formulaires_elements_sections fes ON fe.id_element = fes.id_element
    LEFT JOIN sections s ON fes.id_section = s.id_section
    LEFT JOIN formulaires_elements_fichiers fef ON fe.id_element = fef.id_element
    LEFT JOIN fichiers f ON fef.id_fichier = f.id_fichier
    LEFT JOIN options o ON c.id_champ = o.id_champ
    WHERE fe.id_formulaire = ?
    GROUP BY fe.id_element, fe.type_element, fe.ordre, fe.obligatoire, 
             c.id_champ, c.label, c.type, c.taille_champ, 
             s.label, 
             f.id_fichier, f.nom, f.format, f.taille_fichier
    ORDER BY fe.ordre
");
$stmtElements->execute([$id_formulaire]);
$elementsFormulaire = $stmtElements->fetchAll(PDO::FETCH_ASSOC);

// On récupère les réponses textes déjà enregistrées pour cet adhérent
$stmtValeurs = $pdo->prepare("SELECT id_element, valeur FROM adherent_champs_valeurs WHERE id_utilisateur = ?");
$stmtValeurs->execute([$id_user_cible]);
$valeurs = [];
foreach ($stmtValeurs->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $valeurs[$v['id_element']] = $v['valeur'];
}

// On récupère les fichiers déjà envoyés par cet adhérent
$stmtDocs = $pdo->prepare("SELECT id_fichier, chemin_fichier, statut FROM fichiers_utilisateur WHERE id_utilisateur = ?");
$stmtDocs->execute([$id_user_cible]);
$docsEnvoyes = $stmtDocs->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);


// Si les champs du formulaire (Nom, Email...) sont vides, on met les infos du compte
foreach ($elementsFormulaire as $element) {
    if ($element['type_element'] === 'champ' && !is_null($element['champ_label'])) {
        if (empty($valeurs[$element['id_element']])) {
            $label = mb_strtolower($element['champ_label']);
            if ($label === 'nom') $valeurs[$element['id_element']] = $adherent['nom'];
            elseif ($label === 'prenom' || $label === 'prénom') $valeurs[$element['id_element']] = $adherent['prenom'];
            elseif ($label === 'email' || $label === 'adresse mail') $valeurs[$element['id_element']] = $adherent['email'];
            elseif ($label === 'telephone' || $label === 'téléphone') $valeurs[$element['id_element']] = $adherent['telephone'];
        }
    }
}

// Couleurs de l'interface
$couleurs = getCouleursAssociation($pdo, $_SESSION['id_utilisateur']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Adhérent</title>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/ficheAdherent.js"></script>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../css/styleGlobal.css">
</head>
<body>

<nav class="navbar shadow-sm py-3 px-4"
     style="background: linear-gradient(to right, <?= $couleurs['couleur_principale_1'] ?>, <?= $couleurs['couleur_principale_2'] ?>, <?= $couleurs['couleur_principale_3'] ?>);">
    <div class="container-fluid d-flex align-items-center">
        <div class="me-3">
            <a href="gestion_adherents.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                <i class="fa-solid fa-arrow-left menu-item"></i>
            </a>
        </div>
        <div class="flex-grow-1">
            <h5 class="m-0 fw-bold">Modifier : <?= htmlspecialchars($adherent['prenom'] . ' ' . $adherent['nom']) ?></h5>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i> Les modifications ont été enregistrées avec succès.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> Une erreur est survenue : <?= htmlspecialchars($_GET['msg'] ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formModif" action="../../traitement/traitement_admin_modif_adherent.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_utilisateur" value="<?= $id_user_cible ?>">
        <input type="hidden" name="ancien_email" value="<?= htmlspecialchars($adherent['email']) ?>">

        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="row g-3">

                    <?php foreach ($elementsFormulaire as $element): ?>

                        <?php if ($element['type_element'] === 'section'): ?>
                            <div class="col-12 mt-4 mb-2">
                                <h6 class="fw-bold text-primary border-bottom pb-2">
                                    <i class="fa-solid fa-layer-group me-2"></i>
                                    <?= htmlspecialchars($element['section_label'] ?? 'Section') ?>
                                </h6>
                            </div>

                        <?php elseif ($element['type_element'] === 'champ'): ?>
                            <div class="col-md-<?= (int)$element['taille_champ'] ?: 12 ?>">
                                <label class="form-label fw-semibold">
                                    <?= htmlspecialchars($element['champ_label']) ?>
                                    <?php if ($element['obligatoire']) echo '<span class="text-danger">*</span>'; ?>
                                </label>

                                <?php
                                $valeur = $valeurs[$element['id_element']] ?? '';
                                $options = !empty($element['options_labels']) ? explode('||', $element['options_labels']) : [];

                                // Logique pour Radio & Checkbox
                                if ($element['champ_type'] === 'radio' || $element['champ_type'] === 'checkbox'):
                                    // Transforme "Tennis,Foot" en tableau pour savoir quoi cocher
                                    $valeursEnregistrees = explode(',', $valeur);

                                    foreach ($options as $option):
                                        $isChecked = '';
                                        $nameAttr = '';
                                        if ($element['champ_type'] === 'checkbox') {
                                            // Checkbox : Coché si présent dans le tableau
                                            $isChecked = in_array($option, $valeursEnregistrees) ? 'checked' : '';
                                            $nameAttr = "champs[" . $element['id_element'] . "][]";
                                        } else {
                                            // Radio : Coché si égal
                                            $isChecked = ($valeur === $option) ? 'checked' : '';
                                            $nameAttr = "champs[" . $element['id_element'] . "]";
                                        }
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="<?= $element['champ_type'] ?>"
                                                   name="<?= $nameAttr ?>"
                                                   value="<?= htmlspecialchars($option) ?>"
                                                    <?= $isChecked ?>>
                                            <label class="form-check-label"><?= htmlspecialchars($option) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <input type="<?= htmlspecialchars($element['champ_type']) ?>"
                                           class="form-control"
                                           name="champs[<?= $element['id_element'] ?>]"
                                           value="<?= htmlspecialchars($valeur) ?>"
                                            <?= $element['obligatoire'] ? 'required' : '' ?>>
                                <?php endif; ?>
                            </div>


                        <?php
                        // Logique pour l'upload de fichiers
                        elseif ($element['type_element'] === 'fichier'): ?>
                            <?php
                            $id_f = $element['id_fichier'];
                            // On récupère le doc s'il existe
                            $docActuel = $docsEnvoyes[$id_f] ?? null;
                            // Détermination du statut (pour l'affichage du badge comme sur la fiche adhérent)
                            $statut = $docActuel ? ($docActuel['statut'] ?? 'attente') : 'manquant';

                            $tailleMo = round($element['taille_fichier'] / (1024 * 1024), 2);

                            // Définition de l'attribut 'accept' (pdf, image...)
                            $formatBdd = strtolower($element['fichier_format']);
                            $accept = '.' . $formatBdd;
                            if ($formatBdd === 'image') $accept = '.jpg,.jpeg,.png';
                            ?>
                            <div class="col-md-12">
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-bold m-0">
                                                <i class="fa-solid fa-file-signature me-2"></i>
                                                <?= htmlspecialchars($element['fichier_nom']) ?>
                                                <small class="text-muted fw-normal" style="font-size: 0.85em;">
                                                    (<?= htmlspecialchars(strtoupper($formatBdd)) ?> max <?= $tailleMo ?> Mo)
                                                </small>
                                            </h6>

                                            <?php if ($docActuel): ?>
                                                <span class="badge <?= $statut === 'valide' ? 'bg-success' : ($statut === 'attente' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                                    <?= ucfirst($statut) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Non transmis</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="upload-zone p-3 border rounded text-center bg-light"
                                             onclick="document.getElementById('input_file_<?= $id_f ?>').click()"
                                             style="cursor: pointer; border-style: dashed !important;">
                                            <i class="fa-solid fa-cloud-upload-alt fa-2x mb-2 text-primary"></i><br>
                                            <span id="text_file_<?= $id_f ?>">
                                                <?= $docActuel ? "Cliquez pour remplacer le document" : "Cliquez pour choisir le document" ?>
                                            </span>

                                            <input type="file"
                                                   id="input_file_<?= $id_f ?>"
                                                   name="documents[<?= $id_f ?>]"
                                                   class="d-none"
                                                   onchange="updateFileName(this, 'text_file_<?= $id_f ?>')"
                                                   accept="<?= htmlspecialchars($accept) ?>">
                                        </div>

                                        <?php if ($docActuel && !empty($docActuel['chemin_fichier'])): ?>
                                            <div class="mt-2 text-end">
                                                <a href="../../<?= htmlspecialchars($docActuel['chemin_fichier']) ?>" target="_blank" class="small text-decoration-none">
                                                    <i class="fa-solid fa-eye"></i> Voir le document actuel
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>

                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>

    <div class="text-end mb-5">
        <a href="gestion_adherents.php" class="btn btn-secondary me-2">Annuler</a>
        <button type="submit" form="formModif" class="btn btn-info">Enregistrer les modifications</button>
    </div>
</div>

</body>
</html>