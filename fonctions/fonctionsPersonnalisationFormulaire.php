<?php

function renvoieListeSections($pdo, $id_formulaire) {
    try {
        $sql = "
            SELECT s.id_section, s.label
            FROM formulaires_elements fe
            JOIN formulaires_elements_sections fes ON fe.id_element = fes.id_element
            JOIN sections s ON fes.id_section = s.id_section
            WHERE fe.id_formulaire = ?
            ORDER BY fe.ordre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_formulaire]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // On vérifie si la section "vide" est déjà présente dans les résultats
        $hasEmpty = false;
        foreach ($sections as $s) {
            if ($s['label'] === '' || $s['label'] === null) {
                $hasEmpty = true;
                break;
            }
        }

        // Si elle n'existe pas en base, on l'ajoute artificiellement en début de liste
        if (!$hasEmpty) {
            array_unshift($sections, ['id_section' => null, 'label' => '']);
        }

        return $sections;
    } catch (PDOException $e) {
        return [['id_section' => null, 'label' => '']];
    }
}

/**
 * Vérifie si un élément est utilisé (données ou fichiers)
 */
function estElementUtilise($pdo, $id_element) {
    if (!$id_element) return false;

    $sql = "SELECT COUNT(*) FROM adherent_champs_valeurs WHERE id_element = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id_element]);

    if ($stmt->fetchColumn() > 0) {
        return true;
    }

    $sqlFichier = "SELECT COUNT(*) 
                   FROM fichiers_utilisateur fu
                   JOIN formulaires_elements_fichiers fef ON fu.id_fichier = fef.id_fichier
                   WHERE fef.id_element = :id";
    $stmtF = $pdo->prepare($sqlFichier);
    $stmtF->execute(['id' => $id_element]);

    return $stmtF->fetchColumn() > 0;
}

/**
 * Récupère tous les champs ET fichiers d'un formulaire
 */
function renvoieListeChamp($pdo, $id_formulaire) {
    try {
        $sql = "
            SELECT 
                fe.id_element, fe.type_element, fe.obligatoire, fe.ordre,
                c.id_champ, c.label AS c_label, c.placeholder, c.type AS c_type, c.taille_champ AS c_taille,
                f.id_fichier, f.nom AS f_label, f.taille_fichier, f.format, f.taille_champ AS f_taille,
                s.label AS nom_section
            FROM formulaires_elements fe
            LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
            LEFT JOIN champs c ON fec.id_champ = c.id_champ
            LEFT JOIN formulaires_elements_fichiers fef ON fe.id_element = fef.id_element
            LEFT JOIN fichiers f ON fef.id_fichier = f.id_fichier
            LEFT JOIN sections s ON (c.id_section = s.id_section)
            WHERE fe.id_formulaire = ? AND fe.type_element IN ('champ', 'fichier')
            ORDER BY fe.ordre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_formulaire]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $liste_finale = [];
        foreach ($rows as $row) {
            if ($row['type_element'] === 'champ') {
                $stmtOpt = $pdo->prepare("SELECT label FROM options WHERE id_champ = ?");
                $stmtOpt->execute([$row['id_champ']]);

                $liste_finale[] = [
                    'id_element'  => $row['id_element'],
                    'Label'       => $row['c_label'],
                    'Type'        => $row['c_type'],
                    'Placeholder' => $row['placeholder'],
                    'Taille'      => $row['c_taille'],
                    'Section'     => $row['nom_section'] ?? '',
                    'Obligatoire' => (bool) $row['obligatoire'],
                    'Options'     => $stmtOpt->fetchAll(PDO::FETCH_COLUMN) ?: []
                ];
            } elseif ($row['type_element'] === 'fichier') {
                $liste_finale[] = [
                    'id_element'  => $row['id_element'],
                    'Label'       => $row['f_label'],
                    'Type'        => 'file',
                    'Taille'      => $row['f_taille'] ?? 12,
                    'Section'     => '',
                    'Obligatoire' => (bool) $row['obligatoire'],
                    'Format'      => $row['format'],
                    'MaxFileSize' => $row['taille_fichier'],
                    'Options'     => []
                ];
            }
        }
        return $liste_finale;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les infos de base du formulaire
 */
function getFormulaire($pdo, $id_formulaire) {
    try {
        $stmt = $pdo->prepare("SELECT nom, rgpd FROM formulaires WHERE id_formulaire = ?");
        $stmt->execute([$id_formulaire]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['nom' => '', 'rgpd' => ''];
    } catch (PDOException $e) {
        return ['nom' => '', 'rgpd' => ''];
    }
}

/**
 * Sauvegarde complète : prend maintenant en compte l'ordre de la section
 */
function sauvegarderTousLesChamps($pdo, $liste_champs, $id_formulaire, $titre = null, $rgpd = null) {
    try {
        $pdo->beginTransaction();

        $stmtForm = $pdo->prepare("UPDATE formulaires SET nom = ?, rgpd = ? WHERE id_formulaire = ?");
        $stmtForm->execute([$titre, $rgpd, $id_formulaire]);

        $stmtDel = $pdo->prepare("
            DELETE fe, fec, fes, fef
            FROM formulaires_elements AS fe
            LEFT JOIN formulaires_elements_champs AS fec ON fe.id_element = fec.id_element
            LEFT JOIN formulaires_elements_sections AS fes ON fe.id_element = fes.id_element
            LEFT JOIN formulaires_elements_fichiers AS fef ON fe.id_element = fef.id_element
            WHERE fe.id_formulaire = ?
        ");
        $stmtDel->execute([$id_formulaire]);

        $pdo->exec("DELETE FROM options");
        $pdo->exec("DELETE FROM champs");
        $pdo->exec("DELETE FROM sections");
        $pdo->exec("DELETE FROM fichiers");

        // MISE À JOUR : On ajoute le champ 'ordre' dans l'insert de la section
        $stmtSec     = $pdo->prepare("INSERT INTO sections (label, ordre) VALUES (?, ?)");
        $stmtChamp   = $pdo->prepare("INSERT INTO champs (label, placeholder, type, taille_champ, id_section) VALUES (?, ?, ?, ?, ?)");
        $stmtFichier = $pdo->prepare("INSERT INTO fichiers (nom, type, taille_champ, taille_fichier, format) VALUES (?, 'document', ?, ?, ?)");
        $stmtElem    = $pdo->prepare("INSERT INTO formulaires_elements (id_formulaire, type_element, obligatoire, ordre) VALUES (?, ?, ?, ?)");

        $stmtLinkC   = $pdo->prepare("INSERT INTO formulaires_elements_champs (id_element, id_champ) VALUES (?, ?)");
        $stmtLinkS   = $pdo->prepare("INSERT INTO formulaires_elements_sections (id_element, id_section) VALUES (?, ?)");
        $stmtLinkF   = $pdo->prepare("INSERT INTO formulaires_elements_fichiers (id_element, id_fichier) VALUES (?, ?)");
        $stmtOpt     = $pdo->prepare("INSERT INTO options (id_champ, label) VALUES (?, ?)");

        $sections_creees = [];
        $ordreGlobal = 1;

        foreach ($liste_champs as $element) {
            $typeNormalise = strtolower($element['Type']);
            if ($typeNormalise === 'section') {
                // MISE À JOUR : On passe l'ordre global à la table sections
                $stmtSec->execute([trim($element['Label']), $ordreGlobal]);
                $idSection = $pdo->lastInsertId();
                $sections_creees[trim($element['Label'])] = $idSection;
                $stmtElem->execute([$id_formulaire, 'section', 0, $ordreGlobal]);
                $stmtLinkS->execute([$pdo->lastInsertId(), $idSection]);
            } elseif ($typeNormalise === 'file') {
                $stmtFichier->execute([$element['Label'], $element['Taille'] ?? 12, $element['MaxFileSize'] ?? 800000, $element['Format'] ?? 'pdf']);
                $idFichier = $pdo->lastInsertId();
                $stmtElem->execute([$id_formulaire, 'fichier', !empty($element['Obligatoire']) ? 1 : 0, $ordreGlobal]);
                $stmtLinkF->execute([$pdo->lastInsertId(), $idFichier]);
            } else {
                $idSectionParent = null;
                if (!empty($element['Section']) && isset($sections_creees[trim($element['Section'])])) {
                    $idSectionParent = $sections_creees[trim($element['Section'])];
                }
                $stmtChamp->execute([$element['Label'], $element['Placeholder'] ?? '', $element['Type'], $element['Taille'] ?? 6, $idSectionParent]);
                $idChamp = $pdo->lastInsertId();
                if (!empty($element['Options'])) {
                    foreach ($element['Options'] as $opt) $stmtOpt->execute([$idChamp, $opt]);
                }
                $stmtElem->execute([$id_formulaire, 'champ', !empty($element['Obligatoire']) ? 1 : 0, $ordreGlobal]);
                $stmtLinkC->execute([$pdo->lastInsertId(), $idChamp]);
            }
            $ordreGlobal++;
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return "Erreur BDD : " . $e->getMessage();
    }
}

function getAssociationIdByUser($pdo, $id_utilisateur) {
    try {
        $stmt = $pdo->prepare("SELECT a.id_association FROM associations AS a JOIN utilisateur_association AS ua ON a.id_association = ua.id_association WHERE ua.id_utilisateur = ? LIMIT 1");
        $stmt->execute([$id_utilisateur]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['id_association'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

function getDernierFormulaireIdByAssociation($pdo, $id_association) {
    try {
        $stmt = $pdo->prepare("SELECT id_formulaire FROM formulaires WHERE id_association = ? ORDER BY id_formulaire DESC LIMIT 1");
        $stmt->execute([$id_association]);
        return $stmt->fetchColumn() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}