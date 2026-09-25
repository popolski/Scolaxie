<?php

$dependencyDir = getenv('SCOLAXIE_TFPDF_DIR') ?: __DIR__;
require_once $dependencyDir.'/tfpdf.php';

/** Contrats documentaires D01-D08, indépendants des contrats des interfaces HTML. */
class PdfDocumentScolaxie extends tFPDF
{
    const TURQUOISE = array(11,117,106);
    const TURQUOISE_FONCE = array(9,95,87);
    const TURQUOISE_PALE = array(205,229,225);
    const CREME = array(252,250,240);
    const SABLE = array(217,212,187);
    const SABLE_VOILE = array(230,224,186);
    const BLANC = array(255,255,255);
    const TEXTE = array(48,52,61);
    const GRIS = array(95,99,107);
    const BORDURE = array(170,162,124);
    const SEPARATION = array(158,149,110);
    protected $docBodyTop = 52;
    protected $hauteurIdentite = 22;

    public function __construct($orientation = 'L')
    {
        parent::__construct($orientation, 'mm', 'A4');
        $this->SetMargins(8, 10, 8);
        $this->SetAutoPageBreak(false, 17);
        // Les coordonnées de chaque primitive comprennent leur padding ; aucun mm implicite.
        $this->cMargin = 0;
    }

    public function chargerPoliceDocumentaire()
    {
        $niveau = error_reporting();
        error_reporting($niveau & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        ob_start();
        try {
            $this->AddFont('Lexend', '', 'Lexend-Regular.ttf', true);
            $this->AddFont('Lexend', 'B', 'Lexend-SemiBold.ttf', true);
        } finally {
            ob_end_clean();
            error_reporting($niveau);
        }
    }

    public function lignes($largeur, $texte)
    {
        $resultat = array();
        foreach (explode("\n", str_replace("\r", '', (string)$texte)) as $paragraphe) {
            $ligne = '';
            foreach (preg_split('//u', $paragraphe, -1, PREG_SPLIT_NO_EMPTY) as $lettre) {
                if ($ligne !== '' && $this->GetStringWidth($ligne.$lettre) > $largeur - 2*$this->cMargin) {
                    $espace = mb_strrpos($ligne, ' ');
                    $resultat[] = $espace === false ? $ligne : mb_substr($ligne, 0, $espace);
                    $ligne = $espace === false ? '' : mb_substr($ligne, $espace + 1);
                }
                $ligne .= $lettre;
            }
            $resultat[] = $ligne;
        }
        return $resultat;
    }

    protected function numberOfLines($largeur, $texte) { return count($this->lignes($largeur, $texte)); }

    protected function itemHeight(array $item)
    {
        $this->SetFont('Lexend', '', 9.5);
        return max(13.5, $this->numberOfLines(167, $item['comment'])*4.9+5, count($item['rows'])*9+3);
    }

    protected function fragmenterItems(array $items, $capacite)
    {
        $this->SetFont('Lexend', '', 9.5);
        $maxLignes = (int)floor(($capacite - 5) / 4.9);
        $maxSaisies = (int)floor(($capacite - 3) / 9);
        if ($maxLignes < 1 || $maxSaisies < 1) { throw new RuntimeException('En-tête documentaire trop haut.'); }
        $fragments = array();
        foreach ($items as $item) {
            $lignes = $this->lignes(167, $item['comment']);
            $nombre = max((int)ceil(count($lignes)/$maxLignes), (int)ceil(count($item['rows'])/$maxSaisies));
            if ($nombre === 1) { $fragments[] = $item; continue; }
            for ($i = 0; $i < $nombre; $i++) {
                $fragment = $item;
                $fragment['comment'] = implode("\n", array_slice($lignes, $i*$maxLignes, $maxLignes));
                $fragment['rows'] = array_slice($item['rows'], $i*$maxSaisies, $maxSaisies);
                $fragments[] = $fragment;
            }
        }
        return $fragments;
    }

    protected function lignesIdentite(array $textes)
    {
        $resultat = array();
        foreach ($textes as $texte) {
            $taille = 9;
            do {
                $this->SetFont('Lexend', '', $taille);
                if ($this->GetStringWidth($texte) <= 80 || $taille <= 7) break;
                $taille -= .25;
            } while (true);
            foreach ($this->lignes(80, $texte) as $ligne) $resultat[] = array($taille, $ligne);
        }
        return $resultat;
    }

    protected function preparerIdentites(array $cartes)
    {
        $this->hauteurIdentite = 22;
        foreach ($cartes as $carte) $this->hauteurIdentite = max($this->hauteurIdentite, 7 + count($this->lignesIdentite($carte))*4.8);
        $this->docBodyTop = 23 + $this->hauteurIdentite + 7;
    }

    protected function drawInfoCard($x, $y, $w, $h, $label, array $lignes, $accent = null)
    {
        $this->SetLineWidth(.25);
        $this->SetFillColor(...self::CREME);
        $this->SetDrawColor(...self::BORDURE);
        if ($accent === null) $this->Rect($x, $y, $w, $h, 'DF');
        else $this->RoundedBox($x, $y, $w, $h, 2.4, 'DF');
        $this->SetFillColor(...($accent ?? self::TURQUOISE));
        $this->Rect($x, $y+2, 2.2, $h-4, 'F');
        $this->SetTextColor(...self::GRIS);
        $this->SetFont('Lexend', 'B', 7.6);
        $this->SetXY($x+5, $y+1.3);
        $this->Cell(80, 4, $label);
        $this->SetTextColor(...self::TEXTE);
        foreach ($this->lignesIdentite($lignes) as $i => $ligne) {
            $this->SetFont('Lexend', '', $ligne[0]);
            $this->SetXY($x+5, $y+6.6+$i*4.8);
            $this->Cell(80, 4.5, $ligne[1]);
        }
    }

    protected function enteteBulletin($sousTitre, $periode)
    {
        $this->SetAutoPageBreak(false, 17);
        $logo = __DIR__.'/../img/logofasteval.png';
        if (is_file($logo)) $this->Image($logo, 9, 5.5, 34);
        $this->SetTextColor(...self::TEXTE);
        $this->SetFont('Lexend', 'B', 13);
        $this->SetXY(57, 7.4);
        $this->Cell(232, 7, 'Bilan des acquis scolaires');
        $this->SetTextColor(...self::GRIS);
        $this->SetFont('Lexend', '', 8.5);
        $this->SetXY(57, 15); $this->Cell(112, 5, $sousTitre);
        $this->SetXY(169, 15); $this->Cell(120, 5, $periode, 0, 0, 'R');
        $cartes = array($this->schoolCardLines(), $this->studentCardLines(), $this->followUpCardLines());
        $this->preparerIdentites($cartes);
        $labels = array('ÉTABLISSEMENT', 'ÉLÈVE', 'SUIVI');
        $couleurs = array(self::TURQUOISE, array(91,127,189), array(198,145,54));
        foreach ($cartes as $i => $carte) $this->drawInfoCard(8+95.5*$i, 23, 90, $this->hauteurIdentite, $labels[$i], $carte, $couleurs[$i]);
        $this->SetDrawColor(...self::TURQUOISE); $this->SetLineWidth(.55);
        $this->Line(8, $this->docBodyTop-4, 289, $this->docBodyTop-4);
        $this->SetLineWidth(.25); $this->SetDrawColor(...self::BORDURE);
        $this->SetY($this->docBodyTop);
    }

    public function documentSection($titre)
    {
        $y = $this->GetY();
        $this->SetFillColor(...self::TURQUOISE_PALE);
        $this->RoundedBox(8, $y, 281, 10, 2.4);
        $this->SetFillColor(...self::TURQUOISE); $this->Rect(8, $y+1.4, 1.8, 7.2, 'F');
        $this->SetTextColor(...self::TURQUOISE_FONCE); $this->SetFont('Lexend', 'B', 12);
        $this->SetXY(13, $y); $this->Cell(271, 10, $titre);
        $this->SetXY(8, $y+10);
    }

    public function documentEntetes(array $colonnes, array $labels, array $alignements)
    {
        $y = $this->GetY();
        $this->SetFillColor(...self::SABLE_VOILE); $this->SetDrawColor(...self::BORDURE); $this->SetLineWidth(.25);
        $this->SetTextColor(...self::TEXTE); $this->SetFont('Lexend', 'B', 9.2);
        foreach ($labels as $i => $label) {
            $w = $colonnes[$i+1]-$colonnes[$i];
            $this->Rect($colonnes[$i], $y, $w, 8.5, 'DF');
            $this->SetXY($colonnes[$i]+3, $y);
            $this->Cell($w-6, 8.5, $label, 0, 0, $alignements[$i]);
        }
        $this->SetXY(8, $y+8.5);
    }

    protected function documentObservation($texte)
    {
        $this->SetFont('Lexend', '', 9.5);
        $lignes = $this->lignes(271, trim($texte));
        $suite = false;
        while ($lignes) {
            $place = (int)floor((193-$this->GetY()-9)/4.9);
            if ($place < min(2, count($lignes))) { $this->AddPage(); $this->SetY($this->docBodyTop); $place = (int)floor((193-$this->GetY()-9)/4.9); }
            if ($place < 1) throw new RuntimeException('Observation : aucune ligne ne tient sous l’en-tête.');
            $partie = array_splice($lignes, 0, $place);
            $y = $this->GetY(); $h = max(19, 9+count($partie)*4.9);
            $this->SetFillColor(...self::CREME); $this->SetDrawColor(...self::BORDURE); $this->SetLineWidth(.25);
            $this->RoundedBox(8, $y, 281, $h, 2.4, 'DF');
            $this->SetFillColor(198,145,54); $this->Rect(8, $y+1.4, 1.8, $h-2.8, 'F');
            $this->SetTextColor(115,87,34); $this->SetFont('Lexend', 'B', 8);
            $this->SetXY(13, $y+1.2); $this->Cell(271, 4, "Observation de l'enseignant(e)".($suite ? ' (suite)' : ''));
            $this->SetTextColor(...self::TEXTE); $this->SetFont('Lexend', '', 9.5);
            foreach ($partie as $i => $ligne) { $this->SetXY(13, $y+6.2+$i*4.9); $this->Cell(271, 4.9, $ligne); }
            $this->SetY($y+$h); $suite = true;
        }
    }

    protected function preparerPied()
    {
        $this->SetFont('Lexend', '', 8); $this->SetTextColor(...self::GRIS);
        $this->SetXY(8, 197);
    }

    public function Footer()
    {
        $this->preparerPied();
        $this->Cell(281, 6, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    protected function RoundedBox($x, $y, $w, $h, $r, $style = 'F')
    {
        $k = $this->k;
        $hp = $this->h;
        $c = $r * 0.5523;
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $this->_out(sprintf('%.2F %.2F l', ($x + $w - $r) * $k, ($hp - $y) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $w - $r + $c) * $k, ($hp - $y) * $k,
            ($x + $w) * $k, ($hp - $y - $r + $c) * $k,
            ($x + $w) * $k, ($hp - $y - $r) * $k));
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $y - $h + $r) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $w) * $k, ($hp - $y - $h + $r - $c) * $k,
            ($x + $w - $r + $c) * $k, ($hp - $y - $h) * $k,
            ($x + $w - $r) * $k, ($hp - $y - $h) * $k));
        $this->_out(sprintf('%.2F %.2F l', ($x + $r) * $k, ($hp - $y - $h) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $r - $c) * $k, ($hp - $y - $h) * $k,
            $x * $k, ($hp - $y - $h + $r - $c) * $k,
            $x * $k, ($hp - $y - $h + $r) * $k));
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $y - $r) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x * $k, ($hp - $y - $r + $c) * $k,
            ($x + $r - $c) * $k, ($hp - $y) * $k,
            ($x + $r) * $k, ($hp - $y) * $k));
        $op = 'f';
        if ($style === 'D') { $op = 'S'; }
        elseif ($style === 'DF' || $style === 'FD') { $op = 'B'; }
        $this->_out($op);
    }
}
