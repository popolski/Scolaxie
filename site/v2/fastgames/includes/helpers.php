<?php

function fgH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fgEstEnseignant(): bool
{
    return ($_SESSION['role'] ?? '') === 'enseignant';
}

function fgEstEleve(): bool
{
    return ($_SESSION['role'] ?? '') === 'eleve';
}

function fgNomComplet(): string
{
    $prenom = trim((string)($_SESSION['prenom_client'] ?? ''));
    $nom = trim((string)($_SESSION['nom_client'] ?? ''));
    return trim(ucfirst($prenom) . ' ' . mb_strtoupper($nom, 'UTF-8'));
}

function fgApercuEleve(): bool
{
    return fgEstEleve() || (fgEstEnseignant() && ($_GET['apercu'] ?? '') === 'eleve');
}

function fgSuffixeApercu(string $separateur = '?'): string
{
    return fgEstEnseignant() ? $separateur . 'apercu=eleve' : '';
}

function fgLibelleRubriqueEleve(string $rubrique): string
{
    if (fgEstEnseignant()) {
        return $rubrique;
    }
    $libelles = require __DIR__ . '/../data/rubriques-eleve.php';
    return $libelles[$rubrique] ?? $rubrique;
}

function fgRetourCatalogue(array $parametres): string
{
    require_once __DIR__ . '/catalogue-competences.php';
    $slug = $parametres['matiere'] ?? '';
    $matiere = is_string($slug) ? fgMatiereDepuisSlug($slug) : null;
    if ($slug !== '' && $matiere === null) {
        return 'catalogue.php';
    }
    $retour = array();
    if ($matiere !== null) {
        $retour['matiere'] = $slug;
    }
    $rubrique = $parametres['rubrique'] ?? '';
    if ($matiere !== null && is_string($rubrique) && $rubrique !== '') {
        $catalogue = fgCatalogueCompetences(bdd::connexion((string)$_SESSION['bdd']));
        $categories = array_keys(fgBanquesParCategorieDeMatiere($catalogue[$matiere] ?? array()));
        if ($matiere === 'Mathématiques') {
            $categories[] = 'Défis';
        }
        if (in_array($rubrique, $categories, true)) {
            $retour['rubrique'] = $rubrique;
        }
    }
    $recherche = $parametres['recherche'] ?? '';
    if (is_string($recherche)) {
        $recherche = trim(mb_substr($recherche, 0, 60, 'UTF-8'));
        if ($recherche !== '') {
            $retour['recherche'] = $recherche;
        }
    }
    return 'catalogue.php' . ($retour ? '?' . http_build_query($retour) : '');
}

function fgResultats(): array
{
    return isset($_SESSION['fastgames_resultats']) && is_array($_SESSION['fastgames_resultats'])
        ? $_SESSION['fastgames_resultats']
        : array();
}

/**
 * La progression DURABLE de l'eleve connecte, lue une seule fois par requete.
 *
 * POURQUOI ELLE EXISTE. fgResultats() ne rend que la session courante : un
 * eleve qui revenait le lendemain retrouvait un passeport vide alors que ses
 * parties etaient bien en base (FG-AUDIT-001). Cette lecture les recupere, sans
 * table ni colonne nouvelle - voir fgMeilleursResultatsEleve().
 *
 * ELLE NE CONCERNE QUE L'ELEVE. Une enseignante en apercu joue sans rien
 * enregistrer : lui rendre une progression durable lui ferait voir celle de
 * quelqu'un d'autre, ou rien du tout. Son passeport reste celui de sa session.
 */
function fgProgressionDurable(): array
{
    static $progression = null;
    if ($progression !== null) {
        return $progression;
    }
    $progression = array('categorie' => array(), 'jeu' => array());
    if (!fgEstEleve() || empty($_SESSION['bdd'])) {
        return $progression;
    }
    require_once __DIR__ . '/resultats.php';
    try {
        $progression = fgMeilleursResultatsEleve(
            bdd::connexion((string)$_SESSION['bdd']),
            (int)($_SESSION['id_enseignant'] ?? 0),
            (int)($_SESSION['id_eleve'] ?? 0)
        );
    } catch (Throwable $erreur) {
        // Base indisponible : le passeport retombe sur la session plutot que
        // de casser la page. Il montrera moins, jamais faux.
        error_log('FastGames progression durable illisible: ' . $erreur->getMessage());
    }
    return $progression;
}

/**
 * Le palier de depart d'un jeu a paliers, reconstruit depuis les parties deja
 * enregistrees. Meme raison que fgProgressionDurable() : sans lui, le compte
 * est bon, la boutique et l'horloge repartaient au niveau 1 a chaque nouvelle
 * session, alors que l'eleve avait deja gravi des crans (FG-AUDIT-002).
 *
 * Rend $min pour tout ce qui n'est pas un eleve connecte a une base : une
 * enseignante en apercu garde le comportement d'avant, un palier de session.
 */
function fgNiveauDepartDurable(string $jeu, int $min, int $max): int
{
    if (!fgEstEleve() || empty($_SESSION['bdd'])) {
        return $min;
    }
    require_once __DIR__ . '/resultats.php';
    try {
        return fgNiveauRejoue(
            bdd::connexion((string)$_SESSION['bdd']),
            (int)($_SESSION['id_enseignant'] ?? 0),
            (int)($_SESSION['id_eleve'] ?? 0),
            $jeu,
            $min,
            $max
        );
    } catch (Throwable $erreur) {
        error_log('FastGames palier durable illisible: ' . $erreur->getMessage());
        return $min;
    }
}

/**
 * Garde celui des deux resultats qui vaut le mieux, en proportion.
 * Meme regle que fgRetenirMeilleur() cote base : les mecaniques n'ont pas
 * toutes le meme nombre de questions, comparer les scores bruts serait faux.
 */
function fgResultatLeMeilleur(?array $a, ?array $b): ?array
{
    if ($a === null) { return $b; }
    if ($b === null) { return $a; }
    $tauxA = (int)$a['score'] / max(1, (int)($a['total'] ?? 1));
    $tauxB = (int)$b['score'] / max(1, (int)($b['total'] ?? 1));
    return $tauxB > $tauxA ? $b : $a;
}

function fgMeilleurScore(string $jeu): ?array
{
    $meilleur = fgProgressionDurable()['jeu'][$jeu] ?? null;
    foreach (fgResultats() as $resultat) {
        if (($resultat['jeu'] ?? '') !== $jeu) {
            continue;
        }
        $meilleur = fgResultatLeMeilleur($meilleur, $resultat);
    }
    return $meilleur;
}

/**
 * Meilleur score d'UNE banque precise, distincte de fgMeilleurScore().
 *
 * POURQUOI CETTE FONCTION EXISTE. Un jeu issu d'une banque enregistre son
 * resultat avec 'jeu' = la MECANIQUE ('tri'), pas la cle de la banque - voir
 * jeu.php, $idJeu = $banque['mecanique']. fgMeilleurScore('tri') melangerait
 * donc TOUTES les banques de tri entre elles (animaux, electricite,
 * climats, grammaire...) : un bon score sur les animaux masquerait un
 * mauvais score sur l'accord sujet-verbe. La cle de banque, elle, voyage
 * dans 'categorie' (voir jeu.php, $categorie = $banqueChoisie) : on filtre
 * donc sur cette clé stable, même si la banque change de mécanique.
 *
 * Signale par le responsable technique le 06/09/2026 en demandant si le passeport suivait
 * bien les jeux de banques - il ne le faisait pas du tout avant ce jour :
 * aucune ligne du passeport n'existait pour eux, meme regroupee.
 */
function fgMeilleurScoreBanque(string $categorie): ?array
{
    require_once __DIR__ . '/mecaniques.php';
    if (!isset(fgBanques()[$categorie])) return null;
    $meilleur = fgProgressionDurable()['categorie'][$categorie] ?? null;
    foreach (fgResultats() as $resultat) {
        if (($resultat['categorie'] ?? '') !== $categorie) {
            continue;
        }
        $meilleur = fgResultatLeMeilleur($meilleur, $resultat);
    }
    return $meilleur;
}

/**
 * Icone, couleur et phrase d'accroche par matiere - jamais de vocabulaire du
 * referentiel. Vivait uniquement dans catalogue.php ; deplacee ici le
 * 07/09/2026 pour que le passeport (qui regroupe desormais ses lignes par
 * matiere, demande de le responsable technique) reprenne exactement la meme palette au lieu
 * d'en tenir une seconde copie qui aurait fini par diverger.
 */
function fgVitrineMatiere(string $matiere): array
{
    $vitrines = array(
        'Mathématiques' => array('icone' => 'matiere-maths', 'ton' => 'turquoise', 'accroche' => 'Calcule, compare, amuse-toi avec les nombres !'),
        'Français' => array('icone' => 'matiere-francais', 'ton' => 'corail', 'accroche' => 'Lis, associe, joue avec les mots !'),
        'Histoire-Géographie' => array('icone' => 'matiere-histoire', 'ton' => 'ambre', 'accroche' => 'Voyage dans le temps et sur la carte !'),
        'Sciences et Technologie' => array('icone' => 'matiere-sciences', 'ton' => 'vert', 'accroche' => 'Observe, découvre, expérimente !'),
    );
    return $vitrines[$matiere] ?? array('icone' => 'picto-defaut', 'ton' => 'turquoise', 'accroche' => '');
}
