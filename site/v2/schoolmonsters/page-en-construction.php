<?php
session_start();
if(!isset($_SESSION['nom'])){
	header('location:../index.php');
	exit();
}

$niveau=isset($niveau)?$niveau:'CE1';
$periode=isset($periode)?(int)$periode:1;
$titreAvant=isset($titreAvant)?$titreAvant:'School Monster de';
$titreAccent=isset($titreAccent)?$titreAccent:'la matière';
$icone=isset($icone)?$icone:'108.png';
$retour='../P'.$periode.'_'.$niveau.'.php';

// Cette page dessert neuf matieres qui n'ont pas encore de lecon. Des que
// l'enseignante en cree une depuis l'editeur, elle doit apparaitre ici : sans
// cela, la lecon existerait sans que personne ne puisse y acceder. On capture
// donc une zone de liste, vide au depart, que liste-lecons-fin.php remplit.
ob_start();
require(__DIR__.'/inclusions/liste-lecons-debut.php');
require(__DIR__.'/inclusions/liste-lecons-fin.php');
$lecons=trim(ob_get_clean());
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($titreAccent,ENT_QUOTES,'UTF-8'); ?> — leçon en préparation</title>
	<link rel="stylesheet" href="../css/en-construction.css?v=20260904-01">
	<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">


		<!-- Bandeau commun aux trois sites. Le bandeau propre a la matiere,
		     juste en dessous, garde son bouton de retour et sa mascotte :
		     c'est l'identite de la page. -->
		<header class="gx-entete">
			<img class="gx-entete-logo" src="../img-140/logo_school_monsters.png?v=20260906-01" alt="School Monsters">
			<nav class="fil" aria-label="Fil d'Ariane">
				<a href="/portail/">Portail</a><span aria-hidden="true">›</span>
				<a href="../acceuil.php">Les périodes</a><span aria-hidden="true">›</span>
				<a href="<?php echo htmlspecialchars($retour,ENT_QUOTES,'UTF-8'); ?>">La période</a><span aria-hidden="true">›</span>
				<span class="actuel"><?php echo htmlspecialchars($titreAccent,ENT_QUOTES,'UTF-8'); ?></span>
			</nav>
			<?php if(isset($_SESSION['prenom'])){ ?>

			<?php require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php'; echo gxMenuIdentite('School Monsters'); ?>
			<?php } ?>
		</header>
<main class="page-en-construction gx-largeur-grille">

		<header class="entete-matiere">
			<a class="bouton-retour" href="<?php echo htmlspecialchars($retour,ENT_QUOTES,'UTF-8'); ?>">
				<img src="../img-140/108.png" alt="">
				<span>retour</span>
			</a>

			<div class="titre-matiere">
				<span><?php echo htmlspecialchars($titreAvant,ENT_QUOTES,'UTF-8'); ?></span>
				<strong><?php echo htmlspecialchars($titreAccent,ENT_QUOTES,'UTF-8'); ?></strong>
			</div>

			<img class="mascotte-matiere" src="../img-140/<?php echo htmlspecialchars($icone,ENT_QUOTES,'UTF-8'); ?>" alt="">
		</header>

		<?php if($lecons!==''){ ?>
		<section class="liste-lecons-matiere" aria-label="Les leçons de cette matière">
<?php echo $lecons; ?>
		</section>
		<?php }else{
			// Lot 5 (04/09/2026) : avant, une seule image (travaux-en-cours.png,
			// introuvable en production - l'ecran etait donc vide) portait tout
			// le message. La mascotte de la periode, deja utilisee sur
			// periode-modele.php, remplace ce generique par un repere familier
			// pour un eleve de CE1 qui tombe ici pour de bon.
			$mascottesCE1 = array(1=>'151.png',2=>'87.png',3=>'3.png',4=>'6.png',5=>'10.png');
			$mascottesCE2 = array(1=>'135.png',2=>'46.png',3=>'93.png',4=>'123.png',5=>'57.png');
			$table = $niveau === 'CE1' ? $mascottesCE1 : $mascottesCE2;
			$mascotteTravaux = isset($table[$periode]) ? $table[$periode] : '108.png';
		?>
		<section class="cartouche-travaux" aria-label="Leçon en préparation">
			<img src="../img-140/<?php echo htmlspecialchars($mascotteTravaux,ENT_QUOTES,'UTF-8'); ?>" alt="">
			<h2>Cette leçon n'est pas encore prête</h2>
			<p>Reviens un peu plus tard, elle sera là bientôt !</p>
		</section>
		<?php } ?>
	</main>
</body>
</html>
