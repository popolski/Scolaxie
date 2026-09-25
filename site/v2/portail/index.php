<?php
require_once __DIR__ . '/sso.php';
// Le portail et Fast Games vivent dans le même dossier /v2 en production.
// Le chemin à deux niveaux pointait vers l'ancien emplacement hors V2.
require_once dirname(__DIR__) . '/fastgames/config.php';

$identity = ssoReadToken();
$error = isset($_GET['erreur']);
$sessionExpired = isset($_GET['session']) && $_GET['session'] === 'expiree';
$fastgamesVisible = fastgamesIdentiteAutorisee($identity);
$fastgamesIndisponible = isset($_GET['fastgames']) && $_GET['fastgames'] === 'indisponible';
$roleChoisi = (isset($_GET['role']) && $_GET['role'] === 'teacher') ? 'teacher' : 'student';
$classeChoisie = isset($_GET['classe']) ? (int)$_GET['classe'] : 0;
$classesConnexion = scolaxieClassesConnexion();
$estEnseignant = $identity && ($identity['role'] ?? '') === 'teacher';
$estAdministrateur = false;
if ($estEnseignant) {
    // Le droit peut changer pendant la vie du cookie SSO. Fast Éval reste la
    // source de vérité : on le relit au portail puis on renouvelle le jeton,
    // ce qui transmet immédiatement le bon libellé aux quatre applications.
    try {
        require_once dirname(__DIR__) . '/fasteval/utils/class/class_bdd.php';
        $dbPortail = bdd::connexion(scolaxieConfig('SCOLAXIE_DB_NAME'));
        $stmtAdmin = $dbPortail->prepare('SELECT est_admin FROM ayant_droit WHERE id_enseignant=:tid LIMIT 2');
        $stmtAdmin->execute(array(':tid' => (int)$identity['tid']));
        $droits = $stmtAdmin->fetchAll(PDO::FETCH_COLUMN);
        if (count($droits) === 1) {
            $estAdministrateur = (bool)$droits[0];
            if (!array_key_exists('admin', $identity) || (bool)$identity['admin'] !== $estAdministrateur) {
                $identity['admin'] = $estAdministrateur;
                ssoSetCookie(ssoCreateToken($identity));
            }
        }
    } catch (Throwable $erreurDroits) {
        $estAdministrateur = !empty($identity['admin']);
    }

    // LA LIGNE DU JOUR, 06/09/2026. Deux chiffres, et seulement ceux qui
    // existent vraiment : les saisies d'evaluation et les parties de mini-jeux
    // de la journee, pour CET enseignant. Les deux vivent dans la meme base que
    // celle deja ouverte ci-dessus - aucune connexion supplementaire.
    //
    // Pourquoi ces deux-la et pas une ligne par application : School Monsters
    // n'a aucune table d'activite (sa seule table est `droitsite`, les droits
    // d'acces ; ses lecons sont des pages statiques) et Clic & Mots vit dans
    // une autre base. Mieux vaut deux chiffres vrais qu'une rangee de quatre
    // dont deux seraient inventes.
    //
    // Les requetes des saisies sont exactement celles de l'accueil Fast Eval
    // (presentation.php), pour que les deux pages ne puissent pas se
    // contredire.
    try {
        if (isset($dbPortail)) {
            $idEnseignantJour = (int)$identity['tid'];

            $reqEval = $dbPortail->prepare('SELECT COUNT(*) FROM eval_eleves WHERE date_acqui = DATE(NOW()) AND id_enseignant = :tid');
            $reqEval->execute(array(':tid' => $idEnseignantJour));
            $reqComp = $dbPortail->prepare('SELECT COUNT(*) FROM comp_eleves WHERE date_enr = DATE(NOW()) AND id_enseignant = :tid');
            $reqComp->execute(array(':tid' => $idEnseignantJour));
            $saisiesDuJour = (int)$reqEval->fetchColumn() + (int)$reqComp->fetchColumn();

            // fastgames_resultats est creee A LA DEMANDE par Fast Games
            // (fgAssurerTableResultats) : elle peut tout simplement ne pas
            // exister encore dans la base d'une classe. Son propre try evite
            // que cette absence, parfaitement normale, n'efface aussi le
            // chiffre des saisies.
            try {
                $reqJeux = $dbPortail->prepare('SELECT COUNT(*) FROM fastgames_resultats WHERE DATE(date_enr) = DATE(NOW()) AND id_enseignant = :tid');
                $reqJeux->execute(array(':tid' => $idEnseignantJour));
                $partiesDuJour = (int)$reqJeux->fetchColumn();
            } catch (Throwable $sansTableJeux) {
                $partiesDuJour = 0;
            }
        }
    } catch (Throwable $erreurJour) {
        // Meme principe que le panneau de contexte de Fast Eval : si la base
        // ne repond pas, la ligne s'efface plutot que d'afficher un chiffre
        // faux. Le reste de la page continue de fonctionner.
        $saisiesDuJour = 0;
        $partiesDuJour = 0;
    }
}
$morceauxNom = $identity ? explode(' ', trim((string)$identity['label']), 2) : array();
$prenomAffiche = $morceauxNom ? trim($morceauxNom[0]) : '';
$nomAffiche = isset($morceauxNom[1]) ? trim($morceauxNom[1]) : '';
$datePortail = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
$joursPortail = array('dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi');
$moisPortail = array(1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
$anneePortail = (int)$datePortail->format('Y');
$moisNumeroPortail = (int)$datePortail->format('n');
$debutAnneeScolaire = $moisNumeroPortail >= 9 ? $anneePortail : $anneePortail - 1;
$datePortailAffichee = ucfirst($joursPortail[(int)$datePortail->format('w')]) . ' ' . (int)$datePortail->format('j') . ' ' . $moisPortail[$moisNumeroPortail];
$anneeScolaireAffichee = $debutAnneeScolaire . '-' . ($debutAnneeScolaire + 1);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= $identity ? 'Mon espace' : 'Connexion' ?> - Scolaxie</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-accueils-b">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="style.css?v=20260912-conformite">
</head>
<body class="<?= $identity ? 'dashboard-page' : 'login-page' ?>">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<?php if (!$identity): ?>
<main id="contenu" class="login-layout">
    <header class="brand brand-login">
        <img src="logo-scolaxie.png" alt="Scolaxie — Une galaxie d’outils pour apprendre">
    </header>

    <section class="login-card" aria-labelledby="login-title">
        <div class="login-form-panel">
            <h1 id="login-title">Connexion</h1>

            <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <strong>Connexion impossible</strong>
                <span>Vérifie tes informations et réessaie.</span>
            </div>
            <?php elseif ($sessionExpired): ?>
            <div class="alert" role="status">
                <strong>Ta session a expiré</strong>
                <span>Reconnecte-toi pour continuer.</span>
            </div>
            <?php endif; ?>

            <form action="connexion.php" method="post">
                <fieldset class="role-picker">
                    <legend>Je suis...</legend>
                    <div class="role-options">
                        <label class="role-option role-option-student">
                            <input type="radio" name="role" value="student"<?= $roleChoisi === 'student' ? ' checked' : '' ?>>
                            <span>
                                <span class="role-symbol role-symbol-student" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M8 8V6.5a4 4 0 0 1 8 0V8"/>
                                        <path d="M7 8h10a3 3 0 0 1 3 3v8H4v-8a3 3 0 0 1 3-3Z"/>
                                        <path d="M4 13h16M8 16h8M7 19v2M17 19v2"/>
                                    </svg>
                                </span>
                                Élève
                                <b aria-hidden="true">✓</b>
                            </span>
                        </label>
                        <label class="role-option role-option-teacher">
                            <input type="radio" name="role" value="teacher"<?= $roleChoisi === 'teacher' ? ' checked' : '' ?>>
                            <span>
                                <span class="role-symbol role-symbol-teacher" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <circle cx="6" cy="7" r="2.5"/>
                                        <path d="M2.5 18c.4-3.5 1.6-5.5 3.5-5.5s3.1 2 3.5 5.5"/>
                                        <path d="M12 4h9v12h-9zM15 19h3M16.5 16v3M14.5 12l2-2 1.5 1 2-3"/>
                                    </svg>
                                </span>
                                Enseignant
                                <b aria-hidden="true">✓</b>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <div id="teacher-choice" class="field">
                    <label for="teacherId">Ma classe</label>
                    <select id="teacherId" name="teacherId">
                        <?php foreach ($classesConnexion as $classeConnexion): ?>
                        <option value="<?= $classeConnexion['id'] ?>"<?= $classeChoisie === $classeConnexion['id'] ? ' selected' : '' ?>><?= htmlspecialchars($classeConnexion['label'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="identifiant">Prénom ou identifiant</label>
                    <input id="identifiant" name="identifiant" required autocomplete="username" placeholder="Ton prénom">
                </div>

                <div class="field">
                    <label for="password">Mot de passe</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Ton mot de passe">
                        <button class="password-toggle" type="button" aria-controls="password" aria-pressed="false">
                            <span class="sr-only">Afficher le mot de passe</span>
                            <svg class="pwd-icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="pwd-icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><line x1="4.5" y1="19.5" x2="19.5" y2="4.5"/></svg>
                        </button>
                    </div>
                </div>

                <button class="primary-button" type="submit">Entrer dans mon espace <span aria-hidden="true">→</span></button>
                <p class="login-help" id="login-help">Un problème pour te connecter ? Demande à ton enseignant.</p>
            </form>
        </div>
    </section>

</main>
<?php else: ?>
<header class="topbar">
    <a class="brand brand-dashboard" href="/portail/" aria-label="Accueil du portail Scolaxie">
        <img src="logo-scolaxie.png" alt="">
    </a>
    <nav class="topbar-fil" aria-label="Fil d’Ariane">
        <strong>Portail</strong>
        <span aria-hidden="true">›</span>
        <span>Accueil</span>
    </nav>
    <div class="topbar-actions">
        <details class="user-menu">
            <summary>
                <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($prenomAffiche, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                <span class="user-texte">
                    <strong><span class="user-prenom"><?= htmlspecialchars($prenomAffiche) ?></span><?php if ($nomAffiche !== ''): ?> <span class="user-nom"><?= htmlspecialchars($nomAffiche) ?></span><?php endif; ?></strong>
                    <span class="gx-entete-role"><?= $estEnseignant ? ($estAdministrateur ? 'Administrateur' : 'Enseignant') : 'Élève' ?></span>
                </span>
                <span aria-hidden="true">⌄</span>
            </summary>
            <div class="user-popover">
                <span><?= $estEnseignant ? 'Espace enseignant' : 'Espace élève' ?></span>
                <a class="logout-link" href="deconnexion.php">Se déconnecter partout</a>
            </div>
        </details>
    </div>
</header>

<main id="contenu" class="dashboard gx-accueil-compose">
    <header class="dashboard-heading">
        <div class="dashboard-heading-copy">
            <p class="eyebrow">Tableau de bord</p>
            <h1>Bonjour <?= htmlspecialchars($prenomAffiche) ?></h1>
            <p><?= $estEnseignant ? 'Choisissez l’espace dans lequel vous souhaitez travailler.' : 'Choisis l’espace dans lequel tu souhaites travailler.' ?></p>
        </div>
        <p class="dashboard-date">
            <span><?= htmlspecialchars($datePortailAffichee) ?></span>
            <span>Année scolaire <?= htmlspecialchars($anneeScolaireAffichee) ?></span>
        </p>
    </header>

    <?php
    // La ligne ne s'affiche QUE si la journee a produit quelque chose. Un rang
    // de zeros le matin n'apprend rien a personne et ferait du bruit sur une
    // page dont la regle est que les applications sont les actions.
    $saisiesDuJour = $saisiesDuJour ?? 0;
    $partiesDuJour = $partiesDuJour ?? 0;
    if ($estEnseignant && ($saisiesDuJour > 0 || $partiesDuJour > 0)):
    ?>
    <section class="jour" aria-label="Aujourd’hui dans la classe">
        <span class="jour-titre">Aujourd’hui</span>
        <?php if ($saisiesDuJour > 0): ?>
        <span class="jour-item"><span class="jour-chiffre"><?= $saisiesDuJour ?></span><span class="jour-libelle">saisie<?= $saisiesDuJour > 1 ? 's' : '' ?> d’évaluation</span></span>
        <?php endif; ?>
        <?php if ($partiesDuJour > 0): ?>
        <span class="jour-item"><span class="jour-chiffre"><?= $partiesDuJour ?></span><span class="jour-libelle">partie<?= $partiesDuJour > 1 ? 's' : '' ?> de mini-jeux</span></span>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($fastgamesIndisponible): ?>
    <div class="alert dashboard-alert" role="status">
        <strong>Fast Games est encore en phase d'essai</strong>
        <span>Tu peux continuer avec les autres applications.</span>
    </div>
    <?php endif; ?>

    <div class="dashboard-grid<?= $fastgamesVisible ? '' : ' no-feature' ?>">
        <section class="app-list" aria-label="Applications pédagogiques">
            <a class="app-card fast" href="/fasteval/sso.php">
                <span class="app-logo"><img src="logo-fast-eval.png" alt=""></span>
                <span><strong>Fast Éval</strong><small><?= $estEnseignant ? 'Évaluations, saisies, suivi des compétences et bulletins.' : 'Tes évaluations, tes résultats et tes progrès.' ?></small></span>
                <span class="app-action">Consulter les évaluations<b aria-hidden="true">→</b></span>
            </a>
            <a class="app-card school" href="/schoolmonsters/sso.php">
                <span class="app-logo"><img src="logo-school-monsters.png?v=20260905-02" alt=""></span>
                <span><strong>School Monsters</strong><small>Leçons, activités et ressources pédagogiques de la classe.</small></span>
                <span class="app-action">Explorer les leçons<b aria-hidden="true">→</b></span>
            </a>
            <a class="app-card clic" href="/clicetmots/api/sso.php">
                <span class="app-logo"><img src="logo-clic-et-mots.png" alt=""></span>
                <span><strong>Clic &amp; Mots</strong><small>Lecture, orthographe, vocabulaire et supports imprimables.</small></span>
                <span class="app-action">Ouvrir les activités<b aria-hidden="true">→</b></span>
            </a>
            <?php if ($fastgamesVisible): ?>
            <a class="app-card games" href="/fastgames/sso.php">
                <span class="app-logo"><img src="/v2/fastgames/assets/logo-fastgames.png?v=20260906-02" alt=""></span>
                <span><strong>Fast Games</strong><small>Jeux pédagogiques interactifs pour réviser les programmes.</small></span>
                <span class="app-action">Choisir un jeu<b aria-hidden="true">→</b></span>
            </a>
            <?php endif; ?>
        </section>
    </div>

</main>
<?php endif; ?>

<script>
const roleInputs = document.querySelectorAll('input[name="role"]');
const classField = document.getElementById('teacher-choice');
const identifier = document.getElementById('identifiant');

function updateRole() {
    if (!classField || roleInputs.length === 0) return;
    const teacher = document.querySelector('input[name="role"]:checked')?.value === 'teacher';
    classField.hidden = teacher;
    if (identifier) identifier.placeholder = teacher ? 'Votre identifiant' : 'Ton prénom';
}

roleInputs.forEach(input => input.addEventListener('change', updateRole));
updateRole();

const passwordToggle = document.querySelector('.password-toggle');
const passwordInput = document.getElementById('password');
passwordToggle?.addEventListener('click', () => {
    const visible = passwordInput.type === 'text';
    passwordInput.type = visible ? 'password' : 'text';
    passwordToggle.setAttribute('aria-pressed', String(!visible));
    passwordToggle.querySelector('.sr-only').textContent = visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe';
});

const userMenu = document.querySelector('.user-menu');
userMenu?.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !userMenu.open) return;
    event.preventDefault();
    userMenu.open = false;
    userMenu.querySelector('summary')?.focus();
});
</script>
</body>
</html>
