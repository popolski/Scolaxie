<?php

/**
 * Nettoie un libelle de competence AVANT AFFICHAGE, sans jamais toucher a ce
 * qui est stocke dans Fast Eval. Trouve le 01/09/2026, sur la premiere
 * competence d'histoire-geographie jouee dans Fast Games : « Caracteriser: []
 * les trois principales zones climatiques » - le [] est U+F09F, une puce
 * Wingdings collee depuis Word, sans glyphe dans une police normale. Verifie
 * sur les 185 references du programme 2026/2027 : 3 sont touchees, toutes en
 * histoire-geographie, probablement issues du meme document source.
 *
 * Le remplacement par une puce ordinaire est fait ICI, a la lecture pour
 * l'affichage, et nulle part ailleurs : la ligne en base reste intacte, et
 * Fast Eval continue de voir exactement ce qu'il voyait avant.
 */
function fgNettoyerLibelle(string $texte): string
{
    // U+E000 a U+F8FF : la zone d'usage prive Unicode, reservee a des
    // caracteres sans sens hors du logiciel qui les a produits - typiquement
    // les puces des polices Wingdings ou Symbol.
    return trim(preg_replace('~[\x{E000}-\x{F8FF}]~u', '•', $texte));
}

/**
 * Recherche en lecture seule dans le referentiel de la base Fast Eval ouverte.
 * Les noms de table restent dans une liste blanche : aucune valeur recue du
 * navigateur ne peut devenir un identifiant SQL.
 */
function fgChercherReferentiel(PDO $db, string $recherche, string $type = 'tous', int $limite = 50): array
{
    $recherche = trim(mb_substr($recherche, 0, 100, 'UTF-8'));
    $limite = max(1, min(50, $limite));
    $types = $type === 'competence' || $type === 'connaissance'
        ? array($type)
        : array('competence', 'connaissance');
    $parType = count($types) === 2 ? (int)ceil($limite / 2) : $limite;
    $resultats = array();
    $total = 0;

    foreach ($types as $typeCourant) {
        $configuration = fgConfigurationReferentiel($typeCourant);
        $filtre = $configuration['filtre'];
        $parametres = array();
        if ($typeCourant === 'competence') {
            fgAjouterFiltreNiveaux($filtre, $parametres);
        }
        if ($recherche !== '') {
            $filtre .= ' AND (commentaire LIKE :recherche OR designation LIKE :recherche OR matiere LIKE :recherche OR categorie LIKE :recherche)';
            $parametres[':recherche'] = '%' . $recherche . '%';
        }

        $requeteTotal = $db->prepare('SELECT COUNT(*) FROM ' . $configuration['table'] . ' WHERE ' . $filtre);
        $requeteTotal->execute($parametres);
        $total += (int)$requeteTotal->fetchColumn();
        $requeteTotal->closeCursor();

        $sql = 'SELECT ' . $configuration['id'] . ' AS id, designation AS code, commentaire AS libelle,'
             . ' matiere, categorie, ' . $configuration['cycle'] . ' AS cycle'
             . ' FROM ' . $configuration['table']
             . ' WHERE ' . $filtre
             . ' ORDER BY matiere ASC, categorie ASC, commentaire ASC LIMIT ' . $parType;
        $requete = $db->prepare($sql);
        $requete->execute($parametres);
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $ligne['id'] = (int)$ligne['id'];
            $ligne['type'] = $typeCourant;
            $ligne['libelle'] = fgNettoyerLibelle((string)$ligne['libelle']);
            $resultats[] = $ligne;
        }
        $requete->closeCursor();
    }

    usort($resultats, static function (array $a, array $b): int {
        $cleA = ($a['matiere'] ?? '') . '|' . ($a['categorie'] ?? '') . '|' . ($a['libelle'] ?? '');
        $cleB = ($b['matiere'] ?? '') . '|' . ($b['categorie'] ?? '') . '|' . ($b['libelle'] ?? '');
        return strnatcasecmp($cleA, $cleB);
    });

    return array('total' => $total, 'resultats' => array_slice($resultats, 0, $limite));
}

/**
 * Relit une reference avant de l'afficher dans un jeu. L'intitule provenant de
 * l'URL n'est jamais utilise : seul l'identifiant est accepte, puis le texte est
 * repris depuis Fast Eval.
 */
function fgTrouverReference(PDO $db, string $type, int $id): ?array
{
    if ($id <= 0 || ($type !== 'competence' && $type !== 'connaissance')) {
        return null;
    }
    $configuration = fgConfigurationReferentiel($type);
    $filtre = $configuration['filtre'];
    $parametres = array(':id' => $id);
    if ($type === 'competence') {
        fgAjouterFiltreNiveaux($filtre, $parametres);
    }
    $sql = 'SELECT ' . $configuration['id'] . ' AS id, designation AS code, commentaire AS libelle,'
         . ' matiere, categorie, ' . $configuration['cycle'] . ' AS cycle'
         . ' FROM ' . $configuration['table']
         . ' WHERE ' . $configuration['id'] . '=:id AND ' . $filtre . ' LIMIT 1';
    $requete = $db->prepare($sql);
    $requete->execute($parametres);
    $ligne = $requete->fetch(PDO::FETCH_ASSOC);
    $requete->closeCursor();
    if (!$ligne) {
        return null;
    }
    $ligne['id'] = (int)$ligne['id'];
    $ligne['type'] = $type;
    $ligne['libelle'] = fgNettoyerLibelle((string)$ligne['libelle']);
    return $ligne;
}

/** Une banque peut couvrir plusieurs niveaux ; retenir une référence autorisée. */
function fgReferenceDeBanque(PDO $db, array $banque): ?array
{
    // Le choix d'un administrateur pour le niveau de la classe passe avant l'ordre de la liste.
    $choisie = (int)($banque['liens'][fgNiveauReferentiel()] ?? 0);
    if ($choisie > 0 && ($reference = fgTrouverReference($db, 'competence', $choisie)) !== null) {
        return $reference;
    }
    foreach ($banque['competences'] as $id) {
        $reference = fgTrouverReference($db, 'competence', (int)$id);
        if ($reference !== null) return $reference;
    }
    return null;
}

function fgConfigurationReferentiel(string $type): array
{
    if ($type === 'connaissance') {
        return array(
            'table' => 'eval_type',
            'id' => 'id_eval',
            'cycle' => "''",
            'filtre' => "commentaire IS NOT NULL AND commentaire!='' AND niveau=1 AND archivee=0",
        );
    }
    return array(
        'table' => 'comp_type',
        'id' => 'id_comp',
        'cycle' => 'cycle',
        'filtre' => "commentaire IS NOT NULL AND commentaire!='' AND archivee=0",
    );
}

/**
 * Le niveau qui filtre le referentiel : CE1, CE2, ou rien du tout.
 *
 * PAR DEFAUT, CELUI DE LA CLASSE DE L'ENSEIGNANTE (`enseignant.classe1`). Mais
 * ce champ est une liste a DEUX choix, CE1 ou CE2 : une enseignante de double
 * niveau a donc ete forcee d'en designer un, et se retrouvait privee de la
 * moitie du referentiel sans aucun moyen d'y revenir - Fast Eval offre un
 * « Tous les niveaux », Fast Games n'offrait rien. Constate le 06/09/2026 en
 * cherchant pourquoi trois jeux neufs restaient invisibles.
 *
 * ELLE PEUT DONC CHOISIR, et son choix vit en SESSION plutot que dans l'URL :
 * la disponibilite d'un jeu est reverifiee a son lancement (fgTrouverReference),
 * et un choix qui ne vivrait que dans l'adresse du catalogue serait perdu au
 * premier clic sur une tuile.
 *
 * Un eleve herite de `classe_client` a son ouverture SSO. Un CE1 ne voit que
 * le CE1 ; un CE2 consolide aussi les acquis CE1 et voit les deux niveaux.
 */
function fgNiveauReferentiel(): string
{
    $choisi = strtoupper(trim((string)($_SESSION['fastgames_niveau'] ?? '')));
    if ($choisi === 'TOUS') {
        return '';
    }
    if (in_array($choisi, array('CE1', 'CE2'), true)) {
        return $choisi;
    }
    $niveau = strtoupper(trim((string)($_SESSION['classe_client'] ?? '')));
    return in_array($niveau, array('CE1', 'CE2'), true) ? $niveau : '';
}

/** Les niveaux autorises par la session courante, dans l'ordre pedagogique. */
function fgNiveauxReferentiel(): array
{
    if (fgEstEleve()) {
        $niveau = strtoupper(trim((string)($_SESSION['classe_client'] ?? '')));
        if ($niveau === 'CE1') {
            return array('CE1');
        }
        if ($niveau === 'CE2') {
            return array('CE1', 'CE2');
        }
        return array();
    }

    $niveau = fgNiveauReferentiel();
    return $niveau === '' ? array() : array($niveau);
}

/** Ajoute le meme filtre de niveau a chaque lecture de competence. */
function fgAjouterFiltreNiveaux(string &$filtre, array &$parametres): void
{
    $niveaux = fgNiveauxReferentiel();
    if (!$niveaux) {
        return;
    }
    $conditions = array("niveau='' ");
    foreach ($niveaux as $index => $niveau) {
        $parametre = ':niveau' . $index;
        $conditions[] = 'FIND_IN_SET(' . $parametre . ',niveau)';
        $parametres[$parametre] = $niveau;
    }
    $filtre .= ' AND (' . implode(' OR ', $conditions) . ')';
}

/**
 * Le niveau tel qu'il doit s'afficher dans le selecteur : 'CE1', 'CE2' ou
 * 'tous'. Distinct de fgNiveauReferentiel(), qui rend une chaine vide aussi
 * bien pour « tous les niveaux » que pour une classe non reconnue.
 */
function fgNiveauChoisi(): string
{
    $choisi = strtoupper(trim((string)($_SESSION['fastgames_niveau'] ?? '')));
    if ($choisi === 'TOUS') { return 'tous'; }
    if (in_array($choisi, array('CE1', 'CE2'), true)) { return $choisi; }
    $niveau = fgNiveauReferentiel();
    return $niveau === '' ? 'tous' : $niveau;
}

/**
 * Enregistre le niveau demande par l'enseignante. Refuse tout ce qui n'est pas
 * l'une des trois valeurs prevues, et ne fait rien pour un eleve : le
 * selecteur ne lui est pas propose, un parametre pose a la main ne doit pas
 * lui ouvrir une porte.
 */
function fgNoterNiveauDemande(): void
{
    if (!isset($_GET['niveau']) || !fgEstEnseignant()) {
        return;
    }
    $demande = strtoupper(trim((string)$_GET['niveau']));
    if (in_array($demande, array('CE1', 'CE2', 'TOUS'), true)) {
        $_SESSION['fastgames_niveau'] = $demande;
    }
}
