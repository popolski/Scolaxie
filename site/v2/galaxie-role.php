<?php
/**
 * Renvoie le libelle de role commun aux bandeaux de toute la V2.
 *
 * Le fichier est publie a la racine /v2, a cote de galaxie-tokens.css : les
 * applications utilisent ainsi les memes mots et les memes classes de role.
 */
if (!function_exists('gxSousTitreIdentite')) {
    /**
     * Retrouve le nom quel que soit le vocabulaire de session de l'application.
     * Le SSO historique n'utilise pas les memes cles dans Fast Eval et School
     * Monsters ; sans ce repli, un passage direct d'une application a l'autre
     * pouvait laisser un cartouche de compte sans nom.
     */
    function gxNomIdentite()
    {
        $prenom = trim((string) ($_SESSION['prenom_client'] ?? $_SESSION['prenom'] ?? ''));
        $nom = trim((string) ($_SESSION['nom_client'] ?? $_SESSION['nom'] ?? ''));
        $nomComplet = trim(ucfirst($prenom) . ' ' . mb_strtoupper($nom, 'UTF-8'));
        if ($nomComplet !== '') {
            return $nomComplet;
        }

        return !empty($_SESSION['identifiant_enseignant'])
            ? ucfirst((string) $_SESSION['identifiant_enseignant'])
            : '';
    }

    function gxEstEnseignant()
    {
        return in_array((string) ($_SESSION['role'] ?? ''), array('enseignant', 'teacher'), true);
    }

    function gxEstAdministrateur()
    {
        return gxEstEnseignant() && !empty($_SESSION['est_admin']);
    }

    function gxLibelleRoleIdentite($nomDuSite)
    {
        if (gxEstEnseignant()) {
            return gxEstAdministrateur() ? 'Administrateur' : 'Enseignant';
        }

        if (in_array((string) ($_SESSION['role'] ?? ''), array('eleve', 'student'), true)) {
            return 'Élève';
        }

        return (string) $nomDuSite;
    }

    function gxSousTitreIdentite($nomDuSite)
    {
        $classe = gxEstEnseignant() ? 'gx-role-enseignant' : 'gx-role-eleve';
        return '<span class="gx-entete-role ' . $classe . '">'
             . htmlspecialchars(gxLibelleRoleIdentite($nomDuSite), ENT_QUOTES, 'UTF-8')
             . '</span>';
    }

    /**
     * Rend le meme menu de compte dans tous les anciens gabarits PHP.
     * Le <details> natif fonctionne a la souris, au tactile et au clavier sans
     * script ; l'ancienne fleche dessinee en CSS ne pouvait rien ouvrir.
     */
    function gxMenuIdentite($nomDuSite, array $liensSupplementaires = array())
    {
        // $liensSupplementaires : actions propres a une page, en plus du
        // retour au portail. Chaque entree : array('label' => ..., 'href' =>
        // ..., 'mobileOnly' => bool). mobileOnly (par defaut faux) sert la
        // navigation d'application qui vit normalement dans une rangee du
        // bandeau (Accueil / Mini-jeux / Programme sur Fast Games) : cette
        // rangee disparait sous 720px, ces liens la remplacent alors dans le
        // menu. Une entree sans mobileOnly reste visible a toutes les
        // largeurs : c'est le seul endroit ou elle vit (ex. Reinitialiser la
        // saisie).
        $nom = gxNomIdentite();
        if ($nom === '') {
            return '';
        }

        // L'avatar affichait la lettre du role : tous les enseignants
        // portaient un « E » et tous les eleves un « E » accentue. Clic & Mots,
        // qui a son propre en-tete, affichait deja la vraie initiale : deux
        // avatars, deux significations. On prend l'initiale de la personne.
        $initiale = mb_strtoupper(mb_substr(trim($nom), 0, 1, 'UTF-8'), 'UTF-8');
        if ($initiale === '') {
            $initiale = '?';
        }
        $nomSecurise = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');

        return '<details class="user-menu">'
             . '<summary aria-label="Menu du compte de ' . $nomSecurise . '">'
             . '<span class="user-avatar" aria-hidden="true">' . $initiale . '</span>'
             . '<span class="user-texte"><strong>' . $nomSecurise . '</strong>'
             . gxSousTitreIdentite($nomDuSite) . '</span>'
             . '<span aria-hidden="true">⌄</span>'
             . '</summary>'
             . '<div class="user-popover">'
             . implode('', array_map(function ($lien) {
                 $classe = !empty($lien['mobileOnly']) ? ' class="popover-nav-mobile"' : '';
                 return '<a' . $classe . ' href="' . htmlspecialchars($lien['href'], ENT_QUOTES, 'UTF-8') . '">'
                     . htmlspecialchars($lien['label'], ENT_QUOTES, 'UTF-8') . '</a>';
             }, $liensSupplementaires))
             . '<a href="/portail/">Portail</a>'
             // Signale dans l'audit du 05/09/2026 : aucune de ces pages
             // n'offrait de moyen de se deconnecter, contrairement au
             // portail lui-meme. deconnexion.php ferme la session ET le
             // cookie SSO cv_sso (pose sur /), donc un lien qui y pointe
             // deconnecte bien de toute la galaxie, pas seulement de cette
             // application - meme URL absolue que le lien "Portail" juste
             // au-dessus. Classe reprise du popover du portail
             // (.logout-link) : pas encore stylee dans le socle partage,
             // un lien nu pour l'instant, a uniformiser cote design si
             // Codex le juge utile.
             . '<a class="logout-link" href="/portail/deconnexion.php">Se déconnecter</a></div>'
             . '</details>';
    }
}
