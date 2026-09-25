<?php
// Bulletin Fast Eval : bilan des évaluations chiffrées.
// C'est désormais le seul. L'ancienne présentation a été archivée le
// 29/08/2026 dans /archives/bulletins-historiques-2026-08-29.
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
require('../utils/class/tfpdf.php');
require('../utils/class/font/unifont/ttfonts.php');
require('../utils/class/calcul_moyennes_eval.php');
require('../utils/class/requete_eval.php');
if (isset($_SESSION['modele_bulletin']) && $_SESSION['modele_bulletin'] === 'charte') {
    require('../utils/class/pdf_bilan_eval_charte.php');
    $pdf = new PdfBilanEvalCharte('l');
} else {
    require('../utils/class/pdf_bilan_eval_proto.php');
    $pdf = new PdfBilanEvalProto('l');
}

$data = new RequeteEval($_SESSION, bdd::connexion($_SESSION['bdd']));

$pdf->chargerPoliceDocumentaire();

$pdf->debut_perio_fr = $data->debut_perio_fr;
$pdf->fin_perio_fr   = $data->fin_perio_fr;
$pdf->debut_perio    = $data->debut_perio;
$pdf->nom            = $data->nom;
$pdf->prenom         = $data->prenom;
$pdf->date_naiss_fr  = $data->date_naiss_fr;

// Observations facultatives (POST ou GET selon le formulaire appelant)
$pdf->obsFr  = isset($_POST['francais']) ? $_POST['francais'] : '';
$pdf->obsMat = isset($_POST['mat'])      ? $_POST['mat']      : '';

$pdf->SetMargins(8, 10, 8);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->bulletin($data->evalFr(), $data->evalMat());

$nom     = $data->nom;
$periode = $data->fin_perio_fr;
$pdf->Output('i', 'fasteval-bulletin-'.$nom.'-'.$periode.'.pdf', true);
