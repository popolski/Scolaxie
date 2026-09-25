<?php
require_once dirname(__DIR__, 4) . '/configuration.php';

class bdd
{
    public static function connexion(?string $base = null): PDO
    {
        $base = $base ?: scolaxieConfig('SCOLAXIE_DB_NAME');
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $base)) {
            throw new InvalidArgumentException('Nom de base invalide');
        }
        $hote = scolaxieConfig('SCOLAXIE_DB_HOST');
        $port = getenv('SCOLAXIE_DB_PORT') ?: '3306';
        if (!ctype_digit($port)) {
            throw new InvalidArgumentException('Port de base invalide');
        }
        return new PDO(
            "mysql:host={$hote};port={$port};dbname={$base};charset=utf8mb4",
            scolaxieConfig('SCOLAXIE_DB_USER'),
            scolaxieConfig('SCOLAXIE_DB_PASSWORD'),
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
    }
}
