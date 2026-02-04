<?php
/**
 * Récupère les données de l'unique association présente dans la base.
 */
function getAssociation(PDO $pdo) {
    $sql = "SELECT * FROM associations LIMIT 1";
    return $pdo->query($sql)->fetch();
}

/**
 * Supprime l'association, son paramétrage (via CASCADE) et son logo sur le disque.
 */
function supprimerAssociation(PDO $pdo): void {
    $association = getAssociation($pdo);

    if ($association) {
        // Suppression du fichier image s'il existe
        if (!empty($association['logo']) && file_exists("../" . $association['logo'])) {
            unlink("../" . $association['logo']);
        }

        // La suppression de l'association entraîne celle du paramétrage (ON DELETE CASCADE)
        $pdo->exec("DELETE FROM associations");
    }
}

/*
 * Gère l'enregistrement avec des variables individuelles.
 */
function enregistrerAssociation(PDO $pdo, $nom, $logo, $adr, $cp, $ville, $tel, $mail, $site, $infos, $c1, $c2, $c3, $c4, $c5, $c6): void {
    $existante = getAssociation($pdo);

    if ($existante) {
        $id = $existante['id_association'];

        // Mise à jour associations
        $sqlAssoc = "UPDATE associations SET nom=?, logo=?, adresse=?, code_postal=?, ville=?, 
                telephone=?, email=?, lien_site=?, information=? WHERE id_association=?";
        $pdo->prepare($sqlAssoc)->execute([$nom, $logo, $adr, $cp, $ville, $tel, $mail, $site, $infos, $id]);

        // Mise à jour couleurs
        $sqlParam = "UPDATE parametrage_association SET couleur_principale_1=?, couleur_principale_2=?, couleur_principale_3=?, couleur_secondaire_1=?, couleur_secondaire_2=?, couleur_secondaire_3=? 
                WHERE id_association=?";
        $pdo->prepare($sqlParam)->execute([$c1, $c2, $c3, $c4, $c5, $c6, $id]);
    } else {
        // Création initiale
        $sqlAssoc = "INSERT INTO associations (nom, logo, adresse, code_postal, ville, telephone, email, lien_site, information) 
                VALUES (?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sqlAssoc)->execute([$nom, $logo, $adr, $cp, $ville, $tel, $mail, $site, $infos]);

        $newId = $pdo->lastInsertId();

        $sqlParam = "INSERT INTO parametrage_association (id_association, couleur_principale_1, couleur_principale_2, couleur_principale_3, couleur_secondaire_1, couleur_secondaire_2, couleur_secondaire_3) 
                VALUES (?,?,?,?,?,?,?)";
        $pdo->prepare($sqlParam)->execute([$newId, $c1, $c2, $c3, $c4, $c5, $c6]);
    }
}