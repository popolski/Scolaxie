<?php

/** Qualification commune au rapport et au studio, sans accès base ni heuristique. */
function fgQualifierResultat(array $resultat, array $banques, array $references, array $liens = array()): array
{
    $categorie = (string)($resultat['categorie'] ?? '');
    $jeu = (string)($resultat['jeu'] ?? '');
    $generateur = (string)($resultat['generateur'] ?? '');
    $type = (string)($resultat['type_reference'] ?? '');
    $id = (int)($resultat['id_reference'] ?? 0);
    // Libellé unique pour l'enseignant ; le statut garde la distinction technique.
    $base = array('statut' => 'sans_attribution', 'reference' => null, 'motif' => 'Non relié à une compétence');

    if (isset($banques[$categorie])) {
        $banque = $banques[$categorie];
        $base['activite'] = 'banque:' . $categorie;
        $base['titre'] = (string)$banque['titre'];
        // Un identifiant conservé ne suffit pas : le chemin banque et sa relation
        // écrite doivent tous deux être vérifiables, sans réaffectation a posteriori.
        if ($jeu !== $banque['mecanique'] || $generateur !== $banque['mecanique']) {
            return $base;
        }
        if ($type !== 'competence' || $id <= 0
            || !in_array($id, array_map('intval', $banque['competences']), true)) {
            return $base;
        }
        $reference = $references[$id] ?? null;
        if (!$reference || trim((string)($reference['libelle'] ?? '')) === '') {
            return $base;
        }
        $base['statut'] = 'explicite';
        $base['reference'] = $reference;
        $base['motif'] = 'Chemin banque et identifiant compatibles avec la relation explicite actuelle';
        return $base;
    }

    $speciaux = array('compte-est-bon' => 'Le compte est bon',
        'boutique' => 'La petite boutique', 'horloge' => 'L’horloge');
    if (isset($speciaux[$jeu])) {
        $base['activite'] = 'special:' . $jeu;
        $base['titre'] = $speciaux[$jeu];
        // Pour ces jeux, seule vaut preuve la compétence choisie par un administrateur
        // (liens-competences.php), et seulement tant que ce choix reste en place.
        $reference = $references[$id] ?? null;
        if ($type === 'competence' && in_array($id, $liens[$jeu] ?? array(), true)
            && $reference && trim((string)($reference['libelle'] ?? '')) !== '') {
            $base['statut'] = 'explicite';
            $base['reference'] = $reference;
            $base['motif'] = 'Lien validé dans Fast Games';
            return $base;
        }
    } else {
        // Le tuple distingue les activités générées ; le gabarit seul les mélange.
        $base['activite'] = 'historique:' . json_encode(array(
            (string)($resultat['programme'] ?? ''), (string)($resultat['theme'] ?? ''),
            $categorie, $generateur, $jeu), JSON_UNESCAPED_UNICODE);
        $base['titre'] = $categorie !== '' ? $categorie : ($jeu !== '' ? $jeu : 'Activité historique');
    }
    if (isset($speciaux[$jeu]) || in_array($jeu, array('flash', 'intrus', 'correction'), true)) {
        // Une ancienne référence heuristique n'est jamais une preuve de compétence.
        $base['statut'] = ($id > 0 || $type !== '') ? 'sans_attribution' : 'transversal';
    }
    return $base;
}

/** Partition exclusive : une partie ne peut pas alimenter deux groupes. */
function fgPartitionnerResultats(array $resultats, array $banques, array $references, array $liens = array()): array
{
    $groupes = array('explicite' => array(), 'transversal' => array(), 'sans_attribution' => array());
    foreach ($resultats as $resultat) {
        $qualification = fgQualifierResultat($resultat, $banques, $references, $liens);
        $resultat['qualification'] = $qualification;
        $groupes[$qualification['statut']][] = $resultat;
    }
    return $groupes;
}
