<?php
// Ouvre la capture de la liste des lecons d'une page de matiere.
//
// La page garde son HTML ecrit a la main : c'est lui la source, et il reste
// lisible. On le capture ici, et liste-lecons-fin.php le reemet, eventuellement
// reordonne, renomme ou allege selon les reglages faits dans l'editeur.
//
// Sans fichier de reglages, la fin reemet le tampon TEL QUEL : la page rend
// alors exactement ce qu'elle rendait avant, a l'octet pres.
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(404);
    exit();
}
?>
<script src="../javascript/titre-pedagogique.js?v=20260912-conformite" defer></script>
<?php
$GLOBALS['smListeCapture'] = true;
ob_start();
