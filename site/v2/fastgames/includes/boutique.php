<?php
/**
 * « LA PETITE BOUTIQUE » : composer une somme avec des pieces et des billets.
 *
 * DEUXIEME JEU DU SITE OU LA REPONSE SE CONSTRUIT au lieu de se choisir, apres
 * le compte est bon. Le geste est le glisser-deposer d'une piece vers le
 * comptoir - un vrai geste du monde reel (poser de l'argent pour payer), pas
 * une decoration posee sur un QCM. C'est la regle de conception retenue le
 * 01/09/2026 : le glissement doit representer une action reelle ou une
 * construction, jamais habiller une reponse a choix.
 *
 * CE QUE CA DEBLOQUE. Trois competences du programme etaient marquees « ne se
 * joue pas » faute de pouvoir montrer des pieces :
 *   3975  Comparer les valeurs en euro de deux ensembles de pieces et billets.
 *   3976  Determiner la valeur d'un ensemble de pieces et de billets.
 *   3977  Constituer avec des euros et des centimes une somme donnee.
 * Le programme de cycle 2 demande explicitement de constituer des sommes et de
 * simuler des achats : c'est la manipulation qui est visee, pas le calcul seul.
 *
 * L'ADAPTATION CE1 :
 * - TOUT EST EN CENTIMES a l'interieur, jamais en euros decimaux : 12,50 €
 *   vaut 1250. Un prix en virgule flottante finit toujours par produire un
 *   12,499999 qui ne tombe jamais juste sur la cible.
 * - Les coupures sont celles qui existent vraiment, mais reduites a celles
 *   qu'un CE1 manipule : 10c, 20c, 50c, 1 €, 2 €, 5 €, 10 €, 20 €. Les 1c, 2c
 *   et 5c sont ecartes - ils allongent les compositions sans rien apprendre de
 *   plus a ce niveau.
 * - La somme demandee est TOUJOURS composable avec le porte-monnaie affiche :
 *   la solution est construite d'abord, le porte-monnaie ensuite autour d'elle.
 * - Trois facons de jouer, qui montent avec le niveau : payer exactement,
 *   payer avec un nombre de pieces limite, rendre la monnaie.
 *
 * TOUT EST DETERMINISTE, comme le reste du site : meme graine, meme manche, ce
 * qui permet au serveur de refabriquer la partie pour corriger sans l'avoir
 * stockee.
 */

require_once __DIR__ . '/generateurs.php';

/** Nombre de manches d'une partie. */
const FG_MANCHES_BOUTIQUE = 5;

/** Les coupures manipulables, en CENTIMES, de la plus grande a la plus petite. */
const FG_BOUTIQUE_COUPURES = array(2000, 1000, 500, 200, 100, 50, 20, 10);

/** Celles qu'on s'autorise tant que les centimes ne sont pas de la partie. */
const FG_BOUTIQUE_COUPURES_EUROS = array(2000, 1000, 500, 200, 100);

const FG_BOUTIQUE_NIVEAU_MIN = 1;
const FG_BOUTIQUE_NIVEAU_MAX = 5;

/**
 * Les articles de la boutique. Volontairement des objets du quotidien d'un
 * enfant, pas des produits abstraits : on doit pouvoir se representer la scene.
 * Le prix n'est pas ici - il est tire par la manche, pour que le meme article
 * puisse couter des sommes differentes d'une partie a l'autre.
 */
function fgBoutiqueArticles(): array
{
    return array(
        array('un ballon', '⚽'),
        array('un livre', '📗'),
        array('une trousse', '✏️'),
        array('un cahier', '📓'),
        array('une peluche', '🧸'),
        array('un jeu de cartes', '🃏'),
        array('une gourde', '🧃'),
        array('un cerf-volant', '🪁'),
        array('une boite de feutres', '🖍️'),
        array('un puzzle', '🧩'),
    );
}

/**
 * Ce qui change avec le niveau : la facon de jouer, le plafond du prix, et si
 * les centimes entrent dans la danse.
 *
 * La progression suit celle du programme : d'abord constituer une somme en
 * euros entiers, puis avec des centimes, puis sous contrainte (le moins de
 * pieces possible oblige a chercher la grosse coupure d'abord), et enfin
 * rendre la monnaie - qui demande de calculer un ecart avant de le composer.
 */
function fgBoutiqueNiveau(int $niveau): array
{
    $niveau = max(FG_BOUTIQUE_NIVEAU_MIN, min(FG_BOUTIQUE_NIVEAU_MAX, $niveau));
    $paliers = array(
        1 => array('mode' => 'payer',  'plafond' => 1000, 'centimes' => false, 'limite' => false),
        2 => array('mode' => 'payer',  'plafond' => 2500, 'centimes' => false, 'limite' => false),
        3 => array('mode' => 'payer',  'plafond' => 2000, 'centimes' => true,  'limite' => false),
        4 => array('mode' => 'payer',  'plafond' => 2500, 'centimes' => true,  'limite' => true),
        5 => array('mode' => 'rendre', 'plafond' => 2000, 'centimes' => true,  'limite' => false),
    );
    return $paliers[$niveau];
}

/** Le niveau de depart de la prochaine partie, garde en session. */
function fgBoutiqueNiveauDepart(): int
{
    // Meme raison que fgCompteNiveauDepart() : le palier se reconstruit depuis
    // les parties enregistrees au premier appel de la session (FG-AUDIT-002).
    if (!isset($_SESSION['fastgames_boutique_niveau'])) {
        require_once __DIR__ . '/helpers.php';
        $_SESSION['fastgames_boutique_niveau'] = fgNiveauDepartDurable(
            'boutique', FG_BOUTIQUE_NIVEAU_MIN, FG_BOUTIQUE_NIVEAU_MAX);
    }
    $niveau = (int)$_SESSION['fastgames_boutique_niveau'];
    return max(FG_BOUTIQUE_NIVEAU_MIN, min(FG_BOUTIQUE_NIVEAU_MAX, $niveau));
}

/** Meme regle que le compte est bon : 80 % fait monter, 40 % fait descendre. */
function fgBoutiqueAjusterNiveau(int $score, int $total): void
{
    if ($total <= 0) {
        return;
    }
    $niveau = fgBoutiqueNiveauDepart();
    $taux = $score / $total;
    if ($taux >= 0.8) {
        $niveau++;
    } elseif ($taux <= 0.4) {
        $niveau--;
    }
    $_SESSION['fastgames_boutique_niveau'] = max(FG_BOUTIQUE_NIVEAU_MIN, min(FG_BOUTIQUE_NIVEAU_MAX, $niveau));
}

/** Ecrit une somme en centimes comme on l'ecrit sur une etiquette de prix. */
function fgBoutiqueEuros(int $centimes): string
{
    if ($centimes % 100 === 0) {
        return ($centimes / 100) . ' €';
    }
    return number_format($centimes / 100, 2, ',', ' ') . ' €';
}

/**
 * Decompose une somme en pieces, en prenant toujours la plus grosse coupure
 * possible - c'est la facon la plus courte, celle qu'on veut comme solution de
 * reference et comme reponse attendue quand le nombre de pieces est limite.
 */
function fgBoutiqueDecomposer(int $somme, array $coupures): array
{
    $pieces = array();
    foreach ($coupures as $coupure) {
        while ($somme >= $coupure) {
            $pieces[] = $coupure;
            $somme -= $coupure;
        }
    }
    return $somme === 0 ? $pieces : array();
}

/**
 * Fabrique une manche.
 *
 * CONSTRUCTION DANS CET ORDRE, et il compte : le prix d'abord, la solution
 * ensuite, le porte-monnaie en dernier AUTOUR de la solution. Ainsi la somme
 * demandee est toujours composable avec ce qui est affiche - jamais une manche
 * impossible, jamais de verification a posteriori.
 */
function fgBoutiqueManche(string $graine, int $manche, int $niveauDepart = FG_BOUTIQUE_NIVEAU_MIN): array
{
    $base = $graine . '|boutique|' . $manche;
    $palier = fgBoutiqueNiveau($niveauDepart + $manche);
    $coupures = $palier['centimes'] ? FG_BOUTIQUE_COUPURES : FG_BOUTIQUE_COUPURES_EUROS;

    $articles = fgBoutiqueArticles();
    $article = $articles[fgEntierStable($base . '|article', 0, count($articles) - 1)];

    // Le prix : un multiple de la plus petite coupure autorisee, pour rester
    // toujours composable. En euros entiers, c'est un multiple de 100.
    $pas = $palier['centimes'] ? 10 : 100;
    $minimum = $palier['centimes'] ? 150 : 200;
    $prix = fgEntierStable($base . '|prix', intdiv($minimum, $pas), intdiv($palier['plafond'], $pas)) * $pas;

    $mode = $palier['mode'];
    $donne = null;
    $cible = $prix;
    if ($mode === 'rendre') {
        // Le client paie avec une coupure ronde superieure au prix, et l'eleve
        // compose la difference. On exige au moins 1 € d'ecart : sinon le
        // client donne 5 € pour un article a 4,50 € et il n'y a qu'une seule
        // piece a rendre - ce n'est plus un exercice. Trouve au premier test
        // du circuit complet, avant toute mise en ligne.
        foreach (array(500, 1000, 2000, 5000) as $billet) {
            if ($billet - $prix >= 100) {
                $donne = $billet;
                break;
            }
        }
        if ($donne === null) {
            $donne = $prix + 500;
        }
        $cible = $donne - $prix;
    }

    $solution = fgBoutiqueDecomposer($cible, $coupures);
    if (!$solution) {
        // Ne devrait pas arriver : le prix est un multiple de la plus petite
        // coupure. Repli defensif plutot qu'une manche impossible.
        $cible = $pas * 3;
        $solution = fgBoutiqueDecomposer($cible, $coupures);
        $prix = $mode === 'rendre' ? $prix : $cible;
    }

    // Le porte-monnaie : la solution, plus quelques pieces qui ne servent pas.
    // Elles rendent le choix reel - sans elles, il suffirait de tout glisser.
    $porteMonnaie = $solution;
    $nLeurres = fgEntierStable($base . '|nleurres', 2, 4);
    for ($i = 0; $i < $nLeurres; $i++) {
        $porteMonnaie[] = $coupures[fgEntierStable($base . '|leurre' . $i, 0, count($coupures) - 1)];
    }
    fgMelangerStable($porteMonnaie, $base . '|ordre');

    // La contrainte de nombre de pieces vaut exactement la longueur de la
    // decomposition la plus courte : elle oblige a chercher les grosses
    // coupures d'abord, sans jamais rendre la manche impossible.
    $limite = $palier['limite'] ? count($solution) : null;

    return array(
        'article' => $article[0],
        'embleme' => $article[1],
        'prix' => $prix,
        'mode' => $mode,
        'donne' => $donne,
        'cible' => $cible,
        'porte_monnaie' => $porteMonnaie,
        'limite' => $limite,
        'solution' => $solution,
    );
}

/** La consigne affichee, qui change avec la facon de jouer. */
function fgBoutiqueConsigne(array $manche): string
{
    if ($manche['mode'] === 'rendre') {
        return 'Le client paie ' . fgBoutiqueEuros($manche['prix']) . ' avec '
             . fgBoutiqueEuros($manche['donne']) . '. Glisse la monnaie à lui rendre.';
    }
    if ($manche['limite'] !== null) {
        return 'Glisse de quoi payer exactement, avec ' . $manche['limite']
             . ' pièce' . ($manche['limite'] > 1 ? 's' : '') . ' au maximum.';
    }
    return 'Glisse de quoi payer exactement le prix.';
}

/** L'explication montree apres la reponse : une composition qui marche. */
function fgBoutiqueExplication(array $manche): string
{
    $morceaux = array_map('fgBoutiqueEuros', $manche['solution']);
    return 'Une façon de faire : ' . implode(' + ', $morceaux)
         . ' = ' . fgBoutiqueEuros($manche['cible'])
         . (count($manche['solution']) > 1 ? '. Il pouvait y en avoir d’autres.' : '.');
}

/**
 * Verifie ce que l'eleve a pose sur le comptoir.
 *
 * RIEN N'EST FAIT CONFIANCE. Chaque piece posee doit venir DU PORTE-MONNAIE
 * DE CETTE MANCHE et n'y etre prise qu'une fois - on consomme par position et
 * non par valeur, car un porte-monnaie contient volontairement plusieurs
 * pieces identiques. La somme doit tomber EXACTEMENT sur la cible : ni plus,
 * ni moins. « Presque » n'existe pas quand on paie.
 */
function fgBoutiqueValider(array $manche, array $piecesPosees): array
{
    if (!$piecesPosees) {
        return array('correct' => false, 'total' => 0);
    }

    $disponibles = $manche['porte_monnaie'];
    $prises = array();
    $total = 0;

    foreach ($piecesPosees as $valeur) {
        $valeur = (int)$valeur;
        $index = null;
        foreach ($disponibles as $i => $piece) {
            if (!in_array($i, $prises, true) && $piece === $valeur) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            // Une piece qui n'est pas dans ce porte-monnaie, ou deja posee.
            return array('correct' => false, 'total' => 0);
        }
        $prises[] = $index;
        $total += $valeur;
    }

    if ($manche['limite'] !== null && count($piecesPosees) > $manche['limite']) {
        // Trop de pieces : la somme peut etre juste, la consigne ne l'est pas.
        return array('correct' => false, 'total' => $total);
    }

    return array('correct' => $total === $manche['cible'], 'total' => $total);
}

/** Fabrique la partie complete, meme forme de retour que les autres jeux. */
function fgJeuBoutique(string $graine, int $niveauDepart = FG_BOUTIQUE_NIVEAU_MIN): array
{
    $questions = array();
    for ($m = 0; $m < FG_MANCHES_BOUTIQUE; $m++) {
        $manche = fgBoutiqueManche($graine, $m, $niveauDepart);
        $questions[] = array(
            'consigne' => fgBoutiqueConsigne($manche),
            'article' => $manche['article'],
            'embleme' => $manche['embleme'],
            'prix' => $manche['prix'],
            'prix_affiche' => fgBoutiqueEuros($manche['prix']),
            'mode' => $manche['mode'],
            'donne_affiche' => $manche['donne'] !== null ? fgBoutiqueEuros($manche['donne']) : null,
            'cible' => $manche['cible'],
            'cible_affichee' => fgBoutiqueEuros($manche['cible']),
            'porte_monnaie' => $manche['porte_monnaie'],
            'limite' => $manche['limite'],
            'explication' => fgBoutiqueExplication($manche),
        );
    }

    return array(
        'titre' => 'La petite boutique',
        'description' => 'Compose la somme demandée avec des pièces et des billets.',
        'competence' => 'Monnaie : constituer une somme, rendre la monnaie',
        'duree' => '5 min',
        'ton' => 'corail',
        'mecanique' => 'boutique',
        'questions' => $questions,
    );
}
