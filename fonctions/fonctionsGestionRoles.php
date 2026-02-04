<?php

/**
 * Récupère la liste des membres avec filtres, recherche et pagination SQL
 */
function renvoieListeMembre($pdo, $ordre, $role, $recherche = '', $limit = null, $offset = null) {
    try {
        $sql = "
        SELECT u.id_utilisateur,
               CONCAT(u.prenom, ' ', u.nom) AS nom_complet,
               u.email,
               u.telephone,
               ua.role
        FROM utilisateurs u
        JOIN utilisateur_association ua ON u.id_utilisateur = ua.id_utilisateur
        WHERE 1=1";

        $params = [];

        if (in_array($role, ['responsable', 'membre', 'adherent'], true)) {
            $sql .= " AND ua.role = :role";
            $params['role'] = $role;
        }

        if (!empty($recherche)) {
            $sql .= " AND (
                u.nom COLLATE utf8mb4_general_ci LIKE :search
                OR u.prenom COLLATE utf8mb4_general_ci LIKE :search
                OR u.email COLLATE utf8mb4_general_ci LIKE :search
                OR CAST(u.telephone AS CHAR) LIKE :search
            )";
            $params['search'] = '%' . $recherche . '%';
        }

        if ($ordre === 'date') {
            $sql .= " ORDER BY u.date_creation DESC";
        } else {
            $sql .= " ORDER BY u.prenom, u.nom";
        }

        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = (int)$limit;
            $params['offset'] = (int)$offset;
        }

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Compte le nombre total de membres filtrés (pour pagination)
 */
function compterMembresFiltres($pdo, $role, $recherche = '') {
    $sql = "
    SELECT COUNT(*) 
    FROM utilisateurs u
    JOIN utilisateur_association ua ON u.id_utilisateur = ua.id_utilisateur
    WHERE 1=1";

    $params = [];

    if (in_array($role, ['responsable', 'membre', 'adherent'], true)) {
        $sql .= " AND ua.role = :role";
        $params['role'] = $role;
    }

    if (!empty($recherche)) {
        $sql .= " AND (
            u.nom COLLATE utf8mb4_general_ci LIKE :search
            OR u.prenom COLLATE utf8mb4_general_ci LIKE :search
            OR u.email COLLATE utf8mb4_general_ci LIKE :search
            OR CAST(u.telephone AS CHAR) LIKE :search
        )";
        $params['search'] = '%' . $recherche . '%';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * Modifie le rôle d'un utilisateur
 */
function changerRole($pdo, $id_utilisateur, $nouveau_role) {
    $sql = "UPDATE utilisateur_association SET role = :role WHERE id_utilisateur = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'role' => $nouveau_role,
        'id' => $id_utilisateur
    ]);
}

/**
 * Statistiques par rôle
 */
function compterRole($pdo) {
    $sql = "SELECT role, COUNT(*) AS nombre FROM utilisateur_association GROUP BY role";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}