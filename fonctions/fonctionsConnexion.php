<?php
function connexionBDDUser(): PDO
{
    if ($_SERVER["HTTP_HOST"] == "localhost") {
        $host = "localhost";
        $user = "root";
        $pass = "root";
    } else {
        $host = "mysql-adhesium.alwaysdata.net";
        $user = 'adhesium';
        $pass = 'MotDePasseAdhesiumServeur';
    }
    $db = 'adhesium_bdd';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

function getLogoAssoc($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT logo FROM associations");
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        throw new PDOException($e->getMessage());
    }
}

function getNomAssoc($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT nom FROM associations");
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        throw new PDOException($e->getMessage());
    }
}
function verifUtilisateur(PDO $pdo, string $login, string $mdp): bool
{
    if (empty($login) || empty($mdp)) {
        return false;
    }

    try {
        // On sélectionne tous les utilisateurs avec ce login
        $stmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom, motdepasse FROM utilisateurs WHERE identifiant = :login");
        $stmt->bindParam(':login', $login);
        $stmt->execute();

        $requete = $pdo->prepare("SELECT role FROM utilisateur_association as s JOIN utilisateurs as u ON u.id_utilisateur = s.id_utilisateur WHERE identifiant = :login");
        $requete->bindParam(':login', $login);
        $requete->execute();

        $ligne = $stmt->fetch();
        $role = $requete->fetch();
        // Vérification du mot de passe
        if ($ligne && $ligne['motdepasse'] != null && password_verify($mdp, $ligne['motdepasse'])) {

            // Authentification réussie : on remplit la session
            $_SESSION['nom'] = $ligne['nom'];
            $_SESSION['prenom'] = $ligne['prenom'];
            $_SESSION['idSession'] = session_id();
            $_SESSION['role'] = $role['role'];
            $_SESSION['id_utilisateur'] = $ligne['id_utilisateur'];

            return true;
        }

        return false;

    } catch (Exception $e) {
        return false;
    }
}