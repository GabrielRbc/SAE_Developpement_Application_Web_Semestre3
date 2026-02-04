<?php
session_start();
require "../fonctions/fonctionsConnexion.php";
require '../fonctions/verifDroit.php';

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
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'adherent') {
    header("Location: ../index.php");
    exit();
}

$id_user = $_SESSION['id_utilisateur'];

// On ne traite le code que si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $erreurs = [];
    $champs = $_POST['champs'] ?? [];

    // On récupère la liste des champs marqués "obligatoire" dans la base de données
    $stmtObligatoires = $pdo->prepare("
        SELECT id_element
        FROM formulaires_elements
        WHERE id_formulaire = (SELECT id_formulaire FROM formulaires WHERE id_association = 1 LIMIT 1)
        AND obligatoire = 1
    ");
    $stmtObligatoires->execute();
    $champsObligatoires = $stmtObligatoires->fetchAll(PDO::FETCH_COLUMN);

    // On boucle pour vérifier si chaque champ obligatoire a bien une valeur
    foreach ($champsObligatoires as $id_el) {
        $val = $champs[$id_el] ?? '';

        // Gestion différente si c'est un tableau (ex : cases à cocher multiples)
        if (is_array($val)) {
            if (empty($val)) {
                $erreurs[] = "Les champs avec une étoile (*) sont obligatoires.";
                break; // Arrête la boucle à la première erreur trouvée
            }
        } else {
            if (empty(trim($val))) {
                $erreurs[] = "Les champs avec une étoile (*) sont obligatoires.";
                break;
            }
        }
    }

    // --- GESTION DE L'UPLOAD DE DOCUMENT ---

    // Vérifie si des fichiers ont été envoyés
    if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        foreach ($_FILES['documents']['name'] as $id_fichier => $nom_original) {

            // On ne traite que si un fichier a été réellement envoyé sans erreur système
            if ($_FILES['documents']['error'][$id_fichier] === UPLOAD_ERR_OK) {

                $id_fichier = intval($id_fichier);

                // Récupération des contraintes définies par l'admin pour ce fichier
                $stmtRegles = $pdo->prepare("SELECT taille_fichier, format, nom FROM fichiers WHERE id_fichier = ?");
                $stmtRegles->execute([$id_fichier]);
                $regle = $stmtRegles->fetch(PDO::FETCH_ASSOC);

                if ($regle) {
                    $tmp_name = $_FILES['documents']['tmp_name'][$id_fichier];
                    $size = $_FILES['documents']['size'][$id_fichier];
                    $max_size = intval($regle['taille_fichier']);

                    // Normalisation des extensions pour comparaison
                    $format_attendu = strtolower(trim($regle['format']));
                    $file_extension = strtolower(pathinfo($nom_original, PATHINFO_EXTENSION));

                    // Logique de validation de l'extension
                    $extension_valide = false;

                    // Extension exacte
                    if ($format_attendu === $file_extension) {
                        $extension_valide = true;
                    // Le format attendu est "image", on accepte jpg, jpeg, png
                    } elseif ($format_attendu === 'image' && in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
                        $extension_valide = true;
                    // Sécurité supplémentaire pour PDF
                    } elseif ($format_attendu === 'pdf' && $file_extension === 'pdf') { // Redondant mais explicite
                        $extension_valide = true;
                    }

                    // Gestion des erreurs
                    if (!$extension_valide) {
                        $erreurs[] = "Le fichier \"" . htmlspecialchars($nom_original) . "\" doit être au format " . strtoupper($format_attendu) . ".";
                    } elseif ($size > $max_size) {
                        // Conversion pour affichage lisible
                        $max_Mo = round($max_size / (1024 * 1024), 2);
                        $erreurs[] = "Le fichier \"" . htmlspecialchars($nom_original) . "\" est trop lourd (Max : $max_Mo Mo).";
                    } else {
                        // Tout est bon, on upload
                        $upload_dir = __DIR__ . '/../uploads/documents/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                        $file_name = "doc_" . $id_user . "_" . $id_fichier . "_" . time() . "." . $file_extension;
                        $chemin_fichier = 'uploads/documents/' . $file_name;

                        // Déplace le fichier temporaire vers le dossier final
                        if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                            $stmtDoc = $pdo->prepare("
                                INSERT INTO fichiers_utilisateur (id_fichier, id_utilisateur, statut, chemin_fichier)
                                VALUES (?, ?, 'attente', ?)
                                ON DUPLICATE KEY UPDATE chemin_fichier = VALUES(chemin_fichier), statut = 'attente'
                            ");
                            $stmtDoc->execute([$id_fichier, $id_user, $chemin_fichier]);
                        } else {
                            $erreurs[] = "Erreur technique lors de l'envoi du fichier " . htmlspecialchars($nom_original);
                        }
                    }
                }
            }
        }
    }

    // Si des erreurs ont été détectées, on redirige avec les messages d'erreur
    if (!empty($erreurs)) {
        $_SESSION['erreurs_fiche'] = $erreurs;
        header("Location: ../pages/cartes/fiche_adherent.php?status=error");
        exit();
    }

    // Sauvegarde des champs textes/checkbox
    foreach ($champs as $id_element => $valeur) {
        if (is_array($valeur)) $valeur = implode(',', $valeur);
        $valeur = trim($valeur);

        // Vérification existence élément dans la BDD
        $checkElement = $pdo->prepare("SELECT COUNT(*) FROM formulaires_elements WHERE id_element = ?");
        $checkElement->execute([$id_element]);
        if ($checkElement->fetchColumn() == 0) continue;

        // Vérifie si une réponse existe déjà pour faire un UPDATE ou un INSERT
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM adherent_champs_valeurs WHERE id_utilisateur = ? AND id_element = ?");
        $stmtCheck->execute([$id_user, $id_element]);

        if ($stmtCheck->fetchColumn() > 0) {
            $stmtUpdate = $pdo->prepare("UPDATE adherent_champs_valeurs SET valeur = ? WHERE id_utilisateur = ? AND id_element = ?");
            $stmtUpdate->execute([$valeur, $id_user, $id_element]);
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO adherent_champs_valeurs (id_utilisateur, id_element, valeur) VALUES (?, ?, ?)");
            $stmtInsert->execute([$id_user, $id_element, $valeur]);
        }
    }

    // Mise à jour profil utilisateur
    $stmtLabels = $pdo->prepare("
        SELECT fe.id_element, c.label 
        FROM formulaires_elements fe
        LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
        LEFT JOIN champs c ON fec.id_champ = c.id_champ
        WHERE fe.id_formulaire = (SELECT id_formulaire FROM formulaires WHERE id_association = 1 LIMIT 1)
    ");
    $stmtLabels->execute();
    $labels = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);

    $nom = $prenom = $email = $telephone = '';

    // Associe les valeurs postées aux colonnes de la table utilisateurs
    foreach ($labels as $champ) {
        $label = mb_strtolower($champ['label']);
        $val = $champs[$champ['id_element']] ?? '';
        if (is_array($val)) $val = implode(',', $val);
        $val = trim($val);
        if ($label === 'nom') $nom = $val;
        elseif ($label === 'prenom' || $label === 'prénom') $prenom = $val;
        elseif ($label === 'email' || $label === 'adresse mail') $email = $val;
        elseif ($label === 'telephone' || $label === 'téléphone') $telephone = $val;
    }

    // Construction dynamique de la requête SQL UPDATE seulement si des données sont présentes
    if ($nom || $prenom || $email || $telephone) {
        $fields = []; $params = [];
        if ($nom) { $fields[] = "nom=?"; $params[] = $nom; }
        if ($prenom) { $fields[] = "prenom=?"; $params[] = $prenom; }
        if ($email) { $fields[] = "email=?"; $params[] = $email; }
        if ($telephone) { $fields[] = "telephone=?"; $params[] = $telephone; }
        if (!empty($fields)) {
            $params[] = $id_user;
            $pdo->prepare("UPDATE utilisateurs SET " . implode(',', $fields) . " WHERE id_utilisateur=?")->execute($params);
        }
        // Met à jour la session pour que l'affichage change immédiatement sans reconnexion
        if (!empty($nom)) $_SESSION['nom'] = $nom;
        if (!empty($prenom)) $_SESSION['prenom'] = $prenom;
    }

    header("Location: ../pages/cartes/fiche_adherent.php?status=success");
    exit();
}