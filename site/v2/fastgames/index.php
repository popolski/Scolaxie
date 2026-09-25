<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (fgEstEnseignant() && !$fgApercuEleve) {
    header('Location: studio.php');
    exit;
}

header('Location: catalogue.php');
exit;
