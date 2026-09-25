<?php
session_start();
require_once __DIR__ . '/../utils/session-sso.php';
fastevalExigerEnseignant();
require_once __DIR__ . '/../utils/class/class_bdd.php';

$dbh = bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

$idEleve = isset($_GET['id_eleve']) ? (int) $_GET['id_eleve'] : 0;
$req = $dbh->prepare('SELECT id_eleve,nom,prenom,date_naiss,code_carte,photo_carte,photo_zoom,photo_x,photo_y FROM classe WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
$req->bindParam(':id_eleve', $idEleve);
$req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
$req->execute();
$eleve = $req->fetch(PDO::FETCH_ASSOC);
$req->closeCursor();

if (!$eleve || empty($eleve['code_carte'])) {
    header('Location: ../gestion-classe.php');
    exit();
}

$nomEnseignant = isset($_SESSION['nom_client']) ? ucfirst($_SESSION['nom_client']) : '';
$classeEnseignant = isset($_SESSION['classe_client']) ? $_SESSION['classe_client'] : '';
$labelClasse = 'Classe de Mme ' . $nomEnseignant . ' - ' . $classeEnseignant;
$nomEleve = ucfirst($eleve['prenom']) . ' ' . mb_strtoupper($eleve['nom'], 'UTF-8');
$photoZoom = max(1, min(3, (float) ($eleve['photo_zoom'] ?: 1.15)));
$photoX = max(0, min(100, (float) ($eleve['photo_x'] ?? 50)));
$photoY = max(0, min(100, (float) ($eleve['photo_y'] ?? 50)));

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

$tailleFond = null;
if (!empty($eleve['photo_carte'])) {
    $tailleFond = tailleFondPhoto(__DIR__ . '/photos_eleves/' . basename($eleve['photo_carte']), 2.886, 3.464, $photoZoom);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Photo de la carte - <?php echo htmlspecialchars($nomEleve, ENT_QUOTES, 'UTF-8'); ?></title>
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
        <span class="actuel">Photo et carte</span>
    </nav>
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval'); ?>
</header>

<main id="contenu" class="cartes-page gx-largeur-lecture">
    <section class="cartes-intro" aria-labelledby="titre-photo">
        <div class="cartes-intro-texte">
            <p class="cartes-sur-titre">Carte de connexion</p>
            <h1 id="titre-photo">Préparer la carte de <?php echo htmlspecialchars($nomEleve, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Ajoutez une photo, ajustez son cadrage directement dans la carte, puis ouvrez l’aperçu avant impression.</p>
        </div>
        <div class="cartes-intro-logo" aria-hidden="true">
            <img src="../utils/img/logofasteval.png" alt="">
        </div>
    </section>

    <div class="photo-espace-travail">
        <section class="photo-apercu" aria-labelledby="titre-apercu">
            <div class="photo-apercu-entete">
                <h2 id="titre-apercu">Aperçu de la carte</h2>
                <span>Les modifications sont enregistrées automatiquement.</span>
            </div>
            <div class="photo-carte-scroll">
                <div class="carte" id="carte">
                    <div class="col-a">
                        <div class="titre">Carte de membre</div>
                        <img class="logo" src="img/carte-logo.png" alt="Fast Éval">
                        <div class="champs">
                            Nom : <span class="valeur"><?php echo htmlspecialchars(mb_strtoupper($eleve['nom'], 'UTF-8')); ?></span><br>
                            Prénom : <span class="valeur"><?php echo htmlspecialchars(ucfirst($eleve['prenom'])); ?></span><br>
                            Date de naissance : <span class="valeur"><?php echo !empty($eleve['date_naiss']) ? date('d/m/Y', strtotime($eleve['date_naiss'])) : ''; ?></span>
                        </div>
                    </div>
                    <div class="pli"><?php echo htmlspecialchars($labelClasse); ?></div>
                    <div class="ligne-pliage"></div>
                    <div class="col-b">
                        <img class="cadre-img" src="img/carte-cadre.jpg" alt="">
                        <div class="zone-photo" id="zone-photo" data-photo="<?php echo !empty($eleve['photo_carte']) ? htmlspecialchars(basename($eleve['photo_carte'])) : ''; ?>"
                            <?php if ($tailleFond) { ?>
                                style="background-image:url('photos_eleves/<?php echo rawurlencode(basename($eleve['photo_carte'])); ?>?v=<?php echo time(); ?>');background-size:<?php echo $tailleFond['largeur']; ?>cm <?php echo $tailleFond['hauteur']; ?>cm;background-position:<?php echo $photoX; ?>% <?php echo $photoY; ?>%;"
                            <?php } ?>>
                            <?php if (!$tailleFond) { ?>
                                <div class="vide" id="zone-vide">Pas encore de photo</div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-c">
                        <div class="rotation-code-barre">
                            <svg class="code-barre" data-code="<?php echo htmlspecialchars($eleve['code_carte']); ?>"></svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <aside class="photo-panneau" aria-labelledby="titre-reglages">
            <h2 id="titre-reglages">Ajuster la photo</h2>
            <p class="aide">Vous pouvez aussi faire glisser la photo directement dans son cadre. Chaque réglage est sauvegardé automatiquement.</p>

            <div class="photo-etape">
                <div class="photo-etape-titre"><span class="photo-numero">1</span> Choisir l’image</div>
                <label for="fichier-photo">Photo de l’élève</label>
                <input type="file" id="fichier-photo" accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="photo-etape">
                <div class="photo-etape-titre"><span class="photo-numero">2</span> Régler le cadrage</div>
                <label for="curseur-zoom">Zoom <span class="valeur-reglage" id="valeur-zoom"></span></label>
                <input type="range" id="curseur-zoom" min="1" max="3" step="0.05" value="<?php echo $photoZoom; ?>">

                <label for="curseur-x">Position horizontale</label>
                <input type="range" id="curseur-x" min="0" max="100" step="1" value="<?php echo $photoX; ?>">

                <label for="curseur-y">Position verticale</label>
                <input type="range" id="curseur-y" min="0" max="100" step="1" value="<?php echo $photoY; ?>">

                <div class="actions-reglage">
                    <button type="button" class="btn-secondaire" id="recentrer-photo">Recentrer</button>
                    <button type="button" class="btn-danger" id="supprimer-photo"<?php echo empty($eleve['photo_carte']) ? ' disabled' : ''; ?>>Supprimer la photo</button>
                </div>
                <div class="statut" id="statut" role="status" aria-live="polite"></div>
            </div>

            <div class="photo-etape">
                <div class="photo-etape-titre"><span class="photo-numero">3</span> Vérifier et imprimer</div>
                <div class="photo-actions-finales">
                    <a href="cartes-eleves.php?id_eleve=<?php echo (int) $eleve['id_eleve']; ?>" class="cartes-bouton principal">Prévisualiser et imprimer</a>
                    <a href="../gestion-classe.php" class="cartes-bouton">Revenir à la classe</a>
                </div>
            </div>
        </aside>
    </div>
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

var idEleve = <?php echo (int) $eleve['id_eleve']; ?>;
var zonePhoto = document.getElementById('zone-photo');
var curseurZoom = document.getElementById('curseur-zoom');
var curseurX = document.getElementById('curseur-x');
var curseurY = document.getElementById('curseur-y');
var statut = document.getElementById('statut');
var valeurZoom = document.getElementById('valeur-zoom');
var boutonSupprimer = document.getElementById('supprimer-photo');
var photoNaturelle = null;
var dimensionsFond = null;

function afficherValeurZoom() {
    valeurZoom.textContent = Math.round(parseFloat(curseurZoom.value) * 100) + ' %';
}

function chargerDimensionsPhoto(nomFichier) {
    return new Promise(function (resolve) {
        var image = new Image();
        image.onload = function () {
            photoNaturelle = { largeur: image.naturalWidth, hauteur: image.naturalHeight };
            resolve();
        };
        image.src = 'photos_eleves/' + encodeURIComponent(nomFichier) + '?v=' + Date.now();
    });
}

function appliquerCadrage() {
    if (!photoNaturelle) { return; }
    var rect = zonePhoto.getBoundingClientRect();
    var echelleCouverture = Math.max(rect.width / photoNaturelle.largeur, rect.height / photoNaturelle.hauteur);
    var echelle = echelleCouverture * parseFloat(curseurZoom.value);
    var largeurFond = photoNaturelle.largeur * echelle;
    var hauteurFond = photoNaturelle.hauteur * echelle;
    dimensionsFond = { largeur: largeurFond, hauteur: hauteurFond, zoneLargeur: rect.width, zoneHauteur: rect.height };
    zonePhoto.style.backgroundSize = largeurFond + 'px ' + hauteurFond + 'px';
    zonePhoto.style.backgroundPosition = curseurX.value + '% ' + curseurY.value + '%';
    afficherValeurZoom();
}
afficherValeurZoom();

<?php if ($tailleFond) { ?>
chargerDimensionsPhoto(<?php echo json_encode(basename($eleve['photo_carte'])); ?>).then(appliquerCadrage);
<?php } ?>

var minuteur = null;
function enregistrerCadrage() {
    statut.textContent = 'Enregistrement…';
    var donnees = new FormData();
    donnees.append('id_eleve', idEleve);
    donnees.append('zoom', curseurZoom.value);
    donnees.append('x', curseurX.value);
    donnees.append('y', curseurY.value);
    fetch('enregistrer-cadrage.php', { method: 'POST', body: donnees })
        .then(function (reponseHttp) { return reponseHttp.json(); })
        .then(function (reponse) {
            statut.textContent = reponse.ok ? 'Modifications enregistrées.' : (reponse.erreur || 'Erreur lors de l’enregistrement.');
        })
        .catch(function () { statut.textContent = 'Erreur lors de l’enregistrement.'; });
}

[curseurZoom, curseurX, curseurY].forEach(function (curseur) {
    curseur.addEventListener('input', function () {
        appliquerCadrage();
        clearTimeout(minuteur);
        minuteur = setTimeout(enregistrerCadrage, 400);
    });
});

document.getElementById('recentrer-photo').addEventListener('click', function () {
    curseurZoom.value = 1.15;
    curseurX.value = 50;
    curseurY.value = 50;
    appliquerCadrage();
    enregistrerCadrage();
});

var glissement = null;
zonePhoto.addEventListener('pointerdown', function (e) {
    if (!photoNaturelle || !dimensionsFond) { return; }
    glissement = { x: e.clientX, y: e.clientY, positionX: parseFloat(curseurX.value), positionY: parseFloat(curseurY.value) };
    zonePhoto.classList.add('en-deplacement');
    zonePhoto.setPointerCapture(e.pointerId);
});
zonePhoto.addEventListener('pointermove', function (e) {
    if (!glissement || !dimensionsFond) { return; }
    var debordementX = dimensionsFond.largeur - dimensionsFond.zoneLargeur;
    var debordementY = dimensionsFond.hauteur - dimensionsFond.zoneHauteur;
    if (debordementX > 0) { curseurX.value = Math.max(0, Math.min(100, glissement.positionX - (e.clientX - glissement.x) / debordementX * 100)); }
    if (debordementY > 0) { curseurY.value = Math.max(0, Math.min(100, glissement.positionY - (e.clientY - glissement.y) / debordementY * 100)); }
    appliquerCadrage();
});
function terminerGlissement() {
    if (!glissement) { return; }
    glissement = null;
    zonePhoto.classList.remove('en-deplacement');
    enregistrerCadrage();
}
zonePhoto.addEventListener('pointerup', terminerGlissement);
zonePhoto.addEventListener('pointercancel', terminerGlissement);

boutonSupprimer.addEventListener('click', function () {
    if (!confirm('Supprimer la photo de cet élève ?')) { return; }
    statut.textContent = 'Suppression…';
    var donnees = new FormData();
    donnees.append('id_eleve', idEleve);
    fetch('supprimer-photo.php', { method: 'POST', body: donnees })
        .then(function (reponseHttp) { return reponseHttp.json(); })
        .then(function (reponse) {
            if (!reponse.ok) { statut.textContent = reponse.erreur || 'Échec de la suppression.'; return; }
            zonePhoto.style.backgroundImage = 'none';
            zonePhoto.style.backgroundSize = '';
            zonePhoto.style.backgroundPosition = '';
            photoNaturelle = null;
            dimensionsFond = null;
            zonePhoto.innerHTML = '<div class="vide" id="zone-vide">Pas encore de photo</div>';
            boutonSupprimer.disabled = true;
            statut.textContent = 'Photo supprimée.';
        })
        .catch(function () { statut.textContent = 'Échec de la suppression.'; });
});

document.getElementById('fichier-photo').addEventListener('change', function (evenement) {
    var fichier = evenement.target.files[0];
    if (!fichier) { return; }
    statut.textContent = 'Envoi de la photo…';
    var donnees = new FormData();
    donnees.append('id_eleve', idEleve);
    donnees.append('photo', fichier);
    fetch('upload-photo.php', { method: 'POST', body: donnees })
        .then(function (reponseHttp) { return reponseHttp.json(); })
        .then(function (reponse) {
            if (!reponse.ok) {
                statut.textContent = reponse.erreur || 'Échec de l’envoi.';
                return;
            }
            var zoneVide = document.getElementById('zone-vide');
            if (zoneVide) { zoneVide.remove(); }
            zonePhoto.style.backgroundImage = 'url("photos_eleves/' + encodeURIComponent(reponse.photo) + '?v=' + Date.now() + '")';
            curseurZoom.value = 1.15;
            curseurX.value = 50;
            curseurY.value = 50;
            chargerDimensionsPhoto(reponse.photo).then(appliquerCadrage);
            boutonSupprimer.disabled = false;
            statut.textContent = 'Photo enregistrée.';
        })
        .catch(function () { statut.textContent = 'Échec de l’envoi.'; });
});
</script>
</body>
</html>
