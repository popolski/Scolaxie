<?php
/* Icônes partagées de la galaxie Scolaxie, en SVG trait cohérent avec le socle.

   Remplace les emojis et sigles hétérogènes par un tracé unique et stable : les
   emojis changent de rendu selon la plateforme et jurent avec le soin du socle.
   Le style suit celui des pictos déjà présents (trait 1.8, arrondis, 24×24).

   La couleur vient du contexte : .gx-tuile .ic, .mj-jeu-icone, .mj-sigle,
   .fg-passeport-matiere-sigle et les autres conteneurs définissent déjà color,
   donc `currentColor` teinte l'icône par application (turquoise, ambre, corail,
   vert ou violet) sans jeton supplémentaire.

   Liste fermée : un nom inconnu rend une chaîne vide plutôt que d'inventer un
   pictogramme. */
function gxIcone(string $nom): string
{
    $commun = 'viewBox="0 0 24 24" width="24" height="24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false"';

    $horloge = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
    $calcul  = '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6"/><path d="M9 12h.01M12 12h.01M15 12h.01M9 16h.01M12 16h.01M15 16h.01"/>';
    $monnaie = '<circle cx="12" cy="12" r="9"/><path d="M7 12h10"/>';

    $chemins = array(
        // Fast Éval : accueil enseignant.
        'saisir'      => '<path d="M4 20h4L19.5 8.5a2.12 2.12 0 0 0-3-3L5 17z"/><path d="M13.5 6.5l3 3"/>',
        'bulletin'    => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h6M9 16h6"/>',
        'classe'      => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19c.4-3.2 2.3-5 5.5-5s5.1 1.8 5.5 5"/><circle cx="16.5" cy="9" r="2.2"/><path d="M15.6 14.6c2 .3 3.4 1.8 3.8 4.4"/>',
        'referentiel' => '<path d="M4 6h16M4 12h16M4 18h10"/>',

        // Fast Games : mécaniques.
        'tri'     => '<path d="M4 6h16M4 12h11M4 18h6"/>',
        'paires'  => '<circle cx="7" cy="7" r="3"/><circle cx="17" cy="17" r="3"/><path d="M9.5 9.5 14.5 14.5"/>',
        'ordre'   => '<path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/>',
        'ecoute'  => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="2"/><rect x="17" y="14" width="4" height="6" rx="2"/>',
        'qcm'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.8.3-1 1-1 2.2"/><path d="M12 17h.01"/>',
        'carte'   => '<circle cx="11" cy="11" r="6"/><path d="M16 16l4 4"/>',
        'droite'  => '<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
        'defi'    => '<path d="M13 2 5 14h6l-1 8 8-12h-6z"/>',

        // Fast Games : jeux spéciaux.
        'calcul'  => $calcul,
        'monnaie' => $monnaie,
        'horloge' => $horloge,

        // Fast Games : catégories du référentiel.
        'nombre'    => '<path d="M9 4 7 20M17 4l-2 16M5 9h14M4 15h14"/>',
        'grandeur'  => '<rect x="3" y="9" width="18" height="6" rx="1"/><path d="M7 9v3M11 9v3M15 9v3M19 9v3"/>',
        'geometrie' => '<path d="M4 20 14 4l6 6-10 10z"/><path d="M11 9l4 4"/>',
        'probleme'  => '<path d="M9 18a6 6 0 1 1 6 0c0 2-1 3-1 3h-4s-1-1-1-3z"/><path d="M10 21h4"/>',
        'temps'     => $horloge,
        'donnee'    => '<path d="M4 4v16h16"/><path d="M9 20v-6h3v6zM15 20V8h3v12z"/>',
        'picto-defaut' => '<path d="M12 3l7 9-7 9-7-9z"/>',

        // Fast Games : matières du catalogue et du passeport.
        'matiere-maths'     => $calcul,
        'matiere-francais'  => '<path d="M5 4h13a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M5 16h14"/>',
        'matiere-histoire'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'matiere-sciences'  => '<path d="M10 3h4M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3l-5-9V3"/>',

        // Fast Games : sujets des cartes du catalogue.
        'animal' => '<path d="M5 17c0-4 3-7 7-7s7 3 7 7v3H5z"/><circle cx="7" cy="7" r="1"/><circle cx="11" cy="5" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="19" cy="7" r="1"/>',
        'sens' => '<path d="M4 12c2-4 5-6 8-6s6 2 8 6c-2 4-5 6-8 6s-6-2-8-6z"/><circle cx="12" cy="12" r="2"/>',
        'eau' => '<path d="M12 2C9 7 5 11 5 15a7 7 0 0 0 14 0c0-4-4-8-7-13z"/>',
        'plante' => '<path d="M12 21V9M12 14C6 14 4 11 4 6c5 0 8 2 8 8zM12 11c0-5 3-7 8-7 0 5-3 7-8 7z"/>',
        'electricite' => '<path d="M13 2 5 14h6l-1 8 9-13h-7z"/>',
        'epoque' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 3"/>',
        'paysage' => '<path d="M3 19h18L15 9l-4 6-2-3z"/><circle cx="6" cy="6" r="2"/>',
        'phrase' => '<path d="M4 6h16M4 11h13M4 16h16M4 21h8"/>',
        'mot' => '<path d="M3 19 9 5l6 14M5 15h8M17 5v14M17 12h4"/>',
        'son' => '<path d="M4 10v4h4l5 4V6l-5 4zM16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12"/>',
        'solide' => '<path d="m12 3 8 5v9l-8 4-8-4V8zM4 8l8 5 8-5M12 13v8"/>',
        'figure' => '<rect x="4" y="4" width="16" height="16" rx="1"/><path d="M4 16 16 4"/>',
        'aliment' => '<path d="M12 7c-3-5-9-3-9 4 0 5 4 10 9 10s9-5 9-10c0-7-6-9-9-4zM12 7c0-3 1-5 4-5"/>',
        'sport' => '<circle cx="12" cy="12" r="9"/><path d="m8 4 4 3 4-3M3 12l5-1 2 5-3 4M21 12l-5-1-2 5 3 4"/>',
        'transport' => '<rect x="4" y="7" width="16" height="11" rx="2"/><path d="M6 7V5h12v2M4 13h16"/><circle cx="8" cy="19" r="1"/><circle cx="16" cy="19" r="1"/>',
        'musique' => '<path d="M9 17V5l10-2v12M9 5l10-2"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/>',
        'alphabet' => '<path d="M3 19 8 5l5 14M5 15h6M15 5h6l-6 14h6"/>',
        'puzzle' => '<path d="M4 4h6a2 2 0 1 1 4 0h6v6a2 2 0 1 1 0 4v6h-6a2 2 0 1 1-4 0H4v-6a2 2 0 1 1 0-4z"/>',
        'fraction' => '<circle cx="12" cy="12" r="9"/><path d="M12 3v9h9"/>',
        'robot' => '<rect x="5" y="7" width="14" height="13" rx="2"/><path d="M12 3v4M3 12h2M19 12h2"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><path d="M9 16h6"/>',
        'regle' => '<rect x="3" y="8" width="18" height="9" rx="1"/><path d="M7 8v4M11 8v6M15 8v4M19 8v6"/>',
        'balance' => '<path d="M12 3v18M5 21h14M4 8h16M6 8l-3 7h6zM18 8l-3 7h6z"/>',
        'collection' => '<circle cx="6" cy="7" r="2"/><circle cx="12" cy="7" r="2"/><circle cx="18" cy="7" r="2"/><circle cx="6" cy="17" r="2"/><circle cx="12" cy="17" r="2"/><circle cx="18" cy="17" r="2"/>',
        'livre' => '<path d="M12 6C9 4 6 4 3 5v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1zM12 6v14"/>',
        'nature' => '<path d="M4 20c0-8 4-14 16-16 0 12-6 16-16 16zM4 20c4-6 8-9 14-12"/>',
    );

    if (!isset($chemins[$nom])) {
        return '';
    }

    return '<svg ' . $commun . '>' . $chemins[$nom] . '</svg>';
}
