<?php
// Comment on lit la liste des lecons d'une page de matiere.
//
// Ce fichier est partage entre la page elle-meme (liste-lecons-fin.php) et
// l'editeur. Ils DOIVENT voir la meme chose : si l'editeur presentait un ordre
// et que la page en rendait un autre, l'enseignante ne pourrait plus se fier a
// ce qu'elle voit. D'ou une seule fonction, ici, plutot que deux expressions
// regulieres jumelles qui divergeraient au premier ajustement.

// Ce fichier n'est pas une page : il ne contient qu'une fonction.
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(404);
    exit();
}

if (!function_exists('smLlAnalyser')) {

/**
 * Repere les liens de lecon dans le HTML d'une page de matiere, et les
 * regroupe en suites contigues.
 *
 * Une suite est un paquet de liens que seul du blanc separe. Un titre de
 * groupe, une carte illustree ou un lien de jeu ouvre une nouvelle suite :
 * c'est ce qui garantit qu'un reordonnancement ne fera jamais passer une
 * lecon d'un groupe a un autre.
 *
 * Renvoie un tableau de suites, chaque suite etant un tableau d'elements
 * array(debut, fin, indent, fichier, attrs, libelle).
 */
function smLlAnalyser($html, $matiere)
{
    $motif = '#(?P<avant>[ \t]*)<p>\s*<a\s+href="'
           . preg_quote($matiere, '#') . '/(?P<fichier>[A-Za-z0-9._-]+\.php)"'
           . '(?P<attrs>[^>]*)>(?P<libelle>.*?)</a>\s*</p>#s';
    if (!preg_match_all($motif, $html, $trouves, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return array();
    }
    $items = array();
    foreach ($trouves as $t) {
        $items[] = array(
            'debut' => $t['avant'][1],
            'fin' => $t[0][1] + strlen($t[0][0]),
            'indent' => $t['avant'][0],
            'fichier' => $t['fichier'][0],
            'attrs' => $t['attrs'][0],
            'libelle' => $t['libelle'][0],
        );
    }
    $suites = array();
    $suite = array($items[0]);
    for ($i = 1; $i < count($items); $i++) {
        $entre = substr($html, $items[$i - 1]['fin'], $items[$i]['debut'] - $items[$i - 1]['fin']);
        if (trim($entre) === '') {
            $suite[] = $items[$i];
        } else {
            $suites[] = $suite;
            $suite = array($items[$i]);
        }
    }
    $suites[] = $suite;
    return $suites;
}

}
