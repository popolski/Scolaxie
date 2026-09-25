<?php

/**
 * Catalogue volontairement court. Les intitulés ci-dessous sont ceux que voit
 * l'enseignant ; ils ne recopient pas l'arborescence très détaillée du
 * référentiel Fast Éval.
 */
function fgCatalogueMiniJeux(): array
{
    return array(
        'maths' => array(
            'titre' => 'Mathématiques',
            'description' => 'Nombres, calcul et automatismes.',
            'categories' => array(
                'calcul' => array(
                    'titre' => 'Calcul mental',
                    'description' => 'Additions et soustractions rapides.',
                    // 'calcul' (« Calcul mental », une addition, une soustraction
                    // et une multiplication) retire de l'offre le 21/09/2026
                    // (arbitrage V1, DEC-05) : il recopie exactement les
                    // generateurs addition, soustraction et multiplication. Son
                    // moteur reste dans generateurs.php, pour relire et reprendre
                    // les parties deja jouees et pour le repli `default`.
                    'generateurs' => array('addition', 'soustraction'),
                    'icone' => 'flash',
                ),
                'tables' => array(
                    'titre' => 'Tables et doubles',
                    'description' => 'Multiplications, doubles et moitiés.',
                    'generateurs' => array('multiplication', 'double_moitie'),
                    'icone' => 'flash',
                ),
                'nombres' => array(
                    'titre' => 'Nombres',
                    'description' => 'Comparer, ranger et décomposer.',
                    'generateurs' => array('nombres'),
                    'icone' => 'intrus',
                ),
            ),
        ),
        'francais' => array(
            'titre' => 'Français',
            // « Grammaire » est hors offre depuis DEC-04 : le theme ne l'annonce plus.
            'description' => 'Conjugaison.',
            'categories' => array(
                'conjugaison' => array(
                    'titre' => 'Conjugaison',
                    'description' => 'Conjuguer des verbes au présent.',
                    'generateurs' => array('conjugaison_present'),
                    'icone' => 'correction',
                ),
                'grammaire' => array(
                    'titre' => 'Grammaire',
                    'description' => 'Accorder correctement le sujet et le verbe.',
                    'generateurs' => array('accord_sujet_verbe'),
                    'icone' => 'correction',
                    // Plus proposee depuis le 21/09/2026 (arbitrage V1, DEC-04) :
                    // la banque « La phrase juste » (francais-accord-sujet-verbe)
                    // fait le meme exercice, sur toutes les personnes. La
                    // categorie reste declaree pour que les anciennes adresses
                    // et les parties deja jouees gardent leur titre.
                    'hors_offre' => true,
                ),
            ),
        ),
    );
}

function fgProgrammeChoisi(?string $programme): string
{
    return $programme === '2015' ? '2015' : '2026';
}

function fgProgrammeReference(array $reference): string
{
    return str_contains(fgTexteComparable((string)($reference['matiere'] ?? '')), 'programmes 2026/2027')
        ? '2026'
        : '2015';
}

function fgConfigurationCategorie(string $theme, string $categorie): ?array
{
    $catalogue = fgCatalogueMiniJeux();
    if (!isset($catalogue[$theme]['categories'][$categorie])) {
        return null;
    }
    return $catalogue[$theme]['categories'][$categorie] + array(
        'theme' => $theme,
        'theme_titre' => $catalogue[$theme]['titre'],
    );
}

function fgLibelleCategorie(string $theme, string $categorie): string
{
    $configuration = fgConfigurationCategorie($theme, $categorie);
    if ($configuration !== null) {
        return $configuration['titre'];
    }
    // Un jeu de banque (tri/paires/ordre/ecoute/qcm) porte sa mecanique dans
    // $theme et la cle de banque dans $categorie (voir jeu.php) - absent du
    // catalogue theme+categorie ci-dessus, qui ne connait pas les banques.
    // Sans ce repli, le tableau de bord enseignant affichait la cle brute
    // ("Sciences-regimes" au lieu de "Qui mange quoi ?"). Trouve le
    // 06/09/2026 en verifiant si le tableau de bord suivait les banques.
    require_once __DIR__ . '/mecaniques.php';
    $banques = fgBanques();
    if (isset($banques[$categorie])) {
        return $banques[$categorie]['titre'];
    }
    return ucfirst($categorie);
}

function fgChoisirReferenceCategorie(PDO $db, string $programme, string $theme, string $categorie, int $tirage = 0): ?array
{
    $configuration = fgConfigurationCategorie($theme, $categorie);
    if ($configuration === null) {
        return null;
    }
    $compatibles = array();
    foreach (fgToutesReferencesActives($db) as $reference) {
        $generateur = fgTypeGenerateur($reference);
        if ($generateur === null
            || fgProgrammeReference($reference) !== fgProgrammeChoisi($programme)
            || !in_array($generateur, $configuration['generateurs'], true)) {
            continue;
        }
        $reference['generateur'] = $generateur;
        $compatibles[] = $reference;
    }
    if (!$compatibles) {
        return null;
    }
    $graine = date('Y-m-d') . '|' . fgProgrammeChoisi($programme) . '|' . $theme . '|' . $categorie . '|' . max(0, $tirage);
    return $compatibles[fgEntierStable($graine, 0, count($compatibles) - 1)];
}
