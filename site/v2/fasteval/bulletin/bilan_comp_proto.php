<?php
session_start();

// Meme garde-fou que les autres ecrans enseignants : sans session valide, la
// page rendait un bulletin vide a n'importe quel visiteur.
if (empty($_SESSION['permission'])) {
    header('location:../index.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'enseignant') {
    header('location:../presentation.php');
    exit();
}

require('../utils/class/class_bdd.php');
require('../utils/class/class_requete1.php');
if (isset($_SESSION['modele_bulletin']) && $_SESSION['modele_bulletin'] === 'charte') {
    require('../utils/class/pdf_bilan_comp_charte.php');
    $pdf = new PdfBilanCompCharte('l');
} else {
    require('../utils/class/pdf_bilan_comp_proto.php');
    $pdf = new PdfBilanCompProto('l');
}

$infoEleve=new Requete1($_SESSION,bdd::connexion($_SESSION['bdd']));
$pdf->chargerPoliceDocumentaire();
$pdf->addInfo($infoEleve);
$pdf->observations=isset($_POST['observations'])?$_POST['observations']:'';
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->bulletin();
$pdf->Output('i','fasteval-competences-'.$infoEleve->eleve('nom').'-'.$infoEleve->periode('fin').'.pdf',true);
