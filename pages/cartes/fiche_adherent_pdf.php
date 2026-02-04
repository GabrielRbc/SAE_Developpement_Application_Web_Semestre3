<?php
session_start();
require "../../fonctions/fonctionsConnexion.php";
require '../../fonctions/fonctionsGestionCouleur.php';
require_once '../../libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

try {
    $pdo = connexionBDDUser();

    require "../../fonctions/verifDroit.php";
    // On passe la connexion active à la fonction de vérification
    verifierAccesDynamique($pdo);

} catch (Exception $e) {
    // si la base de données est inaccessible, on déconnecte par sécurité
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

// --- SÉCURITÉ ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'adherent') {
    header("Location: ../../index.php");
    exit();
}

$id_user = $_SESSION['id_utilisateur'];

// --- RÉCUPÉRATION DES DONNÉES ---
// Infos Formulaire
$stmtForm = $pdo->prepare("SELECT id_formulaire, nom, rgpd FROM formulaires WHERE id_association = 1 LIMIT 1");
$stmtForm->execute();
$formulaire = $stmtForm->fetch(PDO::FETCH_ASSOC);
$id_formulaire = $formulaire['id_formulaire'];

// Éléments du formulaire (avec GROUP_CONCAT pour les options)
$stmtElements = $pdo->prepare("
    SELECT 
        fe.id_element, fe.type_element, fe.obligatoire, fe.ordre,
        c.label AS champ_label, c.type AS champ_type, c.taille_champ,
        s.label AS section_label,
        GROUP_CONCAT(o.label ORDER BY o.id_options SEPARATOR '||') as options_labels
    FROM formulaires_elements fe
    LEFT JOIN formulaires_elements_champs fec ON fe.id_element = fec.id_element
    LEFT JOIN champs c ON fec.id_champ = c.id_champ
    LEFT JOIN formulaires_elements_sections fes ON fe.id_element = fes.id_element
    LEFT JOIN sections s ON fes.id_section = s.id_section
    LEFT JOIN options o ON c.id_champ = o.id_champ
    WHERE fe.id_formulaire = ?
    GROUP BY fe.id_element
    ORDER BY fe.ordre
");
$stmtElements->execute([$id_formulaire]);
$elementsFormulaire = $stmtElements->fetchAll(PDO::FETCH_ASSOC);

// Valeurs saisies par l'adhérent
$stmtValeurs = $pdo->prepare("SELECT id_element, valeur FROM adherent_champs_valeurs WHERE id_utilisateur = ?");
$stmtValeurs->execute([$id_user]);
$valeurs = $stmtValeurs->fetchAll(PDO::FETCH_KEY_PAIR);

// Infos adhérent et association (DA)
$stmtAdh = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
$stmtAdh->execute([$id_user]);
$adherent = $stmtAdh->fetch(PDO::FETCH_ASSOC);

$assoc = $pdo->query("SELECT nom, logo FROM associations LIMIT 1")->fetch();
$couleurs = getCouleursAssociation($pdo, $id_user);

ob_start();
?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 0px; }
            body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0; }

            /* En-tête avec dégradé (DA) */
            .navbar-pdf {
                background: linear-gradient(to right, <?= $couleurs['couleur_principale_1'] ?>, <?= $couleurs['couleur_principale_2'] ?>, <?= $couleurs['couleur_principale_3'] ?>);
                padding: 25px 40px;
                color: white;
            }

            .container { padding: 30px 40px; }

            .header-assoc { text-align: center; margin-bottom: 20px; }
            .logo { height: 60px; margin-bottom: 10px; }

            /* Titre de Section (DA) */
            .section-title {
                color: <?= $couleurs['couleur_principale_1'] ?>;
                font-size: 13px; font-weight: bold;
                text-transform: uppercase;
                margin: 20px 0 10px 0;
                border-bottom: 2px solid <?= $couleurs['couleur_principale_1'] ?>;
                padding-bottom: 4px;
                clear: both;
            }

            /* Système de colonnes */
            .row { width: 100%; clear: both; }
            .col-6 { width: 48%; float: left; margin-bottom: 12px; }
            .col-12 { width: 100%; float: left; margin-bottom: 12px; }
            .spacer { width: 4%; float: left; }

            .field-label { font-weight: bold; margin-bottom: 4px; display: block; color: #444; }
            .field-value {
                background: #f9f9f9;
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: 8px;
                min-height: 14px;
            }

            /* --- VISUELS CHECKBOX & RADIO --- */
            .option-container { margin-top: 5px; }
            .box {
                display: inline-block;
                width: 12px; height: 12px;
                border: 1px solid #333;
                margin-right: 7px;
                text-align: center;
                line-height: 10px;
                font-size: 10px;
                background: #fff;
                vertical-align: middle;
            }
            .round { border-radius: 50%; } /* Cercle pour les boutons radio */

            .rgpd-box {
                clear: both; background: #f8f9fa; border: 1px solid #dee2e6;
                border-radius: 8px; padding: 15px; margin-top: 30px;
            }
            .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #999; }
        </style>
    </head>
    <body>

    <div class="navbar-pdf">
        <h2 style="margin:0;">Ma fiche adhérent</h2>
    </div>

    <div class="container">
        <div class="header-assoc">
            <?php if ($assoc['logo']): ?>
                <img src="../../<?= $assoc['logo'] ?>" class="logo">
            <?php endif; ?>
            <h3 style="margin:0;"><?= htmlspecialchars($assoc['nom']) ?></h3>
        </div>

        <h4 style="color: #666; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <?= htmlspecialchars($formulaire['nom']) ?>
        </h4>

        <div class="row">
            <?php
            $floatCount = 0;
            foreach ($elementsFormulaire as $element):
                if ($element['type_element'] === 'section'):
                    $floatCount = 0;
                    ?>
                    <div class="section-title"><?= htmlspecialchars($element['section_label']) ?></div>

                <?php elseif ($element['type_element'] === 'champ'):
                    $isHalf = ((int)$element['taille_champ'] === 6);
                    $val = $valeurs[$element['id_element']] ?? '';
                    $type = $element['champ_type'];

                    // Auto-remplissage des données système si vide
                    if (empty($val)) {
                        $lbl = mb_strtolower($element['champ_label']);
                        if ($lbl == 'nom') $val = $adherent['nom'];
                        elseif ($lbl == 'prenom' || $lbl == 'prénom') $val = $adherent['prenom'];
                        elseif ($lbl == 'email' || $lbl == 'adresse mail') $val = $adherent['email'];
                    }
                    ?>
                    <div class="<?= $isHalf ? 'col-6' : 'col-12' ?>">
                    <span class="field-label">
                        <?= htmlspecialchars($element['champ_label']) ?>
                        <?= $element['obligatoire'] ? '<span style="color:red;">*</span>' : '' ?>
                    </span>

                        <?php if ($type === 'radio' || $type === 'checkbox'): ?>
                            <div class="option-container">
                                <?php
                                $options = explode('||', $element['options_labels']);
                                $valeursSelectionnees = explode(',', $val); // Pour gérer les checkbox multiples

                                foreach ($options as $opt):
                                    $isCheck = in_array($opt, $valeursSelectionnees);
                                    ?>
                                    <div style="margin-bottom: 4px;">
                                    <span class="box <?= ($type === 'radio') ? 'round' : '' ?>">
                                        <?= $isCheck ? 'X' : '' ?>
                                    </span>
                                        <span style="vertical-align: middle;"><?= htmlspecialchars($opt) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="field-value"><?= htmlspecialchars($val) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php
                    if ($isHalf) {
                        $floatCount++;
                        if ($floatCount % 2 != 0) echo '<div class="spacer"></div>';
                        else echo '<div style="clear:both;"></div>';
                    } else {
                        echo '<div style="clear:both;"></div>'; $floatCount = 0;
                    }
                    ?>
                <?php endif; endforeach; ?>
        </div>

        <div class="rgpd-box">
            <p style="margin-top:0; font-size: 10px;"><?= nl2br(htmlspecialchars($formulaire['rgpd'])) ?></p>
            <div>
                <span class="box">X</span>
                <strong>J'accepte la politique de confidentialité</strong>
            </div>
        </div>

        <div class="footer">
            Compte créé le : <?= date('d/m/Y', strtotime($adherent['date_creation'])) ?>
        </div>
    </div>

    </body>
    </html>
<?php
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("fiche_adherent.pdf", ["Attachment" => true]);
exit;