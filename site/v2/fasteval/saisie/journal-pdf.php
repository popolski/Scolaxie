<?php

session_start();
require __DIR__.'/utils/journal-donnees.php';
require_once __DIR__.'/../utils/class/pdf_document_scolaxie.php';
require_once __DIR__.'/../utils/class/font/unifont/ttfonts.php';

// tFPDF utilise encore des propriétés dynamiques ; un avis PHP 8.2 corrompt le flux PDF.
$niveauErreurs = error_reporting();
error_reporting($niveauErreurs & ~E_DEPRECATED);

$moisFr = array(1=>'janvier', 2=>'février', 3=>'mars', 4=>'avril', 5=>'mai', 6=>'juin', 7=>'juillet', 8=>'août', 9=>'septembre', 10=>'octobre', 11=>'novembre', 12=>'décembre');
$semaineFr = array('dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi');
$dateLongue = $semaineFr[(int)date('w')].' '.date('d').' '.$moisFr[(int)date('n')].' '.date('Y').' à '.date('H').' h '.date('i');

class JournalPDF extends PdfDocumentScolaxie
{
    public $dateLongue = '';
    public $section = null;
    public $debutTable = 0;

    public function Header()
    {
        $this->SetAutoPageBreak(false, 17);
        $this->SetTextColor(...self::TEXTE);
        $this->SetFont('Lexend', 'B', 13);
        $this->SetXY(8, 10); $this->Cell(281, 7, 'Fast Éval · Journal des saisies');
        $this->SetTextColor(...self::GRIS); $this->SetFont('Lexend', '', 8.5);
        $this->SetXY(8, 17); $this->Cell(281, 5, 'Saisies du '.$this->dateLongue);
        $this->SetDrawColor(...self::TURQUOISE); $this->SetLineWidth(.55);
        $this->Line(8, 23, 289, 23);
        $this->SetLineWidth(.25); $this->SetDrawColor(...self::BORDURE);
        $this->SetXY(8, 27);
        if ($this->section) ajouterTitreSection($this, $this->section[0], $this->section[1], $this->section[2], true);
    }

    public function placeDisponible() { return 193 - $this->GetY(); }
}

function ajouterTitreSection($pdf, $titre, $nombre, $type, $suite = false)
{
    $pdf->section = array($titre, $nombre, $type);
    // D08 : le bandeau et l'en-tête emportent au moins le premier fragment.
    if ($pdf->placeDisponible() < 18.5 + 8.9) { $pdf->AddPage(); return; }
    $pdf->documentSection($titre.' : '.$nombre.($suite ? ' (suite)' : ''));
    $pdf->documentEntetes(array(8,38,68,269,289), array('Nom','Prénom','Intitulé','Résultat'), array('L','L','L','C'));
    $pdf->debutTable = $pdf->GetY();
}

function ajouterLigne($pdf, $ligne, $type)
{
    $resultat = (string)$ligne['resultat'];
    if ($type === 'comp') {
        $sigles = array('nonatteint'=>'NA', 'patteint'=>'PA', 'atteint'=>'A', 'depasse'=>'D');
        $resultat = $sigles[strtolower(trim($resultat))] ?? $resultat;
    }
    $pdf->SetFont('Lexend', '', 9.5);
    $largeurs = array(30,30,201,20);
    $cellules = array_map(fn($w, $texte) => $pdf->lignes($w-6, $texte), $largeurs, array($ligne['nom'],$ligne['prenom'],$ligne['intitule'],$resultat));
    $nombre = max(array_map('count', $cellules));
    $hauteur = $nombre*4.9+4;
    // Une ligne plus haute qu'une page commence ici : la déplacer laisserait un en-tête orphelin.
    if ($hauteur <= 193-$pdf->debutTable && $hauteur > $pdf->placeDisponible()) $pdf->AddPage();
    for ($debut=0; $debut<$nombre;) {
        if ($pdf->placeDisponible() < 8.9) $pdf->AddPage();
        $quantite = min($nombre-$debut, (int)floor(($pdf->placeDisponible()-4)/4.9));
        if ($quantite < 1) throw new RuntimeException('Journal : fragment sans place.');
        $x=8; $y=$pdf->GetY(); $h=$quantite*4.9+4;
        $pdf->SetFont('Lexend', '', 9.5); $pdf->SetTextColor(...PdfDocumentScolaxie::TEXTE);
        $pdf->SetDrawColor(...PdfDocumentScolaxie::BORDURE); $pdf->SetLineWidth(.25);
        foreach ($cellules as $colonne=>$lignes) {
            $pdf->Rect($x,$y,$largeurs[$colonne],$h);
            // Identité et résultat répétés sur les suites pour garder la ligne attribuable.
            $suite = $debut>0 && $colonne!==2 && $debut>=count($lignes);
            foreach (array_slice($lignes,$suite ? 0 : $debut,$quantite) as $i=>$texte) {
                $pdf->SetXY($x+3,$y+2+$i*4.9);
                $pdf->Cell($largeurs[$colonne]-6,4.9,$texte,0,0,$colonne===3 ? 'C' : 'L');
            }
            $x += $largeurs[$colonne];
        }
        $pdf->SetXY(8,$y+$h); $debut += $quantite;
        if ($debut<$nombre) $pdf->AddPage();
    }
}

$pdf = new JournalPDF('L');
$pdf->dateLongue = $dateLongue;
$pdf->chargerPoliceDocumentaire();
$pdf->AliasNbPages();
$pdf->AddPage();

ajouterTitreSection($pdf, 'Connaissances enregistrées', count($journalEval), 'eval');
if (empty($journalEval)) {
    $pdf->SetFont('Lexend', '', 9.5);
    $pdf->Cell(281, 9, 'Aucune connaissance enregistrée aujourd’hui.', 1, 1, 'C');
} else {
    foreach ($journalEval as $ligne) {
        ajouterLigne($pdf, $ligne, 'eval');
    }
}

$pdf->Ln(5);
ajouterTitreSection($pdf, 'Compétences enregistrées', count($journalComp), 'comp');
if (empty($journalComp)) {
    $pdf->SetFont('Lexend', '', 9.5);
    $pdf->Cell(281, 9, 'Aucune compétence enregistrée aujourd’hui.', 1, 1, 'C');
} else {
    foreach ($journalComp as $ligne) {
        ajouterLigne($pdf, $ligne, 'comp');
    }
}

$pdf->Output('I', 'journal-fast-eval-'.date('Y-m-d').'.pdf', true);
error_reporting($niveauErreurs);
