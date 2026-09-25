<?php

/**
 * Transforme uniquement les references que Fast Games sait traiter avec des
 * regles verifiables. Une reference ambiguë reste hors de la seance : mieux
 * vaut trois jeux fiables qu'une activite inventee au hasard.
 */
function fgTypeGenerateur(array $reference): ?string
{
    $texte = fgTexteComparable(($reference['libelle'] ?? '') . ' ' . ($reference['categorie'] ?? '') . ' ' . ($reference['matiere'] ?? ''));
    if (str_contains($texte, 'addition') || str_contains($texte, 'ajouter') || str_contains($texte, 'somme')) {
        return 'addition';
    }
    if (str_contains($texte, 'soustraction') || str_contains($texte, 'soustraire') || str_contains($texte, 'difference')) {
        return 'soustraction';
    }
    if (str_contains($texte, 'multiplication') || str_contains($texte, 'table de multiplication') || str_contains($texte, 'produit')) {
        return 'multiplication';
    }
    if (str_contains($texte, 'double') || str_contains($texte, 'moitie')) {
        return 'double_moitie';
    }
    if (str_contains($texte, 'calcul mental')) {
        return 'calcul';
    }
    if ((str_contains($texte, 'comparer') || str_contains($texte, 'ranger') || str_contains($texte, 'ordonner') || str_contains($texte, 'decomposer'))
        && str_contains($texte, 'nombre')) {
        return 'nombres';
    }
    if (str_contains($texte, 'conjug') && str_contains($texte, 'present')) {
        return 'conjugaison_present';
    }
    if ((str_contains($texte, 'accord') && (str_contains($texte, 'sujet') || str_contains($texte, 'verbe')))
        || str_contains($texte, 'relation sujet-verbe') || str_contains($texte, 'relation sujetverbe')) {
        return 'accord_sujet_verbe';
    }
    return null;
}

function fgConstruireSeance(PDO $db, int $tirage = 0): array
{
    $groupes = array();
    foreach (fgToutesReferencesActives($db) as $reference) {
        $type = fgTypeGenerateur($reference);
        if ($type !== null) {
            $groupes[$type][] = $reference;
        }
    }

    $mathematiques = array_values(array_intersect(
        array('addition', 'soustraction', 'multiplication', 'double_moitie', 'calcul', 'nombres'),
        array_keys($groupes)
    ));
    $francais = array_values(array_intersect(
        array('conjugaison_present', 'accord_sujet_verbe'),
        array_keys($groupes)
    ));
    $graine = date('Y-m-d') . '|' . max(0, $tirage);
    $typesChoisis = array();

    if ($francais) {
        $typesChoisis[] = $francais[fgEntierStable($graine . '|francais', 0, count($francais) - 1)];
    }
    fgMelangerStable($mathematiques, $graine . '|maths');
    foreach ($mathematiques as $type) {
        if (count($typesChoisis) >= 3) {
            break;
        }
        $typesChoisis[] = $type;
    }
    foreach (array_keys($groupes) as $type) {
        if (count($typesChoisis) >= 3) {
            break;
        }
        if (!in_array($type, $typesChoisis, true)) {
            $typesChoisis[] = $type;
        }
    }

    $seance = array();
    foreach ($typesChoisis as $rang => $type) {
        $references = $groupes[$type];
        $index = fgEntierStable($graine . '|' . $type . '|' . $rang, 0, count($references) - 1);
        $reference = $references[$index];
        $reference['generateur'] = $type;
        $reference['jeu'] = fgConfigurationGenerateur($type)['jeu'];
        $seance[] = $reference;
    }
    return $seance;
}

function fgToutesReferencesActives(PDO $db): array
{
    $references = array();
    foreach (array('competence', 'connaissance') as $type) {
        $configuration = fgConfigurationReferentiel($type);
        $filtre = $configuration['filtre'];
        $parametres = array();
        if ($type === 'competence') {
            fgAjouterFiltreNiveaux($filtre, $parametres);
        }
        $sql = 'SELECT ' . $configuration['id'] . ' AS id, designation AS code, commentaire AS libelle,'
             . ' matiere, categorie, ' . $configuration['cycle'] . ' AS cycle'
             . ' FROM ' . $configuration['table'] . ' WHERE ' . $filtre;
        $requete = $db->prepare($sql);
        $requete->execute($parametres);
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $ligne['id'] = (int)$ligne['id'];
            $ligne['type'] = $type;
            $references[] = $ligne;
        }
        $requete->closeCursor();
    }
    return $references;
}

function fgGenererJeuDepuisReference(array $reference, string $variation = ''): ?array
{
    $type = fgTypeGenerateur($reference);
    if ($type === null) {
        return null;
    }
    $configuration = fgConfigurationGenerateur($type);
    $graine = date('Y-m-d') . '|' . $reference['type'] . '|' . $reference['id'] . '|' . $variation;
    $questions = match ($type) {
        'addition' => fgQuestionsAddition($graine),
        'soustraction' => fgQuestionsSoustraction($graine),
        'multiplication' => fgQuestionsMultiplication($graine),
        'double_moitie' => fgQuestionsDoublesMoities($graine),
        'nombres' => fgQuestionsNombres($graine),
        'conjugaison_present' => fgQuestionsConjugaison($graine),
        'accord_sujet_verbe' => fgQuestionsAccords($graine),
        default => fgQuestionsCalcul($graine),
    };
    return array(
        'titre' => $configuration['titre'],
        'description' => $configuration['description'],
        'competence' => (string)$reference['libelle'],
        'duree' => '3 min',
        'ton' => $configuration['ton'],
        'questions' => $questions,
    );
}

function fgConfigurationGenerateur(string $type): array
{
    $configurations = array(
        'addition' => array('titre' => 'Additions flash', 'description' => 'Trois additions générées automatiquement au niveau CE1.', 'ton' => 'turquoise', 'jeu' => 'flash'),
        'soustraction' => array('titre' => 'Soustractions flash', 'description' => 'Trois soustractions sans résultat négatif.', 'ton' => 'turquoise', 'jeu' => 'flash'),
        'multiplication' => array('titre' => 'Défi des tables', 'description' => 'Tables de 2, 3, 4, 5 et 10 adaptées au CE1.', 'ton' => 'ambre', 'jeu' => 'flash'),
        'double_moitie' => array('titre' => 'Doubles et moitiés', 'description' => 'Calculs courts générés avec des résultats entiers.', 'ton' => 'ambre', 'jeu' => 'flash'),
        'calcul' => array('titre' => 'Calcul mental', 'description' => 'Additions, soustractions et multiplications courtes.', 'ton' => 'turquoise', 'jeu' => 'flash'),
        'nombres' => array('titre' => 'Défi des nombres', 'description' => 'Comparer, ranger et décomposer des nombres.', 'ton' => 'ambre', 'jeu' => 'intrus'),
        'conjugaison_present' => array('titre' => 'Conjugaison flash', 'description' => 'Conjuguer un verbe régulier au présent.', 'ton' => 'corail', 'jeu' => 'correction'),
        'accord_sujet_verbe' => array('titre' => "Corrige l'accord", 'description' => 'Choisir la phrase où le sujet et le verbe sont accordés.', 'ton' => 'corail', 'jeu' => 'correction'),
    );
    return $configurations[$type];
}

function fgQuestionsAddition(string $graine): array
{
    $questions = array();
    for ($i = 0; $i < 3; $i++) {
        $a = fgEntierStable($graine . '|a|' . $i, 12, 58);
        $b = fgEntierStable($graine . '|b|' . $i, 3, 99 - $a);
        $bon = $a + $b;
        $questions[] = fgQuestionNombre("$a + $b = ?", $bon, $graine . '|q|' . $i, "On additionne $a et $b : le résultat est $bon.");
    }
    return $questions;
}

function fgQuestionsSoustraction(string $graine): array
{
    $questions = array();
    for ($i = 0; $i < 3; $i++) {
        $a = fgEntierStable($graine . '|a|' . $i, 25, 99);
        $b = fgEntierStable($graine . '|b|' . $i, 2, $a - 1);
        $bon = $a - $b;
        $questions[] = fgQuestionNombre("$a - $b = ?", $bon, $graine . '|q|' . $i, "En retirant $b à $a, on obtient $bon.");
    }
    return $questions;
}

function fgQuestionsMultiplication(string $graine): array
{
    $tables = array(2, 3, 4, 5, 10);
    $questions = array();
    for ($i = 0; $i < 3; $i++) {
        $table = $tables[fgEntierStable($graine . '|t|' . $i, 0, count($tables) - 1)];
        $facteur = fgEntierStable($graine . '|f|' . $i, 2, 10);
        $bon = $table * $facteur;
        $questions[] = fgQuestionNombre("$table × $facteur = ?", $bon, $graine . '|q|' . $i, "$facteur groupes de $table font $bon.");
    }
    return $questions;
}

function fgQuestionsDoublesMoities(string $graine): array
{
    $questions = array();
    for ($i = 0; $i < 3; $i++) {
        $base = fgEntierStable($graine . '|n|' . $i, 4, 40);
        if ($i % 2 === 0) {
            $bon = $base * 2;
            $questions[] = fgQuestionNombre("Quel est le double de $base ?", $bon, $graine . '|q|' . $i, "Le double, c'est deux fois $base : $bon.");
        } else {
            $nombre = $base * 2;
            $questions[] = fgQuestionNombre("Quelle est la moitié de $nombre ?", $base, $graine . '|q|' . $i, "Deux fois $base font $nombre : sa moitié est $base.");
        }
    }
    return $questions;
}

function fgQuestionsCalcul(string $graine): array
{
    return array_merge(
        array_slice(fgQuestionsAddition($graine . '|add'), 0, 1),
        array_slice(fgQuestionsSoustraction($graine . '|sub'), 0, 1),
        array_slice(fgQuestionsMultiplication($graine . '|mul'), 0, 1)
    );
}

function fgQuestionsNombres(string $graine): array
{
    // Les quatre nombres etaient tires dans quatre tranches disjointes (10-29,
    // 30-49, 50-69, 70-89) : la reponse etait toujours le seul nombre a partir
    // de 70, reconnaissable sans comparer (arbitrage V1, DEC-06). Desormais :
    // un nombre de la MEME dizaine, un nombre en 9 de la dizaine d'avant
    // (plus d'unites mais moins de dizaines) et, quand c'est possible, les
    // memes chiffres inverses. Il faut regarder les dizaines, puis les unites.
    $dizaine = fgEntierStable($graine . '|grand|d', 3, 8);
    $unite = fgEntierStable($graine . '|grand|u', 4, 9);
    $plusGrand = $dizaine * 10 + $unite;
    $nombres = array(
        $plusGrand,
        $dizaine * 10 + fgEntierStable($graine . '|grand|u2', 0, $unite - 1),
        ($dizaine - 1) * 10 + 9,
        $unite < $dizaine
            ? $unite * 10 + $dizaine
            : ($dizaine - 1) * 10 + fgEntierStable($graine . '|grand|u3', 0, 8),
    );
    $q1 = fgQuestionChoix('Quel est le plus grand nombre ?', 'Compare les quatre nombres.', (string)$plusGrand, array_map('strval', array_slice($nombres, 1)), $graine . '|q1', "On compare d’abord les dizaines, puis les unités : $plusGrand est le plus grand des quatre nombres.");

    $a = fgEntierStable($graine . '|ordre|a', 12, 30);
    $suite = array($a, $a + 7, $a + 18, $a + 31);
    $bonOrdre = implode(' < ', $suite);
    $q2 = fgQuestionChoix('Quel rangement va du plus petit au plus grand ?', 'Observe le sens du signe <.', $bonOrdre, array(
        implode(' > ', $suite),
        implode(' < ', array_reverse($suite)),
        $suite[0] . ' < ' . $suite[2] . ' < ' . $suite[1] . ' < ' . $suite[3],
    ), $graine . '|q2', 'Dans l’ordre croissant, chaque nombre est plus grand que le précédent.');

    $dizaines = fgEntierStable($graine . '|dizaines', 2, 8);
    $unites = fgEntierStable($graine . '|unites', 1, 9);
    $bon = ($dizaines * 10) + $unites;
    $q3 = fgQuestionNombre("$dizaines dizaines et $unites unités, c'est…", $bon, $graine . '|q3', "$dizaines dizaines font " . ($dizaines * 10) . ", puis on ajoute $unites unités.");
    return array($q1, $q2, $q3);
}

function fgQuestionsConjugaison(string $graine): array
{
    $verbes = array('chanter' => 'chant', 'jouer' => 'jou', 'parler' => 'parl', 'regarder' => 'regard');
    $personnes = array(
        array('Je', 'e'), array('Tu', 'es'), array('Il', 'e'),
        array('Nous', 'ons'), array('Vous', 'ez'), array('Ils', 'ent'),
    );
    $questions = array();
    $noms = array_keys($verbes);
    for ($i = 0; $i < 3; $i++) {
        $verbe = $noms[fgEntierStable($graine . '|v|' . $i, 0, count($noms) - 1)];
        $personne = $personnes[fgEntierStable($graine . '|p|' . $i, 0, count($personnes) - 1)];
        $bon = $personne[0] . ' ' . $verbes[$verbe] . $personne[1];
        $mauvaises = array();
        foreach (array('e', 'es', 'ons', 'ez', 'ent') as $terminaison) {
            $forme = $personne[0] . ' ' . $verbes[$verbe] . $terminaison;
            if ($forme !== $bon) {
                $mauvaises[] = $forme;
            }
        }
        $questions[] = fgQuestionChoix("Conjugue « $verbe » au présent avec « " . mb_strtolower($personne[0], 'UTF-8') . ' ».', 'Choisis la forme correctement conjuguée.', $bon, $mauvaises, $graine . '|q|' . $i, "Au présent, on écrit « $bon ».");
    }
    return $questions;
}

function fgQuestionsAccords(string $graine): array
{
    $situations = array(
        array('Le chat', 'joue', 'Les chats', 'jouent'),
        array('La fille', 'chante', 'Les filles', 'chantent'),
        array("L'enfant", 'regarde', 'Les enfants', 'regardent'),
        array('Le chien', 'marche', 'Les chiens', 'marchent'),
    );
    $questions = array();
    for ($i = 0; $i < 3; $i++) {
        $s = $situations[fgEntierStable($graine . '|s|' . $i, 0, count($situations) - 1)];
        $pluriel = fgEntierStable($graine . '|p|' . $i, 0, 1) === 1;
        $bon = ($pluriel ? $s[2] . ' ' . $s[3] : $s[0] . ' ' . $s[1]) . '.';
        $mauvaises = array(
            ($pluriel ? $s[2] . ' ' . $s[1] : $s[0] . ' ' . $s[3]) . '.',
            ($pluriel ? $s[0] . ' ' . $s[3] : $s[2] . ' ' . $s[1]) . '.',
            // Au singulier, « sujet + verbe + nt » redonnait le premier leurre :
            // fgQuestionChoix() comblait alors le trou par « Aucune de ces
            // reponses 3 », une question sur deux (arbitrage V1, DEC-14). Le
            // leurre « verbe + s » est une vraie erreur d'accord, et distincte.
            ($pluriel ? $s[2] . ' ' . $s[3] . 's' : $s[0] . ' ' . $s[1] . 's') . '.',
        );
        $questions[] = fgQuestionChoix('Quelle phrase est correctement accordée ?', 'Repère le sujet, puis accorde le verbe.', $bon, $mauvaises, $graine . '|q|' . $i, "Le verbe s’accorde avec le sujet : « $bon »");
    }
    return $questions;
}

function fgQuestionNombre(string $question, int $bon, string $graine, string $explication): array
{
    $ecarts = array(-10, -2, 2, 10);
    $mauvaises = array();
    foreach ($ecarts as $ecart) {
        $valeur = max(0, $bon + $ecart);
        if ($valeur !== $bon && !in_array((string)$valeur, $mauvaises, true)) {
            $mauvaises[] = (string)$valeur;
        }
        if (count($mauvaises) === 3) {
            break;
        }
    }
    return fgQuestionChoix($question, 'Choisis la bonne réponse.', (string)$bon, $mauvaises, $graine, $explication);
}

function fgQuestionChoix(string $question, string $consigne, string $bonne, array $mauvaises, string $graine, string $explication): array
{
    $reponses = array_values(array_unique(array_merge(array($bonne), $mauvaises)));
    while (count($reponses) < 4) {
        $reponses[] = 'Aucune de ces réponses ' . count($reponses);
    }
    $reponses = array_slice($reponses, 0, 4);
    fgMelangerStable($reponses, $graine . '|reponses');
    return array(
        'question' => $question,
        'consigne' => $consigne,
        'reponses' => $reponses,
        'bonne' => array_search($bonne, $reponses, true),
        'explication' => $explication,
    );
}

function fgEntierStable(string $graine, int $minimum, int $maximum): int
{
    if ($maximum <= $minimum) {
        return $minimum;
    }
    $nombre = (int)sprintf('%u', crc32($graine));
    return $minimum + ($nombre % (($maximum - $minimum) + 1));
}

function fgMelangerStable(array &$valeurs, string $graine): void
{
    usort($valeurs, static function ($a, $b) use ($graine): int {
        return strcmp(hash('sha256', $graine . '|' . serialize($a)), hash('sha256', $graine . '|' . serialize($b)));
    });
}

function fgTexteComparable(string $texte): string
{
    $texte = mb_strtolower($texte, 'UTF-8');
    return strtr($texte, array(
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'œ' => 'oe',
    ));
}
