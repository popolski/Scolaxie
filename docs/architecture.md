# Architecture

L'arborescence `site/` reprend les chemins physiques attendus par les inclusions PHP : `site/v2/portail`, `fasteval`, `fastgames` et `schoolmonsters`. `site/v2/galaxie-icones.php`, `galaxie-role.php` et `galaxie-tokens.css` sont communs et n'existent qu'une fois. Le `.htaccess` de `site/` fournit les routes publiques sans préfixe `/v2/`, tandis que les fichiers restent sous `/v2/`.

Le portail signe l'identité par HMAC-SHA256 ; Fast Éval et School Monsters lisent le même jeton. Fast Games utilise la session Fast Éval. Les quatre espaces utilisent une base MySQL partagée. Les valeurs locales proviennent des variables d'environnement lues par `site/configuration.php` et l'adaptateur PDO `site/v2/fasteval/utils/class/class_bdd.php`.

Clic & Mots est un [projet séparé](https://github.com/popolski/ClicEtMots). Les liens applicatifs vers cet espace supposent qu'une installation locale le configure séparément ; le code n'est pas inclus.

Les sources diffusées n'incluent pas les contenus pédagogiques ou médias non qualifiés. La navigation vers ces contenus peut donc aboutir à une ressource absente. Cette limite est décrite dans le README et dans [médias et licences](medias-licences.md).
