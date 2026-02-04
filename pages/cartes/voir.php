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

// Vérifie que l'utilisateur est connecté en tant qu'adhérent
if (!isset($_SESSION['role']) || $_SESSION['role'] == 'adherent') {
    header("Location: ../../index.php");
    exit();
}

// Récupération de l'identifiant (email) depuis l'URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: gestion_adherents.php");
    exit();
}

$email_adherent = $_GET['id'];

// Récupération des informations de l'utilisateur ciblé
$stmtUser = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
$stmtUser->execute([$email_adherent]);
$adherent = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$adherent) {
    header("Location: gestion_adherents.php");
    exit();
}

$id_user_cible = $adherent['id_utilisateur'];

// Récupération des informations du formulaire
$stmtForm = $pdo->prepare("
    SELECT id_formulaire, nom, rgpd
    FROM formulaires
    WHERE id_association = 1
    LIMIT 1
");
$stmtForm->execute();
$formulaire = $stmtForm->fetch(PDO::FETCH_ASSOC);

$id_formulaire = $formulaire['id_formulaire'];
$nom_formulaire = $formulaire['nom'];

// Cette requête récupère toutes les questions, sections et options (choix multiples)
// GROUP_CONCAT sert à regrouper toutes les options d'une question sur une seule ligne (ex: "Tennis||Football")
$stmtElements = $pdo->prepare("
    SELECT 
        fe.id_element, fe.type_element, fe.ordre,
        c.id_champ, c.label AS champ_label, c.type AS champ_type, c.taille_champ,
        s.label AS section_label,
        GROUP_CONCAT(o.label ORDER BY o.id_options SEPARATOR '||') as options_labels
    FROM formulaires_elements fe
    LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
    LEFT JOIN champs c ON fec.id_champ = c.id_champ
    LEFT JOIN formulaires_elements_sections fes ON fe.id_element = fes.id_element
    LEFT JOIN sections s ON fes.id_section = s.id_section
    LEFT JOIN options o ON c.id_champ = o.id_champ
    WHERE fe.id_formulaire = ?
    GROUP BY fe.id_element, fe.type_element, fe.ordre, c.id_champ, c.label, c.type, c.taille_champ, s.label
    ORDER BY fe.ordre
");
$stmtElements->execute([$id_formulaire]);
$elementsFormulaire = $stmtElements->fetchAll(PDO::FETCH_ASSOC);

// Récupération des valeurs saisies
$stmtValeurs = $pdo->prepare("
    SELECT id_element, valeur
    FROM adherent_champs_valeurs
    WHERE id_utilisateur = ?
");
$stmtValeurs->execute([$id_user_cible]);
$valeurs = [];
foreach ($stmtValeurs->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $valeurs[$v['id_element']] = $v['valeur'];
}

// Pré-remplissage avec les infos de la table utilisateur si champs vides
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

// Liste des types de documents demandés
$stmtTypesDocs = $pdo->prepare("
    SELECT f.id_fichier, f.nom, f.format, f.taille_fichier
    FROM fichiers f
    JOIN formulaires_elements_fichiers fef ON f.id_fichier = fef.id_fichier
    JOIN formulaires_elements fe ON fef.id_element = fe.id_element
    WHERE fe.id_formulaire = ?
    ORDER BY fe.ordre
");
$stmtTypesDocs->execute([$id_formulaire]);
$documentsAttendus = $stmtTypesDocs->fetchAll(PDO::FETCH_ASSOC);

// Liste des documents réellement envoyés par l'utilisateur
$stmtDocsEnvoyes = $pdo->prepare("
    SELECT id_fichier, statut, chemin_fichier
    FROM fichiers_utilisateur
    WHERE id_utilisateur = ?
");
$stmtDocsEnvoyes->execute([$id_user_cible]);
$docsEnvoyes = $stmtDocsEnvoyes->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

// Récupération des couleurs pour le style
$couleurs = getCouleursAssociation($pdo, $_SESSION['id_utilisateur']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Visualisation Adhérent</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../css/styleGlobal.css">
</head>
    <body>

        <!-- Barre de navigation -->
        <nav class="navbar shadow-sm py-3 px-4"
             style="background: linear-gradient(to right, <?= $couleurs['couleur_principale_1'] ?>, <?= $couleurs['couleur_principale_2'] ?>, <?= $couleurs['couleur_principale_3'] ?>);">
            <div class="container-fluid d-flex align-items-center">

                <!-- Bouton retour -->
                <div class="me-3">
                    <a href="gestion_adherents.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                        <i class="fa-solid fa-arrow-left menu-item"></i>
                    </a>
                </div>

                <!-- Titre du dossier -->
                <div class="flex-grow-1">
                    <h5 class="m-0 fw-bold">Dossier : <?= htmlspecialchars($adherent['prenom'] . ' ' . $adherent['nom']) ?></h5>
                </div>
            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="container mt-4 mb-5">

            <div class="text-center mb-4">
                <h4 class="text-secondary"><?= htmlspecialchars($nom_formulaire) ?></h4>
                <span class="badge bg-info text-dark">Lecture seule</span>
            </div>

            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($elementsFormulaire as $element): ?>

                            <!-- Section -->
                            <?php if ($element['type_element'] === 'section'): ?>
                                <div class="col-12 mt-4 mb-2">
                                    <h5 class="fw-bold text-primary border-bottom pb-2">
                                        <i class="fa-solid fa-layer-group me-2"></i>
                                        <?= htmlspecialchars($element['section_label'] ?? 'Section') ?>
                                    </h5>
                                </div>

                                <!-- Champ -->
                            <?php elseif ($element['type_element'] === 'champ'): ?>
                                <div class="col-md-<?= (int)$element['taille_champ'] ?: 12 ?>">
                                    <label class="form-label fw-semibold text-muted">
                                        <?= htmlspecialchars($element['champ_label']) ?>
                                    </label>

                                    <?php
                                    $valeur = $valeurs[$element['id_element']] ?? '';
                                    // Transforme la chaine d'options "A||B" en tableau ["A", "B"]
                                    $options = !empty($element['options_labels']) ? explode('||', $element['options_labels']) : [];

                                    // --- Logique d'affichage pour checkbox et radio ---
                                    if ($element['champ_type'] === 'radio' || $element['champ_type'] === 'checkbox'):
                                        // On transforme "Sport,Musique" en tableau ["Sport", "Musique"] pour savoir quoi cocher
                                        $valeursEnregistrees = explode(',', $valeur);

                                        foreach ($options as $option):
                                            $isChecked = '';

                                            if ($element['champ_type'] === 'checkbox') {
                                                // Coche si l'option est présente dans les valeurs enregistrées
                                                $isChecked = in_array($option, $valeursEnregistrees) ? 'checked' : '';
                                            } else {
                                                // Radio : Coche si égalité exacte
                                                $isChecked = ($valeur === $option) ? 'checked' : '';
                                            }
                                            ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="<?= $element['champ_type'] ?>"
                                                       disabled <?= $isChecked ?>>
                                                <label class="form-check-label opacity-75"><?= htmlspecialchars($option) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <input type="text" class="form-control"
                                               value="<?= htmlspecialchars($valeur) ?>"
                                               disabled readonly style="background-color: #e9ecef;">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Documents joints -->
            <?php if (!empty($documentsAttendus)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold">Documents joints</h6>
                </div>

                <!-- Liste des documents -->
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($documentsAttendus as $doc):
                            $id_f = $doc['id_fichier'];
                            $envoye = isset($docsEnvoyes[$id_f]);
                            $infoDoc = $envoye ? $docsEnvoyes[$id_f] : null;
                            $statut = $envoye ? $infoDoc['statut'] : 'manquant';

                            // Calcul taille en Mo pour affichage
                            $tailleMo = round($doc['taille_fichier'] / (1024 * 1024), 2);
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center justify-content-between p-3 border rounded">
                                    <div>
                                        <i class="fa-solid fa-file me-2 text-secondary"></i>
                                        <strong><?= htmlspecialchars($doc['nom']) ?></strong>
                                        <br>
                                        <small class="text-muted" style="font-size: 0.85em;">
                                            Format : <?= htmlspecialchars(strtoupper($doc['format'])) ?> |
                                            Max : <?= $tailleMo ?> Mo
                                        </small>
                                    </div>
                                    <div>
                                        <?php if ($statut === 'valide'): ?>
                                            <span class="badge bg-success">Validé</span>
                                        <?php elseif ($statut === 'attente'): ?>
                                            <span class="badge bg-warning text-dark">En attente</span>
                                        <?php elseif ($statut === 'refuse'): ?>
                                            <span class="badge bg-danger">Refusé</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Manquant</span>
                                        <?php endif; ?>

                                        <?php if ($envoye && !empty($infoDoc['chemin_fichier'])): ?>
                                            <a href="../../<?= htmlspecialchars($infoDoc['chemin_fichier']) ?>"
                                               target="_blank"
                                               class="btn btn-sm btn-outline-primary ms-2"
                                               title="Voir le document">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Bouton retour -->
            <div class="text-end">
                <a href="gestion_adherents.php" class="btn btn-secondary">Retour à la liste</a>
            </div>

        </div>

    </body>
</html>