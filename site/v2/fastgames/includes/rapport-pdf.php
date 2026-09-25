<?php
$socle = dirname(__DIR__, 2).'/fasteval/utils/class/pdf_document_scolaxie.php';
require_once $socle;
require_once dirname((new ReflectionClass(tFPDF::class))->getFileName()).'/font/unifont/ttfonts.php';
require_once __DIR__.'/rapport.php';

class PdfRapportFastGames extends PdfDocumentScolaxie
{
    private array $periode;

    public function __construct(array $identite, array $periode, string $classe, array $rapport, string $commentaire)
    {
        parent::__construct('L');
        $this->periode = $periode;
        $this->chargerPoliceDocumentaire();
        $this->AliasNbPages();
        $this->SetTitle('Fast Games - Rapport de réussites observées', true);
        $this->AddPage();
        // L'identité complète est dans le flux paginé : même un nom exceptionnel
        // ne doit pas rendre l'en-tête de toutes les pages infranchissable.
        $cartes = array(array($identite['prenom'].' '.$identite['nom']), array($classe, $periode['libelle']), array('Du '.$periode['debut'].' au '.$periode['fin'], 'Édition : '.date('d/m/Y')));
        $this->preparerIdentites($cartes);
        if ($this->hauteurIdentite <= 100) {
            foreach ($cartes as $i=>$carte) $this->drawInfoCard(8+95.5*$i, 23, 90, $this->hauteurIdentite, array('ÉLÈVE','CLASSE / PÉRIODE','ÉDITION')[$i], $carte, self::TURQUOISE);
            $this->SetXY(8, 23+$this->hauteurIdentite+7);
        } else {
            $this->SetXY(8,27);
            $this->tableau('Identité', array('Élève', 'Classe et période', 'Édition'), array(array($cartes[0][0], implode("\n",$cartes[1]), implode("\n",$cartes[2]))));
        }
        $this->tableau('Activité sur la période', array('Parties', 'Jeux et jours actifs', 'Disponibilité'), array(array(
            (string)$rapport['parties'], count($rapport['jeux']).' jeu(x), '.$rapport['jours_actifs'].' jour(s)',
            'Peu de données. Aucun jugement scolaire ni tendance calculée.')));
        if ($rapport['tendance'] !== null) $this->tableau('Évolution', array('Observation', 'Périmètre', 'Limites'), array(array($rapport['tendance'], $periode['libelle'], 'Description des résultats uniquement.')));
        $competences = array();
        foreach ($rapport['competences'] as $c) $competences[] = array($c['reference']['libelle'],
            $c['reference']['matiere'].' / '.$c['reference']['categorie']."\n".implode(', ', $c['jeux']),
            $c['parties'].' partie(s). Peu de données. Résultats par jeu ci-dessous.');
        $this->tableau('Compétences explicitement associées', array('Intitulé', 'Rubrique et jeux pratiqués', 'Observations'), $competences ?: array(array('Aucune compétence qualifiée sur cette période.', '', '')));
        $jeux = array();
        foreach ($rapport['jeux'] as $j) $jeux[] = array($j['titre']."\n".(implode('; ', $j['references']) ?: 'Non relié à une compétence'), fgSeriesRapport($j), fgScoreRapport($j['dernier'])."\n".$j['dernier']['date']);
        $this->tableau('Détail par jeu - scores bruts, sans jugement scolaire', array('Jeu et relation explicite', 'Parties et scores par échelle', 'Dernier résultat'), $jeux ?: array(array('Aucun jeu pratiqué sur cette période.', '', '')));
        $transversaux = array();
        foreach ($rapport['sans_attribution'] as $j) $transversaux[] = array($j['titre'], implode("\n", array_keys($j['motifs'])), $j['parties'].' partie(s)');
        $this->tableau('Jeux transversaux / activités non reliées à une compétence', array('Activité', 'Qualification', 'Parties'), $transversaux ?: array(array('Aucune activité dans cette catégorie.', '', '')));
        if (trim($commentaire) !== '') { $this->Ln(5); $this->documentObservation($commentaire); }
    }

    public function Header()
    {
        $this->SetTextColor(...self::TEXTE);
        $this->SetFont('Lexend', 'B', 13);
        $this->SetXY(8, 7.4); $this->Cell(281, 7, 'Fast Games - Rapport de réussites observées');
        $this->SetFont('Lexend', '', 8.5); $this->SetTextColor(...self::GRIS);
        $this->SetXY(8, 15); $this->Cell(281, 5, 'Annexe descriptive - du '.$this->periode['debut'].' au '.$this->periode['fin']);
        $this->docBodyTop = 27;
        $this->SetXY(8, $this->docBodyTop);
    }

    private function tableau(string $titre, array $entetes, array $lignes): void
    {
        $colonnes = array(8, 103.5, 225, 289);
        $ouvrir = function () use ($titre, $entetes, $colonnes) {
            $this->documentSection($titre);
            $this->documentEntetes($colonnes, $entetes, array('L','L','L'));
        };
        $placeInitiale = 34;
        if ($lignes) {
            $this->SetFont('Lexend', '', 9.5);
            $hauteurPremiere = 13.5;
            foreach ($lignes[0] as $i=>$texte) $hauteurPremiere = max($hauteurPremiere, count($this->lignes($colonnes[$i+1]-$colonnes[$i]-6, $texte))*4.9+5);
            if ($hauteurPremiere <= 193-27-18.5) $placeInitiale = 18.5+$hauteurPremiere;
        }
        if ($this->GetY()+$placeInitiale > 193) $this->AddPage();
        $ouvrir();
        foreach ($lignes as $ligne) {
            $this->SetFont('Lexend', '', 9.5);
            $cellules = array();
            foreach ($ligne as $i=>$texte) $cellules[] = $this->lignes($colonnes[$i+1]-$colonnes[$i]-6, $texte);
            $hauteurLigne = max(13.5, max(array_map('count', $cellules))*4.9+5);
            // Une ligne normale reste entière. Seul un bloc plus haut qu'une
            // page utile est fragmenté, avec l'en-tête répété à chaque page.
            if ($hauteurLigne <= 193-27-18.5 && $this->GetY()+$hauteurLigne > 193) { $this->AddPage(); $ouvrir(); }
            while (max(array_map('count', $cellules)) > 0) {
                $restantes = max(array_map('count', $cellules));
                $place = (int)floor((193-$this->GetY()-5)/4.9);
                if ($place < min(2, $restantes)) { $this->AddPage(); $ouvrir(); $place = (int)floor((193-$this->GetY()-5)/4.9); }
                if ($place < 1) throw new RuntimeException('Aucune ligne ne tient sur la page documentaire.');
                $nombre = min($place, $restantes);
                $y = $this->GetY(); $h = max(13.5, $nombre*4.9+5);
                $this->SetFont('Lexend', '', 9.5); $this->SetTextColor(...self::TEXTE);
                $this->SetLineWidth(.25); $this->SetDrawColor(...self::BORDURE);
                foreach ($cellules as $i=>&$texte) {
                    $this->Rect($colonnes[$i], $y, $colonnes[$i+1]-$colonnes[$i], $h);
                    foreach (array_splice($texte, 0, $nombre) as $n=>$fragment) {
                        $this->SetXY($colonnes[$i]+3, $y+2.5+$n*4.9);
                        $this->Cell($colonnes[$i+1]-$colonnes[$i]-6, 4.9, $fragment);
                    }
                }
                unset($texte);
                $this->SetXY(8, $y+$h);
            }
        }
        $this->Ln(5);
    }
}
