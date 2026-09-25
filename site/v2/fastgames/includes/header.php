<?php
// Icônes SVG partagées du socle, utilisées par le catalogue, le passeport et
// les matières. Requis avant tout affichage.
require_once $_SERVER['DOCUMENT_ROOT'] . '/v2/galaxie-icones.php';

$fgTitre = isset($fgTitre) ? $fgTitre : "Fast Games";
$fgPage = isset($fgPage) ? $fgPage : '';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo fgH($fgTitre); ?> - Fast Games</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-accueils-b">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/v2/fastgames/assets/style.css?v=20260920-qcm-centre">
</head>
<body class="gx-typo gx-app-fastgames <?php echo fgEstEnseignant() ? 'gx-enseignant' : 'gx-eleve'; ?>">
<a class="skip-link" href="#contenu">Aller au contenu</a>
    <header class="gx-entete fg-entete">
        <a href="/v2/fastgames/<?php echo fgEstEnseignant() ? 'studio.php' : 'index.php'; ?>" class="fg-marque" aria-label="Accueil Fast Games">
            <img class="gx-entete-logo" src="/v2/fastgames/assets/logo-fastgames.png?v=20260906-02" alt="Fast Games">
        </a>
        <?php $fgAccueil = fgEstEnseignant() ? 'studio.php' : 'index.php'; ?>
        <nav class="fil" aria-label="Fil d'Ariane">
            <a href="/portail/">Portail</a>
            <span aria-hidden="true">›</span>
            <?php if ($fgPage === 'studio' || $fgPage === '') { ?>
            <span class="actuel">Fast Games</span>
            <?php } else { ?>
            <a href="/v2/fastgames/<?php echo $fgAccueil; ?>">Fast Games</a>
            <span aria-hidden="true">›</span>
            <span class="actuel"><?php echo fgH($fgTitre); ?></span>
            <?php } ?>
        </nav>
        <?php
        require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
        // La rangee de navigation "Accueil / Mini-jeux / Programme" retiree
        // du bandeau le 04/09/2026 (demande de le responsable technique) : elle faisait doublon
        // avec le fil d'Ariane et les cartes du tableau de bord. Ces trois
        // liens restent accessibles depuis le menu de compte, desormais a
        // toutes les largeurs et non plus seulement sous 720px.
        //
        // Cote eleve, ce tableau etait reste vide : passeport.php n'avait
        // donc AUCUN lien nulle part dans l'application, trouvable seulement
        // en tapant l'adresse a la main. Signale par le responsable technique le 04/09/2026 en
        // cherchant le passeport depuis l'interface. Corrige en donnant a
        // l'eleve le meme genre de raccourcis que l'enseignant.
        echo gxMenuIdentite(
            "Fast Games",
            fgEstEnseignant() ? array(
                array('label' => 'Accueil', 'href' => '/v2/fastgames/studio.php'),
                array('label' => 'Mini-jeux', 'href' => '/v2/fastgames/catalogue.php'),
                array('label' => 'Programme', 'href' => '/v2/fastgames/couverture-programme.php'),
            ) : array(
                array('label' => 'Mini-jeux', 'href' => '/v2/fastgames/catalogue.php'),
                array('label' => 'Mon passeport', 'href' => '/v2/fastgames/passeport.php'),
            )
        );
        ?>
    </header>
<div class="fg-page gx-largeur-<?php echo in_array($fgPage, ['studio', 'couverture', 'passeport'], true) ? 'riche' : 'grille'; ?><?php echo $fgPage === 'catalogue' ? ' gx-accueil-compose' : ''; ?>">
    <main id="contenu">
        <?php if ($fgPage !== 'jeu') { ?>
        <nav class="fg-navigation" aria-label="Navigation de Fast Games">
            <?php if (fgEstEnseignant()) { ?>
            <a href="studio.php"<?php echo $fgPage === 'studio' ? ' aria-current="page"' : ''; ?>>Suivre la classe</a>
            <a href="catalogue.php"<?php echo $fgPage === 'catalogue' ? ' aria-current="page"' : ''; ?>>Choisir une activité</a>
            <a href="couverture-programme.php"<?php echo $fgPage === 'couverture' ? ' aria-current="page"' : ''; ?>>Programme</a>
            <?php } else { ?>
            <a href="catalogue.php"<?php echo $fgPage === 'catalogue' ? ' aria-current="page"' : ''; ?>>Les jeux</a>
            <a href="passeport.php"<?php echo $fgPage === 'passeport' ? ' aria-current="page"' : ''; ?>>Mon passeport</a>
            <?php } ?>
        </nav>
        <?php } ?>
