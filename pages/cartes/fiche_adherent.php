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

// Si l'utilisateur n'est pas un adhérent, on le renvoie à l'accueil
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'adherent') {
    header("Location: ../../index.php");
    exit();
}

$id_user = $_SESSION['id_utilisateur'];

// Récupère les infos générales du formulaire de l'association
$stmtForm = $pdo->prepare("
    SELECT id_formulaire, nom, rgpd
    FROM formulaires
    WHERE id_association = 1
    LIMIT 1
");
$stmtForm->execute();
$formulaire = $stmtForm->fetch(PDO::FETCH_ASSOC);

$id_formulaire = $formulaire['id_formulaire'];
$texte_rgpd = $formulaire['rgpd'];
$nom_formulaire = $formulaire['nom'];

// Récupère la liste des fichiers que l'adhérent doit fournir
// ainsi que les paramètre (format et taille max)
$stmtTypes = $pdo->prepare("
    SELECT f.id_fichier, f.nom, f.format, f.taille_fichier
    FROM fichiers f
    JOIN formulaires_elements_fichiers fef ON f.id_fichier = fef.id_fichier
    JOIN formulaires_elements fe ON fef.id_element = fe.id_element
    WHERE fe.id_formulaire = ?
    ORDER BY fe.ordre
");
$stmtTypes->execute([$id_formulaire]);
$documentsAttendus = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

// Récupère les infos du compte utilisateur (Nom, Prénom, Email de connexion)
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
$stmt->execute([$id_user]);
$adherent = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupère les fichiers que l'adhérent a déjà envoyés (pour afficher le statut : validé/attente)
$stmtDocsEnvoyes = $pdo->prepare("
    SELECT id_fichier, statut, chemin_fichier
    FROM fichiers_utilisateur
    WHERE id_utilisateur = ?
");
$stmtDocsEnvoyes->execute([$id_user]);
// FETCH_UNIQUE permet d'utiliser l'id_fichier comme clé du tableau pour un accès facile
$docsEnvoyes = $stmtDocsEnvoyes->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

// Cette requête récupère tous les éléments du formulaire (sections + champs)
// GROUP_CONCAT rassemble les options (ex : "a, b, c") dans une seule ligne.
$stmtElements = $pdo->prepare("
    SELECT 
        fe.id_element, fe.type_element, fe.obligatoire, fe.ordre,
        c.id_champ, c.label AS champ_label, c.placeholder, c.type AS champ_type, c.taille_champ,
        s.label AS section_label,
        GROUP_CONCAT(o.label ORDER BY o.id_options SEPARATOR '||') as options_labels
    FROM formulaires_elements fe
    LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
    LEFT JOIN champs c ON fec.id_champ = c.id_champ
    LEFT JOIN formulaires_elements_sections fes ON fe.id_element = fes.id_element
    LEFT JOIN sections s ON fes.id_section = s.id_section
    LEFT JOIN options o ON c.id_champ = o.id_champ
    WHERE fe.id_formulaire = ?
    GROUP BY fe.id_element, fe.type_element, fe.obligatoire, fe.ordre, c.id_champ, c.label, c.placeholder, c.type, c.taille_champ, s.label
    ORDER BY fe.ordre
");
$stmtElements->execute([$id_formulaire]);
$elementsFormulaire = $stmtElements->fetchAll(PDO::FETCH_ASSOC);

// Vérifie rapidement s'il y a des fichiers demandés
$formulaireContientFichier = false;
foreach ($elementsFormulaire as $element) {
    if ($element['type_element'] === 'fichier') {
        $formulaireContientFichier = true;
        break;
    }
}

// Récupère ce que l'adhérent a déjà rempli dans le formulaire
$stmtValeurs = $pdo->prepare("
    SELECT id_element, valeur
    FROM adherent_champs_valeurs
    WHERE id_utilisateur = ?
");
$stmtValeurs->execute([$id_user]);

$valeurs = [];
foreach ($stmtValeurs->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $valeurs[$v['id_element']] = $v['valeur'];
}

// Si un champ est vide, mais correspond au Nom/Prénom/Email du compte, on le remplit automatiquement
foreach ($elementsFormulaire as $element) {
    if ($element['type_element'] === 'champ' && !is_null($element['champ_label'])) {
        if (empty($valeurs[$element['id_element']])) {
            $label = mb_strtolower($element['champ_label']);
            if ($label === 'nom') $valeurs[$element['id_element']] = $adherent['nom'] ?? '';
            elseif ($label === 'prenom' || $label === 'prénom') $valeurs[$element['id_element']] = $adherent['prenom'] ?? '';
            elseif ($label === 'email' || $label === 'adresse mail') $valeurs[$element['id_element']] = $adherent['email'] ?? '';
            elseif ($label === 'telephone' || $label === 'téléphone') $valeurs[$element['id_element']] = $adherent['telephone'] ?? '';
        }
    }
}

// Infos visuelles de l'association (Logo, Nom)
$stmtAssoc = $pdo->prepare("SELECT * FROM associations LIMIT 1");
$stmtAssoc->execute();
$association = $stmtAssoc->fetch(PDO::FETCH_ASSOC);
$nom_association = $association['nom'] ?? '';
$logo = $association['logo'] ?? '';

// Récupération des couleurs personnalisées
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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ma fiche adhérent</title>

        <!-- CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="../../css/styleGlobal.css">

        <!-- JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../../js/ficheAdherent.js"></script>
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
                    <h5 class="m-0 fw-bold">Ma fiche adhérent</h5>
                </div>

                <div class="d-flex gap-2">
                    <a href="fiche_adherent_pdf.php" class="btn btn-light"> <i class="fa-solid fa-download"></i>&nbsp PDF</a>
                    <button type="submit" name="enregistrer" form="ficheAdherent" class="btn btn-primary"> <i class="fas fa-save"></i>&nbsp Enregistrer</button>
                </div>

            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="container mt-4 mb-5">

            <!-- Messages de statut -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i>
                        Vos informations ont été mises à jour avec succès.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($_GET['status'] === 'error'): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <strong>Erreur :</strong>
                        <ul class="mb-0 mt-2">
                            <?php
                            // Affiche la liste précise des erreurs stockées en session
                            if (isset($_SESSION['erreurs_fiche'])) {
                                foreach ($_SESSION['erreurs_fiche'] as $erreur) {
                                    echo "<li>" . htmlspecialchars($erreur) . "</li>";
                                }
                                unset($_SESSION['erreurs_fiche']); // On vide les erreurs après affichage
                            } else {
                                echo "<li>Une erreur inconnue est survenue.</li>";
                            }
                            ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- En-tête association -->
            <div class="text-center mb-4">
                <h3 class="fw-bold mb-2"><?= htmlspecialchars($nom_association) ?></h3>
                <?php if ($logo): ?>
                    <img src="../../<?= htmlspecialchars($logo) ?>" alt="Logo de l'association" style="height: 80px; width: auto; object-fit: contain;">
                <?php endif; ?>
            </div>

            <!-- Formulaire adhérent -->
            <form id="ficheAdherent" action="../../traitement/traitement_fiche_adherent.php" method="POST" enctype="multipart/form-data">
                <h4 class="mb-3 text-secondary"><?= htmlspecialchars($nom_formulaire) ?></h4>
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">

                            <?php foreach ($elementsFormulaire as $element): ?>

                                <?php if ($element['type_element'] === 'section'): ?>
                                    <div class="col-12 mt-4 mb-2">
                                        <h5 class="fw-bold text-primary border-bottom pb-2">
                                            <i class="fa-solid fa-layer-group me-2"></i>
                                            <?= htmlspecialchars($element['section_label'] ?? 'Section sans titre') ?>
                                        </h5>
                                    </div>

                                <?php elseif ($element['type_element'] === 'champ'): ?>
                                    <div class="col-md-<?= (int)$element['taille_champ'] ?: 12 ?>">
                                        <label class="form-label fw-semibold">
                                            <?= htmlspecialchars($element['champ_label']) ?>
                                            <?= $element['obligatoire'] ? '<span class="text-danger">*</span>' : '' ?>
                                        </label>

                                        <?php
                                        $valeurEnregistree = $valeurs[$element['id_element']] ?? '';
                                        $options = !empty($element['options_labels']) ? explode('||', $element['options_labels']) : [];

                                        // Logique pour Radio & Checkbox
                                        if ($element['champ_type'] === 'radio' || $element['champ_type'] === 'checkbox'):
                                            // Si on a plusieurs valeurs (ex: "Sport,Musique"), on les transforme en tableau
                                            $valeursArray = explode(',', $valeurEnregistree);

                                            foreach ($options as $option):
                                                $isChecked = '';
                                                $nameAttr = '';

                                                if ($element['champ_type'] === 'checkbox') {
                                                    // Checkbox : Coché si l'option est dans le tableau
                                                    // Le nom doit finir par [] pour gérer plusieurs choix en PHP
                                                    $isChecked = in_array($option, $valeursArray) ? 'checked' : '';
                                                    $nameAttr = "champs[" . $element['id_element'] . "][]";
                                                } else {
                                                    // Radio : Coché si égalité stricte
                                                    $isChecked = ($valeurEnregistree === $option) ? 'checked' : '';
                                                    $nameAttr = "champs[" . $element['id_element'] . "]";
                                                }
                                                ?>
                                                <div class="form-check">
                                                    <input class="form-check-input"
                                                           type="<?= $element['champ_type'] ?>"
                                                           name="<?= $nameAttr ?>"
                                                           value="<?= htmlspecialchars($option) ?>"
                                                            <?= $isChecked ?>
                                                            <?= ($element['obligatoire'] && $element['champ_type'] === 'radio') ? 'required' : '' ?>>
                                                    <label class="form-check-label"><?= htmlspecialchars($option) ?></label>
                                                </div>
                                            <?php endforeach; ?>

                                        <?php else: // Champs classiques (Text, Date, Number...) ?>
                                            <input
                                                    type="<?= htmlspecialchars($element['champ_type']) ?>"
                                                    name="champs[<?= $element['id_element'] ?>]"
                                                    class="form-control"
                                                    placeholder="<?= htmlspecialchars($element['placeholder']) ?>"
                                                    value="<?= htmlspecialchars($valeurEnregistree) ?>"
                                                    <?= $element['obligatoire'] ? 'required' : '' ?>
                                            >
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Justificatifs -->
                <?php if ($formulaireContientFichier || !empty($documentsAttendus)): ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="mb-3 text-secondary">Mes justificatifs</h5>

                            <?php if (!empty($documentsAttendus)): ?>
                                <?php foreach ($documentsAttendus as $doc):
                                    $id_f = $doc['id_fichier'];
                                    $dejaEnvoye = isset($docsEnvoyes[$id_f]);
                                    $statut = $dejaEnvoye ? $docsEnvoyes[$id_f]['statut'] : 'manquant';

                                    // Calcul taille en Mo et définition de l'attribut 'accept' pour l'input file
                                    $tailleMo = round($doc['taille_fichier'] / (1024 * 1024), 2);
                                    $formatBdd = strtolower($doc['format']);
                                    $accept = '.' . $formatBdd;
                                    if ($formatBdd === 'image') $accept = '.jpg,.jpeg,.png';
                                    ?>
                                    <div class="card mb-3 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="fw-bold m-0">
                                                    <i class="fa-solid fa-file-signature me-2"></i>
                                                    <?= htmlspecialchars($doc['nom']) ?>
                                                    <small class="text-muted fw-normal" style="font-size: 0.85em;">
                                                        (<?= htmlspecialchars(strtoupper($formatBdd)) ?> max <?= $tailleMo ?> Mo)
                                                    </small>
                                                </h6>

                                                <!-- Permet de bien conjuguée les mots pour les badges -->
                                                <?php if ($dejaEnvoye):
                                                if ($statut === 'valide'):
                                                    $statut = 'validé';
                                                endif;
                                                if ($statut === 'refuse'):
                                                    $statut = 'refusé';
                                                endif; ?>

                                                    <span class="badge <?= $statut === 'validé' ? 'bg-success' : ($statut === 'attente' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                                            <?= ucfirst($statut) ?>
                                                        </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non transmis</span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($statut !== 'validé'): ?>
                                                <div class="upload-zone p-3 border rounded text-center bg-light"
                                                     onclick="document.getElementById('input_file_<?= $id_f ?>').click()"
                                                     style="cursor: pointer; border-style: dashed !important;">
                                                    <i class="fa-solid fa-cloud-upload-alt fa-2x mb-2 text-primary"></i><br>
                                                    <span id="text_file_<?= $id_f ?>">Cliquez pour choisir le document</span>

                                                    <input type="file"
                                                           id="input_file_<?= $id_f ?>"
                                                           name="documents[<?= $id_f ?>]"
                                                           class="d-none"
                                                           onchange="updateFileName(this, 'text_file_<?= $id_f ?>')"
                                                           accept="<?= htmlspecialchars($accept) ?>">
                                                </div>

                                                <?php if ($dejaEnvoye && !empty($docsEnvoyes[$id_f]['chemin_fichier'])): ?>
                                                    <div class="mt-2 text-end">
                                                        <a href="../../<?= htmlspecialchars($docsEnvoyes[$id_f]['chemin_fichier']) ?>" target="_blank" class="small text-decoration-none">
                                                            <i class="fa-solid fa-eye"></i> Voir le document envoyé
                                                        </a>
                                                    </div>
                                                <?php endif; ?>

                                            <?php else: ?>
                                                <div class="alert alert-light border m-0 py-2 d-flex justify-content-between align-items-center">
                                                    <span><i class="fa-solid fa-check-circle text-success me-2"></i> Document validé.</span>
                                                    <a href="../../<?= htmlspecialchars($docsEnvoyes[$id_f]['chemin_fichier']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                        <i class="fa-solid fa-eye"></i> Voir
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Aucun justificatif n'est requis pour ce formulaire.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- RGPD -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            <?= nl2br(htmlspecialchars($texte_rgpd)) ?>
                        </p>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rgpd" required>
                            <label class="form-check-label" for="rgpd">
                                J'accepte la politique de confidentialité *
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Infos compte -->
                <p class="text-muted mt-3 small">
                    Compte créé le : <?= date('d/m/Y', strtotime($adherent['date_creation'])) ?>
                </p>
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

    </body>
</html>