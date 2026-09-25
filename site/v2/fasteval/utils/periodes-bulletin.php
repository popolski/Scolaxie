<?php

/** Les bulletins Fast Éval changent d'année scolaire au mois d'août. */
function feAnneeBulletin(DateTimeImmutable $date): array
{
    $annee = (int)$date->format('Y') - ((int)$date->format('n') < 8 ? 1 : 0);
    return array('libelle' => $annee . '-' . ($annee + 1),
        'debut' => $annee . '-08-01', 'fin' => ($annee + 1) . '-07-31');
}

/** Une période affichée avec une fin inclusive se lit en SQL avec une fin exclusive. */
function feBornesBulletin(string $debut, string $fin): array
{
    $dates = array();
    foreach (array($debut, $fin) as $texte) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $texte, new DateTimeZone('Europe/Paris'));
        if (!$date || $date->format('Y-m-d') !== $texte) {
            throw new InvalidArgumentException('Date de période invalide.');
        }
        $dates[] = $date;
    }
    if ($dates[0] > $dates[1]) throw new InvalidArgumentException('La fin précède le début de la période.');
    return array('debut' => $debut, 'fin' => $fin,
        'sql_debut' => $debut . ' 00:00:00',
        'sql_fin' => $dates[1]->modify('+1 day')->format('Y-m-d') . ' 00:00:00');
}
