<?php

function verifierAccesDynamique($pdo) {
    if (!isset($_SESSION['id_utilisateur'])) {
        header("Location: ../index.php");
        exit();
    }

    // On récupère le statut actuel en BDD
    $stmt = $pdo->prepare("SELECT role FROM utilisateurs u INNER JOIN utilisateur_association r ON u.id_utilisateur = r.id_utilisateur WHERE u.id_utilisateur = ?");
    $stmt->execute([$_SESSION['id_utilisateur']]);
    $user = $stmt->fetch();

    // Si le user n'existe pas on renvoie une erreur
    if (!$user) {
        throw new Exception("L'utilisateur n'existe pas");
    }

    // Si le rôle a changé, on met à jour la session en temps réel
    if ($user['role'] !== $_SESSION['role']) {
        $_SESSION['role'] = $user['role'];
        throw new Exception("Role changé");
    }
}