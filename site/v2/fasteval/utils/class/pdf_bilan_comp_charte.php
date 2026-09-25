<?php

// Référence documentaire canonique D01-D08.
require_once __DIR__.'/pdf_bilan_comp_proto.php';

class PdfBilanCompCharte extends PdfBilanCompProto
{

    public function __construct($orientation = 'P')
    {
        parent::__construct($orientation);
        $this->charteEnhanced = true;
    }

    public function Footer()
    {
        $this->preparerPied();
        $text = 'NA : Non Atteint   |   PA : Partiellement Atteint   |   A : Atteint   |   D : Dépassé'
              .'   |   Évaluations = nombre d’évaluations pour cette compétence   |   Page '
              .$this->PageNo().'/{nb}';
        $this->SetX(8);
        $this->Cell(281, 6, versWinAnsi($text), 0, 0, 'C');
    }

    protected function followUpCardLines()
    {
        return array(
            'Enseignant(e) : '.$_SESSION['nom_client'].' '.$_SESSION['prenom_client'],
            'Année scolaire : '.$this->info->anneeScolaire(),
            count($this->info->listeMatiere()).' matière(s) évaluée(s)',
        );
    }

    public function Header()
    {
        $this->enteteBulletin('Bulletin de compétences · Année scolaire '.$this->info->anneeScolaire(), 'Période du '.$this->info->periode('debut').' au '.$this->info->periode('fin'));
    }

    protected function renderTableHeader($depart = 52)
    {
        $y = max($depart, $this->docBodyTop);
        $top = $y;
        $this->currentFrameTop = $top;
        $this->SetY($y);
        $this->documentSection($this->currentSubject);

        $this->documentEntetes($this->columns, array('Domaine', 'Compétence travaillée', 'Niveau', 'Évaluations'), array('L', 'L', 'C', 'C'));
        $this->tableStartY = $this->GetY();
    }

    // Cadre exterieur arrondi de tout le tableau d'une matiere (bandeau +
    // lignes), meme rayon (2.4) que le bandeau et les cartes
    // ETABLISSEMENT/ELEVE/SUIVI. Le sommet suit deja la courbe du bandeau
    // (RoundedBox dans renderTableHeader) : ce cadre trace le meme rayon au
    // meme endroit, rien ne depasse en haut. En bas, les dernieres cellules
    // (Rect carre) restent carrees - meme limite acceptee que sur le bulletin
    // des connaissances.
    protected function closeTableFrame()
    {
        $bottom = $this->GetY();
        if ($bottom <= $this->currentFrameTop) { return; }
        $this->SetDrawColor(...self::BORDURE);
        $this->RoundedBox(
            $this->columns[0],
            $this->currentFrameTop,
            $this->columns[4] - $this->columns[0],
            $bottom - $this->currentFrameTop,
            2.4,
            'D'
        );
        $this->SetDrawColor(...self::BORDURE);
    }

}
