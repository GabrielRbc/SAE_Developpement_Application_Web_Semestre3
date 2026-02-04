<?php

session_start();

require "../../fonctions/fonctionsConnexion.php";
require "../../fonctions/fonctionsPersonnalisationFormulaire.php";

$message_success = null;
$message_erreur = null;

$libellesTypes = [
        'text'     => 'Texte court',
        'email'    => 'Email',
        'tel'      => 'Téléphone',
        'date'     => 'Date',
        'textarea' => 'Texte long',
        'number'   => 'Nombre',
        'radio'    => 'Boutons radio',
        'checkbox' => 'Cases à cocher',
        'file'     => 'Fichier'
];

$champsParDefaut = [
        'nom'     => ['Label' => 'Nom', 'Type' => 'text', 'Placeholder' => 'Ex: Dupont', 'Taille' => 6, 'Obligatoire' => true, 'Section' => 'Identité', 'Options' => []],
        'prenom'  => ['Label' => 'Prénom', 'Type' => 'text', 'Placeholder' => 'Ex: Jean', 'Taille' => 6, 'Obligatoire' => true, 'Section' => 'Identité', 'Options' => []],
        'email'   => ['Label' => 'Email', 'Type' => 'email', 'Placeholder' => 'exemple@domaine.com', 'Taille' => 12, 'Obligatoire' => true, 'Section' => 'Contact', 'Options' => []],
];

/* INITIALISATION */
if (!isset($_SESSION['liste_champ'])) {
    require "../../fonctions/verifDroit.php";
    try {
        $pdo = connexionBDDUser();
        verifierAccesDynamique($pdo);

        $idAssoc = getAssociationIdByUser($pdo, $_SESSION['id_utilisateur']);
        $idFormulaire = getDernierFormulaireIdByAssociation($pdo, $idAssoc);

        $form = getFormulaire($pdo, $idFormulaire);
        $_SESSION['titre_formulaire'] = $form['nom'];
        $_SESSION['rgpd'] = $form['rgpd'];

        $_SESSION['liste_champ'] = renvoieListeChamp($pdo, $idFormulaire);

        $_SESSION['sections'] = renvoieListeSections($pdo, $idFormulaire);
        if (!in_array('', array_column($_SESSION['sections'], 'label'))) {
            array_unshift($_SESSION['sections'], ['label' => '']);
        }

    } catch (Exception $e) {
        session_destroy();
        header("Location: ../../index.php");
        exit();
    }
}

// Calcul des compteurs
$nbChampsActuels = count($_SESSION['liste_champ'] ?? []);
$nbFichiersActuels = 0;
foreach(($_SESSION['liste_champ'] ?? []) as $c) { if($c['Type'] === 'file') $nbFichiersActuels++; }

// Couleurs
if (!isset($_SESSION['couleur_principale_1'], $_SESSION['couleur_principale_2'])) {
    require "../../fonctions/fonctionsGestionCouleur.php";
    try {
        $pdo = connexionBDDUser();
        $couleurs = getCouleursAssociation($pdo, $_SESSION['id_utilisateur']);
        if ($couleurs) {
            $_SESSION['couleur_principale_1'] = $couleurs['couleur_principale_1'];
            $_SESSION['couleur_principale_2'] = $couleurs['couleur_principale_2'];
            $_SESSION['couleur_principale_3'] = $couleurs['couleur_principale_3'];
        } else {
            $_SESSION['couleur_principale_1'] = '#000000'; $_SESSION['couleur_principale_2'] = '#000000'; $_SESSION['couleur_principale_3'] = '#000000';
        }
    } catch (Exception $e) {}
}

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['role'] != 'responsable' || $_SESSION['idSession'] != session_id()) {
    header("Location: ../../index.php"); exit();
}

if (isset($_POST['save_titre'])) $_SESSION['titre_formulaire'] = $_POST['titre_formulaire'];
if (isset($_POST['save_rgpd'])) $_SESSION['rgpd'] = $_POST['rgpd'];

if (isset($_POST['enregistrer_tout'])) {
    $titre = $_POST['titre_formulaire'] ?? $_SESSION['titre_formulaire'] ?? '';
    $rgpd = $_POST['rgpd'] ?? $_SESSION['rgpd'] ?? '';
    $pdo = connexionBDDUser();
    $liste_a_sauvegarder = [];
    $sectionsExistantes = $_SESSION['sections'] ?? [];

    $idAssoc = getAssociationIdByUser($pdo, $_SESSION['id_utilisateur']);
    $idFormulaire = getDernierFormulaireIdByAssociation($pdo, $idAssoc);

    foreach ($sectionsExistantes as $sec) {
        $labelSec = $sec['label'];
        if ($labelSec !== '') $liste_a_sauvegarder[] = ['Label' => $labelSec, 'Type' => 'section'];
        foreach ($_SESSION['liste_champ'] as $champ) {
            if ((string)$champ['Section'] === (string)$labelSec) $liste_a_sauvegarder[] = $champ;
        }
    }
    $resultat = sauvegarderTousLesChamps($pdo, $liste_a_sauvegarder, $idFormulaire, $titre, $rgpd);
    if ($resultat === true) {
        unset($_SESSION['liste_champ']);
        header("Location: personnaliser_formulaire.php?success=1"); exit;
    } else {
        $message_erreur = $resultat;
    }
}

/* GESTION DES ACTIONS  */
$action = $_GET['action'] ?? null;
$idx = isset($_GET['index']) ? (int)$_GET['index'] : null;

if ($action === 'cancel') {
    unset($_SESSION['edit_index'], $_SESSION['edit_mode']);
    header("Location: personnaliser_formulaire.php"); exit;
}

if ($action === 'delete' && $idx !== null && isset($_SESSION['liste_champ'][$idx])) {
    $champCible = $_SESSION['liste_champ'][$idx];
    $id_element = $champCible['id_element'] ?? null;
    if ($id_element) {
        $pdoLocal = connexionBDDUser();
        if (estElementUtilise($pdoLocal, $id_element)) {
            $message_erreur = "Le champ '<strong>".htmlspecialchars($champCible['Label'])."</strong>' ne peut pas être supprimé car des adhérents y ont déjà répondu.";
        } else {
            array_splice($_SESSION['liste_champ'], $idx, 1);
            header("Location: personnaliser_formulaire.php"); exit;
        }
    } else {
        array_splice($_SESSION['liste_champ'], $idx, 1);
        header("Location: personnaliser_formulaire.php"); exit;
    }
}

if ($action === 'do_delete_section' && isset($_GET['name'])) {
    $nomSup = $_GET['name'];
    $_SESSION['sections'] = array_filter($_SESSION['sections'], function($s) use ($nomSup) { return $s['label'] !== $nomSup; });
    $_SESSION['sections'] = array_values($_SESSION['sections']);
    foreach ($_SESSION['liste_champ'] as $k => $c) { if ($c['Section'] === $nomSup) $_SESSION['liste_champ'][$k]['Section'] = ''; }
    header("Location: personnaliser_formulaire.php?action=manage_sections"); exit;
}

if ($action === 'del_opt' && $idx !== null && isset($_GET['oidx'])) {
    unset($_SESSION['liste_champ'][$idx]['Options'][(int)$_GET['oidx']]);
    $_SESSION['liste_champ'][$idx]['Options'] = array_values($_SESSION['liste_champ'][$idx]['Options']);
    header("Location: personnaliser_formulaire.php"); exit;
}

if ($action === 'move_section' && isset($_GET['dir'], $_GET['index'])) {
    $i = (int)$_GET['index'];
    if ($_GET['dir'] === 'up' && $i > 0) { $tmp = $_SESSION['sections'][$i-1]; $_SESSION['sections'][$i-1] = $_SESSION['sections'][$i]; $_SESSION['sections'][$i] = $tmp; }
    elseif ($_GET['dir'] === 'down' && $i < count($_SESSION['sections']) - 1) { $tmp = $_SESSION['sections'][$i+1]; $_SESSION['sections'][$i+1] = $_SESSION['sections'][$i]; $_SESSION['sections'][$i] = $tmp; }
    header("Location: personnaliser_formulaire.php?action=manage_sections"); exit;
}

if ($action === 'edit' && $idx !== null) { $_SESSION['edit_index'] = $idx; $_SESSION['edit_mode'] = $_GET['mode'] ?? 'general'; }
if ($action === 'add_default' && isset($_GET['id_default'])) {
    if ($nbChampsActuels >= 30) {
        $message_erreur = "Impossible d'ajouter le modèle : la limite de 30 champs est atteinte.";
    } else {
        if (isset($champsParDefaut[$_GET['id_default']])) $_SESSION['liste_champ'][] = $champsParDefaut[$_GET['id_default']];
        header("Location: personnaliser_formulaire.php"); exit;
    }
}

if (isset($_GET['move'], $_GET['index']) && $action !== 'move_section') {
    $i = (int)$_GET['index'];
    if ($_GET['move'] === 'up' && $i > 0) { $tmp = $_SESSION['liste_champ'][$i-1]; $_SESSION['liste_champ'][$i-1] = $_SESSION['liste_champ'][$i]; $_SESSION['liste_champ'][$i] = $tmp; }
    elseif ($_GET['move'] === 'down' && $i < count($_SESSION['liste_champ']) - 1) { $tmp = $_SESSION['liste_champ'][$i+1]; $_SESSION['liste_champ'][$i+1] = $_SESSION['liste_champ'][$i]; $_SESSION['liste_champ'][$i] = $tmp; }
    header("Location: personnaliser_formulaire.php"); exit;
}

/* TRAITEMENT POST */
if (isset($_POST['btn_valider_nouvelle_section'])) {
    $nom = trim($_POST['nom_nouvelle_section']);
    if (!empty($nom) && !in_array($nom, array_column($_SESSION['sections'], 'label'))) $_SESSION['sections'][] = ['label' => $nom];
    header("Location: personnaliser_formulaire.php"); exit;
}

if (isset($_POST['btn_valider_champ'])) {
    $idx_edit = $_POST['edit_index'];
    $mode = $_POST['edit_mode'] ?? 'general';
    $nouveauType = $_POST['type'] ?? '';

    // Vérification des limites lors de la création ou changement de type
    $isNew = ($idx_edit === '');
    $isChangingToFile = ($nouveauType === 'file' && ($isNew || $_SESSION['liste_champ'][(int)$idx_edit]['Type'] !== 'file'));

    if ($isNew && $nbChampsActuels >= 30) {
        $message_erreur = "Limite de 30 champs atteinte.";
    } elseif ($isChangingToFile && $nbFichiersActuels >= 10) {
        $message_erreur = "Limite de 10 fichiers atteinte.";
    } else {
        if (!$isNew) {
            $idx_edit = (int)$idx_edit;
            $champ = $_SESSION['liste_champ'][$idx_edit];
            if ($mode === 'file') {
                $champ['Format'] = $_POST['file_format'] ?? 'pdf';
                $champ['MaxFileSize'] = (int)($_POST['file_size'] ?? 800000);
            } else {
                $champ['Label'] = $_POST['label'];
                $champ['Type'] = $_POST['type'];
                $champ['Placeholder'] = $_POST['placeholder'];
                $champ['Taille'] = (int)$_POST['taille'];
                $champ['Section'] = $_POST['section_choisie'];
                $champ['Obligatoire'] = isset($_POST['obligatoire']);
            }
            $_SESSION['liste_champ'][$idx_edit] = $champ;
        } else {
            $_SESSION['liste_champ'][] = [
                    'id_element' => null, 'Label' => $_POST['label'], 'Type' => $_POST['type'], 'Placeholder' => $_POST['placeholder'],
                    'Taille' => (int)$_POST['taille'], 'Section' => $_POST['section_choisie'], 'Obligatoire' => isset($_POST['obligatoire']),
                    'Options' => [], 'Format' => $_POST['file_format'] ?? 'pdf', 'MaxFileSize' => (int)($_POST['file_size'] ?? 800000)
            ];
        }
        unset($_SESSION['edit_index'], $_SESSION['edit_mode']);
        header("Location: personnaliser_formulaire.php"); exit;
    }
}

if (isset($_POST['btn_valider_nouvelle_option'])) {
    $idx_c = (int)$_POST['idx_champ_for_option'];
    if (!empty($_POST['nom_option'])) $_SESSION['liste_champ'][$idx_c]['Options'][] = trim($_POST['nom_option']);
    header("Location: personnaliser_formulaire.php"); exit;
}

/* VARIABLES VUE */
if(isset($_GET['success'])) $message_success = "Enregistrement complet réussi.";
$titre_formulaire = $_SESSION['titre_formulaire'] ?? '';
$rgpd_text = $_SESSION['rgpd'] ?? '';
$edit_index = $_SESSION['edit_index'] ?? null;
$edit_mode = $_SESSION['edit_mode'] ?? 'general';
$mode_gerer_sections = ($action === 'manage_sections');
$mode_ajouter_section = ($action === 'add_section');
$mode_editer_champ = ($action === 'ajouter' || $edit_index !== null);
$mode_ajouter_option = ($action === 'add_opt_list' && $idx !== null);
$data_field = ($edit_index !== null) ? $_SESSION['liste_champ'][$edit_index] : null;
$toutesLesSections = $_SESSION['sections'] ?? [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Edition Formulaire</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="../../css/styleGlobal.css" rel="stylesheet">
</head>
<body class="bg-light">

<form method="POST">
    <nav class="navbar shadow-sm py-3 px-4" style="background: linear-gradient(to right, <?php echo $_SESSION['couleur_principale_1']; ?>, <?php echo $_SESSION['couleur_principale_2']; ?>, <?php echo $_SESSION['couleur_principale_3']; ?>);">
        <div class="container-fluid d-flex align-items-center">
            <div class="me-3">
                <a href="../tableauDeBord.php" class="me-2 d-flex align-items-center text-decoration-none text-dark">
                    <i class="fa-solid fa-arrow-left menu-item"></i>
                </a>
            </div>
            <div class="flex-grow-1"><h5 class="m-0 fw-bold">Edition formulaire</h5></div>
            <div class="d-flex gap-2">
                <a href="apercu_formulaire.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye me-1"></i>Aperçu</a>
                <button type="submit" name="enregistrer_tout" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer tout</button>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="d-flex justify-content-end mb-3 gap-3">
            <span class="badge <?php echo ($nbChampsActuels >= 30) ? 'bg-danger' : 'bg-dark'; ?> p-2 shadow-sm">
                <i class="fa-solid fa-list-check me-1"></i> Champs : <?php echo $nbChampsActuels; ?> / 30
            </span>
            <span class="badge <?php echo ($nbFichiersActuels >= 10) ? 'bg-danger' : 'bg-dark'; ?> p-2 shadow-sm">
                <i class="fa-solid fa-file-arrow-up me-1"></i> Fichiers : <?php echo $nbFichiersActuels; ?> / 10
            </span>
        </div>

        <?php if ($message_success): ?><div class="alert alert-success"><?php echo $message_success; ?></div><?php endif; ?>
        <?php if ($message_erreur): ?><div class="alert alert-danger alert-dismissible fade show">
            <?php echo $message_erreur; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div><?php endif; ?>

        <div class="info mb-4">
            <label class="form-label fw-bold small">Titre du formulaire</label>
            <div class="d-flex gap-2">
                <input type="text" name="titre_formulaire" class="form-control" value="<?php echo htmlspecialchars($titre_formulaire); ?>">
                <button type="submit" name="save_titre" class="btn btn-outline-primary"><i class="fa-solid fa-floppy-disk"></i></button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <div class="info">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fw-bold">Champs du formulaire</span>
                        <div class="d-flex gap-2">
                            <a href="?action=manage_sections" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-layer-group me-1"></i>Catégories</a>
                            <a href="?action=add_section" class="btn btn-outline-secondary btn-sm">Ajouter catégorie</a>
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" <?php if($nbChampsActuels >= 30) echo 'disabled'; ?>>Modèles</button>
                                <ul class="dropdown-menu shadow">
                                    <?php foreach ($champsParDefaut as $id => $d): ?>
                                        <li><a class="dropdown-item" href="?action=add_default&id_default=<?php echo $id; ?>"><?php echo $d['Label']; ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="?action=ajouter" class="btn btn-primary btn-sm <?php if($nbChampsActuels >= 30) echo 'disabled'; ?>">Créer champ</a>
                        </div>
                    </div>

                    <?php foreach ($toutesLesSections as $sec):
                        $labelSec = $sec['label'] ?? '';
                        $champsSec = array_filter($_SESSION['liste_champ'], function ($c) use ($labelSec) { return (string)$c['Section'] === (string)$labelSec; });
                        if (empty($champsSec)) continue; ?>

                        <div class="section-header">
                            <i class="fa-solid fa-folder me-2"></i>
                            <?php echo $labelSec === '' ? 'AUCUNE CATÉGORIE' : mb_strtoupper(htmlspecialchars($labelSec)); ?>
                        </div>

                        <?php foreach ($champsSec as $realIdx => $champ): ?>
                        <div class="border rounded p-3 bg-white mb-2 position-relative shadow-sm">
                            <div class="position-absolute top-0 end-0 p-2 d-flex gap-3">
                                <a href="?move=up&index=<?php echo $realIdx; ?>" class="text-dark"><i class="fa-solid fa-arrow-up"></i></a>
                                <a href="?move=down&index=<?php echo $realIdx; ?>" class="text-dark"><i class="fa-solid fa-arrow-down"></i></a>
                                <a href="?action=edit&index=<?php echo $realIdx; ?>&mode=general" class="text-primary"><i class="fa-solid fa-pen"></i></a>
                                <?php if ($champ['Type'] === 'file'): ?>
                                    <a href="?action=edit&index=<?php echo $realIdx; ?>&mode=file" class="text-secondary"><i class="fa-solid fa-gear"></i></a>
                                <?php endif; ?>
                                <a href="?action=delete&index=<?php echo $realIdx; ?>" class="text-danger" onclick="return confirm('Supprimer ce champ ?');"><i class="fa-solid fa-trash"></i></a>
                            </div>
                            <div class="fw-bold small d-flex align-items-center">
                                <span><?php echo htmlspecialchars($champ['Label']); ?></span>
                                <?php if (!empty($champ['Obligatoire'])): ?><span class="badge rounded-pill bg-danger ms-2" style="font-size: 10px;">Obligatoire</span><?php endif; ?>
                            </div>
                            <div class="text-muted small">
                                Type : <strong><?php echo $libellesTypes[$champ['Type']] ?? $champ['Type']; ?></strong>
                            </div>

                            <?php if (in_array($champ['Type'], ['radio', 'checkbox', 'select'])): ?>
                                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                                    <?php foreach ($champ['Options'] as $o_idx => $o_label): ?>
                                        <span class="badge-option"><?php echo htmlspecialchars($o_label); ?><a href="?action=del_opt&index=<?php echo $realIdx; ?>&oidx=<?php echo $o_idx; ?>" class="text-danger">×</a></span>
                                    <?php endforeach; ?>
                                    <a href="?action=add_opt_list&index=<?php echo $realIdx; ?>" class="btn btn-xs btn-outline-primary">+ Option</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-4">
                <?php if ($mode_gerer_sections): ?>
                    <div class="info border-primary">
                        <h6 class="fw-bold mb-3">Ordre des catégories</h6>
                        <div class="list-group mb-3">
                            <?php foreach ($toutesLesSections as $s_idx => $sec): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="small"><?php echo $sec['label'] === '' ? '<em>Aucune</em>' : htmlspecialchars($sec['label']); ?></span>
                                    <div class="d-flex gap-2">
                                        <a href="?action=move_section&dir=up&index=<?php echo $s_idx; ?>" class="text-dark"><i class="fa-solid fa-chevron-up"></i></a>
                                        <a href="?action=move_section&dir=down&index=<?php echo $s_idx; ?>" class="text-dark"><i class="fa-solid fa-chevron-down"></i></a>
                                        <?php if ($sec['label'] !== ''): ?><a href="?action=do_delete_section&name=<?php echo urlencode($sec['label']); ?>" class="text-danger"><i class="fa-solid fa-trash-can"></i></a><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="?action=cancel" class="btn btn-light btn-sm w-100">Fermer</a>
                    </div>
                <?php elseif ($mode_ajouter_section): ?>
                    <div class="info border-secondary">
                        <h6 class="fw-bold mb-3">Nouvelle catégorie</h6>
                        <input type="text" name="nom_nouvelle_section" class="form-control form-control-sm mb-2" required autofocus>
                        <button type="submit" name="btn_valider_nouvelle_section" class="btn btn-secondary btn-sm w-100">Créer</button>
                        <a href="?action=cancel" class="btn btn-link btn-sm w-100 mt-1 text-muted">Annuler</a>
                    </div>
                <?php elseif ($mode_ajouter_option): ?>
                    <div class="info border-primary">
                        <h6 class="fw-bold mb-1 text-primary">Nouvelle option</h6>
                        <p class="small text-muted mb-3">Champ : <?php echo htmlspecialchars($_SESSION['liste_champ'][$idx]['Label']); ?></p>
                        <input type="hidden" name="idx_champ_for_option" value="<?php echo $idx; ?>">
                        <input type="text" name="nom_option" class="form-control form-control-sm mb-2" placeholder="Titre de l'option" required autofocus>
                        <button type="submit" name="btn_valider_nouvelle_option" class="btn btn-primary btn-sm w-100">Ajouter</button>
                        <a href="?action=cancel" class="btn btn-link btn-sm w-100 mt-1 text-muted">Annuler</a>
                    </div>
                <?php elseif ($mode_editer_champ): ?>
                    <div class="info bg-light">
                        <h6 class="fw-bold mb-3"><?php echo ($edit_index !== null) ? ($edit_mode === 'file' ? "Paramètres fichier" : "Modifier champ") : "Nouveau champ"; ?></h6>
                        <input type="hidden" name="edit_index" value="<?php echo $edit_index ?? ''; ?>">
                        <input type="hidden" name="edit_mode" value="<?php echo $edit_mode; ?>">

                        <?php if ($edit_mode === 'file'): ?>
                            <div class="mb-2">
                                <label class="small fw-bold">Taille max (octets)</label>
                                <input type="number" name="file_size" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data_field['MaxFileSize'] ?? 800000); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Formats acceptés</label>
                                <input type="text" name="file_format" class="form-control form-control-sm" placeholder="ex: pdf,jpg,png" value="<?php echo htmlspecialchars($data_field['Format'] ?? 'pdf'); ?>">
                            </div>
                        <?php else: ?>
                            <div class="mb-2">
                                <label class="small fw-bold">Label</label>
                                <input type="text" name="label" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data_field['Label'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Catégorie</label>
                                <select name="section_choisie" class="form-select form-select-sm">
                                    <?php foreach ($toutesLesSections as $sec): ?>
                                        <option value="<?php echo htmlspecialchars($sec['label']); ?>" <?php echo (($data_field['Section'] ?? '') == $sec['label']) ? 'selected' : ''; ?>><?php echo $sec['label'] === '' ? '-- Aucune --' : htmlspecialchars($sec['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Type</label>
                                <select name="type" class="form-select form-select-sm">
                                    <?php foreach($libellesTypes as $v => $l): ?>
                                        <option value="<?php echo $v; ?>" <?php echo ($data_field['Type'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Placeholder</label>
                                <input type="text" name="placeholder" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data_field['Placeholder'] ?? ''); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold d-block">Largeur</label>
                                <input type="radio" name="taille" value="6" <?php echo ($data_field['Taille'] ?? 6) == 6 ? 'checked' : ''; ?>> 50%
                                <input type="radio" name="taille" value="12" <?php echo ($data_field['Taille'] ?? 6) == 12 ? 'checked' : ''; ?> class="ms-2"> 100%
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="obligatoire" id="obli" <?php echo !empty($data_field['Obligatoire']) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="obli">Obligatoire</label>
                            </div>
                        <?php endif; ?>

                        <button type="submit" name="btn_valider_champ" class="btn btn-success btn-sm w-100">Valider</button>
                        <a href="?action=cancel" class="btn btn-link btn-sm w-100 mt-1 text-muted">Annuler</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info mt-4">
            <label class="form-label fw-bold small">Mention RGPD</label>
            <div class="d-flex gap-2">
                <textarea name="rgpd" class="form-control" rows="4"><?php echo htmlspecialchars($rgpd_text); ?></textarea>
                <button type="submit" name="save_rgpd" class="btn btn-outline-primary"><i class="fa-solid fa-floppy-disk"></i></button>
            </div>
        </div>
    </div>
</form>

<footer class="bg-dark text-white text-center py-3">
    <p>© Adhésium - 2026</p>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>