
<?php
session_start();

if(empty($_SESSION['permission'])){

  header('location:../index.php');
  exit();
}

if(!isset($_SESSION['role']) || $_SESSION['role']!='enseignant'){

  header('location:../presentation.php');
  exit();
}

require('../utils/class/class_bdd.php');

bdd::connexion($_SESSION['bdd'])->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);



$req=bdd::connexion($_SESSION['bdd'])->prepare('SELECT id_eleve,nom,prenom,date_naiss FROM classe WHERE id_enseignant=:id_enseignant ORDER BY nom ASC');
$req->bindParam(':id_enseignant',$_SESSION['id_enseignant']);
$req->execute();
$resultat=$req->fetchAll(PDO::FETCH_NUM);


$req->closeCursor();
?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Préparer un bulletin</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="../utils/style-v2.css?v=20260912-conformite">
    <link rel="stylesheet" href="utils/bulletin-v2.css?v=20260909-fg3">

    </head>

    <body id="haut-page" class="gx-typo gx-app-fasteval v2-fasteval v2-bulletin">
    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <header class="v2-app-header">
        <img class="logo" src="../utils/img/logofasteval.png" alt="Fast Éval">
                <nav class="fil" aria-label="Fil d'Ariane">
            <a href="/portail/">Portail</a><span aria-hidden="true">›</span>
            <a href="../presentation.php">Accueil</a>
            <span aria-hidden="true">›</span>
            <span class="actuel">Préparer un bulletin</span>
        </nav>
        <?php require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval'); ?>
    </header>

    <main id="contenu" class="v2-page bulletin-page gx-largeur-grille">
        <section class="v2-page-hero gx-ligne-titre" aria-labelledby="titre-page">
            <span class="v2-page-hero-surtitre">Livret scolaire</span>
            <h1 id="titre-page">Préparer un bulletin</h1>
            <p>Sélectionnez l'élève, le contenu et la période à faire apparaître dans le PDF.</p>
        </section>

        <form name="bulletin" method="post" action="select.php" class="bulletin-card" onsubmit="return verifierPeriode();">

            <section class="bulletin-step">
                <div class="step-number" aria-hidden="true">1</div>
                <div class="bulletin-step-content">
                <label for="sel_eleve" class="step-title">Élève</label>
                <p class="step-description">Sélectionnez l'élève concerné par le bulletin.</p>

                <select name="eleve" id="sel_eleve" required<?php echo empty($resultat) ? ' disabled' : ''; ?>>

                   <?php
                   if (empty($resultat)) {
                       echo '<option>Aucun élève dans la classe</option>';
                   } else {
                       foreach ($resultat as $ligne) {
                           $dateNaissTexte=$ligne[3]!=null?" (né(e) le ".date("d/m/Y",strtotime($ligne[3])).")":"";
                           $libelle=ucfirst($ligne[2])." ".mb_strtoupper($ligne[1], 'UTF-8').$dateNaissTexte;
                           echo '<option value="'.htmlspecialchars($ligne[0], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8').'</option>';
                       }
                   }
                   ?>
                   
                </select>
                </div>
            </section>

            <section class="bulletin-step">
                <div class="step-number" aria-hidden="true">2</div>
                <div class="bulletin-step-content">
                <label for="sel_type" class="step-title">Contenu du bulletin</label>
                <p class="step-description">Choisissez les compétences ou les connaissances à présenter.</p>

                <select name="type" id="sel_type">
                <option value="comp">Compétences travaillées pendant la période</option>
                <option value="eval">Connaissances fondamentales évaluées pendant la période</option>
                </select>
                </div>
            </section>

            <section class="bulletin-step">
                <div class="step-number" aria-hidden="true">3</div>
                <div class="bulletin-step-content">
                <label for="sel_modele" class="step-title">Présentation du bulletin</label>
                <p class="step-description">La version alignée sur la charte Fast Éval est proposée par défaut. Vous pouvez revenir à l'ancien modèle.</p>

                <select name="modele" id="sel_modele">
                <option value="charte" selected>Charte Fast Éval</option>
                <option value="actuel">Modèle actuel</option>
                </select>
                </div>
            </section>

            <section class="bulletin-step">
                <div class="step-number" aria-hidden="true">4</div>
                <div class="bulletin-step-content">
                    <span class="step-title">Période</span>
                    <p class="step-description">Indiquez les dates de début et de fin à prendre en compte.</p>
                    <div class="date-grid">
                        <div class="date-field">
                            <label for="date_debut">Date de début</label>
                            <input type="date" name="date_debut" id="date_debut" required>
                        </div>
                        <div class="date-field">
                            <label for="date_fin">Date de fin</label>
                            <input type="date" name="date_fin" id="date_fin" required>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bulletin-suite" aria-labelledby="titre-suite">
                <div class="bulletin-suite-entete">
                    <h2 id="titre-suite">Finaliser le bulletin</h2>
                    <p>Vous pourrez l'ouvrir, l'enregistrer ou l'imprimer au format PDF.</p>
                </div>
                <div class="bulletin-actions">
                <button type="submit" class="bulletin-action bulletin-action-primary" name="choix" value="Voir bilan des acquis sans enregistrement des observations"<?php echo empty($resultat) ? ' disabled' : ''; ?>>
                    <span class="bulletin-action-title">Générer sans observation</span>
                    <span class="bulletin-action-description">Créer directement le PDF avec les résultats.</span>
                    <span class="bulletin-action-arrow" aria-hidden="true">→</span>
                </button>

                <button type="submit" class="bulletin-action" name="choix" value="Voir bilan des acquis avec enregistrement des observations"<?php echo empty($resultat) ? ' disabled' : ''; ?>>
                    <span class="bulletin-action-title">Ajouter des observations</span>
                    <span class="bulletin-action-description">Rédiger les appréciations, puis générer le PDF.</span>
                    <span class="bulletin-action-arrow" aria-hidden="true">→</span>
                </button>
                </div>
            </section>
        </form>
    </main>


<script>
function verifierPeriode(){
    var debut=document.getElementById('date_debut').value;
    var fin=document.getElementById('date_fin').value;
    if(!debut || !fin){
        alert("Vous n'avez pas indiqué de dates pour la période.");
        return false;
    }
    if(debut>fin){
        alert("La date de fin doit être postérieure à la date de début.");
        return false;
    }
    return true;
}

</script>

<script src="../utils/ui-v2.js?v=20260903-01"></script>

</body>

</html>
