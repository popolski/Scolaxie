# Configuration locale

`site/configuration.php` lit les variables d'environnement du processus PHP ; `.env.example` est une liste d'exemples fictifs et **n'est pas chargé automatiquement**. Configurez votre serveur local avec vos propres valeurs, sans les enregistrer dans Git.

| Variable | Usage |
| --- | --- |
| `SCOLAXIE_DB_HOST`, `SCOLAXIE_DB_PORT` | Hôte et port MySQL |
| `SCOLAXIE_DB_NAME`, `SCOLAXIE_DB_USER`, `SCOLAXIE_DB_PASSWORD` | Base principale et accès PDO |
| `SCOLAXIE_SSO_SECRET` | Secret local de signature, au moins 32 caractères aléatoires |
| `SCOLAXIE_CLASS_OPTIONS_JSON` | Tableau JSON des classes affichées à la connexion élève : objets `id` entier positif et `label` non vide |
| `SCOLAXIE_FASTGAMES_MODE` | `off`, `preview`, `teachers` ou `all` |
| `SCOLAXIE_PREVIEW_TEACHER_ID` | Identifiant local utilisé uniquement en mode `preview` |
| `SCOLAXIE_EDITOR_TEACHER_ID` | Identifiant local autorisé dans l’éditeur School Monsters |
| `SCOLAXIE_TFPDF_DIR` | Facultatif : dossier local utilisé par certaines classes PDF pour tFPDF et ses dépendances. D'autres parcours exigent encore des composants historiques absents ; cette variable ne suffit pas à les rendre opérationnels |

L'adaptateur PDO conserve le choix de base transmis par les sessions pour les installations qui utilisent plusieurs bases sur le même serveur. Il accepte uniquement un nom composé de lettres ASCII, chiffres et soulignements. Les valeurs marquées `REPLACE_` dans l'exemple sont refusées par la configuration.

Les domaines et identités du service existant ne sont pas intégrés au code public. Les ressources tierces et le schéma complet sont décrits séparément. Ne réutilisez jamais un secret SSO ou une base d'une installation réelle.
