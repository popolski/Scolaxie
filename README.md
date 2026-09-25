<h1 align="center">🌌 Scolaxie</h1>

<h3 align="center">Une galaxie d'outils numériques pour l'école primaire</h3>

<p align="center"><strong>Évaluer · S'entraîner · Apprendre · Suivre les progrès</strong></p>

<p align="center">
  <a href="https://www.scolaxie.fr">Site Scolaxie</a> ·
  <a href="https://github.com/popolski/ClicEtMots">Clic &amp; Mots</a> ·
  <a href="LICENSE">Licence</a>
</p>

<p align="center">
  <a href="LICENSE"><img alt="Licence GNU AGPL v3" src="https://img.shields.io/badge/licence-AGPL--3.0-2b6cb0"></a>
  <img alt="Sources PHP 8.0 ou version ultérieure" src="https://img.shields.io/badge/PHP-8.0%2B-777bb4">
  <img alt="Distribution publique partielle" src="https://img.shields.io/badge/distribution-source%20partielle-7b4bb7">
</p>

Scolaxie réunit des outils numériques pédagogiques pour l'école primaire. Le projet est conçu autour d'usages de classe concrets : préparer et saisir des évaluations, proposer des activités, puis consulter les résultats et les progrès. Ce dépôt présente **une partie du code source actuellement utilisé** ; il ne constitue pas une installation prête à l'emploi.

## Les applications

| Application | Rôle et sources présentes |
| --- | --- |
| **Fast Éval** | Évaluations, référentiel, saisie, gestion de classe et bilans. [Voir les sources](site/v2/fasteval/). |
| **Fast Games** | Mini-jeux, catalogue, studio enseignant et suivi des parties. [Voir les sources](site/v2/fastgames/). |
| **School Monsters** | Interfaces pour les périodes, les leçons, l'éditeur et le lecteur PDF ; plusieurs de ces parcours sont incomplets ici. [Voir les sources](site/v2/schoolmonsters/). |
| **Portail Scolaxie** | Connexion, accueil et accès aux espaces élève et enseignant par authentification signée. [Voir les sources](site/v2/portail/). |

### Clic & Mots

Clic & Mots fait partie de l'écosystème Scolaxie, mais son code est maintenu dans [son propre dépôt](https://github.com/popolski/ClicEtMots). Aucun de ses fichiers n'est copié ici. Les liens applicatifs vers cet espace supposent une installation séparée.

## Principes du projet

- Des interfaces pensées pour de jeunes élèves, avec des présentations adaptées à plusieurs tailles d'écran.
- Des repères de navigation au clavier lorsque cela est pertinent, notamment des liens d'évitement et des contrôles natifs.
- Des espaces distincts pour les élèves et les enseignants, avec des activités organisées par classe, niveau ou période selon l'application.
- Des écrans de saisie et de suivi des apprentissages, alimentés par les données de l'installation.
- Une identité et des composants visuels communs entre les applications.

Ces principes se lisent dans les sources ; ils ne valent pas validation de tous les parcours dans cette distribution partielle.

## Aperçu

Aucune capture n'est publiée pour le moment. Les droits des images et l'absence de données personnelles visibles seront vérifiés avant tout ajout.

<!-- Emplacements futurs, sans images ni liens visibles tant que les fichiers n'existent pas :
docs/screenshots/portail.png
docs/screenshots/fast-eval.png
docs/screenshots/fast-games.png
docs/screenshots/school-monsters.png
-->

## Architecture en bref

```text
site/
├── configuration.php          Configuration locale attendue
└── v2/
    ├── portail/               Connexion et SSO commun
    ├── fasteval/              Évaluations et classe
    ├── fastgames/             Jeux et suivi ; session Fast Éval
    ├── schoolmonsters/        Interfaces des périodes et leçons
    └── galaxie-*              Composants visuels communs

Clic & Mots → dépôt source séparé
```

Les quatre espaces PHP utilisent une base MySQL partagée. Les chemins du schéma représentent les sources distribuées, pas une instance complète en fonctionnement. Voir [l'architecture détaillée](docs/architecture.md).

## État de cette distribution

**Cette distribution publique est partielle et n'est pas encore installable comme une instance complète de Scolaxie.** Le schéma complet permettant de reconstruire une base vierge n'est pas fourni. Une configuration locale, un schéma validé et des données fictives seraient nécessaires pour exécuter les parcours dépendant de la base.

- **Contenus et médias :** les grandes banques pédagogiques, les logos, certaines illustrations et les sons dont les droits ne sont pas établis sont exclus. La [banque Fast Games](site/v2/fastgames/data/banques.php) est volontairement vide : elle permet de charger le moteur, sans fournir les activités manquantes.
- **School Monsters :** le code de plusieurs interfaces est présent, mais **171 pages pédagogiques ne sont pas distribuées**. Des dépendances de l'éditeur manquent ; PDF.js et les documents PDF ne sont pas fournis. Les parcours des leçons, de l'éditeur et du lecteur PDF ne sont donc pas opérationnels ici.
- **Fast Éval :** plusieurs parcours PDF requièrent encore tFPDF, des fontes et des composants historiques non distribués. Ils ne sont pas opérationnels dans cette distribution ; définir `SCOLAXIE_TFPDF_DIR` ne suffit pas à rendre tous les PDF fonctionnels.
- **Exécution :** les sources emploient des fonctions et une syntaxe introduites avec **PHP 8.0**. Ce minimum est déterminé par lecture du code ; une installation complète sous PHP 8.0 n'a pas été validée.

Le dépôt ne contient ni identifiants ni données de production. `.env.example` montre des valeurs fictives et n'est pas chargé automatiquement. Aucune capture n'est incluse avant vérification des droits et des données visibles.

## Documentation et participation

- [Configuration locale](docs/configuration.md) et [état du schéma de base](docs/base-de-donnees.md)
- [Architecture](docs/architecture.md) et [médias et licences](docs/medias-licences.md)
- [Contribution](CONTRIBUTING.md) et [sécurité](SECURITY.md)
- [Texte complet de la licence](LICENSE)

## Licence

Le **code source Scolaxie** est distribué sous licence **GNU AGPL v3** : voir [LICENSE](LICENSE). Les bibliothèques tierces conservent leurs propres notices et licences. Les contenus pédagogiques et médias exclus ne reçoivent aucune licence publique dans ce dépôt ; leur éventuelle diffusion fera l'objet d'une décision distincte.

---

<p align="center">
  <strong>Scolaxie</strong><br>
  Des outils numériques pensés pour la classe. 🌌<br><br>
  Copyright © 2026 Hugues Dubois et Camille Vandewalle
</p>
