<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!fgEstEnseignant()) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/liens-competences.php';
require_once __DIR__ . '/includes/rapport.php';
require_once __DIR__ . '/includes/catalogue-competences.php';

// Liens communs à toutes les classes : tout enseignant les consulte, seul un
// administrateur les modifie (décisions de le responsable technique, 14 et 15/09/2026).
$administrateur = gxEstAdministrateur();
$jeux = fgJeuxReliables();
$jeu = (string)($_GET['jeu'] ?? '');
if (!isset($jeux[$jeu])) $jeu = '';
$erreur = '';
$liens = null;
$competences = array();
$libelles = array();
try {
    $db = bdd::connexion((string)$_SESSION['bdd']);
    $liens = fgLiensCompetences($db);
    if ($jeu !== '') {
        foreach (FG_NIVEAUX_LIENS as $niveau) $competences[$niveau] = fgCompetencesLiables($db, $niveau);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$administrateur || $liens === null || $jeu === '' || !hash_equals((string)$_SESSION['fastgames_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new DomainException('Action non autorisée ou session expirée.');
        }
        fgEnregistrerLienJeu($db, $jeu, (string)($_POST['niveau'] ?? ''), (int)($_POST['id_competence'] ?? 0), (int)($_SESSION['id_enseignant'] ?? 0));
        header('Location: liens-competences.php?' . http_build_query(array('jeu' => $jeu, 'enregistre' => 1)), true, 303);
        exit;
    }
    // Intitulés des choix et des listes écrites, relus dans Fast Éval en une seule requête.
    $ids = array();
    foreach ($liens ?? array() as $parNiveau) foreach ($parNiveau as $ligne) $ids[] = (int)$ligne['id_competence'];
    foreach (fgBanquesProgramme() as $banque) foreach ($banque['competences'] as $id) $ids[] = (int)$id;
    $libelles = fgReferencesRapport($db, array_map(static fn($id) => array('id_reference' => $id), array_values(array_unique($ids))));
} catch (DomainException | InvalidArgumentException $e) {
    http_response_code(400);
    $erreur = $e->getMessage();
} catch (PDOException $e) {
    http_response_code(503);
    $erreur = 'Les liens sont temporairement indisponibles. Aucune donnée n’a été modifiée.';
}
$modifiable = $administrateur && $liens !== null;
$intitule = static function (int $id) use ($libelles): string {
    $libelle = trim((string)($libelles[$id]['libelle'] ?? ''));
    return $libelle !== '' ? fgNettoyerLibelle($libelle) : 'Compétence n° ' . $id . ' introuvable dans le référentiel';
};
$programme = static function (string $cle) use ($intitule): string {
    $banque = fgBanqueDuLien($cle);
    if ($banque === null) return 'Non relié à une compétence';
    return 'Programme du jeu : ' . implode(' ; ', array_map(static fn($id) => $intitule((int)$id), $banque['competences']));
};

$fgTitre = 'Relier les jeux aux compétences';
// Même gabarit que les rapports individuels : largeur riche, fil d'Ariane titré.
$fgPage = 'passeport';
require __DIR__ . '/includes/header.php';
?>
<div class="gx-ligne-titre"><div><p class="gx-ligne-titre-surtitre">Fast Games · espace enseignant</p><h1 class="gx-ligne-titre-bonjour">Relier les jeux aux compétences</h1><p class="gx-ligne-titre-description">Choisissez la compétence créditée par chaque jeu, pour le CE1 et pour le CE2.</p></div></div>
<?php if ($erreur) { ?><p role="alert"><?php echo fgH($erreur); ?></p><?php } ?>
<?php if (isset($_GET['enregistre'])) { ?><p role="status">Enregistrement effectué.</p><?php } ?>
<?php if ($liens === null && !$erreur) { ?><p role="alert">Les liens ne sont pas encore disponibles sur ce serveur. Les jeux gardent leur fonctionnement actuel.</p><?php } ?>
<?php if ($jeu === '') { ?>
<section class="admin-section">
<p>Une partie jouée par un élève compte pour la compétence choisie pour son niveau. Sans choix, un jeu garde la compétence prévue par son programme ; les trois jeux à moteur propre restent alors non reliés. Changer ou retirer un choix retire aussi les parties déjà jouées de l’ancienne compétence dans les rapports et le suivi.</p>
<?php if (!$administrateur) { ?><p>Seul un administrateur peut modifier ces liens.</p><?php } ?>
</section>
<div class="gx-tableau-cadre"><table class="sv-table"><thead><tr><th scope="col">Jeu</th><th scope="col">CE1</th><th scope="col">CE2</th><th scope="col">Action</th></tr></thead><tbody>
<?php foreach ($jeux as $cle => $titre) { ?><tr><td><?php echo fgH($titre); ?></td><?php foreach (FG_NIVEAUX_LIENS as $niveau) { $choix = (int)($liens[$cle][$niveau]['id_competence'] ?? 0); ?><td><?php echo fgH($choix > 0 ? $intitule($choix) : $programme($cle)); ?></td><?php } ?><td><a class="fg-bouton fg-bouton-secondaire" href="liens-competences.php?<?php echo fgH(http_build_query(array('jeu' => $cle))); ?>" aria-label="<?php echo fgH(($modifiable ? 'Modifier' : 'Consulter') . ' les compétences de « ' . $titre . ' »'); ?>"><?php echo $modifiable ? 'Modifier' : 'Consulter'; ?></a></td></tr><?php } ?>
</tbody></table></div>
<?php } else { $banque = fgBanqueDuLien($jeu); $matieres = fgMatieresCatalogue(); ?>
<p><a class="fg-bouton fg-bouton-secondaire" href="liens-competences.php">Tous les jeux</a></p>
<section class="admin-section">
<h2><?php echo fgH($jeux[$jeu]); ?></h2>
<p><?php echo fgH($programme($jeu)); ?></p>
<?php foreach (FG_NIVEAUX_LIENS as $niveau) {
    $liste = $competences[$niveau] ?? array();
    $propositions = fgPropositionsLien($jeu, $liste);
    $lien = $liens[$jeu][$niveau] ?? null;
    $actuel = (int)($lien['id_competence'] ?? 0);
    $idChamp = 'lien-' . strtolower($niveau);
    $option = static function (int $id, array $competence) use ($actuel): string {
        return '<option value="' . $id . '"' . ($id === $actuel ? ' selected' : '') . '>' . fgH($competence['libelle']) . '</option>';
    };
?>
<form method="post" class="sv-filtres">
    <input type="hidden" name="csrf" value="<?php echo fgH($_SESSION['fastgames_csrf'] ?? ''); ?>">
    <input type="hidden" name="niveau" value="<?php echo fgH($niveau); ?>">
    <label for="<?php echo fgH($idChamp); ?>"><?php echo fgH($niveau); ?><select id="<?php echo fgH($idChamp); ?>" name="id_competence"<?php echo $modifiable ? '' : ' disabled'; ?>>
        <option value="0"<?php echo $actuel === 0 ? ' selected' : ''; ?>><?php echo $banque !== null ? 'Programme du jeu (par défaut)' : 'Non relié à une compétence'; ?></option>
        <?php if ($propositions) { ?><optgroup label="<?php echo $banque !== null ? 'Programme du jeu' : 'Propositions à valider'; ?>"><?php foreach (array_keys($propositions) as $id) echo $option($id, $liste[$id]); ?></optgroup><?php } ?>
        <?php foreach ($matieres as $matiere) { ?><optgroup label="<?php echo fgH($matiere); ?>"><?php foreach ($liste as $id => $competence) if ($competence['matiere'] === $matiere && !isset($propositions[$id])) echo $option($id, $competence); ?></optgroup><?php } ?>
    </select></label>
    <?php if ($modifiable) { ?><button class="fg-bouton fg-bouton-principal" type="submit">Enregistrer</button><?php } ?>
</form>
<?php if ($lien && !isset($liste[$actuel])) { ?>
<p role="alert"><?php echo fgH($niveau); ?> : la compétence choisie (n° <?php echo $actuel; ?>) n’est plus disponible dans le référentiel pour ce niveau.</p>
<?php } elseif ($lien) { ?>
<p><?php echo fgH($niveau); ?> : choix enregistré le <?php echo fgH((new DateTimeImmutable((string)$lien['date_validation']))->format('d/m/Y')); ?>.</p>
<?php } ?>
<?php } ?>
</section>
<?php } ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
