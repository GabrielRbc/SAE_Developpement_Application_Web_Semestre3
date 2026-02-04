<?php
function renvoieListeAdherent($pdo, $ordre) {
    try {
        // Requête pour récupérer les informations des utilisateurs + statut de candidature + statut des fichiers
        $sql = "
            SELECT CONCAT(u.prenom, ' ', u.nom) AS nom_complet,
                   u.email,
                   u.telephone,
                   MAX(uc.statut) AS fiche_statut,
                   CASE
                       WHEN COUNT(fu.id_fichier_utilisateur) = 0 THEN 'manquants'
                       WHEN SUM(fu.statut = 'refuse') > 0 THEN 'manquants'
                       WHEN SUM(fu.statut = 'attente') > 0 THEN 'attente'
                       ELSE 'valide'
                   END AS document_statut
            FROM utilisateurs u
            LEFT JOIN utilisateurs_candidatures uc 
                ON u.id_utilisateur = uc.id_utilisateur
            LEFT JOIN utilisateur_association ua
                ON u.id_utilisateur = ua.id_utilisateur
                AND ua.role = 'adherent'
            LEFT JOIN fichiers_utilisateur fu
                ON fu.id_utilisateur = u.id_utilisateur
            WHERE ua.role = 'adherent'
            GROUP BY u.id_utilisateur, u.prenom, u.nom, u.email, u.telephone
            ";

        // Ajout de l'ordre
        if ($ordre === 'alpha') {
            $sql .= " ORDER BY u.prenom, u.nom";
        } else if ($ordre === 'date') {
            $sql .= " ORDER BY u.date_creation";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $resultats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultats[] = [
                'nom' => $row['nom_complet'],
                'email' => $row['email'],
                'telephone' => $row['telephone'],
                'fiche' => $row['fiche_statut'] ?? 'inconnu',
                'documents' => $row['document_statut'] ?? 'inconnu'
            ];
        }

        return $resultats;

    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
        return [];
    }
}

function ajouterAdherent($pdo, $nom, $prenom, $email, $telephone, $identifiant, $motdepasse, $id_utilisateur) {
    try {

        if (
            strlen($motdepasse) < 8 ||
            !preg_match('/[A-Z]/', $motdepasse) ||
            !preg_match('/[a-z]/', $motdepasse) ||
            !preg_match('/[0-9]/', $motdepasse) ||
            !preg_match('/[^A-Za-z0-9]/', $motdepasse)
        ) {
            return "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.";
        }

        // Démarrer la transaction
        $pdo->beginTransaction();

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return "Cet email existe déjà.";
        }

        // Vérifier si l'identifiant existe déjà
        $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE identifiant = ?");
        $stmt->execute([$identifiant]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return "Cet identifiant existe déjà.";
        }

        // Récupérer l'association de l'utilisateur
        $stmt = $pdo->prepare("SELECT a.id_association 
                               FROM associations AS a
                               JOIN utilisateur_association AS ua
                               ON a.id_association = ua.id_association
                               WHERE ua.id_utilisateur = ?");
        $stmt->execute([$id_utilisateur]);
        $idAssociationRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$idAssociationRow) {
            $pdo->rollBack();
            return "L'utilisateur n'appartient à aucune association valide.";
        }

        $idAssociation = $idAssociationRow['id_association'];

        // Hash du mot de passe
        $motdepasseHash = password_hash($motdepasse, PASSWORD_DEFAULT);

        // Insérer l'utilisateur
        $stmt = $pdo->prepare("INSERT INTO utilisateurs 
                               (nom, prenom, email, identifiant, motdepasse, telephone)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nom, $prenom, $email, $identifiant, $motdepasseHash,
            $telephone
        ]);

        $idUtilisateur = $pdo->lastInsertId();

        // Associer l'utilisateur à l'association
        $stmt = $pdo->prepare("INSERT INTO utilisateur_association (id_utilisateur, id_association, role)
                               VALUES (?, ?, 'adherent')");
        $stmt->execute([$idUtilisateur, $idAssociation]);

        // ----- Ajouter l'utilisateur à tous les formulaires de l'association -----
        $stmt = $pdo->prepare("SELECT id_formulaire FROM formulaires WHERE id_association = ?");
        $stmt->execute([$idAssociation]);
        $formulaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtInsertCandidature = $pdo->prepare(
            "INSERT INTO utilisateurs_candidatures (id_utilisateur, id_formulaire, statut)
             VALUES (?, ?, 'attente')"
        );

        foreach ($formulaires as $formulaire) {
            $stmtInsertCandidature->execute([$idUtilisateur, $formulaire['id_formulaire']]);
        }

        // Valider la transaction
        $pdo->commit();

        header('Location: gestion_adherents.php?success=1');
        exit();

    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return "Erreur : " . $e->getMessage();
    }
}

function suppressionAdherent($pdo, $email) {
    try {
        $sql = "DELETE FROM utilisateurs WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
    } catch (PDOException $e) {
        return "Erreur : " . $e->getMessage();
    }
}