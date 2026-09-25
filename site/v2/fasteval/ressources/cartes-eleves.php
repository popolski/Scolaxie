<?php
session_start();
require_once __DIR__ . '/../utils/session-sso.php';
fastevalExigerEnseignant();
require_once __DIR__ . '/../utils/class/class_bdd.php';

$dbh = bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

if (isset($_GET['id_eleve'])) {
    $req = $dbh->prepare('SELECT id_eleve,nom,prenom,code_carte,date_naiss,photo_carte,photo_zoom,photo_x,photo_y FROM classe WHERE id_enseignant=:id_enseignant AND id_eleve=:id_eleve AND code_carte IS NOT NULL');
    $req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
    $req->bindParam(':id_eleve', $_GET['id_eleve']);
} else {
    $req = $dbh->prepare('SELECT id_eleve,nom,prenom,code_carte,date_naiss,photo_carte,photo_zoom,photo_x,photo_y FROM classe WHERE id_enseignant=:id_enseignant AND code_carte IS NOT NULL ORDER BY nom ASC');
    $req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
}
$req->execute();
$eleves = $req->fetchAll(PDO::FETCH_ASSOC);
$req->closeCursor();

$nomEnseignant = isset($_SESSION['nom_client']) ? ucfirst($_SESSION['nom_client']) : '';
$classeEnseignant = isset($_SESSION['classe_client']) ? $_SESSION['classe_client'] : '';
$labelClasse = 'Classe de Mme ' . $nomEnseignant . ' - ' . $classeEnseignant;
$uneSeuleCarte = isset($_GET['id_eleve']);

// Calcule le fond nécessaire pour couvrir le cadre sans déformer la photo.
function tailleFondPhoto($cheminFichier, $zoneLargeurCm, $zoneHauteurCm, $zoom)
{
    $infos = @getimagesize($cheminFichier);
    if (!$infos) {
        return null;
    }
    $largeurNaturelle = $infos[0];
    $hauteurNaturelle = $infos[1];
    $echelleCouverture = max($zoneLargeurCm / $largeurNaturelle, $zoneHauteurCm / $hauteurNaturelle);
    $echelle = $echelleCouverture * max(1, (float) $zoom);
    return array('largeur' => $largeurNaturelle * $echelle, 'hauteur' => $hauteurNaturelle * $echelle);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $uneSeuleCarte ? 'Carte élève' : 'Cartes élèves'; ?> - Fast Éval</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="../utils/style-v2.css?v=20260912-conformite">
    <link rel="stylesheet" href="cartes-v2.css?v=20260909-fg3">
</head>
<body class="gx-typo gx-app-fasteval v2-fasteval">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="v2-app-header">
    <img class="logo" src="../utils/img/logofasteval.png" alt="Fast Éval">
        <nav class="fil" aria-label="Fil d’Ariane">
        <a href="/portail/">Portail</a><span aria-hidden="true">›</span>
        <a href="../presentation.php">Accueil</a><span aria-hidden="true">›</span>
        <a href="../gestion-classe.php">Gérer ma classe</a><span aria-hidden="true">›</span>
        <span class="actuel">Cartes élèves</span>
    </nav>
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval'); ?>
</header>

<main id="contenu" class="cartes-page gx-largeur-grille">
    <section class="cartes-intro gx-ligne-titre" aria-labelledby="titre-cartes">
        <div class="cartes-intro-texte">
            <p class="cartes-sur-titre">Cartes de connexion</p>
            <h1 id="titre-cartes"><?php echo $uneSeuleCarte ? 'Prévisualiser la carte' : 'Imprimer les cartes élèves'; ?></h1>
            <p><?php echo $uneSeuleCarte
                ? 'Vérifiez la photo et les informations avant l’impression.'
                : 'Vérifiez les cartes de la classe, puis lancez une impression ou un enregistrement PDF.'; ?></p>
        </div>
        <div class="cartes-intro-logo" aria-hidden="true">
            <img src="../utils/img/logofasteval.png" alt="">
        </div>
    </section>

    <section class="cartes-toolbar" aria-label="Actions d’impression">
        <div class="cartes-toolbar-texte">
            <strong><?php echo count($eleves); ?> carte<?php echo count($eleves) > 1 ? 's' : ''; ?> prête<?php echo count($eleves) > 1 ? 's' : ''; ?></strong>
            <p>Le bandeau et les commandes ne seront pas imprimés.</p>
        </div>
        <div class="cartes-toolbar-actions">
            <?php if ($uneSeuleCarte && count($eleves) === 1) { ?>
                <a href="carte-photo.php?id_eleve=<?php echo (int) $eleves[0]['id_eleve']; ?>" class="cartes-bouton">← Ajuster la photo</a>
            <?php } else { ?>
                <a href="../gestion-classe.php" class="cartes-bouton">← Revenir à la classe</a>
            <?php } ?>
            <?php if (count($eleves) > 0) { ?>
                <button type="button" class="cartes-bouton principal" onclick="window.print()"><span aria-hidden="true">▣</span> Imprimer ou enregistrer en PDF</button>
            <?php } ?>
        </div>
    </section>

    <?php if (count($eleves) > 0) { ?>
        <div class="cartes-info">
            <span aria-hidden="true">ⓘ</span>
            <p>Dans la fenêtre d’impression, choisissez « Enregistrer en PDF » pour créer un fichier. Chaque carte est prévue pour être pliée sur la ligne centrale.</p>
        </div>

        <div class="grille-cartes">
            <?php foreach ($eleves as $eleve) { ?>
                <article class="carte-document" aria-label="Carte de <?php echo htmlspecialchars(ucfirst($eleve['prenom']) . ' ' . strtoupper($eleve['nom']), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="carte">
                        <div class="col-a">
                            <div class="titre">Carte de membre</div>
                            <img class="logo" src="img/carte-logo.png" alt="Fast Éval">
                            <div class="champs">
                                Nom : <span class="valeur"><?php echo strtoupper(htmlspecialchars($eleve['nom'])); ?></span><br>
                                Prénom : <span class="valeur"><?php echo ucfirst(htmlspecialchars($eleve['prenom'])); ?></span><br>
                                Date de naissance : <span class="valeur"><?php echo !empty($eleve['date_naiss']) ? date('d/m/Y', strtotime($eleve['date_naiss'])) : ''; ?></span>
                            </div>
                        </div>
                        <div class="pli"><?php echo htmlspecialchars($labelClasse); ?></div>
                        <div class="ligne-pliage"></div>
                        <div class="col-b">
                            <img class="cadre-img" src="img/carte-cadre.jpg" alt="">
                            <?php
                            if (!empty($eleve['photo_carte'])) {
                                $cheminPhoto = __DIR__ . '/photos_eleves/' . basename($eleve['photo_carte']);
                                $tailleFond = tailleFondPhoto($cheminPhoto, 2.886, 3.464, $eleve['photo_zoom']);
                                if ($tailleFond) {
                            ?>
                                <div class="zone-photo" style="background-image:url('photos_eleves/<?php echo rawurlencode(basename($eleve['photo_carte'])); ?>');background-size:<?php echo $tailleFond['largeur']; ?>cm <?php echo $tailleFond['hauteur']; ?>cm;background-position:<?php echo max(0, min(100, (float) ($eleve['photo_x'] ?? 50))); ?>% <?php echo max(0, min(100, (float) ($eleve['photo_y'] ?? 50))); ?>%;"></div>
                            <?php
                                }
                            }
                            ?>
                        </div>
                        <div class="col-c">
                            <div class="rotation-code-barre">
                                <svg class="code-barre" data-code="<?php echo htmlspecialchars($eleve['code_carte']); ?>"></svg>
                            </div>
                        </div>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } else { ?>
        <section class="cartes-vide">
            <h2>Aucune carte à imprimer</h2>
            <p>Générez d’abord les codes carte manquants depuis la gestion de classe.</p>
            <a href="../gestion-classe.php" class="cartes-bouton principal">Revenir à la classe</a>
        </section>
    <?php } ?>
</main>

<script>
document.querySelectorAll('.code-barre').forEach(function (svg) {
    JsBarcode(svg, svg.getAttribute('data-code'), {
        format: 'CODE128',
        width: 1.1,
        height: 32,
        displayValue: true,
        fontSize: 10,
        margin: 3
    });
});
</script>
</body>
</html>
