<?php
// Ferme la capture ouverte par liste-lecons-debut.php et reemet la liste des
// lecons d'une page de matiere, en appliquant les reglages faits dans
// l'editeur : ordre, nom du lien, lecons masquees, lecons ajoutees.
//
// Pourquoi passer par une capture plutot que par une reecriture des pages :
// ces 58 pages CE1 sont ecrites a la main, et vingt et une d'entre elles
// melangent des liens de lecon avec des titres de groupe, des cartes
// illustrees et des liens de jeu. Les convertir en donnees aurait suppose de
// modeliser tout cela. Ici le HTML ecrit a la main reste la source, il reste
// lisible dans le fichier, et l'editeur ne pose par-dessus que ce qui differe.
//
// Deux garanties tenues par construction :
//
//  - SANS FICHIER DE REGLAGES, le tampon est reemis tel quel. La page rend
//    exactement ce qu'elle rendait avant. C'est verifie a l'octet.
//  - L'ORDRE NE TRAVERSE JAMAIS UN TITRE. Les liens sont regroupes en suites
//    contigues, separees uniquement par du blanc ; le reordonnancement a lieu
//    a l'interieur d'une suite. Une lecon ne peut donc pas sauter d'un groupe
//    « Lire vite et bien » a un groupe « Lecture a voix haute ».
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(404);
    exit();
}

// On ne referme QUE le tampon qu'on a ouvert soi-meme. Sans ce temoin, un
// ob_get_clean() appele sans notre ob_start() viderait le tampon ouvert par la
// configuration du serveur, et la page entiere disparaitrait.
if (empty($GLOBALS['smListeCapture'])) {
    return;
}
$GLOBALS['smListeCapture'] = false;
$smLlTampon = ob_get_clean();
if ($smLlTampon === false) {
    return;
}

$smLlRacine = dirname(__DIR__);
$smLlNom = basename($_SERVER['SCRIPT_FILENAME'], '.php');   // P3_CE1_grammaire

if (!preg_match('/^(P[1-5]_CE[12])_([a-z]+)$/', $smLlNom, $smLlM)) {
    echo $smLlTampon;
    return;
}
$smLlPeriode = $smLlM[1];
$smLlMatiere = $smLlM[2];
$smLlFichier = $smLlRacine . '/lecons/_listes/' . $smLlPeriode . '_' . $smLlMatiere . '.json';

if (!is_file($smLlFichier)) {
    echo $smLlTampon;
    return;
}
$smLlReglages = json_decode(file_get_contents($smLlFichier), true);
if (!is_array($smLlReglages)) {
    echo $smLlTampon;
    return;
}
$smLlOrdre = isset($smLlReglages['ordre']) && is_array($smLlReglages['ordre'])
           ? array_flip($smLlReglages['ordre']) : array();
$smLlLibelles = isset($smLlReglages['libelles']) && is_array($smLlReglages['libelles'])
              ? $smLlReglages['libelles'] : array();
$smLlMasquees = isset($smLlReglages['masquees']) && is_array($smLlReglages['masquees'])
              ? array_flip($smLlReglages['masquees']) : array();
// Une lecon d'origine supprimee depuis l'editeur n'a plus de page : son lien
// ecrit a la main dans la matiere doit disparaitre, quoi que disent les autres
// reglages.
$smLlSupprimees = isset($smLlReglages['supprimees']) && is_array($smLlReglages['supprimees'])
                ? array_flip($smLlReglages['supprimees']) : array();
$smLlAjoutees = isset($smLlReglages['ajoutees']) && is_array($smLlReglages['ajoutees'])
              ? $smLlReglages['ajoutees'] : array();

require(__DIR__ . '/liste-lecons-lecture.php');
$smLlSuites = smLlAnalyser($smLlTampon, $smLlMatiere);

// Une matiere qui n'a encore aucune lecon n'a evidemment aucun lien a repérer.
// Il faut quand meme poser les lecons creees depuis l'editeur, sinon elles
// n'apparaitraient nulle part : c'est exactement le cas d'une matiere qu'on
// commence a remplir.
if (!$smLlSuites) {
    echo $smLlTampon;
    foreach ($smLlAjoutees as $smLlA) {
        if (!isset($smLlA['fichier'], $smLlA['libelle'])) { continue; }
        if (!preg_match('/^[A-Za-z0-9._-]+\.php$/', $smLlA['fichier'])) { continue; }
        if (isset($smLlMasquees[$smLlA['fichier']])) { continue; }
        if (!is_file($smLlRacine . '/' . $smLlPeriode . '/' . $smLlMatiere . '/' . $smLlA['fichier'])) {
            continue;
        }
        $smLlL = isset($smLlLibelles[$smLlA['fichier']])
               ? $smLlLibelles[$smLlA['fichier']] : $smLlA['libelle'];
        echo '               <p><a href="'
           . htmlspecialchars($smLlMatiere . '/' . $smLlA['fichier'], ENT_QUOTES, 'UTF-8')
           . '">' . htmlspecialchars($smLlL, ENT_NOQUOTES, 'UTF-8') . "</a></p>\n";
    }
    return;
}

/** Le rang voulu d'une lecon ; celles qu'on n'a pas classees restent en place. */
function smLlRang($fichier, $ordre, $defaut)
{
    return isset($ordre[$fichier]) ? $ordre[$fichier] : 100000 + $defaut;
}

function smLlLigne($indent, $matiere, $fichier, $attrs, $libelle)
{
    return $indent . '<p><a href="' . htmlspecialchars($matiere . '/' . $fichier, ENT_QUOTES, 'UTF-8')
         . '"' . $attrs . '>' . $libelle . '</a></p>';
}

// --------------------------------- calculer ce que chaque suite doit rendre
$smLlVoulu = array();
$smLlChange = false;
foreach ($smLlSuites as $s => $suite) {
    $retenus = array();
    foreach ($suite as $rang => $it) {
        if (isset($smLlMasquees[$it['fichier']]) || isset($smLlSupprimees[$it['fichier']])) {
            continue;
        }
        if (isset($smLlLibelles[$it['fichier']])) {
            $it['libelle'] = htmlspecialchars($smLlLibelles[$it['fichier']], ENT_NOQUOTES, 'UTF-8');
        }
        $it['rang'] = smLlRang($it['fichier'], $smLlOrdre, $rang);
        $retenus[] = $it;
    }
    // Les lecons creees depuis l'editeur rejoignent la DERNIERE suite : c'est
    // la fin de la liste de la matiere.
    if ($s === count($smLlSuites) - 1) {
        $rang = count($suite);
        foreach ($smLlAjoutees as $a) {
            if (!isset($a['fichier'], $a['libelle'])) { continue; }
            if (!preg_match('/^[A-Za-z0-9._-]+\.php$/', $a['fichier'])) { continue; }
            if (isset($smLlMasquees[$a['fichier']])) { continue; }
            if (!is_file($smLlRacine . '/' . $smLlPeriode . '/' . $smLlMatiere . '/' . $a['fichier'])) {
                continue;   // la lecon n'existe plus : pas de lien mort
            }
            $libelle = isset($smLlLibelles[$a['fichier']]) ? $smLlLibelles[$a['fichier']] : $a['libelle'];
            $retenus[] = array(
                'indent' => $suite[0]['indent'],
                'fichier' => $a['fichier'],
                'attrs' => '',
                'libelle' => htmlspecialchars($libelle, ENT_NOQUOTES, 'UTF-8'),
                'rang' => smLlRang($a['fichier'], $smLlOrdre, $rang++),
            );
        }
    }
    usort($retenus, function ($x, $y) { return $x['rang'] <=> $y['rang']; });
    $smLlVoulu[$s] = $retenus;

    // Est-ce different de ce que la page dit deja ?
    if (count($retenus) !== count($suite)) {
        $smLlChange = true;
    } else {
        foreach ($retenus as $i => $it) {
            if ($it['fichier'] !== $suite[$i]['fichier'] || $it['libelle'] !== $suite[$i]['libelle']) {
                $smLlChange = true;
                break;
            }
        }
    }
}

// Enregistrer une matiere sans y toucher ne doit RIEN changer, pas meme une
// espace en fin de ligne. Si le resultat voulu est celui que la page ecrit
// deja, on la laisse parler.
if (!$smLlChange) {
    echo $smLlTampon;
    return;
}

// ------------------------------------- reecrire, de la fin vers le debut
// De la fin vers le debut pour que les positions relevees restent valables.
$smLlSortie = $smLlTampon;
for ($s = count($smLlSuites) - 1; $s >= 0; $s--) {
    $suite = $smLlSuites[$s];
    $lignes = array();
    foreach ($smLlVoulu[$s] as $it) {
        $lignes[] = smLlLigne($it['indent'], $smLlMatiere, $it['fichier'], $it['attrs'], $it['libelle']);
    }
    $debut = $suite[0]['debut'];
    $fin = $suite[count($suite) - 1]['fin'];
    $smLlSortie = substr($smLlSortie, 0, $debut)
                . implode("\n", $lignes)
                . substr($smLlSortie, $fin);
}

echo $smLlSortie;
