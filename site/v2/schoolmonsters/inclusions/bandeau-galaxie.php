<?php
// Le bandeau commun aux lecons est factorise ici : les destinations sont
// deduites du chemin de la fiche, afin qu'aucune lecon ne conserve une
// variante locale du bouton Portail ou des liens de navigation.
//
// A inclure avant container_12 : place a l'interieur, il deviendrait le
// premier enfant et casserait les selecteurs historiques du cartouche titre.
$gxChemin = str_replace('\\','/',substr($_SERVER['SCRIPT_FILENAME'],strlen(dirname(__DIR__))+1));
$gxSegments = explode('/',$gxChemin);
$gxPeriode = $gxSegments[0];
$gxMatiere = $gxSegments[1];
$gxRetour = str_repeat('../',count($gxSegments)-1);
?>
       <a class="skip-link" href="#contenu">Aller au contenu</a>
       <header class="gx-entete gx-entete-cadree">
           <img class="gx-entete-logo" src="<?php echo $gxRetour; ?>img-140/logo_school_monsters.png?v=20260906-01" alt="School Monsters">
           <nav class="fil" aria-label="Fil d'Ariane">
               <a href="/portail/">Portail</a><span aria-hidden="true">›</span>
               <a href="<?php echo $gxRetour; ?>acceuil.php">Les périodes</a><span aria-hidden="true">›</span>
               <a href="<?php echo htmlspecialchars($gxRetour.$gxPeriode.'.php'); ?>">La période</a><span aria-hidden="true">›</span>
               <a href="<?php echo htmlspecialchars($gxRetour.$gxPeriode.'/'.$gxPeriode.'_'.$gxMatiere.'.php'); ?>">La matière</a><span aria-hidden="true">›</span>
               <span class="actuel"><?php echo htmlspecialchars(ucfirst($gxMatiere)); ?></span>
           </nav>
           <?php if(isset($_SESSION['prenom'])){ ?>
           <?php
           require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
           echo gxMenuIdentite('School Monsters');
           ?>
           <?php } ?>
       </header>

<script src="<?php echo $gxRetour; ?>javascript/titre-pedagogique.js?v=20260912-conformite" defer></script>
