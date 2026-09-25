<?php
session_start();

if(empty($_SESSION['permission'])){
	header('location:index.php');
	exit();
}

if($_SESSION['role']!='enseignant'){
	header('location:presentation.php');
	exit();
}

require('utils/class/class_bdd.php');

$dbh=bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);

// Meme fonction que dans les deux listes : la base contient encore des
// lignes ecrites en ISO avant la migration.
function nettoyerTexte($texte){
	if($texte===null){ return ''; }
	if(!mb_check_encoding($texte,'UTF-8')){
		$texte=mb_convert_encoding($texte,'UTF-8','ISO-8859-1');
	}
	return trim($texte);
}

// « Français (programmes 2026/2027) » tient mal dans l'en-tete d'un coupon,
// et la mention du programme n'apprend rien a l'eleve. Seule la parenthese
// de programme est retiree : un nom de matiere qui en contiendrait une autre
// n'est pas touche.
function matiereCourte($matiere){
	return trim(preg_replace('/\s*\((?:programmes?|programme)[^)]*\)\s*$/iu','',$matiere));
}

function libelleCoupon($texte){
	return preg_replace('/[½⅓¼⅕⅙⅛⅒]/u','<span class="fraction">$0</span>',htmlspecialchars($texte));
}

$type=(isset($_GET['type'])&&$_GET['type']==='eval')?'eval':'comp';

// ---------------------------------------------------------------------------
// La selection est celle de la liste d'ou vient l'enseignante : les memes
// parametres, lus de la meme facon, pour que la feuille imprimee corresponde
// exactement a l'ecran qu'elle avait sous les yeux.
// ---------------------------------------------------------------------------
$rechercheMatiere=isset($_GET['matiere'])?trim($_GET['matiere']):'';
$rechercheCategorie=isset($_GET['categorie'])?trim($_GET['categorie']):'';
$rechercheTexte=isset($_GET['texte'])?trim($_GET['texte']):'';
$rechercheUtilisation=(isset($_GET['utilisation'])&&$_GET['utilisation']=='zero')?'zero':'';
$voirArchivees=isset($_GET['archivees'])&&$_GET['archivees']==='1';
$exemplaires=(isset($_GET['exemplaires'])&&in_array((int)$_GET['exemplaires'],array(1,2,3,4,5,6),true))?(int)$_GET['exemplaires']:1;

// Un identifiant ou plusieurs, pour le bouton « Coupon » d'une seule ligne.
$identifiants=array();
if(isset($_GET['ids'])&&$_GET['ids']!==''){
	foreach(explode(',',$_GET['ids']) as $brut){
		$n=(int)trim($brut);
		if($n>0){ $identifiants[]=$n; }
	}
}

$parametres=array();

if($type==='comp'){
	$niveauxPossibles=array('CE1','CE2');
	$rechercheNiveau=(isset($_GET['niveau'])&&in_array($_GET['niveau'],$niveauxPossibles))?$_GET['niveau']:'';
	$rechercheCycle=isset($_GET['cycle'])?trim($_GET['cycle']):'';
	$rechercheOrigine=isset($_GET['origine'])?trim($_GET['origine']):'';

	$sql='SELECT id_comp AS id,designation,commentaire,matiere,categorie,niveau
		FROM comp_type WHERE commentaire IS NOT NULL AND commentaire!=\'\'';
	if(!$voirArchivees){ $sql.=' AND archivee=0'; }
	if($rechercheMatiere!==''){ $sql.=' AND matiere=:matiere'; $parametres[':matiere']=$rechercheMatiere; }
	if($rechercheCategorie!==''){ $sql.=' AND categorie=:categorie'; $parametres[':categorie']=$rechercheCategorie; }
	if($rechercheCycle!==''){ $sql.=' AND cycle=:cycle'; $parametres[':cycle']=$rechercheCycle; }
	if($rechercheTexte!==''){ $sql.=' AND commentaire LIKE :texte'; $parametres[':texte']='%'.$rechercheTexte.'%'; }
	if($rechercheOrigine==='officielle'){ $sql.=' AND officiel=1'; }
	if($rechercheOrigine==='enseignant'){ $sql.=' AND officiel=0'; }
	// Comme dans la liste : une competence sans niveau renseigne reste visible.
	if($rechercheNiveau!==''){ $sql.=' AND (niveau=\'\' OR FIND_IN_SET(:niveau,niveau))'; $parametres[':niveau']=$rechercheNiveau; }
	if($rechercheUtilisation==='zero'){ $sql.=' AND (SELECT COUNT(*) FROM comp_eleves WHERE comp_eleves.id_comp=comp_type.id_comp)=0'; }
	if($identifiants){ $sql.=' AND id_comp IN ('.implode(',',$identifiants).')'; }
	$sql.=' ORDER BY matiere ASC, categorie ASC, commentaire ASC';
	$chapeau='Je suis capable de…';
	$retour='liste-competences.php';
	$titreEcran='Coupons de compétences';
}else{
	$rechercheNiveau='';
	// eval_type.niveau n'est PAS un niveau de classe : la liste des
	// connaissances s'en sert comme drapeau et filtre sur niveau=1. La
	// pastille du coupon ne peut donc pas s'appuyer dessus.
	$sql='SELECT id_eval AS id,designation,commentaire,matiere,categorie,\'\' AS niveau
		FROM eval_type WHERE commentaire IS NOT NULL AND commentaire!=\'\' AND niveau=1';
	if(!$voirArchivees){ $sql.=' AND archivee=0'; }
	if($rechercheMatiere!==''){ $sql.=' AND matiere=:matiere'; $parametres[':matiere']=$rechercheMatiere; }
	if($rechercheCategorie!==''){ $sql.=' AND categorie=:categorie'; $parametres[':categorie']=$rechercheCategorie; }
	if($rechercheTexte!==''){ $sql.=' AND commentaire LIKE :texte'; $parametres[':texte']='%'.$rechercheTexte.'%'; }
	if($rechercheUtilisation==='zero'){ $sql.=' AND (SELECT COUNT(*) FROM eval_eleves WHERE eval_eleves.id_eval=eval_type.id_eval)=0'; }
	if($identifiants){ $sql.=' AND id_eval IN ('.implode(',',$identifiants).')'; }
	$sql.=' ORDER BY matiere ASC, categorie ASC, commentaire ASC';
	// « Je connais percevoir les niveaux de langue » ne veut rien dire : les
	// libelles commencent par un infinitif, donc « Je sais » forme une phrase.
	$chapeau='Je sais…';
	$retour='liste-connaissances.php';
	$titreEcran='Coupons de connaissances';
}

$req=$dbh->prepare($sql);
foreach($parametres as $cle=>$valeur){ $req->bindValue($cle,$valeur); }
$req->execute();
$resultats=$req->fetchAll(PDO::FETCH_ASSOC);
$req->closeCursor();

$coupons=array();
foreach($resultats as $ligne){
	for($i=0;$i<$exemplaires;$i++){ $coupons[]=$ligne; }
}

// Le lien de retour rend a l'enseignante la liste exactement telle qu'elle
// l'avait filtree.
$parametresRetour=$_GET;
unset($parametresRetour['type'],$parametresRetour['ids'],$parametresRetour['exemplaires']);
$lienRetour=$retour.($parametresRetour?'?'.http_build_query($parametresRetour):'');

$nombre=count($coupons);
$pages=(int)ceil($nombre/6);
$parametresExemplaires=$_GET;
unset($parametresExemplaires['exemplaires']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($titreEcran); ?></title>
	<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite-3">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
	<style>
	*{ box-sizing:border-box; }
	body{
		margin:0; padding:0 0 60px;
		background:var(--gx-fond,#f2ecc4);
		color:var(--gx-encre,#30343d);
		font-family:Lexend,"Segoe UI",Arial,sans-serif;
	}

	.barre{
		position:sticky; top:0; z-index:5;
		display:flex; flex-wrap:wrap; align-items:center; gap:12px 20px;
		padding:14px 22px;
		background:rgba(255,255,255,.86);
		border-bottom:1px solid var(--gx-sable,rgba(184,177,138,.72));
	}
	.barre h1{ margin:0; font-size:1.02rem; font-weight:600; }
	.barre .compte{ flex:1 1 240px; font-size:.82rem; color:var(--gx-encre-douce,#5f636b); }
	.barre .alerte{ color:#a93226; font-weight:600; }
	.barre form{ margin:0; }
	.barre label{ display:flex; align-items:center; gap:8px; font:600 .78rem/1.2 Lexend,Arial,sans-serif; }
	.barre select{ min-height:44px; border:1px solid var(--gx-sable,rgba(184,177,138,.72)); border-radius:9px; background:#fff; padding:0 10px; font:600 .84rem/1 Lexend,Arial,sans-serif; }
	.barre a,.barre button{
		flex:0 0 auto; min-height:44px; padding:9px 17px;
		border:1px solid var(--gx-sable,rgba(184,177,138,.72)); border-radius:9px;
		background:#fff; color:var(--gx-encre,#30343d);
		font:600 .84rem/1 Lexend,Arial,sans-serif; text-decoration:none; cursor:pointer;
	}
	.barre button{ border-color:transparent; background:var(--gx-action,#0b756a); color:#fff; }
	.barre button:hover,.barre button:focus-visible{ background:var(--gx-action-encre,#095f57); }

	.feuille{
		width:210mm; margin:24px auto; padding:9mm 8mm;
		background:#fff;
		box-shadow:0 10px 34px rgba(30,28,20,.18);
	}
	.grille{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:6mm; }

	.coupon{
		display:flex; flex-direction:column; min-height:82mm; overflow:hidden;
		border:1px solid var(--gx-sable,rgba(184,177,138,.72)); border-radius:10px;
		background:#fff;
	}
	.coupon-entete{
		display:flex; align-items:flex-start; gap:10px;
		padding:9px 11px 8px;
		border-bottom:1px solid rgba(184,177,138,.45);
	}
	.pastille{
		flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center;
		min-width:38px; height:22px; padding:0 8px; border-radius:11px;
		background:var(--gx-action,#0b756a); color:#fff;
		font:700 .68rem/1 Lexend,Arial,sans-serif; letter-spacing:.04em;
	}
	.coupon-identite{ flex:1 1 auto; min-width:0; }
	.matiere{ margin:0; font:600 .78rem/1.25 Lexend,Arial,sans-serif; }
	.categorie{ margin:1px 0 0; font:400 .68rem/1.3 Lexend,Arial,sans-serif; color:var(--gx-encre-douce,#5f636b); }
	.marque{ flex:0 0 auto; align-self:center; display:block; width:21mm; height:auto; }

	.coupon-corps{ flex:1 1 auto; display:flex; flex-direction:column; padding:10px 11px 0; }
	.chapeau{
		align-self:flex-start; margin:0 0 7px; padding:3px 9px; border-radius:7px;
		background:#e6f8f5; color:var(--gx-action-encre,#095f57);
		font:600 .64rem/1.3 Lexend,Arial,sans-serif; letter-spacing:.05em; text-transform:uppercase;
	}
	.libelle{ margin:0; font:500 .82rem/1.38 Lexend,Arial,sans-serif; text-wrap:pretty; }
	.libelle .fraction{ display:inline-block; font-size:1.35em; font-weight:600; line-height:1; vertical-align:-.08em; }

	.zone-code{ margin-top:auto; padding:9px 11px 4px; text-align:center; }
	.zone-code svg{ display:block; width:62mm; height:13mm; margin:0 auto; }
	.zone-code svg rect{ fill:var(--gx-encre,#30343d); }
	.code-impossible{ font:600 .68rem/1.3 Lexend,Arial,sans-serif; color:#a93226; }
	.code-lisible{
		margin:3px 0 0; font:400 .58rem/1 Consolas,"SFMono-Regular",monospace;
		letter-spacing:.14em; color:var(--gx-encre-douce,#5f636b);
	}

	.ligne-date{
		display:flex; align-items:baseline; gap:5px; padding:6px 11px 9px;
		font:500 .74rem/1.4 Lexend,Arial,sans-serif;
	}
	.ligne-date .trait{ flex:1 1 auto; height:.95em; border-bottom:1px solid var(--gx-encre-douce,#5f636b); }
	.ligne-date .trait.court{ flex:0 0 30px; }

	.bareme{ display:grid; grid-template-columns:repeat(4,1fr); border-top:1px solid var(--gx-sable,rgba(184,177,138,.72)); }
	.bareme span{
		padding:6px 2px; border-left:1px solid rgba(184,177,138,.45);
		font:700 .76rem/1.2 Lexend,Arial,sans-serif; text-align:center;
	}
	.bareme span:first-child{ border-left:0; }

	.vide{ margin:60px auto; max-width:520px; text-align:center; color:var(--gx-encre-douce,#5f636b); }

	@media print{
		@page{ size:A4 portrait; margin:0; }
		body{ background:#fff; padding:0; }
		.barre{ display:none; }
		.feuille{ width:auto; margin:0; padding:9mm 8mm; box-shadow:none; }
		.coupon{ break-inside:avoid; }
		.marque,.pastille,.chapeau{ -webkit-print-color-adjust:exact; print-color-adjust:exact; }
	}

	@media screen and (max-width:900px){
		.feuille{ width:auto; max-width:100%; padding:14px; }
		.grille{ grid-template-columns:1fr; }
		.coupon{ min-height:0; }
	}
	</style>
</head>
<body class="gx-app-fasteval">

<div class="barre">
	<h1><?php echo htmlspecialchars($titreEcran); ?></h1>
	<span class="compte">
		<?php echo $nombre; ?> coupon<?php echo $nombre>1?'s':''; ?>,
		<?php echo $pages; ?> feuille<?php echo $pages>1?'s':''; ?> A4
		<span id="avertissement" class="alerte" hidden></span>
	</span>
	<?php if(count($resultats)){ ?>
	<form method="get">
		<?php foreach($parametresExemplaires as $cle=>$valeur){ ?>
			<?php if(!is_scalar($valeur)){ continue; } ?>
			<input type="hidden" name="<?php echo htmlspecialchars($cle); ?>" value="<?php echo htmlspecialchars($valeur); ?>">
		<?php } ?>
		<label for="exemplaires">Exemplaires par coupon
			<select name="exemplaires" id="exemplaires" onchange="this.form.submit()">
				<?php foreach(array(1,2,3,4,5,6) as $nombreExemplaires){ ?>
				<option value="<?php echo $nombreExemplaires; ?>" <?php echo $exemplaires===$nombreExemplaires?'selected':''; ?>><?php echo $nombreExemplaires; ?></option>
				<?php } ?>
			</select>
		</label>
	</form>
	<?php } ?>
	<a class="gx-bouton" href="<?php echo htmlspecialchars($lienRetour); ?>">&larr; Retour à la liste</a>
	<?php if($nombre){ ?><button class="gx-bouton gx-bouton-principal" type="button" onclick="window.print()">Imprimer</button><?php } ?>
</div>

<?php if(!$nombre){ ?>
	<p class="vide">Aucune compétence ne correspond à cette sélection.
	Retourne à la liste, change les filtres, puis redemande les coupons.</p>
<?php }else{ ?>
<div class="feuille">
	<div class="grille">
<?php foreach($coupons as $ligne){
		$code=nettoyerTexte($ligne['designation']);
		$libelle=nettoyerTexte($ligne['commentaire']);
		$matiere=matiereCourte(nettoyerTexte($ligne['matiere']));
		$categorie=nettoyerTexte($ligne['categorie']);
		// La pastille porte le niveau demande s'il y en a un, sinon celui de
		// la ligne. Une competence sans niveau n'en affiche pas du tout.
		$pastille=$rechercheNiveau!==''?$rechercheNiveau:nettoyerTexte($ligne['niveau']);
?>
		<article class="coupon">
			<header class="coupon-entete">
				<?php if($pastille!==''){ ?><span class="pastille"><?php echo htmlspecialchars($pastille); ?></span><?php } ?>
				<div class="coupon-identite">
					<p class="matiere"><?php echo htmlspecialchars($matiere); ?></p>
					<?php if($categorie!==''){ ?><p class="categorie"><?php echo htmlspecialchars($categorie); ?></p><?php } ?>
				</div>
				<img class="marque" src="utils/img/logofasteval.png" alt="Fast Éval">
			</header>
			<div class="coupon-corps">
				<span class="chapeau"><?php echo htmlspecialchars($chapeau); ?></span>
				<p class="libelle"><?php echo libelleCoupon($libelle); ?></p>
			</div>
			<div class="zone-code">
				<span class="case-code" data-code="<?php echo htmlspecialchars($code); ?>"></span>
				<p class="code-lisible"><?php echo htmlspecialchars($code); ?></p>
			</div>
			<div class="ligne-date">Date :<span class="trait"></span>/<span class="trait"></span>/ 20<span class="trait court"></span></div>
			<div class="bareme"><span>NA</span><span>PA</span><span>A</span><span>D</span></div>
		</article>
<?php } ?>
	</div>
</div>
<?php } ?>

<script src="utils/js/code128.js?v=20260829-01"></script>
<script>
(function(){
	var refus=CodeBarres.dessiner('.case-code');
	if(refus>0){
		var z=document.getElementById('avertissement');
		z.textContent=' — '+refus+' code'+(refus>1?'s':'')+' non imprimable'+(refus>1?'s':'')+'.';
		z.hidden=false;
	}
})();
</script>

</body>
</html>
