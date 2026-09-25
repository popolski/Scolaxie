<?php if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!ISSET($_SESSION['role']))
{
header('location:index.php');
exit();
}
?>


<!DOCTYPE html>

<html lang="fr">
    <head>
        <title>School Monsters - Portail</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/grille.css?v=20260831-02"/>
        <link rel="stylesheet" href="css/style1.css?v=20260912-conformite"/>
        <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">

    </head>
    <body class="page-choix-periodes gx-typo gx-app-schoolmonsters <?php echo $_SESSION['role']==='enseignant' ? 'gx-enseignant' : 'gx-eleve'; ?>">

	<a class="skip-link" href="#contenu">Aller au contenu</a>

	<!-- En-tete commun aux trois sites. L'ancien bandeau (grand logo centre,
	     « Bienvenue untel », bouton en dessous) devient une barre : meme
	     composition que sur Fast Eval et Clic & Mots, avec le violet de School
	     Monsters en lisere a gauche pour qu'on sache tout de suite ou l'on est.
	     La phrase d'accueil, elle, reste : c'est elle qui dit quoi faire. -->
	<header class="gx-entete gx-entete-cadree">
		<img class="gx-entete-logo" src="img-140/logo_school_monsters.png?v=20260906-01" alt="School Monsters">
		<nav class="fil" aria-label="Fil d'Ariane">
			<a href="/portail/">Portail</a><span aria-hidden="true">›</span>
			<?php if($_SESSION['role']=='enseignant'){ ?>
			<a class="sm-fil-retour-espace" href="enseignant.php">Mon espace</a><span class="sm-fil-retour-separateur" aria-hidden="true">›</span>
			<?php } ?>
			<span class="actuel">Accueil</span>
		</nav>
		<?php
		require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
		echo gxMenuIdentite('School Monsters');
		?>
	</header>

	<main class="sm-page sm-periodes gx-largeur-grille" id="contenu">
	<section class="sm-hero sm-periodes-hero gx-ligne-titre" aria-labelledby="titre-periodes">
		<div class="sm-hero-contenu">
			<p class="sm-surtitre">School Monsters</p>
			<h1 id="titre-periodes">Choisissez une période pour vous entraîner.</h1>
			<p>Retrouvez les leçons et les exercices de votre classe.</p>
		</div>
	</section>

	<?php if(isset($_GET['acces'])&&$_GET['acces']==='refuse'){ ?>
	<!-- L'eleve arrive ici parce que la garde d'acces l'a renvoye : il a tape
	     une adresse, ou garde un ancien lien vers une periode qui n'est pas
	     ouverte pour lui. Sans ce mot, il retombe sur l'accueil sans comprendre
	     pourquoi. Le ton s'adresse a un enfant de CE1. -->
	<div class="gx-bandeau gx-bandeau-info sm-message-acces">
		Cette page n’est pas encore ouverte pour toi. Choisis une période éclairée ci-dessous.
	</div>
	<?php } ?>

<?php
// Les dix periodes etaient ecrites en dur, toutes cliquables, pour tout le
// monde : l'accueil ne lisait pas les droits que sso.php pose pourtant en
// session a la connexion. Depuis la garde du 31/08/2026 une periode fermee
// renvoie l'eleve ici avec un message, ce qui marchait mais restait desagreable :
// rien ne lui disait a l'avance ou il pouvait aller.
//
// Une periode fermee reste donc VISIBLE mais eteinte, et n'est plus un lien.
// L'eleve voit ce qui l'attend sans pouvoir y aller, ce qui vaut mieux que de
// lui cacher la moitie du site. L'enseignante, elle, voit tout allume.
$smProf = (isset($_SESSION['role']) && $_SESSION['role'] === 'enseignant');
$smImages = array(
    'ce1' => array(1 => '151.png', 2 => '87.png', 3 => '3.png', 4 => '6.png', 5 => '10.png'),
    'ce2' => array(1 => '135.png', 2 => '46.png', 3 => '93.png', 4 => '123.png', 5 => '57.png'),
);
function smGrillePeriodes($classe, array $images, $prof)
{
    for ($i = 1; $i <= 5; $i++) {
        $cle = 'p' . $i . $classe;
        $ouverte = $prof || (isset($_SESSION[$cle]) && $_SESSION[$cle] !== '#');
        $page = 'P' . $i . '_' . strtoupper($classe) . '.php';
        $img = 'img-140/' . $images[$i];
        $libelle = 'Période ' . $i;
        if ($ouverte) {
            echo "						<a href=\"$page\" class=\"carte-periode\">"
               . "<span class=\"carte-periode-mascotte\"><img src=\"$img\" alt=\"\"></span>"
               . "<span class=\"carte-periode-texte\"><strong>$libelle</strong><small>Ouvrir la période</small></span>"
               . "<span class=\"carte-periode-fleche\" aria-hidden=\"true\">→</span></a>
";
        } else {
            // Un <span> et non un <a> : rien a cliquer, rien a ouvrir dans un
            // nouvel onglet, et le lecteur d'ecran ne l'annonce pas comme un lien.
            echo "						<span class=\"carte-periode carte-periode-fermee\""
               . " aria-disabled=\"true\">"
               . "<span class=\"carte-periode-mascotte\"><img src=\"$img\" alt=\"\"></span>"
               . "<span class=\"carte-periode-texte\"><strong>$libelle</strong>"
               . "<small class=\"carte-periode-etat\">Pas encore ouverte</small></span></span>
";
        }
    }
}
?>

			<div class="niveaux-school-monsters">
				<section class="bloc-niveau bloc-ce1">
					<div class="entete-niveau">
						<span class="pastille-niveau">CE1</span>
						<div><h2>Espace CE1</h2><p>Les cinq périodes de l’année.</p></div>
					</div>
					<div class="grille-periodes">
						<?php smGrillePeriodes('ce1', $smImages['ce1'], $smProf); ?>
					</div>
				</section>

				<section class="bloc-niveau bloc-ce2">
					<div class="entete-niveau">
						<span class="pastille-niveau">CE2</span>
						<div><h2>Espace CE2</h2><p>Les cinq périodes de l’année.</p></div>
					</div>
					<div class="grille-periodes">
						<?php smGrillePeriodes('ce2', $smImages['ce2'], $smProf); ?>
					</div>
				</section>
			</div>
	</main>
		
    </body>
</html>
