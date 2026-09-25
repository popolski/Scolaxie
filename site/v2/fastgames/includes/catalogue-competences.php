<?php
/**
 * LE CATALOGUE PAR COMPETENCE.
 *
 * Decide avec le responsable technique le 01/09/2026 : le catalogue descend jusqu'a la
 * competence, sur quatre matieres et le seul programme 2026/2027. Ce fichier
 * lit le referentiel de Fast Eval et dit, pour chaque competence, dans quel
 * etat elle se trouve.
 *
 * TROIS ETATS, PAS DEUX - la lecon retenue en qualifiant les 185 competences
 * le 31/08/2026. Une competence est :
 *   - jouable   : une banque de contenu la couvre reellement (data/banques.php) ;
 *   - bientot   : elle pourrait se jouer un jour - soit competences-jouables.php
 *                 lui donne deja une mecanique, mais aucune banque n'est encore
 *                 ecrite ; soit elle n'a meme pas encore ete examinee (le cas de
 *                 Mathematiques et Francais aujourd'hui) ;
 *   - nesejouepas : marquee explicitement dans competences-jouables.php comme
 *                 hors de portee d'un mini-jeu, avec la raison.
 * Sans le deuxieme etat, l'ecran afficherait une liste a moitie grise et
 * l’enseignante attendrait des jeux qui ne viendront jamais. Sans le troisieme, elle
 * se demanderait pourquoi certaines competences ont disparu.
 *
 * POURQUOI « JOUABLE » NE REGARDE PAS fgTypeGenerateur(). Les huit generateurs
 * historiques devinent le jeu a partir de mots trouves dans l'intitule, et
 * cette detection s'est revelee fausse dans plus d'un cas sur trois lors de la
 * mesure du 31/08/2026 - y compris en Francais (« Ecrire un texte de six ou
 * sept phrases » tombait sur des multiplications). Les qualifier a la main est
 * un travail qui reste a faire pour Mathematiques et Francais : en attendant,
 * mieux vaut les montrer « bientot » que de risquer un jeu faux.
 */

require_once __DIR__ . '/referentiel.php';
require_once __DIR__ . '/mecaniques.php';
require_once __DIR__ . '/places-banques.php';

/**
 * Les quatre matieres retenues, dans l'ordre choisi par le responsable technique le 01/09/2026.
 * Toute matiere absente de cette liste - EPS, EMC, arts - n'est jamais
 * chargee : ce n'est pas un oubli si elle manque, c'est la decision.
 */
function fgMatieresCatalogue(): array
{
    return array('Mathématiques', 'Français', 'Histoire-Géographie', 'Sciences et Technologie');
}

/**
 * Le slug d'une matiere retenue, pour des URL propres (?matiere=sciences),
 * et son inverse. Un slug inconnu rend null plutot qu'une matiere au hasard :
 * jamais deviner a la place de dire qu'on ne sait pas.
 */
function fgSlugMatiere(string $matiere): string
{
    $slugs = array(
        'Mathématiques' => 'mathematiques',
        'Français' => 'francais',
        'Histoire-Géographie' => 'histoire-geo',
        'Sciences et Technologie' => 'sciences',
    );
    return $slugs[$matiere] ?? '';
}

function fgMatiereDepuisSlug(string $slug): ?string
{
    foreach (fgMatieresCatalogue() as $matiere) {
        if (fgSlugMatiere($matiere) === $slug) {
            return $matiere;
        }
    }
    return null;
}

/**
 * Les banques jouables d'une matiere, sans repetition, dans un ordre stable.
 *
 * Sert a l'ecran eleve : il choisit un jeu par son titre - « Qui mange quoi ? »
 * - jamais par l'intitule officiel de la competence, que fgCatalogueCompetences()
 * garde pour l'ecran enseignant.
 *
 * @param array $categoriesMatiere $catalogue[$matiere] tel que rendu par
 *              fgCatalogueCompetences()
 * @return array cles de banques, triees par titre
 */
function fgBanquesJouablesDeMatiere(array $categoriesMatiere): array
{
    $cles = array();
    foreach ($categoriesMatiere as $lignes) {
        foreach ($lignes as $ligne) {
            if ($ligne['etat'] !== 'jouable') {
                continue;
            }
            // TOUTES les banques de la competence, pas seulement la premiere -
            // voir le commentaire du 01/09/2026 dans fgCatalogueCompetences().
            foreach ($ligne['banques'] ?? array() as $cle) {
                $cles[$cle] = true;
            }
        }
    }
    $banques = fgBanques();
    $cles = array_keys($cles);
    // Les banques marquees 'nouveau' (repere manuel pose a la main quand une
    // banque est creee ou retouchee, voir sciences-regimes) remontent en
    // tete, dans le meme ordre alphabetique que le reste - pour que le responsable technique
    // retrouve facilement ce qui vient de changer sans parcourir toute la
    // liste. Demande le 06/09/2026, en illustrant electricite et zones
    // climatiques. Le marqueur reste pose tant que personne ne le retire :
    // pas de peremption automatique, une retouche future decidera si une
    // banque doit encore ressortir.
    usort($cles, static function (string $a, string $b) use ($banques): int {
        $nouveauA = !empty($banques[$a]['nouveau']);
        $nouveauB = !empty($banques[$b]['nouveau']);
        if ($nouveauA !== $nouveauB) {
            return $nouveauA ? -1 : 1;
        }
        return strnatcasecmp($banques[$a]['titre'] ?? $a, $banques[$b]['titre'] ?? $b);
    });
    return $cles;
}

/**
 * Les memes banques, mais rangees par categorie du referentiel - c'est deja
 * la clef de $categoriesMatiere, aucun classement a inventer : les sous-
 * rubriques montrees a l'eleve sont celles du programme officiel.
 * Une banque partagee par deux categories apparait dans les deux.
 *
 * @return array categorie => cles de banques, triees comme ailleurs
 */
function fgBanquesParCategorieDeMatiere(array $categoriesMatiere): array
{
    $groupes = array();
    foreach ($categoriesMatiere as $categorie => $lignes) {
        $cles = fgBanquesJouablesDeMatiere(array($lignes));
        if ($cles) {
            $groupes[$categorie] = $cles;
        }
    }
    uksort($groupes, 'strnatcasecmp');
    return $groupes;
}

/**
 * Un pictogramme par sous-rubrique, choisi sur un mot-cle du libelle. Les
 * categories viennent du referentiel saisi a la main : leur intitule exact
 * varie, d'ou une recherche par mot-cle plutot qu'une table figee, et un
 * repere neutre quand rien ne correspond.
 * ponytail: heuristique par mot-cle ; passer a une table par categorie si le
 * referentiel se stabilise.
 */
function fgPictoCategorie(string $categorie): string
{
    $texte = fgNormaliserNomMatiere($categorie);
    $motsCles = array(
        'nombre' => 'nombre', 'calcul' => 'nombre', 'numeration' => 'nombre',
        'grandeur' => 'grandeur', 'mesure' => 'grandeur',
        'espace' => 'geometrie', 'geometrie' => 'geometrie',
        'probleme' => 'probleme', 'resoudre' => 'probleme',
        'temps' => 'temps', 'heure' => 'temps',
        'monnaie' => 'monnaie',
        'donnee' => 'donnee', 'graphique' => 'donnee',
    );
    foreach ($motsCles as $mot => $icone) {
        if (str_contains($texte, $mot)) {
            return $icone;
        }
    }
    return 'picto-defaut';
}

/**
 * Compare deux noms de matiere sans se laisser arreter par les accents ni la
 * casse. Le referentiel en contient deja des variantes a un accent pres -
 * « Education Physique et Sportive » existe deux fois dans le fonds 2015.
 */
function fgNormaliserNomMatiere(string $texte): string
{
    $sans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
    if ($sans === false) {
        $sans = $texte;
    }
    return strtolower(trim((string)preg_replace('~[^a-zA-Z ]~', '', $sans)));
}

/** Vrai si $matiereBrute (ex. « Mathématiques (programmes 2026/2027) ») est une des quatre retenues. */
function fgMatiereRetenue(string $matiereBrute): ?string
{
    if (!str_contains($matiereBrute, '2026')) {
        return null;
    }
    $courte = trim((string)preg_replace('~\s*\(programmes?[^)]*\)\s*~i', '', $matiereBrute));
    $normalisee = fgNormaliserNomMatiere($courte);
    foreach (fgMatieresCatalogue() as $retenue) {
        if (fgNormaliserNomMatiere($retenue) === $normalisee) {
            return $retenue;
        }
    }
    return null;
}

/**
 * Toutes les references (competences et connaissances) des quatre matieres,
 * programme 2026/2027, avec leur etat de jeu.
 *
 * @return array matiere => categorie => array de references, chacune avec
 *               id, type, libelle (nettoye), etat, banque (cle ou null),
 *               raison (si nesejouepas)
 */
function fgCatalogueCompetences(PDO $db): array
{
    $qualifications = require dirname(__DIR__) . '/data/competences-jouables.php';
    $placesVues = array();
    $catalogue = array();
    foreach (fgMatieresCatalogue() as $matiere) {
        $catalogue[$matiere] = array();
    }

    foreach (array('competence', 'connaissance') as $type) {
        $configuration = fgConfigurationReferentiel($type);
        $filtre = $configuration['filtre'];
        $parametres = array();
        if ($type === 'competence') {
            fgAjouterFiltreNiveaux($filtre, $parametres);
        }
        $sql = 'SELECT ' . $configuration['id'] . ' AS id, commentaire AS libelle, matiere, categorie'
             . ' FROM ' . $configuration['table'] . ' WHERE ' . $filtre;
        $requete = $db->prepare($sql);
        $requete->execute($parametres);

        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $matiereRetenue = fgMatiereRetenue((string)$ligne['matiere']);
            if ($matiereRetenue === null) {
                continue;
            }
            $id = (int)$ligne['id'];
            $categorie = trim((string)$ligne['categorie']) !== '' ? (string)$ligne['categorie'] : 'Autres';

            $banques = fgBanquesDeCompetence($id);
            $qualif = $qualifications[$id] ?? null;

            if ($banques) {
                $etat = 'jouable';
                // $banque (singulier) reste la PREMIERE banque, pour
                // couverture-programme.php qui n'a besoin que d'un lien -
                // n'importe laquelle des banques prouve que la competence se
                // joue. $clesBanques (pluriel) porte TOUTES les banques : sans
                // lui, quand plusieurs banques couvrent la meme competence -
                // les trois themes de vocabulaire transposes de School
                // Monsters partagent tous la 3856 - fgBanquesJouablesDeMatiere()
                // n'en remontait qu'une seule au catalogue eleve, les autres
                // restant injouables sans qu'aucune erreur ne le signale.
                // Trouve le 01/09/2026 en verifiant le rendu, pas en le lisant.
                $banque = array_key_first($banques);
                $clesBanques = array_keys($banques);
                $raison = null;
                // La place est relevee TANT QUE LA COMPETENCE VIT : c'est la
                // seule occasion de la connaitre sans l'inventer. Voir
                // places-banques.php pour ce qu'elle sert a rattraper.
                foreach ($clesBanques as $cleBanque) {
                    $placesVues[$cleBanque] = array('matiere' => $matiereRetenue, 'categorie' => $categorie);
                }
            } elseif ($qualif !== null && !empty($qualif['special'])) {
                // UN JEU SPECIAL COUVRE CETTE COMPETENCE : la boutique, le
                // compte est bon, l'horloge. Ils ont leur propre moteur et ne
                // sont pas des banques, donc rien ici ne les voyait - une
                // competence couverte par la boutique restait affichee
                // « ne se joue pas », avec sa raison d'origine (« manipulation
                // de pieces et billets »), alors que le jeu existait depuis le
                // 04/09/2026. L'ecran de l’enseignante annoncait donc injouable ce
                // qui se jouait. Trouve le 06/09/2026 en branchant l'horloge,
                // qui serait tombee dans le meme trou.
                $etat = 'jouable';
                $banque = null;
                $clesBanques = array();
                $raison = null;
            } elseif ($qualif !== null && $qualif['mecanique'] === null) {
                $etat = 'nesejouepas';
                $banque = null;
                $clesBanques = array();
                $raison = (string)($qualif['raison'] ?? '');
            } else {
                // Soit qualifiee mais sans banque ecrite, soit pas encore
                // examinee : les deux se traduisent par la meme carte grisee,
                // l’enseignante n'a pas besoin de distinguer les deux cas.
                $etat = 'bientot';
                $banque = null;
                $clesBanques = array();
                $raison = null;
            }

            $catalogue[$matiereRetenue][$categorie][] = array(
                'id' => $id,
                'type' => $type,
                'libelle' => fgNettoyerLibelle((string)$ligne['libelle']),
                'etat' => $etat,
                'banques' => $clesBanques,
                'banque' => $banque,
                // Rempli uniquement pour un jeu special (boutique, horloge...) :
                // couverture-programme.php en fait un lien jeu.php?special=...
                // la ou une banque donne jeu.php?banque=...
                'special' => (string)($qualif['special'] ?? ''),
                'raison' => $raison,
            );
        }
    }

    // Un jeu ne quitte pas le catalogue parce que sa competence a ete
    // supprimee : il garde la place relevee de son vivant. Le releve et le
    // rattrapage vivent dans places-banques.php.
    if (fgAssurerTablePlacesBanques($db)) {
        $placesConnues = fgPlacesBanques($db);
        fgEnregistrerPlacesBanques($db, $placesVues, $placesConnues);
        // Une banque retiree de l'offre (arbitrage V1, DEC-08) ne revient pas
        // par le rattrapage des orphelines : sa competence peut avoir disparu,
        // ce n'est pas une raison de la reproposer.
        $banquesOffertes = array_filter(fgBanques(), static fn(array $banque): bool => empty($banque['hors_offre']));
        $orphelines = fgBanquesSansCompetence($db, $banquesOffertes);
        if ($orphelines) {
            $catalogue = fgRangerBanquesOrphelines($catalogue, $orphelines, $placesVues + $placesConnues, $banquesOffertes);
        }
    }

    // Tri stable : categories dans leur ordre d'apparition en base (deja
    // proche de l'ordre pedagogique), competences jouables d'abord dans
    // chaque categorie, puis bientot, puis ce qui ne se joue pas.
    $ordreEtat = array('jouable' => 0, 'bientot' => 1, 'nesejouepas' => 2);
    foreach ($catalogue as $matiere => $categories) {
        foreach ($categories as $categorie => $lignes) {
            usort($lignes, static function (array $a, array $b) use ($ordreEtat): int {
                return $ordreEtat[$a['etat']] <=> $ordreEtat[$b['etat']];
            });
            $catalogue[$matiere][$categorie] = $lignes;
        }
    }

    return $catalogue;
}

/**
 * Petits chiffres pour l'en-tete d'une matiere : combien de competences,
 * combien jouables. Recalcules a partir du catalogue deja charge, jamais
 * d'une seconde requete.
 */
function fgResumeMatiere(array $categories): array
{
    $total = 0;
    $jouables = 0;
    foreach ($categories as $lignes) {
        foreach ($lignes as $ligne) {
            $total++;
            if ($ligne['etat'] === 'jouable') {
                $jouables++;
            }
        }
    }
    return array('total' => $total, 'jouables' => $jouables);
}
