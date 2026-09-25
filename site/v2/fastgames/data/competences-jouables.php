<?php
/**
 * QUELLE COMPETENCE SE JOUE, ET AVEC QUELLE MECANIQUE.
 *
 * POURQUOI CE FICHIER EXISTE. Jusqu'ici, Fast Games devinait le jeu a partir de
 * mots trouves dans l'intitule. Mesure faite le 31/08/2026 sur les 185
 * competences du programme 2026/2027 : sur 26 competences reputees couvertes,
 * une bonne dizaine l'etaient a tort. « Decrire un cube, un pave ou une pyramide
 * en utilisant les termes face, SOMMET et arete » etait classee en additions,
 * parce que « sommet » contient « somme ». « Expliquer la difference entre ville
 * et village » devenait des soustractions. « Comparer des fractions ayant le
 * meme denominateur » tombait sur un generateur qui ne connait que les entiers.
 *
 * Un jeu faux est pire qu'un jeu manquant : le resultat serait enregistre au nom
 * d'une competence que l'eleve n'a pas travaillee, et le suivi de l’enseignante
 * mentirait. D'ou ce fichier, ou chaque competence est qualifiee A LA MAIN.
 *
 * TROIS ETATS, ET PAS DEUX. C'est la lecon principale de la mesure.
 *   - une mecanique nommee : la competence se joue, le jeu existe ou reste a
 *     ecrire ;
 *   - 'mecanique' => null avec une 'raison' : la competence NE SE JOUE PAS sur
 *     ecran, et ce n'est pas un manque de l'outil. « Realiser un circuit
 *     electrique avec une pile », « s'impliquer dans une action de preservation
 *     de l'environnement », « lire a 70 mots par minute » n'ont pas d'equivalent
 *     numerique honnete ;
 *   - absente de ce fichier : pas encore examinee.
 * Sans le deuxieme etat, l'ecran de l’enseignante afficherait une liste a moitie grise
 * et elle attendrait des jeux qui ne viendront jamais.
 *
 * LES MECANIQUES. Elles sont peu nombreuses et reutilisables, contrairement aux
 * generateurs actuels qui melangent la facon de jouer et le contenu joue.
 *   paires   associer deux colonnes (organe et sens, etat et changement d'etat)
 *   tri      classer des elements dans des categories (carnivore / herbivore)
 *   ordre    remettre dans l'ordre (frise, ordre croissant, chronologie)
 *   qcm      question a quatre reponses, ce que Fast Games sait deja faire
 *   ecoute   reconnaitre un son et l'ecrire
 *   carte    localiser sur un planisphere
 *   droite   placer un nombre sur une droite graduee
 *   calcul   generateur procedural, pour ce qui se fabrique a l'infini
 *
 * Et les trois jeux a moteur propre, qui portent en plus une cle 'special' :
 *   boutique, horloge, compte-est-bon.
 *
 * ATTENTION, VOCABULAIRE UNIFIE LE 06/09/2026. Cette liste disait « ranger »
 * la ou le moteur a toujours dit « ordre » : le nom de conception du 31/08
 * n'avait jamais ete repris quand la mecanique a ete ecrite. Les deux noms
 * cohabitaient dans ce fichier meme - quatre competences en « ranger », une
 * en « ordre » - ce qui prouve la derive plutot qu'une distinction voulue.
 * Tout est passe a « ordre », le nom que porte le code. Sans consequence
 * fonctionnelle : la seule chose que la production lit de ce champ est s'il
 * vaut null (« ne se joue pas »), jamais sa valeur. Le controle qui l'a
 * revele est tests/verifier-banques.php, qui compare la mecanique declaree
 * ici a celle de la banque.
 *
 * A TENIR A JOUR : ecoute, droite et les trois jeux speciaux manquaient aussi
 * a cette liste, ecrite le 31/08 et jamais relue depuis. Une liste de
 * mecaniques qui ment coute plus cher que pas de liste du tout.
 *
 * COMMENT LE CORRIGER. C'est un fichier de donnees, pas du code : ajouter,
 * retirer ou changer une ligne ne demande aucune connaissance technique. Les
 * identifiants sont ceux de comp_type dans Fast Eval. Si l’enseignante juge qu'une
 * competence marquee « ne se joue pas » se joue en fait, il suffit de lui donner
 * une mecanique.
 *
 * ETAT : les quatre matieres sont qualifiees depuis le 01/09/2026. Mathematiques
 * et Francais l ont ete en une passe, avec la meme regle qu Histoire-Geographie
 * et Sciences : chaque intitule relu integralement, aucune detection par mots-cles.
 *
 * DEUX PIEGES DE DONNEES TROUVES EN LISANT, propres au fonds 2026/2027 de ces
 * deux matieres : des intitules TRONQUES en base (ex. id 3902, 4020, coupes en
 * plein mot) et un intitule GARBLE avec des chiffres et virgules parasites
 * (id 3915 : « les 1111111 fractions,,,,, et 23456810 »). Sans savoir ce que le
 * texte dit vraiment, marquer ne se joue pas est le seul choix honnete.
 */

return array(

    // ================================================================
    // HISTOIRE-GEOGRAPHIE - 12 competences
    // La matiere la plus mal servie aujourd'hui : 1 jeu sur 12, et ce
    // jeu-la est faux. Pourtant presque tout s'y joue, parce qu'il
    // s'agit surtout de situer, localiser et associer.
    // ================================================================

    // -- Les grandes periodes de l'histoire --
    4190 => array(
        'mecanique' => 'paires',
        'contenu' => 'figure historique et periode',
        'note' => 'Vercingetorix et Antiquite, Jeanne d Arc et Moyen Age. La frise elle-meme se joue en 4191.',
    ),
    4191 => array(
        'mecanique' => 'ordre',
        'contenu' => 'les cinq grandes periodes',
        'note' => 'Remettre Prehistoire, Antiquite, Moyen Age, Temps modernes, Epoque contemporaine dans l ordre.',
    ),

    // -- Comment connait-on le passe ? --
    4192 => array(
        'mecanique' => 'tri',
        'contenu' => 'traces du passe par periode',
        'note' => 'L intitule donne deja les categories : fossiles, ossements, grottes, ruines, monuments, objets, ecrits, images, oeuvres d art, temoignages.',
    ),

    // -- Comment situer des evenements dans le passe ? --
    4188 => array(
        'mecanique' => 'ordre',
        'contenu' => 'lexique du temps',
        'note' => 'Du plus proche au plus lointain : hier, il y a dix jours, il y a dix ans, il y a cent ans, autrefois. Ecrite le 06/09/2026 : banque histoire-lexique-temps.',
    ),
    4189 => array(
        'mecanique' => 'ordre',
        'contenu' => 'evenements sur une frise',
        'note' => 'Melanger des evenements de la vie de l eleve et des evenements historiques, comme le demande l intitule. Ecrite le 06/09/2026 : banque histoire-frise-evenements.',
    ),

    // -- Ou les etres humains vivent-ils dans le monde ? --
    4204 => array(
        'mecanique' => 'carte',
        'contenu' => 'foyers de peuplement',
        'note' => 'Demande la mecanique carte, qui n existe pas encore.',
    ),
    4205 => array(
        'mecanique' => null,
        'raison' => 'placement trop precis pour des CE1',
        'note' => 'A ETE JOUABLE quelques heures le 06/09/2026 (banque geographie-villes-monde, mecanique carte) : l’enseignante a juge le jeu trop difficile pour ses eleves, la banque est retiree. Ce n est donc PAS un manque de l outil - la mecanique carte existe et sert toujours 4204 et 4208, sur des regions et des massifs ou la tolerance est bien plus large. Repasser cette competence en carte demanderait de changer ce qu on demande (situer un pays, une region), pas d ecrire du code.',
    ),
    4206 => array(
        'mecanique' => 'tri',
        'contenu' => 'ville ou village',
        'note' => 'Classer des caracteristiques dans deux colonnes. ETAIT CLASSEE EN SOUSTRACTIONS a cause du mot « difference ».',
    ),

    // -- Caracteristiques des lieux de vie --
    4207 => array(
        'mecanique' => 'tri',
        'contenu' => 'zones climatiques, vegetation, relief',
        'note' => 'Trois series de categories donnees par l intitule lui-meme.',
    ),
    4208 => array(
        'mecanique' => 'tri',
        'contenu' => 'zones climatiques, montagnes, deserts, fleuves',
        'note' => 'A D ABORD ETE ECRITE EN `carte` le 06/09/2026 (banque geographie-reliefs-fleuves), retiree le soir meme par l’enseignante avec 4205 : trop difficile, la tolerance descendait a 10 degres sur les Alpes. REPRISE EN `tri` dans la foulee, banque geographie-types-relief : on ne demande plus de placer un lieu au degre pres, on demande de reconnaitre a quoi on a affaire - meme competence, sans le geste de precision qui bloquait. Clé de banque NEUVE a dessein, pour ne pas heriter des scores de l ancien jeu carte.',
    ),
    4209 => array(
        'mecanique' => 'paires',
        'contenu' => 'paysages par couples opposes',
        'note' => 'Foret temperee et tropicale, desert chaud et froid, prairie et savane. Se joue tres bien avec des photos.',
    ),
    4210 => array(
        'mecanique' => null,
        'raison' => 'production orale ou graphique',
        'note' => 'Decrire un paysage a l oral ou par un dessin ne s evalue pas par un mini-jeu.',
    ),

    // ================================================================
    // SCIENCES ET TECHNOLOGIE - 30 competences
    // Beaucoup de manipulation reelle, donc beaucoup de « ne se joue
    // pas », et c'est normal : les sciences au CE1 se font avec les
    // mains. Ce qui reste tient presque entierement en paires et tri.
    // ================================================================

    // -- Etats physiques de la matiere --
    4133 => array(
        'mecanique' => 'qcm',
        'contenu' => 'changements d etat de l eau',
        'note' => 'L observation se fait en classe ; le jeu verifie ce qui en a ete retenu.',
    ),
    4134 => array(
        'mecanique' => 'paires',
        'contenu' => 'changement d etat et son nom',
        'note' => 'Solidification et fusion. Cas d ecole de la mecanique paires.',
    ),
    4135 => array(
        'mecanique' => 'qcm',
        'contenu' => 'conservation de la masse',
        'note' => null,
    ),
    4136 => array(
        'mecanique' => 'qcm',
        'contenu' => 'non-conservation du volume',
        'note' => null,
    ),
    4137 => array(
        'mecanique' => null,
        'raison' => 'experience a realiser',
        'note' => 'Mettre en evidence la materialite de l air demande du materiel.',
    ),

    // -- L electricite --
    4138 => array(
        'mecanique' => null,
        'raison' => 'montage reel',
        'note' => 'Realiser un circuit avec une pile, un interrupteur et une ampoule.',
    ),
    4139 => array(
        'mecanique' => 'tri',
        'contenu' => 'isolant ou conducteur',
        'note' => 'Deux categories, une liste de materiaux. Le test reel se fait en classe, le jeu consolide.',
    ),

    // -- Masse et volumes --
    4132 => array(
        'mecanique' => null,
        'raison' => 'mesure avec une balance',
        'note' => 'La comparaison des masses pourrait se jouer, mais l intitule porte d abord sur l acte de mesurer.',
    ),

    // -- Nutrition des etres vivants --
    4144 => array(
        'mecanique' => null,
        'raison' => 'demarche experimentale',
        'note' => 'L intitule demande explicitement une experience : semis, eau, lumiere.',
    ),
    4145 => array(
        'mecanique' => 'tri',
        'contenu' => 'organes de la plante',
        'note' => 'Racine, tige, feuille - trois organes seulement, donc pas assez pour quatre paires. En tri, chaque organe recoit quatre affirmations. La fleur est ecartee : c est un organe reproducteur, l intitule dit vegetatifs.',
    ),
    4146 => array(
        'mecanique' => 'tri',
        'contenu' => 'regimes alimentaires',
        'note' => 'Carnivore, herbivore, omnivore. Le meilleur candidat de la matiere pour la mecanique tri.',
    ),

    // -- Sens et perception chez les animaux --
    4147 => array(
        'mecanique' => 'paires',
        'contenu' => 'organe sensoriel et sens',
        'note' => 'Vision, audition, odorat, gout, equilibre, toucher : les couples sont dans l intitule.',
    ),

    // -- Observer et decrire son environnement proche --
    4148 => array(
        'mecanique' => null,
        'raison' => 'observation sur la duree',
        'note' => 'Le changement de peuplement au fil des saisons se constate dehors, sur des mois.',
    ),
    4149 => array(
        'mecanique' => 'paires',
        'contenu' => 'espece et milieu',
        'note' => null,
    ),
    4150 => array(
        'mecanique' => 'tri',
        'contenu' => 'relations entre etres vivants',
        'note' => 'Predation, cooperation, competition, selon ce qui est vu en classe.',
    ),

    // -- Croissance et mouvement --
    4167 => array(
        'mecanique' => null,
        'raison' => 'mesure avec instruments',
        'note' => null,
    ),
    4168 => array(
        'mecanique' => null,
        'raison' => 'activite de classe sur les eleves eux-memes',
        'note' => 'ETAIT CLASSEE EN SOUSTRACTIONS a cause de « differentes ». Comparer les tailles de la classe ne se simule pas.',
    ),
    4169 => array(
        'mecanique' => 'qcm',
        'contenu' => 'dents de lait et dents definitives',
        'note' => null,
    ),

    // -- Alimentation --
    4165 => array(
        'mecanique' => 'tri',
        'contenu' => 'aliments et apports',
        'note' => 'Energie, eau, mineraux, matiere : les categories sont donnees par l intitule.',
    ),
    4166 => array(
        'mecanique' => 'tri',
        'contenu' => 'equilibre alimentaire',
        'note' => 'Composer un repas equilibre par familles d aliments.',
    ),

    // -- Sante et hygiene de vie --
    4170 => array(
        'mecanique' => 'paires',
        'contenu' => 'regle d hygiene et effet sur la sante',
        'note' => 'L intitule demande de RELIER, ce qui designe la mecanique.',
    ),
    4171 => array(
        'mecanique' => null,
        'raison' => 'prise de conscience',
        'note' => 'Le role de l attention dans les apprentissages se travaille, il ne se teste pas par un jeu.',
    ),

    // -- Les objets techniques --
    4177 => array(
        'mecanique' => 'tri',
        'contenu' => 'parties d un objet technique',
        'note' => 'Trois entrees dans l intitule : forme, materiau, fonction.',
    ),
    4178 => array(
        'mecanique' => 'tri',
        'contenu' => 'objets electriques ou non',
        'note' => 'Deux categories franches, ideal pour la mecanique tri.',
    ),
    4179 => array(
        'mecanique' => null,
        'raison' => 'maquette a realiser',
        'note' => null,
    ),
    4180 => array(
        'mecanique' => null,
        'raison' => 'programmation d un robot',
        'note' => 'Se fait avec le robot de la classe. Un jeu de deplacement serait un autre exercice, pas celui-ci.',
    ),

    // -- Agir pour proteger l environnement --
    4151 => array(
        'mecanique' => null,
        'raison' => 'action reelle',
        'note' => null,
    ),
    4152 => array(
        'mecanique' => null,
        'raison' => 'rapport sensible',
        'note' => 'Rien de mesurable par un score, et c est tres bien ainsi.',
    ),
    4153 => array(
        'mecanique' => null,
        'raison' => 'prise de conscience',
        'note' => null,
    ),

    // -- Demarches scientifiques --
    4369 => array(
        'mecanique' => null,
        'raison' => 'reperage dans l espace reel',
        'note' => null,
    ),

    // ================================================================
    // MATHEMATIQUES - 82 competences
    // Beaucoup de calcul pur, deja au territoire des generateurs
    // classiques - mais chaque correspondance est reverifiee a la main,
    // le generateur actuel ne gerant que les entiers. Le reste se
    // heurte souvent au meme mur que les sciences hier : ce qui demande
    // un support visuel (droite graduee, cadran d horloge, diagramme,
    // fractions dessinees) n a pas encore de mecanique.
    // ================================================================

    // -- Espace et geometrie --
    4007 => array('mecanique' => 'paires', 'contenu' => 'nom du solide et description usuelle', 'note' => 'Cube, boule, cone, pyramide, cylindre, pave. Jouable via un objet du quotidien plutot qu une image.'),
    4008 => array('mecanique' => 'paires', 'contenu' => 'nommer un solide', 'note' => 'Meme famille que 4007, exercice miroir.'),
    4009 => array('mecanique' => 'paires', 'contenu' => 'vocabulaire face, sommet, arete', 'note' => 'Piege deja documente le 31/08 : sommet contient somme. Contenu reel = vocabulaire de description d un solide.'),
    4010 => array('mecanique' => 'paires', 'contenu' => 'nature des faces cube et pave', 'note' => 'Le cube a des faces carrees, le pave des faces rectangulaires - convention des manuels CE1, pas de piege dans ce cadre.'),
    4011 => array('mecanique' => null, 'raison' => 'fabrication manuelle', 'note' => 'Construire un solide en papier (patron, pliage).'),
    4012 => array('mecanique' => null, 'raison' => 'competence transversale', 'note' => 'Formulation trop generale pour un contenu propre ; deja couverte par 4009 et 4013.'),
    4013 => array('mecanique' => 'paires', 'contenu' => 'forme et definition', 'note' => 'Cercle, carre, rectangle, triangle, triangle rectangle.'),
    4014 => array('mecanique' => 'paires', 'contenu' => 'proprietes du carre et du rectangle', 'note' => 'Angles droits, egalite des cotes.'),
    4015 => array('mecanique' => null, 'raison' => 'trace au crayon', 'note' => 'Geste graphique avec des instruments.'),
    4016 => array('mecanique' => null, 'raison' => 'manipulation d instruments', 'note' => 'Regle et equerre physiques.'),
    4017 => array('mecanique' => null, 'raison' => 'manipulation d instruments', 'note' => 'Regle graduee, equerre, compas.'),
    4018 => array('mecanique' => null, 'raison' => 'notion trop ponctuelle', 'note' => 'Un seul symbole (angle droit) ; a regrouper avec 4014 le jour ou une banque est ecrite.'),
    4019 => array('mecanique' => 'paires', 'contenu' => 'vocabulaire de position', 'note' => 'Devant, derriere, dessus, dessous, entre, a cote de.'),
    4020 => array('mecanique' => null, 'raison' => 'intitule tronque', 'note' => 'Coupe en plein mot dans le referentiel : "par rapport" a quoi n est pas precise.'),
    4021 => array('mecanique' => null, 'raison' => 'depend d un espace reel', 'note' => 'Plan de l ecole ou de la classe, propre a chaque etablissement.'),
    4022 => array('mecanique' => null, 'raison' => 'manipulation physique', 'note' => 'Empilement de cubes et de paves.'),
    // Ouverte le 06/09/2026 par la mecanique `grille` (banque maths-robot-deplacement) :
    // ce n etait pas la competence qui resistait, c etait l absence d un plateau.
    4023 => array('mecanique' => 'grille', 'contenu' => 'codage d un deplacement sur une grille', 'note' => 'Le robot : l eleve ecrit une suite d ordres (avance, tourne) puis la lance.'),

    // -- Grandeurs et mesures --
    3962 => array('mecanique' => 'paires', 'contenu' => 'unite de longueur et symbole', 'note' => 'Regroupe avec 3963 : le metre, le centimetre, le kilometre.'),
    3963 => array('mecanique' => 'paires', 'contenu' => 'situation et unite adaptee', 'note' => 'La longueur d un crayon en cm, une distance entre villes en km.'),
    3964 => array('mecanique' => 'paires', 'contenu' => 'relations entre unites de longueur', 'note' => '1 metre egale 100 centimetres. A regrouper avec 3971, 3974, 3983 dans une meme famille.'),
    3965 => array('mecanique' => 'regle', 'contenu' => 'mesurer un segment avec une regle graduee', 'note' => 'La regle graduee, ecrite le 07/09/2026. L eleve fait glisser la regle pour poser son zero, puis lit la graduation du bout - le premier geste est celui qui se rate en classe.'),
    3966 => array('mecanique' => 'regle', 'contenu' => 'comparer deux longueurs', 'note' => 'Mode ecart : on demande DE COMBIEN le plus long depasse, pas lequel est le plus long. Designer le plus long serait un choix binaire, donc un QCM habille.'),
    3967 => array('mecanique' => 'regle', 'contenu' => 'estimer une longueur', 'note' => 'Mode estimer, tolerance de 1 cm. La raison d origine - estimation par plage de valeurs, pas une reponse unique - etait PERIMEE : droite et carte valident par tolerance depuis le 06/09/2026.'),
    3968 => array('mecanique' => 'regle', 'contenu' => 'estimer une longueur', 'note' => 'Meme banque que 3967, meme raison perimee.'),
    3969 => array('mecanique' => 'balance', 'contenu' => 'comparer des masses', 'note' => 'La balance a deux plateaux, ecrite le 07/09/2026. Equilibrer sur un plateau dessine est la meme competence que soupeser, sans l objet reel.'),
    3970 => array('mecanique' => 'paires', 'contenu' => 'situation et unite de masse', 'note' => 'Le poids d une pomme en grammes, d un enfant en kilogrammes.'),
    3971 => array('mecanique' => 'paires', 'contenu' => 'relation kilogramme et gramme', 'note' => '1 kg egale 1000 g. A regrouper avec 3964.'),
    3972 => array('mecanique' => 'balance', 'contenu' => 'utiliser une balance a deux plateaux', 'note' => 'Le fleau penche pendant qu on pose : ce n est pas un indice donne par le jeu, c est la competence elle-meme.'),
    3973 => array('mecanique' => 'balance', 'contenu' => 'estimer une masse', 'note' => 'Banque grandeurs-masses-estimer, tolerance de 30 %. Meme raison perimee que 3967 et 3968.'),
    3974 => array('mecanique' => 'paires', 'contenu' => 'relation euro et centime', 'note' => '1 euro egale 100 centimes. A regrouper avec 3964.'),
    // Ces trois-la ne se jouaient pas faute de pouvoir montrer des pieces. La
    // petite boutique (includes/boutique.php) les couvre depuis le 04/09/2026 :
    // la qualification est mise a jour le 06/09/2026, elle etait restee en
    // arriere et l ecran de couverture annoncait donc injouable ce qui se
    // jouait. La cle 'special' dit quel moteur les couvre.
    3975 => array('mecanique' => 'boutique', 'special' => 'boutique', 'contenu' => 'comparer deux ensembles de pieces', 'note' => 'Couverte par la petite boutique.'),
    3976 => array('mecanique' => 'boutique', 'special' => 'boutique', 'contenu' => 'valeur d un ensemble de pieces', 'note' => 'Couverte par la petite boutique.'),
    3977 => array('mecanique' => 'boutique', 'special' => 'boutique', 'contenu' => 'constituer une somme donnee', 'note' => 'Couverte par la petite boutique, mode payer.'),
    3978 => array('mecanique' => null, 'raison' => 'jeu de role', 'note' => 'Simuler un achat, rendre la monnaie.'),
    3979 => array('mecanique' => 'paires', 'contenu' => 'ecriture a virgule et decomposition', 'note' => '3,50 euros egale 3 euros et 50 centimes. Jouable en texte, sans image.'),
    // « Necessite un cadran d horloge », et « aucune mecanique graphique
    // d horloge actuelle » : c etait vrai jusqu au 06/09/2026. Le cadran
    // existe desormais (includes/horloge.php), dessine en SVG, aiguilles
    // posables a la souris, au doigt et au clavier. Meme histoire que la
    // monnaie plus haut : ce n etait pas la competence qui resistait, c etait
    // l absence d un cadran manipulable.
    3980 => array('mecanique' => 'horloge', 'special' => 'horloge', 'contenu' => 'lire l heure sur un cadran', 'note' => 'Couverte par l horloge.'),
    3981 => array('mecanique' => 'horloge', 'special' => 'horloge', 'contenu' => 'lire l heure sur un cadran', 'note' => 'Couverte par l horloge, memes paliers que 3980.'),
    3982 => array('mecanique' => 'tri', 'contenu' => 'moment de la journee', 'note' => 'Reclasse en tri le 01/09/2026, note en paires a tort le 31/08. Le libelle ne distingue que deux moments (le matin et l apres-midi, pas le soir) : deux categories, ce que le tri gere mieux qu une paire ou la moitie des propositions seraient forcement des doublons.'),
    3983 => array('mecanique' => 'paires', 'contenu' => 'unite de duree et symbole', 'note' => 'Heure et minute. A regrouper avec 3964.'),
    // Oubliee lors du lot horloge du 06/09/2026 : 3980 et 3981 avaient ete
    // re-qualifiees, pas celle-ci, alors que sa note disait deja « meme limite
    // que 3980 et 3981 ». L ecran de couverture annoncait donc injouable une
    // competence qui se joue - le meme defaut que la boutique avait laisse
    // trainer deux jours. Trouve en remesurant les trous restants.
    3984 => array('mecanique' => 'horloge', 'special' => 'horloge', 'contenu' => 'lire l heure sur un cadran', 'note' => 'Couverte par l horloge, comme 3980 et 3981.'),

    // -- Nombres, calcul et resolution de problemes --
    3900 => array('mecanique' => 'collection', 'contenu' => 'denombrer une collection', 'note' => 'Les paquets de dix, ecrits le 07/09/2026. Les jetons sont poses en desordre : ranges, il n y aurait plus rien a grouper et compter un a un suffirait.'),
    3901 => array('mecanique' => 'collection', 'contenu' => 'constituer une collection d un nombre donne', 'note' => 'Le meme moteur a l envers, pas un second moteur : une seconde banque suffit.'),
    3902 => array('mecanique' => null, 'raison' => 'intitule tronque', 'note' => 'Coupe apres "unites et" dans le referentiel.'),
    // Note corrigee le 06/09/2026 : elle disait encore « mecanique ranger non
    // encore ecrite » alors que TROIS banques qcm la couvrent depuis
    // longtemps (maths-nombre-mystere, maths-avant-apres-entre,
    // maths-quelle-heure). La competence etait jouable, sa note disait le
    // contraire - meme famille de defaut que l ecran de couverture qui ment.
    // A VERIFIER AVEC l’enseignante : maths-quelle-heure est rangee ici, sous
    // « suite numerique jusqu a mille », ce qui n a rien a voir avec la
    // lecture de l heure. Sa place serait 3980/3981/3984 (lire l heure) ou
    // 3982 (moment de la journee). Non deplacee : cela change l endroit ou
    // l’enseignante trouve le jeu et le rattachement des futurs resultats.
    3903 => array('mecanique' => 'qcm', 'contenu' => 'suite numerique jusqu a mille', 'note' => 'Couverte par trois banques qcm : nombre mystere, avant/apres/entre, et quelle heure (celle-ci mal rangee, voir ci-dessus). Enrichies a douze questions chacune le 06/09/2026.'),
    3904 => array('mecanique' => 'paires', 'contenu' => 'nombre et sa decomposition', 'note' => '45 egale quatre dizaines et cinq unites, ou quarante-cinq en lettres.'),
    3905 => array('mecanique' => 'paires', 'contenu' => 'chiffre et valeur selon sa position', 'note' => 'Dans 45, le chiffre 4 vaut quarante.'),
    3906 => array('mecanique' => 'tri', 'contenu' => 'paire de nombres et symbole de comparaison', 'note' => 'Categories = , < , > : par exemple 45 et 54 va dans la categorie <.'),
    3907 => array('mecanique' => 'ordre', 'contenu' => 'ordre croissant ou decroissant', 'note' => 'Ecrite le 06/09/2026 : DEUX banques, maths-ordre-croissant et maths-ordre-decroissant - la consigne d une banque vaut pour toutes ses manches, on ne peut donc pas melanger les deux sens.'),
    3908 => array('mecanique' => 'paires', 'contenu' => 'expression de comparaison et symbole', 'note' => 'Superieur a, inferieur a, compris entre.'),
    // « Necessite une droite graduee interactive », « aucune mecanique
    // graphique actuelle » : la troisieme fois que cette formulation bloque
    // une competence, apres la monnaie et l horloge - et la troisieme fois que
    // ce n est pas la competence qui resiste, mais l absence d un objet
    // manipulable. La droite existe depuis le 06/09/2026 (mecanique `droite`,
    // includes/mecaniques.php), en trois banques de difficulte croissante.
    3909 => array('mecanique' => 'droite', 'contenu' => 'placer un nombre sur une droite graduee', 'note' => 'Couverte par les banques maths-droite-jusqu-20, -100 et -1000.'),
    3910 => array('mecanique' => 'paires', 'contenu' => 'nombre et ordinal', 'note' => '3 et troisieme, 21 et vingt-et-unieme, jusqu a cent.'),
    3911 => array('mecanique' => 'paires', 'contenu' => 'usage des ordinaux', 'note' => 'Meme famille que 3910.'),
    3912 => array('mecanique' => 'qcm', 'contenu' => 'rang dans une liste ecrite', 'note' => 'Une liste de noms ou d objets ecrite en texte, sans image : qui est en deuxieme position.'),
    3913 => array('mecanique' => 'paires', 'contenu' => 'rang et nombre d elements precedents', 'note' => 'Le quatrieme a trois elements avant lui.'),
    3914 => array('mecanique' => 'paires', 'contenu' => 'ordinaux dans une suite', 'note' => 'Meme famille que 3910 et 3911.'),
    3915 => array('mecanique' => null, 'raison' => 'intitule corrompu', 'note' => 'Chiffres et virgules parasites dans le texte source, sens impossible a etablir avec certitude.'),
    // Ouvertes le 06/09/2026 par la mecanique `partage` (banques maths-fractions-bande
    // et maths-fractions-disque) : deux supports, parce qu'un eleve qui ne reconnait
    // 3/4 que sur une bande a reconnu un dessin, pas une fraction.
    3916 => array('mecanique' => 'partage', 'contenu' => 'representer une fraction simple', 'note' => 'L eleve coupe la bande ou le disque, puis colorie.'),
    3917 => array('mecanique' => 'partage', 'contenu' => 'numerateur et denominateur', 'note' => 'Les deux termes sont nommes dans l explication, apres le dessin - la ou ils servent.'),
    3918 => array('mecanique' => 'qcm', 'contenu' => 'comparaison de fractions de meme denominateur', 'note' => 'Purement numerique, pas besoin d image si le denominateur est commun.'),
    3919 => array('mecanique' => 'qcm', 'contenu' => 'comparaison de fractions de numerateur 1', 'note' => 'Meme famille que 3918.'),
    3920 => array('mecanique' => 'calcul', 'contenu' => 'addition et soustraction de fractions', 'note' => 'Le generateur addition actuel ne gere que les entiers ; il en faudrait un dedie aux fractions.'),
    3921 => array('mecanique' => 'calcul', 'contenu' => 'addition et soustraction en colonnes', 'note' => 'Territoire du generateur addition et soustraction existant.'),
    3922 => array('mecanique' => null, 'raison' => 'notion trop ponctuelle', 'note' => 'Un seul symbole ; a regrouper avec les faits multiplicatifs.'),
    3923 => array('mecanique' => 'qcm', 'contenu' => 'commutativite de la multiplication', 'note' => '3 fois 4 donne le meme resultat que 4 fois 3.'),
    3924 => array('mecanique' => 'tri', 'contenu' => 'nombre pair ou impair', 'note' => 'Le texte source est garble apres le premier point (position des chiffres dans les nombr...) ; seule la partie claire, la parite, est qualifiee.'),
    3925 => array('mecanique' => 'calcul', 'contenu' => 'tables d addition', 'note' => 'Territoire du generateur addition existant.'),
    3926 => array('mecanique' => 'calcul', 'contenu' => 'tables de multiplication', 'note' => 'Territoire du generateur multiplication existant.'),
    3927 => array('mecanique' => 'calcul', 'contenu' => 'faits multiplicatifs usuels', 'note' => 'Meme famille que 3926.'),
    3928 => array('mecanique' => 'calcul', 'contenu' => 'ajouter ou soustraire des dizaines ou centaines', 'note' => 'Territoire du generateur addition et soustraction.'),
    3929 => array('mecanique' => 'calcul', 'contenu' => 'multiplier par dix', 'note' => 'Cas particulier du generateur multiplication, a verifier au moment d ecrire la banque.'),
    3930 => array('mecanique' => 'calcul', 'contenu' => 'ajouter 9, 19 ou 29', 'note' => 'Technique de calcul mental, territoire du generateur addition.'),
    3931 => array('mecanique' => 'calcul', 'contenu' => 'soustraire 9', 'note' => 'Territoire du generateur soustraction.'),
    3932 => array('mecanique' => 'calcul', 'contenu' => 'soustraire un nombre inferieur a 9', 'note' => 'Territoire du generateur soustraction.'),
    3933 => array('mecanique' => 'qcm', 'contenu' => 'moitie d un nombre pair', 'note' => 'Banque Doubles et moitiés ; le générateur historique reste disponible.'),
    3934 => array('mecanique' => 'calcul', 'contenu' => 'produit par decomposition', 'note' => 'Technique de calcul mental specifique, territoire du generateur multiplication.'),
    3935 => array('mecanique' => 'probleme', 'contenu' => 'probleme a resoudre en construisant le calcul', 'note' => 'Ouverte le 06/09/2026 ; enonces a relire par l’enseignante.'),
    3936 => array('mecanique' => 'probleme', 'contenu' => 'probleme a resoudre en construisant le calcul', 'note' => 'Ouverte le 06/09/2026 ; enonces a relire par l’enseignante.'),
    3937 => array('mecanique' => 'probleme', 'contenu' => 'probleme a resoudre en construisant le calcul', 'note' => 'Ouverte le 06/09/2026 ; enonces a relire par l’enseignante.'),
    3938 => array('mecanique' => 'probleme', 'contenu' => 'probleme a resoudre en construisant le calcul', 'note' => 'Ouverte le 06/09/2026 ; enonces a relire par l’enseignante.'),
    3939 => array('mecanique' => 'probleme', 'contenu' => 'probleme a resoudre en construisant le calcul', 'note' => 'Ouverte le 06/09/2026 ; enonces a relire par l’enseignante.'),

    // -- Organisation et gestion de donnees --
    4034 => array('mecanique' => 'graphique', 'contenu' => 'organiser des donnees en diagramme', 'note' => 'Le graphique, ecrit le 07/09/2026. Classee production graphique, donc rangee avec les traces au crayon - mais UNE BARRE QU ON TIRE N EST PAS UN DESSIN : sa hauteur est la donnee. Meme raisonnement que la monnaie et l heure.'),
    4035 => array('mecanique' => 'graphique', 'contenu' => 'lire un diagramme en barres', 'note' => 'Sa raison portait la formulation qui a designe la boutique, l horloge, la droite et la carte : aucune mecanique de lecture de graphique actuelle. Cinquieme fois que cette phrase annonce le prochain jeu.'),

    // ================================================================
    // FRANCAIS - 61 competences
    // La matiere la plus contrastee : beaucoup de lecture, d ecriture
    // libre et d oral qui ne se pretent a aucun mini-jeu a choix de
    // reponses ; en face, la grammaire et le vocabulaire fournissent
    // certains des meilleurs candidats tri et paires de tout le
    // programme.
    // ================================================================

    // -- Apprendre a ecrire en ecriture cursive --
    3825 => array('mecanique' => null, 'raison' => 'geste d ecriture', 'note' => 'Trace de lettres, pas evaluable par un mini-jeu.'),
    3826 => array('mecanique' => null, 'raison' => 'necessite une police cursive', 'note' => 'Aucune police d ecriture cursive disponible sur le site actuellement.'),
    3827 => array('mecanique' => null, 'raison' => 'geste d ecriture', 'note' => 'Meme limite que 3825.'),

    // -- Comprendre un texte --
    3800 => array('mecanique' => null, 'raison' => 'necessite un texte de lecture specifique', 'note' => 'Toute la categorie depend d un texte fourni en amont, hors mini-jeu autonome.'),
    3801 => array('mecanique' => null, 'raison' => 'strategie de lecture', 'note' => 'Methode, pas un contenu factuel isolable.'),
    3802 => array('mecanique' => null, 'raison' => 'necessite un texte de lecture specifique', 'note' => 'Chaine anaphorique observee sur un texte donne.'),
    3803 => array('mecanique' => null, 'raison' => 'necessite un texte de lecture specifique', 'note' => 'Inference sur un texte donne.'),
    3804 => array('mecanique' => null, 'raison' => 'methode de travail', 'note' => 'Retour au texte, pas un contenu isolable.'),
    3805 => array('mecanique' => null, 'raison' => 'necessite un texte de lecture specifique', 'note' => 'Quinze lignes de texte narratif, informatif ou prescriptif.'),

    // -- Copier et acquerir des strategies de copie --
    3830 => array('mecanique' => null, 'raison' => 'geste d ecriture', 'note' => 'Copie chronometree.'),
    3831 => array('mecanique' => null, 'raison' => 'geste d ecriture', 'note' => 'Strategies de copie.'),
    3832 => array('mecanique' => null, 'raison' => 'geste d ecriture', 'note' => 'Copie de quatre a cinq phrases.'),
    3833 => array('mecanique' => null, 'raison' => 'geste d ecriture', 'note' => 'Copie de cinq a six lignes.'),
    3834 => array('mecanique' => null, 'raison' => 'geste d ecriture', 'note' => 'Copie d une dizaine de lignes.'),

    // -- Devenir lecteur --
    3806 => array('mecanique' => null, 'raison' => 'lecture personnelle d oeuvres', 'note' => 'Hors mini-jeu.'),
    3807 => array('mecanique' => null, 'raison' => 'notion transversale', 'note' => 'Genres et types de textes ; risque de generalisation abusive sur un exemple unique.'),
    3808 => array('mecanique' => null, 'raison' => 'attitude de lecteur', 'note' => 'Initiative personnelle, pas un contenu.'),
    3809 => array('mecanique' => null, 'raison' => 'reflexion personnelle', 'note' => 'Lien avec l experience de l eleve.'),

    // -- Dire pour etre compris --
    3846 => array('mecanique' => null, 'raison' => 'expression orale', 'note' => 'Pas evaluable a l ecrit dans un mini-jeu.'),
    3847 => array('mecanique' => null, 'raison' => 'auto-evaluation orale', 'note' => 'Critere d evaluation d une prestation.'),

    // -- Encoder puis ecrire sous dictee --
    3828 => array('mecanique' => 'qcm', 'contenu' => 'bonne orthographe d un mot', 'note' => 'Choisir la graphie correcte parmi plusieurs.'),
    3829 => array('mecanique' => 'tri', 'contenu' => 'accord dans le groupe nominal', 'note' => 'Genre et nombre du determinant, du nom et de l adjectif.'),

    // -- Enrichir son vocabulaire dans toutes les disciplines --
    3856 => array('mecanique' => 'tri', 'contenu' => 'mot de vocabulaire thematique et sa classe', 'note' => 'Requalifiee le 01/09/2026 en transposant les jeux School Monsters : il existe bien une liste fermee, la table vocabulaire de School Monsters (animaux, sport, transports, instruments, bruits...), deja utilisee par l’enseignante dans ses lotos. Le jugement initial etait faux par manque d information, pas par la nature de la competence. Se joue aussi en « paires » (francais-lexique-nature, mot associe a son theme ET a sa classe) : le champ mecanique nomme la PREMIERE mecanique trouvee, pas la seule autorisee - fgCatalogueCompetences() ne verifie jamais cette correspondance en production, seuls mes scripts de controle le font, et savent desormais l ignorer pour cette competence precise.'),
    3857 => array('mecanique' => null, 'raison' => 'notion abstraite', 'note' => 'Reseau de formulations, pas un contenu ponctuel.'),
    3858 => array('mecanique' => 'paires', 'contenu' => 'prefixe ou suffixe et son sens', 'note' => 'Attention aux prefixes polysemiques a eviter dans la banque.'),
    3859 => array('mecanique' => 'ordre', 'contenu' => 'cinq mots a classer par ordre alphabetique', 'note' => 'Requalifiee le 02/09/2026 en transposant alphabet1/2/3.php de School Monsters (dossier vocabulaire/loto) : jugee au depart comme une habitude de consultation, pas un contenu testable. Faux par manque d information, comme la 3856 avant elle. Le jeu original fait deja exactement ce que demande le libelle du programme : ranger cinq mots par ordre alphabetique, en trois niveaux qui deplacent la lettre a partir de laquelle il faut comparer (lettre de depart differente, puis meme lettre de depart, puis mots partageant un prefixe de trois lettres). Nouvelle mecanique « ordre » : le mot n est plus choisi mais range par glisser-depose - un geste reel, pas une decoration de QCM.'),

    // -- Grammaire --
    4324 => array('mecanique' => null, 'raison' => 'production d ecrit libre', 'note' => 'Semble etre un doublon mal categorise de la competence 3836.'),

    // -- Identifier les mots de maniere de plus en plus aisee --
    3793 => array('mecanique' => null, 'raison' => 'deja couverte par Clic & Mots', 'note' => 'Le jeu « le son qui manque » a ete ecrit puis RETIRE le 06/09/2026 : decision de le responsable technique, il faisait doublon avec le travail graphie-son de Clic & Mots. Ce n est donc plus une limite technique - le moteur existe - mais un choix de perimetre.'),
    3794 => array('mecanique' => null, 'raison' => 'deja couverte par Clic & Mots', 'note' => 'Le jeu « le son qui manque » a ete ecrit puis RETIRE le 06/09/2026 : decision de le responsable technique, il faisait doublon avec le travail graphie-son de Clic & Mots. Ce n est donc plus une limite technique - le moteur existe - mais un choix de perimetre.'),
    3795 => array('mecanique' => null, 'raison' => 'deja couverte par Clic & Mots', 'note' => 'Le jeu « le son qui manque » a ete ecrit puis RETIRE le 06/09/2026 : decision de le responsable technique, il faisait doublon avec le travail graphie-son de Clic & Mots. Ce n est donc plus une limite technique - le moteur existe - mais un choix de perimetre.'),
    3796 => array('mecanique' => null, 'raison' => 'mesure de fluence de lecture', 'note' => 'Vitesse et automaticite, pas adaptee a un mini-jeu.'),

    // -- Lire a voix haute --
    3797 => array('mecanique' => null, 'raison' => 'lecture orale chronometree', 'note' => 'Evaluee par l enseignante a l oral.'),
    3798 => array('mecanique' => null, 'raison' => 'lecture orale', 'note' => 'Respect de la ponctuation a voix haute.'),
    3799 => array('mecanique' => null, 'raison' => 'lecture orale', 'note' => 'Expressivite de la lecture.'),

    // -- Memoriser l orthographe des mots --
    3866 => array('mecanique' => 'qcm', 'contenu' => 'orthographe de mots frequents', 'note' => 'Reguliers et irreguliers.'),
    3867 => array('mecanique' => null, 'raison' => 'notion trop ponctuelle', 'note' => 'Les accents seuls ; a regrouper avec 3866.'),
    3868 => array('mecanique' => 'tri', 'contenu' => 'graphemes a prononciation variable', 'note' => 'S prononce ss ou z, c prononce ss ou k, g prononce j ou g.'),
    3869 => array('mecanique' => 'qcm', 'contenu' => 'lettre muette revelee par un mot de la meme famille', 'note' => 'La lettre qui ne s’entend pas : le mot derive dans l’explication revele la consonne finale.'),

    // -- Participer a des echanges --
    3848 => array('mecanique' => null, 'raison' => 'comportement social', 'note' => 'Respect du propos en groupe, a l oral.'),
    3849 => array('mecanique' => 'tri', 'contenu' => 'situation et registre de langue', 'note' => 'Familier, courant, soutenu. Proche de 3861, a regrouper.'),

    // -- Produire des ecrits --
    3835 => array('mecanique' => null, 'raison' => 'production d ecrit libre', 'note' => 'Pas de reponse unique verifiable.'),
    3836 => array('mecanique' => null, 'raison' => 'production d ecrit libre', 'note' => 'Texte court, une a trois phrases.'),
    3837 => array('mecanique' => null, 'raison' => 'production d ecrit libre', 'note' => 'Connecteurs dans un texte a ecrire.'),
    3838 => array('mecanique' => null, 'raison' => 'production d ecrit libre', 'note' => 'Retravailler un texte selon une contrainte.'),
    3839 => array('mecanique' => null, 'raison' => 'production d ecrit libre', 'note' => 'Methodologie de production ecrite.'),
    3840 => array('mecanique' => null, 'raison' => 'production d ecrit libre', 'note' => 'Texte de six a sept phrases. ETAIT CLASSEE EN MULTIPLICATIONS a tort le 31/08, via la categorie Produire des ecrits.'),

    // -- Reemployer le vocabulaire etudie --
    3864 => array('mecanique' => null, 'raison' => 'depend du vocabulaire etudie en classe', 'note' => 'Non generalisable a une banque fixe.'),
    3865 => array('mecanique' => null, 'raison' => 'notion transversale', 'note' => 'Relations entre mots sans liste fermee.'),

    // -- Se reperer dans la phrase simple --
    3883 => array('mecanique' => 'tri', 'contenu' => 'partie de phrase et role', 'note' => 'Groupe sujet, verbe, complements.'),
    3884 => array('mecanique' => 'tri', 'contenu' => 'phrase et type', 'note' => 'Declarative, interrogative, imperative, liees a la ponctuation.'),
    3885 => array('mecanique' => 'tri', 'contenu' => 'forme de phrase', 'note' => 'Negative, exclamative, affirmative.'),
    3886 => array('mecanique' => 'tri', 'contenu' => 'classe de mots', 'note' => 'Determinant, nom commun, nom propre, adjectif, verbe, pronom personnel sujet.'),
    3887 => array('mecanique' => 'tri', 'contenu' => 'groupe nominal et ses composants', 'note' => 'Chevauche 3829 et 3886, a regrouper le jour ou une banque est ecrite.'),
    3888 => array('mecanique' => 'qcm', 'contenu' => 'accord sujet-verbe', 'note' => 'Choisir la phrase ou le sujet et le verbe sont bien accordes.'),
    3889 => array('mecanique' => 'qcm', 'contenu' => 'verbe conjugue et infinitif', 'note' => 'Passee de paires a qcm le 21/09/2026 : associer « il chante » a « chanter » se faisait a vue. Banque francais-infinitif-qcm : radical qui change a l ecrit (appelle, achetes, commençons) et leurres qui se prononcent pareil (-é, -ez).'),
    3890 => array('mecanique' => 'calcul', 'contenu' => 'conjugaison a quatre temps', 'note' => 'Le generateur conjugaison actuel ne couvre que le present ; il manque l imparfait, le futur et le passe compose.'),
    // Identifiants releves en production le 15/09/2026 : 3890 n'y figure plus, la
    // conjugaison 2026/2027 y est 4388 (CE1) et 4391 (CE2).
    4388 => array('mecanique' => 'qcm', 'contenu' => 'conjugaison a quatre temps CE1', 'note' => 'Banque francais-passe-present-futur, choix de le responsable technique du 15/09/2026 : reperer passe, present ou futur, imparfait et passe compose compris.'),
    4391 => array('mecanique' => 'qcm', 'contenu' => 'conjugaison a quatre temps CE2', 'note' => 'Meme banque que 4388, listee en premier : un CE2 est credite ici, un CE1 en 4388.'),

    // -- Ecouter pour comprendre --
    3845 => array('mecanique' => null, 'raison' => 'necessite un support audio', 'note' => 'Ecoute active a l oral.'),

    // -- Etablir des relations entre les mots --
    3860 => array('mecanique' => 'tri', 'contenu' => 'niveau de generalite d un mot', 'note' => 'Generique, de base, specifique. Nuance pedagogique fine, a verifier avec l’enseignante au moment d ecrire la banque.'),
    3861 => array('mecanique' => 'tri', 'contenu' => 'mot et niveau de langue', 'note' => 'Familier, courant, soutenu. A regrouper avec 3849.'),
    3862 => array('mecanique' => 'tri', 'contenu' => 'sens propre ou sens figure', 'note' => 'ETAIT CLASSEE EN SOUSTRACTIONS a tort le 31/08, a cause du mot difference.'),
    3863 => array('mecanique' => 'qcm', 'contenu' => 'mots derives dans une phrase', 'note' => 'Le mot cache : le mot-repere et le sens de la phrase sont tous deux necessaires. Proche de 3858, a regrouper.'),

);
