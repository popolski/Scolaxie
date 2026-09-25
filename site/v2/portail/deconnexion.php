<?php
require_once __DIR__ . '/sso.php';
ssoClearCookie();

// Les trois applications PHP partagent la meme session (cookie PHPSESSID pose
// sur /). La detruire ici deconnecte Fast Eval, School Monsters et Fast Games
// d'un coup, sans endpoint dedie dans chacune. Clic & Mots garde sa session
// sous /v2/clicetmots/ et reste fermee par son API appelee ci-dessous.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Déconnexion - Scolaxie</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-k10-v2">
    <link rel="stylesheet" href="style.css?v=20260912-conformite">
</head>
<body class="gx-typo">
<main class="gx-largeur-lecture">
<section class="gx-panneau" aria-live="polite">
    <span class="spinner" aria-hidden="true"></span>
    <div class="gx-ligne-titre">
    <h1>Déconnexion en cours</h1>
    <p class="gx-ligne-titre-description">Nous fermons les applications en toute sécurité.</p>
    </div>
    <noscript><a class="gx-bouton" href="/portail/">Retour au portail</a></noscript>
</section>
</main>
<script>
Promise.allSettled([
    fetch('/clicetmots/api/logout.php', {method: 'POST', credentials: 'include'})
]).finally(() => location.replace('/portail/'));
</script>
</body>
</html>
