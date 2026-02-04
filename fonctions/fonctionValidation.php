<?php

/**
 * Affiche le statut d'un document
 */
function showStatus($status) {
    return [
        "attente" => '<span class="fiche-incomplete">En attente</span>',
        "valide"  => '<span class="fiche-complete">Validé</span>',
        "refuse"  => '<span class="fiche-attente">Refusé</span>'
    ][$status] ?? '';
}

/**
 * Retourne le nombre de fichiers par statut
 */
function getFichiersCounts(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT 
            SUM(statut='attente') AS attente,
            SUM(statut='valide') AS valide,
            SUM(statut='refuse') AS refuse
        FROM fichiers_utilisateur
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère la liste des fichiers filtrés et paginés
 */
function getFichiers(PDO $pdo, $statut, $recherche, $ordre, $start, $limit) {
    $sql = "
            SELECT 
        fu.id_fichier_utilisateur AS id,
        fu.id_utilisateur AS user_id,
        CONCAT(u.prenom,' ',u.nom) AS name,
        f.type,
        f.nom AS file,
        fu.chemin_fichier,
        DATE_FORMAT(f.date_creation,'%d/%m/%Y') AS date,
        fu.statut AS status
        FROM fichiers_utilisateur fu
        INNER JOIN fichiers f ON fu.id_fichier=f.id_fichier
        INNER JOIN utilisateurs u ON fu.id_utilisateur=u.id_utilisateur
        WHERE 1=1
    ";

    $params = [];

    if ($statut !== '') {
        $sql .= " AND fu.statut=:statut";
        $params[':statut'] = $statut;
    }

    if ($recherche !== '') {
        $sql .= " AND (
            u.prenom LIKE :rech_prenom
            OR u.nom LIKE :rech_nom
            OR u.email LIKE :rech_email
        )";
        $params[':rech_prenom'] = "%$recherche%";
        $params[':rech_nom']    = "%$recherche%";
        $params[':rech_email']  = "%$recherche%";
    }

    $sql .= ($ordre === 'date') ? " ORDER BY f.date_creation DESC" : " ORDER BY u.prenom, u.nom";
    $sql .= " LIMIT ".(int)$start.",".(int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/**
 * Compte le nombre de fichiers correspondant aux filtres
 */
function countFichiers(PDO $pdo, $statut, $recherche) {
    $sql = "
        SELECT COUNT(*)
        FROM fichiers_utilisateur fu
        INNER JOIN utilisateurs u ON fu.id_utilisateur=u.id_utilisateur
        WHERE 1=1
    ";

    $params = [];

    if ($statut !== '') {
        $sql .= " AND fu.statut=:statut";
        $params[':statut'] = $statut;
    }

    if ($recherche !== '') {
        $sql .= " AND (
            u.prenom LIKE :rech_prenom
            OR u.nom LIKE :rech_nom
            OR u.email LIKE :rech_email
        )";
        $params[':rech_prenom'] = "%$recherche%";
        $params[':rech_nom']    = "%$recherche%";
        $params[':rech_email']  = "%$recherche%";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

/**
 * Met à jour le statut d'un fichier
 */
function updateFichierStatut(PDO $pdo, $id, $statut) {
    $stmt = $pdo->prepare("UPDATE fichiers_utilisateur SET statut=:statut WHERE id_fichier_utilisateur=:id");
    $stmt->execute([
        ':statut' => $statut,
        ':id'     => $id
    ]);
}
