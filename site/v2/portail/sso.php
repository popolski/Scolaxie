<?php

// La V2 partage volontairement le jeton du portail historique : une personne
// déjà connectée peut comparer les deux interfaces sans se réauthentifier.
require_once dirname(__DIR__, 2) . '/configuration.php';

const CV_SSO_COOKIE = 'cv_sso';
const CV_SSO_LIFETIME = 28800;
// Adresse PUBLIQUE du portail, celle qu'on met dans un en-tete Location.
// Passee de /v2/portail/ a /portail/ le 06/09/2026 : le masque du .htaccess
// racine sert le meme portail aux deux adresses, et cette constante commande
// a elle seule les trois retours au portail - apres connexion, apres echec de
// connexion, et sur session expiree. Le nom de la constante garde son « V2 »
// pour ne pas toucher a ses quatre appels, mais ce n'est plus qu'un nom.
// Les fichiers, eux, n'ont pas bouge de /v2/portail/.
const CV_PORTAIL_V2_PATH = '/portail/';

function ssoBase64Encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function ssoBase64Decode(string $value): string|false
{
    $padding = strlen($value) % 4;
    if ($padding) {
        $value .= str_repeat('=', 4 - $padding);
    }
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function ssoCreateToken(array $identity): string
{
    $now = time();
    $payload = array(
        'v' => 1,
        'iat' => $now,
        'exp' => $now + CV_SSO_LIFETIME,
        'role' => $identity['role'],
        'tid' => (int)$identity['tid'],
        'sid' => isset($identity['sid']) ? (int)$identity['sid'] : 0,
        'lid' => isset($identity['lid']) ? (int)$identity['lid'] : 0,
        'label' => (string)$identity['label'],
        'admin' => !empty($identity['admin']),
    );
    $encoded = ssoBase64Encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $encoded . '.' . hash_hmac('sha256', $encoded, CV_SSO_SECRET);
}

function ssoReadToken(?string $token = null): ?array
{
    $token = $token ?? ($_COOKIE[CV_SSO_COOKIE] ?? '');
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], CV_SSO_SECRET), $parts[1])) {
        return null;
    }
    $json = ssoBase64Decode($parts[0]);
    $payload = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($payload) || ($payload['v'] ?? 0) !== 1 || ($payload['exp'] ?? 0) < time()) {
        return null;
    }
    if (!in_array($payload['role'] ?? '', array('student', 'teacher'), true)) {
        return null;
    }
    if ((int)($payload['tid'] ?? 0) <= 0) {
        return null;
    }
    if ($payload['role'] === 'student' && (int)($payload['sid'] ?? 0) <= 0) {
        return null;
    }
    return $payload;
}

function ssoSetCookie(string $token): void
{
    setcookie(CV_SSO_COOKIE, $token, array(
        'expires' => time() + CV_SSO_LIFETIME,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
}

function ssoClearCookie(): void
{
    setcookie(CV_SSO_COOKIE, '', array(
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
}

function ssoRequireIdentity(): array
{
    $identity = ssoReadToken();
    if (!$identity) {
        header('Location: ' . CV_PORTAIL_V2_PATH . '?session=expiree');
        exit;
    }
    return $identity;
}
