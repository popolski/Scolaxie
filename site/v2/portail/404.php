<?php
// Lot 5 (04/09/2026) : avant, une page 404 par defaut d'Apache, en anglais,
// sans aucun rapport avec le site. Declaree dans .htaccess (ErrorDocument
// 404), au niveau racine de /v2/ : elle repond donc pour les cinq
// surfaces, pas seulement pour le portail. Voir le releve UI/UX du
// 05/09/2026, planche 8.
http_response_code(404);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Page introuvable - Scolaxie</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/v2/portail/style.css?v=20260912-conformite">
</head>
<body class="login-page">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="brand brand-login">
    <img src="/v2/portail/logo-scolaxie.png" alt="Scolaxie — Une galaxie d’outils pour apprendre">
</header>

<main id="contenu" style="max-width:640px;margin:40px auto;padding:0 24px 48px">
    <div class="gx-etat-vide" style="grid-template-columns:auto 1fr;--gx-filet:var(--v2-marine,#1c246b)">
        <span class="gx-etat-vide-icone" aria-hidden="true">?</span>
        <div>
            <span class="gx-etat-vide-surtitre">Erreur 404</span>
            <h2>Cette page n'existe pas</h2>
            <p>L'adresse est peut-être mal écrite, ou la page a changé de nom. Retourne au portail pour retrouver ton espace.</p>
        </div>
    </div>
    <p style="margin-top:24px;text-align:center">
        <a href="/portail/" style="color:var(--v2-marine,#1c246b);font-weight:600">&larr; Retour au portail</a>
    </p>
</main>

</body>
</html>
