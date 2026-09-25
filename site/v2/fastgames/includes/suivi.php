<?php
/**
 * LE SUIVI PAR COMPETENCE.
 *
 * Le tableau de bord d'origine regarde les resultats par categorie large -
 * « Calcul mental », « Conjugaison ». C'est trop grossier pour preparer une
 * seance : l’enseignante a besoin de savoir QUELLE competence coince, et pour QUI.
 * Ce fichier interroge donc `id_reference`, l'identifiant de la competence
 * dans Fast Eval, que la table enregistre deja a chaque partie.
 *
 * UNE MAITRISE, PAS UNE MOYENNE. C'est la decision de fond de ce fichier.
 * Une moyenne noie les progres : un eleve qui echoue trois fois puis reussit
 * trois fois affiche 50 %, alors qu'il a compris. On ne regarde donc que les
 * TROIS DERNIERS essais sur une competence. Un eleve qui s'ameliore le voit,
 * un eleve qui a oublie aussi.
 *
 * LA FRAICHEUR COMPTE. Une competence reussie il y a trois mois n'est pas une
 * competence acquise aujourd'hui, surtout au CE1. Chaque ligne porte donc l'age
 * du dernier essai, et l'interface peut estomper ce qui date.
 *
 * CE FICHIER NE FAIT QUE LIRE. Aucune ecriture, et rien qui touche aux
 * bulletins : le responsable technique a tranche le 31/08/2026, Fast Games a son suivi, Fast Eval
 * a le sien, et il n'y a pas de pont entre les deux.
 */

require_once __DIR__ . '/resultats.php';
require_once __DIR__ . '/rapport.php';
require_once __DIR__ . '/liens-competences.php';
require_once __DIR__ . '/mecaniques.php';

function fgEssaisQualifiesSuivi(PDO $db, int $idEnseignant, int $jours): array
{
    $jours = max(1, min(365, $jours));
    $q = $db->prepare('SELECT * FROM fastgames_resultats WHERE id_enseignant=? AND date_enr >= DATE_SUB(NOW(), INTERVAL '.$jours.' DAY) ORDER BY date_enr DESC,id_resultat DESC');
    $q->execute(array($idEnseignant));
    $essais = $q->fetchAll(PDO::FETCH_ASSOC);
    return fgPartitionnerResultats($essais, fgBanques(), fgReferencesRapport($db, $essais), fgIdsLiens(fgLiensCompetences($db)));
}

/* QUATRE NIVEAUX, ET D'OU VIENNENT CES SEUILS.
 *
 * Ce sont ceux du livret scolaire, deja utilises par Fast Eval : son generateur
 * de bulletins imprime « NA : Non Atteint | PA : Partiellement Atteint |
 * A : Atteint | D : Depasse ». l’enseignante lit donc cette grille tous les jours, et
 * lui en imposer une seconde n'aurait aucun sens.
 *
 * MAIS LES MOTS SONT DIFFERENTS, ET C'EST VOULU. le responsable technique a tranche : Fast Games
 * n'a aucun lien avec le bulletin. Employer le vocabulaire du livret laisserait
 * croire que les deux communiquent, ou qu'un score de jeu vaut evaluation. Neuf
 * questions de mini-jeu disent qu'un automatisme est en place, pas qu'une
 * competence est validee. Meme grille, memes seuils, autres mots.
 *
 * LE SEUIL HAUT EST A 90 ET NON A 85. Sur trois essais de trois questions, un
 * eleve ne peut obtenir que dix scores : 0, 11, 22, 33, 44, 56, 67, 78, 89 ou
 * 100 %. A 85, le niveau « maitrise » ne couvrirait qu'UNE valeur possible, 7
 * sur 9 : on passerait de fragile a tres a l'aise en gagnant deux reponses. A
 * 90, il en couvre deux, 7 et 8, et « tres a l'aise » devient le sans-faute.
 * Le jour ou les jeux passeront a dix questions, la granularite tombera a 3,3 %
 * et 85 redeviendra utilisable. */
const FG_SEUIL_FRAGILE = 50;
const FG_SEUIL_MAITRISE = 70;
const FG_SEUIL_ALAISE = 90;

/** Au-dela, un resultat est considere comme ancien et signale comme tel. */
const FG_JOURS_FRAICHEUR = 45;

/**
 * Traduit un taux de reussite en niveau.
 *
 * La cle rendue est technique et stable ; le libelle affiche passe par
 * fgLibelleNiveau(). Les deux sont separes pour qu'un changement de mot ne
 * demande pas de toucher aux comparaisons ni a la base.
 */
function fgNiveauMaitrise(?float $taux): string
{
    if ($taux === null) {
        return 'jamais';
    }
    if ($taux < FG_SEUIL_FRAGILE) {
        return 'reprendre';
    }
    if ($taux < FG_SEUIL_MAITRISE) {
        return 'fragile';
    }
    return $taux < FG_SEUIL_ALAISE ? 'maitrise' : 'alaise';
}

/** Le mot montre a l'enseignante. Voir le commentaire des seuils : ce ne sont
 *  volontairement pas ceux du livret scolaire. */
function fgLibelleNiveau(string $niveau): string
{
    // Ces cinq chaines sont LUES PAR l’enseignante, pas par le code : les cles
    // internes ('reprendre', 'maitrise', ...) restent sans accent et rien ne
    // compare ces libelles par egalite de texte. Trois d'entre eux etaient
    // ecrits sans accent alors que le tableau « Par eleve » de studio.php
    // ecrivait bien « A reprendre » et « Maitrisees ou a l'aise » : les deux
    // orthographes cohabitaient sur la meme page (FG-AUDIT-019).
    $mots = array(
        'reprendre' => 'À reprendre',
        'fragile' => 'Fragile',
        'maitrise' => 'Maîtrise',
        'alaise' => 'Très à l’aise',
        'jamais' => 'Jamais joué',
    );
    return $mots[$niveau] ?? $niveau;
}

/** Les quatre niveaux, du plus faible au plus fort, pour initialiser un compteur. */
function fgNiveaux(): array
{
    return array('reprendre', 'fragile', 'maitrise', 'alaise');
}

/**
 * Les trois derniers essais de chaque couple eleve/competence.
 *
 * La requete ramene les resultats bruts et le calcul se fait en PHP : MySQL 5.7
 * cible ne fournit pas toujours les fonctions de fenetrage utiles en SQL,
 * et le volume reste petit - une classe de vingt eleves qui joue toute l'annee
 * tient tres largement en memoire.
 */
function fgLireEssais(PDO $db, int $idEnseignant, int $jours = 180): array
{
    try {
        return fgEssaisQualifiesSuivi($db, $idEnseignant, $jours)['explicite'];
    } catch (PDOException $e) {
        error_log('FastGames : suivi temporairement indisponible.');
        return array();
    }
}

/**
 * Regroupe les essais par couple eleve/competence et calcule la maitrise.
 *
 * @return array cle « idEleve|type|idRef » => taux, niveau, essais, age en jours
 */
function fgMaitrises(array $essais, int $derniers = 3): array
{
    $paquets = array();
    foreach ($essais as $e) {
        $cle = $e['id_eleve'] . '|' . $e['type_reference'] . '|' . $e['id_reference'];
        if (!isset($paquets[$cle])) {
            $paquets[$cle] = array();
        }
        // Les essais arrivent du plus recent au plus ancien : on ne garde que
        // les premiers, donc les derniers joues.
        if (count($paquets[$cle]) < $derniers) {
            $paquets[$cle][] = $e;
        }
    }

    $maitrises = array();
    foreach ($paquets as $cle => $liste) {
        $points = 0;
        $sur = 0;
        foreach ($liste as $e) {
            $points += (int)$e['score'];
            $sur += (int)$e['total'];
        }
        $taux = $sur > 0 ? round($points * 100 / $sur, 1) : null;
        $dernier = $liste[0];
        $age = (int)floor((time() - strtotime((string)$dernier['date_enr'])) / 86400);
        list($idEleve, $type, $idRef) = explode('|', $cle);
        $maitrises[$cle] = array(
            'id_eleve' => (int)$idEleve,
            'type_reference' => $type,
            'id_reference' => (int)$idRef,
            'matiere' => (string)($dernier['theme'] ?? ''),
            'taux' => $taux,
            'niveau' => fgNiveauMaitrise($taux),
            'essais' => count($liste),
            'age_jours' => $age,
            'ancien' => $age > FG_JOURS_FRAICHEUR,
            'dernier' => (string)$dernier['date_enr'],
        );
    }
    return $maitrises;
}

/**
 * Vue par competence : qui l'a acquise, qui doit encore la travailler.
 * C'est la vue qui sert a preparer une seance ou a constituer un groupe.
 */
function fgVueParCompetence(PDO $db, int $idEnseignant, int $jours = 180): array
{
    $maitrises = fgMaitrises(fgLireEssais($db, $idEnseignant, $jours));
    $parCompetence = array();
    foreach ($maitrises as $m) {
        $cle = $m['type_reference'] . '|' . $m['id_reference'];
        if (!isset($parCompetence[$cle])) {
            $parCompetence[$cle] = array(
                'type_reference' => $m['type_reference'],
                'id_reference' => $m['id_reference'],
                'matiere' => $m['matiere'],
                'eleves' => 0,
                'niveaux' => array_fill_keys(fgNiveaux(), 0),
                'anciens' => 0,
                'detail' => array(),
            );
        }
        $c =& $parCompetence[$cle];
        $c['eleves']++;
        if (isset($c['niveaux'][$m['niveau']])) {
            $c['niveaux'][$m['niveau']]++;
        }
        if ($m['ancien']) {
            $c['anciens']++;
        }
        $c['detail'][] = $m;
        unset($c);
    }

    // Les competences qui coincent le plus d'abord : c'est ce qu'on vient
    // chercher sur cet ecran.
    uasort($parCompetence, static function (array $a, array $b): int {
        if ($a['niveaux']['reprendre'] !== $b['niveaux']['reprendre']) {
            return $b['niveaux']['reprendre'] <=> $a['niveaux']['reprendre'];
        }
        return $b['eleves'] <=> $a['eleves'];
    });
    return array_values($parCompetence);
}

/**
 * Vue par eleve : la fiche d'un enfant, toutes competences confondues.
 */
function fgVueParEleve(PDO $db, int $idEnseignant, int $jours = 180): array
{
    $maitrises = fgMaitrises(fgLireEssais($db, $idEnseignant, $jours));
    $parEleve = array();
    foreach ($maitrises as $m) {
        $id = $m['id_eleve'];
        if (!isset($parEleve[$id])) {
            $parEleve[$id] = array(
                'id_eleve' => $id,
                'prenom' => '',
                'nom' => '',
                'competences' => 0,
                'niveaux' => array_fill_keys(fgNiveaux(), 0),
                'dernier' => '',
                'ancien_global' => false,
                'detail' => array(),
            );
        }
        $e =& $parEleve[$id];
        $e['competences']++;
        if (isset($e['niveaux'][$m['niveau']])) {
            $e['niveaux'][$m['niveau']]++;
        }
        if ($m['dernier'] > $e['dernier']) {
            $e['dernier'] = $m['dernier'];
            $e['ancien_global'] = $m['ancien'];
        }
        $e['detail'][] = $m;
        unset($e);
    }

    if ($parEleve) {
        // Les noms viennent de Fast Eval, jamais recopies dans Fast Games.
        $marques = implode(',', array_fill(0, count($parEleve), '?'));
        try {
            $noms = $db->prepare(
                'SELECT id_eleve, prenom, nom FROM classe
                  WHERE id_enseignant = ? AND id_eleve IN (' . $marques . ')'
            );
            $noms->execute(array_merge(array($idEnseignant), array_keys($parEleve)));
            foreach ($noms->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                $id = (int)$ligne['id_eleve'];
                if (isset($parEleve[$id])) {
                    $parEleve[$id]['prenom'] = (string)$ligne['prenom'];
                    $parEleve[$id]['nom'] = (string)$ligne['nom'];
                }
            }
        } catch (PDOException $exception) {
            error_log('FastGames noms d eleves indisponibles: ' . $exception->getMessage());
        }
    }

    uasort($parEleve, static function (array $a, array $b): int {
        if ($a['niveaux']['reprendre'] !== $b['niveaux']['reprendre']) {
            return $b['niveaux']['reprendre'] <=> $a['niveaux']['reprendre'];
        }
        return strnatcasecmp($a['prenom'] . $a['nom'], $b['prenom'] . $b['nom']);
    });
    return array_values($parEleve);
}

/**
 * LE SUIVI DES JEUX TRANSVERSAUX - le compte est bon, la petite boutique.
 *
 * Ni l'un ni l'autre n'est rattache a une competence du referentiel : leur
 * `id_reference` vaut toujours NULL, et fgLireEssais() les exclut donc
 * explicitement (WHERE id_reference > 0). Plutot que de leur inventer une
 * fausse competence pour les faire entrer dans le meme moule - ce qui
 * laisserait croire qu'ils testent UNE notion precise du programme, alors
 * qu'ils travaillent un automatisme transversal - ce bloc reprend le meme
 * calcul de maitrise (trois derniers essais, memes seuils) mais regroupe par
 * la colonne `jeu` elle-meme. Decision de le responsable technique, 01/09/2026.
 *
 * Ajouter ici un futur jeu transversal suffit a le faire apparaitre dans ce
 * suivi separe, sans toucher a fgVueParCompetence() ni a son gabarit.
 */
function fgJeuxTransversaux(): array
{
    return array(
        'compte-est-bon' => 'Le compte est bon',
        'boutique' => 'La petite boutique',
        // LES TROIS GABARITS DES GENERATEURS HISTORIQUES, ajoutes le
        // 10/09/2026. Leurs parties n'enregistrent plus de competence : le
        // lien etait devine dans l'intitule, et se trompait dans plus d'un cas
        // sur deux (FG-AUDIT-017). Sans cette entree, elles disparaitraient de
        // tout suivi - c'est justement ce qu'il ne fallait pas.
        //
        // La cle est le GABARIT, pas le generateur : les huit generateurs se
        // presentent sous ces trois formes, et c'est ce que porte la colonne
        // 'jeu'. Les trois jeux fixes de data/jeux.php partagent ces memes
        // identifiants et se rangent donc au meme endroit, ce qui est juste :
        // eux non plus ne declarent aucune competence.
        'flash' => 'Mini-jeux de calcul (générateurs)',
        'intrus' => 'Mini-jeux de nombres (générateurs)',
        'correction' => 'Mini-jeux de français (générateurs)',
    );
}

/** Les trois derniers essais de chaque jeu transversal, memes bornes que fgLireEssais(). */
function fgLireEssaisTransversaux(PDO $db, int $idEnseignant, int $jours = 180): array
{
    try {
        $groupes = fgEssaisQualifiesSuivi($db, $idEnseignant, $jours);
        $essais = array_merge($groupes['transversal'], $groupes['sans_attribution']);
        usort($essais, static fn($a, $b) => strcmp($b['date_enr'], $a['date_enr']) ?: ($b['id_resultat'] <=> $a['id_resultat']));
        return $essais;
    } catch (PDOException $e) {
        error_log('FastGames : suivi transversal temporairement indisponible.');
        return array();
    }
}

/** Regroupe les essais transversaux par eleve/jeu - meme calcul que fgMaitrises(). */
function fgMaitrisesTransversales(array $essais, int $derniers = 3): array
{
    $paquets = array();
    foreach ($essais as $e) {
        $cle = $e['id_eleve'] . '|' . ($e['qualification']['activite'] ?? $e['jeu']);
        if (!isset($paquets[$cle])) {
            $paquets[$cle] = array();
        }
        if (count($paquets[$cle]) < $derniers) {
            $paquets[$cle][] = $e;
        }
    }

    $maitrises = array();
    foreach ($paquets as $cle => $liste) {
        $points = 0;
        $sur = 0;
        foreach ($liste as $e) {
            $points += (int)$e['score'];
            $sur += (int)$e['total'];
        }
        $taux = $sur > 0 ? round($points * 100 / $sur, 1) : null;
        $dernier = $liste[0];
        $age = (int)floor((time() - strtotime((string)$dernier['date_enr'])) / 86400);
        list($idEleve, $jeu) = explode('|', $cle, 2);
        $maitrises[$cle] = array(
            'id_eleve' => (int)$idEleve,
            'jeu' => $jeu,
            'libelle' => $dernier['qualification']['titre'] ?? $jeu,
            'taux' => $taux,
            'niveau' => fgNiveauMaitrise($taux),
            'essais' => count($liste),
            'age_jours' => $age,
            'ancien' => $age > FG_JOURS_FRAICHEUR,
            'dernier' => (string)$dernier['date_enr'],
        );
    }
    return $maitrises;
}

/**
 * Vue par jeu transversal : meme forme de retour que fgVueParCompetence(),
 * pour que studio.php reutilise exactement le meme gabarit d'affichage.
 */
function fgVueParJeuTransversal(PDO $db, int $idEnseignant, int $jours = 180): array
{
    $maitrises = fgMaitrisesTransversales(fgLireEssaisTransversaux($db, $idEnseignant, $jours));
    $libelles = fgJeuxTransversaux();
    $parJeu = array();
    foreach ($maitrises as $m) {
        $cle = $m['jeu'];
        if (!isset($parJeu[$cle])) {
            $parJeu[$cle] = array(
                'jeu' => $cle,
                'libelle' => $m['libelle'] ?? $libelles[$cle] ?? $cle,
                'eleves' => 0,
                'niveaux' => array_fill_keys(fgNiveaux(), 0),
                'anciens' => 0,
                'detail' => array(),
            );
        }
        $c =& $parJeu[$cle];
        $c['eleves']++;
        if (isset($c['niveaux'][$m['niveau']])) {
            $c['niveaux'][$m['niveau']]++;
        }
        if ($m['ancien']) {
            $c['anciens']++;
        }
        $c['detail'][] = $m;
        unset($c);
    }

    uasort($parJeu, static function (array $a, array $b): int {
        if ($a['niveaux']['reprendre'] !== $b['niveaux']['reprendre']) {
            return $b['niveaux']['reprendre'] <=> $a['niveaux']['reprendre'];
        }
        return $b['eleves'] <=> $a['eleves'];
    });
    return array_values($parJeu);
}

/**
 * Les prenoms de la classe, indexes par identifiant.
 *
 * La vue par competence affiche les eleves sous chaque ligne : sans ce
 * dictionnaire elle n'aurait que des identifiants. Les noms restent dans Fast
 * Eval, Fast Games ne fait que les lire au moment de l'affichage.
 */
function fgPrenomsEleves(PDO $db, int $idEnseignant): array
{
    try {
        $requete = $db->prepare(
            'SELECT id_eleve, prenom FROM classe WHERE id_enseignant = :id_enseignant'
        );
        $requete->execute(array(':id_enseignant' => $idEnseignant));
        $prenoms = array();
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $prenoms[(int)$ligne['id_eleve']] = (string)$ligne['prenom'];
        }
        return $prenoms;
    } catch (PDOException $exception) {
        error_log('FastGames prenoms indisponibles: ' . $exception->getMessage());
        return array();
    }
}

/**
 * Complete les vues avec l'intitule des competences, lu dans Fast Eval.
 *
 * Les intitules ne sont jamais recopies dans la table de Fast Games : si l’enseignante
 * corrige le libelle d'une competence, la correction se voit ici aussitot.
 */
function fgAjouterIntitules(PDO $db, array $lignes): array
{
    require_once __DIR__ . '/referentiel.php';
    $cache = array();
    foreach ($lignes as &$ligne) {
        $cle = $ligne['type_reference'] . '|' . $ligne['id_reference'];
        if (!array_key_exists($cle, $cache)) {
            $reference = fgReferencesRapport($db, array($ligne))[(int)$ligne['id_reference']] ?? null;
            $cache[$cle] = $reference;
        }
        $reference = $cache[$cle];
        $ligne['libelle'] = $reference['libelle'] ?? 'Competence retiree du referentiel';
        $ligne['categorie'] = $reference['categorie'] ?? '';
        $ligne['matiere_reelle'] = $reference['matiere'] ?? '';
    }
    unset($ligne);
    return $lignes;
}
