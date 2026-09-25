<?php

return array(
    'flash' => array(
        'titre' => 'Défi Flash',
        'description' => 'Réponds à trois calculs rapides, sans pression sur le temps.',
        'competence' => 'Calcul mental',
        'duree' => '3 min',
        'ton' => 'turquoise',
        'questions' => array(
            array('question' => '7 × 4 = ?', 'consigne' => 'Choisis le bon résultat.', 'reponses' => array('21', '28', '32', '35'), 'bonne' => 1, 'explication' => '7 groupes de 4 font 28. On peut aussi faire 5 × 4 + 2 × 4.'),
            array('question' => '36 + 19 = ?', 'consigne' => 'Calcule en regroupant mentalement.', 'reponses' => array('45', '55', '56', '65'), 'bonne' => 1, 'explication' => '36 + 20 = 56, puis on retire 1 : 55.'),
            array('question' => 'La moitié de 70 est…', 'consigne' => 'Choisis la bonne réponse.', 'reponses' => array('30', '35', '40', '45'), 'bonne' => 1, 'explication' => 'Deux fois 35 font 70 : la moitié de 70 est donc 35.'),
        ),
    ),
    'intrus' => array(
        'titre' => "Trouve l'intrus",
        'description' => 'Observe, compare et justifie ce qui ne va pas avec les autres.',
        'competence' => 'Vocabulaire et catégorisation',
        'duree' => '4 min',
        'ton' => 'ambre',
        'questions' => array(
            array('question' => "Quel mot est l'intrus ?", 'consigne' => 'Trois mots sont des animaux.', 'reponses' => array('cheval', 'tulipe', 'lion', 'dauphin'), 'bonne' => 1, 'explication' => 'La tulipe est une plante. Les trois autres mots désignent des animaux.'),
            array('question' => "Quel nombre est l'intrus ?", 'consigne' => 'Trois nombres sont pairs.', 'reponses' => array('18', '24', '31', '42'), 'bonne' => 2, 'explication' => '31 est impair. Les autres nombres se terminent par 2, 4 ou 8.'),
            array('question' => "Quelle unité est l'intrus ?", 'consigne' => 'Trois unités mesurent une longueur.', 'reponses' => array('mètre', 'centimètre', 'litre', 'kilomètre'), 'bonne' => 2, 'explication' => 'Le litre mesure une contenance ; les autres unités mesurent une longueur.'),
        ),
    ),
    'correction' => array(
        'titre' => "Corrige l'erreur",
        'description' => 'Deviens le champion de la correction et explique ce qui cloche.',
        'competence' => 'Accord sujet-verbe',
        'duree' => '5 min',
        'ton' => 'corail',
        'questions' => array(
            array('question' => 'Les enfants joue dans la cour.', 'consigne' => 'Choisis la phrase correctement corrigée.', 'reponses' => array('Les enfants jouent dans la cour.', "L'enfant jouent dans la cour.", 'Les enfants joues dans la cour.'), 'bonne' => 0, 'explication' => 'Le sujet « les enfants » est au pluriel : le verbe prend la terminaison -ent.'),
            array('question' => 'Nous mange à la cantine.', 'consigne' => 'Choisis la bonne correction.', 'reponses' => array('Nous manges à la cantine.', 'Nous mangeons à la cantine.', 'Nous mangent à la cantine.'), 'bonne' => 1, 'explication' => 'Avec « nous », le verbe manger se termine par -eons : nous mangeons.'),
            array('question' => 'Le chien et le chat dort.', 'consigne' => 'Choisis la phrase correcte.', 'reponses' => array('Le chien et le chat dorment.', 'Le chien et le chat dors.', 'Le chien et le chat dorme.'), 'bonne' => 0, 'explication' => 'Il y a deux sujets : le verbe dormir s’accorde au pluriel, « dorment ».'),
        ),
    ),
);
