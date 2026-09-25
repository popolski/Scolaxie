<?php
// Socle commun a l'editeur de lecons : la garde, et les quelques fonctions
// qui manipulent les fichiers de donnees.
//
// Regle qui commande tout le reste : RIEN de ce que tape l'enseignante ne
// devient du PHP. L'editeur n'ecrit que des fichiers .json, dans le seul
// dossier lecons/, et seulement pour une lecon qui existe deja. Le fichier
// .php pose sur le serveur reste le talon de deux lignes ecrit une fois pour
// toutes par la conversion.

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header('location:../index.php');
    exit();
}

// L'éditeur reste réservé au compte configuré par l'installation locale.
require_once dirname(__DIR__, 3) . '/configuration.php';
$idEditeur = (int)scolaxieConfig('SCOLAXIE_EDITOR_TEACHER_ID');
if ($idEditeur <= 0) {
    throw new RuntimeException('Compte éditeur invalide');
}
define('SM_ENSEIGNANTE', $idEditeur);
if (!isset($_SESSION['id_enseignant']) || (int)$_SESSION['id_enseignant'] !== SM_ENSEIGNANTE) {
    http_response_code(403);
    exit('L’éditeur de leçons n’est pas encore ouvert à votre compte.');
}

// Seul le CE1 est gere pour l'instant : les 191 lecons de cette famille y sont
// toutes, et le CE2 n'a aucune fiche a pistes.
define('SM_NIVEAU', 'CE1');

define('SM_RACINE', dirname(__DIR__));
define('SM_LECONS', SM_RACINE . '/lecons');
define('SM_VERSIONS', SM_LECONS . '/.versions');

require(__DIR__ . '/depot.php');

/**
 * Verifie qu'un chemin de lecon est acceptable, et renvoie le chemin du
 * fichier de donnees. Renvoie null si quoi que ce soit cloche.
 *
 * On ne fait pas confiance a ce qui arrive du navigateur : seules les formes
 * attendues passent, « .. » est refuse, et on verifie pour finir que le
 * fichier resolu est bien SOUS lecons/ - un lien symbolique ou un encodage
 * exotique ne doit pas permettre d'ecrire ailleurs.
 */
function smFichierDonnees($chemin)
{
    if (!is_string($chemin) || $chemin === '') {
        return null;
    }
    if (strpos($chemin, '..') !== false || strpos($chemin, "\0") !== false) {
        return null;
    }
    if (!preg_match('#^P[1-5]_' . SM_NIVEAU . '/[a-z]+/[A-Za-z0-9._-]+\.php$#', $chemin)) {
        return null;
    }
    $json = SM_LECONS . '/' . substr($chemin, 0, -4) . '.json';
    if (!is_file($json)) {
        return null;
    }
    $reel = realpath($json);
    $racine = realpath(SM_LECONS);
    if ($reel === false || $racine === false || strpos($reel, $racine . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $json;
}

/** Toutes les lecons connues, triees, avec de quoi les presenter. */
function smListeLecons()
{
    $out = array();
    foreach (glob(SM_LECONS . '/P*_' . SM_NIVEAU . '/*/*.json') as $json) {
        $rel = str_replace('\\', '/', substr($json, strlen(SM_LECONS) + 1));
        $chemin = substr($rel, 0, -5) . '.php';
        $d = json_decode(file_get_contents($json), true);
        if (!is_array($d) || !isset($d['blocs'])) {
            continue;
        }
        list($periode, $matiere) = explode('/', $rel);
        $titre = '';
        $pistes = 0;
        $sansPage = 0;
        foreach ($d['blocs'] as $b) {
            if ($b['type'] === 'titre') {
                $titre = smTitreLisible($b['contenu']);
            }
            if ($b['type'] === 'pistes') {
                foreach ($b['pistes'] as $p) {
                    $pistes++;
                    if (empty($p['page'])) { $sansPage++; }
                }
            }
        }
        $out[] = array(
            'chemin' => $chemin, 'periode' => $periode, 'matiere' => $matiere,
            'titre' => $titre !== '' ? $titre : basename($chemin, '.php'),
            'pistes' => $pistes, 'sans_page' => $sansPage,
            'modifie' => filemtime($json),
        );
    }
    usort($out, function ($a, $b) {
        return strcmp($a['periode'] . $a['matiere'] . $a['titre'],
                      $b['periode'] . $b['matiere'] . $b['titre']);
    });
    return $out;
}

/**
 * Le titre est-il decomposable en « texte avant + un morceau colore + texte
 * apres » ? Vingt et une fiches de numeration colorent chaque chiffre
 * separement : celles-la ne se laissent pas reduire a un champ de texte, et
 * l'editeur le dit au lieu de les abimer.
 */
function smTitreSimple($contenu)
{
    if (substr_count($contenu, '<span') !== 1) {
        return null;
    }
    if (!preg_match('#^(.*?)<span class="([A-Za-z0-9_-]+)"\s*>(.*?)</span>(.*)$#s', $contenu, $m)) {
        return null;
    }
    foreach (array($m[1], $m[3], $m[4]) as $bout) {
        if (strpos($bout, '<') !== false) {
            return null;
        }
    }
    return array('avant' => $m[1], 'couleur' => $m[2], 'texte' => $m[3], 'apres' => $m[4]);
}

/** Les mascottes reellement utilisees sur le site, et la matiere de chacune. */
function smMascottes()
{
    return array(
        array('img-140/01-2.png', 'Monstre des nombres', 'numeration'),
        array('img-140/02-2.png', 'Monstre de la conjugaison', 'conjugaison'),
        array('img-140/03-2.png', 'Monstre de l’orthographe', 'orthographe'),
        array('img-140/04-2.png', 'Monstre du vocabulaire', 'vocabulaire'),
        array('img-140/05-2.png', 'Monstre de la grammaire', 'grammaire'),
        array('img-140/06-2.png', 'Monstre des problèmes', 'problemes'),
        array('img-140/07-2.png', 'Monstre de la géométrie', 'geometrie'),
        array('img-140/08-2.png', 'Monstre des mesures', 'mesures'),
        array('img-220/06-2.png', 'Monstre des problèmes (grand)', ''),
        array('img-140/lecture.png', 'Le lecteur', 'lecture'),
        array('img-140/redaction.png', 'L’écrivain', 'redaction'),
        array('img-140/oral.png', 'Le parleur', 'oral'),
        array('img-140/nessie.png', 'Nessie', 'anglais'),
        array('img-140/informatique.png', 'L’ordinateur', 'informatique'),
        array('img-140/monde.png', 'Le globe', 'monde'),
    );
}

/**
 * Les couleurs de titre en usage sur le site.
 *
 * La liste doit couvrir TOUTES les couleurs presentes dans les lecons : une
 * couleur absente d'ici ne figure pas dans le menu deroulant, le navigateur
 * envoie alors la premiere de la liste, et le titre change de couleur sans
 * que personne l'ait demande. « lila » et « marronoral » manquaient : onze
 * lecons auraient vire au bleu au premier enregistrement.
 */
function smCouleurs()
{
    return array('bleu', 'bleuunite', 'rougedizaine', 'vertcentaine', 'vert', 'vertlecture',
                 'orange', 'jaune', 'rouge', 'rose', 'petitrose', 'mauve', 'gris', 'noir',
                 'bleuinformatique', 'rougeanglais', 'lila', 'marronoral');
}

/**
 * Le titre d'une lecon, en texte lisible par un humain.
 *
 * strip_tags() retire les balises mais laisse les entites : sans decodage, la
 * liste affichait « &nbsp;L'infinitif » en toutes lettres. On decode, puis on
 * ecrase l'espace insecable, qui est ici du balisage et non du contenu.
 */
function smTitreLisible($contenu)
{
    // Un <br> est une espace a l'ecran : sans cette substitution, strip_tags()
    // collait les mots et la liste affichait « Decomposer un nombreen unites ».
    $t = preg_replace('#<br\s*/?>#i', ' ', $contenu);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("Â ", ' ', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}

/**
 * Le dossier ou vivent les videos d'une lecon, relatif a la racine du site.
 *
 * Contrairement aux sons, qui ont leur sous-dossier, les videos sont posees a
 * cote de la page, dans le dossier de matiere : c'est la que se trouvent deja
 * les 51 videos du CE1. Ce dossier contient donc aussi des .php, et il ne faut
 * SURTOUT PAS y poser le .htaccess de protection : c'est exactement ce qui a
 * rendu neuf lecons inaccessibles aux eleves le 30/08/2026.
 */
function smDossierVideos($chemin)
{
    return dirname($chemin);
}


/** Les cartes video d'une lecon, a plat, avec le rang de leur galerie. */
function smVideosLecon($lecon)
{
    $out = array();
    foreach ($lecon['blocs'] as $rang => $b) {
        if ($b['type'] !== 'videos' || !isset($b['cartes'])) { continue; }
        foreach ($b['cartes'] as $i => $c) {
            $out[] = array(
                'bloc' => $rang,
                'rang' => $i,
                'titre' => isset($c['titre']) ? (string)$c['titre'] : '',
                'fichier' => isset($c['fichier']) ? trim((string)$c['fichier']) : '',
                'poster' => isset($c['poster']) ? trim((string)$c['poster']) : '',
            );
        }
    }
    return $out;
}


/**
 * Les videos posees dans le dossier de la lecon dont AUCUNE lecon ne se sert.
 *
 * Le dossier de matiere est partage par toutes les lecons de la matiere : une
 * video utilisee par la lecon voisine ne doit pas etre proposee comme libre,
 * sinon on la croirait disponible alors qu'elle sert deja.
 */
function smVideosLibres($chemin, $lecon)
{
    $dossier = smDossierVideos($chemin);
    $absolu = SM_RACINE . '/' . $dossier;
    if (!is_dir($absolu)) { return array(); }

    $utilisees = array();
    foreach (smVideosLecon($lecon) as $v) {
        $utilisees[basename($v['fichier'])] = true;
    }
    foreach (smListeLecons() as $l) {
        if ($l['chemin'] === $chemin || dirname($l['chemin']) !== $dossier) { continue; }
        $d = json_decode((string)@file_get_contents(
            SM_LECONS . '/' . substr($l['chemin'], 0, -4) . '.json'), true);
        if (!is_array($d) || !isset($d['blocs'])) { continue; }
        foreach (smVideosLecon($d) as $v) {
            $utilisees[basename($v['fichier'])] = true;
        }
    }

    $out = array();
    foreach ((array)scandir($absolu) as $f) {
        if ($f === '.' || $f === '..' || isset($utilisees[$f])) { continue; }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (isset(smFormatsVideo()[$ext]) && is_file($absolu . '/' . $f)) {
            $out[] = array('nom' => $f, 'poids' => (int)@filesize($absolu . '/' . $f));
        }
    }
    usort($out, function ($a, $b) { return strcmp($a['nom'], $b['nom']); });
    return $out;
}


/**
 * Ou vont les NOUVELLES images d'une lecon : le dossier de la matiere, comme
 * les videos.
 *
 * Ce n'est pas la que vivent les anciennes. Mesure du 31/08/2026 : les 31
 * images des 11 lecons qui en ont pointent toutes vers ../../img-140, la
 * bibliotheque du site, qui compte 165 fichiers - mascottes, decors, coupons.
 * Y deposer les photos d'une enseignante melangerait deux choses, et la liste
 * des images « libres » y afficherait 165 entrees sans rapport.
 *
 * Les dossiers de matiere, eux, ne contiennent que 10 images pour tout le site :
 * la liste des libres y reste donc lisible. Les anciennes images gardent leur
 * chemin et continuent de s'afficher : rien n'est deplace.
 */
function smDossierImages($chemin)
{
    return dirname($chemin);
}


/**
 * Les textes libres d'une lecon : intertitres et legendes.
 *
 * Chacun garde le rang qu'il occupe dans la lecon. Contrairement aux images,
 * ils ne sont pas rassembles : un intertitre separe deux parties, sa place EST
 * son sens. Un texte vide a l'enregistrement disparait.
 */
function smTextesLecon($lecon)
{
    $out = array();
    foreach ($lecon['blocs'] as $rang => $b) {
        if ($b['type'] !== 'section' && $b['type'] !== 'legende') { continue; }
        $out[] = array(
            'rang' => $rang,
            'type' => $b['type'],
            'contenu' => isset($b['contenu']) ? (string)$b['contenu'] : '',
            'couleur' => isset($b['couleur']) ? (string)$b['couleur'] : 'bleu',
        );
    }
    return $out;
}


/**
 * Les galeries d'images d'une lecon, chacune avec son reglage.
 *
 * Contrairement aux videos, les galeries ne sont PAS fondues en une seule.
 * Mesure du 31/08/2026 : les 8 lecons qui ont plusieurs galeries melangent
 * toutes les nombres de colonnes, toujours 2 puis 1. Les fondre ferait passer
 * la derniere image, aujourd'hui pleine largeur, en demi-largeur.
 */
function smGaleriesImages($lecon)
{
    $out = array();
    foreach ($lecon['blocs'] as $rang => $b) {
        if ($b['type'] !== 'images') { continue; }
        $col = isset($b['colonnes']) ? (int)$b['colonnes'] : 2;
        if ($col < 1 || $col > 4) { $col = 2; }
        $images = array();
        foreach (isset($b['images']) && is_array($b['images']) ? $b['images'] : array() as $im) {
            $f = isset($im['fichier']) ? trim((string)$im['fichier']) : '';
            if ($f === '') { continue; }
            $images[] = array('fichier' => $f, 'alt' => isset($im['alt']) ? (string)$im['alt'] : '');
        }
        $out[] = array('rang' => $rang, 'colonnes' => $col, 'images' => $images);
    }
    return $out;
}


/** Les images d'une lecon, a plat, avec le rang de leur galerie. */
function smImagesLecon($lecon)
{
    $out = array();
    foreach ($lecon['blocs'] as $rang => $b) {
        if ($b['type'] !== 'images' || !isset($b['images'])) { continue; }
        foreach ($b['images'] as $i => $c) {
            $out[] = array(
                'bloc' => $rang,
                'rang' => $i,
                'alt' => isset($c['alt']) ? (string)$c['alt'] : '',
                'fichier' => isset($c['fichier']) ? trim((string)$c['fichier']) : '',
            );
        }
    }
    return $out;
}


/**
 * Les images posees dans le dossier de la matiere dont AUCUNE lecon ne se sert.
 *
 * Meme precaution que pour les videos : le dossier est partage par toutes les
 * lecons de la matiere, donc une image utilisee par la lecon voisine ne doit
 * pas etre proposee ici, sinon on la croirait disponible.
 *
 * Une difference tout de meme : les pages de matiere affichent aussi des images
 * hors lecon - mascottes, decors, boutons. Elles vivent dans img-140 et dans
 * les dossiers du site, pas dans le dossier de la matiere, donc elles ne
 * remontent pas ici. Si un jour c'etait le cas, il faudrait les ecarter.
 */
function smImagesLibres($chemin, $lecon)
{
    $dossier = smDossierImages($chemin);
    $absolu = SM_RACINE . '/' . $dossier;
    if (!is_dir($absolu)) { return array(); }

    $utilisees = array();
    foreach (smImagesLecon($lecon) as $v) {
        $utilisees[basename($v['fichier'])] = true;
    }
    foreach (smListeLecons() as $l) {
        if ($l['chemin'] === $chemin || dirname($l['chemin']) !== $dossier) { continue; }
        $d = json_decode((string)@file_get_contents(
            SM_LECONS . '/' . substr($l['chemin'], 0, -4) . '.json'), true);
        if (!is_array($d) || !isset($d['blocs'])) { continue; }
        foreach (smImagesLecon($d) as $v) {
            $utilisees[basename($v['fichier'])] = true;
        }
    }

    $out = array();
    foreach ((array)scandir($absolu) as $f) {
        if ($f === '.' || $f === '..' || isset($utilisees[$f])) { continue; }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (isset(smFormatsImage()[$ext]) && is_file($absolu . '/' . $f)) {
            $out[] = array('nom' => $f, 'poids' => (int)@filesize($absolu . '/' . $f));
        }
    }
    usort($out, function ($a, $b) { return strcmp($a['nom'], $b['nom']); });
    return $out;
}


/** Un poids de fichier lisible : « 14 Mo », « 812 ko ». */
function smPoidsLisible($octets)
{
    $octets = (int)$octets;
    if ($octets >= 1048576) { return round($octets / 1048576) . ' Mo'; }
    if ($octets >= 1024) { return round($octets / 1024) . ' ko'; }
    return $octets . ' o';
}


/**
 * Les documents PDF disponibles dans le dossier de la lecon.
 *
 * l’enseignante ne peut pas encore deposer un fichier depuis l'editeur, mais 42 PDF
 * sont deja sur le serveur sans etre utilises par ces fiches. Les proposer lui
 * evite un aller-retour en FTP pour un simple changement de document.
 */
function smDocumentsVoisins($pdf)
{
    $dossier = dirname($pdf);
    if ($dossier === '' || $dossier === '.' || strpos($dossier, '..') !== false) {
        return array($pdf);
    }
    $absolu = SM_RACINE . '/' . $dossier;
    $reel = realpath($absolu);
    $racine = realpath(SM_RACINE);
    if ($reel === false || $racine === false || strpos($reel, $racine . DIRECTORY_SEPARATOR) !== 0) {
        return array($pdf);
    }
    $out = array();
    foreach ((array)glob($absolu . '/*.[pP][dD][fF]') as $f) {
        $out[] = $dossier . '/' . basename($f);
    }
    if (!in_array($pdf, $out, true)) {
        array_unshift($out, $pdf);   // le document actuel, meme s'il a disparu du disque
    }
    sort($out);
    return $out;
}

/** Un document est-il acceptable pour cette lecon ? Meme dossier, rien d'autre. */
function smDocumentValide($pdf, $actuel)
{
    return is_string($pdf) && $pdf !== '' && strpos($pdf, "\0") === false
        && in_array($pdf, smDocumentsVoisins($actuel), true);
}

/**
 * Le dossier ou vivent les sons d'une lecon, relatif a la racine du site.
 *
 * Il n'est jamais recu du navigateur : il est deduit des pistes existantes.
 * Sept lecons rangent leurs sons directement dans le dossier de matiere, et
 * cinq dossiers sont partages par plusieurs lecons d'une meme serie ; les deux
 * cas fonctionnent sans traitement particulier.
 */
function smDossierSons($chemin, $lecon)
{
    $base = dirname($chemin);                       // P3_CE1/grammaire
    foreach ($lecon['blocs'] as $b) {
        if ($b['type'] !== 'pistes') { continue; }
        foreach ($b['pistes'] as $p) {
            $sous = str_replace(chr(92), '/', dirname(trim($p['son'])));
            if ($sous === '.' || $sous === '') { return $base; }
            if (strpos($sous, '..') !== false) { return $base; }
            return $base . '/' . $sous;
        }
    }
    return $base;
}

/** Les sons presents dans le dossier de la lecon mais qu'elle n'utilise pas. */
function smSonsLibres($chemin, $lecon)
{
    $dossier = smDossierSons($chemin, $lecon);
    $absolu = SM_RACINE . '/' . $dossier;
    if (!is_dir($absolu)) { return array(); }
    $utilises = array();
    foreach ($lecon['blocs'] as $b) {
        if ($b['type'] !== 'pistes') { continue; }
        foreach ($b['pistes'] as $p) { $utilises[basename(trim($p['son']))] = true; }
    }
    $out = array();
    foreach ((array)scandir($absolu) as $f) {
        if ($f === '.' || $f === '..' || isset($utilises[$f])) { continue; }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (isset(smFormatsAudio()[$ext]) && is_file($absolu . '/' . $f)) { $out[] = $f; }
    }
    sort($out);
    return $out;
}

/**
 * Le chemin d'un son, tel qu'il doit etre ecrit dans les donnees : relatif au
 * dossier de la lecon, comme l'ecrivait l’enseignante a la main.
 */
function smSonRelatif($chemin, $lecon, $nomFichier)
{
    $base = dirname($chemin);
    $dossier = smDossierSons($chemin, $lecon);
    $sous = ltrim(substr($dossier, strlen($base)), '/');
    return $sous === '' ? $nomFichier : $sous . '/' . $nomFichier;
}

/**
 * Un libelle lisible a partir d'un nom de fichier : « exercice3.mp3 » donne
 * « Exercice 3 ». C'est une proposition, l'enseignante la corrige d'un clic.
 */
function smLibelleDepuisNom($nom)
{
    $base = pathinfo($nom, PATHINFO_FILENAME);
    $base = preg_replace('/[_-]+/', ' ', $base);
    $base = preg_replace('/([a-zA-Zà-öø-ÿ])(\d)/u', '$1 $2', $base);
    $base = trim(preg_replace('/\s+/u', ' ', $base));
    if ($base === '') { return $nom; }
    return mb_strtoupper(mb_substr($base, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($base, 1, null, 'UTF-8');
}

/** Les matieres du site, et l'abreviation employee dans les noms de fichiers. */
function smMatieres()
{
    return array(
        'numeration' => array('Nombres et calculs', 'num'),
        'problemes' => array('Problèmes', 'prob'),
        'geometrie' => array('Espace et géométrie', 'geom'),
        'mesures' => array('Grandeurs et mesures', 'mesure'),
        'grammaire' => array('Grammaire', 'gram'),
        'orthographe' => array('Orthographe', 'ortho'),
        'conjugaison' => array('Conjugaison', 'conj'),
        'vocabulaire' => array('Vocabulaire', 'voca'),
        'informatique' => array('Informatique', 'info'),
        'monde' => array('Questionner le monde', 'monde'),
        'lecture' => array('Lecture', 'lecture'),
        'oral' => array('Langage oral', 'oral'),
        'anglais' => array('Anglais', 'anglais'),
        'redaction' => array('Rédaction', 'redaction'),
    );
}

/**
 * Les matieres dans lesquelles on peut creer une lecon, pour une periode.
 *
 * Le critere n'est pas une liste figee : c'est la presence, dans la page de
 * matiere, de l'appel a liste-lecons-fin.php. Treize pages ont une mise en page
 * particuliere et ne l'ont pas ; le jour ou elles l'auront, elles apparaitront
 * ici sans que rien d'autre ne change.
 */
function smMatieresCreables($periode)
{
    $out = array();
    if (!preg_match('/^P[1-5]_' . SM_NIVEAU . '$/', $periode)) { return $out; }
    foreach (smMatieres() as $cle => $info) {
        if (smMatiereCapturee($periode, $cle)) { $out[$cle] = $info[0]; }
    }
    return $out;
}

/** Les periodes existantes, dans l'ordre. */
function smPeriodes()
{
    $out = array();
    foreach (array(SM_NIVEAU) as $niveau) {
        for ($i = 1; $i <= 5; $i++) {
            $p = 'P' . $i . '_' . $niveau;
            if (is_dir(SM_RACINE . '/' . $p)) { $out[$p] = 'Période ' . $i . ' — ' . $niveau; }
        }
    }
    return $out;
}

/** Un morceau de nom de fichier a partir d'un titre : « L'infinitif » -> « linfinitif ». */
function smAbrege($titre)
{
    $s = strtr($titre, array(
        'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n','ý'=>'y','ÿ'=>'y',
        'æ'=>'ae','œ'=>'oe','ß'=>'ss',
    ));
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return substr($s, 0, 44);
}

/**
 * Un texte propre, sur lequel json_encode ne calera pas.
 *
 * Un navigateur envoie de l'UTF-8 valide, le formulaire etant en UTF-8. Mais
 * une requete mal formee suffirait a faire echouer l'enregistrement au tout
 * dernier moment, apres que la version precedente a ete archivee. On remplace
 * donc les sequences invalides plutot que de refuser, et on retire les
 * caracteres de commande qui n'ont rien a faire dans un libelle.
 */
function smTexte($v)
{
    if (!is_string($v)) {
        return '';
    }
    if (!mb_check_encoding($v, 'UTF-8')) {
        $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
    }
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return trim($v);
}

require(SM_RACINE . '/inclusions/liste-lecons-lecture.php');

/** Le fichier de reglages de la liste d'une matiere. */
function smListeFichier($periode, $matiere)
{
    if (!preg_match('/^P[1-5]_' . SM_NIVEAU . '$/', $periode)) { return null; }
    if (!preg_match('/^[a-z]+$/', $matiere)) { return null; }
    return SM_LECONS . '/_listes/' . $periode . '_' . $matiere . '.json';
}

/** Les reglages de la liste d'une matiere, avec leurs valeurs par defaut. */
function smListeReglages($periode, $matiere)
{
    $vide = array('ordre' => array(), 'libelles' => array(),
                  'masquees' => array(), 'ajoutees' => array(), 'supprimees' => array());
    $f = smListeFichier($periode, $matiere);
    if ($f === null || !is_file($f)) { return $vide; }
    $d = json_decode(file_get_contents($f), true);
    if (!is_array($d)) { return $vide; }
    foreach ($vide as $cle => $_) {
        if (!isset($d[$cle]) || !is_array($d[$cle])) { $d[$cle] = array(); }
    }
    return $d;
}

/** Ecrit les reglages, en archivant la version precedente. */
function smListeEcrire($periode, $matiere, $reglages)
{
    $f = smListeFichier($periode, $matiere);
    if ($f === null) { return false; }
    $dossier = dirname($f);
    if (!is_dir($dossier) && !@mkdir($dossier, 0775, true)) { return false; }
    if (is_file($f)) {
        smArchiver($f, '_liste__' . $periode . '_' . $matiere);
    }
    $contenu = json_encode($reglages,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return $contenu !== false && @file_put_contents($f, $contenu, LOCK_EX) !== false;
}

/**
 * La liste des lecons d'une matiere TELLE QU'ELLE SERA VUE par les eleves.
 *
 * On relit la page de matiere, on y repere les liens comme le fait la page
 * elle-meme, puis on applique les reglages. L'editeur montre ainsi exactement
 * ce que la page rendra, et non une reconstitution qui pourrait en differer.
 */
function smLiensMatiere($periode, $matiere)
{
    $page = SM_RACINE . '/' . $periode . '/' . $periode . '_' . $matiere . '.php';
    if (!is_file($page)) { return array(); }
    $suites = smLlAnalyser(file_get_contents($page), $matiere);
    $reglages = smListeReglages($periode, $matiere);
    $ordre = array_flip($reglages['ordre']);
    $masquees = array_flip($reglages['masquees']);
    $supprimees = array_flip($reglages['supprimees']);

    $out = array();
    foreach ($suites as $s => $suite) {
        $lignes = array();
        foreach ($suite as $rang => $it) {
            if (isset($supprimees[$it['fichier']])) { continue; }
            $lignes[] = array(
                'fichier' => $it['fichier'],
                'libelle' => isset($reglages['libelles'][$it['fichier']])
                           ? $reglages['libelles'][$it['fichier']]
                           : trim(html_entity_decode(strip_tags($it['libelle']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'origine' => trim(html_entity_decode(strip_tags($it['libelle']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'masquee' => isset($masquees[$it['fichier']]),
                'ajoutee' => false,
                'rang' => isset($ordre[$it['fichier']]) ? $ordre[$it['fichier']] : 100000 + $rang,
            );
        }
        if ($s === count($suites) - 1) {
            $rang = count($suite);
            foreach ($reglages['ajoutees'] as $a) {
                if (!isset($a['fichier'], $a['libelle'])) { continue; }
                $lignes[] = array(
                    'fichier' => $a['fichier'],
                    'libelle' => isset($reglages['libelles'][$a['fichier']])
                               ? $reglages['libelles'][$a['fichier']] : $a['libelle'],
                    'origine' => $a['libelle'],
                    'masquee' => isset($masquees[$a['fichier']]),
                    'ajoutee' => true,
                    'rang' => isset($ordre[$a['fichier']]) ? $ordre[$a['fichier']] : 100000 + $rang++,
                );
            }
        }
        usort($lignes, function ($x, $y) { return $x['rang'] <=> $y['rang']; });
        $out[] = $lignes;
    }
    return $out;
}

/**
 * Verifie le chemin d'une lecon qui n'a pas forcement de donnees : les pages
 * ecrites avant l'editeur (poesies, jeux...) n'en ont pas, et doivent pouvoir
 * etre supprimees quand meme. Renvoie le chemin absolu de la page, ou null.
 */
function smCheminLecon($chemin)
{
    if (!is_string($chemin) || strpos($chemin, '..') !== false || strpos($chemin, "\0") !== false
            || !preg_match('#^P[1-5]_' . SM_NIVEAU . '/[a-z]+/[A-Za-z0-9._-]+\.php$#', $chemin)) {
        return null;
    }
    $php = SM_RACINE . '/' . $chemin;
    if (!is_file($php) && smFichierDonnees($chemin) === null) { return null; }
    $reel = realpath(dirname($php));
    $racine = realpath(SM_RACINE);
    if ($reel === false || $racine === false || strpos($reel, $racine . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $php;
}

/** Cette lecon a-t-elle ete creee depuis l'editeur ? */
function smLeconAjoutee($chemin)
{
    list($periode, $matiere, $nom) = explode('/', $chemin);
    foreach (smListeReglages($periode, $matiere)['ajoutees'] as $a) {
        if (isset($a['fichier']) && $a['fichier'] === $nom) { return true; }
    }
    return false;
}

/** Met un fichier de cote dans .versions avant de l'ecraser ou de l'effacer. */
function smArchiver($fichier, $etiquette)
{
    if (!is_file($fichier)) { return; }
    if (!is_dir(SM_VERSIONS) && !@mkdir(SM_VERSIONS, 0775, true)) { return; }
    // La seconde ne suffit pas toujours : restaurer une version enregistre
    // aussitot apres, et deux archives se retrouveraient sous le meme nom.
    $base = SM_VERSIONS . '/' . $etiquette . '-' . date('Ymd-His');
    $cible = $base . '.json';
    for ($i = 2; $i < 60 && file_exists($cible); $i++) {
        $cible = $base . '-' . $i . '.json';
    }
    @copy($fichier, $cible);
}

/** Les versions archivees d'une lecon, de la plus recente a la plus ancienne. */
function smVersions($chemin)
{
    $etiquette = str_replace('/', '__', substr($chemin, 0, -4));
    $out = array();
    foreach ((array)glob(SM_VERSIONS . '/' . $etiquette . '-*.json') as $f) {
        if (!preg_match('/-(\d{8})-(\d{6})(?:-\d+)?\.json$/', $f, $m)) { continue; }
        $out[] = array(
            'fichier' => basename($f),
            'quand' => mktime((int)substr($m[2],0,2), (int)substr($m[2],2,2), (int)substr($m[2],4,2),
                              (int)substr($m[1],4,2), (int)substr($m[1],6,2), (int)substr($m[1],0,4)),
            'taille' => filesize($f),
        );
    }
    usort($out, function ($a, $b) { return $b['quand'] <=> $a['quand']; });
    return $out;
}

/** Le formulaire renvoie des tableaux paralleles : on les recolle. */
function smTableau($nom)
{
    return isset($_POST[$nom]) && is_array($_POST[$nom]) ? array_values($_POST[$nom]) : array();
}

/**
 * Les medias d'une lecon dont AUCUNE autre lecon ne se sert.
 *
 * Sert a la suppression : ce qui est partage reste, ce qui ne l'est pas s'en
 * va avec la lecon. Sans cela, supprimer laisserait derriere elle un dossier
 * de sons que plus rien ne reference, et que la page de menage ne verrait meme
 * pas puisqu'elle ne parcourt que les dossiers des lecons existantes.
 */
function smMediasExclusifs($chemin, $lecon)
{
    $dossierSons = smDossierSons($chemin, $lecon);
    $pdf = '';
    foreach ($lecon['blocs'] as $b) {
        if ($b['type'] === 'pdf') { $pdf = trim($b['fichier']); }
    }
    // Ce dont les AUTRES lecons se servent.
    $sonsAilleurs = false;
    $pdfAilleurs = false;
    foreach (smListeLecons() as $l) {
        if ($l['chemin'] === $chemin) { continue; }
        $d = json_decode((string)@file_get_contents(SM_LECONS . '/' . substr($l['chemin'], 0, -4) . '.json'), true);
        if (!is_array($d)) { continue; }
        if (smDossierSons($l['chemin'], $d) === $dossierSons) { $sonsAilleurs = true; }
        foreach ($d['blocs'] as $b) {
            if ($b['type'] === 'pdf' && trim($b['fichier']) === $pdf) { $pdfAilleurs = true; }
        }
    }
    return array(
        'dossier_sons' => $sonsAilleurs ? null : $dossierSons,
        'pdf' => ($pdf !== '' && !$pdfAilleurs) ? $pdf : null,
    );
}

/** Efface un dossier de sons et ce qu'il contient. Ne descend pas plus bas. */
function smEffacerDossierSons($dossier)
{
    $absolu = SM_RACINE . '/' . $dossier;
    $reel = realpath($absolu);
    $racine = realpath(SM_RACINE);
    if ($reel === false || $racine === false
            || strpos($reel, $racine . DIRECTORY_SEPARATOR) !== 0
            || !is_dir($absolu)) {
        return 0;
    }
    // Un dossier de matiere ne doit jamais partir : on ne supprime qu'un
    // dossier de sons, donc a trois niveaux au moins sous la racine.
    if (substr_count(trim($dossier, '/'), '/') < 2) {
        return 0;
    }
    $n = 0;
    foreach ((array)scandir($absolu) as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!isset(smFormatsAudio()[$ext]) && $f !== '.htaccess') {
            return $n;   // un fichier inattendu : on n'insiste pas
        }
        // On ne compte que les sons : le .htaccess de protection part aussi,
        // mais l'annoncer comme un fichier son serait faux.
        if (@unlink($absolu . '/' . $f) && $f !== '.htaccess') { $n++; }
    }
    @rmdir($absolu);
    return $n;
}

/**
 * La page de matiere applique-t-elle les reglages de l'editeur ?
 *
 * Sans l'appel a liste-lecons-fin.php, tout ce qu'on enregistrerait ici serait
 * ignore par la page : du travail perdu qui ne previent pas. On verifie donc
 * avant de proposer quoi que ce soit.
 */
function smMatiereCapturee($periode, $matiere)
{
    if (!preg_match('/^P[1-5]_CE[12]$/', $periode) || !preg_match('/^[a-z]+$/', $matiere)) {
        return false;
    }
    $page = SM_RACINE . '/' . $periode . '/' . $periode . '_' . $matiere . '.php';
    if (!is_file($page)) { return false; }
    $t = file_get_contents($page);
    if ($t === false) { return false; }
    if (strpos($t, 'liste-lecons-fin.php') !== false) { return true; }
    // Neuf pages ne font que deleguer au modele « en construction » : c'est lui
    // qui porte la capture. On suit donc l'inclusion, sur un niveau.
    if (strpos($t, 'page-en-construction.php') !== false) {
        $modele = SM_RACINE . '/page-en-construction.php';
        $m = is_file($modele) ? file_get_contents($modele) : false;
        return $m !== false && strpos($m, 'liste-lecons-fin.php') !== false;
    }
    return false;
}

function smEch($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Echappement pour du texte place DANS un element, ou les guillemets et les
 * apostrophes n'ont pas besoin d'etre transformes. Sans cela, un titre
 * enregistre par l'editeur s'ecrirait « L&#039;infinitif » la ou les donnees
 * importees gardent « L'infinitif » : meme rendu a l'ecran, mais deux
 * ecritures pour la meme chose dans les fichiers.
 */
function smEchTexte($v)
{
    return htmlspecialchars((string)$v, ENT_NOQUOTES, 'UTF-8');
}

/**
 * L'identite de la connectee, a droite du bandeau de l'editeur.
 *
 * Elle etait ecrite en dur dans index.php et n'affichait que l'identifiant de
 * connexion - « l’enseignante » -, dans une casse et une graisse a elle, alors que
 * le reste du site montre « l’enseignante nom d’exemple ». Les six autres pages de
 * l'editeur ne l'affichaient pas du tout.
 *
 * Le nom vient du portail, qui pose prenom et nom a la connexion par le SSO.
 * La connexion directe par identification-enseignant.php, elle, ne pose que
 * l'identifiant : d'ou le repli, qui evite un bandeau sans nom.
 */
function smIdentiteBandeau()
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/v2/galaxie-role.php';
    return gxMenuIdentite('School Monsters');
}
