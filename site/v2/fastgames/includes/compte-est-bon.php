<?php
/**
 * « LE COMPTE EST BON », ADAPTE AU CE1.
 *
 * POURQUOI CE FICHIER EST A PART. Tri et paires se ramenent tous les deux a
 * « pour cet element, quelle est la bonne case ? » - une reponse est un index
 * dans une liste, et toute la machinerie de seance (fgEnregistrerChoix,
 * fgScoreSeance) est construite autour de ce seul format. Le compte est bon
 * n'a pas de liste de propositions : l'eleve COMPOSE son calcul, un nombre et
 * un operateur a la fois. C'est une reponse LIBRE, pas un choix. Plutot que de
 * tordre le format existant pour un cas qui n'y entre pas, ce fichier fournit
 * son propre generateur et sa propre validation ; includes/seance.php les
 * appelle a cote de ceux de tri/paires, sans toucher a leur code.
 *
 * L'ADAPTATION CE1, DECIDEE ICI :
 * - La cible est TOUJOURS exactement atteignable, contrairement au jeu
 *   original qui accepte le nombre le plus proche possible. Un enfant de CE1
 *   n'a pas a juger si 46 est « assez proche » de 47 : soit c'est exact, soit
 *   ce n'est pas encore ca.
 * - Aucun resultat intermediaire negatif. Les nombres negatifs ne sont pas au
 *   programme du cycle 2 ; une soustraction qui passerait sous zero est
 *   refusee a la validation, pas seulement decouragee a l'affichage.
 * - La multiplication ne porte que sur les tables 2, 3, 4, 5 et 10 - les
 *   memes que « Defi des tables » dans includes/generateurs.php - ET
 *   seulement quand le nombre a multiplier est deja petit (10 au plus) : on
 *   ne demande jamais un calcul hors table, comme 23 × 4.
 * - La division n'existe que quand elle tombe juste, jamais avec un reste :
 *   le CE1 ne connait pas la division euclidienne. Le diviseur n'est que 2 ou
 *   5 - plus restreint que les tables de la multiplication, demande de
 *   le responsable technique le 01/09/2026 : moitie et cinquieme sont les seuls partages surs a
 *   ce niveau.
 * - Les nombres d'une meme manche sont toujours DISTINCTS, pour qu'aucune
 *   tuile ne soit ambigue a l'ecran.
 * - Un × ou un ÷ ne vient JAMAIS apres un + ou un −, dans la solution comme
 *   dans ce que la validation accepte. Un CE1 ne connait ni les parentheses
 *   ni les priorites operatoires : le jeu calcule toujours de gauche a
 *   droite, mais si on ecrivait un calcul comme « 29 − 3 ÷ 2 » a plat, il se
 *   lirait, pour qui connait ces priorites, comme 29 − (3 ÷ 2) = 27,5 et non
 *   13. En imposant que la multiplication et la division arrivent toujours
 *   AVANT l'addition et la soustraction, un calcul ecrit a plat, sans aucune
 *   parenthese, se lit exactement pareil dans les deux sens - celui du jeu et
 *   celui des vraies regles de calcul. Trouve par le responsable technique le 01/09/2026 sur
 *   une vraie capture d'ecran ; explore d'abord une piste a base de
 *   parentheses, ecartee parce qu'un CE1 ne les a pas encore apprises, et une
 *   piste de composition libre par glisser-depose, ecartee pour l'instant
 *   comme un chantier trop lourd pour ce qu'il resolvait ici.
 *
 * EVOLUTIF EN DEUX SENS, demande de le responsable technique le 01/09/2026 :
 * - DANS une partie : chacune des cinq manches est un peu plus dure que la
 *   precedente (voir fgCompteNiveau) - plus de nombres, puis + et - deviennent
 *   +, -, × avant d'accueillir enfin ÷.
 * - D'UNE partie a l'autre : le niveau de depart suit la reussite recente,
 *   garde en session (fgCompteNiveauDepart / fgCompteAjusterNiveau). Une
 *   partie tres reussie commence un cran plus haut la prochaine fois ; une
 *   partie difficile revient un cran plus bas. Rien n'est stocke en base : la
 *   session suffit, et repart a zero comme le reste du site quand elle expire.
 *
 * TOUT EST DETERMINISTE, comme le reste du site : fgEntierStable et
 * fgMelangerStable reproduisent exactement la meme manche a partir de la
 * meme graine, donc le serveur peut la refabriquer pour corriger sans jamais
 * l'avoir stockee.
 */

require_once __DIR__ . '/generateurs.php';

/** Nombre de manches d'une partie. */
const FG_MANCHES_COMPTE = 5;

/** Plafond impose a la cible et aux nombres, pour rester dans le calcul CE1. */
const FG_COMPTE_MAX = 60;

/** En dessous de ce total, la cible est jugee trop derisoire (voir plus bas). */
const FG_COMPTE_CIBLE_MINIMALE = 8;

/** Les tables de multiplication couvertes, les memes que « Defi des tables ». */
const FG_COMPTE_TABLES = array(2, 3, 4, 5, 10);

// Diviseurs plus restreints que les tables de multiplication - demande de
// le responsable technique le 01/09/2026 : moitie et cinquieme sont les deux seuls partages
// vraiment surs a ce niveau, la aussi meme sans tomber sur un reste.
const FG_COMPTE_DIVISEURS = array(2, 5);

const FG_COMPTE_NIVEAU_MIN = 1;
const FG_COMPTE_NIVEAU_MAX = 5;

/**
 * Ce qui change avec le niveau : combien de nombres composent la solution de
 * reference, et quels operateurs sont autorises pour les choisir.
 *
 * Le passage de 2 a 3 nombres se fait AVANT l'arrivee de la multiplication
 * (niveau 3 revient a deux nombres) : une manche qui decouvre × n'a pas en
 * plus a jongler avec un troisieme nombre le meme jour.
 */
function fgCompteNiveau(int $niveau): array
{
    $niveau = max(FG_COMPTE_NIVEAU_MIN, min(FG_COMPTE_NIVEAU_MAX, $niveau));
    $paliers = array(
        1 => array('nUtiles' => 2, 'operateurs' => array('+', '-')),
        2 => array('nUtiles' => 3, 'operateurs' => array('+', '-')),
        3 => array('nUtiles' => 2, 'operateurs' => array('+', '-', '×')),
        4 => array('nUtiles' => 3, 'operateurs' => array('+', '-', '×', '÷')),
        5 => array('nUtiles' => 4, 'operateurs' => array('+', '-', '×', '÷')),
    );
    return $paliers[$niveau];
}

/**
 * Le niveau de depart de la PROCHAINE partie, garde en session. Chaque manche
 * grimpe encore d'un cran par rapport a ce depart (voir fgCompteEstBonManche),
 * donc relever ce depart pousse toute la partie vers plus difficile.
 */
function fgCompteNiveauDepart(): int
{
    // PREMIER APPEL D'UNE SESSION : le palier est reconstruit depuis les
    // parties deja enregistrees, au lieu de repartir du niveau 1 comme avant
    // le 10/09/2026 (FG-AUDIT-002). Ensuite il vit en session, ou
    // fgCompteAjusterNiveau() le fait bouger partie apres partie.
    if (!isset($_SESSION['fastgames_ceb_niveau'])) {
        require_once __DIR__ . '/helpers.php';
        $_SESSION['fastgames_ceb_niveau'] = fgNiveauDepartDurable(
            'compte-est-bon', FG_COMPTE_NIVEAU_MIN, FG_COMPTE_NIVEAU_MAX);
    }
    $niveau = (int)$_SESSION['fastgames_ceb_niveau'];
    return max(FG_COMPTE_NIVEAU_MIN, min(FG_COMPTE_NIVEAU_MAX, $niveau));
}

/**
 * A appeler quand une partie de compte est bon se termine, avec son score.
 * Une partie tres reussie (80 % ou plus) fait monter le niveau de depart d'un
 * cran ; une partie difficile (40 % ou moins) le fait redescendre. Entre les
 * deux, rien ne bouge : il ne faut pas qu'un seul faux pas fasse repartir
 * l'eleve tout en bas.
 */
function fgCompteAjusterNiveau(int $score, int $total): void
{
    if ($total <= 0) {
        return;
    }
    $niveau = fgCompteNiveauDepart();
    $taux = $score / $total;
    if ($taux >= 0.8) {
        $niveau++;
    } elseif ($taux <= 0.4) {
        $niveau--;
    }
    $_SESSION['fastgames_ceb_niveau'] = max(FG_COMPTE_NIVEAU_MIN, min(FG_COMPTE_NIVEAU_MAX, $niveau));
}

/**
 * Fabrique une manche : les nombres a afficher, la cible, et la solution de
 * reference qui a servi a la construire (utile pour l'explication).
 *
 * CONSTRUCTION EN AVANT, PAS EN ARRIERE. Plutot que de partir d'une cible et
 * de chercher quels nombres y menent - ce qui peut echouer et demande une
 * recherche - on part d'un premier nombre et on ajoute des operations une a
 * une, en choisissant CHAQUE fois une operation qui reste dans les clous
 * (jamais negative, jamais hors table, jamais une division avec un reste). La
 * cible est ce qu'on obtient a la fin : elle est donc SYSTEMATIQUEMENT
 * atteignable, par construction, jamais par verification a posteriori.
 */
function fgCompteEstBonManche(string $graine, int $manche, int $niveauDepart = FG_COMPTE_NIVEAU_MIN): array
{
    // Sans ce filtre, une manche sur cinq environ tombait sur une cible
    // derisoire - « trouve 1 », « trouve 3 » - mesure sur 15 000 manches le
    // 01/09/2026. Rien de faux mathematiquement, mais un jeu qui s'appelle
    // « le compte est bon » perd son sel si le compte est deja presque bon
    // au premier nombre pose. On retente avec une graine deplacee jusqu'a
    // obtenir une cible d'au moins FG_COMPTE_CIBLE_MINIMALE ; 8 tentatives
    // suffisent toujours en pratique, et la derniere est acceptee telle
    // quelle pour garantir que la fonction rend toujours quelque chose.
    $derniere = 8 - 1;
    for ($tentative = 0; $tentative <= $derniere; $tentative++) {
        $essai = fgCompteEstBonMancheBrute($graine . '|essai' . $tentative, $manche, $niveauDepart);
        if ($essai['cible'] >= FG_COMPTE_CIBLE_MINIMALE || $tentative === $derniere) {
            return $essai;
        }
    }
}

function fgCompteEstBonMancheBrute(string $graine, int $manche, int $niveauDepart): array
{
    $base = $graine . '|ceb|' . $manche;

    // La difficulte grimpe manche apres manche AU-DESSUS du niveau de depart
    // de la partie : une partie qui commence forte (niveau de depart eleve)
    // reste difficile du debut a la fin plutot que de retomber au plus facile
    // a la premiere manche.
    $niveau = $niveauDepart + $manche;
    $palier = fgCompteNiveau($niveau);
    $nUtiles = $palier['nUtiles'];
    $operateursDisponibles = $palier['operateurs'];

    $nombresUtiles = array();
    $operateurs = array();
    $premier = fgEntierStable($base . '|n0', 5, FG_COMPTE_MAX - 20);
    $nombresUtiles[] = $premier;
    $courant = $premier;

    // Le calcul s'affiche desormais LIGNE PAR LIGNE (une operation par ligne,
    // avec son propre total), et non plus a plat sur une seule ligne : voir
    // fgCompteEstBonExplication() et assets/jeu.js (rendreCompte). Un CE1 ne
    // connait pas les priorites operatoires, mais une ligne qui ne contient
    // QU'UNE seule operation ne peut pas se lire autrement que dans l'ordre
    // ou elle est ecrite - la contrainte "jamais de × ou ÷ apres un + ou un
    // −", necessaire quand tout tenait sur une ligne (trouvee par le responsable technique le
    // 01/09/2026 sur « 29 − 3 ÷ 2 »), n'a donc plus lieu d'etre : elle a ete
    // retiree ici, dans fgCompteEstBonValider() et dans fgCompteEstBonExplorer(),
    // le 05/09/2026, a la demande de le responsable technique.
    for ($i = 1; $i < $nUtiles; $i++) {
        // $avant est LA seule verite sur le total avant ce pas : chaque
        // branche calcule $courant a partir de lui, jamais d'un total
        // recalcule autrement - c'est cette confusion qui avait produit un
        // total faux dans une premiere version, corrigee avant la moindre
        // mise en ligne.
        $avant = $courant;

        $candidats = array();
        if ($avant < FG_COMPTE_MAX) {
            $candidats[] = '+';
        }
        if ($avant >= 2) {
            $candidats[] = '-';
        }
        // × seulement si le nombre a multiplier est encore une table
        // connue : jamais 23 × 4, seulement des calculs comme 6 × 4.
        if (in_array('×', $operateursDisponibles, true) && $avant >= 1 && $avant <= 10) {
            $candidats[] = '×';
        }
        // ÷ seulement s'il existe un diviseur de la meme famille de tables
        // qui tombe juste : pas de reste, jamais.
        if (in_array('÷', $operateursDisponibles, true) && fgCompteDiviseursExacts($avant)) {
            $candidats[] = '÷';
        }
        $voulu = $candidats[fgEntierStable($base . '|op' . $i, 0, count($candidats) - 1)];

        // Un nombre deja utilise ne peut pas revenir : deux tuiles identiques
        // seraient ambigues a l'ecran (laquelle des deux l'eleve a-t-il
        // prise ?). Pour + et -, on parcourt les valeurs possibles jusqu'a en
        // trouver une libre ; pour × et ÷, glisser casserait la table, donc on
        // parcourt les AUTRES facteurs ou diviseurs de la meme famille. Si
        // vraiment aucun n'est libre, on retombe sur une addition plutot que
        // de bloquer la manche.
        if ($voulu === '+') {
            $max = max(1, min(30, FG_COMPTE_MAX - $avant));
            $n = fgCompteValeurLibre($base . '|n' . $i, 1, $max, $nombresUtiles);
            $courant = $avant + $n;
        } elseif ($voulu === '-') {
            $max = min(30, $avant - 1);
            $n = fgCompteValeurLibre($base . '|n' . $i, 1, max(1, $max), $nombresUtiles);
            $courant = $avant - $n;
        } elseif ($voulu === '×') {
            $options = array_values(array_diff(
                array_filter(FG_COMPTE_TABLES, static fn($f) => $avant * $f <= FG_COMPTE_MAX),
                $nombresUtiles
            ));
            if (!$options) {
                $voulu = '+';
                $max = max(1, min(30, FG_COMPTE_MAX - $avant));
                $n = fgCompteValeurLibre($base . '|n' . $i . '|repli', 1, $max, $nombresUtiles);
                $courant = $avant + $n;
            } else {
                $n = $options[fgEntierStable($base . '|n' . $i, 0, count($options) - 1)];
                $courant = $avant * $n;
            }
        } else {
            $options = array_values(array_diff(fgCompteDiviseursExacts($avant), $nombresUtiles));
            if (!$options) {
                $voulu = '+';
                $max = max(1, min(30, FG_COMPTE_MAX - $avant));
                $n = fgCompteValeurLibre($base . '|n' . $i . '|repli', 1, $max, $nombresUtiles);
                $courant = $avant + $n;
            } else {
                $n = $options[fgEntierStable($base . '|n' . $i, 0, count($options) - 1)];
                $courant = intdiv($avant, $n);
            }
        }

        $nombresUtiles[] = $n;
        $operateurs[] = $voulu;
    }

    $cible = $courant;

    // Un ou deux nombres qui ne servent a rien, distincts de ceux de la
    // solution et entre eux : encore une fois, pour qu'aucune tuile ne se
    // confonde avec une autre.
    $nLeurres = $nUtiles <= 2 ? 2 : 1;
    $tousLesNombres = $nombresUtiles;
    for ($i = 0; $i < $nLeurres; $i++) {
        $leurre = fgEntierStable($base . '|leurre' . $i, 3, FG_COMPTE_MAX);
        while (in_array($leurre, $tousLesNombres, true)) {
            $leurre++;
            if ($leurre > FG_COMPTE_MAX) {
                $leurre = 3;
            }
        }
        $tousLesNombres[] = $leurre;
    }
    fgMelangerStable($tousLesNombres, $base . '|ordre');

    return array(
        'nombres' => $tousLesNombres,
        'cible' => $cible,
        'operateurs' => $operateursDisponibles,
        'solution' => array('nombres' => $nombresUtiles, 'operateurs' => $operateurs),
    );
}

/**
 * Les diviseurs qui divisent EXACTEMENT $n, sans reste - seulement 2 et 5,
 * plus restreint que les tables de multiplication. Voir FG_COMPTE_DIVISEURS.
 */
function fgCompteDiviseursExacts(int $n): array
{
    return array_values(array_filter(FG_COMPTE_DIVISEURS, static fn($d) => $d <= $n && $n % $d === 0));
}

/**
 * Tire un nombre entre $min et $max qui n'est pas deja dans $exclues.
 *
 * Le premier tirage reste deterministe (fgEntierStable) ; s'il tombe sur une
 * valeur deja prise, on balaie le reste de la plage dans l'ordre - toujours
 * deterministe, puisque $exclues l'est. Si la plage entiere est deja prise
 * (arrive seulement quand $max - $min est tres petit), on rend le premier
 * tirage tel quel : une tuile en double, rarissime, vaut mieux qu'une manche
 * qui ne se genere pas.
 */
function fgCompteValeurLibre(string $graine, int $min, int $max, array $exclues): int
{
    $n = fgEntierStable($graine, $min, $max);
    if (!in_array($n, $exclues, true)) {
        return $n;
    }
    for ($candidat = $min; $candidat <= $max; $candidat++) {
        if (!in_array($candidat, $exclues, true)) {
            return $candidat;
        }
    }
    return $n;
}

/**
 * Ecrit la solution de reference EN PLUSIEURS LIGNES, une operation par
 * ligne avec son propre total, pour l'explication.
 *
 * Jusqu'au 05/09/2026, la solution s'ecrivait a plat sur une seule ligne
 * (« 31 − 1 + 2 = 32 »), ce qui obligeait a interdire un × ou un ÷ apres un
 * + ou un − : sans cette regle, lire l'expression avec les vraies priorites
 * operatoires (× et ÷ avant + et −) aurait pu tomber sur un resultat
 * different de celui du jeu - le probleme trouve par le responsable technique le 01/09/2026
 * sur « 29 − 3 ÷ 2 ». En multi-lignes, chaque ligne ne contient qu'UNE
 * operation : plus aucune ambiguite possible, donc plus besoin de la regle
 * (retiree du generateur et du validateur le meme jour, a la demande de
 * le responsable technique - « c'est pour ca qu'il faudrait faire les calculs sur plusieurs
 * lignes »).
 *
 * Rendu en tableau plutot qu'en chaine : assets/jeu.js affiche chaque ligne
 * separement, exactement comme il affiche le calcul de l'eleve pendant la
 * manche (rendreCompte()) - les deux partagent maintenant le meme langage
 * visuel.
 */
function fgCompteEstBonExplication(array $manche): array
{
    $nombres = $manche['solution']['nombres'];
    $operateurs = $manche['solution']['operateurs'];
    $total = $nombres[0];
    // Meme forme que lignesCompte() cote client (assets/jeu.js) : la
    // premiere ligne ne porte que le nombre de depart, chaque ligne
    // suivante son operateur, son nombre et le total qui en resulte. Du
    // JSON structure plutot qu'une chaine deja mise en forme, pour que le
    // client n'ait jamais a re-analyser un texte pour en extraire les
    // nombres.
    $lignes = array(array('nombre' => $total));
    foreach ($operateurs as $i => $operateur) {
        $suivant = $nombres[$i + 1];
        $total = match ($operateur) {
            '+' => $total + $suivant,
            '-' => $total - $suivant,
            '×' => $total * $suivant,
            '÷' => intdiv($total, $suivant),
        };
        $lignes[] = array('operateur' => $operateur, 'nombre' => $suivant, 'total' => $total);
    }
    return array(
        'lignes' => $lignes,
        'autres' => fgCompteEstBonAutresSolutions($manche),
    );
}

/**
 * Existe-t-il VRAIMENT une autre facon d'atteindre la cible ?
 *
 * Corrige le 04/09/2026 : la premiere version comptait juste les etapes de la
 * solution de reference (plus d'une etape => la phrase s'affiche), ce qui
 * n'a aucun rapport avec la question posee. le responsable technique : « c'est pas une
 * question d'etapes, mais si y'a qu'une solution, cette phrase ne doit pas
 * s'afficher ». Ici, une vraie recherche : parmi TOUTES les tuiles de la
 * manche (solution ET leurres), y a-t-il un AUTRE sous-ensemble de tuiles
 * qui, combine dans un ordre et avec des operateurs autorises par le palier,
 * tombe aussi exactement sur la cible ?
 *
 * « Un autre sous-ensemble » et pas « un autre ordre » : 6 + 2 + 10 et
 * 2 + 10 + 6 utilisent les memes tuiles, ce n'est pas une autre solution aux
 * yeux d'un CE1, juste la meme ecrite dans un ordre different. Deux
 * sous-ensembles de tuiles differents qui tombent tous les deux juste, en
 * revanche, sont deux VRAIES facons de faire.
 *
 * Rejoue exactement les regles de fgCompteEstBonValider() (meme ordre de
 * verification, memes limites - diviseur dans FG_COMPTE_DIVISEURS, division
 * exacte, jamais de x ou / apres un + ou un -, jamais de resultat negatif) :
 * une solution que la recherche trouve ici doit etre une solution qu'un
 * eleve pourrait vraiment poser et voir acceptee.
 *
 * Au plus 5 tuiles (FG_COMPTE_NIVEAU_MAX) : la recherche explore toutes les
 * permutations de tous les sous-ensembles, largement a la portee d'une seule
 * requete web sans temps de calcul perceptible.
 */
function fgCompteEstBonAutresSolutions(array $manche): bool
{
    $nombres = $manche['nombres'];
    $cible = $manche['cible'];
    $operateursDisponibles = $manche['operateurs'];
    $total = count($nombres);

    $sousEnsemblesReussis = array();
    for ($masque = 1; $masque < (1 << $total); $masque++) {
        $indices = array();
        for ($i = 0; $i < $total; $i++) {
            if ($masque & (1 << $i)) {
                $indices[] = $i;
            }
        }
        if (count($indices) < 2) {
            continue;
        }

        $reussi = false;
        foreach ($indices as $depart) {
            $restants = array_values(array_diff($indices, array($depart)));
            if (fgCompteEstBonExplorer($nombres[$depart], $restants, $nombres, $cible, $operateursDisponibles)) {
                $reussi = true;
                break;
            }
        }
        if (!$reussi) {
            continue;
        }

        $valeurs = array();
        foreach ($indices as $i) {
            $valeurs[] = $nombres[$i];
        }
        sort($valeurs);
        $sousEnsemblesReussis[implode(',', $valeurs)] = true;
        if (count($sousEnsemblesReussis) > 1) {
            return true;
        }
    }
    return false;
}

/** Explore, en profondeur, toutes les facons de consommer $restants a partir de $courant. */
function fgCompteEstBonExplorer(int $courant, array $restants, array $nombres, int $cible, array $operateursDisponibles): bool
{
    if (!$restants) {
        return $courant === $cible;
    }
    foreach ($restants as $index) {
        $nombre = $nombres[$index];
        $suite = array_values(array_diff($restants, array($index)));
        foreach ($operateursDisponibles as $operateur) {
            if ($operateur === '÷') {
                if (!in_array($nombre, FG_COMPTE_DIVISEURS, true) || $courant % $nombre !== 0) {
                    continue;
                }
                $valeur = intdiv($courant, $nombre);
            } elseif ($operateur === '×') {
                $valeur = $courant * $nombre;
            } elseif ($operateur === '+') {
                $valeur = $courant + $nombre;
            } else {
                $valeur = $courant - $nombre;
            }
            if ($valeur < 0) {
                continue;
            }
            if (fgCompteEstBonExplorer($valeur, $suite, $nombres, $cible, $operateursDisponibles)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Verifie l'expression soumise par l'eleve pour une manche donnee.
 *
 * @param array $manche La manche telle que rendue par fgCompteEstBonManche().
 * @param array $etapes Liste ordonnee de ['nombre' => int, 'operateur' => '+'|'-'|'×'|'÷'|null].
 *                       Le premier element porte operateur = null.
 *
 * RIEN N'EST FAIT CONFIANCE. Chaque nombre soumis doit exister PARMI LES
 * TUILES DE LA MANCHE et n'etre pris qu'une fois - on consomme les tuiles par
 * position, pas par valeur, pour rester correct meme si une future manche
 * autorisait des doublons. Un operateur hors des quatre autorises est refuse.
 * Un total intermediaire negatif rend l'expression invalide, meme si un pas
 * ulterieur la ramenait au-dessus de zero. Une division qui ne tombe pas
 * juste est refusee elle aussi, meme si le resultat final coincide par
 * ailleurs avec la cible : le CE1 ne fait pas de division a reste, la
 * validation ne peut donc pas la laisser passer en cours de route.
 *
 * CE QUI N'EST PAS VERIFIE ICI, VOLONTAIREMENT : que × ne porte que sur un
 * nombre a un chiffre. C'est une regle de GENERATION, pour que la solution de
 * reference reste un vrai calcul de table - pas une regle de SECURITE. Un
 * eleve qui trouve un chemin different, avec une multiplication sur un nombre
 * plus grand mais qui tombe juste, a droit a sa reponse : la contrainte ne
 * doit pas le punir d'avoir ete plus habile que la solution prevue.
 *
 * ANCIENNE REGLE DE SECURITE, RETIREE LE 05/09/2026 : un × ou un ÷ ne
 * pouvait pas arriver apres un + ou un −, pour que l'ECRITURE A PLAT du
 * calcul, lue avec les vraies priorites operatoires, tombe toujours sur le
 * meme resultat que le jeu - le probleme trouve par le responsable technique sur
 * « 29 − 3 ÷ 2 ». Cette regle n'a plus de raison d'etre depuis que le calcul
 * s'affiche LIGNE PAR LIGNE (une operation par ligne, chacune avec son
 * propre total, assets/jeu.js) : une ligne a une seule operation ne peut pas
 * se lire autrement que dans l'ordre ou elle est ecrite. Demande de le responsable technique :
 * « c'est pour ca qu'il faudrait faire les calculs sur plusieurs lignes ».
 *
 * AUTRE REGLE DE SECURITE, ET LA DIFFERENCE AVEC × EST VOULUE : le diviseur
 * doit valoir 2 ou 5, jamais un autre nombre, meme si la division tombe
 * juste. Demande de le responsable technique le 01/09/2026. Contrairement a une grosse
 * multiplication - que la repetition d'une addition rend accessible a un
 * eleve habile meme hors table memorisee - diviser par 3, 4 ou 7 n'est pas
 * une operation que le CE1 sait faire du tout, table ou pas : rien a gagner a
 * laisser passer une division qui « tombe juste » par hasard sur un nombre
 * que l'eleve n'a de toute facon pas les moyens de diviser de tete.
 */
function fgCompteEstBonValider(array $manche, array $etapes): array
{
    if (count($etapes) < 2) {
        // Une seule tuile posee n'est pas un calcul.
        return array('correct' => false, 'resultat' => null);
    }

    $disponibles = $manche['nombres'];
    $prises = array();
    $resultat = null;

    foreach ($etapes as $position => $etape) {
        $nombre = isset($etape['nombre']) ? (int)$etape['nombre'] : null;
        $operateur = $etape['operateur'] ?? null;

        $index = null;
        foreach ($disponibles as $i => $valeur) {
            if (!in_array($i, $prises, true) && $valeur === $nombre) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            // Un nombre qui n'est pas une tuile de cette manche, ou deja pris.
            return array('correct' => false, 'resultat' => null);
        }
        $prises[] = $index;

        if ($position === 0) {
            if ($operateur !== null) {
                return array('correct' => false, 'resultat' => null);
            }
            $resultat = $nombre;
            continue;
        }
        if (!in_array($operateur, array('+', '-', '×', '÷'), true)) {
            return array('correct' => false, 'resultat' => null);
        }
        if ($operateur === '÷' && !in_array($nombre, FG_COMPTE_DIVISEURS, true)) {
            // Diviser par autre chose que 2 ou 5 - y compris par zero, qui
            // n'y figure pas non plus : voir le commentaire de la fonction.
            return array('correct' => false, 'resultat' => null);
        }
        if ($operateur === '÷' && $resultat % $nombre !== 0) {
            // Une division qui laisserait un reste : le CE1 ne les pratique
            // pas, la reponse est donc invalide.
            return array('correct' => false, 'resultat' => null);
        }
        $resultat = match ($operateur) {
            '+' => $resultat + $nombre,
            '-' => $resultat - $nombre,
            '×' => $resultat * $nombre,
            '÷' => intdiv($resultat, $nombre),
        };
        if ($resultat < 0) {
            return array('correct' => false, 'resultat' => $resultat);
        }
    }

    return array('correct' => $resultat === $manche['cible'], 'resultat' => $resultat);
}

/** Fabrique la partie complete, meme forme de retour que fgJeuDepuisBanque(). */
function fgJeuCompteEstBon(string $graine, int $niveauDepart = FG_COMPTE_NIVEAU_MIN): array
{
    $questions = array();
    for ($m = 0; $m < FG_MANCHES_COMPTE; $m++) {
        $manche = fgCompteEstBonManche($graine, $m, $niveauDepart);
        $questions[] = array(
            'consigne' => 'Utilise certains de ces nombres, une seule fois chacun, pour tomber exactement sur la cible.',
            'nombres' => $manche['nombres'],
            'cible' => $manche['cible'],
            'operateurs' => $manche['operateurs'],
            'explication' => fgCompteEstBonExplication($manche),
        );
    }

    return array(
        'titre' => 'Le compte est bon',
        'description' => 'Compose un calcul pour atteindre le nombre demandé.',
        'competence' => 'Calcul mental : additions, soustractions, multiplications et divisions',
        'duree' => '5 min',
        'ton' => 'ambre',
        'mecanique' => 'compte',
        'questions' => $questions,
    );
}
