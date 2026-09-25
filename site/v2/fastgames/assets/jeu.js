(function () {
    'use strict';

    /* Icônes SVG inline pour les éléments dessinés côté navigateur, où le
       composant serveur galaxie-icones.php n'est pas disponible. Mêmes tracés
       que le socle (trait 1.8, arrondis, 24×24), couleur par currentColor. */
    function fgIconeSVG(nom, taille) {
        var chemins = {
            drapeau: '<path d="M6 21V4"/><path d="M6 4c3-1.5 6 2 9 .5V12c-3 1.5-6-2-9-.5z"/>',
            robot: '<rect x="5" y="8" width="14" height="10" rx="2"/><path d="M12 8V5M9 5h6"/><circle cx="9.5" cy="13" r="1"/><circle cx="14.5" cy="13" r="1"/><path d="M10 16h4"/>',
            ecoute: '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="2"/><rect x="17" y="14" width="4" height="6" rx="2"/>',
            etoile: '<path d="M12 3l2.5 5.5 6 .8-4.5 4.2 1.2 6L12 16.8 6.8 19.5 8 13.5 3.5 9.3l6-.8z"/>',
            juste: '<path d="m4 12 5 5L20 6"/>',
            faux: '<path d="M6 6l12 12M18 6 6 18"/>'
        };
        var chemin = chemins[nom] || '';
        var remplissage = nom === 'etoile'
            ? ' fill="currentColor" stroke="none"'
            : ' fill="none" stroke="currentColor"';
        return '<svg viewBox="0 0 24 24" width="' + taille + '" height="' + taille + '"'
            + remplissage
            + ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
            + ' aria-hidden="true" focusable="false">' + chemin + '</svg>';
    }

    var racine = document.getElementById('fg-jeu');
    var donnees = document.getElementById('fg-donnees-jeu');
    if (!racine || !donnees) {
        return;
    }

    var jeu = JSON.parse(donnees.textContent);
    var questions = jeu.questions || [];
    /* Un tri et une association ne se presentent pas comme un QCM : les
       propositions y sont des categories ou des termes a relier, pas des choix
       numerotes. La lettre A/B/C/D n'a aucun sens sur « Carnivore », et les
       libelles sont longs - « Le courant ne passe pas » ne tient pas dans une
       pastille. La mecanique ne dit rien des bonnes reponses : la connaitre
       cote navigateur ne devoile rien. */
    var mecanique = jeu.mecanique || 'qcm';
    var index = 0;
    var score = 0;
    var verrouille = false;

    var question = document.getElementById('fg-question');
    var consigne = document.getElementById('fg-consigne');
    var reponses = document.getElementById('fg-reponses');
    var retour = document.getElementById('fg-retour');
    var etape = document.getElementById('fg-etape');
    var points = document.getElementById('fg-points');
    var FG_POINTS_MAX = 10;

    /* Un contrôle recréé doit rendre le focus à sa place dans la même action. */
    function fgReconstruire(conteneur, html) {
        var actif = document.activeElement;
        var ancienGroupe = actif && conteneur.contains(actif) ? actif.closest('[data-focus-groupe]') : null;
        var groupe = ancienGroupe && ancienGroupe.dataset.focusGroupe;
        var controles = function (zone) {
            return zone ? Array.from(zone.querySelectorAll('button:not(:disabled), input:not(:disabled), [role="button"][tabindex="0"]')) : [];
        };
        var rang = ancienGroupe ? controles(ancienGroupe).indexOf(actif) : -1;
        var cle = actif && actif.dataset.focusCle;
        var apres = actif && actif.dataset.focusApres;
        conteneur.innerHTML = html;
        if (!ancienGroupe) { return; }
        var groupes = Array.from(conteneur.querySelectorAll('[data-focus-groupe]'));
        var trouver = function (nom) { return groupes.find(function (zone) { return zone.dataset.focusGroupe === nom; }); };
        var suivants = controles(trouver(apres));
        var memes = controles(trouver(groupe));
        var cible = (cle && suivants.find(function (element) { return element.dataset.focusCle === cle; }))
            || suivants[0]
            || (cle && memes.find(function (element) { return element.dataset.focusCle === cle; }))
            || memes[Math.min(rang, memes.length - 1)]
            || conteneur.querySelector('[data-focus-defaut]:not(:disabled)')
            || question;
        cible.focus({preventScroll: true});
    }

    function afficherQuestion() {
        var item = questions[index];
        verrouille = false;
        consigne.textContent = item.consigne;
        etape.textContent = 'Question ' + (index + 1) + ' sur ' + questions.length;
        points.hidden = questions.length > FG_POINTS_MAX;
        points.innerHTML = points.hidden ? '' : questions.map(function (_, position) {
            return '<span class="' + (position === index ? 'est-courant' : '') + '"></span>';
        }).join('');
        retour.hidden = true;
        retour.className = 'fg-retour';
        retour.innerHTML = '';
        reponses.innerHTML = '';
        reponses.className = 'fg-reponses fg-reponses-' + mecanique;
        /* L'element a traiter est mis en avant : dans un tri c'est le mot a
           ranger, dans une association le terme a relier. C'est lui que l'oeil
           doit trouver en premier, pas la consigne. */
        question.className = mecanique === 'qcm' ? '' : 'fg-item-' + mecanique;

        /* Le compte est bon n'a pas de liste de propositions a cliquer : c'est
           une reponse composee, pas choisie. Sa mise en place vit dans ses
           propres fonctions plutot que de forcer un QCM a lui ressembler. */
        if (mecanique === 'compte') {
            question.textContent = 'Trouve ' + item.cible;
            afficherCompte(item);
            return;
        }

        /* La boutique non plus : l'eleve pose des pieces sur un comptoir, il
           ne choisit pas dans une liste. Le titre porte l'article et son prix,
           c'est la scene qu'on doit lire en premier. */
        if (mecanique === 'boutique') {
            question.textContent = item.embleme + ' ' + item.article + ' — ' + item.prix_affiche;
            afficherBoutique(item);
            return;
        }

        /* La droite graduee non plus : l'eleve pose un repere sur une ligne
           continue. Le titre porte le nombre a placer. */
        if (mecanique === 'droite') {
            question.textContent = item.question;
            afficherDroite(item);
            return;
        }

        /* La carte non plus n'est pas un QCM : l'eleve pose un repere sur le
           planisphere. Le titre porte le nom du lieu a situer. */
        if (mecanique === 'carte') {
            question.textContent = item.question;
            afficherCarte(item);
            return;
        }

        /* Le son qui manque non plus : l'eleve compose une graphie a partir de
           quatorze propositions. Le titre porte la consigne d'ecoute, le mot
           troue vit dans la zone de reponse. */
        if (mecanique === 'graphie') {
            question.textContent = 'Écoute, puis écris ce qui manque';
            afficherGraphie(item);
            return;
        }

        /* Le probleme du jour non plus : l'eleve construit un calcul a partir
           de l'enonce. Le titre porte l'enonce lui-meme - c'est lui qu'il faut
           lire avant de toucher aux nombres. */
        if (mecanique === 'probleme') {
            question.textContent = item.enonce_probleme;
            afficherProbleme(item);
            return;
        }

        /* Le partage non plus : l'eleve coupe un support vierge puis en colorie
           une partie. Le titre porte la fraction demandee. */
        if (mecanique === 'partage') {
            question.textContent = item.enonce_partage;
            afficherPartage(item);
            return;
        }

        /* Le graphique non plus : l'eleve tire des barres jusqu'a la hauteur
           des donnees. Le titre porte le sujet de l'enquete. */
        if (mecanique === 'graphique') {
            question.textContent = item.enonce_graphique;
            afficherGraphique(item);
            return;
        }

        /* La regle graduee non plus : l'eleve la fait glisser puis lit une
           graduation. Le titre porte la consigne du mode. */
        if (mecanique === 'regle') {
            question.textContent = item.enonce_regle;
            afficherRegle(item);
            return;
        }

        /* La balance non plus : l'eleve construit une masse en posant des
           poids. Le titre porte l'objet a peser. */
        if (mecanique === 'balance') {
            question.textContent = item.enonce_balance;
            afficherBalance(item);
            return;
        }

        /* Les paquets de dix non plus : l'eleve groupe des jetons puis compose
           un nombre. Le titre porte la consigne. */
        if (mecanique === 'collection') {
            question.textContent = item.enonce_collection;
            afficherCollection(item);
            return;
        }

        /* La grille non plus n'est pas un QCM : l'eleve ECRIT une suite
           d'ordres, puis la lance. Le titre porte la consigne du parcours. */
        if (mecanique === 'grille') {
            question.textContent = item.enonce_grille;
            afficherGrille(item);
            return;
        }

        /* L'horloge non plus : l'eleve tourne deux aiguilles pour afficher
           l'heure demandee. Le titre porte l'enonce - « 7 h 30 », ou en
           toutes lettres au dernier palier - c'est lui qu'il faut lire avant
           de toucher au cadran. */
        if (mecanique === 'horloge') {
            question.textContent = item.enonce;
            afficherHorloge(item);
            return;
        }

        /* Le tri alphabetique non plus n'est pas un QCM : l'eleve range cinq
           mots dans une rangee, il ne choisit rien dans une liste. */
        if (mecanique === 'ordre') {
            question.textContent = item.question || 'Range ces éléments dans l’ordre';
            afficherOrdre(item);
            return;
        }

        /* Le loto des sons non plus n'est pas un QCM : l'eleve ecoute un
           son puis tape ce qu'il entend, il ne choisit rien dans une liste. */
        if (mecanique === 'ecoute') {
            question.textContent = 'Écoute, puis tape ce que tu entends';
            afficherEcoute(item);
            return;
        }

        /* Un tri illustre porte un pictogramme optionnel (item.image) : un
           tri classique n'en a pas, ce texte reste alors seul comme avant.
           Deux noeuds separes plutot qu'un innerHTML concatene - le mot vient
           d'une banque de contenu, pas d'une saisie utilisateur, mais
           textContent evite d'y penser a chaque nouvelle banque. */
        question.textContent = '';
        if (item.image) {
            var pictogramme = document.createElement('span');
            pictogramme.className = 'fg-question-image';
            pictogramme.setAttribute('aria-hidden', 'true');
            pictogramme.textContent = item.image;
            question.appendChild(pictogramme);
        }
        var mot = document.createElement('span');
        mot.className = 'fg-question-mot';
        mot.textContent = item.question;
        question.appendChild(mot);
        item.reponses.forEach(function (libelle, numero) {
            var bouton = document.createElement('button');
            bouton.type = 'button';
            bouton.className = 'fg-reponse';
            if (mecanique === 'qcm') {
                bouton.innerHTML = '<span></span><strong></strong>';
                bouton.querySelector('span').textContent = String.fromCharCode(65 + numero);
            } else {
                bouton.innerHTML = '<strong></strong>';
            }
            bouton.querySelector('strong').textContent = libelle;
            bouton.addEventListener('click', function () {
                choisir(numero, bouton);
            });
            reponses.appendChild(bouton);
        });
    }

    /* --------------------------------------------------- le compte est bon */
    /* L'etat d'une manche en cours de composition : quelles tuiles restent
       disponibles, ce qui a deja ete pose, et l'operateur choisi en attente
       d'un nombre pour le suivre. Reinitialise a chaque nouvelle manche par
       afficherCompte(). */
    var compte = null;

    /* Le signe affiche n'est pas toujours le caractere envoye au serveur : un
       simple trait ASCII '-' est ambigu a l'ecran (facile a confondre avec un
       tiret), on affiche donc le vrai signe moins typographique tout en
       envoyant '-' comme le reste du site. Les deux autres passent tels
       quels : ce sont deja les caracteres attendus par l'API. */
    function signeAffiche(operateur) {
        /* L'asterisque n'est pas un signe mathematique pour un CE1 : la banque
           « Problemes : par paquets » l'affichait tel quel alors que le compte
           est bon montre bien un signe de multiplication (FG-AUDIT-020). Seul
           l'AFFICHAGE change : le serveur attend toujours '*', c'est ce que
           fgProblemeValider() et api/probleme.php filtrent. */
        if (operateur === '-') { return '−'; }
        if (operateur === '*') { return '×'; }
        return operateur;
    }

    function afficherCompte(item) {
        compte = {
            tuiles: item.nombres.map(function (nombre) { return {nombre: nombre, utilisee: false}; }),
            expression: [],
            operateurEnAttente: null,
            // Une manche facile ne propose que + et - ; le repli existe pour
            // rester jouable meme si une donnee plus ancienne ne portait pas
            // encore ce champ.
            operateursDisponibles: item.operateurs || ['+', '-']
        };
        rendreCompte();
    }

    /* Une ligne par operation, chacune avec son propre total - voir
       fgCompteEstBonExplication() (includes/compte-est-bon.php). Le premier
       element n'a pas d'operateur : c'est juste le nombre de depart. */
    function lignesCompte(expression) {
        var lignes = [];
        var total = null;
        expression.forEach(function (pas) {
            if (total === null) { total = pas.nombre; return; }
            if (pas.operateur === '+') { total += pas.nombre; }
            else if (pas.operateur === '-') { total -= pas.nombre; }
            else if (pas.operateur === '×') { total *= pas.nombre; }
            else if (pas.operateur === '÷') { total = pas.nombre !== 0 ? total / pas.nombre : total; }
            lignes.push({operateur: pas.operateur, nombre: pas.nombre, total: total});
        });
        return lignes;
    }

    function calculerCompte(expression) {
        if (expression.length === 0) { return null; }
        var lignes = lignesCompte(expression);
        return lignes.length ? lignes[lignes.length - 1].total : expression[0].nombre;
    }

    /* Le calcul s'affiche LIGNE PAR LIGNE, une operation par ligne avec son
       propre total, plutot qu'a plat sur une seule ligne qui s'allonge.
       Demande de le responsable technique le 05/09/2026, apres avoir bute sur une manche ou il
       aurait voulu enchainer un × apres un −, alors interdit : « c'est pour
       ca qu'il faudrait faire les calculs sur plusieurs lignes ». Chaque
       ligne ne portant qu'une seule operation, plus aucune ambiguite de
       lecture n'est possible - contrairement a l'ecriture a plat trouvee en
       defaut par le responsable technique le 01/09/2026 sur « 29 − 3 ÷ 2 ». × et ÷ sont donc
       desormais proposes a chaque etape, plus seulement au debut de la
       manche (voir aussi compte-est-bon.php, ou la meme regle est retiree
       cote serveur). */
    function rendreCompte() {
        var html = '<div class="fg-compte-lignes" aria-live="polite">';
        if (compte.expression.length === 0) {
            html += '<span class="fg-compte-vide">Touche un nombre pour commencer.</span>';
        } else {
            html += '<p class="fg-compte-ligne fg-compte-ligne-depart"><span class="fg-compte-chip">' + compte.expression[0].nombre + '</span></p>';
            lignesCompte(compte.expression).forEach(function (ligne) {
                html += '<p class="fg-compte-ligne"><span class="fg-compte-signe">' + signeAffiche(ligne.operateur) + '</span>'
                      + '<span class="fg-compte-chip">' + ligne.nombre + '</span>'
                      + '<span class="fg-compte-egal">= ' + ligne.total + '</span></p>';
            });
        }
        html += '</div><div class="fg-compte-nombres" data-focus-groupe="tuiles">';
        compte.tuiles.forEach(function (tuile, i) {
            html += '<button type="button" class="fg-compte-tuile" data-index="' + i + '" data-focus-cle="' + i + '" data-focus-apres="operateurs"'
                  + (tuile.utilisee ? ' disabled' : '') + '>' + tuile.nombre + '</button>';
        });
        var resteUneTuile = compte.tuiles.some(function (t) { return !t.utilisee; });
        var peutOperer = compte.expression.length > 0 && resteUneTuile;
        html += '</div><div class="fg-compte-operateurs" data-focus-groupe="operateurs">';
        compte.operateursDisponibles.forEach(function (operateur) {
            html += '<button type="button" class="fg-compte-op' + (compte.operateurEnAttente === operateur ? ' est-choisi' : '') + '" data-op="' + operateur + '" data-focus-cle="' + operateur + '" data-focus-apres="tuiles"' + (peutOperer ? '' : ' disabled') + '>' + signeAffiche(operateur) + '</button>';
        });
        html += '</div><div class="fg-compte-actions">'
              + '<button type="button" class="fg-bouton fg-bouton-secondaire" id="fg-compte-effacer"' + (compte.expression.length > 0 ? '' : ' disabled') + '>↺ Recommencer</button>'
              + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-compte-valider" data-focus-defaut' + (compte.expression.length >= 2 ? '' : ' disabled') + '>Valider</button>'
              + '</div>';
        fgReconstruire(reponses, html);

        reponses.querySelectorAll('.fg-compte-tuile').forEach(function (bouton) {
            bouton.addEventListener('click', function () { poserTuileCompte(parseInt(bouton.dataset.index, 10)); });
        });
        reponses.querySelectorAll('.fg-compte-op').forEach(function (bouton) {
            bouton.addEventListener('click', function () { choisirOperateurCompte(bouton.dataset.op); });
        });
        var effacer = document.getElementById('fg-compte-effacer');
        if (effacer) { effacer.addEventListener('click', reinitialiserCompte); }
        var valider = document.getElementById('fg-compte-valider');
        if (valider) { valider.addEventListener('click', validerCompte); }
    }

    function poserTuileCompte(i) {
        if (verrouille) { return; }
        var tuile = compte.tuiles[i];
        if (!tuile || tuile.utilisee) { return; }
        // La toute premiere tuile n'a besoin d'aucun operateur ; toutes les
        // suivantes en exigent un, choisi juste avant.
        if (compte.expression.length > 0 && compte.operateurEnAttente === null) {
            return;
        }
        tuile.utilisee = true;
        compte.expression.push({ nombre: tuile.nombre, operateur: compte.expression.length === 0 ? null : compte.operateurEnAttente });
        compte.operateurEnAttente = null;
        rendreCompte();
    }

    function choisirOperateurCompte(operateur) {
        if (verrouille) { return; }
        if (compte.expression.length === 0) { return; }
        // Recliquer le meme operateur l'annule : un geste malheureux se
        // rattrape sans devoir tout recommencer.
        compte.operateurEnAttente = compte.operateurEnAttente === operateur ? null : operateur;
        rendreCompte();
    }

    function reinitialiserCompte() {
        if (verrouille) { return; }
        compte.tuiles.forEach(function (t) { t.utilisee = false; });
        compte.expression = [];
        compte.operateurEnAttente = null;
        rendreCompte();
    }

    function validerCompte() {
        if (verrouille || compte.expression.length < 2) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/compte.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                etapes: compte.expression
            })
        }).then(lireCorrection).then(function (correction) {
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* L'explication est un objet {lignes, autres} depuis le 05/09/2026 (voir
       fgCompteEstBonExplication()), plus une simple chaine : rendue avec le
       meme langage visuel que le calcul de l'eleve pendant la manche
       (.fg-compte-ligne), une operation par ligne. Affichee que la reponse
       soit juste ou fausse - regarder la bonne solution apres une erreur
       fait deja partie du parcours, ce n'est pas une case a part. */
    function afficherCorrectionCompte(correction) {
        if (correction.juste) {
            score += 1;
        }
        retour.hidden = false;
        retour.classList.remove('est-juste', 'est-faux');
        retour.classList.add(correction.juste ? 'est-juste' : 'est-faux');
        var explication = correction.explication;
        /* DEUX FORMES D'EXPLICATION, et il faut les deux. Le compte est bon
           envoie un objet {lignes, autres} depuis le 05/09/2026 ; la boutique,
           le tri alphabetique et le loto des sons envoient - depuis toujours -
           une simple chaine. Cette fonction sert les quatre. Sans ce test, un
           `explication.lignes.map` sur une chaine leve une TypeError et la
           correction ne s'affiche jamais : la manche reste bloquee, bouton
           « suivant » compris. Regression introduite le 05/09 en passant le
           seul compte est bon au format structure, et passee inapercue parce
           que c'est le jeu qui a ete reteste ce jour-la. Trouvee le
           06/09/2026 en branchant l'horloge, qui renvoie elle aussi une
           chaine. */
        var estStructuree = explication && typeof explication === 'object' && explication.lignes;
        var htmlExplication = estStructuree
            ? ('<p class="fg-compte-solution-titre">Une solution possible :</p>'
                + '<div class="fg-compte-lignes fg-compte-lignes-solution">'
                + explication.lignes.map(function (ligne, i) {
                    if (i === 0) {
                        return '<p class="fg-compte-ligne fg-compte-ligne-depart"><span class="fg-compte-chip">' + ligne.nombre + '</span></p>';
                    }
                    return '<p class="fg-compte-ligne"><span class="fg-compte-signe">' + signeAffiche(ligne.operateur) + '</span>'
                         + '<span class="fg-compte-chip">' + ligne.nombre + '</span>'
                         + '<span class="fg-compte-egal">= ' + ligne.total + '</span></p>';
                }).join('')
                + '</div>'
                + (explication.autres ? '<p class="fg-compte-autres">Il pouvait y en avoir d’autres.</p>' : ''))
            : '<p class="fg-compte-solution-texte"></p>';
        retour.innerHTML = '<div><strong></strong>' + htmlExplication + '</div><button type="button" class="fg-bouton fg-bouton-principal"></button>';
        retour.querySelector('strong').textContent = correction.juste ? 'Bravo, c’est juste !' : 'Pas tout à fait, regarde pourquoi.';
        if (!estStructuree) {
            retour.querySelector('.fg-compte-solution-texte').textContent = explication || '';
        }
        var suite = retour.querySelector('button');
        suite.textContent = index + 1 < questions.length ? 'Question suivante →' : 'Voir mon résultat →';
        suite.addEventListener('click', function () {
            if (index + 1 < questions.length) {
                index += 1;
                afficherQuestion();
                question.focus();
            } else {
                terminer();
            }
        });
        suite.focus();
    }

    /* ------------------------------------------------- la petite boutique */
    /* L'etat d'une manche : le porte-monnaie avec, pour chaque piece, si elle
       est deja sur le comptoir. On garde des INDEX et non des valeurs, parce
       qu'un porte-monnaie contient volontairement plusieurs pieces
       identiques - deux pieces de 2 € sont deux objets distincts. */
    var boutique = null;

    function afficherBoutique(item) {
        boutique = {
            pieces: item.porte_monnaie.map(function (valeur) { return {valeur: valeur, posee: false}; }),
            cible: item.cible,
            cibleAffichee: item.cible_affichee,
            limite: item.limite,
            mode: item.mode,
            donneAffiche: item.donne_affiche
        };
        rendreBoutique();
    }

    /* Les centimes s'ecrivent comme sur une etiquette de prix. Le serveur
       envoie des entiers en centimes - jamais de decimaux, qui finiraient par
       afficher un 4,299999. */
    function eurosAffiches(centimes) {
        if (centimes % 100 === 0) { return (centimes / 100) + ' €'; }
        var euros = Math.floor(centimes / 100);
        var reste = centimes % 100;
        return euros + ',' + (reste < 10 ? '0' + reste : reste) + ' €';
    }

    function totalBoutique() {
        return boutique.pieces.reduce(function (somme, piece) {
            return somme + (piece.posee ? piece.valeur : 0);
        }, 0);
    }

    function piecesPoseesBoutique() {
        return boutique.pieces.filter(function (p) { return p.posee; });
    }

    function rendreBoutique() {
        var total = totalBoutique();
        var posees = piecesPoseesBoutique();
        var html = '';

        if (boutique.mode === 'rendre' && boutique.donneAffiche) {
            html += '<p class="fg-boutique-scene">Le client donne <strong>' + boutique.donneAffiche + '</strong>.</p>';
        }
        html += '<p class="fg-boutique-cible">À composer : <strong>' + boutique.cibleAffichee + '</strong>'
             + (boutique.limite !== null ? ' <span class="fg-boutique-limite">' + boutique.limite + ' pièce' + (boutique.limite > 1 ? 's' : '') + ' maximum</span>' : '')
             + '</p>';

        // LE COMPTOIR : la zone ou l'on depose. C'est elle qui recoit le
        // glisser-deposer ; un clic sur une piece du porte-monnaie fait la
        // meme chose, pour ne jamais dependre d'un geste qui peut rater.
        html += '<div class="fg-boutique-comptoir" id="fg-boutique-comptoir" data-focus-groupe="comptoir">';
        if (!posees.length) {
            html += '<span class="fg-boutique-vide">Glisse ou touche une pièce pour la poser ici.</span>';
        } else {
            boutique.pieces.forEach(function (piece, i) {
                if (!piece.posee) { return; }
                html += '<button type="button" class="fg-piece fg-piece-posee" data-index="' + i + '" data-focus-cle="' + i + '" data-focus-apres="porte-monnaie" title="Retirer cette pièce">'
                      + eurosAffiches(piece.valeur) + '</button>';
            });
        }
        html += '</div>';
        html += '<p class="fg-boutique-total' + (total === boutique.cible ? ' est-atteint' : '') + '">'
             + 'Sur le comptoir : <strong>' + eurosAffiches(total) + '</strong>'
             + (posees.length ? ' <small>(' + posees.length + ' pièce' + (posees.length > 1 ? 's' : '') + ')</small>' : '')
             + '</p>';

        html += '<div class="fg-boutique-porte-monnaie" data-focus-groupe="porte-monnaie">';
        boutique.pieces.forEach(function (piece, i) {
            if (piece.posee) { return; }
            html += '<button type="button" class="fg-piece" draggable="true" data-index="' + i + '" data-focus-cle="' + i + '">'
                  + eurosAffiches(piece.valeur) + '</button>';
        });
        html += '</div>';

        html += '<div class="fg-compte-actions">'
              + '<button type="button" class="fg-bouton fg-bouton-secondaire" id="fg-boutique-vider"' + (posees.length ? '' : ' disabled') + '>↺ Tout reprendre</button>'
              + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-boutique-valider" data-focus-defaut' + (posees.length ? '' : ' disabled') + '>Valider</button>'
              + '</div>';
        fgReconstruire(reponses, html);

        reponses.querySelectorAll('.fg-piece').forEach(function (bouton) {
            var i = parseInt(bouton.dataset.index, 10);
            bouton.addEventListener('click', function () { basculerPieceBoutique(i); });
            bouton.addEventListener('dragstart', function (e) {
                e.dataTransfer.setData('text/plain', String(i));
                e.dataTransfer.effectAllowed = 'move';
                bouton.classList.add('est-portee');
            });
            bouton.addEventListener('dragend', function () { bouton.classList.remove('est-portee'); });
        });

        var comptoir = document.getElementById('fg-boutique-comptoir');
        if (comptoir) {
            comptoir.addEventListener('dragover', function (e) {
                e.preventDefault();
                comptoir.classList.add('est-survole');
            });
            comptoir.addEventListener('dragleave', function () { comptoir.classList.remove('est-survole'); });
            comptoir.addEventListener('drop', function (e) {
                e.preventDefault();
                comptoir.classList.remove('est-survole');
                var i = parseInt(e.dataTransfer.getData('text/plain'), 10);
                if (!isNaN(i) && boutique.pieces[i] && !boutique.pieces[i].posee) {
                    basculerPieceBoutique(i);
                }
            });
        }

        var vider = document.getElementById('fg-boutique-vider');
        if (vider) { vider.addEventListener('click', viderComptoirBoutique); }
        var valider = document.getElementById('fg-boutique-valider');
        if (valider) { valider.addEventListener('click', validerBoutique); }
    }

    /* Poser une piece, ou la reprendre si elle est deja sur le comptoir : le
       meme geste dans les deux sens, pour qu'une erreur se repare sans tout
       recommencer. */
    function basculerPieceBoutique(i) {
        if (verrouille) { return; }
        var piece = boutique.pieces[i];
        if (!piece) { return; }
        piece.posee = !piece.posee;
        rendreBoutique();
    }

    function viderComptoirBoutique() {
        if (verrouille) { return; }
        boutique.pieces.forEach(function (p) { p.posee = false; });
        rendreBoutique();
    }

    function validerBoutique() {
        var posees = piecesPoseesBoutique();
        if (verrouille || !posees.length) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/boutique.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                pieces: posees.map(function (p) { return p.valeur; })
            })
        }).then(lireCorrection).then(function (correction) {
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* ------------------------------------------------------ la droite graduee */
    /* Meme famille que la carte, a une dimension : l'eleve pose un repere sur
       un espace CONTINU, il n'y a rien a eliminer. Les positions sont en
       pourcentages de la droite, donc justes a toutes les tailles d'ecran.

       LES GRADUATIONS INTERMEDIAIRES NE PORTENT PAS D'ETIQUETTE, et c'est le
       coeur de l'exercice : si chaque trait etait numerote, placer 35 ne
       demanderait que de lire. Ici il faut comprendre qu'il est entre 30 et
       40, un peu apres le milieu. */
    var droite = null;

    function afficherDroite(item) {
        droite = { valeur: null, min: item.min, max: item.max, pas: item.pas, cible: null };
        rendreDroite();
    }

    function pourcentDroite(valeur) {
        return ((valeur - droite.min) / (droite.max - droite.min) * 100).toFixed(2);
    }

    function rendreDroite() {
        var graduations = '';
        // Une graduation etiquetee tous les `pas`, et quatre traits nus entre
        // deux etiquettes : de quoi situer sans donner la reponse.
        var etape = droite.pas / 5;
        for (var v = droite.min; v <= droite.max + 0.0001; v += etape) {
            var majeure = Math.abs(v / droite.pas - Math.round(v / droite.pas)) < 0.0001;
            graduations += '<span class="fg-droite-trait' + (majeure ? ' est-majeure' : '')
                + '" style="left:' + pourcentDroite(v) + '%"></span>';
            if (majeure) {
                graduations += '<span class="fg-droite-etiquette" style="left:' + pourcentDroite(v)
                    + '%">' + Math.round(v) + '</span>';
            }
        }
        var repere = droite.valeur === null ? ''
            : '<span class="fg-droite-repere" style="left:' + pourcentDroite(droite.valeur) + '%"></span>';
        var vraie = droite.cible === null ? ''
            : '<span class="fg-droite-vraie" style="left:' + pourcentDroite(droite.cible) + '%"></span>';

        reponses.innerHTML = '<div class="fg-droite-jeu">'
            + '<div class="fg-droite-piste" id="fg-droite-piste" tabindex="0" role="application"'
            + ' aria-label="Droite graduée de ' + droite.min + ' à ' + droite.max
            + '. Clique pour poser ton repère, ou déplace-le avec les flèches du clavier.">'
            + '<span class="fg-droite-axe"></span>' + graduations + repere + vraie
            + '</div>'
            /* REGLAGE FIN, meme motif que la regle graduee depuis le
               07/09/2026. Sur un telephone de 390 px, la zone acceptee ne fait
               que 7 px de large pour les droites jusqu'a 100 et jusqu'a 1000 :
               viser juste du premier coup au doigt est illusoire
               (FG-AUDIT-010). Le pas est celui du clavier, un cinquieme de
               graduation, donc exactement la tolerance de ces deux banques.

               AUCUN CHIFFRE N'EST AFFICHE, contrairement a la regle : ici
               l'eleve place un nombre DONNE. Lui montrer ou il en est
               reviendrait a lui souffler la reponse, il suffirait d'ajuster
               jusqu'a lire le nombre demande. */
            + '<div class="fg-droite-reglage">'
            + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-cran="-1"'
            + ' aria-label="Deplacer le repere vers la gauche"'
            + (droite.valeur === null ? ' disabled' : '') + '>&#8592;</button>'
            + '<span>Ajuster</span>'
            + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-cran="1"'
            + ' aria-label="Deplacer le repere vers la droite"'
            + (droite.valeur === null ? ' disabled' : '') + '>&#8594;</button>'
            + '</div>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-droite-valider"'
            + (droite.valeur === null ? ' disabled' : '') + '>Valider</button>'
            + '</div></div>';

        var piste = document.getElementById('fg-droite-piste');
        piste.addEventListener('pointerdown', function (e) { poserSurDroite(e, piste); });
        piste.addEventListener('pointermove', function (e) {
            if (e.buttons === 1 || e.pressure > 0) { poserSurDroite(e, piste); }
        });
        piste.addEventListener('keydown', function (e) {
            var sens = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
            if (!sens) { return; }
            e.preventDefault();
            if (verrouille) { return; }
            // Le pas du clavier est le cinquieme d'une graduation, comme les
            // traits intermediaires : on avance de trait en trait.
            var pas = droite.pas / 5;
            var depart = droite.valeur === null ? droite.min : droite.valeur + sens * pas;
            droite.valeur = Math.max(droite.min, Math.min(droite.max, depart));
            rendreDroite();
            document.getElementById('fg-droite-piste').focus();
        });
        reponses.querySelectorAll('[data-cran]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille || droite.valeur === null) { return; }
                var pas = droite.pas / 5;
                droite.valeur = Math.max(droite.min,
                    Math.min(droite.max, droite.valeur + parseInt(bouton.dataset.cran, 10) * pas));
                rendreDroite();
            });
        });
        var valider = document.getElementById('fg-droite-valider');
        if (valider) { valider.addEventListener('click', validerDroite); }
    }

    function poserSurDroite(e, piste) {
        if (verrouille) { return; }
        var rect = piste.getBoundingClientRect();
        if (!rect.width) { return; }
        var f = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        droite.valeur = droite.min + f * (droite.max - droite.min);
        rendreDroite();
    }

    function validerDroite() {
        if (verrouille || droite.valeur === null) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/droite.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                valeur: droite.valeur
            })
        }).then(lireCorrection).then(function (correction) {
            // Voir ou le nombre allait fait partie de la reponse, surtout
            // quand on s'est trompe.
            droite.cible = correction.cible;
            rendreDroite();
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* --------------------------------------------------------------- la carte */
    /* L'eleve pose un repere N'IMPORTE OU sur le planisphere : l'espace des
       reponses est continu, il n'y a rien a eliminer, il faut situer. C'est ce
       qui distingue cette mecanique d'un QCM habille en carte.

       TOUT EST EN POURCENTAGES, jamais en pixels : le fond de carte est en
       projection equirectangulaire, donc
           x% = (longitude + 180) / 360      y% = (90 - latitude) / 180
       Ces deux formules valent quelle que soit la taille d'affichage, ce qui
       rend le jeu juste sur telephone comme sur grand ecran sans un seul
       calcul de redimensionnement. */
    var carte = null;

    function afficherCarte(item) {
        carte = { lat: null, lon: null, corrige: null };
        rendreCarte();
    }

    function pourcentX(lon) { return ((lon + 180) / 360 * 100).toFixed(2); }
    function pourcentY(lat) { return ((90 - lat) / 180 * 100).toFixed(2); }

    function rendreCarte() {
        var repere = carte.lat === null ? ''
            : '<span class="fg-carte-repere" style="left:' + pourcentX(carte.lon)
              + '%;top:' + pourcentY(carte.lat) + '%"></span>';
        // Le vrai lieu n'apparait qu'apres la reponse : le serveur ne l'envoie
        // pas avant (voir fgQuestionsPourLeClient).
        var vrai = !carte.corrige ? ''
            : '<span class="fg-carte-vrai" style="left:' + pourcentX(carte.corrige.lon)
              + '%;top:' + pourcentY(carte.corrige.lat) + '%"></span>';
        reponses.innerHTML = '<div class="fg-carte-jeu">'
            + '<div class="fg-carte-cadre" id="fg-carte-cadre" tabindex="0" role="application"'
            + ' aria-label="Planisphère. Clique pour poser ton repère, ou déplace-le avec les flèches du clavier.">'
            + '<img class="fg-carte-fond" src="/v2/fastgames/assets/planisphere.svg" alt="" draggable="false">'
            + repere + vrai
            + '</div>'
            /* MEME REGLAGE FIN QUE LA DROITE. Sur un telephone de 390 px, le
               planisphere ne fait que 290 px de large pour 360 degres : la plus
               petite zone acceptee de cette banque tombe a 26 px de diametre,
               sous la cible tactile de 44 px (FG-AUDIT-011). Le pas est celui
               des fleches du clavier, cinq degres.

               LA TOLERANCE N'EST TOUJOURS PAS ENVOYEE AU CLIENT : dessiner le
               cercle accepte reviendrait a dire de combien on peut se tromper,
               ce que fgQuestionsPourLeClient() garde volontairement au
               serveur. On aide le geste, pas la reponse. */
            + '<div class="fg-carte-reglage">'
            + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-pas="ouest"'
            + ' aria-label="Deplacer le repere vers l&#39;ouest"'
            + (carte.lat === null ? ' disabled' : '') + '>&#8592;</button>'
            + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-pas="nord"'
            + ' aria-label="Deplacer le repere vers le nord"'
            + (carte.lat === null ? ' disabled' : '') + '>&#8593;</button>'
            + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-pas="sud"'
            + ' aria-label="Deplacer le repere vers le sud"'
            + (carte.lat === null ? ' disabled' : '') + '>&#8595;</button>'
            + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-pas="est"'
            + ' aria-label="Deplacer le repere vers l&#39;est"'
            + (carte.lat === null ? ' disabled' : '') + '>&#8594;</button>'
            + '</div>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-carte-valider"'
            + (carte.lat === null ? ' disabled' : '') + '>Valider</button>'
            + '</div></div>';

        var cadre = document.getElementById('fg-carte-cadre');
        cadre.addEventListener('pointerdown', function (e) { poserRepere(e, cadre); });
        cadre.addEventListener('pointermove', function (e) {
            if (e.buttons === 1 || e.pressure > 0) { poserRepere(e, cadre); }
        });
        cadre.addEventListener('keydown', function (e) {
            var dLon = e.key === 'ArrowRight' ? 5 : (e.key === 'ArrowLeft' ? -5 : 0);
            var dLat = e.key === 'ArrowUp' ? 5 : (e.key === 'ArrowDown' ? -5 : 0);
            if (!dLon && !dLat) { return; }
            e.preventDefault();
            if (verrouille) { return; }
            // Premiere touche : le repere apparait au centre de la carte,
            // puis se deplace. Sans ca, les fleches n'auraient rien a bouger.
            if (carte.lat === null) { carte.lat = 0; carte.lon = 0; }
            else { carte.lat = Math.max(-90, Math.min(90, carte.lat + dLat)); carte.lon += dLon; }
            if (carte.lon > 180) { carte.lon -= 360; }
            if (carte.lon < -180) { carte.lon += 360; }
            rendreCarte();
            document.getElementById('fg-carte-cadre').focus();
        });
        reponses.querySelectorAll('[data-pas]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille || carte.lat === null) { return; }
                var sens = bouton.dataset.pas;
                if (sens === 'nord') { carte.lat = Math.min(90, carte.lat + 5); }
                else if (sens === 'sud') { carte.lat = Math.max(-90, carte.lat - 5); }
                else { carte.lon += (sens === 'est' ? 5 : -5); }
                if (carte.lon > 180) { carte.lon -= 360; }
                if (carte.lon < -180) { carte.lon += 360; }
                rendreCarte();
            });
        });
        var valider = document.getElementById('fg-carte-valider');
        if (valider) { valider.addEventListener('click', validerCarte); }
    }

    function poserRepere(e, cadre) {
        if (verrouille) { return; }
        var rect = cadre.getBoundingClientRect();
        if (!rect.width || !rect.height) { return; }
        var fx = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        var fy = Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height));
        carte.lon = fx * 360 - 180;
        carte.lat = 90 - fy * 180;
        rendreCarte();
    }

    function validerCarte() {
        if (verrouille || carte.lat === null) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/carte.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                lat: carte.lat,
                lon: carte.lon
            })
        }).then(lireCorrection).then(function (correction) {
            // On redessine AVANT la correction : voir ou etait vraiment le lieu
            // fait partie de la reponse, surtout quand on s'est trompe.
            carte.corrige = { lat: correction.lat, lon: correction.lon };
            rendreCarte();
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* ------------------------------------------------- le robot sur la grille */
    /* L'etat d'une manche : le plateau tel qu'il est arrive, le programme en
       cours d'ecriture, et la position du robot pendant l'execution.

       CE QUI SE CONSTRUIT ICI EST UNE PHRASE D'ORDRES, pas un clic. L'eleve
       ajoute « avance », « tourne », relit sa suite, l'efface s'il veut, puis
       la lance. Rien n'est propose a eliminer : la grille est dessinee en CSS,
       aucun fichier a heberger.

       LE ROBOT NE BOUGE QU'A L'EXECUTION, jamais pendant l'ecriture. C'est ce
       qui fait la difference avec un simple pilotage a la fleche : il faut
       ANTICIPER le trajet, pas le corriger case apres case. */
    var grille = null;

    /* Les quatre orientations, dans l'ordre horaire - le meme que le serveur
       (FG_GRILLE_CAPS). Tourner a droite, c'est +1. */
    var GRILLE_CAPS = ['nord', 'est', 'sud', 'ouest'];
    var GRILLE_VECTEURS = [[0, -1], [1, 0], [0, 1], [-1, 0]];
    var GRILLE_LIBELLES = { avance: '↑ Avance', gauche: '↰ Gauche', droite: '↱ Droite' };

    function afficherGrille(item) {
        grille = {
            taille: item.taille,
            depart: item.depart,
            cap: GRILLE_CAPS.indexOf(item.cap) < 0 ? 0 : GRILLE_CAPS.indexOf(item.cap),
            cible: item.cible,
            murs: item.murs || [],
            programme: [],
            robot: null,      /* null tant que le programme n'a pas ete lance */
            surligne: -1      /* l'ordre en cours pendant l'animation */
        };
        rendreGrille();
    }

    function grilleEstMur(x, y) {
        return grille.murs.some(function (m) { return m[0] === x && m[1] === y; });
    }

    function rendreGrille() {
        var robot = grille.robot || { x: grille.depart[0], y: grille.depart[1], cap: grille.cap };
        var cases = '';
        for (var y = 0; y < grille.taille; y++) {
            for (var x = 0; x < grille.taille; x++) {
                var classes = 'fg-grille-case';
                var contenu = '';
                if (x === grille.cible[0] && y === grille.cible[1]) {
                    classes += ' est-cible';
                    contenu = '<span aria-hidden="true">' + fgIconeSVG('drapeau', 24) + '</span>';
                }
                if (grilleEstMur(x, y)) { classes += ' est-mur'; }
                if (robot.x === x && robot.y === y) {
                    contenu += '<span class="fg-grille-robot" aria-hidden="true" style="transform:rotate('
                        + (robot.cap * 90) + 'deg)">' + fgIconeSVG('robot', 24) + '</span>';
                }
                cases += '<span class="' + classes + '">' + contenu + '</span>';
            }
        }

        var puces = grille.programme.map(function (ordre, i) {
            return '<span class="fg-grille-ordre' + (i === grille.surligne ? ' est-encours' : '') + '">'
                + GRILLE_LIBELLES[ordre] + '</span>';
        }).join('');

        fgReconstruire(reponses, '<div class="fg-grille-jeu">'
            + '<div class="fg-grille-plateau" style="--fg-grille-taille:' + grille.taille + '"'
            + ' role="img" aria-label="Grille de ' + grille.taille + ' cases sur ' + grille.taille + '">'
            + cases + '</div>'
            + '<div class="fg-grille-programme" aria-live="polite"'
            + ' aria-label="Programme du robot, ' + grille.programme.length + ' ordre'
            + (grille.programme.length > 1 ? 's' : '') + '">' + puces + '</div>'
            + '<div class="fg-grille-commandes" data-focus-groupe="commandes">'
            + '<button type="button" class="fg-bouton" data-ordre="avance" data-focus-cle="avance">↑ Avance</button>'
            + '<button type="button" class="fg-bouton" data-ordre="gauche" data-focus-cle="gauche">↰ Tourne à gauche</button>'
            + '<button type="button" class="fg-bouton" data-ordre="droite" data-focus-cle="droite">↱ Tourne à droite</button>'
            + '<button type="button" class="fg-bouton" id="fg-grille-effacer"'
            + (grille.programme.length ? '' : ' disabled') + '>Effacer</button>'
            + '</div>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-grille-lancer"'
            + (grille.programme.length ? '' : ' disabled') + ' data-focus-defaut>Lancer le robot</button>'
            + '</div></div>');

        reponses.querySelectorAll('[data-ordre]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille) { return; }
                grille.programme.push(bouton.dataset.ordre);
                rendreGrille();
            });
        });
        var effacer = document.getElementById('fg-grille-effacer');
        if (effacer) {
            effacer.addEventListener('click', function () {
                if (verrouille) { return; }
                grille.programme = [];
                grille.robot = null;
                rendreGrille();
            });
        }
        var lancer = document.getElementById('fg-grille-lancer');
        if (lancer) { lancer.addEventListener('click', validerGrille); }
        if (verrouille) {
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }
    }

    /* Rejoue le programme pas a pas, a l'ecran seulement : le verdict est deja
       tombe cote serveur. Voir le robot avancer est ce qui montre OU le
       programme derape - un simple « faux » ne l'apprendrait pas. */
    function animerGrille(programme, fini) {
        var etat = { x: grille.depart[0], y: grille.depart[1], cap: grille.cap };
        var i = 0;
        var minuteur = setInterval(function () {
            if (i >= programme.length) {
                clearInterval(minuteur);
                grille.surligne = -1;
                rendreGrille();
                fini();
                return;
            }
            var ordre = programme[i];
            if (ordre === 'gauche') { etat.cap = (etat.cap + 3) % 4; }
            else if (ordre === 'droite') { etat.cap = (etat.cap + 1) % 4; }
            else {
                var nx = etat.x + GRILLE_VECTEURS[etat.cap][0];
                var ny = etat.y + GRILLE_VECTEURS[etat.cap][1];
                var dehors = nx < 0 || ny < 0 || nx >= grille.taille || ny >= grille.taille;
                if (!dehors && !grilleEstMur(nx, ny)) { etat.x = nx; etat.y = ny; }
            }
            grille.robot = { x: etat.x, y: etat.y, cap: etat.cap };
            grille.surligne = i;
            rendreGrille();
            i++;
        }, 300);
    }

    function validerGrille() {
        if (verrouille || !grille.programme.length) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/grille.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                programme: grille.programme
            })
        }).then(lireCorrection).then(function (correction) {
            /* On anime le programme RETENU PAR LE SERVEUR, pas celui qu'on
               croit avoir envoye : c'est lui qui a ete corrige. */
            animerGrille(correction.programme, function () {
                afficherCorrectionCompte(correction);
            });
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* --------------------------------------------------- le son qui manque */
    /* L'eleve entend un mot et compose la graphie qui lui manque, a partir de
       quatorze propositions : il n'elimine pas entre trois choix.

       LE SON VIENT D'ABORD D'UN FICHIER, sinon du navigateur. C'est l'ordre
       retenu par Clic & Mots (voir son src/lib/speech.ts) : un mp3 pre-genere
       dit mieux les schwas qu'une voix de synthese. Aucun fichier n'existe
       encore ici ; en deposer un suffira, sans toucher a ce code. */
    var graphie = null;
    var voixFrancaise = null;
    var voixDemandee = false;

    /* PIEGE DOCUMENTE PAR CLIC & MOTS : getVoices() rend souvent un tableau
       vide au premier appel, le temps que le navigateur charge la liste. Prendre
       la voix par defaut a ce moment-la donne parfois une voix anglaise. */
    function chargerVoix() {
        if (voixDemandee || !('speechSynthesis' in window)) { return; }
        voixDemandee = true;
        var choisir = function () {
            var voix = window.speechSynthesis.getVoices();
            voixFrancaise = voix.filter(function (v) { return v.lang === 'fr-FR'; })[0]
                || voix.filter(function (v) { return v.lang.indexOf('fr') === 0; })[0]
                || null;
        };
        choisir();
        window.speechSynthesis.onvoiceschanged = choisir;
    }

    function direLeMot() {
        if (graphie.fichier) {
            new Audio('/v2/fastgames/assets/son/' + graphie.fichier).play();
            return;
        }
        if (!('speechSynthesis' in window) || !graphie.mot) { return; }
        var parole = new SpeechSynthesisUtterance(graphie.mot);
        parole.lang = 'fr-FR';
        /* Un peu plus lent que la parole ordinaire : on ecoute pour ecrire, pas
           pour comprendre une phrase. */
        parole.rate = 0.85;
        if (voixFrancaise) { parole.voice = voixFrancaise; }
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(parole);
    }

    function afficherGraphie(item) {
        chargerVoix();
        graphie = { avant: item.avant, apres: item.apres, graphies: item.graphies,
                    fichier: item.fichier, mot: item.mot, saisie: '' };
        rendreGraphie();
        /* On prononce des l'arrivee : la manche COMMENCE par l'ecoute, et
           obliger a cliquer d'abord ajouterait une etape qui n'apprend rien. */
        direLeMot();
    }

    function rendreGraphie() {
        var clavier = graphie.graphies.map(function (g) {
            return '<button type="button" class="fg-graphie-touche" data-graphie="' + g + '">' + g + '</button>';
        }).join('');

        reponses.innerHTML = '<div class="fg-graphie">'
            + '<p class="fg-graphie-mot" aria-live="polite">'
            + '<span></span><span class="fg-graphie-trou"></span><span></span></p>'
            + '<div class="fg-graphie-actions">'
            + '<button type="button" class="fg-bouton" id="fg-graphie-ecouter">' + fgIconeSVG('ecoute', 22) + ' Écouter le mot</button>'
            + '<button type="button" class="fg-bouton" id="fg-graphie-effacer"'
            + (graphie.saisie ? '' : ' disabled') + '>Effacer</button>'
            + '</div>'
            + '<div class="fg-graphie-clavier" role="group" aria-label="Graphies proposées">' + clavier + '</div>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-graphie-valider"'
            + (graphie.saisie ? '' : ' disabled') + '>Valider</button>'
            + '</div></div>';

        /* textContent et non innerHTML : les mots viennent d'une banque, mais on
           n'a pas a y penser a chaque nouvelle banque. */
        var morceaux = reponses.querySelectorAll('.fg-graphie-mot span');
        morceaux[0].textContent = graphie.avant;
        morceaux[1].textContent = graphie.saisie || ' ';
        morceaux[2].textContent = graphie.apres;

        reponses.querySelectorAll('[data-graphie]').forEach(function (touche) {
            touche.addEventListener('click', function () {
                if (verrouille) { return; }
                graphie.saisie += touche.dataset.graphie;
                rendreGraphie();
            });
        });
        var ecouter = document.getElementById('fg-graphie-ecouter');
        if (ecouter) { ecouter.addEventListener('click', direLeMot); }
        var effacer = document.getElementById('fg-graphie-effacer');
        if (effacer) {
            effacer.addEventListener('click', function () {
                if (verrouille) { return; }
                graphie.saisie = '';
                rendreGraphie();
            });
        }
        var valider = document.getElementById('fg-graphie-valider');
        if (valider) { valider.addEventListener('click', validerGraphie); }
        if (verrouille) {
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }
    }

    function validerGraphie() {
        if (verrouille || !graphie.saisie) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/graphie.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                composee: graphie.saisie
            })
        }).then(lireCorrection).then(function (correction) {
            /* Le mot entier s'affiche une fois la reponse posee : c'est la
               correction, et c'est elle qui s'apprend. */
            graphie.saisie = correction.manque;
            graphie.mot = correction.mot;
            rendreGraphie();
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* --------------------------------------------------- le probleme du jour */
    /* CE QUI LE DISTINGUE DU COMPTE EST BON : la cible n'est pas affichee.
       Lire l'enonce pour savoir ce qu'on cherche, c'est l'exercice.

       Les nombres proposes sont ceux de l'enonce, et rien d'autre : on ne peut
       pas ecrire la reponse directement. Un nombre reste disponible apres usage
       - six boites de quatre se comptent aussi 4+4+4+4+4+4. */
    var probleme = null;

    function afficherProbleme(item) {
        probleme = { nombres: item.nombres_enonce, operateurs: item.operateurs, etapes: [], total: null, operateur: item.operateurs[0] };
        rendreProbleme();
    }

    function rendreProbleme() {
        var lignes = probleme.etapes.map(function (etape, i) {
            return '<p class="fg-compte-ligne">'
                + '<span>' + (etape.op ? signeAffiche(etape.op) + ' ' : '') + etape.n + '</span>'
                + (i > 0 ? '<b>= ' + etape.total + '</b>' : '')
                + '</p>';
        }).join('');

        var nombres = probleme.nombres.map(function (n, i) {
            return '<button type="button" class="fg-bouton fg-probleme-nombre" data-nombre="' + i + '" data-focus-cle="' + i + '" data-focus-apres="operateurs">' + n + '</button>';
        }).join('');

        var operateurs = probleme.operateurs.map(function (o) {
            return '<button type="button" class="fg-bouton fg-probleme-operateur' + (probleme.operateur === o ? ' est-choisi' : '')
                + '" data-op="' + o + '" data-focus-cle="' + o + '" data-focus-apres="nombres" aria-pressed="' + (probleme.operateur === o) + '">' + signeAffiche(o) + '</button>';
        }).join('');

        fgReconstruire(reponses, '<div class="fg-probleme">'
            + '<div class="fg-compte-lignes" aria-live="polite">'
            + (lignes || '<p class="fg-probleme-attente">Choisis un nombre de l’énoncé pour commencer ton calcul.</p>')
            + '</div>'
            + '<div class="fg-probleme-operateurs" role="group" aria-label="Choisir une opération" data-focus-groupe="operateurs">' + operateurs + '</div>'
            + '<div class="fg-probleme-nombres" role="group" aria-label="Nombres de l’énoncé" data-focus-groupe="nombres">' + nombres + '</div>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-probleme-valider"'
            + (probleme.etapes.length ? '' : ' disabled') + ' data-focus-defaut>C’est ma réponse</button>'
            + '<button type="button" class="fg-bouton" id="fg-probleme-effacer"'
            + (probleme.etapes.length ? '' : ' disabled') + '>Recommencer</button>'
            + '</div></div>');

        reponses.querySelectorAll('[data-op]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille) { return; }
                probleme.operateur = bouton.dataset.op;
                rendreProbleme();
            });
        });
        reponses.querySelectorAll('[data-nombre]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille) { return; }
                var n = probleme.nombres[parseInt(bouton.dataset.nombre, 10)];
                if (probleme.total === null) {
                    probleme.total = n;
                    probleme.etapes.push({ op: '', n: n, total: n });
                } else {
                    var o = probleme.operateur;
                    probleme.total = o === '+' ? probleme.total + n : (o === '-' ? probleme.total - n : probleme.total * n);
                    probleme.etapes.push({ op: o, n: n, total: probleme.total });
                }
                rendreProbleme();
            });
        });
        var effacer = document.getElementById('fg-probleme-effacer');
        if (effacer) {
            effacer.addEventListener('click', function () {
                if (verrouille) { return; }
                probleme.etapes = []; probleme.total = null;
                rendreProbleme();
            });
        }
        var valider = document.getElementById('fg-probleme-valider');
        if (valider) { valider.addEventListener('click', validerProbleme); }
        if (verrouille) {
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }
    }

    function validerProbleme() {
        if (verrouille || !probleme.etapes.length) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/probleme.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                /* Seuls l'operateur et le nombre partent : le total est
                   recalcule cote serveur, jamais recu de la page. */
                etapes: probleme.etapes.map(function (e) { return { op: e.op, n: e.n }; })
            })
        }).then(lireCorrection).then(function (correction) {
            if (!correction.juste && correction.total !== null) {
                correction.explication = 'Ton calcul donne ' + correction.total
                    + ', et la réponse est ' + correction.reponse + '. ' + correction.explication;
            }
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* ------------------------------------------------------- les parts */
    /* DEUX GESTES, ET C'EST L'EXERCICE : couper d'abord (le denominateur),
       colorier ensuite (le numerateur). Un support deja coupe ne ferait
       travailler que la moitie de la notion.

       QUELLES PARTS SONT COLORIEES N'A AUCUNE IMPORTANCE - trois quarts, ce
       sont trois parts sur quatre, ou qu'elles soient. Le serveur ne compare
       donc que deux nombres, et le client n'envoie que ca.

       La bande est en CSS, le disque en SVG : aucun fichier a heberger, net a
       toutes les tailles, comme le cadran de l'horloge. */
    var partage = null;

    /* Les decoupages proposes : ceux du cycle 2, plus rien. Offrir 7 ou 9
       n'ajouterait que du bruit a un choix qui doit se lire d'un coup. */
    var PARTAGE_DECOUPAGES = [2, 3, 4, 5, 6, 8];

    function afficherPartage(item) {
        partage = {
            support: item.support,
            numerateur: item.numerateur,
            denominateur: item.denominateur,
            parts: 0,          /* rien n'est coupe tant que l'eleve n'a pas choisi */
            coloriees: {},
            corrige: null
        };
        rendrePartage();
    }

    function partageNbColoriees() {
        var n = 0;
        for (var cle in partage.coloriees) { if (partage.coloriees[cle]) { n++; } }
        return n;
    }

    /* Un secteur de disque, en SVG. L'angle part du haut (-90 degres) pour que
       la premiere part commence a midi, comme sur un camembert dessine a la
       main. */
    function partageSecteur(index, total, rayon, cx, cy) {
        var depart = (-90 + index * 360 / total) * Math.PI / 180;
        var fin = (-90 + (index + 1) * 360 / total) * Math.PI / 180;
        var grand = (360 / total) > 180 ? 1 : 0;
        return 'M ' + cx + ' ' + cy
            + ' L ' + (cx + rayon * Math.cos(depart)).toFixed(2) + ' ' + (cy + rayon * Math.sin(depart)).toFixed(2)
            + ' A ' + rayon + ' ' + rayon + ' 0 ' + grand + ' 1 '
            + (cx + rayon * Math.cos(fin)).toFixed(2) + ' ' + (cy + rayon * Math.sin(fin)).toFixed(2)
            + ' Z';
    }

    function rendrePartage() {
        var choix = PARTAGE_DECOUPAGES.map(function (n) {
            return '<button type="button" class="fg-partage-decoupage' + (partage.parts === n ? ' est-choisi' : '')
                + '" data-parts="' + n + '" data-focus-cle="' + n + '" data-focus-apres="parts" aria-pressed="' + (partage.parts === n) + '">' + n + '</button>';
        }).join('');

        var support = '';
        if (partage.parts > 0) {
            if (partage.support === 'disque') {
                var secteurs = '';
                for (var i = 0; i < partage.parts; i++) {
                    secteurs += '<path class="fg-partage-part' + (partage.coloriees[i] ? ' est-coloriee' : '')
                        + '" data-part="' + i + '" data-focus-cle="' + i + '" role="button" tabindex="0"'
                        + ' aria-pressed="' + (partage.coloriees[i] ? 'true' : 'false') + '"'
                        + ' aria-label="Part ' + (i + 1) + ' sur ' + partage.parts + '"'
                        + ' d="' + partageSecteur(i, partage.parts, 96, 100, 100) + '"></path>';
                }
                support = '<svg class="fg-partage-disque" viewBox="0 0 200 200" role="group" data-focus-groupe="parts"'
                    + ' aria-label="Disque partagé en ' + partage.parts + ' parts">' + secteurs + '</svg>';
            } else {
                var parts = '';
                for (var j = 0; j < partage.parts; j++) {
                    parts += '<button type="button" class="fg-partage-part' + (partage.coloriees[j] ? ' est-coloriee' : '')
                        + '" data-part="' + j + '" data-focus-cle="' + j + '" aria-pressed="' + (partage.coloriees[j] ? 'true' : 'false') + '"'
                        + ' aria-label="Part ' + (j + 1) + ' sur ' + partage.parts + '"></button>';
                }
                support = '<div class="fg-partage-bande" role="group" data-focus-groupe="parts"'
                    + ' aria-label="Bande partagée en ' + partage.parts + ' parts">' + parts + '</div>';
            }
        } else {
            support = '<p class="fg-partage-attente">Choisis d’abord en combien de parts tu coupes.</p>';
        }

        fgReconstruire(reponses, '<div class="fg-partage">'
            + '<div class="fg-partage-choix" role="group" aria-label="En combien de parts couper" data-focus-groupe="decoupage">'
            + '<span>Couper en</span>' + choix + '</div>'
            + support
            + '<p class="fg-partage-compte" aria-live="polite">'
            + (partage.parts > 0 ? partageNbColoriees() + ' part' + (partageNbColoriees() > 1 ? 's' : '')
                + ' coloriée' + (partageNbColoriees() > 1 ? 's' : '') + ' sur ' + partage.parts : '')
            + '</p>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-partage-valider"'
            + (partage.parts > 0 ? '' : ' disabled') + ' data-focus-defaut>Valider</button>'
            + '</div></div>');

        reponses.querySelectorAll('[data-parts]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille) { return; }
                /* Recouper remet le coloriage a zero : garder des parts
                   coloriees d'un decoupage precedent ne voudrait rien dire. */
                partage.parts = parseInt(bouton.dataset.parts, 10);
                partage.coloriees = {};
                rendrePartage();
            });
        });
        reponses.querySelectorAll('[data-part]').forEach(function (element) {
            var basculer = function () {
                if (verrouille) { return; }
                var index = element.dataset.part;
                partage.coloriees[index] = !partage.coloriees[index];
                rendrePartage();
            };
            element.addEventListener('click', basculer);
            /* Un <path> n'est pas un bouton : il lui faut le clavier a la main. */
            element.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); basculer(); }
            });
        });
        var valider = document.getElementById('fg-partage-valider');
        if (valider) { valider.addEventListener('click', validerPartage); }
        if (verrouille) {
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }
    }

    function validerPartage() {
        if (verrouille || partage.parts === 0) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/partage.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                parts: partage.parts,
                coloriees: partageNbColoriees()
            })
        }).then(lireCorrection).then(function (correction) {
            /* Dire LAQUELLE des deux moities a echoue : le decoupage ou le
               coloriage. Un simple « faux » laisserait l'eleve chercher. */
            if (!correction.juste) {
                var quoi = !correction.decoupage
                    ? 'Le nombre de parts n’est pas le bon.'
                    : 'Le nombre de parts coloriées n’est pas le bon.';
                correction.explication = quoi + ' ' + correction.explication;
            }
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* --------------------------------------------------------- le graphique */
    /* L'etat d'une manche : une hauteur par barre. Les barres sont en CSS,
       hauteurs en pourcentages - rien a heberger, juste a toutes les tailles
       d'ecran, meme principe que la droite graduee.

       CLIQUER DANS UNE COLONNE plutot que des boutons plus et moins : la
       hauteur se choisit d'un geste, dans un espace continu. Une paire de
       boutons ferait de chaque barre un compteur a incrementer, ce qui est
       une autre competence. */
    var graphique = null;

    function afficherGraphique(item) {
        graphique = {
            series: item.series_graphique,
            max: item.max_graphique,
            hauteurs: item.series_graphique.map(function () { return 0; })
        };
        rendreGraphique();
    }

    function rendreGraphique() {
        var donnees = graphique.series.map(function (s) {
            return '<span class="fg-graphique-donnee"><b></b><i></i></span>';
        }).join('');

        /* UNE SEULE FORMULE POUR LES TROIS, `valeur / max * 100` en pourcentage
           de la zone de trace. Avant, chacun avait la sienne et aucune ne
           coincidait : les etiquettes etaient reparties par `space-between`,
           qui aligne le BORD des boites et non leur milieu ; la grille avait un
           pas fixe de 25 px alors qu'une unite en valait 22,4 ; seule la barre
           etait juste. Le sommet d'une barre ne tombait donc jamais sur sa
           graduation. Signale par le responsable technique le 08/09/2026.

           LE LIBELLE SORT DE LA COLONNE, et c'est ce qui rend le reste
           possible : son `padding-bottom` amputait la colonne de 26 px, donc
           la zone de trace ne valait pas la hauteur de la boite. Elles sont
           maintenant egales, et un pourcentage veut dire la meme chose partout. */
        var colonnes = graphique.series.map(function (s, i) {
            return '<button type="button" class="fg-graphique-colonne" data-barre="' + i + '"'
                + ' aria-label="Barre ' + s.libelle + ', hauteur ' + graphique.hauteurs[i] + ' sur ' + graphique.max + '">'
                /* AUCUN CHIFFRE SUR LA BARRE. Il y en avait un depuis le
                   07/09 pour compenser l'imprecision du geste ; maintenant que
                   le sommet tombe exactement sur sa graduation, l'eleve LIT sa
                   hauteur sur l'axe - ce qu'un graphique est fait pour. */
                + '<span class="fg-graphique-barre" style="height:'
                + (graphique.hauteurs[i] / graphique.max * 100) + '%"></span>'
                + '</button>';
        }).join('');

        var libelles = graphique.series.map(function () {
            return '<span class="fg-graphique-libelle"></span>';
        }).join('');

        /* Une ligne par unite, marquee sur les graduations etiquetees : c'est
           elle qui permet de compter, maintenant que le chiffre a disparu. */
        var pasEtiquette = graphique.max > 10 ? 4 : 2;
        var grille = '';
        var axe = '';
        for (var v = 0; v <= graphique.max; v++) {
            var etiquetee = (v % pasEtiquette === 0);
            grille += '<span class="fg-graphique-ligne' + (etiquetee ? ' est-marquee' : '')
                + '" style="bottom:' + (v / graphique.max * 100) + '%"></span>';
            if (etiquetee) {
                axe += '<span style="bottom:' + (v / graphique.max * 100) + '%">' + v + '</span>';
            }
        }

        reponses.innerHTML = '<div class="fg-graphique">'
            + '<div class="fg-graphique-donnees" aria-label="Données de l’enquête">' + donnees + '</div>'
            + '<div class="fg-graphique-cadre">'
            + '<div class="fg-graphique-axe" aria-hidden="true">' + axe + '</div>'
            + '<div class="fg-graphique-zone">'
            + '<div class="fg-graphique-grille" aria-hidden="true">' + grille + '</div>'
            + '<div class="fg-graphique-colonnes" role="group" aria-label="Barres du graphique">' + colonnes + '</div>'
            + '</div>'
            + '<span></span>'
            + '<div class="fg-graphique-libelles" aria-hidden="true">' + libelles + '</div>'
            + '</div>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-graphique-valider">Valider</button>'
            + '</div></div>';

        /* textContent et non innerHTML : les libelles viennent d'une banque de
           contenu, mais rien n'oblige a y penser a chaque nouvelle banque. */
        reponses.querySelectorAll('.fg-graphique-donnee').forEach(function (noeud, i) {
            noeud.querySelector('b').textContent = graphique.series[i].libelle;
            noeud.querySelector('i').textContent = graphique.series[i].valeur;
        });
        reponses.querySelectorAll('.fg-graphique-libelle').forEach(function (noeud, i) {
            noeud.textContent = graphique.series[i].libelle;
        });

        reponses.querySelectorAll('[data-barre]').forEach(function (colonne) {
            var index = parseInt(colonne.dataset.barre, 10);

            /* UNE UNITE FAIT 17 PX SUR TELEPHONE, un doigt en couvre quarante :
               viser la bonne hauteur du premier coup est impossible. Le clic
               pose donc une valeur approchee, et le GLISSER l'ajuste sans
               relacher - c'est le meme doigt qui corrige, en voyant le chiffre
               changer sur la barre. Signale par le responsable technique le 07/09/2026 : « pas
               assez precis ». */
            var poser = function (clientY) {
                /* La colonne EST la zone de trace depuis que le libelle en est
                   sorti : plus de reserve a retrancher, et le clic tombe donc
                   sur la meme echelle que la grille et l'axe. */
                var boite = colonne.getBoundingClientRect();
                var depuisBas = boite.bottom - clientY;
                var valeur = Math.max(0, Math.min(graphique.max,
                    Math.round(depuisBas / boite.height * graphique.max)));
                if (valeur === graphique.hauteurs[index]) { return; }
                graphique.hauteurs[index] = valeur;
                /* Redessiner la seule barre concernee, pas toute la zone : un
                   innerHTML complet a chaque pixel de glissement casserait la
                   capture du pointeur et rendrait le geste saccade. */
                var barre = colonne.querySelector('.fg-graphique-barre');
                barre.style.height = (valeur / graphique.max * 100) + '%';
                colonne.setAttribute('aria-label', 'Barre ' + graphique.series[index].libelle
                    + ', hauteur ' + valeur + ' sur ' + graphique.max);
            };

            colonne.addEventListener('pointerdown', function (e) {
                if (verrouille) { return; }
                colonne.setPointerCapture(e.pointerId);
                poser(e.clientY);
            });
            colonne.addEventListener('pointermove', function (e) {
                if (verrouille || !colonne.hasPointerCapture(e.pointerId)) { return; }
                poser(e.clientY);
            });
            /* Au clavier, une colonne se regle par pas de un : cliquer a une
               hauteur precise n'a pas d'equivalent au clavier, il faut donc
               un second chemin et pas seulement un focus visible. */
            colonne.addEventListener('keydown', function (e) {
                if (verrouille) { return; }
                var pas = 0;
                if (e.key === 'ArrowUp' || e.key === 'ArrowRight') { pas = 1; }
                if (e.key === 'ArrowDown' || e.key === 'ArrowLeft') { pas = -1; }
                if (!pas) { return; }
                e.preventDefault();
                graphique.hauteurs[index] = Math.max(0, Math.min(graphique.max,
                    graphique.hauteurs[index] + pas));
                rendreGraphique();
                var reprise = reponses.querySelector('[data-barre="' + index + '"]');
                if (reprise) { reprise.focus(); }
            });
        });

        var valider = document.getElementById('fg-graphique-valider');
        if (valider) { valider.addEventListener('click', validerGraphique); }
        if (verrouille) {
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }
    }

    /* DIRE LAQUELLE, PAS COMBIEN. La correction annoncait « 1 barre a revoir »
       sans jamais designer laquelle : sur trois series, l'eleve devait comparer
       lui-meme les trois hauteurs aux trois nombres pour trouver son erreur.
       Signale par le responsable technique le 08/09/2026.

       Le serveur renvoie deja `attendues` depuis la premiere version, le client
       avait donc tout pour le dire - il se contentait de compter. */
    function marquerBarresFausses(attendues) {
        if (!attendues) { return; }
        reponses.querySelectorAll('[data-barre]').forEach(function (colonne) {
            var i = parseInt(colonne.dataset.barre, 10);
            if (graphique.hauteurs[i] === attendues[i]) { return; }
            colonne.classList.add('est-fausse');
            /* Un trait a la hauteur attendue : l'eleve voit OU il aurait du
               s'arreter, plutot que de relire un nombre dans une phrase. */
            var cible = document.createElement('span');
            cible.className = 'fg-graphique-cible';
            cible.style.bottom = (attendues[i] / graphique.max * 100) + '%';
            colonne.appendChild(cible);
        });
    }

    function phraseBarresFausses(attendues) {
        if (!attendues) { return ''; }
        var fautes = [];
        graphique.series.forEach(function (s, i) {
            if (graphique.hauteurs[i] !== attendues[i]) {
                fautes.push(s.libelle + ' : tu as posé ' + graphique.hauteurs[i]
                    + ', il en fallait ' + attendues[i]);
            }
        });
        return fautes.join('. ') + '.';
    }

    function validerGraphique() {
        if (verrouille) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/graphique.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                hauteurs: graphique.hauteurs
            })
        }).then(lireCorrection).then(function (correction) {
            if (!correction.juste) {
                marquerBarresFausses(correction.attendues);
                correction.explication = phraseBarresFausses(correction.attendues);
            }
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* ----------------------------------------------------- la regle graduee */
    /* L'etat d'une manche : ou est posee la regle, et quelle graduation a ete
       cliquee. La regle glisse a la souris, au doigt et aux fleches.

       L'ECHELLE SUIT LA LARGEUR REELLE DU BANC, elle n'est pas ecrite en dur.
       A 26 px par centimetre - la valeur naturelle sur ordinateur - une regle
       de 15 cm mesure 406 px pour un banc de 291 sur telephone : sa course
       tombait a zero, elle ne glissait plus, et le premier geste devenait
       impossible. Trouve en mesurant la maquette a 390 px, pas en relisant.

       SUR TELEPHONE LA REGLE RACCOURCIT au lieu de se comprimer : a echelle
       serree, deux graduations voisines tombent a 13 px l'une de l'autre, trop
       pres pour un doigt. Decision de le responsable technique du 07/09/2026 entre trois issues
       possibles - regle plus courte, banc qui defile, ou pas de graduation a
       cliquer sur petit ecran.

       AUCUN RAPPEL CHIFFRE de la longueur en cours de lecture, meme principe
       que l'horloge : l'afficher transformerait l'exercice en reglage jusqu'a
       ce que le nombre tombe juste. */
    var regle = null;

    function afficherRegle(item) {
        regle = {
            mode: item.mode_regle,
            segments: item.segments,
            regleCm: item.regle_cm,
            pxParCm: 26,
            x: 0,
            /* OU COMMENCE LE SEGMENT, en pixels depuis le bord du banc. C'etait
               un `left: 14px` fige dans le CSS : le zero de la regle, pose a
               8 px, tombait donc a 6 px de sa cible - DANS la zone d'aimant.
               La regle s'accrochait toute seule des l'affichage et le premier
               geste, placer le zero, ne se jouait jamais. Signale par le responsable technique le
               08/09/2026, capture a l'appui.

               Le decalage suit le NUMERO DE LA MANCHE et non un tirage : deux
               parties reconstruites depuis la meme graine doivent se ressembler,
               c'est la regle du projet depuis les premieres mecaniques. */
            // 36 px au minimum : le zero de la regle est a 8 px, l'aimant
            // accroche a 12, donc en dessous de 20 la regle serait deja
            // collee a l'ouverture - le defaut qu'on corrige ici.
            depart: 36 + (index % 4) * 22,
            graduation: null
        };
        rendreRegle();
        /* Un redimensionnement change l'echelle, donc toute la geometrie. On
           redessine plutot que de laisser une regle calculee pour une autre
           largeur. */
        window.addEventListener('resize', redimensionnerRegle);
    }

    function redimensionnerRegle() {
        if (!regle || verrouille) { return; }
        rendreRegle();
    }

    /* Combien de centimetres la regle porte, et a quelle echelle, pour la
       largeur disponible. Sur petit ecran on RACCOURCIT la regle avant de
       resserrer les graduations : mieux vaut 11 cm lisibles que 15 cm
       impossibles a viser. Decision de le responsable technique du 07/09/2026.

       MAIS JAMAIS PLUS COURTE QUE LE SEGMENT A MESURER. Sans ce plancher, un
       segment de 14 cm se retrouvait devant une regle de 11 : la bonne
       graduation n'existait tout simplement pas, et la manche devenait
       insoluble. Trouve en mesurant a 390 px, pas en relisant - la banque
       « mesurer » monte a 14 cm alors que la regle declaree en fait 15. */
    function regleGeometrie(largeur) {
        var minimum = Math.max.apply(null, regle.segments) + 1;
        var cm = regle.regleCm;
        var px = Math.floor((largeur * 0.72 - 16) / cm);
        while (px < 20 && cm > minimum) {
            cm -= 1;
            px = Math.floor((largeur * 0.72 - 16) / cm);
        }
        return {cm: cm, px: Math.max(13, Math.min(26, px))};
    }

    function rendreRegle() {
        var large = reponses.clientWidth || 640;
        var geo = regleGeometrie(large);
        regle.pxParCm = geo.px;
        regle.cmAffiches = geo.cm;

        var largeurRegle = geo.cm * geo.px + 16;
        var couleurs = ['fg-regle-segment-a', 'fg-regle-segment-b'];
        var segments = regle.segments.map(function (cm, i) {
            return '<span class="fg-regle-segment ' + couleurs[i] + '" style="top:' + (18 + i * 30)
                + 'px;left:' + regle.depart + 'px;width:' + (cm * geo.px) + 'px"></span>';
        }).join('');

        var graduations = '';
        for (var c = 0; c <= geo.cm; c++) {
            var x = 8 + c * geo.px;
            graduations += '<span class="fg-regle-grad" style="left:' + x + 'px"></span>'
                + '<span class="fg-regle-num" style="left:' + x + 'px">' + c + '</span>';
            if (c < geo.cm) {
                graduations += '<span class="fg-regle-grad fg-regle-grad-demi" style="left:'
                    + (x + geo.px / 2) + 'px"></span>';
            }
            graduations += '<button type="button" class="fg-regle-cible" data-grad="' + c + '"'
                + ' style="left:' + x + 'px" aria-label="Graduation ' + c + '"'
                + ' aria-pressed="' + (regle.graduation === c) + '"></button>';
        }

        /* Le mode « estimer » n'affiche PAS de regle : c'est tout l'exercice.
           Un temoin d'un centimetre reste visible, sans quoi il n'y aurait
           aucune unite a reporter et la question n'aurait pas de sens. */
        var outil = regle.mode === 'estimer'
            ? '<span class="fg-regle-temoin" style="width:' + geo.px + 'px">1 cm</span>'
            : '<div class="fg-regle-outil" id="fg-regle-outil" tabindex="0" role="slider"'
                + ' aria-label="Règle graduée, à faire glisser" aria-valuemin="0" aria-valuemax="100"'
                + ' aria-valuenow="' + Math.round(regle.x) + '" style="width:' + largeurRegle + 'px">'
                + graduations + '</div>';

        /* CE QUE L'ELEVE ANNONCE, avec deux boutons pour le corriger d'un cran.
           Une graduation fait 20 px sur telephone : cliquer juste du premier
           coup au doigt est illusoire, et sans ce reglage fin l'eleve se
           trompait de lecture pour un geste rate. Ce chiffre n'est pas la
           reponse - c'est ce qu'il vient de designer sur la regle. */
        var saisie = regle.mode === 'estimer'
            ? '<div class="fg-regle-saisie"><label for="fg-regle-cm">Je pense qu’il mesure</label>'
                + '<input type="number" id="fg-regle-cm" min="0" max="30" inputmode="numeric"'
                + ' value="' + (regle.graduation === null ? '' : regle.graduation) + '"><span>cm</span></div>'
            : '<div class="fg-regle-lecture">'
                + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-cran="-1"'
                + ' aria-label="Un centimètre de moins">−</button>'
                + '<p aria-live="polite">'
                + (regle.graduation === null
                    ? '<span class="fg-regle-vide">Clique la graduation du bout</span>'
                    : 'Je lis <b>' + regle.graduation + ' cm</b>')
                + '</p>'
                + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-cran="1"'
                + ' aria-label="Un centimètre de plus">+</button></div>';

        reponses.innerHTML = '<div class="fg-regle">'
            + '<div class="fg-regle-banc" id="fg-regle-banc">' + segments + outil
            + '<span class="fg-regle-accroche" hidden>zéro en place</span></div>'
            + saisie
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-regle-valider">Valider</button>'
            + '</div></div>';

        var banc = document.getElementById('fg-regle-banc');
        var outilNoeud = document.getElementById('fg-regle-outil');
        if (outilNoeud) {
            poserRegle(regle.x, banc, outilNoeud);
            var glisse = null;
            outilNoeud.addEventListener('pointerdown', function (e) {
                if (verrouille || e.target.classList.contains('fg-regle-cible')) { return; }
                glisse = e.clientX - regle.x;
                outilNoeud.setPointerCapture(e.pointerId);
            });
            outilNoeud.addEventListener('pointermove', function (e) {
                if (glisse === null) { return; }
                poserRegle(e.clientX - glisse, banc, outilNoeud);
            });
            outilNoeud.addEventListener('pointerup', function () { glisse = null; });
            outilNoeud.addEventListener('keydown', function (e) {
                if (verrouille) { return; }
                var pas = e.shiftKey ? 1 : 4;
                if (e.key === 'ArrowLeft') { poserRegle(regle.x - pas, banc, outilNoeud); e.preventDefault(); }
                if (e.key === 'ArrowRight') { poserRegle(regle.x + pas, banc, outilNoeud); e.preventDefault(); }
            });
            outilNoeud.querySelectorAll('[data-grad]').forEach(function (cible) {
                cible.addEventListener('click', function (e) {
                    if (verrouille) { return; }
                    e.stopPropagation();
                    regle.graduation = parseInt(cible.dataset.grad, 10);
                    /* Redessiner, pas seulement marquer la cible : depuis que la
                       lecture est ecrite sous la regle, la mettre a jour fait
                       partie du clic. Sans cela le chiffre annonce restait
                       celui du clic precedent. */
                    rendreRegle();
                });
            });
        }

        var champ = document.getElementById('fg-regle-cm');
        if (champ) {
            champ.addEventListener('input', function () {
                regle.graduation = champ.value === '' ? null : parseInt(champ.value, 10);
            });
        }
        reponses.querySelectorAll('[data-cran]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille) { return; }
                var depart = regle.graduation === null ? 0 : regle.graduation;
                regle.graduation = Math.max(0, Math.min(regle.cmAffiches,
                    depart + parseInt(bouton.dataset.cran, 10)));
                rendreRegle();
            });
        });
        var valider = document.getElementById('fg-regle-valider');
        if (valider) { valider.addEventListener('click', validerRegle); }
        if (verrouille) {
            reponses.querySelectorAll('button,input').forEach(function (b) { b.disabled = true; });
        }
    }

    /* Distance en pixels sous laquelle le zero de la regle s'accroche au debut
       du segment. Douze pixels : assez pour rattraper un doigt, trop peu pour
       poser la regle a la place de l'eleve - il faut deja viser le bon
       centimetre pour entrer dans la zone. */
    var AIMANT_REGLE = 12;

    function poserRegle(x, banc, outilNoeud) {
        /* Le banc mesure zero tant que la zone n'est pas dessinee : sans ce
           Math.max, la borne haute devient negative et la regle reste collee a
           gauche, donc inutilisable. */
        var maxPos = Math.max(0, banc.clientWidth - outilNoeud.offsetWidth);
        x = Math.max(0, Math.min(maxPos, x));

        /* AIMANTATION DU ZERO SUR LE DEBUT DU SEGMENT. Sans elle, poser le zero
           exactement demande une precision au pixel qu'un CE1 n'a pas au doigt,
           et l'eleve rate la mesure pour un geste, pas pour un raisonnement.
           L'aimant ne dit PAS ou est le debut du segment : il faut deja y etre
           presque. Il rattrape la main, il ne fait pas le travail. */
        var debut = regle.depart - REGLE_MARGE_ZERO;
        var accroche = Math.abs(x - debut) <= AIMANT_REGLE;
        if (accroche) { x = debut; }

        regle.x = x;
        regle.accroche = accroche;
        outilNoeud.style.left = regle.x + 'px';
        outilNoeud.setAttribute('aria-valuenow', String(Math.round(regle.x)));
        outilNoeud.classList.toggle('est-accrochee', accroche);
        var temoin = reponses.querySelector('.fg-regle-accroche');
        if (temoin) { temoin.hidden = !accroche; }
    }

    /* Le zero de la regle est a 8 px de son bord gauche : c'est la seule marge
       encore ecrite en dur, et elle vit aussi dans construireRegle(). Le debut
       du segment, lui, n'est plus fige - il vient de regle.depart, pose par
       manche. */
    var REGLE_MARGE_ZERO = 8;

    /* MONTRER LA BONNE LONGUEUR SUR LE BANC, pas seulement l'ecrire. Un ruban
       de la longueur attendue, decoupe en centimetres, se pose sous le segment :
       l'eleve compare des longueurs plutot que de relire un nombre.

       LE MEME RUBAN POUR LES TROIS MODES. « Mesurer » aurait pu se contenter de
       marquer une graduation, mais « ecart » n'a pas de graduation a marquer -
       sa reponse est une difference - et « estimer » n'a pas de regle du tout.
       Un seul rendu couvre les trois et se lit pareil partout. */
    function montrerLongueurAttendue(cm) {
        var banc = document.getElementById('fg-regle-banc');
        if (!banc || !cm) { return; }
        var ruban = document.createElement('div');
        ruban.className = 'fg-regle-attendu';
        ruban.style.left = regle.depart + 'px';
        ruban.style.width = (cm * regle.pxParCm) + 'px';
        var traits = '';
        for (var c = 1; c < cm; c++) {
            traits += '<span style="left:' + (c * regle.pxParCm) + 'px"></span>';
        }
        ruban.innerHTML = traits + '<b>' + cm + ' cm</b>';
        banc.appendChild(ruban);
    }

    function validerRegle() {
        if (verrouille || regle.graduation === null || isNaN(regle.graduation)) {
            retour.hidden = false;
            retour.classList.remove('est-juste', 'est-faux');
            retour.textContent = regle.mode === 'estimer'
                ? 'Écris ton estimation en centimètres.'
                : 'Clique la graduation qui tombe au bout du segment.';
            return;
        }
        verrouille = true;
        reponses.querySelectorAll('button,input').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/regle.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                cm: regle.graduation
            })
        }).then(lireCorrection).then(function (correction) {
            if (!correction.juste) { montrerLongueurAttendue(correction.attendu); }
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* ------------------------------------------------------------ la balance */
    /* L'etat d'une manche : les masses posees sur le plateau de droite.

       LE FLEAU DEMANDE AU SERVEUR DE QUEL COTE IL PENCHE. Le client ne connait
       pas la masse de l'objet - il ne doit pas la connaitre - donc il ne peut
       pas calculer l'inclinaison lui-meme. La premiere version le faisait quand
       meme, sur une CONSTANTE INVENTEE de 600 g : le fleau penchait au hasard,
       affichait un desequilibre sous un « Bravo, c'est juste », et n'apprenait
       rien alors que lire l'inclinaison EST la competence 3972. Signale par
       le responsable technique, capture a l'appui, le 07/09/2026.
       api/balance.php repond maintenant « leger », « juste » ou « lourd » -
       LE SENS SEUL, jamais l'ecart chiffre, qui donnerait la masse en une pesee.

       EN MODE « estimer », IL N'Y A PAS DE BALANCE DU TOUT. Un fleau qui repond
       a chaque pose donnerait la masse par dichotomie en quelques essais, alors
       que 3973 demande justement d'estimer sans peser. L'objet est montre, les
       masses sont proposees, et c'est tout. */
    var balance = null;

    function afficherBalance(item) {
        balance = {
            nom: item.objet_nom,
            image: item.objet_image,
            masses: item.masses_offertes,
            mode: item.mode_balance,
            posees: [],
            /* Ce que la derniere pesee a repondu. `null` = pas encore pese,
               donc fleau au repos plutot qu'incline au hasard. */
            sens: null
        };
        rendreBalance();
        /* Une pesee des l'ouverture : plateau vide contre objet, une vraie
           balance penche franchement du cote de l'objet. La laisser droite
           ferait croire qu'un plateau vide equilibre quelque chose. */
        peserBalance();
    }

    /* Demande au serveur de quel cote la balance penche, puis redessine. Une
       requete par pose : c'est le prix d'un fleau qui ne ment pas, et une vraie
       balance met elle aussi un instant a se stabiliser. */
    function peserBalance() {
        if (balance.mode !== 'equilibre') { return; }
        fetch('/v2/fastgames/api/balance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                action: 'peser',
                posees: balance.posees
            })
        }).then(function (reponse) {
            return reponse.json();
        }).then(function (pesee) {
            if (!pesee || !pesee.ok) { return; }
            balance.sens = pesee.sens;
            balance.cran = pesee.cran;
            inclinerFleau();
        }).catch(function () {
            /* Une pesee qui echoue laisse le fleau ou il est : mieux vaut une
               balance qui ne bouge pas qu'une balance qui ment. */
        });
    }

    /* Incline le fleau selon la DERNIERE reponse du serveur, qui rend un cran
       de -12 a +12, un degre par cran. Assez pour voir la balance se redresser
       a chaque masse posee - c'est le raisonnement de la competence - sans
       qu'un angle continu ne trahisse la masse. */
    function angleBalance() {
        return balance.cran || 0;
    }

    function inclinerFleau() {
        var fleau = reponses.querySelector('.fg-balance-fleau');
        if (!fleau) { return; }
        var angle = angleBalance();
        fleau.style.transform = 'translateX(-50%) rotate(' + angle + 'deg)';
        var gauche = reponses.querySelector('.fg-balance-gauche');
        var droite = reponses.querySelector('.fg-balance-droite');
        /* Le plateau le plus lourd DESCEND, donc son `top` augmente. Masses
           trop legeres (angle negatif) : l'objet, a gauche, descend. Les deux
           formules etaient inversees dans la premiere version - defaut masque
           par le fleau faux, trouve en le corrigeant. */
        if (gauche) { gauche.style.top = (24 - (angle / 12) * 18) + 'px'; }
        if (droite) { droite.style.top = (24 + (angle / 12) * 18) + 'px'; }
    }

    function balanceLibelle(g) {
        return g >= 1000 && g % 1000 === 0 ? (g / 1000) + ' kg'
             : (g >= 1000 ? String(g / 1000).replace('.', ',') + ' kg' : g + ' g');
    }

    function rendreBalance() {
        var angle = angleBalance();
        var pente = angle / 12;

        var posees = balance.posees.map(function (m) {
            return '<span class="fg-balance-poids">' + balanceLibelle(m) + '</span>';
        }).join('');

        var boutons = balance.masses.map(function (m) {
            return '<button type="button" class="fg-balance-masse" data-masse="' + m + '" data-focus-cle="' + m + '">'
                + balanceLibelle(m) + '</button>';
        }).join('');

        /* En mode « estimer », ni fleau ni plateaux mobiles : l'objet est
           simplement montre. Voir le commentaire du bloc. */
        var scene = balance.mode === 'equilibre'
            ? '<div class="fg-balance-scene">'
                + '<span class="fg-balance-socle" aria-hidden="true"></span>'
                + '<span class="fg-balance-pivot" aria-hidden="true"></span>'
                + '<span class="fg-balance-fleau" aria-hidden="true" style="transform:translateX(-50%) rotate('
                + angle + 'deg)"></span>'
                /* Repos a 24 px et amplitude de 18 : a 14 et 22, le plateau qui
                   monte sortait du cadre de la scene par le haut. */
                + '<div class="fg-balance-plateau fg-balance-gauche" style="top:' + (24 - pente * 18) + 'px">'
                + '<h3>À peser</h3><p class="fg-balance-objet"><span></span><small></small></p></div>'
                + '<div class="fg-balance-plateau fg-balance-droite" style="top:' + (24 + pente * 18) + 'px">'
                + '<h3>Tes masses</h3><div class="fg-balance-posees" aria-live="polite">' + posees + '</div></div>'
                + '</div>'
            : '<div class="fg-balance-estimation">'
                + '<p class="fg-balance-objet"><span></span><small></small></p>'
                + '<p class="fg-balance-note">Pas de balance ici : c’est à toi de deviner.</p>'
                + '<div class="fg-balance-posees" aria-live="polite">' + posees + '</div>'
                + '</div>';

        fgReconstruire(reponses, '<div class="fg-balance">'
            + scene
            + '<div class="fg-balance-masses" role="group" aria-label="Masses à poser" data-focus-groupe="masses">' + boutons + '</div>'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-secondaire" id="fg-balance-retirer"'
            + (balance.posees.length ? '' : ' disabled') + '>Retirer la dernière</button>'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-balance-valider" data-focus-defaut>Valider</button>'
            + '</div></div>');

        var objet = reponses.querySelector('.fg-balance-objet');
        objet.querySelector('span').textContent = balance.image;
        objet.querySelector('small').textContent = balance.nom;

        reponses.querySelectorAll('[data-masse]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille) { return; }
                balance.posees.push(parseInt(bouton.dataset.masse, 10));
                rendreBalance();
                peserBalance();
            });
        });
        var retirer = document.getElementById('fg-balance-retirer');
        if (retirer) {
            retirer.addEventListener('click', function () {
                if (verrouille) { return; }
                balance.posees.pop();
                rendreBalance();
                peserBalance();
            });
        }
        var valider = document.getElementById('fg-balance-valider');
        if (valider) { valider.addEventListener('click', validerBalance); }
        if (verrouille) {
            reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }
    }

    /* MONTRER CE QU'IL FALLAIT POSER, a cote de ce qui a ete pose. Les deux
       rangees de pastilles se comparent d'un coup d'oeil, la ou « il fallait
       700 g » demande de redecomposer soi-meme.

       LA DECOMPOSITION EST CALCULEE ICI, pas envoyee par le serveur : il ne dit
       que la masse, et le glouton sur des poids canoniques (1000, 500, 200,
       100, 50) donne toujours la decomposition la plus courte. En mode
       « estimer » la masse tombe rarement juste sur ces poids, d'ou le reste
       affiche tel quel plutot qu'arrondi en silence. */
    function montrerMassesAttendues(masse) {
        var plateau = reponses.querySelector('.fg-balance-gauche')
            || reponses.querySelector('.fg-balance-estimation');
        if (!plateau || !masse) { return; }

        var reste = masse;
        var poids = balance.masses.slice().sort(function (a, b) { return b - a; });
        var pastilles = '';
        poids.forEach(function (m) {
            while (reste >= m) { reste -= m; pastilles += '<span>' + balanceLibelle(m) + '</span>'; }
        });
        if (reste > 0) { pastilles += '<span>+ ' + balanceLibelle(reste) + '</span>'; }

        var bloc = document.createElement('div');
        bloc.className = 'fg-balance-attendu';
        bloc.innerHTML = '<h4>Il fallait</h4><div>' + pastilles + '</div>';
        plateau.appendChild(bloc);
    }

    function validerBalance() {
        if (verrouille) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/balance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                posees: balance.posees
            })
        }).then(lireCorrection).then(function (correction) {
            /* Dire le SENS de l'erreur : trop leger et trop lourd ne se
               corrigent pas par le meme geste. */
            if (!correction.juste) {
                var quoi = correction.sens === 'leger'
                    ? 'Trop léger : il fallait ajouter des masses.'
                    : 'Trop lourd : il fallait en retirer.';
                correction.explication = quoi + ' ' + correction.explication;
                montrerMassesAttendues(correction.masse);
            }
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* ------------------------------------------------------ les paquets de dix */
    /* L'etat d'une manche : quels jetons sont dans le paquet en cours, combien
       de paquets sont deja fermes.

       LES JETONS SONT POSES EN DESORDRE, jamais alignes par dix. Ranges, il n'y
       aurait plus rien a grouper et compter un a un suffirait - ce qui n'est
       pas la competence visee.

       AUCUN TOTAL AFFICHE pendant le groupage, meme principe que l'horloge et
       la regle. La reponse se compose en DIZAINES ET UNITES, et pas en un seul
       nombre : un champ unique accepterait un comptage un a un sans jamais le
       distinguer d'un vrai groupement. */
    var collection = null;

    function afficherCollection(item) {
        collection = {
            mode: item.mode_collection,
            motif: item.motif_collection,
            total: item.total_jetons,
            cible: item.cible_jetons,
            enCours: [],
            paquets: 0,
            ranges: {},
            poses: 0
        };
        rendreCollection();
    }

    function rendreCollection() {
        var jetons = '';
        var combien = collection.mode === 'constituer' ? collection.poses : collection.total;
        for (var i = 0; i < combien; i++) {
            var etat = collection.ranges[i] ? ' est-range'
                     : (collection.enCours.indexOf(i) !== -1 ? ' est-encours' : '');
            /* Desordre volontaire, mais REPRODUCTIBLE : l'angle vient de
               l'index, pas d'un tirage. Un jeton qui bougerait a chaque
               redessin donnerait le tournis. */
            jetons += '<button type="button" class="fg-collection-jeton' + etat + '" data-jeton="' + i + '" data-focus-cle="' + i + '"'
                + ' style="transform:rotate(' + (((i * 37) % 31) - 15) + 'deg)"'
                + ' aria-label="Jeton ' + (i + 1) + '"></button>';
        }

        var paquets = '';
        for (var p = 0; p < collection.paquets; p++) {
            paquets += '<span class="fg-collection-paquet">10<small>dizaine</small></span>';
        }

        var saisie = collection.mode === 'constituer'
            ? '<p class="fg-collection-consigne">Il faut en poser <b>' + collection.cible + '</b>.</p>'
                + '<div class="fg-collection-poser" data-focus-groupe="poser">'
                + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-poser="10" data-focus-cle="10">+ un paquet de 10</button>'
                + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-poser="1" data-focus-cle="1">+ 1 jeton</button>'
                + '<button type="button" class="fg-bouton fg-bouton-secondaire" data-poser="-1" data-focus-cle="-1">− 1 jeton</button>'
                + '</div>'
            : '<div class="fg-collection-compose">'
                + '<label for="fg-collection-d">dizaines</label>'
                + '<input type="number" id="fg-collection-d" min="0" max="9" inputmode="numeric">'
                + '<label for="fg-collection-u">unités</label>'
                + '<input type="number" id="fg-collection-u" min="0" max="9" inputmode="numeric">'
                + '</div>';

        fgReconstruire(reponses, '<div class="fg-collection">'
            + '<div class="fg-collection-paquets" aria-live="polite" aria-label="Paquets de dix">' + paquets + '</div>'
            + '<div class="fg-collection-jetons" role="group" aria-label="Jetons" data-focus-groupe="jetons">' + jetons + '</div>'
            + saisie
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-collection-valider" data-focus-defaut>Valider</button>'
            + '</div></div>');

        reponses.querySelectorAll('.fg-collection-jeton').forEach(function (jeton) {
            jeton.textContent = collection.motif;
        });

        reponses.querySelectorAll('[data-jeton]').forEach(function (jeton) {
            jeton.addEventListener('click', function () {
                if (verrouille || collection.mode === 'constituer') { return; }
                var i = parseInt(jeton.dataset.jeton, 10);
                if (collection.ranges[i] || collection.enCours.indexOf(i) !== -1) { return; }
                collection.enCours.push(i);
                if (collection.enCours.length === 10) {
                    collection.enCours.forEach(function (k) { collection.ranges[k] = true; });
                    collection.enCours = [];
                    collection.paquets += 1;
                }
                rendreCollection();
            });
        });

        reponses.querySelectorAll('[data-poser]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                if (verrouille) { return; }
                var delta = parseInt(bouton.dataset.poser, 10);
                collection.poses = Math.max(0, Math.min(99, collection.poses + delta));
                collection.paquets = Math.floor(collection.poses / 10);
                collection.ranges = {};
                for (var k = 0; k < collection.paquets * 10; k++) { collection.ranges[k] = true; }
                collection.enCours = [];
                for (var j = collection.paquets * 10; j < collection.poses; j++) { collection.enCours.push(j); }
                rendreCollection();
            });
        });

        var valider = document.getElementById('fg-collection-valider');
        if (valider) { valider.addEventListener('click', validerCollection); }
        if (verrouille) {
            reponses.querySelectorAll('button,input').forEach(function (b) { b.disabled = true; });
        }
    }

    /* MONTRER LE GROUPEMENT ATTENDU : autant de paquets pleins que de dizaines,
       puis les jetons qui restent. C'est le geste lui-meme qui est rejoue, pas
       son resultat - « 4 dizaines et 7 unites » redit la reponse sans montrer
       d'ou elle vient. */
    function montrerGroupementAttendu(total) {
        var zone = reponses.querySelector('.fg-collection');
        if (!zone || !total) { return; }
        var paquets = Math.floor(total / 10);
        var reste = total % 10;

        var vignettes = '';
        for (var p = 0; p < paquets; p++) {
            vignettes += '<span class="fg-collection-paquet">10<small>dizaine</small></span>';
        }
        var restants = '';
        for (var u = 0; u < reste; u++) {
            restants += '<span class="fg-collection-reste">' + collection.motif + '</span>';
        }

        var bloc = document.createElement('div');
        bloc.className = 'fg-collection-attendu';
        bloc.innerHTML = '<h4>Il fallait</h4>'
            + '<div class="fg-collection-attendu-rangee">' + vignettes
            + (restants ? '<span class="fg-collection-plus">+</span>' + restants : '')
            + '</div>';
        zone.appendChild(bloc);
    }

    function validerCollection() {
        if (verrouille) { return; }
        var d;
        var u;
        if (collection.mode === 'constituer') {
            d = Math.floor(collection.poses / 10);
            u = collection.poses % 10;
        } else {
            var champD = document.getElementById('fg-collection-d');
            var champU = document.getElementById('fg-collection-u');
            d = parseInt(champD.value, 10);
            u = parseInt(champU.value, 10);
            if (isNaN(d) || isNaN(u)) {
                retour.hidden = false;
                retour.classList.remove('est-juste', 'est-faux');
                retour.textContent = 'Écris le nombre de dizaines et le nombre d’unités.';
                return;
            }
        }
        verrouille = true;
        reponses.querySelectorAll('button,input').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/collection.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                dizaines: d,
                unites: u
            })
        }).then(lireCorrection).then(function (correction) {
            /* Nommer l'erreur : un mauvais nombre de paquets et un mauvais
               reste ne se reprennent pas par le meme geste. */
            if (!correction.juste) {
                var quoi = correction.dizaines_justes
                    ? 'Les paquets sont bons, ce sont les jetons qui restent qu’il faut recompter.'
                    : 'Le nombre de paquets de dix n’est pas le bon.';
                correction.explication = quoi + ' ' + correction.explication;
                montrerGroupementAttendu(correction.total);
            }
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* ------------------------------------------------------------ l'horloge */
    /* L'etat d'une manche : ou pointent les deux aiguilles, et laquelle on
       deplace. Le cadran est dessine en SVG - aucun fichier a heberger, et il
       reste net a n'importe quelle taille.

       AUCUN RAPPEL CHIFFRE de ce que l'eleve vient de poser. Afficher « tu
       affiches 7 h 30 » transformerait l'exercice en reglage jusqu'a ce que le
       texte corresponde a l'enonce, alors que ce qui s'apprend ici est
       justement de LIRE la position des aiguilles. */
    var horloge = null;

    function afficherHorloge(item) {
        // On part toujours de midi : une position neutre, jamais la reponse ni
        // un hasard qui pourrait tomber juste tout seul.
        horloge = { heures: 12, minutes: 0, aiguille: 'heures' };
        rendreHorloge();
    }

    /* Un point du cadran, en coordonnees SVG : centre (100,100). Les angles
       partent de midi et tournent dans le sens des aiguilles, d'ou le -90. */
    function pointHorloge(angleDegres, rayon) {
        var a = (angleDegres - 90) * Math.PI / 180;
        return { x: 100 + rayon * Math.cos(a), y: 100 + rayon * Math.sin(a) };
    }

    function svgHorloge() {
        var parties = [];
        var i, interne, externe, p;
        for (i = 0; i < 60; i++) {
            externe = pointHorloge(i * 6, 88);
            interne = pointHorloge(i * 6, i % 5 === 0 ? 78 : 83);
            parties.push('<line class="fg-horloge-graduation' + (i % 5 === 0 ? ' est-heure' : '')
                + '" x1="' + interne.x.toFixed(1) + '" y1="' + interne.y.toFixed(1)
                + '" x2="' + externe.x.toFixed(1) + '" y2="' + externe.y.toFixed(1) + '"/>');
        }
        for (i = 1; i <= 12; i++) {
            p = pointHorloge(i * 30, 64);
            parties.push('<text class="fg-horloge-chiffre" x="' + p.x.toFixed(1)
                + '" y="' + p.y.toFixed(1) + '">' + i + '</text>');
        }
        // La grande aiguille est dessinee AVANT la petite : quand les deux se
        // superposent (a midi pile, position de depart), c'est celle des heures
        // qu'on veut voir dessus, sinon le cadran parait vide d'une aiguille.
        var m = pointHorloge(horloge.minutes * 6, 72);
        var h = pointHorloge(horloge.heures * 30, 46);
        parties.push('<line class="fg-horloge-aiguille est-minutes'
            + (horloge.aiguille === 'minutes' ? ' est-active' : '')
            + '" x1="100" y1="100" x2="' + m.x.toFixed(1) + '" y2="' + m.y.toFixed(1) + '"/>');
        parties.push('<line class="fg-horloge-aiguille est-heures'
            + (horloge.aiguille === 'heures' ? ' est-active' : '')
            + '" x1="100" y1="100" x2="' + h.x.toFixed(1) + '" y2="' + h.y.toFixed(1) + '"/>');
        parties.push('<circle class="fg-horloge-axe" cx="100" cy="100" r="5"/>');
        return '<svg id="fg-horloge-cadran" class="fg-horloge-cadran" viewBox="0 0 200 200"'
             + ' tabindex="0" role="application"'
             + ' aria-label="Cadran de l’horloge. Clique pour placer l’aiguille choisie, ou utilise les flèches du clavier.">'
             + '<circle class="fg-horloge-fond" cx="100" cy="100" r="92"/>'
             + parties.join('') + '</svg>';
    }

    function rendreHorloge() {
        reponses.innerHTML = '<div class="fg-horloge">'
            + '<div class="fg-horloge-choix" role="group" aria-label="Aiguille à déplacer">'
            + '<button type="button" class="fg-horloge-bouton" id="fg-horloge-h" aria-pressed="'
            + (horloge.aiguille === 'heures') + '">Petite aiguille<small>les heures</small></button>'
            + '<button type="button" class="fg-horloge-bouton" id="fg-horloge-m" aria-pressed="'
            + (horloge.aiguille === 'minutes') + '">Grande aiguille<small>les minutes</small></button>'
            + '</div>'
            + svgHorloge()
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-horloge-valider">Valider</button>'
            + '</div></div>';

        document.getElementById('fg-horloge-h').addEventListener('click', function () {
            choisirAiguille('heures');
        });
        document.getElementById('fg-horloge-m').addEventListener('click', function () {
            choisirAiguille('minutes');
        });
        document.getElementById('fg-horloge-valider').addEventListener('click', validerHorloge);

        var cadran = document.getElementById('fg-horloge-cadran');
        cadran.addEventListener('pointerdown', function (e) { poserDepuisPointeur(e, cadran); });
        cadran.addEventListener('pointermove', function (e) {
            // Seulement si le doigt ou la souris est enfonce : on tourne
            // l'aiguille en la trainant, comme on regle une vraie pendule.
            if (e.buttons === 1 || e.pressure > 0) { poserDepuisPointeur(e, cadran); }
        });
        cadran.addEventListener('keydown', function (e) {
            var pas = e.key === 'ArrowRight' || e.key === 'ArrowUp' ? 1
                : (e.key === 'ArrowLeft' || e.key === 'ArrowDown' ? -1 : 0);
            if (pas === 0) { return; }
            e.preventDefault();
            if (horloge.aiguille === 'heures') {
                horloge.heures = ((horloge.heures - 1 + pas + 12) % 12) + 1;
            } else {
                horloge.minutes = (horloge.minutes + pas * 5 + 60) % 60;
            }
            rendreHorloge();
            document.getElementById('fg-horloge-cadran').focus();
        });
    }

    function choisirAiguille(laquelle) {
        if (verrouille) { return; }
        horloge.aiguille = laquelle;
        rendreHorloge();
        document.getElementById(laquelle === 'heures' ? 'fg-horloge-h' : 'fg-horloge-m').focus();
    }

    /* L'angle se calcule sur le RECTANGLE du cadran a l'ecran, pas en unites
       SVG : le SVG est carre et mis a l'echelle uniformement, l'angle est donc
       le meme dans les deux reperes. Ca evite toute conversion de coordonnees. */
    function poserDepuisPointeur(e, cadran) {
        if (verrouille) { return; }
        var rect = cadran.getBoundingClientRect();
        var dx = e.clientX - (rect.left + rect.width / 2);
        var dy = e.clientY - (rect.top + rect.height / 2);
        if (dx === 0 && dy === 0) { return; }
        var angle = (Math.atan2(dy, dx) * 180 / Math.PI + 90 + 360) % 360;
        if (horloge.aiguille === 'heures') {
            var heure = Math.round(angle / 30) % 12;
            horloge.heures = heure === 0 ? 12 : heure;
        } else {
            // Cinq en cinq : c'est la graduation que l'eleve sait lire, et la
            // seule que le generateur demande (voir includes/horloge.php).
            horloge.minutes = (Math.round(angle / 30) * 5) % 60;
        }
        rendreHorloge();
    }

    function validerHorloge() {
        if (verrouille) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch('/v2/fastgames/api/horloge.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                heures: horloge.heures,
                minutes: horloge.minutes
            })
        }).then(lireCorrection).then(function (correction) {
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* --------------------------------------------------- le tri alphabetique */
    /* Cinq mots a ranger dans une rangee de cinq emplacements, dans l'ordre
       alphabetique. Le geste est le meme que poser des etiquettes-mots sur une
       table, dans l'ordre choisi : glisser un mot vers la rangee - peu importe
       a quel endroit precis on le lache - le fait entrer dans le PREMIER
       emplacement encore vide, exactement comme un clic sur ce mot. Viser un
       pixel n'ajoute rien pour un CE1 ; pouvoir se tromper et retirer un mot
       pose, si. */
    var ordre = null;

    function afficherOrdre(item) {
        ordre = {
            motsDisponibles: item.mots_a_ranger.map(function (mot, i) { return {mot: mot, index: i, placee: false}; }),
            emplacements: item.mots_a_ranger.map(function () { return null; })
        };
        rendreOrdre();
    }

    var RANGS_ORDRE = ['1er', '2e', '3e', '4e', '5e', '6e', '7e', '8e'];

    function rendreOrdre() {
        var complet = ordre.emplacements.every(function (i) { return i !== null; });
        var unMotPlace = ordre.motsDisponibles.some(function (e) { return e.placee; });

        var html = '<div class="fg-ordre-emplacements" id="fg-ordre-emplacements" data-focus-groupe="emplacements">';
        ordre.emplacements.forEach(function (indexMot, position) {
            html += '<div class="fg-ordre-emplacement' + (indexMot === null ? ' est-vide' : '') + '" data-position="' + position + '">'
                  + '<span class="fg-ordre-rang">' + (RANGS_ORDRE[position] || (position + 1) + 'e') + '</span>';
            if (indexMot !== null) {
                html += '<button type="button" class="fg-ordre-mot fg-ordre-mot-pose" data-index="' + indexMot + '" data-focus-cle="' + indexMot + '" data-focus-apres="banc" title="Retirer ce mot">'
                      + ordre.motsDisponibles[indexMot].mot + '</button>';
            } else {
                html += '<span class="fg-ordre-mot-attendu" aria-hidden="true">?</span>';
            }
            html += '</div>';
        });
        html += '</div>';

        html += '<div class="fg-ordre-banc" id="fg-ordre-banc" data-focus-groupe="banc">';
        if (!ordre.motsDisponibles.some(function (e) { return !e.placee; })) {
            html += '<span class="fg-ordre-banc-vide">Tous les mots sont posés.</span>';
        }
        ordre.motsDisponibles.forEach(function (entree) {
            if (entree.placee) { return; }
            html += '<button type="button" class="fg-ordre-mot" draggable="true" data-index="' + entree.index + '" data-focus-cle="' + entree.index + '">' + entree.mot + '</button>';
        });
        html += '</div>';

        html += '<div class="fg-compte-actions">'
              + '<button type="button" class="fg-bouton fg-bouton-secondaire" id="fg-ordre-effacer"' + (unMotPlace ? '' : ' disabled') + '>↺ Recommencer</button>'
              + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-ordre-valider" data-focus-defaut' + (complet ? '' : ' disabled') + '>Valider</button>'
              + '</div>';
        fgReconstruire(reponses, html);

        reponses.querySelectorAll('#fg-ordre-banc .fg-ordre-mot').forEach(function (bouton) {
            var i = parseInt(bouton.dataset.index, 10);
            bouton.addEventListener('click', function () { poserMotOrdre(i); });
            bouton.addEventListener('dragstart', function (e) {
                e.dataTransfer.setData('text/plain', String(i));
                e.dataTransfer.effectAllowed = 'move';
                bouton.classList.add('est-portee');
            });
            bouton.addEventListener('dragend', function () { bouton.classList.remove('est-portee'); });
        });

        reponses.querySelectorAll('#fg-ordre-emplacements .fg-ordre-mot-pose').forEach(function (bouton) {
            var i = parseInt(bouton.dataset.index, 10);
            bouton.addEventListener('click', function () { retirerMotOrdre(i); });
        });

        var rangee = document.getElementById('fg-ordre-emplacements');
        if (rangee) {
            rangee.addEventListener('dragover', function (e) {
                e.preventDefault();
                rangee.classList.add('est-survole');
            });
            rangee.addEventListener('dragleave', function () { rangee.classList.remove('est-survole'); });
            rangee.addEventListener('drop', function (e) {
                e.preventDefault();
                rangee.classList.remove('est-survole');
                var i = parseInt(e.dataTransfer.getData('text/plain'), 10);
                if (!isNaN(i)) { poserMotOrdre(i); }
            });
        }

        var effacer = document.getElementById('fg-ordre-effacer');
        if (effacer) { effacer.addEventListener('click', reinitialiserOrdre); }
        var valider = document.getElementById('fg-ordre-valider');
        if (valider) { valider.addEventListener('click', validerOrdre); }
    }

    /* Pose un mot du banc dans le premier emplacement libre - qu'il soit
       arrive par un clic ou par un glisser-depose sur la rangee. */
    function poserMotOrdre(i) {
        if (verrouille) { return; }
        var entree = ordre.motsDisponibles[i];
        if (!entree || entree.placee) { return; }
        var position = ordre.emplacements.indexOf(null);
        if (position === -1) { return; }
        entree.placee = true;
        ordre.emplacements[position] = i;
        rendreOrdre();
    }

    /* Reprend un mot deja pose : sa position redevient libre, sans decaler
       les autres - une erreur se corrige sans tout recommencer. */
    function retirerMotOrdre(i) {
        if (verrouille) { return; }
        var entree = ordre.motsDisponibles[i];
        if (!entree || !entree.placee) { return; }
        entree.placee = false;
        var position = ordre.emplacements.indexOf(i);
        if (position !== -1) { ordre.emplacements[position] = null; }
        rendreOrdre();
    }

    function reinitialiserOrdre() {
        if (verrouille) { return; }
        ordre.motsDisponibles.forEach(function (e) { e.placee = false; });
        ordre.emplacements = ordre.emplacements.map(function () { return null; });
        rendreOrdre();
    }

    function validerOrdre() {
        if (verrouille || ordre.emplacements.some(function (i) { return i === null; })) { return; }
        verrouille = true;
        reponses.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        var motsEnvoyes = ordre.emplacements.map(function (i) { return ordre.motsDisponibles[i].mot; });

        fetch('/v2/fastgames/api/ordre.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                ordre: motsEnvoyes
            })
        }).then(lireCorrection).then(function (correction) {
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* --------------------------------------------------------- le loto des sons */
    /* Un seul element d'etat : le nom du fichier a ecouter. La reponse elle-meme
       vit dans le champ texte du DOM, pas dupliquee en JS - il n'y a rien a
       animer ni a recalculer avant l'envoi, contrairement au compte est bon ou
       a la boutique. */
    var ecoute = null;

    function afficherEcoute(item) {
        ecoute = { fichier: item.fichier };
        rendreEcoute();
    }

    function rendreEcoute() {
        var html = '<div class="fg-ecoute">'
            + '<audio id="fg-ecoute-audio" preload="none" src="/v2/fastgames/assets/son/' + encodeURIComponent(ecoute.fichier) + '.mp3"></audio>'
            + '<button type="button" class="fg-ecoute-jouer" id="fg-ecoute-jouer">'
            + '<span aria-hidden="true">' + fgIconeSVG('ecoute', 22) + '</span> Écouter le son</button>'
            + '<input type="text" class="fg-ecoute-champ" id="fg-ecoute-champ" aria-label="Écris ce que tu entends" placeholder="Tape ce que tu entends" '
            + 'autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">'
            + '<div class="fg-compte-actions">'
            + '<button type="button" class="fg-bouton fg-bouton-principal" id="fg-ecoute-valider" disabled>Valider</button>'
            + '</div></div>';
        reponses.innerHTML = html;

        var audio = document.getElementById('fg-ecoute-audio');
        var jouer = document.getElementById('fg-ecoute-jouer');
        jouer.addEventListener('click', function () {
            audio.currentTime = 0;
            audio.play();
        });

        var champ = document.getElementById('fg-ecoute-champ');
        var valider = document.getElementById('fg-ecoute-valider');
        champ.addEventListener('input', function () {
            valider.disabled = champ.value.trim() === '';
        });
        champ.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !valider.disabled) {
                validerEcoute();
            }
        });
        valider.addEventListener('click', validerEcoute);

        /* Pas de lecture automatique, meme si l'eleve vient de cliquer
           « Question suivante » et qu'un geste recent l'autoriserait dans la
           plupart des navigateurs : le jeu d'origine ne joue jamais de son
           sans un clic explicite sur le phonographe, et un son qui demarre
           sans prevenir dans une salle de classe pleine de tablettes n'est
           pas souhaitable. */
        champ.focus();
    }

    function validerEcoute() {
        var champ = document.getElementById('fg-ecoute-champ');
        var texte = champ ? champ.value.trim() : '';
        if (verrouille || texte === '') {
            return;
        }
        verrouille = true;
        reponses.querySelectorAll('button, input').forEach(function (el) { el.disabled = true; });

        fetch('/v2/fastgames/api/ecoute.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                reponse: texte
            })
        }).then(lireCorrection).then(function (correction) {
            afficherCorrectionCompte(correction);
        }).catch(function (erreur) {
            echecEnvoi(erreur);
        });
    }

    /* LIRE UNE CORRECTION, ET SAVOIR SI LA PARTIE EXISTE ENCORE.

       Le code HTTP disparaissait des qu'on passait a reponse.json() : les
       seize points d'entree ne pouvaient plus distinguer « le reseau a
       hoquete » de « cette partie n'existe plus ». Le 409 est le second cas,
       et lui seul justifie de proposer une relance plutot que de rendre la
       main aux boutons. */
    function lireCorrection(reponse) {
        var perdue = reponse.status === 409;
        return reponse.json().catch(function () { return null; }).then(function (correction) {
            if (!correction || !correction.ok) {
                var erreur = new Error(correction && correction.erreur ? correction.erreur : 'Correction indisponible');
                erreur.partiePerdue = perdue;
                throw erreur;
            }
            return correction;
        });
    }

    /* UN SEUL TRAITEMENT D'ECHEC D'ENVOI, pour les seize points d'entree.
       Il vivait en seize copies, a un selecteur pres ; corriger le message
       demandait donc seize retouches, et personne ne les faisait toutes.

       LE 409 N'EST PAS UN INCIDENT PASSAGER : la partie n'existe plus cote
       serveur - elle a expire au bout d'une heure, ou une autre partie a pris
       sa place. Reactiver les boutons ne menait alors qu'a un nouveau 409, et
       l'eleve tournait en rond sans porte de sortie (FG-AUDIT-028). Dans ce
       cas on propose de relancer, au lieu de rendre la main a des boutons qui
       ne servent plus. */
    function echecEnvoi(erreur) {
        retour.hidden = false;
        retour.classList.remove('est-juste', 'est-faux');
        retour.innerHTML = '';
        if (erreur && erreur.partiePerdue) {
            var perdu = document.createElement('div');
            var texte = document.createElement('strong');
            texte.textContent = erreur.message;
            perdu.appendChild(texte);
            retour.appendChild(perdu);
            var relancer = document.createElement('a');
            relancer.className = 'fg-bouton fg-bouton-principal';
            relancer.href = racine.dataset.rejouer || 'catalogue.php';
            relancer.textContent = 'Recommencer cette activité →';
            retour.appendChild(relancer);
            relancer.focus();
            return;
        }
        reponses.querySelectorAll('button, input').forEach(function (el) { el.disabled = false; });
        verrouille = false;
        retour.textContent = erreur.message + ' Réessaie dans un instant.';
    }

    /* La correction vient du serveur, qui seul connait la bonne reponse : elle
       n'est plus dans la page. Le choix part d'abord, la correction revient
       ensuite - c'est ce qui empeche de lire les reponses avant de repondre. */
    function choisir(numero, boutonChoisi) {
        if (verrouille) {
            return;
        }
        verrouille = true;
        var boutons = reponses.querySelectorAll('.fg-reponse');
        boutons.forEach(function (bouton) { bouton.disabled = true; });

        fetch('/v2/fastgames/api/reponse.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance,
                question: index,
                choix: numero
            })
        }).then(lireCorrection).then(function (correction) {
            afficherCorrection(correction, numero, boutonChoisi, boutons);
        }).catch(function (erreur) {
            /* Sans correction, on ne devine pas : on rend la main plutot que de
               laisser l'eleve devant une question figee. */
            echecEnvoi(erreur);
        });
    }

    function afficherCorrection(correction, numero, boutonChoisi, boutons) {
        function etiquette(bouton, texte, icone) {
            var zone = bouton.querySelector('.fg-correction-etiquettes');
            if (!zone) {
                zone = document.createElement('div');
                zone.className = 'fg-correction-etiquettes';
                bouton.appendChild(zone);
            }
            var libelle = document.createElement('span');
            libelle.className = 'fg-correction-etiquette';
            libelle.innerHTML = fgIconeSVG(icone, 18) + texte;
            zone.appendChild(libelle);
        }
        boutons.forEach(function (bouton, position) {
            if (position === correction.bonne) {
                bouton.classList.add('est-juste');
                etiquette(bouton, 'Bonne réponse', 'juste');
            }
        });
        etiquette(boutonChoisi, 'Ta réponse', correction.juste ? 'juste' : 'faux');
        if (!correction.juste) {
            boutonChoisi.classList.add('est-faux');
        } else {
            score += 1;
        }

        retour.hidden = false;
        retour.classList.remove('est-juste', 'est-faux');
        retour.classList.add(correction.juste ? 'est-juste' : 'est-faux');
        /* « tu progresses » sous-entend qu'il s'est deja passe quelque chose,
           ce qui est faux a la toute premiere question. La phrase de mauvaise
           reponse ne parle donc plus du temps qui passe, seulement de ce qui
           suit : l'explication juste en dessous. */
        retour.innerHTML = '<div><strong>' + (correction.juste ? 'Bravo, c’est juste !' : 'Pas tout à fait, regarde pourquoi.') + '</strong><p></p></div><button type="button" class="fg-bouton fg-bouton-principal"></button>';
        retour.querySelector('p').textContent = correction.explication;
        var suite = retour.querySelector('button');
        suite.textContent = index + 1 < questions.length ? 'Question suivante →' : 'Voir mon résultat →';
        suite.addEventListener('click', function () {
            if (index + 1 < questions.length) {
                index += 1;
                afficherQuestion();
                question.focus();
            } else {
                terminer();
            }
        });
        suite.focus();
    }

    function phraseResultat(score, total) {
        return score + ' bonne' + (score > 1 ? 's' : '') + ' réponse' + (score > 1 ? 's' : '') + ' sur ' + total;
    }

    function terminer() {
        var total = questions.length;
        racine.classList.add('est-termine');
        racine.innerHTML = '<section class="fg-carte fg-resultat"><span class="fg-resultat-etoile" aria-hidden="true">' + fgIconeSVG('etoile', 40) + '</span><span class="fg-surtitre">Bien joué !</span><h1 tabindex="-1"></h1><p>Tu peux recommencer pour consolider, ou choisir une nouvelle activité.</p><div class="fg-resultat-actions"><a class="fg-bouton fg-bouton-principal" href="#">Rejouer</a><a class="fg-bouton fg-bouton-secondaire" href="#">Choisir un autre jeu</a></div><small id="fg-enregistrement" role="status">Enregistrement de la progression…</small></section>';
        racine.querySelector('h1').textContent = phraseResultat(score, total);
        racine.querySelector('h1').focus();
        racine.querySelector('.fg-resultat-actions a:first-child').href = racine.dataset.rejouer || 'catalogue.php';
        /* Le meme retour que « Quitter l'activite », calcule cote serveur. La
           page posait ici « catalogue.php?programme=... », un parametre que le
           catalogue ne lit pas (FG-AUDIT-025). */
        racine.querySelector('.fg-resultat-actions a:last-child').href = racine.dataset.retour || 'catalogue.php';
        enregistrer(total);
    }

    function enregistrer(total) {
        var etat = document.getElementById('fg-enregistrement');
        fetch('/v2/fastgames/api/resultat.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            /* Plus rien a declarer : le serveur tient la seance, connait les
               choix envoyes un par un et recompte lui-meme. */
            body: JSON.stringify({
                csrf: racine.dataset.csrf,
                seance: racine.dataset.seance
            })
        }).then(function (reponse) {
            if (!reponse.ok) {
                throw new Error('Réponse invalide');
            }
            return reponse.json();
        }).then(function (donneesResultat) {
            /* Le score du serveur fait foi si l'estimation locale diverge. */
            if (typeof donneesResultat.score === 'number') {
                var titreNoeud = racine.querySelector('h1');
                if (titreNoeud) {
                    titreNoeud.textContent = phraseResultat(donneesResultat.score, donneesResultat.total);
                }
            }
            etat.textContent = racine.dataset.apercu === '1'
                ? 'Aperçu terminé : ce résultat enseignant n’entre pas dans les statistiques.'
                : (donneesResultat.persistant ? 'Résultat enregistré dans ton suivi.' : 'Résultat conservé pour cette session.');
            if (racine.dataset.apercu !== '1' && donneesResultat.persistant === true) {
                var lienPasseport = document.createElement('a');
                lienPasseport.className = 'fg-bouton fg-bouton-secondaire';
                lienPasseport.href = 'passeport.php';
                lienPasseport.textContent = 'Voir mon passeport';
                racine.querySelector('.fg-resultat-actions').appendChild(lienPasseport);
            }
        }).catch(function () {
            etat.setAttribute('role', 'alert');
            etat.textContent = 'Le résultat n’a pas pu être enregistré. Tu peux quand même continuer.';
        });
    }

    /* Une partie reprise continue a la question que le serveur attend : repartir
       de la premiere faisait refuser chaque reponse. */
    var deja = parseInt(racine.dataset.deja, 10) || 0;
    if (deja > 0 && deja < questions.length) {
        index = deja;
        score = parseInt(racine.dataset.score, 10) || 0;
    }

    if (questions.length) {
        afficherQuestion();
    }
}());
