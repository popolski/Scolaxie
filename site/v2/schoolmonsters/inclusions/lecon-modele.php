<?php
// Le modele d'affichage d'une lecon. La page en ligne n'est plus qu'un talon
// de deux lignes qui appelle ce fichier ; tout le contenu vient d'un JSON.
//
// Pourquoi : tant que la page EST la donnee, la moindre harmonisation demande
// une campagne d'expressions regulieres sur des centaines de fichiers. C'est
// ce qui a coute les chantiers 1 et 2. Avec un modele, la prochaine devient
// une modification a un seul endroit.
//
// Et rien de ce que tape l'enseignante ne devient du PHP : les valeurs sont
// echappees a l'affichage, le fichier .php pose sur le serveur ne contient
// jamais que le talon.

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['nom'])) {
    header('location:../../index.php');
    exit();
}
require(__DIR__ . '/../theme.php');

// Le chemin de la lecon se deduit du talon qui nous appelle : pas de
// parametre a recopier, donc pas de talon qui pointe vers la mauvaise lecon.
$smRacine = dirname(__DIR__);
$smFiche = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$smRelatif = ltrim(substr($smFiche, strlen(str_replace('\\', '/', $smRacine))), '/');
$smJson = $smRacine . '/lecons/' . preg_replace('/\.php$/', '.json', $smRelatif);

if (!is_file($smJson)) {
    http_response_code(500);
    exit('Lecon introuvable : ' . htmlspecialchars($smRelatif));
}
$lecon = json_decode(file_get_contents($smJson), true);
if (!is_array($lecon) || !isset($lecon['blocs'])) {
    http_response_code(500);
    exit('Lecon illisible : ' . htmlspecialchars($smRelatif));
}

function smEch($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Les valeurs qui deviennent un nom de classe CSS ne sont jamais reprises
// telles quelles : elles sont cherchees dans une liste fermee. Une couleur
// inconnue redevient la couleur par defaut plutot que d'entrer dans le
// balisage.
function smDansListe($valeur, $liste, $defaut)
{
    return in_array((string)$valeur, $liste, true) ? (string)$valeur : $defaut;
}
function smCouleurTexte($v) {
    return smDansListe($v, array('bleu', 'bleuunite', 'rougedizaine', 'vertcentaine',
                                 'vert', 'vertlecture', 'orange', 'jaune', 'rouge',
                                 'rose', 'petitrose', 'mauve', 'gris', 'noir',
                                 'bleuinformatique', 'rougeanglais', 'lila',
                                 'marronoral'), 'bleu');
}
?>
<!DOCTYPE html>

<html lang="fr">
    <head>
        <title><?php echo smEch($lecon['onglet']); ?></title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../../css/grille.css?v=20260831-02"/>
        <link rel="stylesheet" href="../../css/style1.css?v=20260912-conformite"/>
        <link rel="stylesheet" href="../../css/theme-clair.css"/>
        <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite"/>
        <link rel="stylesheet" href="../../css/lecon.css?v=20260912-conformite"/>
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body class="gx-typo gx-app-schoolmonsters sm-lecon-page <?php echo $bodyClass; ?><?php echo in_array('pdf',array_column($lecon['blocs'],'type'),true)?' sm-pdf-pret':''; ?>">
       <?php require(__DIR__ . '/bandeau-galaxie.php'); ?>

       <main class="sm-lecon-contenu<?php echo in_array('pdf',array_column($lecon['blocs'],'type'),true)?' sm-avec-pdf':''; ?> gx-largeur-<?php echo in_array('pdf',array_column($lecon['blocs'],'type'),true)?'riche':'lecture'; ?>" id="contenu">
<?php foreach ($lecon['blocs'] as $smIndex => $bloc):
    switch ($bloc['type']):

    case 'titre': ?>
            <div class="sm-lecon-titre gx-carte">
                <div class="sm-lecon-intitule"><h1<?php echo $bloc['classe_p'] !== '' ? ' class="' . smEch($bloc['classe_p']) . '"' : ''; ?>><?php
                    // Le titre porte des <span> de couleur, parfois un par
                    // chiffre sur les fiches de numeration : c'est du balisage
                    // voulu, pose par l'editeur et non saisi librement.
                    echo $bloc['contenu']; ?></h1></div>
<?php $smMascotte = isset($lecon['blocs'][$smIndex + 1]) && $lecon['blocs'][$smIndex + 1]['type'] === 'mascotte'
        ? $lecon['blocs'][$smIndex + 1] : null;
      if ($smMascotte): ?>
                <img class="sm-lecon-mascotte" alt="" src="../../<?php echo smEch($smMascotte['fichier']); ?>"/>
<?php endif; ?>
            </div>
<?php break;

    case 'mascotte': ?>
<?php if ($smIndex > 0 && $lecon['blocs'][$smIndex - 1]['type'] === 'titre') { break; } ?>
            <div class="sm-lecon-mascotte">
                <img alt="" src="../../<?php echo smEch($bloc['fichier']); ?>"/>
            </div>
            <div class="clear"></div>
<?php break;

    case 'pistes':
        // Une lecon en video peut n'avoir aucun son. Afficher la barre vide
        // laisserait un cadre blanc sans rien dedans : on ne l'affiche pas.
        $smActives = 0;
        foreach ($bloc['pistes'] as $smP) {
            if (!isset($smP['actif']) || $smP['actif']) { $smActives++; }
        }
        if ($smActives === 0) { break; } ?>
            <aside class="sm-lecon-pistes" aria-label="Contenu de la leçon">
                <h2>À lire et à écouter</h2>
                <div class="gx-pistes">
                    <p>
<?php foreach ($bloc['pistes'] as $piste):
        if (isset($piste['actif']) && !$piste['actif']) { continue; } ?>
                        <button type="button" class="btn-audio-quiz" data-audio-src="<?php echo smEch($piste['son']); ?>"<?php
                            echo !empty($piste['page']) ? ' data-page="' . (int)$piste['page'] . '"' : ''; ?>><?php
                            echo smEch($piste['libelle']); ?></button>
<?php endforeach; ?>
                    </p>
                </div>
                <p id="LaQuestion"><?php echo smEch($bloc['question']); ?></p>
                <p>
                 <audio controls id="lecteurAudioQuiz"></audio>
                </p>
            </aside>
<?php break;

    case 'pdf': ?>
            <div class="sm-lecon-document">
                <iframe src="../../lecteur-pdf.php?fichier=<?php echo rawurlencode($bloc['fichier']); ?>&amp;v=20260909-audio-integre" width="100%" height="<?php echo (int)$bloc['hauteur']; ?>" style="border:none;border-radius:12px;" allowfullscreen title="Document PDF">
                </iframe>
            </div>
<?php break;

    // --------------------------------------------------------------- video
    // Une galerie, meme pour une seule video : c'est la meme carte, le meme
    // cadre et le meme titre a un ou a quatre exemplaires. Deux pages avaient
    // deja cette galerie, ecrite a la main avec ses propres classes ; elles
    // passent ici sans changer d'aspect.
    case 'videos':
        $smCol = isset($bloc['colonnes']) ? (int)$bloc['colonnes'] : 2;
        if ($smCol < 1 || $smCol > 4) { $smCol = 2; }
        $smCartes = isset($bloc['cartes']) && is_array($bloc['cartes']) ? $bloc['cartes'] : array();
        if (!$smCartes) { break; } ?>
            <div class="grid_12 gx-videos gx-videos-<?php echo $smCol; ?>">
<?php foreach ($smCartes as $smCarte):
        $smTitre = isset($smCarte['titre']) ? trim((string)$smCarte['titre']) : '';
        $smFichier = isset($smCarte['fichier']) ? (string)$smCarte['fichier'] : '';
        if ($smFichier === '') { continue; }
        $smPoster = isset($smCarte['poster']) ? (string)$smCarte['poster'] : ''; ?>
                <article class="gx-video<?php echo $smTitre === '' ? ' gx-video-nu' : ''; ?>">
<?php if ($smTitre !== ''): ?>
                    <h2><?php // le titre peut tenir sur deux lignes : le retour est
                              // dans la donnee, jamais une balise saisie.
                              echo nl2br(smEch($smTitre)); ?></h2>
<?php endif; ?>
                    <video controls preload="metadata" src="<?php echo smEch($smFichier); ?>"<?php
                        echo $smPoster !== '' ? ' poster="../../' . smEch($smPoster) . '"' : ''; ?>></video>
                </article>
<?php endforeach; ?>
            </div>
            <div class="clear"></div>
<?php break;

    // ------------------------------------------------------------- legende
    // Une phrase courte, centree, de la couleur de la matiere. C'est ce que
    // les pages ecrites a la main posaient au-dessus de chaque video ; quand
    // la video a un titre de carte, la legende n'a plus lieu d'etre.
    case 'legende': ?>
            <div class="grid_12">
                <p class="<?php echo smCouleurTexte(isset($bloc['couleur']) ? $bloc['couleur'] : ''); ?>"
                   style="text-align: center"><?php echo smEch($bloc['contenu']); ?></p>
            </div>
            <div class="clear"></div>
<?php break;

    // ------------------------------------------------------------- section
    // Un intertitre, pour separer deux parties d'une meme lecon.
    case 'section': ?>
            <div class="grid_12">
                <h2 class="gx-section"><?php echo nl2br(smEch($bloc['contenu'])); ?></h2>
            </div>
            <div class="clear"></div>
<?php break;

    // -------------------------------------------------------------- images
    // Des illustrations, une a quatre par ligne. L'attribut alt est ecrit
    // dans les donnees : le chantier 2 a montre ce que coute une image sans
    // description pour un eleve qui utilise une synthese vocale.
    case 'images':
        $smCol = isset($bloc['colonnes']) ? (int)$bloc['colonnes'] : 2;
        if ($smCol < 1 || $smCol > 4) { $smCol = 2; }
        $smImages = isset($bloc['images']) && is_array($bloc['images']) ? $bloc['images'] : array();
        if (!$smImages) { break; } ?>
            <div class="grid_12 gx-images gx-images-<?php echo $smCol; ?>">
<?php foreach ($smImages as $smImage):
        $smFichier = isset($smImage['fichier']) ? (string)$smImage['fichier'] : '';
        if ($smFichier === '') { continue; } ?>
                <img src="<?php echo smEch($smFichier); ?>"
                     alt="<?php echo smEch(isset($smImage['alt']) ? $smImage['alt'] : ''); ?>">
<?php endforeach; ?>
            </div>
            <div class="clear"></div>
<?php break;

    endswitch;
endforeach; ?>
       </main>
    <script src="../../javascript/quiz-audio.js?v=20260909-fg3"></script>
    </body>
</html>
