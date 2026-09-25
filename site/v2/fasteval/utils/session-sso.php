<?php
/*
 * Ouverture d'une session Fast Éval à partir du cookie de connexion unique.
 *
 * Ce fichier existe pour un bug precis : depuis Clic & Mots et depuis School
 * Monsters, le lien « Gérer la classe dans Fast Éval » pointe droit sur
 * gestion-classe.php. Or cette page exige une session Fast Éval, que le
 * visiteur n'a pas forcement : il s'est connecte au portail, ce qui lui donne
 * le cookie commun, mais il n'a pas encore ouvert Fast Éval dans ce
 * navigateur. La page le renvoyait alors sur index.php, c'est-a-dire sur
 * l'ancien portail, ce qui donnait l'impression que le lien etait casse.
 *
 * La logique d'ouverture de session vivait uniquement dans sso.php. Elle est
 * mise ici pour que n'importe quel ecran atteint par lien direct puisse
 * l'appeler, et pour qu'il n'y en ait qu'une seule version.
 */

require_once __DIR__ . '/sso.php';
require_once __DIR__ . '/class/class_bdd.php';

/**
 * Remplit $_SESSION a partir d'une identite lue dans le cookie commun.
 * Renvoie false si l'identite ne correspond a aucune ligne exploitable :
 * l'appelant decide alors quoi faire.
 */
function fastevalOuvrirSession(array $identity): bool
{
    $db = bdd::connexion(scolaxieConfig('SCOLAXIE_DB_NAME'));

    if ($identity['role'] === 'student') {
        $stmt = $db->prepare('SELECT c.id_eleve,c.id_enseignant,c.nom,c.prenom,e.classe1
            FROM classe c
            INNER JOIN enseignant e ON e.id_enseignant=c.id_enseignant
            WHERE c.id_eleve=:sid AND c.id_enseignant=:tid AND c.actif=1 LIMIT 2');
        $stmt->execute(array(':sid' => (int)$identity['sid'], ':tid' => (int)$identity['tid']));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) { return false; }
        $row = $rows[0];
        session_regenerate_id(true);
        $_SESSION['role'] = 'eleve';
        $_SESSION['permission'] = true;
        $_SESSION['bdd'] = scolaxieConfig('SCOLAXIE_DB_NAME');
        $_SESSION['id_enseignant'] = (int)$row['id_enseignant'];
        $_SESSION['id_eleve'] = (int)$row['id_eleve'];
        $_SESSION['nom_client'] = $row['nom'];
        $_SESSION['prenom_client'] = $row['prenom'];
        // La classe appartient a l'enseignant dans le schema actuel : tous ses
        // eleves suivent donc le meme niveau. Fast Games l'utilise pour ne pas
        // proposer a un CE1 les jeux reserves au CE2.
        $_SESSION['classe_client'] = $row['classe1'];
        $_SESSION['saisie'] = 'debut';
        $_SESSION['justification_eleve'] = '';
        return true;
    }

    $stmt = $db->prepare(
        'SELECT ad.id_client,ad.bdd,ad.id_enseignant,ad.est_admin,e.classe1,
                e.nom AS nom_enseignant,e.prenom AS prenom_enseignant,
                et.nom AS nom_etablissement,et.ville,et.departement,et.tel,et.adresse
           FROM ayant_droit ad
           LEFT JOIN enseignant e ON e.id_enseignant=ad.id_enseignant
           LEFT JOIN info_client ic ON ic.id_client=ad.id_client
           LEFT JOIN etablissement et ON et.id_etablissement=ic.id_etablissement
          WHERE ad.id_enseignant=:tid LIMIT 2'
    );
    $stmt->execute(array(':tid' => (int)$identity['tid']));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) { return false; }

    $row = $rows[0];
    session_regenerate_id(true);
    $_SESSION['role'] = 'enseignant';
    $_SESSION['id_client'] = $row['id_client'];
    // Le cartouche doit identifier la personne connectee, pas le titulaire
    // du compte client partage par plusieurs enseignants.
    $_SESSION['nom_client'] = $row['nom_enseignant'];
    $_SESSION['prenom_client'] = $row['prenom_enseignant'];
    $_SESSION['bdd'] = $row['bdd'];
    $_SESSION['nom_etablissement'] = $row['nom_etablissement'];
    $_SESSION['ville_etablissement'] = $row['ville'];
    $_SESSION['departement_etablissement'] = $row['departement'];
    $_SESSION['tel_etablissement'] = $row['tel'];
    $_SESSION['adresse_etablissement'] = $row['adresse'];
    $_SESSION['permission'] = true;
    $_SESSION['justification'] = '';
    $_SESSION['saisie'] = 'debut';
    $_SESSION['bilan'] = 'debut';
    $_SESSION['classe_client'] = $row['classe1'];
    $_SESSION['id_enseignant'] = (int)$row['id_enseignant'];
    $_SESSION['est_admin'] = (bool)$row['est_admin'];
    return true;
}

/**
 * Garde-fou des ecrans enseignants atteignables par lien direct depuis les
 * autres sites. Si la session Fast Éval existe deja, on ne fait rien. Sinon,
 * on l'ouvre a partir du cookie du portail plutot que de renvoyer le visiteur
 * sur une page de connexion.
 */
function fastevalExigerEnseignant(): void
{
    if (!empty($_SESSION['permission'])) {
        if (($_SESSION['role'] ?? '') !== 'enseignant') {
            header('Location: presentation.php');
            exit;
        }
        return;
    }

    $identity = ssoReadToken();
    if (!$identity) {
        header('Location: /portail/?session=expiree');
        exit;
    }

    if (!fastevalOuvrirSession($identity)) {
        header('Location: /portail/?erreur=liaison');
        exit;
    }

    if (($_SESSION['role'] ?? '') !== 'enseignant') {
        header('Location: presentation.php');
        exit;
    }
}
