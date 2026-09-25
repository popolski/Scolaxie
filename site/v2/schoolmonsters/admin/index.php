<?php
session_start();

if($_SESSION['role']!='enseignant'){

	header('location:../index.php');
	exit();
}

require_once dirname(__DIR__, 2) . '/fasteval/utils/class/class_bdd.php';
$dbh = bdd::connexion(scolaxieConfig('SCOLAXIE_DB_NAME'));
$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);

$classeEnseignant=$_SESSION['classe_enseignant'];
$message='';
// ---------------------------------------------------------------------
// Fast Éval est la seule source de vérité pour les inscriptions.
// La création, la suppression d'un élève et le vidage de la classe se font
// là-bas, et nulle part ailleurs. Ces actions sont refusées ici même si la
// requête est envoyée à la main, formulaire retiré ou non.
// School Monsters ne garde que ce qui lui appartient : le NIVEAU, qui
// commande les périodes accessibles et n'a pas d'équivalent dans Fast Éval.
// ---------------------------------------------------------------------
if(isset($_POST['submit']) && in_array($_POST['submit'], array('ajouter','supprimer','vider_classe'), true)){
    $message = "Les élèves s'ajoutent et se suppriment dans Fast Éval, qui fait autorité pour les trois sites. Ici, seul le niveau se règle.";
    $_POST['submit'] = '';
}


if(ISSET($_POST['submit'])){

	if($_POST['submit']=='inserer_eleve'){

		$nom=strtoupper(htmlspecialchars(trim($_POST['nom'])));
		$prenom=htmlspecialchars(trim($_POST['prenom']));
		$code=htmlspecialchars(trim($_POST['code']));
		$niveau=htmlspecialchars($_POST['niveau']);

		if($nom==''||$prenom==''||$code==''){
			$message="Le nom, le prénom et le mot de passe sont obligatoires.";
		}else{
			$req=$dbh->prepare('SELECT nom FROM droitsite WHERE nom=:nom AND prenom=:prenom AND classe=:classe');
			$req->bindParam(':nom',$nom);
			$req->bindParam(':prenom',$prenom);
			$req->bindParam(':classe',$classeEnseignant);
			$req->execute();

			if($req->fetch()){
				$message="Un élève de ce nom et prénom existe déjà dans votre classe.";
			}else{
				$reqCollision=$dbh->prepare('SELECT nom FROM droitsite WHERE classe=:classe AND LOWER(prenom)=LOWER(:prenom) AND code=:code');
				$reqCollision->bindParam(':classe',$classeEnseignant);
				$reqCollision->bindParam(':prenom',$prenom);
				$reqCollision->bindParam(':code',$code);
				$reqCollision->execute();
				$collision=(bool)$reqCollision->fetch();
				$reqCollision->closeCursor();

				if($collision){
					$message="Un autre élève prénommé ".$prenom." utilise déjà ce mot de passe. Choisissez-en un différent pour éviter toute confusion à la connexion.";
				}else{
					$requete='INSERT INTO droitsite (nom,prenom,code,classe,niveau) VALUES (:nom,:prenom,:code,:classe,:niveau)';
					$prep=$dbh->prepare($requete);
					$prep->bindParam(':nom',$nom);
					$prep->bindParam(':prenom',$prenom);
					$prep->bindParam(':code',$code);
					$prep->bindParam(':classe',$classeEnseignant);
					$prep->bindParam(':niveau',$niveau);
					$prep->execute();
					$message=ucfirst(strtolower($prenom))." ".$nom." a été ajouté(e).";
				}
			}
			$req->closeCursor();
		}
	}

	if($_POST['submit']=='modifier_eleve'&&isset($_POST['nom_cible'])&&isset($_POST['prenom_cible'])){

		$nomCible=$_POST['nom_cible'];
		$prenomCible=$_POST['prenom_cible'];
		$niveau=htmlspecialchars($_POST['niveau']);
		$code=htmlspecialchars(trim($_POST['code']));

		if($code==''){
			$message="Le mot de passe ne peut pas être vide.";
		}else{
			$reqCollision=$dbh->prepare('SELECT nom FROM droitsite WHERE classe=:classe AND LOWER(prenom)=LOWER(:prenom) AND code=:code AND nom!=:nom');
			$reqCollision->bindParam(':classe',$classeEnseignant);
			$reqCollision->bindParam(':prenom',$prenomCible);
			$reqCollision->bindParam(':code',$code);
			$reqCollision->bindParam(':nom',$nomCible);
			$reqCollision->execute();
			$collision=(bool)$reqCollision->fetch();
			$reqCollision->closeCursor();

			if($collision){
				$message="Un autre élève prénommé ".$prenomCible." utilise déjà ce mot de passe. Choisissez-en un différent pour éviter toute confusion à la connexion.";
			}else{
				$req=$dbh->prepare('UPDATE droitsite SET niveau=:niveau, code=:code WHERE nom=:nom AND prenom=:prenom AND classe=:classe');
				$req->bindParam(':niveau',$niveau);
				$req->bindParam(':code',$code);
				$req->bindParam(':nom',$nomCible);
				$req->bindParam(':prenom',$prenomCible);
				$req->bindParam(':classe',$classeEnseignant);
				$req->execute();
				$req->closeCursor();
				$message="Élève mis à jour.";
			}
		}
	}

	if($_POST['submit']=='supprimer'&&isset($_POST['nom_cible'])&&isset($_POST['prenom_cible'])){

		$req=$dbh->prepare('DELETE FROM droitsite WHERE nom=:nom AND prenom=:prenom AND classe=:classe');
		$req->bindParam(':nom',$_POST['nom_cible']);
		$req->bindParam(':prenom',$_POST['prenom_cible']);
		$req->bindParam(':classe',$classeEnseignant);
		$req->execute();
		$nb=$req->rowCount();
		$req->closeCursor();

		$message=$nb>0?"L'élève a été supprimé.":"Élève introuvable.";
	}

	if($_POST['submit']=='vider_classe'){

		$req=$dbh->prepare('DELETE FROM droitsite WHERE classe=:classe');
		$req->bindParam(':classe',$classeEnseignant);
		$req->execute();
		$nb=$req->rowCount();
		$message=$nb." élève(s) supprimé(s) (fin d'année).";
	}
}

$reponse=$dbh->prepare('SELECT nom,prenom,code,niveau FROM droitsite WHERE classe=:classe ORDER BY nom ASC');
$reponse->bindParam(':classe',$classeEnseignant);
$reponse->execute();
$eleves=$reponse->fetchAll(PDO::FETCH_ASSOC);
$reponse->closeCursor();

?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <title>Administration des élèves</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Le socle commun aux trois sites. Il apporte le creme, l'encre, le
             turquoise, la Lexend et les classes « gx- ». Ce qui reste dans le
             <style> est ce que le socle ne couvre pas, et rien d'autre : la
             page portait auparavant 45 regles maison qui redecrivaient des
             cartes, des boutons et un tableau qu'il sait deja dessiner. -->
        <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600&display=swap">
        <link rel="stylesheet" href="../css/admin-v2.css?v=20260903-05">

        <style>
            /* Le socle ne pose pas de fond de page : il ne fournit que des
               variables et des classes « gx- », pour pouvoir etre ajoute a une
               page existante sans rien lui changer. Les autres pages de School
               Monsters tiennent leur creme de style1.css, que celle-ci n'inclut
               plus. Elle le pose donc elle-meme, avec le meme jeton. */
            body{margin:0;background:var(--gx-fond);}

            /* La ligne d'edition d'un eleve : le choix du niveau et son bouton
               cote a cote, dans une cellule de tableau. .gx-champ empile en
               grille par defaut, ce qui est juste dans un formulaire mais pas
               dans une cellule ou la place manque. */
            .admin-ligne{display:flex;align-items:center;gap:var(--gx-esp-2);margin:0;}
            /* Les champs du socle ne sont dessines que sous .gx-champ, qui les
               empile en grille. Ici la place manque, donc on reprend le meme
               dessin sans le conteneur : memes bordure, rayon et hauteur que
               partout ailleurs, pour que le select ne detonne pas. */
            .admin-ligne select{
              flex:0 1 190px;
              font:inherit;
              min-height:var(--gx-touche);
              padding:0 var(--gx-esp-3);
              border:1px solid var(--gx-bordure);
              border-radius:var(--gx-rayon-champ);
              background:#fff;
              color:var(--gx-encre);
            }

            /* Largeur plancher du tableau : en dessous, .gx-tableau-cadre fait
               defiler horizontalement plutot que de comprimer les colonnes. */
            .admin-tableau{min-width:620px;}

            /* Le titre de section, avec son compteur pousse a droite. */
            .admin-tete{display:flex;align-items:flex-start;justify-content:space-between;gap:var(--gx-esp-3);}
            .admin-tete h2{margin:0;font-size:var(--gx-t-titre-2);font-weight:600;}
            .admin-tete p{margin:var(--gx-esp-1) 0 0;max-width:70ch;color:var(--gx-encre-douce);font-size:var(--gx-t-petit);}

            /* .gx-carte ne pose pas de marge exterieure : c'est au conteneur de
               separer les cartes, sinon deux cartes voisines se touchent. */
            .admin-section{gap:var(--gx-esp-4);padding:var(--gx-esp-6);margin-bottom:var(--gx-esp-6);}

            /* Le libelle du choix de niveau existe pour les lecteurs d'ecran,
               qui annoncent sinon cinq listes « Niveau 1 » sans dire de qui. */
            .admin-invisible{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;}
        </style>
        <link rel="stylesheet" href="interface.css?v=20260905-02">

    </head>
    <body class="gx-typo gx-app-schoolmonsters gx-enseignant sm-admin-page">

        <a class="skip-link" href="#contenu">Aller au contenu</a>

        <!-- En-tete commun aux trois sites, meme composition que les pages de
             periode et que l'editeur : logo, retours, identite a droite. -->
        <header class="gx-entete gx-entete-cadree">
            <img class="gx-entete-logo" src="../img-140/logo_school_monsters.png?v=20260906-01" alt="School Monsters">
            <nav class="fil" aria-label="Fil d'Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><a href="../enseignant.php">Espace enseignant</a><span aria-hidden="true">›</span><span class="actuel" aria-current="page">Administration des élèves</span></nav>
            <?php
            // Le nom vient de la session du portail ; a defaut, l'identifiant
            // de connexion, seule chose dont on soit sur apres un SSO.
            $nomAffiche = trim(
                (isset($_SESSION['prenom']) ? ucfirst($_SESSION['prenom']) : '')
                . ' ' . mb_strtoupper($_SESSION['nom'] ?? '', 'UTF-8')
            );
            if($nomAffiche === '' && isset($_SESSION['identifiant_enseignant'])){
                $nomAffiche = ucfirst($_SESSION['identifiant_enseignant']);
            }
            if($nomAffiche !== ''){
            ?>
            <?php
            require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
            echo gxMenuIdentite('School Monsters');
            ?>
            <?php } ?>
        </header>

        <main class="gx-page gx-largeur-riche" id="contenu"><div class="gx-cartouche gx-entete-cadree gx-ligne-titre">
            <span class="sm-surtitre">La classe</span>
            <h1>Administration des <em>&eacute;l&egrave;ves</em> - <?php echo strtoupper(htmlspecialchars($classeEnseignant)); ?></h1>
            <p>Réglez les périodes accessibles. Les inscriptions et les accès communs se gèrent dans Fast Éval.</p>
        </div>



            <?php if($message!=''){ ?>
            <p class="gx-bandeau gx-bandeau-info" role="status"><?php echo htmlspecialchars($message); ?></p>
            <?php } ?>

            <section class="gx-carte admin-section">

                <div class="admin-tete">
                    <div>
                        <h2>&Eacute;l&egrave;ves de la classe</h2>
                        <p>R&eacute;glez le niveau, c'est-&agrave;-dire les p&eacute;riodes accessibles &agrave; chaque &eacute;l&egrave;ve. Les &eacute;l&egrave;ves eux-m&ecirc;mes s'inscrivent dans Fast &Eacute;val.</p>
                    </div>
                    <span class="gx-pastille-support"><span class="gx-pastille gx-pastille-info"><?php echo count($eleves); ?></span></span>
                </div>

                <div class="sm-recherche" role="search" aria-label="Rechercher dans la classe">
                    <label for="chercher-eleve">Rechercher un élève</label>
                    <input id="chercher-eleve" type="search" placeholder="Nom ou prénom">
                    <span id="compte-eleves" role="status"><?php echo count($eleves); ?> élèves</span>
                </div>
                <div class="gx-tableau-cadre">
                <table class="gx-tableau admin-tableau">
                    <thead>
                        <tr><th>Nom</th><th>Pr&eacute;nom</th><th>Niveau (p&eacute;riodes accessibles)</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($eleves as $eleve){
                        $idNiveau = 'niveau-'.preg_replace('~[^a-z0-9]+~i', '-', $eleve['nom'].'-'.$eleve['prenom']);
                    ?>
                    <tr data-eleve>
                        <td data-label="Nom"><?php echo htmlspecialchars($eleve['nom']); ?></td>
                        <td data-label="Prénom"><?php echo htmlspecialchars($eleve['prenom']); ?></td>
                        <td data-label="Périodes accessibles">
                            <form method="post" action="" class="admin-ligne">
                                <input type="hidden" name="submit" value="modifier_eleve">
                                <input type="hidden" name="nom_cible" value="<?php echo htmlspecialchars($eleve['nom']); ?>">
                                <input type="hidden" name="prenom_cible" value="<?php echo htmlspecialchars($eleve['prenom']); ?>">
                                <label class="admin-invisible" for="<?php echo htmlspecialchars($idNiveau); ?>">Niveau de <?php echo htmlspecialchars($eleve['prenom']); ?></label>
                                <select name="niveau" id="<?php echo htmlspecialchars($idNiveau); ?>">
                                    <?php for($n=1;$n<=5;$n++){ ?>
                                    <option value="<?php echo $n; ?>" <?php echo ($eleve['niveau']==$n)?'selected':''; ?>>Niveau <?php echo $n; ?></option>
                                    <?php } ?>
                                </select>
                                <button type="submit" class="gx-bouton gx-bouton-principal">Mettre &agrave; jour</button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                    <?php if(count($eleves)==0){ ?>
                    <tr><td colspan="3">Aucun élève pour le moment. Gérez les inscriptions dans Fast Éval ; les élèves apparaîtront ici après leur première connexion par le portail.</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>
                <p id="aucun-eleve" hidden>Aucun élève ne correspond à cette recherche. Effacez le nom pour revoir toute la classe.</p>

            </section>

            <section class="gx-carte admin-section">

                <div class="admin-tete">
                    <div>
                        <h2>Ajouter ou retirer un &eacute;l&egrave;ve</h2>
                        <p>Les &eacute;l&egrave;ves s'inscrivent dans Fast &Eacute;val, qui fait autorit&eacute; pour les trois sites. Ils apparaissent ici d&egrave;s leur premi&egrave;re connexion par le portail.</p>
                    </div>
                </div>

                <p><a href="/v2/fasteval/sso.php?vers=gestion-classe.php" class="gx-bouton gx-bouton-secondaire">G&eacute;rer la classe dans Fast &Eacute;val</a></p>

            </section>

            <section class="gx-carte admin-section">

                <div class="admin-tete">
                    <div>
                        <h2>Fin d'ann&eacute;e</h2>
                        <p>Le d&eacute;part d'un &eacute;l&egrave;ve se r&egrave;gle dans Fast &Eacute;val : il y est d&eacute;sactiv&eacute; ou supprim&eacute;, et il dispara&icirc;t alors des trois sites. Ses r&eacute;sultats restent archiv&eacute;s ici tant qu'on ne demande pas explicitement leur effacement.</p>
                    </div>
                </div>

            </section>

        </main>

        <script>
        // La recherche reste locale et ne soumet aucun formulaire de niveau.
        (function () {
            var champ = document.getElementById('chercher-eleve');
            var lignes = Array.prototype.slice.call(document.querySelectorAll('[data-eleve]'));
            champ.addEventListener('input', function () {
                var recherche = champ.value.trim().toLocaleLowerCase('fr');
                var visibles = 0;
                lignes.forEach(function (ligne) {
                    var identite = ligne.cells[0].textContent + ' ' + ligne.cells[1].textContent;
                    ligne.hidden = identite.toLocaleLowerCase('fr').indexOf(recherche) === -1;
                    if (!ligne.hidden) { visibles++; }
                });
                document.getElementById('compte-eleves').textContent = visibles + ' sur ' + lignes.length;
                document.getElementById('aucun-eleve').hidden = visibles > 0 || lignes.length === 0;
            });
        })();
        </script>
    </body>
</html>
