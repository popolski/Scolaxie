# Installation

Une installation complète sur une base vierge n'est pas encore documentée. Cette distribution est destinée d'abord à la lecture et à la revue du code source. Les étapes ci-dessous décrivent la configuration attendue, sans promettre un démarrage fonctionnel complet.

1. Utiliser PHP avec PDO MySQL et les extensions nécessaires au code (`mbstring` notamment), MySQL/MariaDB et Apache avec `mod_rewrite`. Placer `site/` à la racine du serveur web.
2. Définir les variables de [configuration](configuration.md) avec des valeurs propres à l'installation locale. `.env.example` n'est pas chargé automatiquement.
3. Fournir un schéma de base validé et des données de démonstration fictives. Voir [base de données](base-de-donnees.md) pour les éléments encore manquants.
4. Installer séparément les dépendances PDF et les contenus/médias dont la redistribution sera autorisée. Voir [médias et licences](medias-licences.md).
5. Vérifier les routes, le SSO et les droits avec des comptes fictifs avant tout usage.

Le code public ne contient ni identifiants ni données de production. Les modules sans base, par exemple les icônes communes et la qualification de résultats Fast Games, peuvent être examinés indépendamment.
