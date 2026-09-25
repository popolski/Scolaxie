# Base de données

Le code utilise **MySQL/MariaDB via PDO**. Il ne contient aucune ligne issue d'une base de production. Le schéma complet d'une installation vierge n'est pas fourni : **Installation complète : schéma de base en cours de documentation**.

Tables explicitement référencées dans les requêtes des sources diffusées :

| Domaine | Tables repérées |
| --- | --- |
| Comptes, classe et établissements | `ayant_droit`, `enseignant`, `classe`, `droitsite`, `etablissement`, `info_client` |
| Évaluations et référentiel | `eval_eleves`, `eval_type`, `comp_eleves`, `comp_type` |
| Fast Games et rapports | `fastgames_resultats`, `fastgames_places_banques`, `fastgames_liens_competences`, `fastgames_periodes_rapport`, `fastgames_commentaires_rapport` |

Cet inventaire vient des références SQL littérales ; les noms produits dynamiquement et toutes les colonnes/contraintes n'ont pas été qualifiés. Les scripts SQL internes disponibles sont des migrations partielles, certains liés à une base réelle : ils n'ont pas été copiés. Un schéma public fiable demande encore une revue des `CREATE TABLE` à l'exécution, des clés étrangères, des migrations et des éventuelles bases multiples. Ne construisez pas de tables par supposition. Les données de démonstration devront être entièrement fictives.
