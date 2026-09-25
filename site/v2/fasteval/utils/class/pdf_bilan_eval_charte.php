<?php

// Référence documentaire canonique D01-D08.
require_once __DIR__.'/pdf_bilan_eval_proto.php';

class PdfBilanEvalCharte extends PdfBilanEvalProto
{

    public function __construct($orientation = 'P')
    {
        parent::__construct($orientation);
        $this->charteEnhanced = true;
    }

    protected function followUpCardLines()
    {
        return array(
            'Enseignant(e) : '.$_SESSION['nom_client'].' '.$_SESSION['prenom_client'],
            'Année scolaire : '.$this->anneeScolaireLocal(),
            'Évaluations sur la période',
        );
    }

    public function Header()
    {
        $this->enteteBulletin('Bulletin de connaissances · Année scolaire '.$this->anneeScolaireLocal(), 'Période du '.$this->debut_perio_fr.' au '.$this->fin_perio_fr);
    }

    protected function renderTableHeader()
    {
        $y = $this->docBodyTop;
        $top = $y;
        $this->currentFrameTop = $top;
        $this->SetY($y);

        $this->documentSection($this->currentSubject);
        if ($this->currentAverage !== null && $this->currentAverage !== '') {
            // Meme couleur que les notes de la saisie, demande de le responsable technique le
            // 07/09/2026 : la moyenne dit deja son niveau, pas juste un chiffre.
            $this->SetTextColor(...$this->noteColor((float)$this->currentAverage));
            $this->SetFont('Lexend', 'B', 9);
            $this->SetXY(143, $y);
            $this->Cell(141, 10, versWinAnsi('Moyenne de la période : '.$this->formatAverage($this->currentAverage).' / 10'), 0, 0, 'R');
        }
        $this->SetY($y + 10);

        if ($this->currentObservation !== '') { $this->documentObservation($this->currentObservation); }

        $this->documentEntetes($this->columns, array('Domaine', 'Connaissance évaluée', 'Résultat', 'Date'), array('L', 'L', 'C', 'C'));
        $this->tableStartY = $this->GetY();
    }

    // Cadre exterieur arrondi de tout le tableau (bandeau + lignes), demande
    // de le responsable technique le 07/09/2026 : meme rayon (2.4) que le bandeau et les cartes
    // ETABLISSEMENT/ELEVE/SUIVI, pour que le tableau se referme comme elles.
    // Le sommet suit deja la courbe du bandeau (RoundedBox dans
    // renderTableHeader) : ce cadre trace le meme rayon au meme endroit, donc
    // rien ne depasse en haut. En bas, les dernieres cellules (Rect carre)
    // restent carrees - un tres leger residu carre peut affleurer sous le
    // rayon, accepte plutot que de complexifier chaque cellule de donnees.
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
