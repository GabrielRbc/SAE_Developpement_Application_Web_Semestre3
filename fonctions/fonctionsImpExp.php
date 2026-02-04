<?php

function exportData($pdo, $assocId, $type = 'adherents', $champs = null)
{
    if ($type === 'adherents') {
        if (!$champs || !is_array($champs) || count($champs) === 0) {
            $stmt = $pdo->prepare("
                SELECT u.*
                FROM utilisateurs u
                INNER JOIN utilisateur_association ua ON u.id_utilisateur = ua.id_utilisateur
                WHERE ua.id_association = ? AND ua.role = 'adherent'
            ");
            $stmt->execute([$assocId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $export = [];

        $stmt = $pdo->prepare("
            SELECT u.id_utilisateur
            FROM utilisateurs u
            INNER JOIN utilisateur_association ua ON u.id_utilisateur = ua.id_utilisateur
            WHERE ua.id_association = ?
        ");
        $stmt->execute([$assocId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $placeholders = implode(',', array_fill(0, count($champs), '?'));

        foreach ($users as $user) {
            $stmt = $pdo->prepare("
                SELECT id_element, valeur
                FROM adherent_champs_valeurs
                WHERE id_utilisateur = ? AND id_element IN ($placeholders)
            ");
            $stmt->execute(array_merge([$user['id_utilisateur']], $champs));
            $values = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $rowData = [];
            foreach ($champs as $id) {
                $rowData[$id] = $values[$id] ?? '';
            }

            $export[] = $rowData;
        }

        return $export;
    } elseif ($type === 'formulaire') {
        $stmt = $pdo->prepare("
        SELECT f.id_formulaire, f.nom AS formulaire_nom, f.rgpd,
               fe.id_element, fe.type_element, fe.obligatoire, fe.ordre,
               c.id_champ, c.label AS champ_label, c.placeholder, c.type AS champ_type, c.taille_champ,
               s.label AS section_label_for_champ, o.label AS option_label,
               sec.id_section AS id_section, sec.label AS section_label,
               fi.id_fichier, fi.nom AS fichier_nom, fi.type AS fichier_type, fi.taille_champ AS fichier_taille_champ,
               fi.taille_fichier, fi.format
        FROM formulaires f
        LEFT JOIN formulaires_elements fe ON f.id_formulaire = fe.id_formulaire
        LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
        LEFT JOIN champs c ON fec.id_champ = c.id_champ
        LEFT JOIN options o ON c.id_champ = o.id_champ
        LEFT JOIN sections s ON c.id_section = s.id_section
        LEFT JOIN formulaires_elements_sections fes ON fe.id_element = fes.id_element
        LEFT JOIN sections sec ON fes.id_section = sec.id_section
        LEFT JOIN formulaires_elements_fichiers fef ON fe.id_element = fef.id_element
        LEFT JOIN fichiers fi ON fef.id_fichier = fi.id_fichier
        WHERE f.id_association = ?
        ORDER BY fe.ordre ASC
    ");
        $stmt->execute([$assocId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formulaires = [];

        foreach ($rows as $r) {

            $fid = $r['id_formulaire'];

            if (!isset($formulaires[$fid])) {
                $formulaires[$fid] = [
                    'nom' => $r['formulaire_nom'],
                    'rgpd' => $r['rgpd'],
                    'elements' => []
                ];
            }

            $key = $r['id_element'] . '_' . $r['type_element'];

            if (!isset($formulaires[$fid]['elements'][$key])) {
                $element = [
                    'id_element' => $r['id_element'],
                    'type' => $r['type_element'],
                    'obligatoire' => (bool)$r['obligatoire'],
                    'ordre' => (int)$r['ordre']
                ];

                switch ($r['type_element']) {

                    case 'champ':
                        $element['champ'] = [
                            'label' => $r['champ_label'],
                            'placeholder' => $r['placeholder'],
                            'type' => $r['champ_type'],
                            'taille_champ' => $r['taille_champ'],
                            'section' => $r['section_label_for_champ'],
                            'options' => [] // ⚠ on initie ici
                        ];
                        break;

                    case 'fichier':
                        $element['fichier'] = [
                            'nom' => $r['fichier_nom'],
                            'type' => $r['fichier_type'],
                            'taille_champ' => $r['fichier_taille_champ'],
                            'taille_fichier' => $r['taille_fichier'],
                            'format' => $r['format']
                        ];
                        break;

                    case 'section':
                        $element['section'] = [
                            'label' => $r['section_label']
                        ];
                        break;
                }

                $formulaires[$fid]['elements'][$key] = $element;
            }

            if ($r['type_element'] === 'champ' && !empty($r['option_label'])) {
                $formulaires[$fid]['elements'][$key]['champ']['options'][] = $r['option_label'];
            }
        }
        foreach ($formulaires as &$f) {
            $f['elements'] = array_values($f['elements']);
        }

        return $formulaires;
    }
}

function arrayToJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function arrayToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $f = fopen('php://output', 'w');
    if (!empty($data)) {
        fputcsv($f, array_keys($data[0]));
        foreach ($data as $row) fputcsv($f, $row);
    }
    fclose($f);
}

function importAdherents($pdo, $assocId, $data) {
    foreach ($data as $row) {

        $nom         = trim($row['nom'] ?? '');
        $prenom      = trim($row['prenom'] ?? '');
        $email       = trim($row['email'] ?? '');
        $identifiant = trim($row['identifiant'] ?? '');
        $motdepasse  = $row['motdepasse'] ?? null;
        $telephone   = trim($row['telephone'] ?? '');
        $date_crea   = $row['date_creation'] ?? null;

        if (empty($nom) || empty($prenom) || empty($email) || empty($identifiant) || empty($motdepasse)) {
            continue;
        }

        $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetchColumn();

        if (!$existing) {
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, identifiant, motdepasse, telephone, date_creation)
                VALUES (:nom, :prenom, :email, :identifiant, :motdepasse, :telephone, :date_creation)
            ");
            $stmt->execute([
                'nom'          => $nom,
                'prenom'       => $prenom,
                'email'        => $email,
                'identifiant'  => $identifiant,
                'motdepasse'   => $motdepasse,
                'telephone'    => $telephone ?: null,
                'date_creation'=> $date_crea ?: date('Y-m-d H:i:s')
            ]);
            $userId = $pdo->lastInsertId();
        } else {
            $userId = $existing;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur_association WHERE id_utilisateur = ? AND id_association = ?");
        $stmt->execute([$userId, $assocId]);
        $existsLink = $stmt->fetchColumn();

        if (!$existsLink) {
            $pdo->prepare("
                INSERT INTO utilisateur_association (id_utilisateur, id_association, role)
                VALUES (?, ?, 'adherent')
            ")->execute([$userId, $assocId]);
        }

        $stmt = $pdo->prepare("SELECT id_formulaire FROM formulaires WHERE id_association = ? LIMIT 1");
        $stmt->execute([$assocId]);
        $idFormulaire = $stmt->fetchColumn();

        if ($idFormulaire) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM utilisateurs_candidatures WHERE id_utilisateur = ? AND id_formulaire = ?
            ");
            $stmt->execute([$userId, $idFormulaire]);
            $already = $stmt->fetchColumn();

            if (!$already) {
                $pdo->prepare("
                    INSERT INTO utilisateurs_candidatures (id_utilisateur, id_formulaire, statut)
                    VALUES (?, ?, 'attente')
                ")->execute([$userId, $idFormulaire]);
            }
        }
    }
}

function importFormulaire($pdo, $assocId, $data, $replaceExisting = true) {
    if ($replaceExisting) {
        // suppression des anciens formulaires
        $stmt = $pdo->prepare("SELECT id_formulaire FROM formulaires WHERE id_association = ?");
        $stmt->execute([$assocId]);
        $oldForms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($oldForms)) {
            foreach ($oldForms as $fid) {
                $pdo->prepare("DELETE FROM formulaires_elements_champs WHERE id_element IN (SELECT id_element FROM formulaires_elements WHERE id_formulaire = ?)")->execute([$fid]);
                $pdo->prepare("DELETE FROM formulaires_elements_sections WHERE id_element IN (SELECT id_element FROM formulaires_elements WHERE id_formulaire = ?)")->execute([$fid]);
                $pdo->prepare("DELETE FROM formulaires_elements_fichiers WHERE id_element IN (SELECT id_element FROM formulaires_elements WHERE id_formulaire = ?)")->execute([$fid]);
                $pdo->prepare("DELETE FROM formulaires_elements WHERE id_formulaire = ?")->execute([$fid]);
                $pdo->prepare("DELETE FROM formulaires WHERE id_formulaire = ?")->execute([$fid]);
            }
        }
    }

    foreach ($data as $form) {
        $stmt = $pdo->prepare("INSERT INTO formulaires (id_association, nom, rgpd) VALUES (?, ?, ?)");
        $stmt->execute([$assocId, $form['nom'] ?? 'Formulaire importé', $form['rgpd'] ?? null]);
        $idFormulaire = $pdo->lastInsertId();

        $elements = $form['elements'] ?? [];
        if (is_string($elements)) $elements = json_decode($elements, true) ?: [];

        $orderIndex = 1;
        $lastSectionIdByLabel = [];

        foreach ($elements as $el) {
            $type = $el['type'] ?? 'champ';

            if ($type === 'section') {
                // création élément section
                $stmtEl = $pdo->prepare("INSERT INTO formulaires_elements (id_formulaire, type_element, obligatoire, ordre) VALUES (?, ?, ?, ?)");
                $stmtEl->execute([$idFormulaire, 'section', !empty($el['obligatoire']) ? 1 : 0, $el['ordre'] ?? $orderIndex++]);
                $idElementDB = $pdo->lastInsertId();

                $label = trim($el['section']['label'] ?? 'Section');
                $stmt = $pdo->prepare("INSERT INTO sections (label) VALUES (?)");
                $stmt->execute([$label]);
                $idSectionDB = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO formulaires_elements_sections (id_element, id_section) VALUES (?, ?)")->execute([$idElementDB, $idSectionDB]);

                $lastSectionIdByLabel[$label] = $idSectionDB;
                continue;
            }

            // création élément champ ou fichier
            $stmtEl = $pdo->prepare("INSERT INTO formulaires_elements (id_formulaire, type_element, obligatoire, ordre) VALUES (?, ?, ?, ?)");
            $stmtEl->execute([$idFormulaire, $type, !empty($el['obligatoire']) ? 1 : 0, $el['ordre'] ?? $orderIndex++]);
            $idElementDB = $pdo->lastInsertId();

            if ($type === 'champ') {
                $champ = $el['champ'] ?? [];
                $sectionLabel = $champ['section'] ?? null;
                $sectionId = $sectionLabel && isset($lastSectionIdByLabel[$sectionLabel]) ? $lastSectionIdByLabel[$sectionLabel] : null;

                $stmt = $pdo->prepare("INSERT INTO champs (label, placeholder, type, taille_champ, id_section) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $champ['label'] ?? '',
                    $champ['placeholder'] ?? '',
                    $champ['type'] ?? 'text',
                    $champ['taille_champ'] ?? 6,
                    $sectionId
                ]);
                $idChampDB = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO formulaires_elements_champs (id_element, id_champ) VALUES (?, ?)")->execute([$idElementDB, $idChampDB]);

                if (!empty($champ['options']) && is_array($champ['options'])) {
                    $optStmt = $pdo->prepare("INSERT INTO options (label, id_champ) VALUES (?, ?)");
                    foreach ($champ['options'] as $optLabel) {
                        $optStmt->execute([$optLabel, $idChampDB]);
                    }
                }
            }

            if ($type === 'fichier') {
                $fichier = $el['fichier'] ?? [];
                $stmt = $pdo->prepare("INSERT INTO fichiers (nom, type, taille_champ, taille_fichier, format) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $fichier['nom'] ?? '',
                    $fichier['type'] ?? 'document',
                    $fichier['taille_champ'] ?? 1,
                    $fichier['taille_fichier'] ?? null,
                    $fichier['format'] ?? ''
                ]);
                $idFichier = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO formulaires_elements_fichiers (id_element, id_fichier) VALUES (?, ?)")->execute([$idElementDB, $idFichier]);
            }
        }

        $_SESSION['titre_formulaire'] = $form['nom'] ?? 'Formulaire importé';
        $_SESSION['rgpd'] = $form['rgpd'] ?? '';
        $_SESSION['sections'] = renvoieListeSections($pdo, $idFormulaire);
        $_SESSION['liste_champ'] = renvoieListeChamp($pdo, $idFormulaire);

    }
}


function getChampsFormulaire($pdo, $assocId) {
    $stmt = $pdo->prepare("
        SELECT fe.id_element, c.label 
        FROM formulaires_elements fe
        INNER JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
        INNER JOIN champs c ON fec.id_champ = c.id_champ
        INNER JOIN formulaires f ON fe.id_formulaire = f.id_formulaire
        WHERE f.id_association = ?
        ORDER BY fe.ordre ASC
    ");
    $stmt->execute([$assocId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAssociationIdByUser($pdo, $id_utilisateur) {
    $stmt = $pdo->prepare("
        SELECT a.id_association
        FROM associations AS a
        JOIN utilisateur_association AS ua ON a.id_association = ua.id_association
        WHERE ua.id_utilisateur = ?
        LIMIT 1
    ");
    $stmt->execute([$id_utilisateur]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['id_association'] : null;
}

function renvoieListeSections($pdo, $id_formulaire = 1) {
    try {
        $sql = "
            SELECT s.id_section, s.label
            FROM formulaires_elements fe
            JOIN formulaires_elements_sections fes ON fe.id_element = fes.id_element
            JOIN sections s ON fes.id_section = s.id_section
            WHERE fe.id_formulaire = ?
            AND TRIM(s.label) <> ''  -- ignore les sections vides
            ORDER BY fe.ordre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_formulaire]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // filtrage de sécurité supplémentaire
        return array_values(array_filter($sections, function($s) {
            return isset($s['label']) && trim($s['label']) !== '';
        }));
    } catch (PDOException $e) {
        return [];
    }
}

function renvoieListeChamp($pdo, $id_formulaire)
{
    $stmt = $pdo->prepare("
        SELECT c.*, s.label AS nom_section, fe.obligatoire
        FROM formulaires_elements fe
        JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
        JOIN champs c ON fec.id_champ = c.id_champ
        LEFT JOIN sections s ON c.id_section = s.id_section
        WHERE fe.id_formulaire = ?
        ORDER BY fe.ordre ASC
    ");
    $stmt->execute([$id_formulaire]);
    $champs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $liste_finale = [];
    foreach ($champs as $row) {
        $stmtOpt = $pdo->prepare("SELECT label FROM options WHERE id_champ = ?");
        $stmtOpt->execute([$row['id_champ']]);
        $options = $stmtOpt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Si pas de section, mettre une chaîne vide
        $sectionLabel = $row['nom_section'] ?? '';

        $liste_finale[] = [
            'Label'       => $row['label'],
            'Type'        => $row['type'],
            'Placeholder' => $row['placeholder'],
            'Taille'      => $row['taille_champ'],
            'Section'     => $sectionLabel,
            'Obligatoire' => (bool)$row['obligatoire'],
            'Options'     => $options
        ];
    }

    return $liste_finale;
}