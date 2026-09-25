<?php
/**
 * La seance de jeu en cours, tenue par le SERVEUR.
 *
 * POURQUOI CE FICHIER EXISTE. Jusqu'au 31/08/2026, jeu.php envoyait au
 * navigateur l'objet complet du jeu, « bonne » comprise, le JavaScript comptait
 * les points et l'API croyait le chiffre sur parole - elle verifiait seulement
 * qu'il tenait entre 0 et le total. Deux consequences : les reponses etaient
 * lisibles dans la source de la page, et un score pouvait etre annonce sans
 * avoir joue. Sans effet tant que l’enseignante est seule en apercu, mais la moyenne
 * de la classe et la liste des eleves a accompagner en auraient dependu des
 * l'ouverture aux eleves.
 *
 * CE QUI REND LA CORRECTION LEGERE : les generateurs sont deterministes. A
 * partir de la meme reference et de la meme graine, fgGenererJeuDepuisReference
 * rend exactement les memes questions, dans le meme ordre, avec les reponses
 * melangees de la meme facon. La session n'a donc pas a stocker les questions :
 * elle garde la reference et la graine, et le serveur reconstruit la seance
 * quand il en a besoin. C'est aussi ce qui verifie le determinisme en continu -
 * s'il se cassait un jour, la correction cesserait de tomber juste.
 *
 * LE CLIENT NE RECOIT PLUS « bonne » NI « explication » a l'affichage. Il les
 * demande apres avoir repondu, une question a la fois : la bonne reponse n'est
 * revelee qu'une fois le choix fait et enregistre.
 */

/** Duree au-dela de laquelle une seance abandonnee est consideree perimee. */
const FG_SEANCE_DUREE = 3600;

/**
 * Combien de parties un meme navigateur peut tenir ouvertes en meme temps.
 *
 * POURQUOI CE N'EST PLUS UNE. Jusqu'au 10/09/2026 la session ne gardait qu'une
 * seance : ouvrir un jeu dans un second onglet effacait la partie en cours du
 * premier, qui repondait ensuite 409 a chaque clic sans porte de sortie
 * (FG-AUDIT-004). Quatre suffisent largement pour un eleve qui ouvre deux ou
 * trois onglets, et bornent ce que la session porte.
 */
const FG_SEANCES_MAX = 4;

/**
 * Toutes les seances encore en session, de la plus ancienne a la plus recente.
 *
 * REPRISE DES SESSIONS DEJA OUVERTES : avant ce changement la seance vivait
 * seule sous 'fastgames_seance'. Une partie commencee juste avant la livraison
 * doit pouvoir se terminer, d'ou ce rattrapage - il ne sert qu'une fois par
 * session et disparaitra de lui-meme.
 *
 * @return array identifiant => seance
 */
function fgSeances(): array
{
    if (!isset($_SESSION['fastgames_seances']) || !is_array($_SESSION['fastgames_seances'])) {
        $_SESSION['fastgames_seances'] = array();
        $ancienne = $_SESSION['fastgames_seance'] ?? null;
        if (is_array($ancienne) && isset($ancienne['id'])) {
            $_SESSION['fastgames_seances'][(string)$ancienne['id']] = $ancienne;
        }
        unset($_SESSION['fastgames_seance']);
    }
    return $_SESSION['fastgames_seances'];
}

/**
 * Ecrit une seance, ou la remet a jour apres une reponse.
 *
 * UN SEUL ENDROIT QUI ECRIT. Les seize fonctions fgEnregistrerX() posaient
 * chacune leur propre affectation en session ; il a suffi d'en oublier une
 * pour qu'une mecanique perde ses reponses. Elles passent toutes par ici.
 */
function fgEnregistrerSeance(array $seance): void
{
    $seances = fgSeances();
    $identifiant = (string)$seance['id'];
    // Reposer la seance en fin de table : la plus recemment touchee est la
    // derniere, c'est elle que « Reprendre ma partie » doit proposer.
    unset($seances[$identifiant]);
    $seances[$identifiant] = $seance;
    // Les perimees partent d'abord : inutile d'evincer une partie vivante
    // pour faire de la place a une autre si une morte occupe un rang.
    $maintenant = time();
    foreach ($seances as $cle => $gardee) {
        if ($maintenant - (int)$gardee['creee'] > FG_SEANCE_DUREE) {
            unset($seances[$cle]);
        }
    }
    while (count($seances) > FG_SEANCES_MAX) {
        array_shift($seances);
    }
    $_SESSION['fastgames_seances'] = $seances;
}

/** Retire une seance de la session, par son identifiant. */
function fgOublierSeance(string $identifiant): void
{
    $seances = fgSeances();
    unset($seances[$identifiant]);
    $_SESSION['fastgames_seances'] = $seances;
}

/** Nombre de resultats qu'un eleve peut enregistrer dans une journee. */
const FG_MAX_RESULTATS_JOUR = 60;

/** Delai minimal entre deux enregistrements, en secondes. */
const FG_DELAI_ENTRE_RESULTATS = 5;

/**
 * Ouvre une seance et rend l'identifiant a poser dans la page.
 *
 * @param array       $jeu        Le jeu deja construit, pour son nombre de questions.
 * @param array|null  $reference  La reference Fast Eval, si le jeu est genere.
 * @param string      $graine     La graine ayant servi a le produire.
 * @param array       $contexte   Dont 'reference_qualifiee' : voir ci-dessous.
 */
function fgOuvrirSeance(array $jeu, ?array $reference, string $graine, array $contexte): string
{
    // DEUX CHOSES DIFFERENTES, ET ELLES NE VONT PLUS ENSEMBLE DEPUIS LE
    // 10/09/2026.
    //
    // 'reference' sert a REFABRIQUER la partie : son type et son identifiant
    // entrent dans la graine, sans eux le serveur ne saurait plus corriger.
    // Elle reste donc toujours en session, quelle que soit son origine.
    //
    // 'type_reference' et 'id_reference' sont autre chose : c'est ce qui
    // finira EN BASE, et donc dans le suivi par competence de l'enseignante.
    // Cela n'a de sens que si le lien avec la competence est declare quelque
    // part a la main - c'est le cas des banques, dont data/banques.php porte
    // la liste 'competences'. Ce n'est PAS le cas des generateurs
    // historiques, dont fgTypeGenerateur() devine la competence a partir de
    // mots trouves dans l'intitule : mesure du 10/09/2026 sur les 577
    // intitules officiels 2026/2027, 52 competences captees dont 15 dans une
    // autre matiere que le generateur, et de l'ordre de 30 fausses en tout
    // (FG-AUDIT-017). « Comprendre la difference entre sens propre et sens
    // figure » tombait en soustraction sur le mot « difference », « Decrire
    // un cube, un pave ou une pyramide... sommet et arete » en addition.
    //
    // Un resultat range sous une competence que l'eleve n'a pas travaillee
    // fait mentir le suivi. Une partie sans competence, elle, se retrouve
    // dans la vue « jeux transversaux » qui existe deja : rien n'est perdu.
    // Arbitrage de le responsable technique, 10/09/2026.
    //
    // POUR PLUS TARD : le jour ou une relation explicite competence <->
    // generateur sera ecrite, il suffira de passer 'reference_qualifiee' a
    // vrai sur ce chemin. Rien d'autre ici ne change.
    $qualifiee = !empty($contexte['reference_qualifiee']) && $reference !== null;
    $identifiant = bin2hex(random_bytes(16));
    fgEnregistrerSeance(array(
        'id' => $identifiant,
        'reference' => $reference,
        'graine' => $graine,
        'banque' => (string)($contexte['banque'] ?? ''),
        'jeu' => (string)($contexte['jeu'] ?? ''),
        'programme' => (string)($contexte['programme'] ?? ''),
        'theme' => (string)($contexte['theme'] ?? ''),
        'categorie' => (string)($contexte['categorie'] ?? ''),
        'generateur' => (string)($contexte['generateur'] ?? ''),
        // Uniquement pour le compte est bon : le niveau de depart, fige au
        // lancement pour que la reconstruction retombe toujours sur les
        // memes manches, meme si le niveau memorise en session a bouge
        // entre-temps (une autre partie close pendant que celle-ci tourne).
        'niveau' => (int)($contexte['niveau'] ?? 0),
        'reference_qualifiee' => $qualifiee,
        'type_reference' => $qualifiee ? (string)($reference['type'] ?? '') : '',
        'id_reference' => $qualifiee ? (int)($reference['id'] ?? 0) : 0,
        'total' => count($jeu['questions']),
        'reponses' => array(),
        'creee' => time(),
        // CE QUE L'ADRESSE DEMANDAIT, tirage exclu. Deux parties de la meme
        // activite portent le meme appel, meme si leurs graines different :
        // c'est ce qui permet de reconnaitre « la partie que j'avais
        // commencee » apres un rafraichissement. Voir fgAppelDemande() dans
        // jeu.php, qui le construit, et fgSeanceAReprendrePour() plus bas.
        'appel' => (string)($contexte['appel'] ?? ''),
    ));
    return $identifiant;
}

/**
 * Rend la seance demandee si elle existe encore et n'a pas expire.
 */
function fgSeanceOuverte(string $identifiant): ?array
{
    if ($identifiant === '') {
        return null;
    }
    foreach (fgSeances() as $cle => $seance) {
        if (!is_array($seance) || !hash_equals((string)$cle, $identifiant)) {
            continue;
        }
        if (time() - (int)$seance['creee'] > FG_SEANCE_DUREE) {
            fgOublierSeance($cle);
            return null;
        }
        return $seance;
    }
    return null;
}

/** Vrai si cette seance peut encore etre reprise : ouverte et non terminee. */
function fgSeanceReprenable(array $seance): bool
{
    return time() - (int)$seance['creee'] <= FG_SEANCE_DUREE
        && count($seance['reponses'] ?? array()) < (int)($seance['total'] ?? 0);
}

/**
 * La partie encore jouable la plus recente du navigateur courant.
 *
 * Une partie terminee n'est jamais proposee a nouveau : « Reprendre » doit
 * signifier reprendre exactement l'endroit quitte, pas rejouer un resultat.
 */
function fgSeanceAReprendre(): ?array
{
    foreach (array_reverse(fgSeances(), true) as $seance) {
        if (is_array($seance) && fgSeanceReprenable($seance)) {
            return $seance;
        }
    }
    return null;
}

/**
 * La partie en cours SUR CETTE ACTIVITE PRECISE, s'il y en a une.
 *
 * POURQUOI. Rafraichir une page de jeu rouvrait une seance neuve et jetait les
 * reponses deja donnees : le score repartait a zero sans rien dire
 * (FG-AUDIT-005). La reprise existait pourtant deja, mais seulement depuis le
 * bouton du catalogue.
 *
 * ELLE NE S'APPLIQUE QUE SI L'ADRESSE NE FIXE PAS DE TIRAGE. « Rejouer » en
 * pose un explicitement : demander une autre partie de la meme activite doit
 * rester possible, sinon l'eleve serait prisonnier de sa partie en cours.
 */
function fgSeanceAReprendrePour(string $appel): ?array
{
    if ($appel === '') {
        return null;
    }
    foreach (array_reverse(fgSeances(), true) as $seance) {
        if (is_array($seance) && (string)($seance['appel'] ?? '') === $appel && fgSeanceReprenable($seance)) {
            return $seance;
        }
    }
    return null;
}

/** Reconstruit l'enveloppe de presentation d'une seance sans en ouvrir une nouvelle. */
function fgJeuDepuisSeance(array $seance): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null) {
        return null;
    }
    $idJeu = (string)($seance['jeu'] ?? '');
    if ($idJeu === 'compte-est-bon') {
        require_once __DIR__ . '/compte-est-bon.php';
        $jeu = fgJeuCompteEstBon((string)$seance['graine'], (int)$seance['niveau']);
    } elseif ($idJeu === 'boutique') {
        require_once __DIR__ . '/boutique.php';
        $jeu = fgJeuBoutique((string)$seance['graine'], (int)$seance['niveau']);
    } elseif ($idJeu === 'horloge') {
        require_once __DIR__ . '/horloge.php';
        $jeu = fgJeuHorloge((string)$seance['graine'], (int)$seance['niveau']);
    } elseif (!empty($seance['banque'])) {
        require_once __DIR__ . '/mecaniques.php';
        $banques = fgBanques();
        $jeu = isset($banques[(string)$seance['banque']])
            ? fgJeuDepuisBanque($banques[(string)$seance['banque']], (string)($seance['reference']['libelle'] ?? ''), (string)$seance['graine'])
            : null;
    } elseif (!empty($seance['reference'])) {
        require_once __DIR__ . '/generateurs.php';
        $jeu = fgGenererJeuDepuisReference($seance['reference'], (string)$seance['graine']);
    } else {
        $jeux = require __DIR__ . '/../data/jeux.php';
        $jeu = $jeux[$idJeu] ?? null;
    }
    if (!is_array($jeu)) {
        return null;
    }
    // Les questions sont la source de verite de la seance, pas un nouveau tirage.
    $jeu['questions'] = $questions;
    return $jeu;
}

/**
 * Reconstruit les questions de la seance, cote serveur uniquement.
 *
 * Pour un jeu genere, on repasse par le generateur avec la meme graine. Pour un
 * jeu du catalogue fixe, les questions sont dans data/jeux.php.
 */
function fgSeanceQuestions(array $seance): ?array
{
    // Le compte est bon n'a ni banque ni reference du referentiel : la cle
    // « jeu » suffit a le reconnaitre, et la graine suffit a le refabriquer.
    // Le niveau vient de la seance elle-meme, jamais de la session courante :
    // voir le commentaire sur 'niveau' dans fgOuvrirSeance().
    if ((string)($seance['jeu'] ?? '') === 'compte-est-bon') {
        require_once __DIR__ . '/compte-est-bon.php';
        $niveau = (int)($seance['niveau'] ?? FG_COMPTE_NIVEAU_MIN);
        return fgJeuCompteEstBon((string)$seance['graine'], $niveau)['questions'];
    }
    if ((string)($seance['jeu'] ?? '') === 'boutique') {
        require_once __DIR__ . '/boutique.php';
        $niveau = (int)($seance['niveau'] ?? FG_BOUTIQUE_NIVEAU_MIN);
        return fgJeuBoutique((string)$seance['graine'], $niveau)['questions'];
    }
    if ((string)($seance['jeu'] ?? '') === 'horloge') {
        require_once __DIR__ . '/horloge.php';
        $niveau = (int)($seance['niveau'] ?? FG_HORLOGE_NIVEAU_MIN);
        return fgJeuHorloge((string)$seance['graine'], $niveau)['questions'];
    }
    // Une partie issue d'une banque se refabrique a partir de la cle de banque
    // et de la graine, exactement comme un jeu genere : rien de la seance n'est
    // stocke en clair, et le serveur reste seul a connaitre les bonnes reponses.
    if (!empty($seance['banque'])) {
        require_once __DIR__ . '/mecaniques.php';
        $banques = fgBanques();
        $cle = (string)$seance['banque'];
        if (!isset($banques[$cle])) {
            return null;
        }
        $jeu = fgJeuDepuisBanque(
            $banques[$cle],
            (string)($seance['reference']['libelle'] ?? ''),
            (string)$seance['graine']
        );
        return $jeu ? $jeu['questions'] : null;
    }
    if (!empty($seance['reference'])) {
        require_once __DIR__ . '/generateurs.php';
        $jeu = fgGenererJeuDepuisReference($seance['reference'], (string)$seance['graine']);
        return $jeu ? $jeu['questions'] : null;
    }
    $jeux = require __DIR__ . '/../data/jeux.php';
    $idJeu = (string)$seance['jeu'];
    return isset($jeux[$idJeu]) ? $jeux[$idJeu]['questions'] : null;
}

/**
 * Retire de chaque question ce que le navigateur n'a pas a savoir avant d'avoir
 * repondu. Le reste - enonce, consigne, libelles - lui est indispensable.
 */
function fgQuestionsPourLeClient(array $questions): array
{
    $publiques = array();
    foreach ($questions as $question) {
        // Le compte est bon n'a pas de liste de propositions a cacher : les
        // nombres et la cible sont precisement ce que l'eleve doit voir pour
        // jouer, rien n'y est une reponse a deviner.
        if (array_key_exists('nombres', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'nombres' => $question['nombres'],
                'cible' => $question['cible'],
                // Sans ce champ, jeu.js retombait sur son repli + et - pour
                // toutes les manches, y compris celles qui autorisent × et ÷ :
                // le repli existe pour la robustesse, pas pour cacher un
                // oubli. Trouve en testant un niveau eleve, pas au niveau 1
                // ou le repli donnait par coincidence le bon resultat.
                'operateurs' => $question['operateurs'] ?? array('+', '-'),
            );
            continue;
        }
        // La boutique non plus n'a rien a cacher : l'article, son prix et le
        // porte-monnaie sont l'enonce meme. Seule l'explication reste au
        // serveur jusqu'a ce que l'eleve ait pose ses pieces.
        if (array_key_exists('porte_monnaie', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'article' => $question['article'],
                'embleme' => $question['embleme'],
                'prix_affiche' => $question['prix_affiche'],
                'mode' => $question['mode'],
                'donne_affiche' => $question['donne_affiche'],
                'cible' => $question['cible'],
                'cible_affichee' => $question['cible_affichee'],
                'porte_monnaie' => $question['porte_monnaie'],
                'limite' => $question['limite'],
            );
            continue;
        }
        // Le tri alphabetique non plus n'a rien a cacher que l'ORDRE : les
        // cinq mots a ranger sont l'enonce meme, seul le champ
        // « ordre_correct » reste au serveur jusqu'a la reponse.
        if (array_key_exists('mots_a_ranger', $question)) {
            $publiques[] = array(
                'question' => $question['question'] ?? null,
                'consigne' => $question['consigne'],
                'mots_a_ranger' => $question['mots_a_ranger'],
            );
            continue;
        }
        // La droite graduee : le nombre a placer EST l'enonce, il n'y a donc
        // rien a cacher de lui. Mais la droite doit etre dessinable, d'ou min,
        // max et le pas des graduations. La tolerance, elle, reste au serveur :
        // savoir de combien on peut se tromper ne sert pas a jouer.
        if (array_key_exists('cible', $question) && array_key_exists('pas', $question)) {
            $publiques[] = array(
                'question' => $question['question'],
                'consigne' => $question['consigne'],
                'min' => $question['min'],
                'max' => $question['max'],
                'pas' => $question['pas'],
            );
            continue;
        }
        // La carte : le nom du lieu est l'enonce, mais SA POSITION est
        // precisement ce que l'eleve doit trouver. lat, lon et tolerance
        // restent donc au serveur jusqu'a ce que le repere soit pose - les
        // envoyer reviendrait a ecrire la reponse dans la page.
        if (array_key_exists('lat', $question)) {
            $publiques[] = array(
                'question' => $question['question'],
                'consigne' => $question['consigne'],
            );
            continue;
        }
        // La graphie : `manque` - la reponse - reste au serveur.
        //
        // MAIS `mot` PART, ET C'EST UN COMPROMIS ASSUME. Sans fichier son, c'est
        // le navigateur qui prononce le mot, et pour le prononcer il faut le lui
        // donner : un eleve curieux peut donc le lire dans la page. Le meme
        // compromis existe pour la cible du compte est bon, a une difference
        // pres - ici il donne la reponse.
        //
        // CE QUI LE REFERME : deposer le mp3 du mot dans assets/son/ et le
        // declarer dans la banque. Le client prefere alors le fichier et `mot`
        // n'est plus envoye. Le code est deja ecrit pour ce jour-la ; il ne
        // manque que les fichiers.
        if (array_key_exists('manque', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'avant' => $question['avant'],
                'apres' => $question['apres'],
                'graphies' => $question['graphies'],
                'fichier' => $question['fichier'],
                'mot' => $question['fichier'] ? null : $question['mot'],
            );
            continue;
        }
        // Le probleme : l'enonce et ses nombres sont ce qu'il faut lire pour
        // jouer. La REPONSE, elle, ne part pas - contrairement au compte est
        // bon, la trouver EST la question.
        if (array_key_exists('nombres_enonce', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce_probleme' => $question['enonce_probleme'],
                'nombres_enonce' => $question['nombres_enonce'],
                'operateurs' => $question['operateurs'],
            );
            continue;
        }
        // Le partage : la FRACTION EST L'ENONCE, comme la cible du compte est
        // bon. Numerateur et denominateur partent donc au client - sans eux il
        // n'y a rien a demander. Ce qui se construit, c'est le dessin, et lui
        // n'est nulle part dans ce qu'on envoie. Seule l'explication reste au
        // serveur jusqu'a la correction.
        if (array_key_exists('denominateur', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce_partage' => $question['enonce_partage'],
                'support' => $question['support'],
                'numerateur' => $question['numerateur'],
                'denominateur' => $question['denominateur'],
            );
            continue;
        }
        // Le graphique : les donnees de l'enquete SONT l'enonce - sans elles il
        // n'y a rien a representer. Ce qui se construit, c'est le dessin, et il
        // n'est nulle part dans ce qu'on envoie. Seule l'explication reste au
        // serveur jusqu'a la correction.
        if (array_key_exists('series_graphique', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce_graphique' => $question['enonce_graphique'],
                'max_graphique' => $question['max_graphique'],
                'series_graphique' => $question['series_graphique'],
            );
            continue;
        }
        // La regle graduee. LA LONGUEUR DU SEGMENT PART AU CLIENT, ET C'EST
        // INEVITABLE : il faut bien le dessiner a sa vraie taille, sinon la
        // mesure ne veut rien dire. Un eleve qui ouvre l'inspecteur y lit donc
        // la reponse.
        //
        // CE N'EST PAS UNE NEGLIGENCE, c'est le meme compromis que le mot de la
        // graphie, la fraction du partage et les jetons a compter : quand
        // l'objet A MESURER est l'enonce, le cacher ne laisse rien a demander.
        // Ce qui est evalue ici est le GESTE - poser le zero, lire la bonne
        // graduation - pas la capacite a deviner un nombre cache.
        //
        // `longueur_cm` et `tolerance_cm` ne partent pas pour autant. Ils ne
        // protegent pas grand-chose, mais rien n'oblige a ecrire la reponse en
        // clair a cote du dessin qui la porte deja.
        if (array_key_exists('longueur_cm', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce_regle' => $question['enonce_regle'],
                'mode_regle' => $question['mode_regle'],
                'segments' => $question['segments'],
                'regle_cm' => $question['regle_cm'],
            );
            continue;
        }
        // La balance : le nom de l'objet et les masses disponibles sont
        // l'enonce ; SA MASSE est ce qu'on cherche, elle ne part donc pas. Le
        // fleau penche cote client a partir des seules masses posees, sans
        // jamais connaitre la cible - sinon il suffirait de lire la page.
        if (array_key_exists('masse_objet', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce_balance' => $question['enonce_balance'],
                'mode_balance' => $question['mode_balance'],
                'objet_nom' => $question['objet_nom'],
                'objet_image' => $question['objet_image'],
                'masses_offertes' => $question['masses_offertes'],
            );
            continue;
        }
        // Les paquets de dix : le NOMBRE DE JETONS A AFFICHER part forcement -
        // il faut bien les dessiner - et c'est aussi la reponse. Ce n'est pas
        // une fuite : la reponse est sous les yeux de l'eleve, comme les
        // objets sur une table. Ce qui est demande, c'est de les GROUPER pour
        // les compter, pas de deviner un nombre cache. En mode « constituer »,
        // le nombre est l'enonce et rien n'est affiche au depart.
        if (array_key_exists('total_jetons', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce_collection' => $question['enonce_collection'],
                'mode_collection' => $question['mode_collection'],
                'motif_collection' => $question['motif_collection'],
                'total_jetons' => $question['mode_collection'] === 'constituer'
                    ? 0
                    : $question['total_jetons'],
                'cible_jetons' => $question['mode_collection'] === 'constituer'
                    ? $question['total_jetons']
                    : null,
            );
            continue;
        }
        // La grille du robot : TOUT l'enonce est visible, et doit l'etre - on
        // ne peut pas programmer un deplacement sans voir la grille, le point
        // de depart, l'orientation, les murs et le drapeau. Rien n'est cache
        // ici parce qu'il n'y a rien a deviner : ce qui est demande, c'est
        // d'ECRIRE le chemin, pas de le trouver au hasard. Seule
        // l'explication (la longueur du plus court programme) reste au serveur
        // jusqu'a la correction.
        if (array_key_exists('depart', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce_grille' => $question['enonce_grille'],
                'taille' => $question['taille'],
                'depart' => $question['depart'],
                'cap' => $question['cap'],
                'cible' => $question['cible'],
                'murs' => $question['murs'],
            );
            continue;
        }
        // L'horloge : l'heure demandee EST l'enonce, il n'y a donc rien a
        // cacher de ce que l'eleve doit lire. Mais 'heures' et 'minutes' ne
        // partent pas pour autant - le client n'a besoin que du texte pour
        // presenter la manche, et la position des aiguilles est ce que le
        // serveur compare. Meme principe que partout ici : le navigateur
        // recoit de quoi afficher, rien de plus.
        if (array_key_exists('heures', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'enonce' => $question['enonce'],
            );
            continue;
        }
        // Le son a ecouter est l'enonce lui-meme - c'est justement ce que
        // l'eleve doit entendre pour jouer. Seules les reponses valides
        // restent au serveur : les envoyer reviendrait a afficher la
        // correction avant la question.
        if (array_key_exists('fichier', $question)) {
            $publiques[] = array(
                'consigne' => $question['consigne'],
                'fichier' => $question['fichier'],
            );
            continue;
        }
        $publiques[] = array(
            'question' => $question['question'],
            'consigne' => $question['consigne'],
            'reponses' => $question['reponses'],
            // Pictogramme optionnel d'un tri illustre (voir mecaniques.php,
            // fgQuestionsTri) - absent (null) pour tout le reste, aucune
            // reponse a cacher ici : c'est un decor de l'enonce, pas un
            // indice sur la bonne case.
            'image' => $question['image'] ?? null,
        );
    }
    return $publiques;
}

/**
 * Ce que le navigateur recoit du jeu lui-meme : de quoi presenter, rien de
 * plus. La mecanique en fait partie - savoir qu'il s'agit d'un tri n'aide en
 * rien a deviner la bonne case, mais change tout a l'affichage.
 */
function fgJeuPourLeClient(array $jeu): array
{
    return array(
        'titre' => $jeu['titre'],
        'mecanique' => $jeu['mecanique'] ?? 'qcm',
        'questions' => fgQuestionsPourLeClient($jeu['questions']),
    );
}

/**
 * Enregistre le choix de l'eleve pour une question, et rend la correction.
 *
 * Les reponses arrivent dans l'ordre : on refuse un index qui sauterait une
 * question ou reviendrait sur une reponse deja donnee, sans quoi la meme
 * question pourrait etre rejouee jusqu'a tomber juste.
 */
function fgEnregistrerChoix(array &$seance, int $numero, int $choix): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }
    $question = $questions[$numero];
    if ($choix < 0 || $choix >= count($question['reponses'])) {
        return null;
    }

    $seance['reponses'][] = $choix;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $choix === (int)$question['bonne'],
        'bonne' => (int)$question['bonne'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre l'expression composee par l'eleve pour une manche du compte est
 * bon, et rend la correction. Pendant complementaire de fgEnregistrerChoix()
 * pour cette seule mecanique : la reponse n'est pas un index dans une liste
 * de propositions, mais une suite de nombres et d'operateurs a verifier avec
 * includes/compte-est-bon.php.
 */
function fgEnregistrerEtapesCompte(array &$seance, int $numero, array $etapes): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/compte-est-bon.php';
    $manche = $questions[$numero];
    $correction = fgCompteEstBonValider($manche, $etapes);

    // Les etapes elles-memes sont gardees, pas seulement le verdict : c'est
    // ce qui permet a fgScoreSeance() de revalider a la cloture plutot que de
    // se fier a un booleen calcule une seule fois ici.
    $seance['reponses'][] = $etapes;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        'resultat' => $correction['resultat'],
        'cible' => $manche['cible'],
        // Plus une chaine depuis le 05/09/2026 : un tableau {lignes, autres},
        // voir fgCompteEstBonExplication(). assets/jeu.js sait desormais lire
        // les deux formes (compte est bon en tableau, les autres jeux en
        // chaine) - voir afficherCorrectionCompte().
        'explication' => $manche['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre les pieces posees sur le comptoir pour une manche de boutique.
 * Meme role que fgEnregistrerEtapesCompte() pour le compte est bon : une
 * reponse construite, verifiee par includes/boutique.php.
 */
function fgEnregistrerPiecesBoutique(array &$seance, int $numero, array $pieces): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/boutique.php';
    $manche = $questions[$numero];
    $correction = fgBoutiqueValider($manche, $pieces);

    // Les pieces posees sont gardees telles quelles, pas le verdict : le score
    // final les revalide, comme pour le compte est bon.
    $seance['reponses'][] = $pieces;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        'total' => $correction['total'],
        'cible' => $manche['cible'],
        'cible_affichee' => (string)$manche['cible_affichee'],
        'explication' => (string)$manche['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre le repere pose sur la droite graduee, et rend la correction.
 * Meme role que fgEnregistrerPositionCarte(), a une dimension.
 */
function fgEnregistrerPositionDroite(array &$seance, int $numero, float $valeur): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $correction = fgDroiteValider($question, $valeur);

    $seance['reponses'][] = $valeur;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        // La bonne place n'est renvoyee qu'une fois le repere pose : le client
        // la dessine pour montrer ou le nombre allait.
        'cible' => (float)$question['cible'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre le repere pose sur le planisphere, et rend la correction. Meme
 * role que fgEnregistrerPiecesBoutique() pour la carte : la reponse n'est pas
 * un index choisi, mais un point.
 */
function fgEnregistrerPositionCarte(array &$seance, int $numero, float $lat, float $lon): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $correction = fgCarteValider($question, $lat, $lon);

    // Le point pose est garde tel quel, pas le verdict : le score final le
    // revalide, comme pour la boutique, l'horloge et le compte est bon.
    $seance['reponses'][] = array('lat' => $lat, 'lon' => $lon);
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        // La bonne position n'est revelee qu'ICI, une fois la reponse posee :
        // le client la dessine pour montrer ou c'etait.
        'lat' => (float)$question['lat'],
        'lon' => (float)$question['lon'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre la graphie composee par l'eleve, et rend la correction.
 */
function fgEnregistrerGraphie(array &$seance, int $numero, string $composee): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $correction = fgGraphieValider($question, $composee);

    $seance['reponses'][] = $composee;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        // Le mot entier n'est revele qu'ICI, une fois la reponse posee : c'est
        // la correction, et c'est ce qui s'apprend.
        'mot' => (string)$question['mot'],
        'manque' => (string)$question['manque'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre le calcul construit pour un probleme, et rend la correction.
 * Meme forme d'etapes que le compte est bon - c'est le meme geste, et le
 * client en partage le rendu multi-lignes.
 */
function fgEnregistrerCalculProbleme(array &$seance, int $numero, array $etapes): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $correction = fgProblemeValider($question, $etapes);

    $seance['reponses'][] = $etapes;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        // Le total obtenu ET la reponse attendue : sans le premier, un eleve qui
        // s'est trompe ne sait pas ou son calcul l'a mene.
        'total' => $correction['total'],
        'reponse' => (int)$question['reponse'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre le dessin de l'eleve : en combien de parts il a coupe, et combien
 * il en a colorie. Deux entiers suffisent - lesquelles sont coloriees n'entre
 * pas dans la regle (voir fgQuestionsPartage).
 */
function fgEnregistrerPartage(array &$seance, int $numero, int $parts, int $coloriees): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $correction = fgPartageValider($question, $parts, $coloriees);

    $seance['reponses'][] = array('parts' => $parts, 'coloriees' => $coloriees);
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        // Dire LAQUELLE des deux moities a echoue : s'etre trompe de decoupage
        // n'est pas la meme faute que d'avoir mal compte les parts coloriees.
        'decoupage' => $correction['decoupage'],
        'coloriage' => $correction['coloriage'],
        'numerateur' => (int)$question['numerateur'],
        'denominateur' => (int)$question['denominateur'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre le graphique construit : une hauteur par barre, dans l'ordre des
 * series de l'enquete.
 */
function fgEnregistrerGraphique(array &$seance, int $numero, array $hauteurs): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $hauteurs = array_map('intval', array_values($hauteurs));
    $correction = fgGraphiqueValider($question, $hauteurs);

    $seance['reponses'][] = $hauteurs;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        'fausses' => $correction['fausses'],
        'attendues' => array_map(static function (array $s): int { return (int)$s['valeur']; },
                                 $question['series_graphique']),
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre la longueur lue ou estimee, en centimetres entiers.
 */
function fgEnregistrerRegle(array &$seance, int $numero, int $cm): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $correction = fgRegleValider($question, $cm);

    $seance['reponses'][] = $cm;
    fgEnregistrerSeance($seance);

    // La tolerance n'est mentionnee QUE si elle a servi. Dire « a 1 cm pres,
    // c'etait bon » a un eleve tombe pile lui fait croire qu'on l'a repeche.
    $explication = (string)$question['explication'];
    $tolerance = (int)$question['tolerance_cm'];
    if ($correction['correct'] && $tolerance > 0 && $correction['ecart'] > 0) {
        $explication .= ' Tu as dit ' . $cm . ' cm : à ' . $tolerance . ' cm près, c’est bon.';
    } elseif ($correction['correct'] && $correction['ecart'] === 0) {
        $explication .= ' Tu es tombé pile.';
    }

    return array(
        'juste' => $correction['correct'],
        'attendu' => (int)$question['longueur_cm'],
        'explication' => $explication,
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre les masses posees sur le plateau. La liste est gardee telle quelle
 * pour que le score final la rejoue - comme le programme du robot.
 */
function fgEnregistrerBalance(array &$seance, int $numero, array $posees): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    // Filtre etroit, comme pour le programme du robot : seules les masses que
    // la banque propose entrent en session. Sans lui, une valeur envoyee a la
    // main y resterait jusqu'au recomptage du score.
    $offertes = array_map('intval', $question['masses_offertes']);
    $posees = array_values(array_filter(array_map('intval', $posees),
        static function (int $m) use ($offertes): bool {
            return in_array($m, $offertes, true);
        }));
    $correction = fgBalanceValider($question, $posees);

    $seance['reponses'][] = $posees;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        // Le total pose ET le sens de l'erreur : un eleve qui s'est trompe doit
        // savoir s'il faut ajouter ou retirer, pas seulement que c'est faux.
        'total' => $correction['total'],
        'sens' => $correction['sens'],
        'masse' => (int)$question['masse_objet'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Repond « plus leger », « equilibre » ou « plus lourd » pour des masses posees,
 * SANS RIEN ENREGISTRER et SANS JAMAIS DIRE LA MASSE.
 *
 * C'est ce que fait une vraie balance : elle ne donne pas le poids, elle donne
 * le cote qui descend. Sans cette fonction, le client ne pouvait pas incliner
 * le fleau honnetement - il ne connait pas la masse de l'objet - et la premiere
 * version calculait donc l'inclinaison sur une CONSTANTE INVENTEE de 600 g. Le
 * fleau penchait au hasard, contredisait la correction (« Bravo, c'est juste »
 * sous une balance de travers) et n'enseignait rien, alors que lire
 * l'inclinaison EST la competence 3972. Signale par le responsable technique, capture a l'appui,
 * le 07/09/2026.
 *
 * RESERVE AU MODE « equilibre ». En mode « estimer », repondre au fil des poses
 * permettrait de trouver la masse par dichotomie en quelques essais, ce qui
 * viderait 3973 de son sens.
 */
function fgPeserBalance(array $seance, int $numero, array $posees): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    // La pesee n'a de sens que sur la manche en cours : peser une manche deja
    // repondue, ou une manche a venir, n'apprendrait rien et ouvrirait un
    // chemin pour sonder les questions suivantes avant de les jouer.
    if ($numero !== count($seance['reponses'])) {
        return null;
    }
    $question = $questions[$numero];
    if ((string)($question['mode_balance'] ?? '') !== 'equilibre') {
        return null;
    }

    $offertes = array_map('intval', $question['masses_offertes']);
    $total = 0;
    foreach ($posees as $m) {
        if (in_array((int)$m, $offertes, true)) {
            $total += (int)$m;
        }
    }
    $masse = (int)$question['masse_objet'];
    $ecart = $total - $masse;

    // SIX CRANS PAR COTE, PAS UN SEUL. La premiere version ne rendait que le
    // sens : la balance restait figee dans la meme position tant que
    // l'equilibre exact n'etait pas atteint, et poser une masse ne changeait
    // rien a l'ecran. Signale par le responsable technique le 07/09/2026 : « le premier poids
    // pose ne fait pas bouger la balance ».
    //
    // DOUZE CRANS PAR COTE, ET L'ECHELLE PORTE SUR LE RAPPORT POSE/CIBLE, pas
    // sur l'ecart. Deux essais ont echoue avant celui-la, tous deux mesures :
    //   - trois crans : sur un dictionnaire de 1200 g, poser 50, 200 puis 500 g
    //     laissait le fleau au meme cran ;
    //   - six crans sur l'ecart relatif : a vide la proportion vaut 1,00 et
    //     avec 50 g elle vaut 0,93 - deux valeurs trop proches pour tomber dans
    //     des crans differents, donc la premiere masse ne bougeait toujours pas.
    // Le rapport pose/cible, lui, part de zero : chaque masse ajoutee le fait
    // monter d'un pas visible. C'est aussi la grandeur physiquement juste - un
    // plateau vide est au maximum, un plateau a moitie rempli a mi-course.
    //
    // POURQUOI C'EST LEGITIME ICI. Voir qu'on s'approche EST le raisonnement de
    // la competence 3972 : ajuster par essais en lisant l'inclinaison. Ce n'est
    // PAS le cas du mode « estimer », d'ou le refus plus haut - la, approcher
    // par essais remplacerait l'estimation qu'on demande.
    //
    // L'ECART CHIFFRE NE SORT TOUJOURS PAS : douze crans sur une echelle
    // relative disent a quelle distance on est, jamais de combien de grammes.
    if ($ecart === 0) {
        $cran = 0;
    } elseif ($ecart < 0) {
        // Trop leger : le fleau part du maximum a vide et se redresse. `floor`
        // et non `round` : a 50 g sur 1200, l'echelle vaut 11,5 et l'arrondi la
        // ramenait a 12, c'est-a-dire a la position du plateau vide - la
        // premiere masse ne bougeait donc toujours pas sur les objets lourds.
        $cran = -max(1, (int)floor(12 * (1 - $total / max(1, $masse))));
    } else {
        // Trop lourd : meme echelle, bornee a douze au-dela du double.
        $cran = max(1, (int)round(12 * min(1, $ecart / max(1, $masse))));
    }

    return array(
        'sens' => $ecart === 0 ? 'juste' : ($ecart < 0 ? 'leger' : 'lourd'),
        'cran' => $cran,
    );
}

/**
 * Enregistre le nombre annonce, decompose en dizaines et unites.
 */
function fgEnregistrerCollection(array &$seance, int $numero, int $dizaines, int $unites): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    $correction = fgCollectionValider($question, $dizaines, $unites);

    $seance['reponses'][] = array('dizaines' => $dizaines, 'unites' => $unites);
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        'annonce' => $correction['annonce'],
        'dizaines_justes' => $correction['dizaines_justes'],
        'total' => (int)$question['total_jetons'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre le programme ecrit par l'eleve, et rend la correction. La reponse
 * n'est ni un index ni un point : c'est une SUITE D'ORDRES, gardee telle quelle
 * pour que le score final la rejoue - comme la boutique, l'horloge, la carte et
 * le compte est bon.
 */
function fgEnregistrerProgrammeGrille(array &$seance, int $numero, array $programme): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/mecaniques.php';
    $question = $questions[$numero];
    // Filtre volontairement etroit : seuls les trois ordres du jeu entrent en
    // session. Sans lui, n'importe quelle chaine envoyee a la main y resterait
    // jusqu'au recomptage du score.
    $programme = array_values(array_filter($programme, static function ($ordre): bool {
        return in_array($ordre, array('avance', 'gauche', 'droite'), true);
    }));
    $correction = fgGrilleValider($question, $programme);

    $seance['reponses'][] = $programme;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        // Le client rejoue le programme pour animer le robot ; il lui faut donc
        // savoir ce que le serveur a retenu, pas ce qu'il croit avoir envoye.
        'programme' => $programme,
        'cogne' => $correction['cogne'],
        'explication' => (string)$question['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre la position des aiguilles posee par l'eleve, et rend la
 * correction. Meme role que fgEnregistrerPiecesBoutique() pour l'horloge : la
 * reponse n'est pas un index choisi, mais deux positions d'aiguilles.
 */
function fgEnregistrerHeureHorloge(array &$seance, int $numero, int $heures, int $minutes): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    require_once __DIR__ . '/horloge.php';
    $manche = $questions[$numero];
    $correction = fgHorlogeValider($manche, $heures, $minutes);

    // Les aiguilles posees sont gardees telles quelles, pas le verdict : le
    // score final les revalide, comme pour la boutique et le compte est bon.
    $seance['reponses'][] = array('heures' => $heures, 'minutes' => $minutes);
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correction['correct'],
        'heures' => (int)$manche['heures'],
        'minutes' => (int)$manche['minutes'],
        'explication' => (string)$manche['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre l'ordre pose par l'eleve pour une manche de tri alphabetique, et
 * rend la correction. Meme role que fgEnregistrerPiecesBoutique() pour cette
 * mecanique : la reponse n'est pas un index, mais les cinq mots dans l'ordre
 * choisi.
 *
 * LA VALIDATION EST STRICTE PAR POSITION, pas par ensemble : les cinq mots
 * recus doivent correspondre EXACTEMENT, position par position, a
 * « ordre_correct ». Un ensemble juste mais mal range compte faux, ce qui est
 * precisement ce que l'exercice verifie.
 */
function fgEnregistrerOrdreMots(array &$seance, int $numero, array $ordrePose): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    $manche = $questions[$numero];
    $attendu = $manche['ordre_correct'];

    // Les mots recus doivent etre exactement ceux de cette manche, ni plus ni
    // moins, ni un mot invente : on ne compare jamais deux textes ici, on
    // verifie une permutation d'un ensemble connu.
    $recus = array_values(array_map('strval', $ordrePose));
    $recusTries = $recus;
    sort($recusTries);
    $attenduTries = $attendu;
    sort($attenduTries);
    $ensembleValide = $recusTries === $attenduTries;

    $correct = $ensembleValide && $recus === $attendu;

    $seance['reponses'][] = $recus;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correct,
        'ordre_correct' => $attendu,
        'explication' => (string)$manche['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Enregistre la reponse tapee par l'eleve pour un son a identifier, et rend
 * la correction. Un seul essai, comme toutes les mecaniques de Fast Games -
 * le jeu d'origine (School Monsters) en autorisait cinq avec des indices
 * progressifs, mais aucune autre mecanique du site ne fonctionne ainsi :
 * repondre une fois, voir la correction, passer a la suite. Rester coherent
 * avec le reste du site l'a emporte sur reproduire le detail du jeu source.
 *
 * La comparaison passe par fgEcouteNormaliser() (mecaniques.php) : accents,
 * casse, apostrophes et espaces multiples ne comptent pas. Plusieurs
 * reponses peuvent etre valides pour un meme son (« oiseau » et « oiseaux »
 * par exemple) : il suffit d'egaler UNE seule d'entre elles.
 */
function fgEnregistrerReponseEcoute(array &$seance, int $numero, string $reponseTexte): ?array
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null || !isset($questions[$numero])) {
        return null;
    }
    if ($numero !== count($seance['reponses'])) {
        return null;
    }

    $manche = $questions[$numero];
    $normalisee = fgEcouteNormaliser($reponseTexte);
    $correct = false;
    foreach ($manche['reponses_valides'] as $valide) {
        if ($normalisee !== '' && $normalisee === fgEcouteNormaliser($valide)) {
            $correct = true;
            break;
        }
    }

    $seance['reponses'][] = $reponseTexte;
    fgEnregistrerSeance($seance);

    return array(
        'juste' => $correct,
        'reponse_attendue' => (string)$manche['reponses_valides'][0],
        'explication' => (string)$manche['explication'],
        'termine' => count($seance['reponses']) >= (int)$seance['total'],
    );
}

/**
 * Compte le score a partir des reponses gardees en session.
 */
function fgScoreSeance(array $seance): ?int
{
    $questions = fgSeanceQuestions($seance);
    if ($questions === null) {
        return null;
    }
    $score = 0;
    if ((string)($seance['jeu'] ?? '') === 'compte-est-bon') {
        require_once __DIR__ . '/compte-est-bon.php';
        foreach ($seance['reponses'] as $numero => $etapes) {
            if (isset($questions[$numero]) && is_array($etapes)
                    && fgCompteEstBonValider($questions[$numero], $etapes)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'boutique') {
        require_once __DIR__ . '/boutique.php';
        foreach ($seance['reponses'] as $numero => $pieces) {
            if (isset($questions[$numero]) && is_array($pieces)
                    && fgBoutiqueValider($questions[$numero], $pieces)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'droite') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $valeur) {
            if (isset($questions[$numero]) && is_numeric($valeur)
                    && fgDroiteValider($questions[$numero], (float)$valeur)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'carte') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $point) {
            if (isset($questions[$numero]) && is_array($point)
                    && fgCarteValider($questions[$numero],
                                      (float)($point['lat'] ?? 999),
                                      (float)($point['lon'] ?? 999))['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'graphie') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $composee) {
            if (isset($questions[$numero]) && is_string($composee)
                    && fgGraphieValider($questions[$numero], $composee)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'probleme') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $etapes) {
            if (isset($questions[$numero]) && is_array($etapes)
                    && fgProblemeValider($questions[$numero], $etapes)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'partage') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $dessin) {
            if (isset($questions[$numero]) && is_array($dessin)
                    && fgPartageValider($questions[$numero],
                                        (int)($dessin['parts'] ?? 0),
                                        (int)($dessin['coloriees'] ?? -1))['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'graphique') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $hauteurs) {
            if (isset($questions[$numero]) && is_array($hauteurs)
                    && fgGraphiqueValider($questions[$numero], $hauteurs)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'regle') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $cm) {
            if (isset($questions[$numero]) && is_numeric($cm)
                    && fgRegleValider($questions[$numero], (int)$cm)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'balance') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $posees) {
            if (isset($questions[$numero]) && is_array($posees)
                    && fgBalanceValider($questions[$numero], $posees)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'collection') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $annonce) {
            if (isset($questions[$numero]) && is_array($annonce)
                    && fgCollectionValider($questions[$numero],
                                           (int)($annonce['dizaines'] ?? -1),
                                           (int)($annonce['unites'] ?? -1))['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'grille') {
        require_once __DIR__ . '/mecaniques.php';
        foreach ($seance['reponses'] as $numero => $programme) {
            if (isset($questions[$numero]) && is_array($programme)
                    && fgGrilleValider($questions[$numero], $programme)['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'horloge') {
        require_once __DIR__ . '/horloge.php';
        foreach ($seance['reponses'] as $numero => $aiguilles) {
            if (isset($questions[$numero]) && is_array($aiguilles)
                    && fgHorlogeValider($questions[$numero],
                                        (int)($aiguilles['heures'] ?? -1),
                                        (int)($aiguilles['minutes'] ?? -1))['correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'ordre') {
        // Meme regle que fgEnregistrerOrdreMots() : l'ordre pose doit
        // correspondre EXACTEMENT, position par position.
        foreach ($seance['reponses'] as $numero => $ordrePose) {
            if (isset($questions[$numero]) && is_array($ordrePose)
                    && array_values($ordrePose) === $questions[$numero]['ordre_correct']) {
                $score++;
            }
        }
        return $score;
    }
    if ((string)($seance['jeu'] ?? '') === 'ecoute') {
        // Meme regle que fgEnregistrerReponseEcoute() : egaler une seule des
        // reponses valides, une fois normalisee.
        foreach ($seance['reponses'] as $numero => $reponseTexte) {
            if (!isset($questions[$numero]) || !is_string($reponseTexte)) {
                continue;
            }
            $normalisee = fgEcouteNormaliser($reponseTexte);
            foreach ($questions[$numero]['reponses_valides'] as $valide) {
                if ($normalisee !== '' && $normalisee === fgEcouteNormaliser($valide)) {
                    $score++;
                    break;
                }
            }
        }
        return $score;
    }
    foreach ($seance['reponses'] as $numero => $choix) {
        if (isset($questions[$numero]) && (int)$choix === (int)$questions[$numero]['bonne']) {
            $score++;
        }
    }
    return $score;
}

/**
 * Ferme la seance. Appelee une fois le resultat enregistre, elle interdit de
 * renvoyer deux fois le meme jeu.
 */
function fgFermerSeance(string $identifiant = ''): void
{
    if ($identifiant === '') {
        // Repli des sessions ouvertes avant le 10/09/2026, ou l'appelant ne
        // pouvait pas nommer la seance : il n'y en avait qu'une.
        $_SESSION['fastgames_seances'] = array();
        unset($_SESSION['fastgames_seance']);
        return;
    }
    fgOublierSeance($identifiant);
}

/**
 * Le rythme d'envoi est-il raisonnable ?
 *
 * Sans cette garde, un eleve peut poster des resultats en boucle et fabriquer
 * lui-meme la moyenne de la classe. Les deux bornes sont larges a dessein :
 * elles arretent la boucle, pas l'eleve qui enchaine les parties.
 *
 * @return string Vide si l'envoi est accepte, sinon la raison du refus.
 */
function fgRefusDeCadence(): string
{
    $jour = date('Y-m-d');
    $suivi = $_SESSION['fastgames_cadence'] ?? array();
    if (($suivi['jour'] ?? '') !== $jour) {
        $suivi = array('jour' => $jour, 'nombre' => 0, 'dernier' => 0);
    }
    if ($suivi['dernier'] > 0 && time() - (int)$suivi['dernier'] < FG_DELAI_ENTRE_RESULTATS) {
        return 'Doucement : attends quelques secondes avant d’enregistrer une nouvelle partie.';
    }
    if ((int)$suivi['nombre'] >= FG_MAX_RESULTATS_JOUR) {
        return 'Tu as déjà enregistré beaucoup de parties aujourd’hui. Reviens demain !';
    }
    return '';
}

/**
 * Prend acte d'un enregistrement accepte, pour la garde ci-dessus.
 */
function fgNoterCadence(): void
{
    $jour = date('Y-m-d');
    $suivi = $_SESSION['fastgames_cadence'] ?? array();
    if (($suivi['jour'] ?? '') !== $jour) {
        $suivi = array('jour' => $jour, 'nombre' => 0, 'dernier' => 0);
    }
    $suivi['nombre'] = (int)$suivi['nombre'] + 1;
    $suivi['dernier'] = time();
    $_SESSION['fastgames_cadence'] = $suivi;
}
