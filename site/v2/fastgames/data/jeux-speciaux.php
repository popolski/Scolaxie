<?php
/**
 * Les jeux « speciaux », par matiere : ni generes depuis le referentiel, ni
 * des banques - le compte est bon et la petite boutique, chacun avec son
 * propre moteur (includes/compte-est-bon.php, includes/boutique.php).
 *
 * Source unique, lue par catalogue.php (pour l'afficher) et passeport.php
 * (pour retrouver son meilleur score) : avant le 04/09/2026 ce tableau
 * n'existait que dans catalogue.php, et passeport.php n'en savait rien - un
 * eleve pouvait reussir « Le compte est bon » sans que son passeport ne
 * bouge d'un pourcent.
 */
return array(
    'Mathématiques' => array(
        array(
            'special' => 'compte-est-bon',
            'titre' => 'Le compte est bon',
            'consigne' => 'Compose un calcul avec + − × ÷ pour tomber exactement juste.',
            'sigle' => '+−',
        ),
        array(
            'special' => 'boutique',
            'titre' => 'La petite boutique',
            'consigne' => 'Compose une somme avec des pièces et des billets.',
            'sigle' => '€',
        ),
        // Ajoute le 06/09/2026. Comme la boutique en son temps, ce jeu existe
        // parce qu'une competence du programme etait marquee « ne se joue
        // pas » faute d'un cadran manipulable (3980 et 3981, voir
        // data/competences-jouables.php et includes/horloge.php).
        array(
            'special' => 'horloge',
            'titre' => 'L’horloge',
            'consigne' => 'Place les aiguilles pour afficher l’heure demandée.',
            'sigle' => '🕐',
        ),
    ),
);
