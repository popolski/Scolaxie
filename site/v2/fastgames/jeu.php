<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/seance.php';
if (fgEstEnseignant() && !$fgApercuEleve) {
    header('Location: catalogue.php');
    exit;
}

$programme = isset($_GET['programme']) ? (string)$_GET['programme'] : '';
$theme = isset($_GET['theme']) ? (string)$_GET['theme'] : '';
$categorie = isset($_GET['categorie']) ? (string)$_GET['categorie'] : '';
// Sans 'tirage' dans l'URL - donc a chaque lancement depuis le catalogue,
// par opposition au bouton "Rejouer" qui le fournit explicitement - la graine
// ne variait qu'avec la date du jour : DEUX lancements le meme jour tombaient
// donc sur exactement la meme partie, meme ordre de questions compris. Signale
// par le responsable technique le 06/09/2026 ("j'ai l'impression que ce sont toujours les memes
// questions"), confirme en rejouant la meme graine deux fois en CLI. Un vrai
// tirage aleatoire quand l'URL n'en precise pas, pour tous les jeux : cette
// valeur alimente les quatre chemins plus bas (banque, special, genere depuis
// le referentiel), un seul correctif suffit.
$tirage = isset($_GET['tirage']) ? max(0, min(99, (int)$_GET['tirage'])) : random_int(0, 99);
$reference = null;
$jeuGenere = false;
$generateur = '';
$banqueChoisie = isset($_GET['banque']) ? (string)$_GET['banque'] : '';
$special = isset($_GET['special']) ? (string)$_GET['special'] : '';
$seanceReprise = isset($_GET['reprendre']) && $_GET['reprendre'] === '1' ? fgSeanceAReprendre() : null;
if (isset($_GET['reprendre']) && $_GET['reprendre'] === '1' && $seanceReprise === null) {
    header('Location: catalogue.php');
    exit;
}

/**
 * CE QUE CETTE ADRESSE DEMANDE, tirage exclu.
 *
 * Sert a reconnaitre une partie deja commencee sur la meme activite quand la
 * page est simplement rechargee. Le tirage n'en fait pas partie : c'est
 * justement ce qui distingue « la meme activite » de « une nouvelle partie ».
 */
$appelDemande = $banqueChoisie !== '' ? 'banque:' . $banqueChoisie
    : ($special !== '' ? 'special:' . $special
    : ($theme !== '' && $categorie !== '' ? 'generateur:' . $programme . '|' . $theme . '|' . $categorie : ''));

// RAFRAICHIR NE JETTE PLUS LA PARTIE EN COURS. Avant le 10/09/2026, un F5
// rouvrait une seance neuve et les reponses deja donnees etaient perdues, sans
// message (FG-AUDIT-005). La reprise existait pourtant, mais seulement depuis
// le bouton du catalogue.
//
// LA REGLE TIENT AU TIRAGE. Une adresse qui en fixe un dit « cette partie-la »
// : c'est « Rejouer », qui doit bien redonner une partie neuve. Une adresse
// sans tirage dit « cette activite », et l'eleve s'attend alors a retrouver la
// sienne. Les liens du catalogue n'en portent jamais, ceux de Rejouer toujours.
if ($seanceReprise === null && !isset($_GET['tirage']) && $appelDemande !== '') {
    $seanceReprise = fgSeanceAReprendrePour($appelDemande);
}

// QUATRIEME CHEMIN D'ENTREE : un jeu qui n'est ni genere depuis le
// referentiel, ni une banque de contenu, ni du catalogue fixe - le compte est
// bon compose ses manches a la volee, sans etre rattache a une competence du
// referentiel. Seule cette valeur est acceptee : un « special » invente ne
// doit pas silencieusement se comporter comme un jeu fixe inconnu.
if ($seanceReprise !== null) {
    $jeu = fgJeuDepuisSeance($seanceReprise);
    if ($jeu === null) {
        fgFermerSeance((string)$seanceReprise['id']);
        header('Location: catalogue.php?indisponible=1');
        exit;
    }
    $idJeu = (string)$seanceReprise['jeu'];
    $reference = $seanceReprise['reference'];
    $graineJeu = (string)$seanceReprise['graine'];
    $banqueChoisie = (string)$seanceReprise['banque'];
    $programme = (string)$seanceReprise['programme'];
    $theme = (string)$seanceReprise['theme'];
    $categorie = (string)$seanceReprise['categorie'];
    $generateur = (string)$seanceReprise['generateur'];
    $special = in_array($idJeu, array('boutique', 'compte-est-bon', 'horloge'), true) ? $idJeu : '';
    $jeuGenere = $reference !== null || $banqueChoisie !== '' || $special !== '';
} elseif ($special === 'boutique') {
    require_once __DIR__ . '/includes/boutique.php';
    $graineJeu = date('Y-m-d') . '|boutique|' . $tirage;
    // Meme raison que pour le compte est bon : le niveau est fige au
    // lancement et voyage dans la seance, jamais relu de la session courante.
    $niveauCompte = fgBoutiqueNiveauDepart();
    $jeu = fgJeuBoutique($graineJeu, $niveauCompte);
    $jeuGenere = true;
    $generateur = 'boutique';
    $idJeu = 'boutique';
    $programme = '2026';
    $theme = '';
    $categorie = '';
} elseif ($special === 'horloge') {
    require_once __DIR__ . '/includes/horloge.php';
    $graineJeu = date('Y-m-d') . '|horloge|' . $tirage;
    // Meme raison que pour les deux autres jeux a paliers : le niveau est fige
    // au lancement et voyage dans la seance, jamais relu de la session courante.
    $niveauCompte = fgHorlogeNiveauDepart();
    $jeu = fgJeuHorloge($graineJeu, $niveauCompte);
    $jeuGenere = true;
    $generateur = 'horloge';
    $idJeu = 'horloge';
    $programme = '2026';
    $theme = '';
    $categorie = '';
} elseif ($special === 'compte-est-bon') {
    require_once __DIR__ . '/includes/compte-est-bon.php';
    $graineJeu = date('Y-m-d') . '|compte|' . $tirage;
    // Le niveau de depart est fige ICI, au lancement, et voyage dans la
    // seance (voir plus bas, cle 'niveau') : le reconstruire plus tard a
    // partir de la session courante serait fragile, car fgCompteAjusterNiveau
    // peut le faire bouger avant que CETTE partie ne soit close.
    $niveauCompte = fgCompteNiveauDepart();
    $jeu = fgJeuCompteEstBon($graineJeu, $niveauCompte);
    $jeuGenere = true;
    $generateur = 'compte';
    $idJeu = 'compte-est-bon';
    $programme = '2026';
    $theme = '';
    $categorie = '';
} elseif ($banqueChoisie !== '') {
    require_once __DIR__ . '/includes/mecaniques.php';
    require_once __DIR__ . '/includes/referentiel.php';
    $banques = fgBanques();
    if (!isset($banques[$banqueChoisie])) {
        header('Location: catalogue.php?indisponible=1');
        exit;
    }
    $banque = $banques[$banqueChoisie];
    // Une banque retiree de l'offre ne lance plus de partie neuve. Une partie
    // deja commencee passe par la reprise, plus haut, et peut se terminer
    // (arbitrage V1, DEC-08).
    if (!empty($banque['hors_offre'])) {
        header('Location: catalogue.php?indisponible=1');
        exit;
    }
    $dbReference = bdd::connexion((string)$_SESSION['bdd']);
    $reference = fgReferenceDeBanque($dbReference, $banque);
    if (!$reference) {
        // AUCUNE COMPETENCE NE REPOND. Deux cas tres differents.
        //
        // Elle existe mais sort du niveau de la classe : le jeu n'est pas pour
        // cet eleve, on renvoie au catalogue comme avant.
        //
        // Elle n'existe plus du tout - supprimee dans Fast Eval : le jeu, lui,
        // est intact. Il reste jouable et prend son propre titre pour intitule,
        // ce qui n'invente rien. Demande de le responsable technique, 09/09/2026.
        require_once __DIR__ . '/includes/places-banques.php';
        $orpheline = in_array($banqueChoisie, fgBanquesSansCompetence($dbReference, array($banqueChoisie => $banque)), true);
        if (!$orpheline) {
            header('Location: catalogue.php?indisponible=1');
            exit;
        }
    }
    $graineJeu = date('Y-m-d') . '|banque|' . $banqueChoisie . '|' . $tirage;
    $jeu = fgJeuDepuisBanque($banque, (string)($reference['libelle'] ?? $banque['titre'] ?? ''), $graineJeu);
    if ($jeu === null) {
        header('Location: catalogue.php?indisponible=1');
        exit;
    }
    $jeuGenere = true;
    $generateur = $banque['mecanique'];
    $idJeu = $banque['mecanique'];
    $programme = '2026';
    $theme = $banque['mecanique'];
    $categorie = $banqueChoisie;
} elseif ($theme !== '' && $categorie !== '') {
    require_once __DIR__ . '/includes/referentiel.php';
    require_once __DIR__ . '/includes/generateurs.php';
    require_once __DIR__ . '/includes/catalogue-mini-jeux.php';
    $programme = fgProgrammeChoisi($programme);
    $configurationCategorie = fgConfigurationCategorie($theme, $categorie);
    $dbReference = bdd::connexion((string)$_SESSION['bdd']);
    $reference = $configurationCategorie
        ? fgChoisirReferenceCategorie($dbReference, $programme, $theme, $categorie, $tirage)
        : null;
    if ($reference) {
        $generateur = (string)$reference['generateur'];
        $idJeu = fgConfigurationGenerateur($generateur)['jeu'];
        $graineJeu = $programme . '|' . $theme . '|' . $categorie . '|' . $tirage;
        $jeu = fgGenererJeuDepuisReference($reference, $graineJeu);
        $jeuGenere = $jeu !== null;
    }
    if (!$jeuGenere) {
        // Ce chemin theme+categorie n'est plus atteignable que depuis
        // catalogue-generateurs.php : y renvoyer, pas vers le nouveau
        // catalogue par competence qui ignore ces parametres.
        header('Location: catalogue-generateurs.php?programme=' . rawurlencode($programme) . '&indisponible=1');
        exit;
    }
} else {
    // PLUS AUCUNE ENTREE PAR « ?jeu= ». Les trois jeux fixes de data/jeux.php
    // (flash, intrus, correction) ne sont lies depuis aucune page : leurs
    // intitules servent de gabarit aux jeux generes, rien de plus. L'adresse
    // restait pourtant ouverte, et une partie jouee ainsi s'enregistrait avec
    // un theme et une categorie vides, donc sans rubrique ni competence dans
    // le suivi de l'enseignante (FG-AUDIT-015, ferme le 10/09/2026).
    //
    // data/jeux.php reste necessaire : fgConfigurationGenerateur() y prend
    // titre, description et ton, et passeport.php ses intitules.
    header('Location: catalogue.php');
    exit;
}

// JEUX SPECIAUX : LA COMPETENCE CHOISIE PAR UN ADMINISTRATEUR, ET RIEN D'AUTRE.
// Lien pose dans liens-competences.php pour le niveau de la classe (decision de
// le responsable technique, 14/09/2026). Sans lien, ou si la table manque, la partie se lance et
// s'enregistre sans competence, comme avant : une panne ne bloque jamais le jeu.
// La competence n'entre pas dans la graine de ces jeux, refabriques par leur cle.
$lienValide = false;
if ($seanceReprise === null && $special !== '') {
    require_once __DIR__ . '/includes/liens-competences.php';
    try {
        $reference = fgReferenceLienJeu(bdd::connexion((string)$_SESSION['bdd']), $special);
    } catch (PDOException $e) {
        error_log('FastGames : lien de competence illisible, partie lancee sans competence.');
        $reference = null;
    }
    $lienValide = $reference !== null;
}

// La seance est tenue par le serveur : c'est lui qui gardera les bonnes
// reponses et comptera les points. Une reprise garde son identifiant et ses
// reponses deja enregistrees : ouvrir une nouvelle seance les effacerait.
$idSeance = $seanceReprise !== null ? (string)$seanceReprise['id'] : fgOuvrirSeance($jeu, $jeuGenere ? $reference : null, $jeuGenere ? $graineJeu : '', array(
    'jeu' => $idJeu,
    'programme' => $programme,
    'theme' => $theme,
    'categorie' => $categorie,
    'generateur' => $generateur,
    // Sans cette cle, le serveur ne saurait pas refabriquer la partie pour
    // corriger : la seance ne garde ni les questions ni les bonnes reponses.
    'banque' => $banqueChoisie,
    // Uniquement pour le compte est bon : le niveau de depart fige au lancement.
    'niveau' => $niveauCompte ?? 0,
    // D'OU VIENT LA COMPETENCE, ET EST-CE QUE QUELQU'UN L'A DECLAREE.
    //
    // Une banque porte sa liste 'competences' ecrite a la main dans
    // data/banques.php : le lien est explicite et verifiable, il part en base.
    // Le chemin theme+categorie, lui, prend la competence que
    // fgTypeGenerateur() a devinee dans l'intitule : ce lien-la ne part plus
    // (voir fgOuvrirSeance, et FG-AUDIT-017). Les jeux speciaux n'ont que le
    // lien valide ci-dessus ; les jeux fixes n'ont pas de reference du tout.
    'reference_qualifiee' => $banqueChoisie !== '' || $lienValide,
    // Ce que l'adresse demandait, pour retrouver cette partie apres un
    // rafraichissement. Voir fgSeanceAReprendrePour().
    'appel' => $appelDemande,
));

// UNE REPRISE REPART LA OU L'ELEVE S'ETAIT ARRETE. Le serveur attend la
// question suivant les reponses deja enregistrees ; une page qui repartait de
// la question 1 voyait chaque clic refuse en « Reponse inattendue », sans
// issue (signale le 21/09/2026). Le score affiche repart du score serveur.
$dejaRepondues = $seanceReprise !== null ? count($seanceReprise['reponses'] ?? array()) : 0;
$scoreReprise = $dejaRepondues > 0 ? (int)fgScoreSeance($seanceReprise) : 0;

// « Quitter » ramene la ou la partie a ete choisie : le nouveau catalogue par
// competence pour une banque, l'ancien catalogue-generateurs.php pour le
// chemin theme+categorie qui n'est plus atteignable que depuis lui.
$retourCatalogue = $banqueChoisie !== '' || $special !== '' || $reference === null
    ? fgRetourCatalogue($_GET)
    : 'catalogue-generateurs.php' . ($programme ? '?programme=' . rawurlencode($programme) : '');
$parametresRetour = array();
if ($banqueChoisie !== '' || $special !== '') {
    parse_str(parse_url($retourCatalogue, PHP_URL_QUERY) ?? '', $parametresRetour);
}
if ($special !== '') {
    // Sans ce cas, ces chemins tombaient dans la branche « jeuGenere »
    // generique ci-dessous, qui construit son URL a partir de
    // programme/theme/categorie - tous vides ici. Rejouer aurait recharge
    // jeu.php sans aucun parametre reconnu, et la redirection serait retombee
    // sur le catalogue au lieu de relancer une nouvelle partie.
    $parametresRejouer = array('special' => $special, 'tirage' => $tirage + 1,
                               'apercu' => fgEstEnseignant() ? 'eleve' : null);
} elseif ($banqueChoisie !== '') {
    // Rejouer une banque change le tirage, donc les items proposes.
    $parametresRejouer = array('banque' => $banqueChoisie, 'tirage' => $tirage + 1,
                               'apercu' => fgEstEnseignant() ? 'eleve' : null);
} elseif ($jeuGenere) {
    $parametresRejouer = array('programme' => $programme, 'theme' => $theme, 'categorie' => $categorie,
                               'tirage' => $tirage + 1, 'apercu' => fgEstEnseignant() ? 'eleve' : null);
} else {
    $parametresRejouer = array('jeu' => $idJeu, 'apercu' => fgEstEnseignant() ? 'eleve' : null);
}
$parametresRejouer = array_filter($parametresRejouer, static fn($valeur) => $valeur !== null && $valeur !== '');
$parametresRejouer = array_merge($parametresRejouer, $parametresRetour);
$urlRejouer = 'jeu.php?' . http_build_query($parametresRejouer);
$fgTitre = $jeu['titre'];
$fgPage = 'jeu';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/v2/fastgames/assets/partie.css?v=20260924-tri-quatre">

<div id="fg-jeu" class="fg-jeu"
     data-jeu="<?php echo fgH($idJeu); ?>"
     data-programme="<?php echo fgH($programme); ?>"
     data-theme="<?php echo fgH($theme); ?>"
     data-categorie="<?php echo fgH($categorie); ?>"
     data-generateur="<?php echo fgH($generateur); ?>"
     data-type-reference="<?php echo fgH($reference['type'] ?? ''); ?>"
     data-id-reference="<?php echo (int)($reference['id'] ?? 0); ?>"
     data-rejouer="<?php echo fgH($urlRejouer); ?>"
     data-retour="<?php echo fgH($retourCatalogue); ?>"
     data-csrf="<?php echo fgH($_SESSION['fastgames_csrf']); ?>"
     data-seance="<?php echo fgH($idSeance); ?>"
     data-deja="<?php echo (int)$dejaRepondues; ?>"
     data-score="<?php echo (int)$scoreReprise; ?>"
     data-apercu="<?php echo fgEstEnseignant() ? '1' : '0'; ?>">
    <div class="fg-partie-entete">
        <a class="fg-bouton fg-bouton-secondaire fg-partie-sortie" href="<?php echo fgH($retourCatalogue); ?>">Quitter l’activité</a>
        <strong class="fg-partie-titre"><?php echo fgH($jeu['titre']); ?></strong>
        <div class="fg-partie-progression">
            <span id="fg-etape">Question 1 sur <?php echo count($jeu['questions']); ?></span>
            <div id="fg-points" class="fg-partie-points" aria-hidden="true"></div>
        </div>
    </div>
    <div class="fg-partie-corps">
    <div class="fg-aire-jeu">
        <section class="fg-carte fg-question" aria-labelledby="fg-question">
            <span id="fg-consigne" class="fg-surtitre"></span>
            <h1 id="fg-question" tabindex="-1"></h1>
            <div id="fg-reponses" class="fg-reponses"></div>
        </section>
        <div id="fg-retour" class="fg-retour" role="status" hidden></div>
    </div>
    <aside class="fg-aide-jeu" aria-label="Aide">
        <details open>
            <summary>Comment jouer ?</summary>
            <p><?php echo fgH($jeu['description']); ?></p>
        </details>
        <details>
            <summary>À propos de cette activité</summary>
<?php if ($jeuGenere) { ?>
<div class="fg-bandeau-genere"><strong>Programme <?php echo fgH($programme === '2026' ? '2026/2027' : '2015'); ?></strong><span><?php
    // Deux origines derriere « jeu genere », deux formulations. Un jeu de calcul
    // est produit par une regle a partir du referentiel ; un jeu de banque est
    // du contenu ecrit a la main pour cette competence precise - dire « regle
    // CE1 verifiable » serait faux pour lui. fgLibelleCategorie() suppose par
    // ailleurs un theme du catalogue fixe (maths, francais) : sur le chemin des
    // banques, $theme vaut la mecanique ('tri', 'paires'), pas un theme reel, et
    // catalogue-mini-jeux.php n'est meme pas charge sur ce chemin - l'appeler
    // ici provoquait une erreur fatale qui coupait la page juste apres ce
    // bandeau, sans rien afficher au-dela.
    if ($special !== '') {
        // Meme piege que celui documente juste au-dessus pour les banques :
        // fgLibelleCategorie() suppose un theme/categorie du catalogue fixe,
        // que ces chemins n'ont pas. L'appeler ici provoquerait la meme erreur
        // fatale, silencieuse au-dela de ce bandeau.
        $libellesSpeciaux = array(
            'boutique' => 'Mini-jeu de monnaie · pièces et billets à composer, jamais deux fois la même somme.',
            'horloge' => 'Mini-jeu de lecture de l’heure · aiguilles à poser, jamais deux fois la même heure.',
        );
        echo $libellesSpeciaux[$special]
            ?? 'Mini-jeu de calcul · manches composées à la volée, jamais deux fois les mêmes nombres.';
    } elseif ($banqueChoisie !== '') {
        $libellesMecaniques = array('tri' => 'Mini-jeu de tri', 'paires' => 'Mini-jeu d’association', 'ordre' => 'Mini-jeu de classement', 'ecoute' => 'Mini-jeu d’écoute', 'qcm' => 'Mini-jeu de questions', 'carte' => 'Mini-jeu de localisation', 'droite' => 'Mini-jeu de droite graduée');
        echo fgH($libellesMecaniques[$banque['mecanique']] ?? 'Mini-jeu');
        echo ' · activité conçue pour cette compétence.';
    } else {
        echo fgH(fgLibelleCategorie($theme, $categorie));
        echo ' · activité produite par une règle CE1 vérifiable, sans IA.';
    }
?></span></div>
<?php } ?>

        </details>
    </aside>
    </div>
</div>
<?php
// « bonne » et « explication » sont retirees ici : elles etaient lisibles dans
// la source de la page, donc les reponses aussi. Le navigateur les demande
// maintenant a l'API, apres avoir envoye son choix.
$jeuPublic = fgJeuPourLeClient($jeu);
?>
<script type="application/json" id="fg-donnees-jeu"><?php echo json_encode($jeuPublic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<script src="/v2/fastgames/assets/jeu.js?v=20260923-lot2"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
