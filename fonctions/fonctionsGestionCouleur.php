<?php
function getCouleursAssociation(PDO $pdo, int $id_utilisateur): ?array {

    $stmt = $pdo->prepare("
        SELECT ua.id_association
        FROM utilisateur_association AS ua
        WHERE ua.id_utilisateur = ?
        LIMIT 1
    ");
    $stmt->execute([$id_utilisateur]);
    $idAssociationRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$idAssociationRow) {
        return null;
    }

    $idAssociation = $idAssociationRow['id_association'];

    $stmt = $pdo->prepare("
        SELECT couleur_principale_1, couleur_principale_2, couleur_principale_3, couleur_secondaire_1, couleur_secondaire_2, couleur_secondaire_3
        FROM parametrage_association
        WHERE id_association = ?
    ");
    $stmt->execute([$idAssociation]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ?: null;
}