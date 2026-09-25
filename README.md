# Scolaxie

Scolaxie est une suite d'outils numériques pédagogiques pour l'école primaire. Ce dépôt est une distribution publique partielle du code source actuellement utilisé pour Scolaxie. Il présente le portail commun, Fast Éval, Fast Games et School Monsters. [Clic & Mots](https://github.com/popolski/ClicEtMots) est maintenu dans un dépôt indépendant : aucun de ses fichiers n'est copié ici.

| Application | Rôle | Sources |
| --- | --- | --- |
| Portail | Connexion et accès aux espaces élève et enseignant | [`site/v2/portail/`](site/v2/portail/) |
| Fast Éval | Évaluations, référentiel, saisie, classe et bilans | [`site/v2/fasteval/`](site/v2/fasteval/) |
| Fast Games | Mini-jeux, catalogue, studio et suivi | [`site/v2/fastgames/`](site/v2/fastgames/) |
| School Monsters | Interfaces des leçons, de l'éditeur et du lecteur PDF, avec parcours incomplets dans cette distribution | [`site/v2/schoolmonsters/`](site/v2/schoolmonsters/) |

Les interfaces visent de jeunes élèves et différents écrans, avec navigation au clavier et espaces distincts selon le rôle. Le portail et les applications partagent une authentification signée et des composants visuels communs dans `site/v2/`.

## État de cette distribution

Cette première distribution permet de lire une partie du code source ; elle n'est pas prête à installer comme une instance complète. Le schéma nécessaire pour recréer intégralement une base vierge reste en cours de documentation. Les grandes banques pédagogiques, logos, illustrations et sons dont les droits ne sont pas établis sont exclus. Une banque vide occupe `site/v2/fastgames/data/banques.php` : elle préserve le chargement du moteur sans fournir les activités manquantes.

Pour School Monsters, le code de plusieurs interfaces est présent, mais les 171 pages pédagogiques ne sont pas distribuées. Des dépendances de l'éditeur sont absentes ; PDF.js et les documents PDF ne sont pas fournis. Les parcours des leçons, de l'éditeur et du lecteur PDF ne sont donc pas opérationnels dans ce candidat public.

Dans Fast Éval, plusieurs parcours PDF dépendent encore de tFPDF, de fontes et de composants historiques non distribués. La variable `SCOLAXIE_TFPDF_DIR` ne suffit pas à rendre tous ces PDF opérationnels.

**Le dépôt ne contient ni identifiants, ni données de production. Une configuration locale est nécessaire pour exécuter Scolaxie.** Voir [configuration](docs/configuration.md), [base de données](docs/base-de-donnees.md), [architecture](docs/architecture.md) et [médias et licences](docs/medias-licences.md). Aucune capture n'est incluse avant vérification des droits et des données visibles.

## Licence

Le code source de Scolaxie est distribué sous licence GNU AGPL v3. Voir le texte complet dans [LICENSE](LICENSE). Les bibliothèques tierces conservent leurs licences propres ; les contenus pédagogiques et médias exclus ne reçoivent aucune licence dans ce dépôt. Voir [médias et licences](docs/medias-licences.md).

Copyright © 2026 Hugues Dubois et Camille Vandewalle.
