<?php if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// Le palier de l'enseignante. Elle y choisit ce qu'elle vient faire, avant
// de choisir une periode : ce sont deux decisions differentes, et les melanger
// sur la meme page donnait une carte d'administration posee au-dessus de la
// grille des periodes.
if(!isset($_SESSION['role']) || $_SESSION['role']!='enseignant')
{
header('location:index.php');
exit();
}
?>
<!DOCTYPE html>

<html lang="fr">
    <head>
        <title>School Monsters - Mon espace</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/grille.css?v=20260831-02"/>
		<link rel="stylesheet" href="css/style1.css?v=20260912-conformite"/>
        <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-accueils-b">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    </head>
    <body class="page-espace-enseignant gx-typo gx-app-schoolmonsters gx-enseignant">

	<a class="skip-link" href="#contenu">Aller au contenu</a>

	<header class="gx-entete gx-entete-cadree">
		<img class="gx-entete-logo" src="img-140/logo_school_monsters.png?v=20260906-01" alt="School Monsters">
		<nav class="fil" aria-label="Fil d'Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><span class="actuel">Accueil</span></nav>
		<?php
		require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
		echo gxMenuIdentite('School Monsters');
		?>
	</header>

	<main class="sm-page sm-dashboard gx-largeur-grille gx-accueil-compose" id="contenu">
		<div class="gx-ligne-titre">
			<div>
				<p class="gx-ligne-titre-surtitre">Tableau de bord</p>
				<h1 class="gx-ligne-titre-bonjour">Espace enseignant</h1>
				<p class="gx-ligne-titre-description">Préparer les leçons et gérer les accès des élèves.</p>
			</div>
		</div>

        <div class="sm-actions-groupes">
            <section aria-labelledby="sm-preparer">
                <h2 id="sm-preparer">Préparer les activités</h2>
                <nav aria-label="Préparer les activités">
                    <a href="acceuil.php" class="gx-carte-accueil">
                        <span class="ic" aria-hidden="true">📖</span>
                        <b>Ouvrir les leçons</b>
                        <small>Choisir une période et parcourir les fiches.</small>
                    </a>
                    <a href="editeur/index.php" class="gx-carte-accueil">
                        <span class="ic" aria-hidden="true">✎</span>
                        <b>Éditeur de leçons</b>
                        <small>Modifier le titre, la mascotte et les pistes audio.</small>
                    </a>
                </nav>
            </section>
            <section aria-labelledby="sm-organiser">
                <h2 id="sm-organiser">Organiser la classe</h2>
                <nav aria-label="Organiser la classe">
                    <a href="admin/index.php" class="gx-carte-accueil gestion">
                        <span class="ic" aria-hidden="true">👥</span>
                        <b>Administration des élèves</b>
                        <small>Gérer les comptes et les accès de votre classe.</small>
                    </a>
                </nav>
            </section>
        </div>
	</main>

    </body>
</html>
