<?php
/**
 * LES MECANIQUES : TRI, PAIRES ET ORDRE.
 *
 * Une mecanique sait jouer N'IMPORTE QUELLE banque de son type. C'est toute la
 * difference avec les huit generateurs d'origine, qui fabriquaient a la fois la
 * facon de jouer et le contenu joue : ajouter une notion demandait du code.
 * Ici, ajouter une notion demande une liste dans data/banques.php.
 *
 * ORDRE EST DIFFERENTE DES DEUX AUTRES, ajoutee le 02/09/2026 en transposant
 * alphabet1/2/3.php de School Monsters : la reponse n'y est pas choisie parmi
 * des propositions, elle est CONSTRUITE en rangeant cinq mots. Voir
 * fgQuestionsOrdre() plus bas - c'est la meme famille que le compte est bon et
 * la boutique (une reponse composee, pas cochee), mais elle reste une banque
 * comme tri et paires, donc elle profite de tout le reste sans rien
 * dupliquer : catalogue, suivi, couverture du programme.
 *
 * UN CHOIX A ASSUMER : LA VALIDATION RESTE UN CHOIX PARMI DES PROPOSITIONS.
 * Un tri se joue naturellement en glissant des etiquettes dans des colonnes, et
 * une association en reliant deux listes. Mais cote serveur, les deux se
 * ramenent a « pour cet element, quelle est la bonne case ? ». On reutilise
 * donc entierement la machinerie de seance deja en place - reponses dans
 * l'ordre, correction par le serveur, score recalcule - au lieu d'inventer un
 * second circuit a securiser.
 *
 * Ce qui change, c'est l'AFFICHAGE : le client recoit le type de mecanique et
 * presente des colonnes de tri ou deux listes a relier, pas quatre boutons
 * empiles. Le glisser-deposer pourra remplacer les boutons plus tard sans
 * toucher au serveur, puisque la reponse envoyee restera un index.
 *
 * Pour un CE1 c'est meme preferable au glisser-deposer : pas de frustration
 * motrice, ca marche au doigt sur tablette comme a la souris.
 *
 * TOUT EST DETERMINISTE. Les questions sont tirees a partir d'une graine, comme
 * les generateurs de calcul, pour que le serveur puisse refabriquer exactement
 * la meme seance au moment de corriger sans avoir a la stocker en entier.
 */

require_once __DIR__ . '/generateurs.php';

/** Nombre de questions d'une partie. Voir fgQuestionsTri pour le cas du tri. */
const FG_QUESTIONS_TRI = 6;
const FG_QUESTIONS_PAIRES = 5;
const FG_QUESTIONS_QCM = 6;

/** Nombre de manches par partie pour la mecanique `ordre`, dans ses deux
 *  formes : le tri alphabetique a pool de mots, et les suites ecrites a la
 *  main (frises, phrases, nombres). Depuis le 10/09/2026 ces dernieres sont
 *  PUISEES et non plus toutes deroulees - voir fgQuestionsOrdre. */
const FG_QUESTIONS_ORDRE = 4;

/**
 * Nombre de mots a ranger, MANCHE PAR MANCHE. Trois d'abord, puis quatre,
 * puis cinq : c'est la progression de difficulte du tri alphabetique, et
 * elle est gratuite - ranger trois mots est reellement plus facile que d'en
 * ranger cinq, aucune qualification a la main n'est necessaire pour le
 * savoir. La derniere valeur vaut pour toutes les manches suivantes, donc
 * cinq reste le format d'origine des que la partie est lancee.
 */
const FG_MOTS_PAR_MANCHE_ORDRE = array(3, 4, 5);

/** Nombre de sons tires par partie, sur un pool de dix par theme. */
const FG_QUESTIONS_ECOUTE = 6;

/**
 * LA PROGRESSION DE DIFFICULTE AU FIL D'UNE PARTIE, ecrite le 06/09/2026 a la
 * demande de le responsable technique.
 *
 * LE PRINCIPE, VALABLE POUR LES TROIS MECANIQUES QUI L'APPLIQUENT : on trie
 * APRES le tirage, jamais avant. Le tirage reste aleatoire - c'est lui qui
 * fait qu'on ne rejoue pas la meme partie - et le classement ne fait que
 * choisir dans quel ORDRE les questions deja tirees sont posees. Trier avant
 * reviendrait a toujours poser les memes six questions, la plus facile en
 * tete : on perdrait la variete gagnee le meme jour.
 *
 * POURQUOI SEULEMENT TROIS MECANIQUES. La difficulte d'un item n'est pas une
 * opinion ici, elle se CALCULE :
 *   - droite : la distance a la graduation etiquetee la plus proche. Placer
 *     32 sur une droite graduee de dix en dix est facile, 35 est le cas dur,
 *     et c'est exactement ce que le jeu travaille - l'interpolation ;
 *   - carte  : la tolerance en degres, deja ecrite lieu par lieu ;
 *   - ordre  : le nombre de mots a ranger, ou la taille des nombres.
 * Pour tri, paires, qcm et ecoute, aucune mesure de ce genre n'existe : leur
 * difficulte demanderait une qualification a la main des 765 items. Elles
 * gardent donc l'ordre du tirage, et c'est un manque assume, pas un oubli.
 *
 * LES EGALITES SE DEPARTAGENT PAR LE RANG DU TIRAGE, jamais au hasard : deux
 * items de meme difficulte doivent rester dans un ordre reproductible, sans
 * quoi la reconstruction d'une seance depuis sa graine ne retomberait pas sur
 * la meme partie et le score serait recalcule sur autre chose.
 *
 * @param array    $items    les items DEJA tires
 * @param callable $mesure   rend la difficulte d'un item ; plus grand = plus dur
 * @return array             les memes items, du plus facile au plus difficile
 */
function fgOrdonnerParDifficulte(array $items, callable $mesure): array
{
    $indexes = array_keys($items);
    usort($indexes, static function ($a, $b) use ($items, $mesure): int {
        $ecart = $mesure($items[$a]) <=> $mesure($items[$b]);
        return $ecart !== 0 ? $ecart : ($a <=> $b);
    });

    $ordonnes = array();
    foreach ($indexes as $i) {
        $ordonnes[] = $items[$i];
    }
    return $ordonnes;
}

/**
 * Carte et droite graduee. Ces deux mecaniques, ecrites le 06/09/2026,
 * PARCOURAIENT TOUT LE RESERVOIR de leur banque au lieu d'y puiser : la
 * partie faisait autant de questions que la banque avait d'items. Sans
 * consequence visible tant que chaque banque en contenait exactement six -
 * ce qui etait le cas partout - mais avec deux effets pervers : enrichir
 * une banque aurait RALLONGE la partie au lieu de la varier, et deux
 * lancements du meme jeu montraient forcement les memes items, seulement
 * dans un autre ordre. Les cinq mecaniques plus anciennes coupent toutes
 * apres melange ; ces deux-la manquaient simplement leur coupe.
 * Trouve en verifiant l'aleatoire banque par banque, a la demande de
 * le responsable technique. Valeurs choisies egales aux tailles actuelles : rien ne change
 * aujourd'hui, mais une banque enrichie varie desormais au lieu de gonfler.
 */
const FG_QUESTIONS_CARTE = 6;
const FG_QUESTIONS_DROITE = 6;

/** Toutes les banques, chargees une seule fois, avec les competences choisies par un administrateur. */
function fgBanques(): array
{
    static $banques = null;
    if ($banques === null) {
        $banques = fgBanquesProgramme();
        // Sans session de base (tests, controle des banques), le programme ecrit s'applique seul.
        if (class_exists('bdd', false) && !empty($_SESSION['bdd'])) {
            require_once __DIR__ . '/liens-competences.php';
            try {
                $banques = fgFusionnerLiensBanques($banques, fgLiensCompetences(bdd::connexion((string)$_SESSION['bdd'])) ?? array());
            } catch (PDOException $e) {
                error_log('FastGames : liens de competence illisibles, programme ecrit applique.');
            }
        }
    }
    return $banques;
}

/** Les banques telles qu'ecrites dans data/banques.php, sans choix d'administrateur. */
function fgBanquesProgramme(): array
{
    static $programme = null;
    return $programme ??= require dirname(__DIR__) . '/data/banques.php';
}

/**
 * Remplace, niveau par niveau, la competence d'une banque par le choix d'un administrateur
 * (liens-competences.php, demande de le responsable technique du 15/09/2026). Tant qu'un niveau garde le
 * programme ecrit, sa liste reste valable : les parties de ce niveau y sont encore creditees.
 */
function fgFusionnerLiensBanques(array $banques, array $liens): array
{
    foreach ($banques as $cle => &$banque) {
        $parNiveau = array_map(static fn($ligne) => (int)$ligne['id_competence'], $liens['banque:' . $cle] ?? array());
        if (!$parNiveau) continue;
        $banque['liens'] = $parNiveau;
        $choisies = array_values(array_unique(array_filter(array($parNiveau['CE2'] ?? 0, $parNiveau['CE1'] ?? 0))));
        $banque['competences'] = isset($parNiveau['CE1'], $parNiveau['CE2'])
            ? $choisies
            : array_values(array_unique(array_merge($choisies, array_map('intval', $banque['competences']))));
    }
    unset($banque);
    return $banques;
}

/**
 * Les banques qui portent sur une competence donnee, parmi celles de l'offre.
 *
 * Une banque marquee 'hors_offre' (data/banques.php) reste dans fgBanques() :
 * ses parties anciennes gardent leur titre et leur qualification. Elle ne
 * doit simplement plus rendre sa competence « jouable » ni apparaitre au
 * catalogue ou dans la couverture du programme (arbitrage V1, DEC-08).
 */
function fgBanquesDeCompetence(int $idReference): array
{
    $trouvees = array();
    foreach (fgBanques() as $cle => $banque) {
        if (!empty($banque['hors_offre'])) {
            continue;
        }
        if (in_array($idReference, $banque['competences'], true)) {
            $trouvees[$cle] = $banque;
        }
    }
    return $trouvees;
}

/**
 * Un tri : chaque element tire devient une question, et les categories de la
 * banque sont les propositions.
 *
 * ON TIRE DANS TOUTES LES CATEGORIES, et pas au hasard dans le tas : un tirage
 * purement aleatoire peut sortir six animaux carnivores, et l'eleve repond juste
 * six fois sans avoir rien trie. On prend donc a tour de role dans chaque
 * categorie, puis on melange l'ordre des questions.
 */
function fgQuestionsTri(array $banque, string $graine): array
{
    // Le TRIPLET COMPLET voyage jusqu'au bout - libelle, categorie, et une
    // explication facultative en troisieme position - au lieu du seul
    // libelle. Avant ce correctif, l'explication ecrite dans une banque etait
    // perdue des le regroupement par categorie : sans elle nulle part dans le
    // pipeline, la correction se rabattait sur « le requin : Carnivore. »,
    // qui redit la reponse au lieu de dire pourquoi.
    $parCategorie = array();
    foreach ($banque['elements'] as $element) {
        $parCategorie[$element[1]][] = $element;
    }
    foreach ($parCategorie as $categorie => $liste) {
        fgMelangerStable($liste, $graine . '|elements|' . $categorie);
        $parCategorie[$categorie] = $liste;
    }

    $categories = $banque['categories'];
    $choisis = array();
    $tour = 0;
    while (count($choisis) < FG_QUESTIONS_TRI) {
        $ajoute = false;
        foreach ($categories as $categorie) {
            if (!isset($parCategorie[$categorie][$tour])) {
                continue;
            }
            $choisis[] = $parCategorie[$categorie][$tour];
            $ajoute = true;
            if (count($choisis) >= FG_QUESTIONS_TRI) {
                break;
            }
        }
        if (!$ajoute) {
            break;      // banque plus courte que le nombre de questions voulu
        }
        $tour++;
    }
    fgMelangerStable($choisis, $graine . '|ordre');

    $questions = array();
    foreach ($choisis as $rang => $choisi) {
        // Les propositions gardent l'ordre de la banque : les categories d'un
        // tri ont un sens de lecture - « Carnivore, Herbivore, Omnivore », pas
        // dans le desordre a chaque question.
        $questions[] = array(
            'question' => $choisi[0],
            'consigne' => $banque['consigne'],
            'reponses' => array_values($banque['categories']),
            'bonne' => array_search($choisi[1], array_values($banque['categories']), true),
            'explication' => $choisi[2] ?? ($choisi[0] . ' : ' . $choisi[1] . '.'),
            // Un pictogramme optionnel, 4e position du triplet - absent dans
            // la plupart des banques (rien a illustrer sur « Determinant »
            // ou « Sens figure »), pose seulement sur celles ou une image
            // aide vraiment. Voir sciences-regimes, premiere banque a s'en
            // servir (06/09/2026).
            'image' => $choisi[3] ?? null,
        );
    }
    return $questions;
}

/**
 * Une association : chaque terme de gauche devient une question, et les termes
 * de droite servent de propositions.
 *
 * Les mauvaises propositions sont prises dans la banque elle-meme, jamais
 * inventees : elles sont donc toujours plausibles, ce qui est le propre d'un
 * bon exercice d'association. Avec quatre paires ou plus, on en montre quatre.
 */
function fgQuestionsPaires(array $banque, string $graine): array
{
    // Le sens peut s'inverser d'une partie a l'autre : « les yeux -> la vue »
    // ou « la vue -> les yeux ». C'est pour cela que les banques exigent des
    // paires lisibles dans les deux sens.
    $inverse = fgEntierStable($graine . '|sens', 0, 1) === 1;

    $paires = $banque['paires'];
    fgMelangerStable($paires, $graine . '|paires');

    // Une banque peut faire pointer PLUSIEURS termes de gauche vers le meme
    // terme de droite : « la glace devient de l'eau » et « un glacon fond dans
    // un verre » valent tous les deux « la fusion ». Dans le sens inverse, ces
    // deux paires posent alors la meme question. Sans ce dedoublonnage, la
    // partie posait deux fois « la fusion » en attendant a chaque fois une
    // reponse differente - mesure le 02/09/2026 : une partie sur deux de
    // sciences-etats-eau et de maths-unite-adaptee-longueur etait dans ce cas.
    $vues = array();
    $retenues = array();
    foreach ($paires as $paire) {
        $intitule = $inverse ? $paire[1] : $paire[0];
        if (isset($vues[$intitule])) { continue; }
        $vues[$intitule] = true;
        $retenues[] = $paire;
        if (count($retenues) >= FG_QUESTIONS_PAIRES) { break; }
    }

    $questions = array();
    foreach ($retenues as $rang => $paire) {
        $gauche = $inverse ? $paire[1] : $paire[0];
        $droite = $inverse ? $paire[0] : $paire[1];

        // Sans le controle de doublon, une banque ou plusieurs paires
        // partagent le meme terme de droite - comme sciences-etats-eau, ou
        // deux exemples valent « la fusion » - proposerait deux fois le meme
        // texte parmi les quatre choix, ce qui n'a aucun sens pour l'eleve.
        //
        // Le second controle est plus important encore : un leurre qui repond
        // JUSTE a la meme question n'est pas un leurre. Dans le sens inverse,
        // la question « un verbe qui parle du feu » acceptait « incendier »
        // mais comptait « embraser » faux, alors que les deux sont exacts.
        // Mesure avant correction le 02/09/2026 : de 8 % (francais-lexique-
        // nature) a 29 % (maths-unite-adaptee-longueur) des questions tirees
        // etaient dans ce cas. On ecarte donc toute paire qui pose la meme
        // question, pas seulement celle qui porte la meme reponse.
        $autres = array();
        foreach ($banque['paires'] as $candidate) {
            if (($inverse ? $candidate[1] : $candidate[0]) === $gauche) { continue; }
            $valeur = $inverse ? $candidate[0] : $candidate[1];
            if ($valeur !== $droite && !in_array($valeur, $autres, true)) {
                $autres[] = $valeur;
            }
        }
        fgMelangerStable($autres, $graine . '|leurres|' . $rang);
        $propositions = array_merge(array($droite), array_slice($autres, 0, 3));
        fgMelangerStable($propositions, $graine . '|melange|' . $rang);

        $questions[] = array(
            'question' => $gauche,
            'consigne' => $banque['consigne'],
            'reponses' => array_values($propositions),
            'bonne' => array_search($droite, array_values($propositions), true),
            'explication' => $paire[2] ?? ($gauche . ' : ' . $droite . '.'),
        );
    }
    return $questions;
}

/** Un QCM fixe : le contenu est ecrit dans la banque, les choix sont melanges. */
function fgQuestionsQcm(array $banque, string $graine): array
{
    $questions = $banque['questions'];
    fgMelangerStable($questions, $graine . '|questions');
    if (isset($banque['progression'])) {
        // Difficulte croissante : tant de questions tirees par niveau, les niveaux dans l'ordre.
        $tirees = array();
        foreach ($banque['progression'] as $niveau => $nombre) {
            // Reponses differentes d'abord : deux « Passe » de suite se devineraient sans lire.
            $distinctes = array();
            $autres = array();
            foreach ($questions as $q) {
                if ((int)($q['niveau'] ?? 0) !== (int)$niveau) continue;
                if (isset($distinctes[$q['bonne']])) $autres[] = $q;
                else $distinctes[$q['bonne']] = $q;
            }
            $tirees = array_merge($tirees, array_slice(array_merge(array_values($distinctes), $autres), 0, (int)$nombre));
        }
        $questions = $tirees;
    } else {
        $questions = array_slice($questions, 0, FG_QUESTIONS_QCM);
    }
    foreach ($questions as $rang => &$question) {
        $bonne = (string)$question['bonne'];
        $reponses = array_values($question['reponses']);
        if (empty($banque['reponses_fixes'])) fgMelangerStable($reponses, $graine . '|reponses|' . $rang);
        $question = array(
            'question' => (string)$question['question'],
            'consigne' => $banque['consigne'],
            'reponses' => $reponses,
            'bonne' => array_search($bonne, $reponses, true),
            'explication' => (string)($question['explication'] ?? ('La bonne réponse était : ' . $bonne . '.')),
        );
    }
    unset($question);
    return $questions;
}

/**
 * Un tri alphabetique : chaque manche pioche cinq mots dans le pool de la
 * banque et l'eleve doit les ranger. La reponse n'est pas choisie parmi des
 * propositions, elle est CONSTRUITE en glissant les mots dans le bon ordre -
 * voir le grand commentaire en tete de fichier.
 *
 * LE POOL EST DEJA TRIE (voir data/banques.php) : piocher des INDICES puis
 * les relire dans l'ordre croissant suffit a retrouver la bonne reponse d'un
 * sous-ensemble. Aucune comparaison de mots n'a lieu ici, et c'est voulu -
 * une comparaison alphabetique francaise correcte a l'execution demanderait
 * soit l'extension intl (absente en local, non garantie sur l'hebergement),
 * soit iconv//TRANSLIT (teste le 02/09/2026 : produit des artefacts selon la
 * locale du serveur, par exemple « bénéfice » devient « b'en'efice » sous
 * locale C, qui trie AVANT « bac » a cause de l'apostrophe). Trier une fois
 * pour toutes hors ligne, a la main, evite ce risque completement.
 */
/**
 * Une droite graduee : placer un nombre a sa place.
 *
 * SEPTIEME MECANIQUE, ecrite le 06/09/2026. La competence 3909 etait marquee
 * « ne se joue pas » avec la raison « necessite une droite graduee
 * interactive » et la note « aucune mecanique graphique actuelle » - mot pour
 * mot la formulation qui bloquait l'horloge et, avant elle, la monnaie. La
 * troisieme fois que ce n'est pas la competence qui resiste, mais l'absence
 * d'un objet manipulable.
 *
 * MEME FAMILLE QUE LA CARTE, et le moteur s'en inspire directement : l'eleve
 * pose un repere sur un espace CONTINU. Il n'y a rien a eliminer, donc ce
 * n'est pas un QCM habille - la regle du 01/09/2026. La difference tient a la
 * dimension : une ligne au lieu d'un plan, donc une seule coordonnee.
 *
 * CE QUI S'APPREND ICI, c'est d'INTERPOLER. Les graduations principales sont
 * etiquetees (0, 10, 20...), les intermediaires ne le sont pas : placer 35,
 * c'est comprendre qu'il est entre le 30 et le 40, plus pres du milieu. Une
 * droite ou chaque graduation porterait son nombre ne demanderait que de
 * lire.
 *
 * LA TOLERANCE EST DANS L'UNITE DE LA DROITE, pas en pixels : sur une droite
 * de 0 a 100, deux unites de marge valent deux centiemes de la longueur,
 * quelle que soit la taille de l'ecran. Elle vit au niveau de la BANQUE et
 * non de chaque nombre, contrairement a la carte : une droite est reguliere,
 * il n'y a pas de nombre « plus gros » qu'un autre.
 */
function fgQuestionsDroite(array $banque, string $graine): array
{
    $nombres = array_values($banque['nombres']);
    fgMelangerStable($nombres, $graine . '|droite|ordre');
    // Meme raison que pour la carte : on puise, on ne deroule pas.
    $nombres = array_slice($nombres, 0, FG_QUESTIONS_DROITE);

    $pasGraduation = (float)$banque['pas'];
    // Du plus facile au plus difficile : la distance a la graduation
    // ETIQUETEE la plus proche. Sur une droite graduee de dix en dix, 32 est
    // a 2 du 30 et se pose presque sans reflechir ; 35 est a 5 des deux
    // graduations qui l'encadrent, c'est le maximum possible et le vrai
    // exercice d'interpolation. Voir fgOrdonnerParDifficulte.
    $nombres = fgOrdonnerParDifficulte($nombres, static function ($n) use ($pasGraduation): float {
        if ($pasGraduation <= 0) {
            return 0.0;
        }
        $reste = fmod((float)$n, $pasGraduation);
        return min($reste, $pasGraduation - $reste);
    });

    $min = (float)$banque['min'];
    $max = (float)$banque['max'];
    $questions = array();
    foreach ($nombres as $nombre) {
        $questions[] = array(
            'question' => (string)$nombre,
            'consigne' => $banque['consigne'],
            'cible' => (float)$nombre,
            'min' => $min,
            'max' => $max,
            // Le pas des graduations ETIQUETEES. Les graduations
            // intermediaires sont dessinees par le client entre deux
            // etiquettes ; c'est ce qui laisse quelque chose a interpoler.
            'pas' => (float)$banque['pas'],
            'tolerance' => (float)($banque['tolerance'] ?? 2),
            'explication' => $nombre . ' se place entre ' . fgDroiteBorne($nombre, (float)$banque['pas'], false)
                           . ' et ' . fgDroiteBorne($nombre, (float)$banque['pas'], true) . '.',
        );
    }
    return $questions;
}

/**
 * La graduation etiquetee juste avant (ou juste apres) un nombre : de quoi
 * dire « 35 se place entre 30 et 40 » sans ecrire chaque explication a la
 * main dans la banque.
 */
function fgDroiteBorne(float $nombre, float $pas, bool $apres): string
{
    $borne = $apres ? (ceil($nombre / $pas) * $pas) : (floor($nombre / $pas) * $pas);
    // Un nombre pile sur une graduation n'est encadre par rien : on prend
    // alors la graduation voisine, sinon l'explication dirait « 30 se place
    // entre 30 et 30 ».
    if ($borne == $nombre) {
        $borne = $apres ? $nombre + $pas : $nombre - $pas;
    }
    return rtrim(rtrim(number_format($borne, 2, ',', ''), '0'), ',');
}

/** Verifie le repere pose sur la droite. Comparaison dans l'unite de la droite. */
function fgDroiteValider(array $question, float $valeur): array
{
    if ($valeur < (float)$question['min'] || $valeur > (float)$question['max']) {
        return array('correct' => false);
    }
    $ecart = abs($valeur - (float)$question['cible']);
    return array('correct' => $ecart <= (float)$question['tolerance']);
}

/**
 * Une carte : situer un lieu sur le planisphere.
 *
 * SIXIEME MECANIQUE, ecrite le 06/09/2026. Elle etait DECLAREE dans la
 * conception depuis le 31/08 (« carte : localiser sur un planisphere ou un
 * plan ») mais jamais construite : trois competences l'attendaient sans
 * pouvoir se jouer - 4204 foyers de peuplement, 4205 principales villes du
 * monde, 4208 zones climatiques, montagnes, deserts et fleuves.
 *
 * DEUX DES TROIS SONT REPARTIES le soir meme, ecartees par l’enseignante : 4205
 * (placer des villes) puis 4208 (montagnes, deserts, fleuves), trop
 * difficiles pour ses eleves. Ce que ces deux jeux demandaient de trop est
 * la PRECISION, pas le planisphere : leurs tolerances descendaient a 9 et
 * 10 degres. Seule reste 4204, qui vise des REGIONS a 16 a 25 degres.
 *
 * 4208 N'EST PAS PERDUE POUR AUTANT : elle est reprise en `tri` le meme jour
 * (banque geographie-types-relief), ou l'on reconnait un type de lieu au
 * lieu de le pointer. Une competence qui resiste a une mecanique n'est donc
 * pas forcement injouable - elle peut demander une autre mecanique.
 *
 * REGLE POUR LA PROCHAINE BANQUE `carte` : sous ~15 degres de tolerance, on
 * demande de pointer et non de situer, et le cycle 2 ne suit pas. Viser
 * large, ou ne pas viser. Et si une seule banque devait rester longtemps,
 * se demander si cette mecanique merite encore son moteur, son API et son
 * fond de carte de 58 Ko - pour l'instant oui, elle porte 4204.
 *
 * POURQUOI CE N'EST PAS UN QCM DEGUISE. Cliquer une zone parmi trois serait
 * un choix habille en carte, ce que la regle du 01/09/2026 interdit. Ici
 * l'eleve pose un repere N'IMPORTE OU sur le planisphere : l'espace des
 * reponses est continu, il n'y a rien a eliminer, il faut situer.
 *
 * LES COORDONNEES SONT REELLES, jamais relevees a la main sur l'image. Le
 * fond de carte (assets/planisphere.svg, Natural Earth, domaine public) est
 * en projection equirectangulaire : un degre y vaut exactement deux unites,
 * donc x = (longitude + 180) * 2 et y = (90 - latitude) * 2. Une ville se
 * place depuis sa latitude et sa longitude, et rien d'autre.
 *
 * LA TOLERANCE EST EN DEGRES, ET MESUREE A PLAT. Sur un planisphere, l'eleve
 * vise ce qu'il voit : la distance qui compte est donc celle a l'ecran, pas
 * une distance a la surface du globe. Un ecart en degres sur la carte plate
 * est exactement cela - ce n'est pas une approximation de la distance reelle,
 * c'est la bonne mesure pour ce geste. Chaque lieu porte la sienne : Paris se
 * vise plus precisement que le Sahara.
 */
function fgQuestionsCarte(array $banque, string $graine): array
{
    $lieux = array_values($banque['lieux']);
    fgMelangerStable($lieux, $graine . '|carte|ordre');
    // On PUISE dans la banque, on ne la deroule pas : c'est ce qui fait
    // qu'ajouter des lieux varie les parties au lieu de les rallonger.
    $lieux = array_slice($lieux, 0, FG_QUESTIONS_CARTE);
    // Du plus facile au plus difficile : la tolerance, deja ecrite lieu par
    // lieu, EST la mesure - viser l'Antarctique a 25 degres pres est plus
    // simple que viser une region a 16. Elle est donc triee a l'envers des
    // deux autres mecaniques : grande tolerance d'abord.
    $lieux = fgOrdonnerParDifficulte($lieux, static fn(array $lieu): float => -(float)($lieu['tolerance'] ?? 10));

    $questions = array();
    foreach ($lieux as $lieu) {
        $questions[] = array(
            'question' => (string)$lieu['nom'],
            'consigne' => $banque['consigne'],
            'lat' => (float)$lieu['lat'],
            'lon' => (float)$lieu['lon'],
            // Sans tolerance ecrite, 10 degres : environ mille kilometres a
            // l'equateur, soit un doigt d'enfant sur un planisphere affiche
            // en pleine largeur. Assez large pour ne pas punir la motricite,
            // assez etroit pour qu'un continent voisin compte faux.
            'tolerance' => (float)($lieu['tolerance'] ?? 10),
            'explication' => (string)($lieu['explication'] ?? ($lieu['nom'] . '.')),
        );
    }
    return $questions;
}

/**
 * Verifie le repere pose par l'eleve. Voir fgQuestionsCarte pour pourquoi la
 * distance se mesure a plat, en degres de la carte.
 */
function fgCarteValider(array $question, float $lat, float $lon): array
{
    // Hors du planisphere : un repere qui n'existe pas ne peut pas etre juste.
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        return array('correct' => false, 'ecart' => null);
    }
    $dLat = $lat - (float)$question['lat'];
    $dLon = $lon - (float)$question['lon'];
    // Le planisphere se recolle a lui-meme : cliquer juste a l'ouest de la
    // ligne de changement de date est a deux pas de l'extreme est, pas a
    // 360 degres. Sans ce repli, un lieu proche du bord serait invalidable.
    if ($dLon > 180) { $dLon -= 360; }
    if ($dLon < -180) { $dLon += 360; }
    $ecart = sqrt($dLat * $dLat + $dLon * $dLon);
    return array('correct' => $ecart <= (float)$question['tolerance'], 'ecart' => $ecart);
}

/**
 * Une grille de deplacement : programmer un robot pour l'amener au drapeau.
 *
 * HUITIEME MECANIQUE, ecrite le 06/09/2026. Meme signal que la boutique,
 * l'horloge et la droite : la competence 4023 etait marquee « ne se joue pas »
 * avec la raison « necessite une grille de deplacement », note « aucune
 * mecanique graphique de plateau n'existe encore ». Ce n'est pas la competence
 * qui resistait, c'est l'absence d'un objet manipulable.
 *
 * CE QUI SE CONSTRUIT ICI EST UNE SUITE D'ORDRES, pas une case. L'eleve
 * n'elimine rien : il ecrit `avance, avance, droite, avance`, puis regarde son
 * programme s'executer. C'est la definition meme d'un codage de deplacement, et
 * c'est ce qui empeche d'en faire un QCM deguise (regle du 01/09/2026).
 *
 * LE ROBOT SE BLOQUE, IL NE TOMBE PAS. Un ordre qui mene dans un mur ou hors de
 * la grille est simplement refuse, le robot reste ou il est et le programme
 * continue. Pour un CE1, un echec immediat au premier ordre fautif punit une
 * faute de comptage comme une faute de raisonnement ; se cogner et rester sur
 * place se voit tout aussi bien et laisse finir sa phrase.
 *
 * LA GRILLE N'A PAS DE COORDONNEES AFFICHEES, et c'est voulu : l'attendu du
 * cycle 2 est de coder un deplacement relatif (avance, tourne), pas de lire un
 * repere. Ecrire A3 ou (2,4) serait une autre competence.
 */
const FG_QUESTIONS_GRILLE = 5;

/** Les quatre orientations, dans l'ordre horaire : tourner a droite, c'est +1. */
const FG_GRILLE_CAPS = array('nord', 'est', 'sud', 'ouest');

/** Un programme plus long que ca n'est plus un raisonnement, c'est du hasard. */
const FG_GRILLE_ORDRES_MAX = 40;

function fgGrilleIndexCap(string $cap): int
{
    $index = array_search($cap, FG_GRILLE_CAPS, true);
    return $index === false ? 0 : (int)$index;
}

/**
 * Rejoue un programme sur la grille et rend l'etat final. Le meme code sert a
 * corriger la reponse de l'eleve ET a mesurer la difficulte d'un parcours :
 * deux usages, une seule regle du jeu, donc aucun risque qu'ils divergent.
 *
 * @return array x, y, cap, et `cogne` : le robot a-t-il bute au moins une fois.
 */
function fgGrilleExecuter(array $question, array $programme): array
{
    $vecteurs = array(array(0, -1), array(1, 0), array(0, 1), array(-1, 0));
    $x = (int)$question['depart'][0];
    $y = (int)$question['depart'][1];
    $cap = fgGrilleIndexCap((string)$question['cap']);
    $taille = (int)$question['taille'];
    $cogne = false;

    foreach ($programme as $ordre) {
        if ($ordre === 'gauche') {
            $cap = ($cap + 3) % 4;
        } elseif ($ordre === 'droite') {
            $cap = ($cap + 1) % 4;
        } elseif ($ordre === 'avance') {
            $nx = $x + $vecteurs[$cap][0];
            $ny = $y + $vecteurs[$cap][1];
            $dehors = $nx < 0 || $ny < 0 || $nx >= $taille || $ny >= $taille;
            $mur = false;
            foreach ($question['murs'] as $m) {
                if ((int)$m[0] === $nx && (int)$m[1] === $ny) { $mur = true; break; }
            }
            if ($dehors || $mur) { $cogne = true; } else { $x = $nx; $y = $ny; }
        }
        // Un ordre inconnu est ignore plutot que refuse : le client n'en envoie
        // pas, et un programme a demi rejoue serait plus difficile a expliquer
        // qu'un ordre sans effet.
    }
    return array('x' => $x, 'y' => $y, 'cap' => $cap, 'cogne' => $cogne);
}

/**
 * Le plus court programme qui atteint le drapeau, par parcours en largeur sur
 * les etats (case + orientation). Sert a deux choses, et c'est pour la seconde
 * qu'il est ecrit : classer les parcours du plus simple au plus difficile, et
 * PROUVER qu'un parcours est resoluble - un test le rejoue sur chaque item de
 * chaque banque, ce qu'aucune relecture a l'oeil ne garantit.
 *
 * @return int|null nombre d'ordres, ou null si le drapeau est inatteignable.
 */
function fgGrilleLongueurMinimale(array $question): ?int
{
    $taille = (int)$question['taille'];
    $depart = fgGrilleIndexCap((string)$question['cap'])
            . '|' . (int)$question['depart'][0] . '|' . (int)$question['depart'][1];
    $file = array(array((int)$question['depart'][0], (int)$question['depart'][1],
                        fgGrilleIndexCap((string)$question['cap']), 0));
    $vus = array($depart => true);
    $vecteurs = array(array(0, -1), array(1, 0), array(0, 1), array(-1, 0));

    while ($file) {
        list($x, $y, $cap, $pas) = array_shift($file);
        if ($x === (int)$question['cible'][0] && $y === (int)$question['cible'][1]) {
            return $pas;
        }
        $suivants = array(
            array($x, $y, ($cap + 1) % 4),
            array($x, $y, ($cap + 3) % 4),
        );
        $nx = $x + $vecteurs[$cap][0];
        $ny = $y + $vecteurs[$cap][1];
        $mur = false;
        foreach ($question['murs'] as $m) {
            if ((int)$m[0] === $nx && (int)$m[1] === $ny) { $mur = true; break; }
        }
        if ($nx >= 0 && $ny >= 0 && $nx < $taille && $ny < $taille && !$mur) {
            $suivants[] = array($nx, $ny, $cap);
        }
        foreach ($suivants as $s) {
            $cle = $s[2] . '|' . $s[0] . '|' . $s[1];
            if (!isset($vus[$cle])) {
                $vus[$cle] = true;
                $file[] = array($s[0], $s[1], $s[2], $pas + 1);
            }
        }
    }
    return null;
}

function fgQuestionsGrille(array $banque, string $graine): array
{
    $parcours = array_values($banque['parcours']);
    fgMelangerStable($parcours, $graine . '|grille|ordre');
    // On puise, on ne deroule pas : enrichir la banque doit varier la partie,
    // pas l'allonger. Meme regle que la carte et la droite.
    $parcours = array_slice($parcours, 0, FG_QUESTIONS_GRILLE);

    $taille = (int)($banque['taille'] ?? 6);
    $questions = array();
    foreach ($parcours as $p) {
        // L'intitule de la manche DECRIT LE PLATEAU au lieu de repeter la
        // consigne : sans lui, le titre disait exactement la meme phrase que la
        // ligne au-dessus. Il est calcule et non ecrit dans la banque, donc il
        // ne peut pas mentir sur le nombre de murs - et il n'en dit pas plus :
        // ou passer reste a trouver.
        $nbMurs = count($p['murs'] ?? array());
        $enonce = $nbMurs === 0 ? 'Le chemin est libre.'
                : ($nbMurs === 1 ? 'Un mur barre le passage.'
                                 : $nbMurs . ' murs à contourner.');
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'enonce_grille' => $enonce,
            'taille' => $taille,
            'depart' => array((int)$p['depart'][0], (int)$p['depart'][1]),
            'cap' => (string)($p['cap'] ?? 'nord'),
            'cible' => array((int)$p['cible'][0], (int)$p['cible'][1]),
            'murs' => array_map(static function ($m) { return array((int)$m[0], (int)$m[1]); },
                                $p['murs'] ?? array()),
        );
    }

    // Du plus simple au plus difficile : la longueur du plus court programme.
    // Trier APRES le tirage, jamais avant - sinon la partie ne varierait plus.
    $questions = fgOrdonnerParDifficulte($questions, static function (array $q): float {
        $longueur = fgGrilleLongueurMinimale($q);
        // Un parcours insoluble ne doit pas se glisser en tete comme s'il etait
        // facile : il part au fond, et le test de banques le refuse de toute
        // facon avant qu'il n'arrive jusqu'ici.
        return $longueur === null ? 999.0 : (float)$longueur;
    });

    foreach ($questions as $i => $q) {
        $longueur = fgGrilleLongueurMinimale($q);
        $questions[$i]['explication'] = $longueur === null
            ? 'Le drapeau n’était pas atteignable.'
            : 'Le chemin le plus court demande ' . $longueur . ' ordre' . ($longueur > 1 ? 's' : '') . '.';
    }
    return $questions;
}

/**
 * Verifie le programme ecrit par l'eleve : on le rejoue, et on regarde ou le
 * robot s'arrete. Le nombre d'ordres n'entre PAS dans le verdict - un chemin
 * plus long qu'il ne faut reste un chemin qui arrive, et exiger l'optimal
 * transformerait un codage de deplacement en concours.
 */
function fgGrilleValider(array $question, array $programme): array
{
    if (count($programme) > FG_GRILLE_ORDRES_MAX) {
        return array('correct' => false);
    }
    $fin = fgGrilleExecuter($question, $programme);
    return array(
        'correct' => $fin['x'] === (int)$question['cible'][0] && $fin['y'] === (int)$question['cible'][1],
        'cogne' => $fin['cogne'],
    );
}

/**
 * Le partage : couper une bande ou un disque, puis en colorier une partie.
 *
 * NEUVIEME MECANIQUE, ecrite le 06/09/2026. Meme signal que les precedentes :
 * 3916 etait marquee « ne se joue pas », raison « necessite une representation
 * visuelle du partage », note « camembert ou bande divisee ; aucune mecanique
 * graphique de fractions actuelle ». 3917 tombait avec elle - « notion trop
 * ponctuelle, liee aux fractions qui manquent de support visuel ».
 *
 * DEUX GESTES, ET C'EST TOUT L'EXERCICE. L'eleve choisit d'abord EN COMBIEN DE
 * PARTS il coupe (le denominateur), puis COMBIEN il en colorie (le numerateur).
 * Une bande deja coupee ne ferait travailler que la moitie de la notion, et
 * c'est justement la moitie la plus facile.
 *
 * CE N'EST PAS UN QCM : l'eleve compose sa reponse a partir d'un support vierge,
 * il n'elimine rien. Conforme a la regle du 01/09/2026.
 *
 * QUELLES PARTS SONT COLORIEES N'A AUCUNE IMPORTANCE : trois quarts, ce sont
 * trois parts sur quatre, ou qu'elles soient. Exiger les trois premieres serait
 * ajouter une regle qui n'est pas dans la notion.
 *
 * LA FRACTION EST L'ENONCE, PAS LA REPONSE : numerateur et denominateur partent
 * donc au client, comme la cible du compte est bon. Ce qui se construit, c'est
 * le dessin.
 *
 * DEUX SUPPORTS, DEUX BANQUES. Un eleve qui ne reconnait 3/4 que sur une bande
 * n'a pas compris la fraction, il a reconnu un dessin. Le disque n'est donc pas
 * une variante decorative, c'est la preuve que la notion a ete comprise.
 */
const FG_QUESTIONS_PARTAGE = 6;

function fgQuestionsPartage(array $banque, string $graine): array
{
    $fractions = array_values($banque['fractions']);
    fgMelangerStable($fractions, $graine . '|partage|ordre');
    $fractions = array_slice($fractions, 0, FG_QUESTIONS_PARTAGE);

    // Du plus simple au plus difficile : d'abord le nombre de parts, puis le
    // numerateur. Couper en deux se voit sans compter ; couper en huit demande
    // de partager regulierement, et 3/8 se compte apres coup.
    $fractions = fgOrdonnerParDifficulte($fractions, static function (array $f): float {
        return (float)($f[1] * 10 + $f[0]);
    });

    $support = (string)($banque['support'] ?? 'bande');
    $questions = array();
    foreach ($fractions as $f) {
        $numerateur = (int)$f[0];
        $denominateur = (int)$f[1];
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'enonce_partage' => 'Colorie ' . $numerateur . '/' . $denominateur,
            'support' => $support,
            'numerateur' => $numerateur,
            'denominateur' => $denominateur,
            // Le vocabulaire de 3917 est dit dans l'explication, la ou il sert :
            // apres coup, une fois le dessin fait, et jamais comme une lecon
            // avant l'exercice.
            'explication' => 'Le ' . $denominateur . ' du bas dit en combien de parts on coupe (le dénominateur), '
                           . 'le ' . $numerateur . ' du haut combien on en colorie (le numérateur).',
        );
    }
    return $questions;
}

/**
 * Verifie le dessin de l'eleve : le bon nombre de parts, et le bon nombre de
 * parts coloriees. Lesquelles, on s'en moque - voir fgQuestionsPartage.
 */
function fgPartageValider(array $question, int $parts, int $coloriees): array
{
    $bonDecoupage = $parts === (int)$question['denominateur'];
    $bonColoriage = $coloriees === (int)$question['numerateur'];
    return array(
        'correct' => $bonDecoupage && $bonColoriage,
        // Distinguer les deux erreurs permet de le dire a l'eleve : s'etre
        // trompe de decoupage n'est pas la meme faute que d'avoir mal compte.
        'decoupage' => $bonDecoupage,
        'coloriage' => $bonColoriage,
    );
}

/**
 * Le probleme du jour : un enonce court, un calcul a construire.
 *
 * DIXIEME MECANIQUE, ecrite le 06/09/2026. Cinq competences l'attendaient -
 * 3935 a 3939 - toutes bloquees par la meme raison : « necessite un enonce de
 * probleme ; contexte narratif, au-dela d'un simple calcul ».
 *
 * CE N'EST PAS UN TROU DE MECANIQUE, C'EST UN TROU DE CONTENU, et ca change la
 * nature du travail : le rendu multi-lignes et la validation d'un calcul
 * construit existent depuis le 05/09 (compte est bon). Ce qu'il fallait ecrire,
 * ce sont les ENONCES.
 *
 * CE QUI DISTINGUE CE JEU DU COMPTE EST BON : la cible n'est pas donnee. C'est
 * meme tout l'exercice - lire l'enonce pour savoir ce qu'on cherche. Le compte
 * est bon montre le nombre a atteindre ; ici, le trouver EST la question.
 *
 * L'ELEVE CONSTRUIT LE CALCUL, il ne tape pas un resultat. On voit donc ce
 * qu'il a compris de l'enonce, et pas seulement s'il tombe juste - c'est ce qui
 * fait la difference entre corriger un probleme et corriger une operation.
 *
 * LES NOMBRES PROPOSES SONT CEUX DE L'ENONCE, et rien d'autre : impossible
 * d'ecrire la reponse directement, il faut passer par les donnees. Un nombre
 * peut servir plusieurs fois - six boites de quatre se comptent aussi
 * 4+4+4+4+4+4, et refuser cette ecriture punirait une strategie correcte.
 */
const FG_QUESTIONS_PROBLEME = 5;

function fgQuestionsProbleme(array $banque, string $graine): array
{
    $problemes = array_values($banque['problemes']);
    fgMelangerStable($problemes, $graine . '|probleme|ordre');
    $problemes = array_slice($problemes, 0, FG_QUESTIONS_PROBLEME);

    // Du plus simple au plus difficile : le nombre d'etapes attendues, puis la
    // quantite de donnees a trier dans l'enonce. Un probleme a deux etapes
    // demande de garder un resultat intermediaire en tete, ce qui est la vraie
    // marche du cycle 2.
    $problemes = fgOrdonnerParDifficulte($problemes, static function (array $p): float {
        return (float)(((int)($p['etapes'] ?? 1)) * 10 + count($p['nombres']));
    });

    $questions = array();
    foreach ($problemes as $p) {
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'enonce_probleme' => (string)$p['texte'],
            // `nombres_enonce` et non `nombres` : cette derniere cle est celle du
            // compte est bon, et fgQuestionsPourLeClient s'en sert pour
            // reconnaitre sa mecanique. Deux jeux differents ne doivent pas se
            // confondre sur un nom de champ.
            'nombres_enonce' => array_map('intval', $p['nombres']),
            'operateurs' => $banque['operateurs'] ?? array('+', '-'),
            'reponse' => (int)$p['reponse'],
            'explication' => (string)$p['explication'],
        );
    }
    return $questions;
}

/**
 * Rejoue le calcul construit par l'eleve et compare le total attendu.
 *
 * Les etapes arrivent sous la forme [{op, n}, ...], la premiere sans operateur.
 * On ne juge PAS le chemin : deux calculs differents qui arrivent au bon
 * resultat sont deux raisonnements valables, et trancher entre eux demanderait
 * de connaitre celui que l'eleve avait en tete.
 */
function fgProblemeValider(array $question, array $etapes): array
{
    if (!$etapes) {
        return array('correct' => false, 'total' => null);
    }
    $autorises = $question['nombres_enonce'];
    $total = null;
    foreach ($etapes as $etape) {
        $n = isset($etape['n']) ? (int)$etape['n'] : null;
        $op = isset($etape['op']) ? (string)$etape['op'] : '';
        // Seuls les nombres de l'enonce entrent dans le calcul : sans ce
        // controle, un envoi bricole poserait directement la reponse.
        if ($n === null || !in_array($n, $autorises, true)) {
            return array('correct' => false, 'total' => null);
        }
        if ($total === null) {
            $total = $n;
            continue;
        }
        if ($op === '+') { $total += $n; }
        elseif ($op === '-') { $total -= $n; }
        elseif ($op === '*') { $total *= $n; }
        else { return array('correct' => false, 'total' => null); }
    }
    return array('correct' => $total === (int)$question['reponse'], 'total' => $total);
}

/**
 * Le son qui manque : entendre un mot, ecrire la graphie qui lui manque.
 *
 * DORMANTE PAR DECISION, A NE PAS REACTIVER TELLE QUELLE. Le jeu a ete retire
 * le 06/09/2026 parce qu'il faisait doublon avec Clic & Mots, et le moteur
 * garde « pour plus tard » (docs/HISTORIQUE.md). Regle de perimetre fixee le
 * 21/09/2026 (arbitrage DEC-01, DEC-13) : la boucle « mot entendu -> sa
 * graphie », en production comme en choix, appartient a Clic & Mots. Une
 * banque `graphie` n'est donc acceptable que pour un usage qui ne reproduit
 * pas cette boucle. Voir docs/implementation-fastgames-arbitrage-v1-2026-09-21/.
 *
 * ONZIEME MECANIQUE, ecrite le 06/09/2026. Trois competences l'attendaient -
 * 3793, 3794, 3795 - toutes bloquees par « necessite un support audio ;
 * correspondance graphie-son, aucune mecanique audio actuelle ».
 *
 * LA MECANIQUE `ecoute` NE COUVRAIT PAS CE BESOIN, malgre son nom : ses sept
 * banques sont des BRUITS - animaux, nature, quotidien - jamais des mots
 * prononces. Le trou etait donc reel.
 *
 * D'OU VIENT LE SON, ET POURQUOI. Chaque mot peut porter un `fichier` mp3
 * depose dans assets/son/, comme la mecanique `ecoute`. Quand il n'y en a pas -
 * c'est le cas aujourd'hui de tous - le client fait parler le navigateur.
 * C'EST EXACTEMENT LE MOTIF DEJA RETENU PAR CLIC & MOTS (voir son
 * src/lib/speech.ts) : fichiers pre-generes en priorite, `speechSynthesis` en
 * repli. On adopte le meme ordre, avec les fichiers encore vides : rien a
 * heberger pour livrer, et poser un mp3 plus tard suffit a l'ameliorer, sans
 * toucher au code.
 *
 * LA LIMITE EST CONNUE ET ECRITE : Clic & Mots a mesure que `speechSynthesis`
 * avale les schwas (« ch'vauchee »). Les mots de ces banques sont donc choisis
 * courts et nets, et aucun ne repose sur une syllabe muette.
 *
 * CE N'EST PAS UN QCM : le clavier propose QUATORZE graphies, dont beaucoup ne
 * conviennent jamais. L'eleve compose, il n'elimine pas entre trois choix.
 *
 * LE TROU PORTE TOUJOURS SUR UNE GRAPHIE COMPLEXE - ou, oi, on, an, in, ch,
 * eau - jamais sur une consonne isolee : c'est exactement ce que visent ces
 * trois competences, et une consonne se devine sans ecouter.
 */
const FG_QUESTIONS_GRAPHIE = 6;

function fgQuestionsGraphie(array $banque, string $graine): array
{
    $mots = array_values($banque['mots']);
    fgMelangerStable($mots, $graine . '|graphie|ordre');
    $mots = array_slice($mots, 0, FG_QUESTIONS_GRAPHIE);

    // Du plus simple au plus difficile : la longueur de la graphie manquante.
    // Une voyelle composee de deux lettres (ou, oi) s'entend d'un bloc ; « eau »
    // demande de savoir qu'un seul son s'ecrit avec trois lettres.
    $mots = fgOrdonnerParDifficulte($mots, static function (array $m): float {
        return (float)mb_strlen($m['manque']);
    });

    $questions = array();
    foreach ($mots as $m) {
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'avant' => (string)$m['avant'],
            'apres' => (string)$m['apres'],
            'graphies' => $banque['graphies'],
            // Le mot entier ne part PAS au client : il est precisement ce que
            // l'eleve doit reconstituer. Seul le son le lui donne.
            'mot' => (string)$m['mot'],
            'manque' => (string)$m['manque'],
            'fichier' => isset($m['fichier']) ? (string)$m['fichier'] : null,
            'explication' => 'On écrit « ' . $m['mot'] . ' » : il manquait « ' . $m['manque'] . ' ».',
        );
    }
    return $questions;
}

/**
 * Verifie la graphie composee. Comparaison stricte : l'eleve ne tape pas, il
 * assemble des graphies proposees, il ne peut donc pas se tromper d'accent ou
 * d'espace - normaliser ici masquerait une vraie erreur au lieu d'aider.
 */
function fgGraphieValider(array $question, string $composee): array
{
    return array('correct' => $composee === (string)$question['manque']);
}

/**
 * Le graphique : construire un diagramme en barres a partir d'une enquete.
 *
 * ONZIEME MECANIQUE, ecrite le 07/09/2026. Deux competences l'attendaient -
 * 4034 et 4035 - dont la seconde portait encore la formulation qui a designe
 * la boutique, l'horloge, la droite graduee et la carte : « aucune mecanique
 * de lecture de graphique actuelle ». CINQUIEME FOIS que cette phrase exacte
 * annonce le prochain jeu a ecrire ; ce n'est plus une coincidence, c'est un
 * signal a chercher volontairement dans data/competences-jouables.php.
 *
 * 4034 TOMBE PAR LE MEME RAISONNEMENT QUE LA MONNAIE ET L'HEURE. Elle etait
 * classee « production graphique - dessiner un tableau ou un diagramme en
 * barres », donc rangee avec les gestes au crayon. Mais UNE BARRE QU'ON TIRE
 * N'EST PAS UN DESSIN : sa hauteur EST la donnee, pas un trace de main. Le
 * meme argument avait ouvert « manipulation de pieces et billets » (boutique)
 * et « necessite un cadran d'horloge ».
 *
 * CE N'EST PAS UN QCM DEGUISE : la hauteur d'une barre se choisit dans un
 * espace continu, il n'y a rien a eliminer. Regle du 01/09/2026.
 *
 * TOUTES LES BARRES COMPTENT POUR UN SEUL POINT. Un graphique a moitie juste
 * ne dit pas la moitie de la verite sur l'enquete : il est faux. Compter
 * barre par barre transformerait la manche en quatre questions independantes
 * et recompenserait un eleve qui en aurait reussi une par hasard.
 */
const FG_QUESTIONS_GRAPHIQUE = 5;

function fgQuestionsGraphique(array $banque, string $graine): array
{
    $enquetes = array_values($banque['enquetes']);
    fgMelangerStable($enquetes, $graine . '|graphique|ordre');
    $enquetes = array_slice($enquetes, 0, FG_QUESTIONS_GRAPHIQUE);

    // Du plus simple au plus difficile : le nombre de barres d'abord, la plus
    // haute valeur ensuite. Trois barres qui montent a 5 se posent d'un coup
    // d'oeil ; cinq barres qui vont jusqu'a 10 demandent de tenir l'echelle
    // sur toute la largeur.
    $enquetes = fgOrdonnerParDifficulte($enquetes, static function (array $e): float {
        $valeurs = array_map(static function (array $s): int { return (int)$s[1]; }, $e['series']);
        return (float)(count($e['series']) * 20 + max($valeurs));
    });

    $max = (int)($banque['max'] ?? 10);
    $questions = array();
    foreach ($enquetes as $e) {
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'enonce_graphique' => (string)$e['titre'],
            'max_graphique' => $max,
            // Libelles et valeurs partent au client : ce sont LES DONNEES, donc
            // l'enonce. Ce qui se construit, c'est le dessin - et lui n'est
            // nulle part dans ce qu'on envoie.
            'series_graphique' => array_map(static function (array $s): array {
                return array('libelle' => (string)$s[0], 'valeur' => (int)$s[1]);
            }, $e['series']),
            'explication' => 'Chaque barre monte jusqu’au nombre de l’enquête : '
                           . implode(', ', array_map(static function (array $s): string {
                               return $s[0] . ' ' . $s[1];
                           }, $e['series'])) . '.',
        );
    }
    return $questions;
}

/**
 * Compare le graphique construit aux donnees de l'enquete. Egalite stricte sur
 * chaque barre - voir fgQuestionsGraphique pour le choix de ne pas donner de
 * point partiel.
 */
function fgGraphiqueValider(array $question, array $hauteurs): array
{
    $attendues = array_map(static function (array $s): int { return (int)$s['valeur']; },
                           $question['series_graphique']);
    if (count($hauteurs) !== count($attendues)) {
        return array('correct' => false, 'fausses' => count($attendues));
    }
    $fausses = 0;
    foreach ($attendues as $i => $valeur) {
        if ((int)$hauteurs[$i] !== $valeur) {
            $fausses++;
        }
    }
    return array('correct' => $fausses === 0, 'fausses' => $fausses);
}

/**
 * La regle graduee : mesurer, comparer, estimer une longueur.
 *
 * DOUZIEME MECANIQUE, ecrite le 07/09/2026. Quatre competences l'attendaient -
 * 3965 a 3968 - et elles etaient bloquees pour DEUX raisons distinctes, dont
 * une qui avait pourri sur pied :
 *
 *   3965 « geste de mesure - regle graduee physique »
 *   3966 « necessite une comparaison visuelle - deux segments dessines »
 *   3967 et 3968 « estimation par plage de valeurs - pas une reponse unique »
 *
 * LA RAISON DE 3967 ET 3968 ETAIT PERIMEE. « Pas une reponse unique » etait un
 * obstacle reel tant qu'aucun moteur ne savait valider autrement que par
 * egalite. Or `droite` et `carte` valident par TOLERANCE depuis le 06/09 :
 * deux competences restaient donc fermees par un motif que notre propre code
 * avait deja dementi. A retenir comme methode - quand une mecanique nouvelle
 * est ecrite, RELIRE LES RAISONS QU'ELLE REND CADUQUES. Personne ne le fait
 * spontanement, parce que la raison est ecrite loin du moteur qui la dement.
 *
 * TROIS MODES, UNE SEULE REPONSE : un entier en centimetres. Uniformiser la
 * reponse evite trois chemins de validation la ou un seul suffit.
 *   - mesurer : un segment, la regle est fournie, tolerance nulle ;
 *   - ecart   : deux segments, la regle est fournie, on demande DE COMBIEN
 *               l'un depasse l'autre ;
 *   - estimer : un segment, AUCUNE regle, tolerance depuis la banque.
 *
 * POURQUOI « ecart » ET NON « lequel est le plus long ». Designer le plus long
 * de deux segments est un choix binaire, donc un QCM habille - exactement ce
 * que la regle du 01/09/2026 interdit. Demander l'ECART fait construire un
 * nombre, et travaille la comparaison ET la soustraction.
 *
 * CE QUE LE SERVEUR NE PEUT PAS VOIR, ET POURQUOI CE N'EST PAS GRAVE :
 * l'alignement du zero de la regle ne lui est jamais envoye. Il n'a pas a
 * l'etre - un eleve qui pose mal le zero LIT une mauvaise longueur, et se
 * trompe donc naturellement. Ajouter un controle d'alignement reviendrait a
 * corriger le geste plutot que sa consequence, et a faire confiance a une
 * mesure calculee par le navigateur.
 */
const FG_QUESTIONS_REGLE = 6;

function fgQuestionsRegle(array $banque, string $graine): array
{
    $items = array_values($banque['longueurs']);
    fgMelangerStable($items, $graine . '|regle|ordre');
    $items = array_slice($items, 0, FG_QUESTIONS_REGLE);

    $mode = (string)($banque['mode'] ?? 'mesurer');
    // Du plus simple au plus difficile : la longueur a lire. Un segment court
    // se compte graduation par graduation ; un segment long oblige a lire le
    // nombre plutot qu'a compter.
    $items = fgOrdonnerParDifficulte($items, static function ($item) use ($mode): float {
        return $mode === 'ecart'
            ? (float)max((int)$item[0], (int)$item[1])
            : (float)(is_array($item) ? (int)$item[0] : (int)$item);
    });

    $tolerance = (int)($banque['tolerance'] ?? 0);
    $questions = array();
    foreach ($items as $item) {
        if ($mode === 'ecart') {
            $a = (int)$item[0];
            $b = (int)$item[1];
            $questions[] = array(
                'consigne' => $banque['consigne'],
                'enonce_regle' => 'De combien le segment bleu dépasse-t-il le vert ?',
                'mode_regle' => 'ecart',
                'segments' => array($a, $b),
                'regle_cm' => (int)($banque['regle_cm'] ?? 15),
                'longueur_cm' => abs($a - $b),
                'tolerance_cm' => $tolerance,
                'explication' => 'Le bleu mesure ' . $a . ' cm, le vert ' . $b . ' cm : '
                               . $a . ' − ' . $b . ' = ' . abs($a - $b) . ' cm.',
            );
            continue;
        }
        $cm = is_array($item) ? (int)$item[0] : (int)$item;
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'enonce_regle' => $mode === 'estimer'
                ? 'À ton avis, combien mesure ce segment ?'
                : 'Mesure le segment. Place d’abord le zéro de la règle.',
            'mode_regle' => $mode,
            'segments' => array($cm),
            'regle_cm' => (int)($banque['regle_cm'] ?? 15),
            'longueur_cm' => $cm,
            'tolerance_cm' => $tolerance,
            // L'explication ne parle PAS de la tolerance : elle est completee
            // apres coup par fgEnregistrerRegle, qui sait si l'eleve est tombe
            // pile. « A 1 cm pres, c'etait bon » sous une reponse exacte laisse
            // croire qu'on a ete indulgent - signale par le responsable technique le 07/09/2026,
            // qui avait justement repondu la valeur juste.
            'explication' => $mode === 'estimer'
                ? 'Il mesure ' . $cm . ' cm.'
                : 'Il mesure ' . $cm . ' cm : le zéro au début, on lit la graduation du bout.',
        );
    }
    return $questions;
}

/**
 * Valide une longueur lue ou estimee. La tolerance vient de la banque et vaut
 * zero en mode « mesurer » : une regle graduee donne une reponse exacte, s'en
 * accommoder a un centimetre pres reviendrait a ne plus rien demander.
 */
function fgRegleValider(array $question, int $cm): array
{
    $ecart = abs($cm - (int)$question['longueur_cm']);
    return array(
        'correct' => $ecart <= (int)$question['tolerance_cm'],
        'ecart' => $ecart,
    );
}

/**
 * La balance a deux plateaux : construire une masse.
 *
 * TREIZIEME MECANIQUE, ecrite le 07/09/2026. Trois competences l'attendaient -
 * 3969, 3972 et 3973 - dont l'une, la aussi, par une raison periment par le
 * moteur `droite` (voir fgQuestionsRegle) :
 *
 *   3969 « manipulation physique - soupeser ou peser des objets »
 *   3972 « necessite une comparaison visuelle - balance a deux plateaux dessinee »
 *   3973 « estimation par plage de valeurs - masses de reference approximatives »
 *
 * FAMILLE DE LA BOUTIQUE, et c'est ce qui a decide de l'ecrire : on construit
 * une SOMME avec des valeurs discretes, exactement comme on rend la monnaie.
 * Aucune invention, un moteur deja eprouve comme modele.
 *
 * LE FLEAU QUI PENCHE N'EST PAS UN INDICE DONNE PAR LE JEU. C'est ce que fait
 * une vraie balance, et c'est meme precisement la competence : lire
 * l'inclinaison pour savoir s'il faut ajouter ou retirer. Le cacher rendrait
 * l'exercice plus difficile SANS travailler la notion visee.
 *
 * DEUX MODES SELON LA BANQUE. « exact » demande l'equilibre au gramme pres
 * (3972) ; « estimer » demande d'approcher la masse d'un objet connu, avec
 * tolerance (3973 - une pomme pese environ 200 g, pas 200 g pile).
 */
const FG_QUESTIONS_BALANCE = 5;

/**
 * La masse la plus proche de $cible qu'un eleve puisse REELLEMENT poser.
 *
 * POURQUOI CE CALCUL EXISTE. En mode « estimer », la tolerance vaut un
 * pourcentage de la masse : elle retrecit avec l'objet, alors que le jeu de
 * masses, lui, garde son plus petit poids. Sous une certaine masse, plus aucune
 * combinaison ne tombe dans la tolerance et la manche devient insoluble.
 * Mesure du 09/09/2026 sur grandeurs-masses-estimer : la fraise (20 g,
 * tolerance 6 g) et la souris (30 g, tolerance 9 g) etaient hors d'atteinte,
 * et environ 45 % des parties en contenaient au moins une (FG-AUDIT-009).
 *
 * ON NE DEVINE RIEN : chaque masse est disponible en plusieurs exemplaires, les
 * totaux possibles se construisent donc de proche en proche jusqu'a la borne
 * haute utile. Ce n'est pas un multiple du plus petit poids : un jeu de masses
 * futur pourrait ne pas contenir son propre pas.
 *
 * @return int|null Le total posable le plus proche dans la tolerance, ou null
 *                  si aucun n'y tombe - la manche est alors injouable.
 */
function fgBalanceMasseAtteignable(int $cible, array $masses, int $tolerance): ?int
{
    $masses = array_values(array_filter(array_map('intval', $masses), static fn(int $m): bool => $m > 0));
    if ($cible < 0 || $tolerance < 0 || !$masses) {
        return null;
    }
    $haut = $cible + $tolerance;
    // Garde-fou : une banque qui viserait des masses absurdes ne doit pas
    // faire enfler ce tableau. Aucun objet reel n'en approche.
    if ($haut > 100000) {
        return null;
    }
    $posable = array_fill(0, $haut + 1, false);
    $posable[0] = true;
    for ($somme = 1; $somme <= $haut; $somme++) {
        foreach ($masses as $masse) {
            if ($somme >= $masse && $posable[$somme - $masse]) {
                $posable[$somme] = true;
                break;
            }
        }
    }
    $meilleur = null;
    $bas = max(0, $cible - $tolerance);
    for ($somme = $bas; $somme <= $haut; $somme++) {
        if (!$posable[$somme]) {
            continue;
        }
        if ($meilleur === null || abs($somme - $cible) < abs($meilleur - $cible)) {
            $meilleur = $somme;
        }
    }
    return $meilleur;
}

/**
 * La tolerance d'un objet, telle que fgQuestionsBalance la pose. Isolee pour
 * que le controle de banques applique EXACTEMENT la meme regle que le jeu,
 * plutot qu'une copie qui finirait par diverger.
 */
function fgBalanceTolerance(array $banque, array $objet): int
{
    return (bool)($objet['exact'] ?? true)
        ? 0
        : (int)round((int)$objet['masse'] * (float)($banque['tolerance_pct'] ?? 0.3));
}

/** Vrai si l'objet peut etre approche dans sa tolerance avec les masses offertes. */
function fgBalanceObjetJouable(array $banque, array $objet): bool
{
    return fgBalanceMasseAtteignable(
        (int)$objet['masse'],
        array_map('intval', $banque['masses']),
        fgBalanceTolerance($banque, $objet)
    ) !== null;
}

function fgQuestionsBalance(array $banque, string $graine): array
{
    // LE FILTRE PASSE AVANT LE TIRAGE, pas apres : un objet injouable qui
    // serait ecarte une fois la manche choisie laisserait un trou dans la
    // partie. Ecarte ici, il ne prend simplement pas de place. Le controle
    // de banques signale ces objets pour qu'ils soient corriges dans la
    // donnee plutot que silencieusement perdus - voir tests/verifier-banques.php.
    $objets = array_values(array_filter(
        $banque['objets'],
        static fn(array $objet): bool => fgBalanceObjetJouable($banque, $objet)
    ));
    fgMelangerStable($objets, $graine . '|balance|ordre');
    $objets = array_slice($objets, 0, FG_QUESTIONS_BALANCE);

    // Du plus simple au plus difficile : le nombre de masses qu'il faut poser
    // au minimum. Une masse qui tombe pile sur un poids disponible se resout
    // d'un geste ; 750 g en demande trois, donc une decomposition.
    $masses = array_map('intval', $banque['masses']);
    $objets = fgOrdonnerParDifficulte($objets, static function (array $o) use ($masses): float {
        return (float)fgBalanceNombreMinimal((int)$o['masse'], $masses);
    });

    $questions = array();
    foreach ($objets as $o) {
        $exact = (bool)($o['exact'] ?? true);
        // Une seule regle de tolerance, partagee avec le controle de banques.
        $tolerance = fgBalanceTolerance($banque, $o);
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'enonce_balance' => $exact
                ? 'Équilibre la balance avec tes masses.'
                : 'Environ combien pèse ' . $o['nom'] . ' ?',
            // DEUX MODES, ET LE FLEAU NE SE COMPORTE PAS PAREIL DANS LES DEUX.
            // En « equilibre », il penche vraiment : c'est la competence 3972,
            // lire l'inclinaison pour savoir s'il faut ajouter ou retirer, et
            // le serveur repond par api/balance.php sans jamais dire la masse.
            // En « estimer », IL N'Y A PAS DE BALANCE : une balance qui penche
            // donnerait la masse par dichotomie en quelques essais, alors que
            // 3973 demande justement d'estimer sans peser.
            'mode_balance' => $exact ? 'equilibre' : 'estimer',
            'objet_nom' => (string)$o['nom'],
            'objet_image' => (string)($o['image'] ?? ''),
            'masses_offertes' => $masses,
            'masse_objet' => (int)$o['masse'],
            'tolerance_g' => $tolerance,
            'explication' => ucfirst((string)$o['nom']) . ' pèse ' . fgBalanceLibelle((int)$o['masse']) . '.',
        );
    }
    return $questions;
}

/**
 * Nombre minimal de masses pour atteindre une cible, en prenant toujours la
 * plus lourde qui rentre. Sert UNIQUEMENT a classer les manches par
 * difficulte : l'eleve, lui, n'est jamais oblige de trouver cette
 * decomposition-la. Glouton suffit ici, les jeux de masses usuels etant
 * canoniques (1000, 500, 200, 100, 50).
 */
function fgBalanceNombreMinimal(int $cible, array $masses): int
{
    rsort($masses);
    $reste = $cible;
    $n = 0;
    foreach ($masses as $m) {
        if ($m <= 0) {
            continue;
        }
        while ($reste >= $m) {
            $reste -= $m;
            $n++;
        }
    }
    return $reste === 0 ? $n : $n + 1;
}

function fgBalanceLibelle(int $grammes): string
{
    if ($grammes >= 1000 && $grammes % 1000 === 0) {
        return ($grammes / 1000) . ' kg';
    }
    if ($grammes >= 1000) {
        return str_replace('.', ',', (string)($grammes / 1000)) . ' kg';
    }
    return $grammes . ' g';
}

/**
 * Somme les masses posees et compare a l'objet. Les masses etrangeres a la
 * banque sont ecartees AVANT la somme - meme filtre etroit que le programme du
 * robot : sans lui, une valeur envoyee a la main entrerait dans le calcul.
 */
function fgBalanceValider(array $question, array $posees): array
{
    $offertes = array_map('intval', $question['masses_offertes']);
    $total = 0;
    foreach ($posees as $m) {
        if (in_array((int)$m, $offertes, true)) {
            $total += (int)$m;
        }
    }
    $ecart = $total - (int)$question['masse_objet'];
    return array(
        'correct' => abs($ecart) <= (int)$question['tolerance_g'],
        'total' => $total,
        // Le SENS de l'erreur, pas seulement son existence : trop leger et trop
        // lourd ne se corrigent pas par le meme geste.
        'sens' => $ecart === 0 ? 'juste' : ($ecart < 0 ? 'leger' : 'lourd'),
    );
}

/**
 * Les paquets de dix : denombrer, ou constituer une collection.
 *
 * QUATORZIEME MECANIQUE, ecrite le 07/09/2026. Deux competences l'attendaient -
 * 3900 « necessite une collection a denombrer - image d'objets a compter » et
 * 3901 « manipulation ou dessin - placer des objets en nombre donne ».
 *
 * CE QUI S'APPREND EST DE GROUPER, PAS DE COMPTER. Le programme du CE1 vise la
 * numeration decimale : faire des paquets de dix AVANT d'annoncer le nombre.
 * Deux consequences ecrites dans le code :
 *   - les jetons sont poses EN DESORDRE, jamais alignes par dix. Ranges, il n'y
 *     aurait plus rien a grouper et compter un a un suffirait ;
 *   - la reponse se compose en DIZAINES ET UNITES, pas en un seul nombre. Un
 *     champ unique accepterait un comptage un a un sans jamais le distinguer
 *     d'un vrai groupement.
 *
 * AUCUN TOTAL AFFICHE PENDANT LE GROUPAGE, meme principe que l'horloge et la
 * regle : l'annoncer annulerait l'exercice.
 *
 * 3901 EST LE MEME MOTEUR A L'ENVERS, pas un second moteur : un nombre est
 * donne, l'eleve pose ce nombre de jetons. Une seconde banque suffit.
 */
const FG_QUESTIONS_COLLECTION = 6;

function fgQuestionsCollection(array $banque, string $graine): array
{
    $nombres = array_map('intval', array_values($banque['nombres']));
    fgMelangerStable($nombres, $graine . '|collection|ordre');
    $nombres = array_slice($nombres, 0, FG_QUESTIONS_COLLECTION);

    // Du plus simple au plus difficile : la taille de la collection. Vingt-trois
    // jetons font deux paquets ; soixante-dix-sept en font sept, et il faut
    // tenir le compte des paquets deja faits.
    $nombres = fgOrdonnerParDifficulte($nombres, static function (int $n): float {
        return (float)$n;
    });

    $mode = (string)($banque['mode'] ?? 'denombrer');
    $motif = (string)($banque['motif'] ?? '');
    $questions = array();
    foreach ($nombres as $n) {
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'enonce_collection' => $mode === 'constituer'
                ? 'Pose exactement ' . $n . ' jetons.'
                : 'Fais des paquets de dix, puis écris combien il y a de jetons.',
            'mode_collection' => $mode,
            'motif_collection' => $motif,
            // En mode « constituer », le nombre EST l'enonce : le cacher ne
            // laisserait rien a demander. En mode « denombrer », il ne part
            // pas - le trouver est toute la question - mais la QUANTITE de
            // jetons a afficher, si : voir fgQuestionsPourLeClient.
            'total_jetons' => $n,
            'explication' => $mode === 'constituer'
                ? 'Il en fallait ' . $n . ' : ' . intdiv($n, 10) . ' paquet(s) de dix et ' . ($n % 10) . ' tout seuls.'
                : 'Il y en avait ' . $n . ' : ' . intdiv($n, 10) . ' dizaines et ' . ($n % 10) . ' unités.',
        );
    }
    return $questions;
}

/**
 * Valide le nombre annonce. En mode « denombrer » la reponse arrive decomposee
 * en dizaines et unites ; on compare la valeur reconstruite ET on retient si
 * la decomposition elle-meme est plausible, pour pouvoir le dire a l'eleve.
 */
function fgCollectionValider(array $question, int $dizaines, int $unites): array
{
    $total = (int)$question['total_jetons'];
    $annonce = $dizaines * 10 + $unites;
    return array(
        'correct' => $annonce === $total,
        'annonce' => $annonce,
        // Distinguer « mauvais nombre de paquets » de « mauvais reste » permet
        // de nommer l'erreur : ce ne sont pas les memes gestes a reprendre.
        'dizaines_justes' => $dizaines === intdiv($total, 10),
    );
}

function fgQuestionsOrdre(array $banque, string $graine): array
{
    if (isset($banque['sequences'])) {
        // ON PUISE DANS LA BANQUE, ON NE LA DEROULE PLUS (10/09/2026).
        //
        // Jusqu'ici toutes les sequences etaient servies : une banque de quatre
        // suites donnait quatre questions, toujours les memes, et la partie ne
        // variait jamais (FG-AUDIT-006). Enrichir la banque aurait alors
        // RALLONGE la partie au lieu de la faire varier - neuf questions la ou
        // les autres mecaniques en posent quatre a six.
        //
        // Comme partout ailleurs ici : on melange, on prend les premieres, puis
        // on les reclasse du plus facile au plus difficile. Ajouter des suites
        // varie desormais les parties sans les allonger.
        $sequences = array_values($banque['sequences']);
        fgMelangerStable($sequences, $graine . '|ordre|sequences');
        $sequences = array_slice($sequences, 0, FG_QUESTIONS_ORDRE);
        //
        // DEUX MESURES, ET C'EST LA BANQUE QUI CHOISIT LAQUELLE :
        //   - une sequence de NOMBRES se mesure a son plus grand nombre.
        //     Ranger 4, 14, 40, 41, 44 vient donc avant 12, 21, 102, 120,
        //     201 : passer a trois chiffres est la vraie marche ;
        //   - une sequence de MOTS se mesure a sa longueur. Une phrase de
        //     cinq mots se remet en ordre plus vite qu'une phrase de six.
        // Pour les frises historiques et le lexique du temps, aucune des deux
        // ne dit quoi que ce soit d'utile - toutes leurs sequences font cinq
        // elements : l'ordre du fichier est alors conserve tel quel, ce qui
        // est le comportement d'avant. Une progression y demanderait un
        // jugement pedagogique, pas un calcul.
        $sequences = fgOrdonnerParDifficulte($sequences, static function (array $sequence): float {
            $numerique = true;
            $maximum = 0.0;
            foreach ($sequence as $element) {
                if (!is_numeric($element)) {
                    $numerique = false;
                    break;
                }
                $maximum = max($maximum, abs((float)$element));
            }
            return $numerique ? $maximum : (float)count($sequence);
        });

        $questions = array();
        foreach ($sequences as $rang => $sequence) {
            $ordreCorrect = array_values($sequence);
            // ON RETENTE SI LE MELANGE TOMBE JUSTE, comme le fait deja la
            // branche a pool de mots plus bas. Sans cette boucle, une manche
            // pouvait etre servie DEJA RESOLUE : l'eleve n'avait rien a
            // glisser et gagnait le point sans rien faire. Mesure sur 300
            // tirages le 06/09/2026 : cela arrivait sur cinq banques, une
            // manche sur cinquante environ - assez rare pour n'avoir jamais
            // ete remarque, assez frequent pour fausser un score. Trouve en
            // rejouant la suite de tests, pas en relisant le code.
            $tentative = 0;
            do {
                $affichage = $ordreCorrect;
                fgMelangerStable($affichage, $graine . '|sequence|' . $rang . '|' . $tentative);
                $tentative++;
            } while ($affichage === $ordreCorrect && $tentative < 5);
            $questions[] = array(
                'question' => $banque['question'],
                'consigne' => $banque['consigne'],
                'mots_a_ranger' => $affichage,
                'ordre_correct' => $ordreCorrect,
                'explication' => 'L’ordre attendu était : ' . implode(', ', $ordreCorrect) . '.',
            );
        }
        return $questions;
    }
    $pool = $banque['mots'];
    $indices = range(0, count($pool) - 1);
    fgMelangerStable($indices, $graine . '|ordre-pool');

    $questions = array();
    $curseur = 0;
    for ($manche = 0; $manche < FG_QUESTIONS_ORDRE; $manche++) {
        // La manche grossit au fil de la partie - trois mots, puis quatre,
        // puis cinq - et la derniere valeur vaut pour toutes les manches
        // suivantes. Ici, pas besoin de reclasser apres coup : la difficulte
        // est portee par la TAILLE de la manche, donc elle est croissante
        // par construction.
        $taille = FG_MOTS_PAR_MANCHE_ORDRE[min($manche, count(FG_MOTS_PAR_MANCHE_ORDRE) - 1)];
        if ($curseur + $taille > count($indices)) {
            // Pool trop court pour une manche de plus : une partie plus
            // courte vaut mieux qu'une manche tronquee ou des mots repetes.
            break;
        }
        $tires = array_slice($indices, $curseur, $taille);
        $curseur += $taille;

        // Les indices tries retrouvent l'ordre alphabetique de ce
        // sous-ensemble, puisque le pool entier l'est deja.
        $indicesTries = $tires;
        sort($indicesTries);
        $motsCorrects = array_values(array_map(static fn($i) => $pool[$i], $indicesTries));

        // L'ordre d'AFFICHAGE - les etiquettes que l'eleve voit et doit
        // glisser - est un second melange, independant du tirage. Sans lui,
        // les mots pourraient apparaitre par hasard deja dans le bon ordre,
        // ce qui rendrait la manche triviale plus souvent qu'il ne faudrait :
        // on retente quelques fois si c'est le cas.
        $motsAMelanger = array_values(array_map(static fn($i) => $pool[$i], $tires));
        $tentative = 0;
        do {
            $copie = $motsAMelanger;
            fgMelangerStable($copie, $graine . '|ordre-affichage|' . $manche . '|' . $tentative);
            $tentative++;
        } while ($copie === $motsCorrects && $tentative < 5);

        $questions[] = array(
            'question' => $banque['question'] ?? null,
            'consigne' => $banque['consigne'],
            'mots_a_ranger' => $copie,
            'ordre_correct' => $motsCorrects,
            'explication' => 'Ordre alphabétique : ' . implode(', ', $motsCorrects) . '.',
        );
    }
    return $questions;
}

/**
 * Normalise une reponse tapee au clavier pour la comparaison.
 *
 * PLUS TOLERANT QUE LE JEU D'ORIGINE, deliberement. School Monsters
 * comparait `reponse.toLowerCase().trim()` a l'identique (accents compris)
 * et ne rattrapait les variantes qu'a la main, entree par entree
 * (« oiseau » ET « oiseaux » ecrits en dur). Ici, l'accent, la casse, les
 * apostrophes et les espaces multiples ne comptent plus : un CE1 qui tape
 * "d eau" au lieu de « d'eau », ou "chateau" sans circonflexe sur un
 * clavier de tablette, n'a pas a etre penalise pour ca - la notion testee
 * est le vocabulaire entendu, pas la ponctuation.
 */
function fgEcouteNormaliser(string $texte): string
{
    $texte = fgTexteComparable($texte);
    $texte = str_replace(array('\'', '’', '‘', '-'), ' ', $texte);
    $texte = (string)preg_replace('/\s+/u', ' ', $texte);
    return trim($texte);
}

/**
 * Un son a identifier : chaque manche tire un element du pool et l'eleve
 * tape ce qu'il entend. Pas de propositions a cacher ici - il n'y a rien a
 * choisir, la reponse se construit au clavier, comme pour le compte est bon
 * ou la boutique. Voir fgEnregistrerReponseEcoute() dans seance.php pour la
 * comparaison, faite avec fgEcouteNormaliser() ci-dessus.
 */
function fgQuestionsEcoute(array $banque, string $graine): array
{
    $sons = $banque['sons'];
    fgMelangerStable($sons, $graine . '|ecoute');
    $retenus = array_slice($sons, 0, FG_QUESTIONS_ECOUTE);

    $questions = array();
    foreach ($retenus as $son) {
        $questions[] = array(
            'consigne' => $banque['consigne'],
            'fichier' => $son['fichier'],
            'reponses_valides' => $son['reponses'],
            'explication' => 'La bonne réponse était : ' . $son['reponses'][0] . '.',
        );
    }
    return $questions;
}

/**
 * Fabrique la partie complete a partir d'une banque.
 *
 * Meme forme de retour que fgGenererJeuDepuisReference, pour que jeu.php et la
 * seance n'aient pas a distinguer les deux origines. Le champ « mecanique »
 * s'ajoute : c'est lui qui dit au navigateur comment presenter les questions.
 */
function fgJeuDepuisBanque(array $banque, string $libelleCompetence, string $graine): ?array
{
    if ($banque['mecanique'] === 'tri') {
        $questions = fgQuestionsTri($banque, $graine);
    } elseif ($banque['mecanique'] === 'droite') {
        $questions = fgQuestionsDroite($banque, $graine);
    } elseif ($banque['mecanique'] === 'carte') {
        $questions = fgQuestionsCarte($banque, $graine);
    } elseif ($banque['mecanique'] === 'grille') {
        $questions = fgQuestionsGrille($banque, $graine);
    } elseif ($banque['mecanique'] === 'partage') {
        $questions = fgQuestionsPartage($banque, $graine);
    } elseif ($banque['mecanique'] === 'probleme') {
        $questions = fgQuestionsProbleme($banque, $graine);
    } elseif ($banque['mecanique'] === 'graphie') {
        $questions = fgQuestionsGraphie($banque, $graine);
    } elseif ($banque['mecanique'] === 'graphique') {
        $questions = fgQuestionsGraphique($banque, $graine);
    } elseif ($banque['mecanique'] === 'regle') {
        $questions = fgQuestionsRegle($banque, $graine);
    } elseif ($banque['mecanique'] === 'balance') {
        $questions = fgQuestionsBalance($banque, $graine);
    } elseif ($banque['mecanique'] === 'collection') {
        $questions = fgQuestionsCollection($banque, $graine);
    } elseif ($banque['mecanique'] === 'ordre') {
        $questions = fgQuestionsOrdre($banque, $graine);
    } elseif ($banque['mecanique'] === 'ecoute') {
        $questions = fgQuestionsEcoute($banque, $graine);
    } elseif ($banque['mecanique'] === 'qcm') {
        $questions = fgQuestionsQcm($banque, $graine);
    } else {
        $questions = fgQuestionsPaires($banque, $graine);
    }

    if (count($questions) < 3) {
        // Banque trop maigre : mieux vaut pas de jeu qu'un jeu de deux
        // questions, dont le score ne voudrait rien dire.
        return null;
    }

    $tons = array('tri' => 'ambre', 'paires' => 'corail', 'ordre' => 'turquoise', 'ecoute' => 'ambre', 'qcm' => 'corail', 'carte' => 'turquoise', 'droite' => 'ambre', 'grille' => 'corail', 'partage' => 'turquoise', 'probleme' => 'ambre', 'graphie' => 'corail', 'graphique' => 'turquoise', 'regle' => 'ambre', 'balance' => 'corail', 'collection' => 'turquoise');
    return array(
        'titre' => $banque['titre'],
        'description' => $banque['consigne'],
        'competence' => $libelleCompetence,
        'duree' => '3 min',
        'ton' => $tons[$banque['mecanique']] ?? 'turquoise',
        'mecanique' => $banque['mecanique'],
        'questions' => $questions,
    );
}
