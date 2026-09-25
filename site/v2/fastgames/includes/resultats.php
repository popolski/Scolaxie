<?php

function fgAssurerTableResultats(PDO $db): bool
{
    try {
        $db->query('SELECT 1 FROM fastgames_resultats LIMIT 1')->closeCursor();
        return true;
    } catch (PDOException $exception) {
        try {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS fastgames_resultats (
                    id_resultat BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_enseignant INT NOT NULL,
                    id_eleve INT NOT NULL,
                    programme VARCHAR(8) NOT NULL,
                    theme VARCHAR(32) NOT NULL,
                    categorie VARCHAR(48) NOT NULL,
                    generateur VARCHAR(48) NOT NULL,
                    type_reference VARCHAR(16) NULL,
                    id_reference INT NULL,
                    jeu VARCHAR(32) NOT NULL,
                    score SMALLINT UNSIGNED NOT NULL,
                    total SMALLINT UNSIGNED NOT NULL,
                    date_enr DATETIME NOT NULL,
                    PRIMARY KEY (id_resultat),
                    KEY idx_fastgames_enseignant_date (id_enseignant,date_enr),
                    KEY idx_fastgames_eleve_date (id_eleve,date_enr),
                    KEY idx_fastgames_categorie (id_enseignant,theme,categorie)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            fgAssurerIndexCompetence($db);
            return true;
        } catch (PDOException $creationException) {
            error_log('FastGames création table résultats impossible: ' . $creationException->getMessage());
            return false;
        }
    }
}

/**
 * Ajoute l'index dont le suivi par competence a besoin, s'il manque.
 *
 * La table a ete creee avant que ce suivi n'existe : elle est indexee par
 * enseignant, par eleve et par categorie, mais pas par competence. Sans cet
 * index, la vue « qui coince sur quoi » balaye toute la table.
 *
 * MySQL 5.7 ne connait pas CREATE INDEX IF NOT EXISTS, d'ou le passage par
 * information_schema plutot qu'un simple essai rattrape : une erreur SQL
 * attrapee polluerait le journal a chaque affichage.
 */
function fgAssurerIndexCompetence(PDO $db): bool
{
    try {
        $existe = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = :table
                AND index_name = :index'
        );
        $existe->execute(array(
            ':table' => 'fastgames_resultats',
            ':index' => 'idx_fastgames_competence',
        ));
        if ((int)$existe->fetchColumn() > 0) {
            return true;
        }
        $db->exec(
            'ALTER TABLE fastgames_resultats
               ADD INDEX idx_fastgames_competence (id_enseignant, id_reference, date_enr)'
        );
        return true;
    } catch (PDOException $exception) {
        error_log('FastGames index competence non cree: ' . $exception->getMessage());
        return false;
    }
}

function fgEnregistrerResultatDurable(PDO $db, array $resultat): bool
{
    if (empty($resultat['id_eleve']) || !fgAssurerTableResultats($db)) {
        return false;
    }
    try {
        $colonnesNiveau = '';
        $valeursNiveau = '';
        $niveau = (int)($resultat['niveau_depart'] ?? 0);
        $avecNiveau = false;
        if (in_array($resultat['jeu'], array('compte-est-bon', 'boutique', 'horloge'), true) && $niveau >= 1 && $niveau <= 5) {
            try {
                $db->query('SELECT niveau_depart,version_progression FROM fastgames_resultats LIMIT 0')->closeCursor();
                $avecNiveau = true;
            } catch (PDOException $e) {
                // Compatibilité avant migration : seul un schéma ancien est admis.
                if ((int)($e->errorInfo[1] ?? 0) !== 1054 && !str_contains($e->getMessage(), 'no such column')) throw $e;
            }
        }
        if ($avecNiveau) { $colonnesNiveau = ',niveau_depart,version_progression'; $valeursNiveau = ',:niveau_depart,:version_progression'; }
        $requete = $db->prepare(
            'INSERT INTO fastgames_resultats
                (id_enseignant,id_eleve,programme,theme,categorie,generateur,type_reference,id_reference,jeu,score,total,date_enr'.$colonnesNiveau.')
             VALUES
                (:id_enseignant,:id_eleve,:programme,:theme,:categorie,:generateur,:type_reference,:id_reference,:jeu,:score,:total,NOW()'.$valeursNiveau.')'
        );
        $parametres = array(
            ':id_enseignant' => (int)$resultat['id_enseignant'],
            ':id_eleve' => (int)$resultat['id_eleve'],
            ':programme' => (string)$resultat['programme'],
            ':theme' => (string)$resultat['theme'],
            ':categorie' => (string)$resultat['categorie'],
            ':generateur' => (string)$resultat['generateur'],
            ':type_reference' => $resultat['type_reference'] ?: null,
            ':id_reference' => $resultat['id_reference'] ?: null,
            ':jeu' => (string)$resultat['jeu'],
            ':score' => (int)$resultat['score'],
            ':total' => (int)$resultat['total'],
        );
        if ($avecNiveau) { $parametres[':niveau_depart'] = $niveau; $parametres[':version_progression'] = 'depart-20260913-v1'; }
        return $requete->execute($parametres);
    } catch (PDOException $exception) {
        error_log('FastGames stockage résultat indisponible: ' . $exception->getMessage());
        return false;
    }
}

/** Supprime uniquement les résultats de la classe de l'enseignant connecté. */
function fgSupprimerResultats(PDO $db, int $idEnseignant): ?int
{
    if ($idEnseignant <= 0 || !fgAssurerTableResultats($db)) {
        return null;
    }
    try {
        $requete = $db->prepare('DELETE FROM fastgames_resultats WHERE id_enseignant = :id_enseignant');
        $requete->execute(array(':id_enseignant' => $idEnseignant));
        return $requete->rowCount();
    } catch (PDOException $exception) {
        error_log('FastGames suppression résultats impossible: ' . $exception->getMessage());
        return null;
    }
}

/**
 * Les meilleurs resultats DURABLES d'un eleve, activite par activite.
 *
 * POURQUOI CETTE LECTURE EXISTE. Le passeport et les paliers des trois jeux a
 * moteur propre ne lisaient que $_SESSION : un eleve qui revenait le lendemain
 * repartait de zero, alors que ses parties etaient bien en base et que le
 * tableau de l'enseignante, lui, les affichait. Constate le 09/09/2026 sur un
 * eleve de recette : 18 parties et 6 activites distinctes en base, « 0 sur 41 »
 * au passeport dans une session neuve (FG-AUDIT-001 et FG-AUDIT-002).
 *
 * AUCUNE NOUVELLE TABLE, AUCUNE COLONNE. La progression se recalcule a partir
 * des resultats deja enregistres : un second systeme de progression finirait
 * par diverger de celui que voit l'enseignante.
 *
 * L'ISOLATION EST DOUBLE, eleve ET enseignant, comme partout ailleurs ici : un
 * identifiant d'eleve seul ne suffit pas a decider ce qu'on a le droit de lire.
 *
 * @return array array('categorie' => cle de banque => array(score,total),
 *                     'jeu' => identifiant de jeu => array(score,total))
 */
function fgMeilleursResultatsEleve(PDO $db, int $idEnseignant, int $idEleve): array
{
    $vide = array('categorie' => array(), 'jeu' => array());
    if ($idEnseignant <= 0 || $idEleve <= 0 || !fgAssurerTableResultats($db)) {
        return $vide;
    }
    try {
        // Le plafond de cadence est de 60 parties par jour et par eleve : cette
        // borne large protege la memoire sans jamais amputer un historique reel.
        $requete = $db->prepare(
            'SELECT categorie, jeu, score, total
               FROM fastgames_resultats
              WHERE id_enseignant = :id_enseignant AND id_eleve = :id_eleve AND total > 0
              ORDER BY date_enr DESC
              LIMIT 5000'
        );
        $requete->bindValue(':id_enseignant', $idEnseignant, PDO::PARAM_INT);
        $requete->bindValue(':id_eleve', $idEleve, PDO::PARAM_INT);
        $requete->execute();
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $resultat = array('score' => (int)$ligne['score'], 'total' => (int)$ligne['total']);
            fgRetenirMeilleur($vide['categorie'], (string)$ligne['categorie'], $resultat);
            fgRetenirMeilleur($vide['jeu'], (string)$ligne['jeu'], $resultat);
        }
    } catch (PDOException $exception) {
        error_log('FastGames progression durable indisponible: ' . $exception->getMessage());
        return $vide;
    }
    return $vide;
}

/**
 * Garde le meilleur des deux resultats sous une cle. Le meilleur se juge sur la
 * PROPORTION, pas sur le nombre brut : 3 sur 3 vaut mieux que 4 sur 6, et les
 * mecaniques n'ont pas toutes le meme nombre de questions.
 */
function fgRetenirMeilleur(array &$table, string $cle, array $resultat): void
{
    if ($cle === '' || (int)$resultat['total'] <= 0) {
        return;
    }
    $ancien = $table[$cle] ?? null;
    if ($ancien === null
            || $resultat['score'] / $resultat['total'] > $ancien['score'] / max(1, $ancien['total'])) {
        $table[$cle] = $resultat;
    }
}

/**
 * Rejoue l'historique durable d'un jeu a paliers et rend le niveau de depart.
 *
 * LA REGLE N'EST PAS REECRITE ICI : c'est exactement celle de
 * fgCompteAjusterNiveau() - 80 % ou plus fait monter d'un cran, 40 % ou moins
 * fait descendre, entre les deux rien ne bouge. La rejouer du plus ancien au
 * plus recent redonne le palier ou l'eleve en etait, sans stocker ce palier
 * nulle part.
 *
 * Les parties sont lues de la plus RECENTE a la plus ancienne puis remises a
 * l'endroit : c'est le seul ordre qu'un index (id_eleve, date_enr) sert
 * directement, et la borne coupe alors les parties les plus vieilles, pas les
 * plus recentes.
 */
function fgNiveauRejoue(PDO $db, int $idEnseignant, int $idEleve, string $jeu, int $min, int $max): int
{
    if ($idEnseignant <= 0 || $idEleve <= 0 || $jeu === '' || !fgAssurerTableResultats($db)) {
        return $min;
    }
    try {
        $requete = $db->prepare(
            'SELECT score, total
               FROM fastgames_resultats
              WHERE id_enseignant = :id_enseignant AND id_eleve = :id_eleve
                AND jeu = :jeu AND total > 0
              ORDER BY date_enr DESC
              LIMIT 500'
        );
        $requete->bindValue(':id_enseignant', $idEnseignant, PDO::PARAM_INT);
        $requete->bindValue(':id_eleve', $idEleve, PDO::PARAM_INT);
        $requete->bindValue(':jeu', $jeu, PDO::PARAM_STR);
        $requete->execute();
        $parties = array_reverse($requete->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $exception) {
        error_log('FastGames palier durable indisponible: ' . $exception->getMessage());
        return $min;
    }
    $niveau = $min;
    foreach ($parties as $partie) {
        $taux = (int)$partie['score'] / max(1, (int)$partie['total']);
        if ($taux >= 0.8) {
            $niveau++;
        } elseif ($taux <= 0.4) {
            $niveau--;
        }
        $niveau = max($min, min($max, $niveau));
    }
    return $niveau;
}

/**
 * Tous les chiffres de cette fonction portent sur la MEME periode, $jours.
 * L'activite recente l'ignorait et pouvait afficher une partie de l'an dernier
 * sous un titre annoncant trente jours.
 *
 * $jours est interpole dans le SQL et non lie : c'est volontaire, car MySQL
 * n'accepte pas de parametre apres INTERVAL. La borne max(1, min(365, ...)) sur
 * un entier deja type est donc ce qui rend l'interpolation sure - ne pas la
 * retirer, et ne pas recopier ce motif avec une valeur qui ne serait pas bornee.
 */
function fgTableauDeBord(PDO $db, int $idEnseignant, int $jours = 30): array
{
    $jours = max(1, min(365, $jours));
    $vide = array(
        'disponible' => true,
        'eleves_classe' => 0,
        'eleves_actifs' => 0,
        'quiz' => 0,
        'moyenne' => null,
        'alertes' => 0,
        'categories' => array(),
        'recents' => array(),
    );
    if (!fgAssurerTableResultats($db)) {
        $vide['disponible'] = false;
        return $vide;
    }
    try {
        $classe = $db->prepare('SELECT COUNT(*) FROM classe WHERE id_enseignant=:id_enseignant AND actif=1');
        $classe->execute(array(':id_enseignant' => $idEnseignant));
        $vide['eleves_classe'] = (int)$classe->fetchColumn();

        $resume = $db->prepare(
            'SELECT COUNT(*) AS quiz, COUNT(DISTINCT id_eleve) AS eleves_actifs,
                    ROUND(AVG(score * 100 / NULLIF(total,0))) AS moyenne
               FROM fastgames_resultats
              WHERE id_enseignant=:id_enseignant AND date_enr >= DATE_SUB(NOW(), INTERVAL ' . $jours . ' DAY)'
        );
        $resume->bindValue(':id_enseignant', $idEnseignant, PDO::PARAM_INT);
        $resume->execute();
        $ligne = $resume->fetch(PDO::FETCH_ASSOC) ?: array();
        $vide['quiz'] = (int)($ligne['quiz'] ?? 0);
        $vide['eleves_actifs'] = (int)($ligne['eleves_actifs'] ?? 0);
        $vide['moyenne'] = $ligne['moyenne'] === null ? null : (int)$ligne['moyenne'];

        $categories = $db->prepare(
            'SELECT theme,categorie,COUNT(*) AS quiz,ROUND(AVG(score * 100 / NULLIF(total,0))) AS moyenne
               FROM fastgames_resultats
              WHERE id_enseignant=:id_enseignant AND date_enr >= DATE_SUB(NOW(), INTERVAL ' . $jours . ' DAY)
              GROUP BY theme,categorie ORDER BY quiz DESC,moyenne ASC'
        );
        $categories->bindValue(':id_enseignant', $idEnseignant, PDO::PARAM_INT);
        $categories->execute();
        $vide['categories'] = $categories->fetchAll(PDO::FETCH_ASSOC);

        $alertes = $db->prepare(
            'SELECT COUNT(*) FROM (
                SELECT id_eleve FROM fastgames_resultats
                 WHERE id_enseignant=:id_enseignant AND date_enr >= DATE_SUB(NOW(), INTERVAL ' . $jours . ' DAY)
                 GROUP BY id_eleve HAVING COUNT(*) >= 2 AND AVG(score / NULLIF(total,0)) < .5
             ) AS eleves_a_suivre'
        );
        $alertes->bindValue(':id_enseignant', $idEnseignant, PDO::PARAM_INT);
        $alertes->execute();
        $vide['alertes'] = (int)$alertes->fetchColumn();

        $recents = $db->prepare(
            'SELECT r.programme,r.theme,r.categorie,r.score,r.total,r.date_enr,r.id_eleve,c.prenom,c.nom
               FROM fastgames_resultats r
               JOIN classe c ON c.id_eleve=r.id_eleve AND c.id_enseignant=r.id_enseignant
              WHERE r.id_enseignant=:id_enseignant
                AND r.date_enr >= DATE_SUB(NOW(), INTERVAL ' . $jours . ' DAY)
              ORDER BY r.date_enr DESC LIMIT 8'
        );
        $recents->execute(array(':id_enseignant' => $idEnseignant));
        $vide['recents'] = $recents->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        error_log('FastGames tableau de bord indisponible: ' . $exception->getMessage());
        $vide['disponible'] = false;
    }
    return $vide;
}
