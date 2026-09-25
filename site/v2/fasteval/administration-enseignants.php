<?php
session_start();

if(!$_SESSION['permission']){
	header('location:index.php');
	exit();
}

if($_SESSION['role']!='enseignant'||!$_SESSION['est_admin']){
	header('location:presentation.php');
	exit();
}

require('utils/class/class_bdd.php');

$dbh=bdd::connexion(scolaxieConfig('SCOLAXIE_DB_NAME'));
$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);

$message='';

if(isset($_POST['action'])&&$_POST['action']=='creer_etablissement'){

	$nomEtab=htmlspecialchars(trim($_POST['nom_etablissement']));
	$villeEtab=htmlspecialchars(trim($_POST['ville_etablissement']));
	$adresseEtab=htmlspecialchars(trim($_POST['adresse_etablissement']));
	$departementEtab=htmlspecialchars(trim($_POST['departement_etablissement']));
	$telEtab=htmlspecialchars(trim($_POST['tel_etablissement']));

	if($nomEtab==''||$villeEtab==''){

		$message="Le nom et la ville de l'établissement sont obligatoires.";

	}else{

		$req=$dbh->prepare('INSERT INTO etablissement (nom,ville,adresse,departement,tel) VALUES (:nom,:ville,:adresse,:departement,:tel)');
		$req->bindParam(':nom',$nomEtab);
		$req->bindParam(':ville',$villeEtab);
		$req->bindParam(':adresse',$adresseEtab);
		$req->bindParam(':departement',$departementEtab);
		$req->bindParam(':tel',$telEtab);
		$req->execute();

		$message="Établissement \"".$nomEtab."\" créé. Tu peux maintenant l'assigner à un enseignant.";
	}
}

if(isset($_POST['action'])&&$_POST['action']=='creer'){

	$nom=strtoupper(htmlspecialchars(trim($_POST['nom'])));
	$prenom=htmlspecialchars(trim($_POST['prenom']));
	$classe1=htmlspecialchars(trim($_POST['classe1']));
	$identifiant=htmlspecialchars(trim($_POST['identifiant']));
	$motDePasse=htmlspecialchars(trim($_POST['mot_de_passe']));
	$idEtablissementChoisi=(int)$_POST['id_etablissement'];
	$estAdmin=isset($_POST['est_admin'])?1:0;

	if($nom==''||$prenom==''||$classe1==''||$identifiant==''||$motDePasse==''||$idEtablissementChoisi==0){

		$message="Tous les champs sont obligatoires.";

	}else{

		$req=$dbh->prepare('SELECT id_client FROM ayant_droit WHERE identifiant=:identifiant');
		$req->bindParam(':identifiant',$identifiant);
		$req->execute();
		$dejaPris=(bool)$req->fetch();
		$req->closeCursor();

		if($dejaPris){

			$message="Cet identifiant est déjà utilisé par un autre compte.";

		}else{

			$idEtablissement=$idEtablissementChoisi;

			$req=$dbh->prepare('INSERT INTO enseignant (nom,prenom,classe1) VALUES (:nom,:prenom,:classe1)');
			$req->bindParam(':nom',$nom);
			$req->bindParam(':prenom',$prenom);
			$req->bindParam(':classe1',$classe1);
			$req->execute();
			$idEnseignant=$dbh->lastInsertId();

			$req=$dbh->prepare('INSERT INTO info_client (id_etablissement,nom,prenom) VALUES (:id_etablissement,:nom,:prenom)');
			$req->bindParam(':id_etablissement',$idEtablissement);
			$req->bindParam(':nom',$nom);
			$req->bindParam(':prenom',$prenom);
			$req->execute();
			$idClient=$dbh->lastInsertId();

			$req=$dbh->prepare('INSERT INTO ayant_droit (identifiant,code,id_client,id_enseignant,bdd,est_admin,date_enr,instant_visite,nb_visite) VALUES (:identifiant,:code,:id_client,:id_enseignant,:bdd,:est_admin,NOW(),NOW(),0)');
			$req->bindParam(':identifiant',$identifiant);
			$req->bindParam(':code',$motDePasse);
			$req->bindParam(':id_client',$idClient);
			$req->bindParam(':id_enseignant',$idEnseignant);
			$req->bindParam(':bdd',$_SESSION['bdd']);
			$req->bindParam(':est_admin',$estAdmin);
			$req->execute();

			$message="Le compte de ".$prenom." ".$nom." a été créé. Identifiant : ".$identifiant.".";
		}
	}
}

if(isset($_POST['action'])&&$_POST['action']=='modifier'){

	$idClientModif=(int)$_POST['id_client'];
	$idEnseignantModif=(int)$_POST['id_enseignant'];
	$nom=strtoupper(htmlspecialchars(trim($_POST['nom'])));
	$prenom=htmlspecialchars(trim($_POST['prenom']));
	$classe1=htmlspecialchars(trim($_POST['classe1']));
	$identifiant=htmlspecialchars(trim($_POST['identifiant']));
	$motDePasse=htmlspecialchars(trim($_POST['mot_de_passe']));
	$idEtablissementChoisi=(int)$_POST['id_etablissement'];
	$estAdmin=isset($_POST['est_admin'])?1:0;

	if($idClientModif==$_SESSION['id_client']){
		$estAdmin=1;
	}

	if($nom==''||$prenom==''||$classe1==''||$identifiant==''||$idEtablissementChoisi==0){

		$message="Tous les champs sont obligatoires.";

	}else{

		$req=$dbh->prepare('SELECT id_client FROM ayant_droit WHERE identifiant=:identifiant AND id_client!=:id_client');
		$req->bindParam(':identifiant',$identifiant);
		$req->bindParam(':id_client',$idClientModif);
		$req->execute();
		$dejaPris=(bool)$req->fetch();
		$req->closeCursor();

		if($dejaPris){

			$message="Cet identifiant est déjà utilisé par un autre compte.";

		}else{

			$req=$dbh->prepare('UPDATE enseignant SET nom=:nom,prenom=:prenom,classe1=:classe1 WHERE id_enseignant=:id_enseignant');
			$req->bindParam(':nom',$nom);
			$req->bindParam(':prenom',$prenom);
			$req->bindParam(':classe1',$classe1);
			$req->bindParam(':id_enseignant',$idEnseignantModif);
			$req->execute();

			$req=$dbh->prepare('UPDATE info_client SET nom=:nom,prenom=:prenom,id_etablissement=:id_etablissement WHERE id_client=:id_client');
			$req->bindParam(':nom',$nom);
			$req->bindParam(':prenom',$prenom);
			$req->bindParam(':id_etablissement',$idEtablissementChoisi);
			$req->bindParam(':id_client',$idClientModif);
			$req->execute();

			// Un mot de passe vide conserve le mot de passe existant. L'interface
			// ne relit donc jamais la valeur actuelle pour la remettre dans le DOM.
			$sqlAcces='UPDATE ayant_droit SET identifiant=:identifiant,est_admin=:est_admin';
			if($motDePasse!==''){ $sqlAcces.=',code=:code'; }
			$sqlAcces.=' WHERE id_client=:id_client';
			$req=$dbh->prepare($sqlAcces);
			$req->bindParam(':identifiant',$identifiant);
			if($motDePasse!==''){ $req->bindParam(':code',$motDePasse); }
			$req->bindParam(':est_admin',$estAdmin);
			$req->bindParam(':id_client',$idClientModif);
			$req->execute();

			$message="Le compte de ".$prenom." ".$nom." a été modifié.";
		}
	}
}

if(isset($_POST['action'])&&$_POST['action']=='supprimer'){

	$idClientSuppr=(int)$_POST['id_client'];

	if($idClientSuppr==$_SESSION['id_client']){

		$message="Tu ne peux pas supprimer ton propre accès.";

	}else{

		// on ne retire que l'acces de connexion : les eleves et evaluations de l'enseignant restent intacts
		$req=$dbh->prepare('DELETE FROM ayant_droit WHERE id_client=:id_client');
		$req->bindParam(':id_client',$idClientSuppr);
		$req->execute();

		$message="L'accès a été supprimé. Les élèves et évaluations associés sont conservés.";
	}
}

if(isset($_POST['action'])&&$_POST['action']=='supprimer_etablissement'){

	$idEtablissementSuppr=(int)$_POST['id_etablissement'];

	$req=$dbh->prepare('SELECT etablissement.nom,COUNT(info_client.id_client) AS nb_enseignants FROM etablissement LEFT JOIN info_client ON info_client.id_etablissement=etablissement.id_etablissement WHERE etablissement.id_etablissement=:id_etablissement GROUP BY etablissement.id_etablissement,etablissement.nom');
	$req->bindParam(':id_etablissement',$idEtablissementSuppr);
	$req->execute();
	$etablissementSuppr=$req->fetch(PDO::FETCH_ASSOC);
	$req->closeCursor();

	if(!$etablissementSuppr){
		$message="Cet établissement n'existe plus.";
	}else if((int)$etablissementSuppr['nb_enseignants']>0){
		$message="Impossible de supprimer l'établissement \"".$etablissementSuppr['nom']."\" : un ou plusieurs enseignants y sont encore rattachés. Modifie d'abord leur établissement.";
	}else{
		$req=$dbh->prepare('DELETE FROM etablissement WHERE id_etablissement=:id_etablissement');
		$req->bindParam(':id_etablissement',$idEtablissementSuppr);
		$req->execute();
		$req->closeCursor();

		$message="L'établissement \"".$etablissementSuppr['nom']."\" a été supprimé.";
	}
}

$req=$dbh->query('SELECT id_etablissement,nom,ville,adresse,departement,tel,(SELECT COUNT(*) FROM info_client WHERE info_client.id_etablissement=etablissement.id_etablissement) AS nb_enseignants FROM etablissement ORDER BY nom ASC');
$etablissements=$req->fetchAll(PDO::FETCH_ASSOC);

$enseignantAModifier=null;
if(isset($_GET['modifier'])){
	$req=$dbh->prepare('SELECT ayant_droit.id_client,ayant_droit.id_enseignant,ayant_droit.identifiant,ayant_droit.est_admin,enseignant.nom,enseignant.prenom,enseignant.classe1,info_client.id_etablissement FROM ayant_droit LEFT JOIN enseignant ON enseignant.id_enseignant=ayant_droit.id_enseignant LEFT JOIN info_client ON info_client.id_client=ayant_droit.id_client WHERE ayant_droit.id_client=:id_client');
	$req->bindParam(':id_client',$_GET['modifier']);
	$req->execute();
	$enseignantAModifier=$req->fetch(PDO::FETCH_ASSOC);
	$req->closeCursor();
}

$req=$dbh->prepare('SELECT ayant_droit.id_client,ayant_droit.id_enseignant,ayant_droit.identifiant,enseignant.nom,enseignant.prenom,enseignant.classe1,ayant_droit.est_admin,etablissement.nom AS nom_etablissement,etablissement.ville AS ville_etablissement FROM ayant_droit LEFT JOIN enseignant ON enseignant.id_enseignant=ayant_droit.id_enseignant LEFT JOIN info_client ON info_client.id_client=ayant_droit.id_client LEFT JOIN etablissement ON etablissement.id_etablissement=info_client.id_etablissement ORDER BY enseignant.nom ASC');
$req->execute();
$enseignants=$req->fetchAll(PDO::FETCH_ASSOC);
$req->closeCursor();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Administration des enseignants</title>

    <link href="utils/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="utils/style.css" rel="stylesheet">
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="utils/refonte-pages-v2.css?v=20260912-conformite">

    <style>
        body{font-size:16px;}
        .admin-page{padding-bottom:45px;}

        .message-admin{max-width:1068px;margin:0 auto 18px;padding:11px 15px;border:1px solid rgba(22,184,170,.35);border-radius:9px;background:rgba(255,255,255,.68);color:#28756e;font-size:14px;}
        .section-encadree{max-width:1068px;margin:0 auto 24px;padding:24px;background:rgba(255,255,255,.62);border:1px solid rgba(48,52,61,.12);border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.05);}
        .section-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:17px;}
        .section-encadree h2,.section-encadree h3{margin:0;color:#30343d;font-size:21px;font-weight:600;}
        .section-description{margin:5px 0 0;color:#74777d;font-size:13px;line-height:1.45;}
        .section-admin-accent{border-top:4px solid var(--gx-role);}

        .table-responsive-fasteval{width:100%;overflow-x:auto;border-radius:9px;}
        .table{min-width:780px;margin-bottom:0;background:#fff;font-size:14px;}
        .table td,.table th{vertical-align:middle!important;border:1px solid #d2d0c5!important;padding:8px 9px!important;}
        .table thead th{background:#12baa6;color:#fff;border-color:#0d9889!important;font-weight:600;white-space:nowrap;}
        .table tbody tr:nth-child(even){background:#f7f6ef;}
        .table tbody tr:hover{background:#f0f8f6;}
        .ligne-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
		.etablissement-utilise{display:inline-block;padding:4px 8px;border-radius:12px;background:#ece9dc;color:#6b675d;font-size:12px;white-space:nowrap;}

        .form-admin{max-width:720px;}
        .form-admin label{display:block;margin:0 0 6px;color:#3a3a3c;font-size:14px;font-weight:600;}
        .form-admin input,.form-admin select{width:100%;height:44px;box-sizing:border-box;border:1px solid #c9c5ae;border-radius:7px;font-size:15px;box-shadow:none;}
        .form-admin input:focus,.form-admin select:focus{border-color:#16b8aa;box-shadow:0 0 0 3px rgba(22,184,170,.13);}
        .form-admin .champ{margin-bottom:15px;}
        .form-admin .champ-checkbox{display:flex;align-items:center;gap:9px;padding:10px 12px;border:1px solid #d9d5c3;border-radius:8px;background:rgba(255,255,255,.55);}
        .form-admin .champ-checkbox input{width:auto;height:auto;margin:0;}
        .form-admin .champ-checkbox label{margin:0;}
        .aide-admin{margin:-6px 0 14px;color:#6b6355;font-size:13px;}

        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;box-sizing:border-box;border-radius:7px;font-size:13px;transition:background .15s ease,border-color .15s ease,transform .15s ease;}
        .btn:hover,.btn:focus{transform:translateY(-1px);}
        .btn-color{padding:9px 18px;background:#16b8aa;border-color:#16a899;color:#fff;font-size:14px;font-weight:600;}
        .btn-color:hover,.btn-color:focus{background:#10998d;border-color:#10998d;color:#fff;}
        .btn-secondary{background:#697079;border-color:#697079;color:#fff;}
        .btn-secondary:hover,.btn-secondary:focus{background:#555b63;border-color:#555b63;color:#fff;}
        .btn-supprimer{min-height:44px;padding:7px 10px;border:1px solid #c0392b;border-radius:7px;background:#c0392b;color:#fff;font-size:12px;cursor:pointer;}
        .btn-supprimer:hover,.btn-supprimer:focus{background:#a93226;border-color:#a93226;color:#fff;transform:translateY(-1px);}
        .form-actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center;margin-top:4px;}
        .form-actions .btn-secondary{padding:9px 18px;font-size:14px;}

        @media(max-width:767px){
            .section-encadree{padding:19px 15px;}
            .form-actions{align-items:stretch;}
            .form-actions .btn{width:100%;}
            .table-responsive-fasteval{overflow:visible;border-radius:0;}
            .table{display:block;min-width:0;background:transparent;}
            .table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
            .table tbody{display:grid;gap:12px;}
            .table tbody tr{display:block;overflow:hidden;border:1px solid #d2d0c5;border-radius:11px;background:#fff!important;box-shadow:0 4px 12px rgba(0,0,0,.05);}
            .table tbody td{display:grid;grid-template-columns:minmax(96px,.72fr) minmax(0,1.28fr);gap:12px;align-items:center;width:100%;box-sizing:border-box;border:0!important;border-bottom:1px solid #ece9de!important;}
            .table tbody td:last-child{border-bottom:0!important;}
            .table tbody td::before{content:attr(data-intitule);color:#74777d;font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
            .table tbody td[colspan]{display:block;text-align:center;}
            .table tbody td[colspan]::before{content:none;}
            .ligne-actions{justify-content:flex-start;}
        }
    </style>

    </head>

    <body class="gx-typo gx-app-fasteval fe-page fe-page-administration">

     <a class="skip-link" href="#contenu">Aller au contenu</a>



        <!-- En-tete commun aux trois sites : grand logo centre, retour vers
             Fast Eval et vers le portail, identite a droite. Meme composition
             que gestion-classe.php et les deux pages de liste. -->
        <header class="gx-entete gx-entete-cadree">
            <img class="gx-entete-logo" src="utils/img/logofasteval.png" alt="Fast Éval">
            <nav class="fil" aria-label="Fil d'Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><a href="presentation.php">Accueil</a><span aria-hidden="true">›</span><span class="actuel">Administration des enseignants</span></nav>
            <?php
            require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
            echo gxMenuIdentite('Fast Éval');
            ?>
        </header>
<div class="container admin-page gx-largeur-grille" id="haut-page">

        <div class="gx-cartouche gx-entete-cadree fe-hero gx-ligne-titre" id="contenu">
            <span class="fe-surtitre">Administration Fast Eval</span>
            <h1>Administration des enseignants</h1>
            <p>Gérez les comptes enseignants et les établissements associés aux bulletins.</p>
        </div>

		<?php if($message!=''){ ?>
		<div class="message-admin text-center"><?php echo htmlspecialchars($message); ?></div>
		<?php } ?>

        <div class="fe-admin-comptes">
		<div class="section-encadree section-admin-accent">

			<div class="section-header">
				<div>
					<h2>Enseignants existants</h2>
					<p class="section-description">Comptes utilisables sur FastEval et School Monsters.</p>
				</div>
			</div>

			<div class="table-responsive-fasteval">
			<table class="table table-bordered">
				<thead>
					<tr><th>Compte</th><th>Rattachement</th><th>Actions</th></tr>
				</thead>
				<tbody>
					<?php foreach($enseignants as $ens){ ?>
					<tr>
						<td data-intitule="Compte"><strong><?php echo ucfirst(htmlspecialchars($ens['prenom'])).' '.strtoupper(htmlspecialchars($ens['nom'])); ?></strong><small>Identifiant : <?php echo htmlspecialchars($ens['identifiant']); ?></small><small><?php echo $ens['est_admin']?'Administrateur':'Enseignant'; ?></small></td>
                        <td data-intitule="Rattachement"><?php echo htmlspecialchars($ens['classe1']); ?><small><?php echo $ens['nom_etablissement']?htmlspecialchars($ens['nom_etablissement']).' ('.htmlspecialchars($ens['ville_etablissement']).')':'Aucun établissement'; ?></small></td>
                        <td data-intitule="Actions">
							<div class="ligne-actions">
								<a href="administration-enseignants.php?modifier=<?php echo (int)$ens['id_client']; ?>#form-enseignant" class="btn btn-sm btn-secondary">Modifier</a>
								<?php if($ens['id_client']!=$_SESSION['id_client']){ ?>
								<form method="post" action="administration-enseignants.php" onsubmit="return confirm('Supprimer l\'accès de connexion de cet enseignant ? Ses élèves et évaluations resteront conservés.');" style="margin:0;">
									<input type="hidden" name="action" value="supprimer">
									<input type="hidden" name="id_client" value="<?php echo (int)$ens['id_client']; ?>">
									<button type="submit" class="btn-supprimer">Supprimer</button>
								</form>
								<?php } ?>
							</div>
						</td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
			</div>

		</div>

		<div class="section-encadree" id="form-enseignant">

			<div class="section-header">
				<div>
					<h3><?php echo $enseignantAModifier?'Modifier un compte enseignant':'Créer un compte enseignant'; ?></h3>
					<p class="section-description">Le compte fonctionnera sur FastEval et sur School Monsters, avec le même identifiant et mot de passe.</p>
				</div>
			</div>

			<form method="post" action="administration-enseignants.php" class="form-admin">
				<input type="hidden" name="action" value="<?php echo $enseignantAModifier?'modifier':'creer'; ?>">
				<?php if($enseignantAModifier){ ?>
				<input type="hidden" name="id_client" value="<?php echo (int)$enseignantAModifier['id_client']; ?>">
				<input type="hidden" name="id_enseignant" value="<?php echo (int)$enseignantAModifier['id_enseignant']; ?>">
				<?php } ?>

				<div class="champ">
					<label for="nom">Nom</label>
					<input type="text" class="form-control" name="nom" id="nom" value="<?php echo $enseignantAModifier?htmlspecialchars($enseignantAModifier['nom']):''; ?>" required>
				</div>

				<div class="champ">
					<label for="prenom">Prénom</label>
					<input type="text" class="form-control" name="prenom" id="prenom" value="<?php echo $enseignantAModifier?htmlspecialchars($enseignantAModifier['prenom']):''; ?>" required>
				</div>

				<div class="champ">
					<label for="classe1">Classe</label>
					<select class="form-control" name="classe1" id="classe1" required>
						<option value="CE1" <?php echo ($enseignantAModifier&&$enseignantAModifier['classe1']=='CE1')?'selected':''; ?>>CE1</option>
						<option value="CE2" <?php echo ($enseignantAModifier&&$enseignantAModifier['classe1']=='CE2')?'selected':''; ?>>CE2</option>
					</select>
				</div>

				<div class="champ">
					<label for="identifiant">Identifiant</label>
					<input type="text" class="form-control" name="identifiant" id="identifiant" autocomplete="off" value="<?php echo $enseignantAModifier?htmlspecialchars($enseignantAModifier['identifiant']):''; ?>" required>
				</div>

				<div class="champ">
					<label for="mot_de_passe"><?php echo $enseignantAModifier?'Nouveau mot de passe':'Mot de passe'; ?></label>
					<input type="password" class="form-control" name="mot_de_passe" id="mot_de_passe" autocomplete="new-password"<?php echo $enseignantAModifier?'':' required'; ?>>
					<?php if($enseignantAModifier){ ?><p class="aide-admin" style="margin:6px 0 0">Laissez vide pour conserver le mot de passe actuel.</p><?php } ?>
				</div>

				<div class="champ">
					<label for="id_etablissement">Établissement (utilisé sur les bulletins PDF)</label>
					<select class="form-control" name="id_etablissement" id="id_etablissement" required>
						<option value="">-- Choisir --</option>
						<?php foreach($etablissements as $etab){ ?>
						<option value="<?php echo (int)$etab['id_etablissement']; ?>" <?php echo ($enseignantAModifier&&$enseignantAModifier['id_etablissement']==$etab['id_etablissement'])?'selected':''; ?>><?php echo htmlspecialchars($etab['nom']).' ('.htmlspecialchars($etab['ville']).')'; ?></option>
						<?php } ?>
					</select>
				</div>

				<div class="champ champ-checkbox">
					<input type="checkbox" name="est_admin" id="est_admin" value="1" <?php echo ($enseignantAModifier&&$enseignantAModifier['est_admin'])?'checked':''; ?> <?php echo ($enseignantAModifier&&$enseignantAModifier['id_client']==$_SESSION['id_client'])?'disabled':''; ?>>
					<label for="est_admin">Compte administrateur (accès à cette page)</label>
				</div>
				<?php if($enseignantAModifier&&$enseignantAModifier['id_client']==$_SESSION['id_client']){ ?>
				<p class="aide-admin">Tu ne peux pas retirer tes propres droits admin.</p>
				<?php } ?>

				<div class="form-actions">
				<button type="submit" class="btn btn-color"><?php echo $enseignantAModifier?'Enregistrer les modifications':'Créer le compte'; ?></button>
				<?php if($enseignantAModifier){ ?>
				<a href="administration-enseignants.php" class="btn btn-secondary">Annuler</a>
				<?php } ?>
				</div>
			</form>

		</div>

        </div>
        <details class="fe-admin-etablissements">
            <summary>Établissements</summary>
		<div class="section-encadree">

			<div class="section-header">
				<div>
					<h3>Établissements</h3>
					<p class="section-description">Le nom et l'adresse apparaissent sur les bulletins PDF. Chaque enseignant est rattaché à un établissement.</p>
				</div>
			</div>

			<div class="table-responsive-fasteval">
			<table class="table table-bordered">
				<thead>
					<tr><th>Nom</th><th>Ville</th><th>Adresse</th><th>Département</th><th>Téléphone</th><th>Action</th></tr>
				</thead>
				<tbody>
					<?php foreach($etablissements as $etab){ ?>
					<tr>
						<td data-intitule="Nom"><?php echo htmlspecialchars($etab['nom']); ?></td>
						<td data-intitule="Ville"><?php echo htmlspecialchars($etab['ville']); ?></td>
						<td data-intitule="Adresse"><?php echo htmlspecialchars($etab['adresse']); ?></td>
						<td data-intitule="Département"><?php echo htmlspecialchars($etab['departement']); ?></td>
						<td data-intitule="Téléphone"><?php echo htmlspecialchars($etab['tel']); ?></td>
						<td data-intitule="Action">
							<?php if((int)$etab['nb_enseignants']>0){ ?>
							<span class="etablissement-utilise">Utilisé par <?php echo (int)$etab['nb_enseignants']; ?> enseignant<?php echo (int)$etab['nb_enseignants']>1?'s':''; ?></span>
							<?php }else{ ?>
							<form method="post" action="administration-enseignants.php" onsubmit="return confirm('Supprimer définitivement cet établissement ?');" style="margin:0;">
								<input type="hidden" name="action" value="supprimer_etablissement">
								<input type="hidden" name="id_etablissement" value="<?php echo (int)$etab['id_etablissement']; ?>">
								<button type="submit" class="btn-supprimer">Supprimer</button>
							</form>
							<?php } ?>
						</td>
					</tr>
					<?php } ?>
					<?php if(count($etablissements)==0){ ?>
					<tr><td colspan="6" class="text-center">Aucun établissement.</td></tr>
					<?php } ?>
				</tbody>
			</table>
			</div>

		</div>

		<div class="section-encadree">

			<div class="section-header">
				<div>
					<h3>Ajouter un établissement</h3>
					<p class="section-description">Ces informations seront reprises dans l'en-tête des bulletins.</p>
				</div>
			</div>

			<form method="post" action="administration-enseignants.php" class="form-admin">
				<input type="hidden" name="action" value="creer_etablissement">

				<div class="champ">
					<label for="nom_etablissement">Nom de l'établissement</label>
					<input type="text" class="form-control" name="nom_etablissement" id="nom_etablissement" required>
				</div>

				<div class="champ">
					<label for="ville_etablissement">Ville</label>
					<input type="text" class="form-control" name="ville_etablissement" id="ville_etablissement" required>
				</div>

				<div class="champ">
					<label for="adresse_etablissement">Adresse</label>
					<input type="text" class="form-control" name="adresse_etablissement" id="adresse_etablissement">
				</div>

				<div class="champ">
					<label for="departement_etablissement">Département (ex : 59100)</label>
					<input type="text" class="form-control" name="departement_etablissement" id="departement_etablissement">
				</div>

				<div class="champ">
					<label for="tel_etablissement">Téléphone</label>
					<input type="text" class="form-control" name="tel_etablissement" id="tel_etablissement">
				</div>

				<button type="submit" class="btn btn-color">Ajouter l'établissement</button>
			</form>

		</div>

	</details>
    </div>

<script src="utils/jquery/jquery-1.11.3.min.js"></script>
<script src="utils/bootstrap/js/bootstrap.min.js"></script>

</body>

</html>
