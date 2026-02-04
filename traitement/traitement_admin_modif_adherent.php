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

// On refuse l'accès si l'utilisateur n'est pas connecté ou s'il est un simple adhérent
if (!isset($_SESSION['role']) || $_SESSION['role'] == 'adherent') {
    header("Location: ../index.php");
    exit();
}

// Traitement uniquement si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupération des données de base
    $id_utilisateur = isset($_POST['id_utilisateur']) ? intval($_POST['id_utilisateur']) : 0;
    $ancien_email = $_POST['ancien_email'] ?? '';
    $champs = $_POST['champs'] ?? []; // Tableau contenant toutes les réponses aux questions

    if ($id_utilisateur <= 0) {
        header("Location: ../pages/cartes/gestion_adherents.php?error=id_invalide");
        exit();
    }

    try {
        // Permet d'annuler toutes les modifications si une seule erreur survient
        $pdo->beginTransaction();

        // Sauvegarde des réponses
        foreach ($champs as $id_element => $valeur) {
            // Convertit les tableaux (checkbox) en texte séparé par des virgules
            if (is_array($valeur)) $valeur = implode(',', $valeur);
            $valeur = trim($valeur);

            // Vérifie si une réponse existe déjà pour cet utilisateur et cette question
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM adherent_champs_valeurs WHERE id_utilisateur = ? AND id_element = ?");
            $stmtCheck->execute([$id_utilisateur, $id_element]);

            // Si oui, mise à jour (UPDATE), sinon, création (INSERT)
            if ($stmtCheck->fetchColumn() > 0) {
                $stmtUpdate = $pdo->prepare("UPDATE adherent_champs_valeurs SET valeur = ? WHERE id_utilisateur = ? AND id_element = ?");
                $stmtUpdate->execute([$valeur, $id_utilisateur, $id_element]);
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO adherent_champs_valeurs (id_utilisateur, id_element, valeur) VALUES (?, ?, ?)");
                $stmtInsert->execute([$id_utilisateur, $id_element, $valeur]);
            }
        }

        // Gestion des documents
        if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
            foreach ($_FILES['documents']['name'] as $id_fichier => $nom_original) {

                // On ne traite que si le fichier a bien été reçu sans erreur technique
                if ($_FILES['documents']['error'][$id_fichier] === UPLOAD_ERR_OK) {

                    $id_fichier = intval($id_fichier);

                    // On récupère les règles (taille, format) imposées pour ce fichier spécifique
                    $stmtRegles = $pdo->prepare("SELECT taille_fichier, format FROM fichiers WHERE id_fichier = ?");
                    $stmtRegles->execute([$id_fichier]);
                    $regle = $stmtRegles->fetch(PDO::FETCH_ASSOC);

                    if ($regle) {
                        $tmp_name = $_FILES['documents']['tmp_name'][$id_fichier];
                        $size = $_FILES['documents']['size'][$id_fichier];

                        $max_size = intval($regle['taille_fichier']);
                        $format_attendu = strtolower(trim($regle['format']));
                        $file_extension = strtolower(pathinfo($nom_original, PATHINFO_EXTENSION));

                        // Vérification du format (PDF, Image, etc.)
                        $extension_valide = false;
                        if ($format_attendu === $file_extension) {
                            $extension_valide = true;
                        } elseif ($format_attendu === 'image' && in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
                            $extension_valide = true; // "Image" accepte jpg, jpeg et png
                        } elseif ($format_attendu === 'pdf' && $file_extension === 'pdf') {
                            $extension_valide = true;
                        }

                        // Si tout est ok
                        if ($extension_valide && $size <= $max_size) {

                            // Création du dossier de stockage s'il n'existe pas
                            $upload_dir = __DIR__ . '/../uploads/documents/';
                            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                            // Génération d'un nom unique pour éviter d'écraser d'autres fichiers
                            $file_name = "doc_" . $id_utilisateur . "_" . $id_fichier . "_" . time() . "." . $file_extension;
                            $chemin_fichier = 'uploads/documents/' . $file_name;

                            // Déplacement final du fichier
                            if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                                // Enregistrement en BDD.
                                // Comme c'est un admin qui upload, le statut est direct 'valide'
                                $stmtDoc = $pdo->prepare("
                                    INSERT INTO fichiers_utilisateur (id_fichier, id_utilisateur, statut, chemin_fichier)
                                    VALUES (?, ?, 'valide', ?)
                                    ON DUPLICATE KEY UPDATE chemin_fichier = VALUES(chemin_fichier), statut = 'valide'
                                ");
                                $stmtDoc->execute([$id_fichier, $id_utilisateur, $chemin_fichier]);
                            }
                        } else {
                            // En cas d'erreur (format ou taille), on annule tout via l'exception
                            throw new Exception("Le fichier " . $nom_original . " ne respecte pas le format ($format_attendu) ou la taille définie.");
                        }
                    }
                }
            }
        }

        // On récupère quels champs correspondent au Nom, Prénom, Email, Tel pour mettre à jour la table de connexion 'utilisateurs'
        $stmtLabels = $pdo->prepare("
            SELECT fe.id_element, c.label 
            FROM formulaires_elements fe
            LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
            LEFT JOIN champs c ON fec.id_champ = c.id_champ
            WHERE fe.id_formulaire = (SELECT id_formulaire FROM formulaires WHERE id_association = 1 LIMIT 1)
        ");
        $stmtLabels->execute();
        $labels = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);

        $nom = $prenom = $email = $telephone = null;

        // On cherche les valeurs correspondantes
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

        // Construction dynamique de la requête SQL de mise à jour du compte
        $fieldsToUpdate = []; $params = [];
        if ($nom !== null) { $fieldsToUpdate[] = "nom = ?"; $params[] = $nom; }
        if ($prenom !== null) { $fieldsToUpdate[] = "prenom = ?"; $params[] = $prenom; }
        if ($email !== null) { $fieldsToUpdate[] = "email = ?"; $params[] = $email; }
        if ($telephone !== null) { $fieldsToUpdate[] = "telephone = ?"; $params[] = $telephone; }

        if (!empty($fieldsToUpdate)) {
            $params[] = $id_utilisateur;
            $pdo->prepare("UPDATE utilisateurs SET " . implode(', ', $fieldsToUpdate) . " WHERE id_utilisateur = ?")->execute($params);
        }

        // Tout s'est bien passé, on valide la transaction
        $pdo->commit();

        $redirectId = ($email) ? $email : $ancien_email;
        header("Location: ../pages/cartes/modifier.php?id=" . urlencode($redirectId) . "&status=success");
        exit();

    } catch (Exception $e) {
        // Si une erreur survient n'importe où, on annule tout
        $pdo->rollBack();
        header("Location: ../pages/cartes/modifier.php?id=" . urlencode($ancien_email) . "&status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Si on essaie d'accéder à la page sans soumettre le formulaire
    header("Location: ../pages/cartes/gestion_adherents.php");
    exit();
}