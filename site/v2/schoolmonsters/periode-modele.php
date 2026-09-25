<?php
if(!isset($niveau,$periode)||!in_array($niveau,array('CE1','CE2'),true)||$periode<1||$periode>5){
	header('location:acceuil.php');
	exit();
}

// Les pages de periode ne passent pas par theme.php : la garde est rappelee ici.
require_once __DIR__ . '/inclusions/acces-eleve.php';

$prefixe='P'.$periode.'_'.$niveau;
$dossier=$prefixe.'/';

$groupes=array(
	array(
		'titre'=>'Mathématiques',
		'classe'=>'groupe-maths',
		'matieres'=>array(
			array('numeration','Nombres et calculs','01-2.png','Compter, calculer et comprendre les nombres.'),
			array('problemes','Problèmes','06-2.png','Chercher, raisonner et résoudre des situations.'),
			array('geometrie','Espace et géométrie','07-2.png','Reconnaître les formes et se repérer dans l’espace.'),
			array('mesures','Grandeurs et mesures','08-2.png','Mesurer le temps, les longueurs et les quantités.')
		)
	),
	array(
		'titre'=>'Français',
		'classe'=>'groupe-francais',
		'matieres'=>array(
			array('grammaire','Grammaire','05-2.png','Comprendre comment les phrases sont construites.'),
			array('orthographe','Orthographe','03-2.png','Écrire les mots et les accords correctement.'),
			array('conjugaison','Conjugaison','02-2.png','Reconnaître et utiliser les temps des verbes.'),
			array('vocabulaire','Vocabulaire','04-2.png','Découvrir des mots et mieux les comprendre.')
		)
	)
);

if($niveau==='CE1'){
	$groupes[]=array(
		'titre'=>'Découvrir et communiquer',
		'classe'=>'groupe-autres',
		'matieres'=>array(
			array('informatique','Informatique','informatique.png','Utiliser les outils numériques avec méthode.'),
			array('monde','Questionner le monde','monde.png','Observer et comprendre le monde qui nous entoure.'),
			array('lecture','Lecture','lecture.png','Lire avec attention et comprendre les textes.'),
			array('oral','Langage oral','oral.png','Écouter, expliquer et prendre la parole.'),
			array('anglais','Anglais','nessie.png','Écouter et dire ses premiers mots en anglais.'),
			array('redaction','Rédaction','redaction.png','Organiser ses idées et écrire de petits textes.')
		)
	);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $niveau; ?> — Période <?php echo $periode; ?></title>
	<link rel="stylesheet" href="css/periode.css?v=20260909-mascotte">
	<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters <?php echo isset($_SESSION['role']) && $_SESSION['role']==='enseignant' ? 'gx-enseignant' : 'gx-eleve'; ?>">


		<!-- Bandeau commun aux trois sites. L'ancien lien s'appelait « Retour au
		     portail » mais menait à l'accueil de School Monsters : les deux
		     destinations sont maintenant distinctes et portent chacune son nom. -->
		<header class="gx-entete">
			<img class="gx-entete-logo" src="img-140/logo_school_monsters.png?v=20260906-01" alt="School Monsters">
			<nav class="fil" aria-label="Fil d’Ariane">
				<a href="/portail/">Portail</a><span aria-hidden="true">›</span>
				<a href="acceuil.php">Les périodes</a><span aria-hidden="true">›</span>
				<span class="actuel"><?php echo htmlspecialchars($niveau); ?> – Période <?php echo htmlspecialchars($periode); ?></span>
			</nav>
			<?php if(isset($_SESSION['prenom'])){ ?>

			<?php
			require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
			echo gxMenuIdentite('School Monsters');
			?>
			<?php } ?>
		</header>
<main class="page-periode gx-largeur-grille">

		<section class="sm-hero sm-periode-hero gx-ligne-titre" aria-labelledby="titre-periode">
			<div class="sm-hero-contenu">
				<p class="sm-surtitre"><span class="badge-niveau <?php echo strtolower($niveau); ?>"><?php echo $niveau; ?></span> Ton programme</p>
				<h1 id="titre-periode">Période <?php echo $periode; ?></h1>
				<p>Choisis une matière pour commencer.</p>
			</div>
			<img class="sm-hero-mascotte" src="img-140/<?php echo $niveau==='CE1' ? array(1=>'151.png',2=>'87.png',3=>'3.png',4=>'6.png',5=>'10.png')[$periode] : array(1=>'135.png',2=>'46.png',3=>'93.png',4=>'123.png',5=>'57.png')[$periode]; ?>" alt="" aria-hidden="true">
		</section>

        <nav class="sm-domaines" aria-label="Domaines de la période">
            <?php foreach($groupes as $groupe){ ?>
            <a href="#<?php echo htmlspecialchars($groupe['classe']); ?>"><?php echo htmlspecialchars($groupe['titre']); ?></a>
            <?php } ?>
        </nav>

		<?php foreach($groupes as $groupe){ ?>
		<section id="<?php echo htmlspecialchars($groupe['classe']); ?>" class="bloc-matieres <?php echo $groupe['classe']; ?>">
			<div class="entete-groupe"><div><p class="sm-surtitre">À explorer</p><h2><?php echo $groupe['titre']; ?></h2></div><span><?php echo count($groupe['matieres']); ?> matières</span></div>
			<div class="grille-matieres">
				<?php foreach($groupe['matieres'] as $matiere){
					$lien=$dossier.$prefixe.'_'.$matiere[0].'.php';
				?>
				<a href="<?php echo htmlspecialchars($lien); ?>" class="carte-matiere">
					<span class="carte-matiere-icone"><img src="img-140/<?php echo htmlspecialchars($matiere[2]); ?>" alt=""></span>
					<span class="carte-matiere-texte"><strong><?php echo htmlspecialchars($matiere[1]); ?></strong><small><?php echo htmlspecialchars($matiere[3]); ?></small></span>
					<span class="carte-matiere-fleche" aria-hidden="true">→</span>
				</a>
				<?php } ?>
			</div>
		</section>
		<?php } ?>
	</main>
</body>
</html>
