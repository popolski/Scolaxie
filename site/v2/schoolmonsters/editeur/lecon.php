<?php
// Le formulaire d'edition d'une lecon.
//
// A gauche les briques, a droite le vrai lecteur PDF du site : c'est le seul
// endroit ou l'on voit en meme temps une piste et la page qu'elle doit
// atteindre. Le bouton « prendre la page affichee » lit le numero directement
// dans le lecteur, meme origine, sans que l'enseignante ait a le recopier.
require(__DIR__ . '/garde.php');

$chemin = isset($_GET['l']) ? $_GET['l'] : '';
$json = smFichierDonnees($chemin);
if ($json === null) {
    http_response_code(404);
    // Lot 5 (04/09/2026) : avant, "Leçon inconnue." en texte brut, avant
    // meme le <!DOCTYPE> - ni galaxie-tokens.css ni editeur.css n'etaient
    // charges. Page complete, avec le meme bandeau que le reste de
    // l'editeur, plutot qu'un exit() en police par defaut.
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leçon inconnue — Éditeur</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">
<?php require(__DIR__.'/bandeau.php'); ?>
<main id="contenu" style="max-width:var(--gx-large,1440px);margin:0 auto;padding:40px">
    <div class="gx-etat-vide ed-etat-vide">
        <span class="gx-etat-vide-icone" aria-hidden="true">?</span>
        <div>
            <span class="gx-etat-vide-surtitre">Éditeur de leçons</span>
            <h2>Cette leçon n'existe pas</h2>
            <p>Le lien pointe vers un chemin inconnu ou une leçon supprimée. Reviens à la liste pour en choisir une autre.</p>
        </div>
        <a class="ed-bouton ed-fort" href="index.php">Retour à la liste</a>
    </div>
</main>
</body>
</html>
    <?php
    exit();
}
$lecon = json_decode(file_get_contents($json), true);

$blocs = array();
foreach ($lecon['blocs'] as $b) {
    $blocs[$b['type']] = $b;
}
$titreSimple = smTitreSimple($blocs['titre']['contenu']);
list($periode, $matiere) = explode('/', $chemin);
$conseillee = '';
foreach (smMascottes() as $m) {
    if ($m[2] === $matiere) { $conseillee = $m[0]; }
}
$pdf = trim($blocs['pdf']['fichier']);
$documents = smDocumentsVoisins($pdf);
$mascotte = trim($blocs['mascotte']['fichier']);
$hauteur = (int)$blocs['pdf']['hauteur'];
// Une lecon peut n'avoir aucune barre de pistes : douze lecons en video sont
// dans ce cas. On travaille alors sur une barre vide plutot que de refuser
// d'ouvrir la lecon.
if (!isset($blocs['pistes'])) {
    $blocs['pistes'] = array('type' => 'pistes', 'question' => '', 'pistes' => array());
}
$nbPistes = count($blocs['pistes']['pistes']);
$sansPage = 0;
foreach ($blocs['pistes']['pistes'] as $p) { if (empty($p['page'])) { $sansPage++; } }

// Les videos, a plat : l'enseignante voit une seule liste, meme si la lecon
// range ses cartes en deux galeries. Elles se suivent toujours dans la page,
// donc les rassembler ne deplace rien.
$videos = smVideosLecon($lecon);
$colonnes = 2;
foreach ($lecon['blocs'] as $b) {
    if ($b['type'] === 'videos' && isset($b['colonnes'])) {
        $colonnes = (int)$b['colonnes'];
        break;
    }
}
$videosLibres = smVideosLibres($chemin, $lecon);
$limiteVideo = smLimiteEnvoi();
$dossierVideos = smDossierVideos($chemin);
// Les images : une liste PAR galerie, chacune gardant son nombre de colonnes.
// Voir smGaleriesImages pour la raison de ne pas les fondre.
$textes = smTextesLecon($lecon);
$galeries = smGaleriesImages($lecon);
$imagesLibres = smImagesLibres($chemin, $lecon);
$dossierImages = smDossierImages($chemin);
$nbImages = 0;
foreach ($galeries as $g) { $nbImages += count($g['images']); }
// L'apercu se calcule depuis l'editeur : le chemin range dans les donnees est
// relatif a la PAGE de la lecon, pas a cette page-ci.
$apercu = function ($fichier) use ($chemin) {
    return '../' . dirname($chemin) . '/' . $fichier;
};

$sonsLibres = smSonsLibres($chemin, $lecon);
$dossierSons = smDossierSons($chemin, $lecon);
$avis = isset($_GET['avis']) ? explode('|', $_GET['avis']) : array();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo smEch(smTitreLisible($blocs['titre']['contenu'])); ?> — Éditeur</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">

<form method="post" action="enregistrer.php" id="formulaire" enctype="multipart/form-data">
<input type="hidden" name="l" value="<?php echo smEch($chemin); ?>">

<?php require(__DIR__.'/bandeau.php'); ?>
<div class="gx-largeur-riche"><div class="ed-titre-page gx-ligne-titre"><h1>Modifier « <?php echo smEch(smTitreLisible($blocs['titre']['contenu'])); ?> »</h1></div>
<div class="ed-commandes-page">
        <span class="ed-etat" id="etat"></span>

        <a class="ed-bouton" href="../<?php echo smEch($chemin); ?>" target="_blank" rel="noopener">Voir la page élève</a>
        <button type="submit" class="ed-bouton ed-fort">Enregistrer</button>
    </div>
<main id="contenu" class="ed-atelier gx-largeur-riche">

    <div class="ed-colonne">

        <?php foreach ($avis as $a): if (trim($a) === '') { continue; } ?>
        <p class="ed-avis <?php echo substr($a, 0, 1) === '+' ? 'ed-avis-ok' : 'ed-avis-info'; ?>"><?php
            echo smEch(ltrim($a, '+-')); ?></p>
        <?php endforeach; ?>

        <?php if ($sansPage > 0): ?>
        <p class="ed-avis ed-avis-info">
            <b><?php echo $sansPage; ?></b> piste<?php echo $sansPage > 1 ? 's' : ''; ?> de cette leçon
            n’envoie<?php echo $sansPage > 1 ? 'nt' : ''; ?> l’élève sur aucune page. Faites défiler le
            document à droite, puis cliquez « page affichée » sur la ligne concernée.
        </p>
        <?php endif; ?>

        <!-- ------------------------------------------------ le titre -->
        <article class="ed-brique">
            <header>
                <span class="ed-nom">Titre de la leçon</span>
                <span class="ed-compte-brique">en haut de la page, à côté de la mascotte</span>
            </header>
            <div class="ed-corps">
            <?php if ($titreSimple !== null): ?>
                <div class="ed-ligne">
                    <label class="ed-champ-large">
                        <span class="ed-etiquette">Titre affiché</span>
                        <input type="text" name="titre_texte" class="ed-champ"
                               value="<?php echo smEch($titreSimple['texte']); ?>" required>
                    </label>
                    <label>
                        <span class="ed-etiquette">Couleur</span>
                        <select name="titre_couleur" class="ed-champ ed-champ-court">
                            <?php
                            // La couleur actuelle figure toujours dans le menu, meme si
                            // elle n'est pas dans la liste connue : sans cela, le
                            // navigateur enverrait la premiere de la liste et le titre
                            // changerait de couleur tout seul.
                            $couleurs = smCouleurs();
                            if ($titreSimple['couleur'] !== ''
                                    && !in_array($titreSimple['couleur'], $couleurs, true)) {
                                array_unshift($couleurs, $titreSimple['couleur']);
                            }
                            foreach ($couleurs as $c): ?>
                            <option value="<?php echo smEch($c); ?>"<?php
                                echo $c === $titreSimple['couleur'] ? ' selected' : ''; ?>><?php echo smEch($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <?php
                // « &nbsp; » est une espace technique heritee du balisage d'origine,
                // pas du contenu : l'afficher dans un champ n'aurait aucun sens pour
                // l'enseignante. On la conserve telle quelle, sans la montrer.
                $avantVisible = trim(str_replace(array('&nbsp;', "\xC2\xA0"), ' ', $titreSimple['avant']));
                if ($avantVisible !== ''): ?>
                <label class="ed-bloc">
                    <span class="ed-etiquette">Texte avant le titre coloré</span>
                    <input type="text" name="titre_avant_texte" class="ed-champ"
                           value="<?php echo smEch($avantVisible); ?>">
                </label>
                <?php endif; ?>
                <?php
                // Ce qui entoure le titre n'est PAS renvoye par le formulaire quand
                // il n'est pas modifiable : le serveur le relit dans les donnees.
                // Le faire transiter reechapperait « &nbsp; » en « &amp;nbsp; », et
                // l'espace deviendrait un mot affiche a l'ecran.
                ?>
            <?php else: ?>
                <p class="ed-note">
                    Ce titre colore ses morceaux un par un - c’est le cas des fiches de numération,
                    où chaque chiffre a sa couleur. Il n’est pas modifiable ici pour l’instant,
                    et reste tel quel :
                </p>
                <p class="ed-apercu-titre"><?php echo $blocs['titre']['contenu']; ?></p>
            <?php endif; ?>

                <label class="ed-bloc">
                    <span class="ed-etiquette">Nom de l’onglet du navigateur</span>
                    <input type="text" name="onglet" class="ed-champ"
                           value="<?php echo smEch($lecon['onglet']); ?>">
                </label>
            </div>
        </article>

<article class="ed-brique">
            <header>
                <span class="ed-nom">La leçon en document</span>
                <span class="ed-compte-brique"><?php echo count($documents); ?> disponible<?php
                    echo count($documents) > 1 ? 's' : ''; ?> dans ce dossier</span>
            </header>
            <div class="ed-corps">
                <div class="ed-ligne">
                    <label class="ed-champ-large">
                        <span class="ed-etiquette">Document affiché</span>
                        <select name="pdf" class="ed-champ" id="choix-document">
                            <?php foreach ($documents as $d): ?>
                            <option value="<?php echo smEch($d); ?>"<?php echo $d === $pdf ? ' selected' : ''; ?>
                                    data-url="../lecteur-pdf.php?fichier=<?php echo smEch(rawurlencode($d)); ?>"><?php
                                echo smEch(basename($d)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="ed-etiquette">Hauteur affichée</span>
                        <input type="number" name="hauteur" class="ed-champ ed-champ-court" min="200" max="2000"
                               step="10" value="<?php echo $hauteur; ?>">
                    </label>
                </div>
                <label class="ed-depot ed-depot-mince" for="depot-pdf">
                    <input type="file" id="depot-pdf" name="document" accept=".pdf,application/pdf">
                    <span class="ed-depot-mot">
                        <b>Déposer un nouveau document</b>
                        <i>PDF &middot; jusqu’à 60 Mo &middot; il remplacera celui affiché</i>
                    </span>
                    <span class="ed-depot-choix" id="choix-pdf"></span>
                </label>
                <p class="ed-note">Le document déposé arrive dans
                   <code><?php echo smEch(dirname($pdf)); ?></code> et devient celui de la leçon.
                   L’ancien reste sur le serveur, et reste proposé dans la liste ci-dessus.</p>
            </div>
        </article>

        <!-- --------------------------------------------- la mascotte -->


        <!-- ----------------------------------------------- les pistes -->
        <article class="ed-brique">
            <header>
                <span class="ed-nom">Barre de pistes audio</span>
                <span class="ed-compte-brique"><?php echo $nbPistes; ?> piste<?php echo $nbPistes > 1 ? 's' : ''; ?></span>
            </header>
            <div class="ed-corps">
                <label class="ed-bloc ed-bloc-premier">
                    <span class="ed-etiquette">Titre affiché au-dessus des boutons</span>
                    <input type="text" name="question" class="ed-champ"
                           value="<?php echo smEch($blocs['pistes']['question']); ?>">
                </label>

                <div class="ed-entetes" aria-hidden="true">
                    <span></span><span>Nom du bouton</span><span>Page du document</span><span>Visible</span><span></span>
                </div>

                <ol class="ed-pistes" id="pistes">
                <?php foreach ($blocs['pistes']['pistes'] as $i => $p):
                    $actif = !isset($p['actif']) || $p['actif']; ?>
                    <li class="ed-piste<?php echo $actif ? '' : ' eteinte'; ?>">
                        <span class="ed-poignee" title="Glisser pour déplacer" draggable="true">⠿</span>
                        <?php // Une cle stable par ligne : le navigateur renvoie les champs dans
                              // l'ordre AFFICHE, donc un index d'origine designerait la mauvaise
                              // piste des qu'on en deplace une. La cle, elle, suit sa ligne. ?>
                        <input type="hidden" name="piste_cle[]" value="<?php echo $i; ?>">
                        <input type="hidden" name="piste_son[]" value="<?php echo smEch($p['son']); ?>">
                        <span class="ed-nommage">
                            <input type="text" name="piste_libelle[]" class="ed-libelle"
                                   value="<?php echo smEch($p['libelle']); ?>" aria-label="Nom du bouton">
                            <span class="ed-son" title="<?php echo smEch($p['son']); ?>"><?php
                                echo smEch(basename($p['son'])); ?></span>
                        </span>
                        <span class="ed-saut">
                            <input type="number" name="piste_page[]" class="ed-num" min="1" max="999"
                                   value="<?php echo empty($p['page']) ? '' : (int)$p['page']; ?>"
                                   placeholder="—" aria-label="Page du document">
                            <button type="button" class="ed-prendre" title="Prendre la page affichée dans le document">page affichée</button>
                        </span>
                        <label class="ed-bascule" title="Afficher ou masquer ce bouton pour les élèves">
                            <input type="checkbox" name="piste_actif[]" value="<?php echo $i; ?>"<?php
                                echo $actif ? ' checked' : ''; ?>>
                            <span></span>
                        </label>
                        <button type="button" class="ed-supprimer" title="Retirer cette piste de la leçon">✕</button>
                    </li>
                <?php endforeach; ?>
                </ol>
                <p class="ed-note">Un bouton masqué reste dans la leçon mais n’apparaît plus aux élèves :
                   c’est ce que vous faisiez en le mettant en commentaire. La poignée <b>⠿</b> sert à
                   changer l’ordre des boutons.</p>
            </div>
        </article>

        <!-- --------------------------------------------- ajouter des sons -->
        <article class="ed-brique">
            <header>
                <span class="ed-nom">Ajouter des sons</span>
                <span class="ed-compte-brique">ils viendront en bas de la liste ci-dessus</span>
            </header>
            <div class="ed-corps">
                <label class="ed-depot" for="depot-sons">
                    <input type="file" id="depot-sons" name="sons[]" multiple
                           accept=".mp3,.m4a,.ogg,.wav,audio/*">
                    <span class="ed-depot-mot">
                        <b>Choisir des fichiers son</b>
                        <i>MP3, M4A, OGG ou WAV &middot; jusqu’à 40 Mo par fichier</i>
                    </span>
                    <span class="ed-depot-choix" id="choix-sons"></span>
                </label>

                <?php if ($sonsLibres): ?>
                <p class="ed-etiquette ed-etiquette-espacee">Déjà sur le serveur, dans le dossier de
                   cette leçon, mais pas encore utilisés :</p>
                <div class="ed-libres">
                    <?php foreach ($sonsLibres as $s): ?>
                    <label class="ed-libre">
                        <input type="checkbox" name="sons_libres[]" value="<?php echo smEch($s); ?>">
                        <span><?php echo smEch($s); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <p class="ed-note">Destination : <code><?php echo smEch($dossierSons); ?></code>.
                   Un fichier portant un nom déjà pris est renommé, jamais écrasé.</p>
            </div>
        </article>

        <!-- ------------------------------------------------ les videos -->
        <details class="ed-brique"><summary>Mascotte</summary>
            <div class="ed-corps">
                <div class="ed-mascottes">
                <?php foreach (smMascottes() as $m):
                    $choisie = ($m[0] === $mascotte); ?>
                    <label class="ed-mascotte<?php echo $choisie ? ' choisie' : ''; ?>"
                           title="<?php echo smEch($m[0]); ?>">
                        <input type="radio" name="mascotte" value="<?php echo smEch($m[0]); ?>"
                               data-nom="<?php echo smEch($m[1]); ?>"<?php echo $choisie ? ' checked' : ''; ?>>
                        <span class="ed-cadre"><img src="../<?php echo smEch($m[0]); ?>" alt=""></span>
                        <span class="ed-legende"><?php echo smEch($m[1]); ?><?php
                            echo $m[0] === $conseillee ? ' <b>conseillée</b>' : ''; ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
        </details>
<details class="ed-brique"><summary>Vidéos</summary>
            <div class="ed-corps">

                <div class="ed-ligne">
                    <label>
                        <span class="ed-etiquette">Vidéos par ligne</span>
                        <select name="video_colonnes" class="ed-champ ed-champ-court">
                            <?php foreach (array(1, 2, 3, 4) as $c): ?>
                            <option value="<?php echo $c; ?>"<?php
                                echo $c === $colonnes ? ' selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="ed-note ed-note-cote">Une seule par ligne pour une démonstration à
                       regarder en grand, deux ou trois pour une série courte. Sur un téléphone,
                       elles repassent toutes les unes sous les autres.</p>
                </div>

                <?php if ($videos): ?>
                <div class="ed-entetes ed-entetes-video" aria-hidden="true">
                    <span></span><span>Titre affiché sur la carte</span><span></span>
                </div>
                <ol class="ed-videos" id="videos">
                <?php foreach ($videos as $i => $v): ?>
                    <li class="ed-video-ligne">
                        <span class="ed-poignee" title="Glisser pour déplacer" draggable="true">⠿</span>
                        <input type="hidden" name="video_cle[]" value="<?php echo $i; ?>">
                        <input type="hidden" name="video_fichier[]" value="<?php echo smEch($v['fichier']); ?>">
                        <span class="ed-nommage">
                            <input type="text" name="video_titre[]" class="ed-libelle"
                                   value="<?php echo smEch($v['titre']); ?>"
                                   placeholder="Sans titre" aria-label="Titre de la vidéo">
                            <span class="ed-son" title="<?php echo smEch($v['fichier']); ?>"><?php
                                echo smEch(basename($v['fichier']));
                                $abs = SM_RACINE . '/' . $dossierVideos . '/' . basename($v['fichier']);
                                echo is_file($abs) ? ' · ' . smPoidsLisible(filesize($abs))
                                                   : ' · introuvable sur le serveur'; ?></span>
                        </span>
                        <button type="button" class="ed-supprimer" title="Retirer cette vidéo de la leçon">✕</button>
                    </li>
                <?php endforeach; ?>
                </ol>
                <p class="ed-note">Retirer une vidéo de la liste ne l’efface pas du serveur :
                   elle réapparaît ci-dessous, prête à être remise.</p>
                <?php else: ?>
                <p class="ed-vide">Cette leçon n’a pas encore de vidéo.</p>
                <?php endif; ?>

                <label class="ed-depot ed-depot-mince" for="depot-videos">
                    <input type="file" id="depot-videos" name="videos[]" multiple accept=".mp4,.webm,video/mp4,video/webm">
                    <span class="ed-depot-mot">
                        <b>Déposer une vidéo</b>
                        <i>MP4 ou WEBM &middot; jusqu’à <?php echo round($limiteVideo / 1048576); ?> Mo par fichier</i>
                    </span>
                    <span class="ed-depot-choix" id="choix-videos"></span>
                </label>
                <p class="ed-note">Une vidéo est lourde : comptez plusieurs minutes d’envoi pour
                   100 Mo, et ne fermez pas la page pendant ce temps. Au-delà de
                   <?php echo round($limiteVideo / 1048576); ?> Mo, le serveur refuse le fichier :
                   il faut alors passer par le FTP, comme avant.</p>

                <?php if ($videosLibres): ?>
                <p class="ed-etiquette ed-etiquette-espacee">Déjà sur le serveur, dans le dossier de
                   cette matière, mais utilisées par aucune leçon :</p>
                <div class="ed-libres">
                    <?php foreach ($videosLibres as $v): ?>
                    <label class="ed-libre">
                        <input type="checkbox" name="videos_libres[]" value="<?php echo smEch($v['nom']); ?>">
                        <span><?php echo smEch($v['nom']); ?> <i><?php
                            echo smEch(smPoidsLisible($v['poids'])); ?></i></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <p class="ed-note">Destination : <code><?php echo smEch($dossierVideos); ?></code>,
                   le dossier de la matière, là où sont déjà les vidéos du site.
                   Un fichier portant un nom déjà pris est renommé, jamais écrasé.</p>
            </div>
        </details>

        <!-- ------------------------------------------------- les images -->
        <!-- L'identifiant porte sur la brique et non sur la liste, contrairement
             aux pistes et aux videos : une lecon peut avoir plusieurs series
             d'images, donc plusieurs <ol>. C'est la brique qui les contient
             toutes, et c'est donc elle qui ecoute les clics. -->
        <details class="ed-brique" id="images"><summary>Images</summary>
            <div class="ed-corps">

                <?php if ($galeries): ?>
                <?php foreach ($galeries as $gi => $g): ?>
                <fieldset class="ed-serie">
                    <legend>Série <?php echo $gi + 1; ?></legend>

                    <div class="ed-ligne">
                        <label>
                            <span class="ed-etiquette">Images par ligne</span>
                            <select name="image_colonnes[]" class="ed-champ ed-champ-court">
                                <?php foreach (array(1, 2, 3, 4) as $c): ?>
                                <option value="<?php echo $c; ?>"<?php
                                    echo $c === $g['colonnes'] ? ' selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <p class="ed-note ed-note-cote">Une seule par ligne pour un document à
                           regarder en détail, deux ou trois pour une série de vignettes.
                           Sur un téléphone, elles repassent les unes sous les autres.</p>
                    </div>

                    <?php if ($g['images']): ?>
                    <ol class="ed-images">
                    <?php foreach ($g['images'] as $im): ?>
                        <li class="ed-image-ligne">
                            <input type="hidden" name="image_galerie[]" value="<?php echo $gi; ?>">
                            <input type="hidden" name="image_fichier[]" value="<?php echo smEch($im['fichier']); ?>">
                            <img class="ed-vignette" src="<?php echo smEch($apercu($im['fichier'])); ?>"
                                 alt="" loading="lazy">
                            <span class="ed-nommage">
                                <input type="text" name="image_alt[]" class="ed-libelle"
                                       value="<?php echo smEch($im['alt']); ?>"
                                       placeholder="Décrire l’image en une phrase"
                                       aria-label="Description de l’image">
                                <span class="ed-son" title="<?php echo smEch($im['fichier']); ?>"><?php
                                    echo smEch(basename($im['fichier'])); ?></span>
                            </span>
                            <button type="button" class="ed-supprimer" title="Retirer cette image de la leçon">✕</button>
                        </li>
                    <?php endforeach; ?>
                    </ol>
                    <?php else: ?>
                    <p class="ed-vide">Cette série est vide : elle disparaîtra à l’enregistrement.</p>
                    <?php endif; ?>
                </fieldset>
                <?php endforeach; ?>

                <p class="ed-note">La description sert aux élèves qui ne voient pas l’image :
                   lecteur d’écran, image qui ne charge pas, impression en noir et blanc.
                   Dire ce qu’on y voit, pas « image » ni « photo ».</p>
                <?php else: ?>
                <p class="ed-vide">Cette leçon n’a pas encore d’image.</p>
                <?php endif; ?>

                <label class="ed-depot ed-depot-mince" for="depot-images">
                    <input type="file" id="depot-images" name="images[]" multiple
                           accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                    <span class="ed-depot-mot">
                        <b>Déposer une image</b>
                        <i>JPG, PNG, WEBP ou GIF</i>
                    </span>
                    <span class="ed-depot-choix" id="choix-images"></span>
                </label>
                <p class="ed-note">Une image déposée arrive dans la dernière série, sans
                   description : c’est à vous de la décrire ensuite.</p>

                <?php if ($imagesLibres): ?>
                <p class="ed-etiquette ed-etiquette-espacee">Déjà sur le serveur, dans le dossier de
                   cette matière, mais utilisées par aucune leçon :</p>
                <div class="ed-libres">
                    <?php foreach ($imagesLibres as $im): ?>
                    <label class="ed-libre">
                        <input type="checkbox" name="images_libres[]" value="<?php echo smEch($im['nom']); ?>">
                        <span><?php echo smEch($im['nom']); ?> <i><?php
                            echo smEch(smPoidsLisible($im['poids'])); ?></i></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <p class="ed-note">Destination : <code><?php echo smEch($dossierImages); ?></code>,
                   le dossier de la matière. Les images déjà en place, elles, vivent dans
                   <code>img-140</code>, la bibliothèque du site : elles ne bougent pas.
                   Un fichier portant un nom déjà pris est renommé, jamais écrasé.</p>
            </div>
        </details>

        <!-- --------------------------------------------- les textes libres -->
        <details class="ed-brique"><summary>Textes</summary>
            <div class="ed-corps">

                <p class="ed-note">Deux sortes de texte. Un <b>intertitre</b> annonce une partie
                   de la leçon, en gros et en couleur. Une <b>légende</b> est une phrase discrète,
                   centrée, pour commenter ce qui vient juste au-dessus.</p>

                <?php if ($textes): ?>
                <ol class="ed-textes">
                <?php foreach ($textes as $tx): ?>
                    <li class="ed-texte-ligne">
                        <input type="hidden" name="texte_rang[]" value="<?php echo (int)$tx['rang']; ?>">
                        <div class="ed-texte-reglages">
                            <label>
                                <span class="ed-etiquette">Sorte</span>
                                <select name="texte_type[]" class="ed-champ ed-champ-court">
                                    <option value="section"<?php
                                        echo $tx['type'] === 'section' ? ' selected' : ''; ?>>Intertitre</option>
                                    <option value="legende"<?php
                                        echo $tx['type'] === 'legende' ? ' selected' : ''; ?>>Légende</option>
                                </select>
                            </label>
                            <label>
                                <span class="ed-etiquette">Couleur</span>
                                <select name="texte_couleur[]" class="ed-champ ed-champ-court">
                                    <?php foreach (smCouleurs() as $c): ?>
                                    <option value="<?php echo smEch($c); ?>"<?php
                                        echo $c === $tx['couleur'] ? ' selected' : ''; ?>><?php
                                        echo smEch($c); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <textarea name="texte_contenu[]" class="ed-champ ed-zone" rows="2"
                                  placeholder="Laisser vide pour supprimer ce texte"
                                  aria-label="Contenu du texte"><?php echo smEchTexte($tx['contenu']); ?></textarea>
                    </li>
                <?php endforeach; ?>
                </ol>
                <p class="ed-note">Vider un texte le supprime de la leçon à l’enregistrement.
                   Sa place dans la page, elle, ne bouge pas : un intertitre sépare deux parties,
                   le déplacer changerait le sens de la leçon.</p>
                <?php else: ?>
                <p class="ed-vide">Cette leçon n’a pas encore de texte libre.</p>
                <?php endif; ?>

                <fieldset class="ed-serie">
                    <legend>Ajouter un texte</legend>
                    <div class="ed-texte-reglages">
                        <label>
                            <span class="ed-etiquette">Sorte</span>
                            <select name="texte_neuf_type" class="ed-champ ed-champ-court">
                                <option value="section">Intertitre</option>
                                <option value="legende">Légende</option>
                            </select>
                        </label>
                        <label>
                            <span class="ed-etiquette">Couleur</span>
                            <select name="texte_neuf_couleur" class="ed-champ ed-champ-court">
                                <?php foreach (smCouleurs() as $c): ?>
                                <option value="<?php echo smEch($c); ?>"><?php echo smEch($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <textarea name="texte_neuf_contenu" class="ed-champ ed-zone" rows="2"
                              placeholder="Laisser vide pour ne rien ajouter"
                              aria-label="Contenu du nouveau texte"></textarea>
                    <p class="ed-note">Le nouveau texte se place juste avant le document.
                       Pour le mettre ailleurs, il faudra passer par le fichier de données.</p>
                </fieldset>
            </div>
        </details>

        <!-- -------------------------------------------------- le PDF -->


        <details class="ed-options-danger"><summary>Versions et suppression</summary><p class="ed-pied">
<a href="versions.php?l=<?php echo rawurlencode($chemin); ?>">Versions précédentes</a>
            <a class="ed-lien-danger" href="supprimer.php?l=<?php echo rawurlencode($chemin); ?>">Supprimer la leçon</a>
            <span>Une copie des données est gardée avant toute suppression.</span>
        </p></details>

    </div>

    <aside class="ed-lecteur">
        <p class="ed-colonne-titre">Le document <span>faites défiler, puis cliquez « page affichée » sur une piste</span></p>
        <iframe id="lecteur" src="../lecteur-pdf.php?fichier=<?php echo rawurlencode($pdf); ?>"
                title="Document de la leçon"></iframe>
    </aside>

</main>
</div>
</form>

<script src="editeur.js?v=20260901-01"></script>
</body>
</html>
