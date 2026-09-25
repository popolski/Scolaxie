<?php
/**
 * « L'HORLOGE » : poser les aiguilles pour afficher une heure demandee.
 *
 * TROISIEME JEU DU SITE OU LA REPONSE SE CONSTRUIT au lieu de se choisir,
 * apres le compte est bon et la boutique. Le geste est celui de regler une
 * pendule : on attrape une aiguille et on la tourne. C'est une action reelle,
 * pas un glissement decoratif pose sur un QCM - la regle de conception du
 * 01/09/2026 (voir boutique.php).
 *
 * CE QUE CA DEBLOQUE. Deux competences du programme etaient marquees « ne se
 * joue pas » dans data/competences-jouables.php, avec la meme raison :
 *   3980  « necessite un cadran d horloge »
 *   3981  « meme limite que 3980 »
 * et la note « Aucune mecanique graphique d horloge actuelle ». C'est
 * exactement la situation de la monnaie avant la boutique : ce n'etait pas la
 * competence qui resistait, c'etait l'absence d'un cadran manipulable. La
 * banque `maths-quelle-heure` qui existe deja ne lit aucun cadran - c'est un
 * QCM textuel sur les moments de la journee (competence 3903), un autre sujet.
 *
 * L'ADAPTATION CE1, ET LA SIMPLIFICATION ASSUMEE :
 * - Sur une vraie pendule, a 7 h 30, la petite aiguille est a MI-CHEMIN entre
 *   le 7 et le 8. Ici elle pointe franchement le 7. C'est la convention des
 *   manuels de cycle 2, et surtout la seule qui se valide sans ambiguite : une
 *   position intermediaire demanderait une tolerance en degres, donc un
 *   « presque juste » qui n'a pas de sens quand on apprend a lire l'heure.
 *   A dire en classe, pas a corriger ici.
 * - L'apres-midi n'est pas demande : un cadran a douze heures ne peut pas
 *   distinguer 3 h de 15 h, et le programme de cycle 2 lit d'abord le cadran.
 *   Les heures visees sont donc toujours entre 1 et 12.
 * - La minute avance de cinq en cinq au maximum : c'est la graduation que
 *   l'eleve sait lire, et celle que le cadran affiche.
 *
 * TOUT EST DETERMINISTE, comme le reste du site : meme graine, meme manche, ce
 * qui permet au serveur de refabriquer la partie pour corriger sans l'avoir
 * stockee.
 */

require_once __DIR__ . '/generateurs.php';

/** Nombre de manches d'une partie, comme le compte est bon et la boutique. */
const FG_MANCHES_HORLOGE = 5;

const FG_HORLOGE_NIVEAU_MIN = 1;
const FG_HORLOGE_NIVEAU_MAX = 5;

/**
 * Ce qui change avec le niveau : la finesse de la minute demandee, et la
 * facon dont l'heure est ecrite.
 *
 * La progression suit celle du programme : l'heure juste d'abord, puis la
 * demie, puis les quarts, puis les minutes de cinq en cinq. Le dernier palier
 * ne rend pas la minute plus fine - il change l'ENONCE, qui passe en toutes
 * lettres (« huit heures moins le quart ») : lire l'heure, c'est aussi la
 * reconnaitre dite comme on la dit vraiment.
 */
function fgHorlogeNiveau(int $niveau): array
{
    $niveau = max(FG_HORLOGE_NIVEAU_MIN, min(FG_HORLOGE_NIVEAU_MAX, $niveau));
    $paliers = array(
        1 => array('minutes' => array(0),                       'lettres' => false),
        2 => array('minutes' => array(0, 30),                   'lettres' => false),
        3 => array('minutes' => array(0, 15, 30, 45),           'lettres' => false),
        4 => array('minutes' => array(0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55), 'lettres' => false),
        5 => array('minutes' => array(0, 15, 30, 45),           'lettres' => true),
    );
    return $paliers[$niveau];
}

/** Le niveau de depart de la prochaine partie, garde en session. */
function fgHorlogeNiveauDepart(): int
{
    // Meme raison que fgCompteNiveauDepart() : le palier se reconstruit depuis
    // les parties enregistrees au premier appel de la session (FG-AUDIT-002).
    if (!isset($_SESSION['fastgames_horloge_niveau'])) {
        require_once __DIR__ . '/helpers.php';
        $_SESSION['fastgames_horloge_niveau'] = fgNiveauDepartDurable(
            'horloge', FG_HORLOGE_NIVEAU_MIN, FG_HORLOGE_NIVEAU_MAX);
    }
    $niveau = (int)$_SESSION['fastgames_horloge_niveau'];
    return max(FG_HORLOGE_NIVEAU_MIN, min(FG_HORLOGE_NIVEAU_MAX, $niveau));
}

/** Meme regle que le compte est bon et la boutique : 80 % monte, 40 % descend. */
function fgHorlogeAjusterNiveau(int $score, int $total): void
{
    if ($total <= 0) {
        return;
    }
    $niveau = fgHorlogeNiveauDepart();
    $taux = $score / $total;
    if ($taux >= 0.8) {
        $niveau++;
    } elseif ($taux <= 0.4) {
        $niveau--;
    }
    $_SESSION['fastgames_horloge_niveau'] = max(FG_HORLOGE_NIVEAU_MIN, min(FG_HORLOGE_NIVEAU_MAX, $niveau));
}

/** L'heure ecrite en chiffres, comme sur une etiquette : « 7 h 30 », « 9 h ». */
function fgHorlogeChiffres(int $heures, int $minutes): string
{
    return $minutes === 0
        ? $heures . ' h'
        : $heures . ' h ' . str_pad((string)$minutes, 2, '0', STR_PAD_LEFT);
}

/**
 * L'heure dite comme on la dit vraiment : « huit heures moins le quart ».
 *
 * Seuls la demie et les quarts sont formules ainsi - ce sont les seules
 * tournures que le palier 5 tire, et les seules que le programme de cycle 2
 * demande de reconnaitre a l'oral. Une minute quelconque (« sept heures
 * vingt-cinq ») s'ecrirait sans difficulte, mais n'apporterait rien de plus.
 */
function fgHorlogeLettres(int $heures, int $minutes): string
{
    $noms = array(1 => 'une', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq',
                  6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix',
                  11 => 'onze', 12 => 'douze');
    if ($minutes === 45) {
        // Moins le quart : c'est l'heure SUIVANTE qui est nommee. Apres douze
        // on revient a une, sinon « treize heures moins le quart » apparaitrait
        // sur un cadran qui ne compte que jusqu'a douze.
        $suivante = $heures === 12 ? 1 : $heures + 1;
        return $noms[$suivante] . ' heure' . ($suivante > 1 ? 's' : '') . ' moins le quart';
    }
    $base = $noms[$heures] . ' heure' . ($heures > 1 ? 's' : '');
    if ($minutes === 15) {
        return $base . ' et quart';
    }
    if ($minutes === 30) {
        return $base . ' et demie';
    }
    return $base;
}

/**
 * Fabrique une manche : une heure a afficher.
 *
 * L'heure est tiree dans 1..12 et la minute dans les valeurs autorisees par le
 * palier. Aucune manche n'est impossible : toute heure tiree est affichable sur
 * le cadran, il n'y a donc rien a verifier apres coup, contrairement a la
 * boutique ou la somme devait rester composable avec le porte-monnaie affiche.
 */
function fgHorlogeManche(string $graine, int $manche, int $niveauDepart = FG_HORLOGE_NIVEAU_MIN): array
{
    $base = $graine . '|horloge|' . $manche;
    $palier = fgHorlogeNiveau($niveauDepart + $manche);

    $heures = fgEntierStable($base . '|heures', 1, 12);
    $minutesPossibles = $palier['minutes'];
    $minutes = $minutesPossibles[fgEntierStable($base . '|minutes', 0, count($minutesPossibles) - 1)];

    // Au palier 5, l'enonce est en toutes lettres : « moins le quart » nomme
    // l'heure suivante, ce qui est precisement la difficulte visee.
    $enonce = $palier['lettres']
        ? fgHorlogeLettres($heures, $minutes)
        : fgHorlogeChiffres($heures, $minutes);

    return array(
        'heures' => $heures,
        'minutes' => $minutes,
        'enonce' => $enonce,
        'lettres' => $palier['lettres'],
    );
}

/** La consigne affichee, qui change avec la facon dont l'heure est ecrite. */
function fgHorlogeConsigne(array $manche): string
{
    return $manche['lettres']
        ? 'Place les aiguilles sur l’heure que tu entends dire.'
        : 'Place les aiguilles pour afficher cette heure.';
}

/** L'explication montree apres la reponse : l'heure, et ou vont les aiguilles. */
function fgHorlogeExplication(array $manche): string
{
    $heures = (int)$manche['heures'];
    $minutes = (int)$manche['minutes'];
    $ou = $minutes === 0
        ? 'la grande aiguille sur le 12'
        : 'la grande aiguille sur le ' . intdiv($minutes, 5);
    return fgHorlogeChiffres($heures, $minutes) . ' : la petite aiguille sur le '
         . $heures . ', ' . $ou . '.';
}

/**
 * Verifie les aiguilles posees par l'eleve.
 *
 * RIEN N'EST FAIT CONFIANCE : l'heure et la minute recues sont ramenees dans
 * leurs bornes avant d'etre comparees. La comparaison est EXACTE - a une
 * position d'aiguille pres, il n'y a pas de « presque » quand on lit l'heure.
 * Le cadran ne compte que douze heures : 12 et 0 designent la meme position,
 * on les ramene donc a 12 pour ne pas compter faux une aiguille bien posee.
 */
function fgHorlogeValider(array $manche, int $heures, int $minutes): array
{
    $heures = $heures === 0 ? 12 : $heures;
    $correct = $heures >= 1 && $heures <= 12
        && $minutes >= 0 && $minutes <= 59
        && $heures === (int)$manche['heures']
        && $minutes === (int)$manche['minutes'];
    return array('correct' => $correct);
}

/** Fabrique la partie complete, meme forme de retour que les autres jeux. */
function fgJeuHorloge(string $graine, int $niveauDepart = FG_HORLOGE_NIVEAU_MIN): array
{
    $questions = array();
    for ($m = 0; $m < FG_MANCHES_HORLOGE; $m++) {
        $manche = fgHorlogeManche($graine, $m, $niveauDepart);
        $questions[] = array(
            'consigne' => fgHorlogeConsigne($manche),
            'enonce' => $manche['enonce'],
            'heures' => $manche['heures'],
            'minutes' => $manche['minutes'],
            'explication' => fgHorlogeExplication($manche),
        );
    }

    return array(
        'titre' => 'L’horloge',
        'description' => 'Place les aiguilles pour afficher l’heure demandée.',
        'competence' => 'Lire l’heure sur une horloge à aiguilles',
        'duree' => '5 min',
        'ton' => 'corail',
        'mecanique' => 'horloge',
        'questions' => $questions,
    );
}
