<?php
require_once __DIR__ . '/qualification-resultats.php';

/** Un palier ancien sans provenance vérifiable reste inconnu. */
function fgPalierConnu(array $resultat): ?int
{
    return in_array($resultat['jeu'], array('compte-est-bon', 'boutique', 'horloge'), true)
        && ($resultat['version_progression'] ?? '') === 'depart-20260913-v1'
        && isset($resultat['niveau_depart'])
        && (int)$resultat['niveau_depart'] >= 1
        && (int)$resultat['niveau_depart'] <= 5
        ? (int)$resultat['niveau_depart'] : null;
}

/** Référentiel historique lisible indépendamment du filtre de niveau du catalogue. */
function fgReferencesRapport(PDO $db, array $resultats): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn($r) => (int)($r['id_reference'] ?? 0), $resultats))));
    if (!$ids) return array();
    $q = $db->prepare('SELECT id_comp AS id, commentaire AS libelle, matiere, categorie FROM comp_type WHERE id_comp IN ('.implode(',', array_fill(0, count($ids), '?')).')');
    $q->execute($ids);
    $references = array();
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $references[(int)$r['id']] = $r;
    return $references;
}

/** Aucun seuil pédagogique et aucune reconstruction de niveau dans le rapport. */
function fgConstruireRapport(array $resultats, array $banques, array $references, array $liens = array()): array
{
    usort($resultats, static fn($a, $b) => strcmp($a['date_enr'], $b['date_enr']) ?: ((int)($a['id_resultat'] ?? 0) <=> (int)($b['id_resultat'] ?? 0)));
    $rapport = array('parties' => count($resultats), 'jeux' => array(), 'competences' => array(),
        'sans_attribution' => array(), 'jours_actifs' => 0, 'premiere' => null, 'derniere' => null,
        'indicateur' => 'Peu de données', 'tendance' => null);
    $jours = array();
    foreach ($resultats as $r) {
        $q = fgQualifierResultat($r, $banques, $references, $liens);
        $cle = $q['activite'];
        $jours[substr($r['date_enr'], 0, 10)] = true;
        $rapport['premiere'] ??= $r['date_enr'];
        $rapport['derniere'] = $r['date_enr'];
        if (!isset($rapport['jeux'][$cle])) $rapport['jeux'][$cle] = array('titre' => $q['titre'], 'parties' => 0, 'series' => array(), 'references' => array(), 'dernier' => null);
        $jeu =& $rapport['jeux'][$cle];
        $jeu['parties']++;
        // Les scores restent séparés par mécanique, dénominateur et palier connu.
        // Un niveau inconnu ne signifie pas un niveau constant ou égal aux nouveaux.
        $palier = fgPalierConnu($r);
        $valide = is_numeric($r['score']) && is_numeric($r['total']) && $r['total'] > 0 && $r['score'] >= 0 && $r['score'] <= $r['total'];
        $observation = array('score' => $r['score'], 'total' => $r['total'], 'date' => $r['date_enr'], 'palier' => $palier, 'valide' => $valide);
        $jeu['dernier'] = $observation;
        $serieCle = json_encode(array($r['jeu'], $r['total'], $palier, $r['version_progression'] ?? null, $q['statut'], $r['id_reference'] ?? null));
        if (!isset($jeu['series'][$serieCle])) $jeu['series'][$serieCle] = array('parties' => 0, 'somme' => 0, 'valides' => 0, 'meilleur' => null, 'total' => $r['total'], 'palier' => $palier, 'adaptatif' => in_array($r['jeu'], array('compte-est-bon','boutique','horloge'), true), 'statut' => $q['statut'], 'relation' => $q['reference']['libelle'] ?? $q['motif']);
        $s =& $jeu['series'][$serieCle];
        $s['parties']++;
        if ($valide) { $s['valides']++; $s['somme'] += (int)$r['score']; $s['meilleur'] = max($s['meilleur'] ?? 0, (int)$r['score']); }
        unset($s);
        if ($q['statut'] === 'explicite') {
            $id = (int)$r['id_reference'];
            $jeu['references'][$id] = $q['reference']['libelle'];
            if (!isset($rapport['competences'][$id])) $rapport['competences'][$id] = array('reference' => $q['reference'], 'parties' => 0, 'jeux' => array());
            $rapport['competences'][$id]['parties']++;
            $rapport['competences'][$id]['jeux'][$cle] = $q['titre'];
        } else {
            if (!isset($rapport['sans_attribution'][$cle])) $rapport['sans_attribution'][$cle] = array('titre' => $q['titre'], 'parties' => 0, 'motifs' => array());
            $rapport['sans_attribution'][$cle]['parties']++;
            $rapport['sans_attribution'][$cle]['motifs'][$q['motif']] = true;
        }
        unset($jeu);
    }
    $rapport['jours_actifs'] = count($jours);
    return $rapport;
}

function fgScoreRapport(array $score): string
{
    return $score['valide'] ? $score['score'].' / '.$score['total'] : 'Résultat non exploitable';
}

function fgSeriesRapport(array $jeu): string
{
    // La colonne du jeu affiche déjà son lien ; le répéter ne sert qu'à distinguer des séries aux liens différents.
    $plusieursLiens = count(array_unique(array_column($jeu['series'], 'relation'))) > 1;
    $textes = array();
    foreach ($jeu['series'] as $s) {
        $texte = ($plusieursLiens ? $s['relation']."\n" : '').$s['parties'].' partie(s), sur '.$s['total'];
        // Seuls les trois jeux adaptatifs ont un niveau : le mentionner ailleurs faisait croire à une donnée perdue.
        if ($s['adaptatif']) $texte .= $s['palier'] !== null ? ', niveau de départ '.$s['palier'] : ', niveau de départ inconnu';
        if ($s['valides']) $texte .= ' : meilleur '.$s['meilleur'].' / '.$s['total'].', moyenne brute '.number_format($s['somme']/$s['valides'], 2, ',', ' ').' / '.$s['total'];
        if ($s['valides'] !== $s['parties']) $texte .= ' ; '.($s['parties']-$s['valides']).' résultat(s) non exploitable(s)';
        $textes[] = $texte;
    }
    return implode("\n", $textes);
}
