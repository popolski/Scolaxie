<?php

$dependencyDir = getenv('SCOLAXIE_TFPDF_DIR') ?: __DIR__;
require_once __DIR__.'/pdf_document_scolaxie.php';
require_once $dependencyDir.'/font/unifont/ttfonts.php';
require_once $dependencyDir.'/calcul_moyennes_eval.php';

if (!function_exists('versWinAnsi')) {
    function versWinAnsi($text) { return $text; }
}

class PdfBilanEvalProto extends PdfDocumentScolaxie
{
    // Les couleurs viennent de galaxie-tokens.css, comme le reste de la suite.
    // Intensite des voiles choisie par le responsable technique le 31/08/2026, sur un tirage reel :
    // lignes D et D de la planche d'essai. Un premier essai a 14 % d'encre
    // sortait en bleu franc et en jaune, « moche et pas fidele a l'ecran » ;
    // les valeurs d'origine, elles, ne s'imprimaient pas du tout.
    //
    // A retenir : le pourcentage d'encre calcule depuis la luminance NE PREDIT
    // PAS ce que rend une imprimante sur une teinte claire et saturee. Le
    // raisonnement valait pour les traits gris, pas pour les aplats colores. On
    // choisit donc sur un tirage, pas au calcul. La planche se refabrique.
    //
    // Reserve de le responsable technique au moment du choix : ses toners sont en fin de vie et
    // il doit les changer. Si le rendu bouge apres remplacement, ces deux
    // valeurs sont a reprendre - c'est le seul reglage a toucher.
    // Assombrie le 31/08/2026. À 217/212/187 le trait ne représentait qu'environ
    // 17 % d'encre : lisible à l'écran sur le fond crème, il disparaissait à
    // l'impression, et les séparations entre matières devenaient invisibles sur
    // le bulletin papier. Signalé par le responsable technique, photo d'un tirage à l'appui.
    // La nouvelle valeur reste dans la famille sable, entre l'ancienne et
    // SEPARATION, et double la charge d'encre. Elle ne sert QUE pour des traits :
    // vérifié, aucun SetFillColor ne l'utilise.
    // Le trait qui separe deux domaines. Volontairement plus sombre que le
    // sable des lignes : dessine dans le meme ton, il ne se distinguait pas
    // du filet ordinaire et les domaines se lisaient comme un seul bloc.
    const NA = array(169, 50, 38);
    // Assombrie le 07/09/2026, sur signalement plutot que sur tirage : cette
    // teinte ambre/jaune est exactement la famille qui a rate un premier
    // tirage le 31/08/2026 (« un premier essai a 14 % d'encre sortait en bleu
    // franc et en jaune »), et n'avait jamais ete revalidee depuis. Alignee
    // sur le jeton web #855900 (journal-v2.css), deja en usage a l'ecran :
    // plus d'encre, meme famille de teinte, coupe au passage l'ecart deja
    // signale entre les valeurs PDF et web. A confirmer sur un vrai tirage,
    // comme TURQUOISE_PALE et BORDURE avant elle.
    const PA = array(133, 89, 0);
    const A = array(0, 118, 74);
    const D = array(0, 92, 66);

    public $debut_perio_fr;
    public $fin_perio_fr;
    public $debut_perio;
    public $nom;
    public $prenom;
    public $date_naiss_fr;
    public $obsFr = '';
    public $obsMat = '';

    private $moyennes = array();
    private $moyFr;
    private $moyMat;
    private $idTest = array();
    private $attemptCounts = array();
    protected $currentSubject = '';
    protected $currentAverage;
    protected $currentObservation = '';
    protected $tableStartY = 0;
    protected $currentFrameTop = 0;
    private $bottomLimit = 193;
    protected $charteEnhanced = false;
    private $charteRowParity = 0;

    // 8 | 62 | 235 | 259 | 289 : marges a 8 mm, et les 23 mm repris a la date
    // et aux notes vont au libelle, qui passe de 150 a 173 mm.
    protected $columns = array(8, 62, 235, 259, 289);

    public function Header()
    {
        $this->SetAutoPageBreak(false);
        $this->SetTextColor(...self::TEXTE);

        $logo = __DIR__.'/../img/logofasteval.png';
        if (is_file($logo)) {
            // Le logo ne doit pas toucher le bord supérieur des cartouches
            // d'identité : il est légèrement réduit et remonté pour garder
            // une respiration nette avant la ligne turquoise.
            $this->Image($logo, 9, 5.5, 34);
        } else {
            $this->SetFont('Lexend', 'B', 13);
            $this->SetXY(9, 10);
            $this->Cell(45, 10, 'Fast Eval', 0, 0, 'L');
        }

        $this->SetFont('Lexend', 'B', 13);
        $this->SetXY(57, 8);
        $this->Cell(232, 7, versWinAnsi("Bilan des acquis scolaires de l'élève · historique / non canonique"), 0, 0, 'L');
        $this->SetFont('Lexend', '', 9);
        $this->SetTextColor(...self::GRIS);
        $this->SetXY(57, 15.5);
        $this->Cell(112, 5, versWinAnsi('Année scolaire '.$this->anneeScolaireLocal()), 0, 0, 'L');
        $this->SetXY(169, 15.5);
        $this->Cell(120, 5, versWinAnsi('Période du '.$this->debut_perio_fr.' au '.$this->fin_perio_fr), 0, 0, 'R');

        $this->preparerIdentites(array($this->schoolCardLines(), $this->studentCardLines(), $this->followUpCardLines()));
        $this->drawInfoCard(8, 23, 90, $this->hauteurIdentite, 'ÉTABLISSEMENT', $this->schoolCardLines());
        $this->drawInfoCard(103, 23, 90, $this->hauteurIdentite, 'ÉLÈVE', $this->studentCardLines());
        $this->drawInfoCard(199, 23, 90, $this->hauteurIdentite, 'SUIVI', $this->followUpCardLines());

        $this->SetDrawColor(...self::TURQUOISE);
        $this->SetLineWidth(0.55);
        $this->Line(8, $this->docBodyTop - 4, 289, $this->docBodyTop - 4);
        $this->SetLineWidth(0.25);
        $this->SetDrawColor(...self::BORDURE);
        $this->SetAutoPageBreak(false);
    }

    public function Footer()
    {
        $this->preparerPied();
        // Le marqueur est dessine, pas ecrit : Lexend ne contient aucune
        // fleche. La legende le redessine donc a la bonne place.
        $legend = ' = meilleure note parmi plusieurs essais   |   Page '.$this->PageNo().'/{nb}';
        $largeur = $this->GetStringWidth(versWinAnsi($legend));
        $depart = (297 - $largeur) / 2;
        $this->drawBestMark($depart - 3.4, $this->GetY() + 4.1, self::GRIS);
        $this->SetX($depart);
        $this->Cell($largeur, 6, versWinAnsi($legend), 0, 0, 'L');
    }

    public function bulletin(array $dataFr, array $dataMat)
    {
        $calcul = new CalculMoyennesEval($dataFr, $dataMat);
        $this->idTest = $calcul->idTest;
        $this->moyFr = $calcul->moyFr;
        $this->moyMat = $calcul->moyMat;
        $this->moyennes = array(
            'Conjugaison' => $calcul->moyConj,
            'Grammaire' => $calcul->moyGram,
            'Orthographe' => $calcul->moyOrt,
            'Vocabulaire' => $calcul->moyVoc,
            'Nombres et Calculs' => $calcul->moyNum,
            'Résolution de problèmes' => $calcul->moyProb,
            'Espace et Géométrie' => $calcul->moyGeo,
            'Grandeurs et Mesures' => $calcul->moyMes,
        );
        $this->attemptCounts = $this->countAttempts(array_merge($dataFr, $dataMat));

        if (!$dataFr && !$dataMat) {
            $this->SetXY(8, $this->docBodyTop + 6);
            $this->SetFont('Lexend', '', $this->charteEnhanced ? 9.5 : 11);
            $this->SetTextColor(...self::GRIS);
            $this->Cell(281, 10, versWinAnsi('Aucune évaluation enregistrée sur cette période.'), 0, 0, 'C');
            return;
        }

        // Une matiere par page, et c'est voulu : il n'y en a que deux, et
        // l’enseignante veut le francais et les mathematiques sur deux feuilles
        // distinctes. Le bilan des competences, lui, en a bien plus et les
        // fait se suivre.
        if ($dataFr) {
            $this->renderSubject('Français', $this->moyFr, $this->obsFr, $dataFr);
        }
        if ($dataMat) {
            if ($dataFr) { $this->AddPage(); }
            $this->renderSubject('Mathématiques', $this->moyMat, $this->obsMat, $dataMat);
        }
    }

    private function renderSubject($subject, $average, $observation, array $rows)
    {
        $this->currentSubject = $subject;
        $this->currentAverage = $average;
        $this->currentObservation = trim(str_replace(array("\r\n", "\r", "\n"), ' ', $observation));
        $this->renderTableHeader();

        foreach ($this->groupRows($rows) as $group) {
            $group['items'] = $this->fragmenterItems($group['items'], $this->bottomLimit - $this->tableStartY);
            $heights = array();
            foreach ($group['items'] as $item) { $heights[] = $this->itemHeight($item); }
            // Le libellé de domaine est centré sur toute la hauteur du groupe.
            // Un domaine long pouvait donc être dessiné sur 3 ou 4 lignes dans
            // une cellule de 13,5 mm et perdre sa dernière ligne à l'impression.
            if ($heights) {
                $this->SetFont('Lexend', 'B', 9.5);
                $hauteurDomaine = $this->numberOfLines(
                    $this->columns[1] - $this->columns[0] - ($this->charteEnhanced ? 6 : 4),
                    $group['category']
                ) * 4.7 + 4;
                $heights[0] = max($heights[0], $hauteurDomaine);
            }
            $groupHeight = array_sum($heights);
            $freshCapacity = $this->bottomLimit - $this->tableStartY;

            if ($groupHeight <= $freshCapacity && $this->GetY() + $groupHeight > $this->bottomLimit) {
                $this->closeTableFrame();
                $this->newSubjectPage();
            }

            $offset = 0;
            $count = count($group['items']);
            while ($offset < $count) {
                if ($this->GetY() + $heights[$offset] > $this->bottomLimit) {
                    $this->closeTableFrame();
                    $this->newSubjectPage();
                }

                $chunkStart = $offset;
                $chunkHeight = 0;
                while ($offset < $count && $this->GetY() + $chunkHeight + $heights[$offset] <= $this->bottomLimit) {
                    $chunkHeight += $heights[$offset];
                    $offset++;
                }

                if ($offset === $chunkStart) { throw new RuntimeException('Fragment documentaire trop haut : pagination interrompue sans perte silencieuse.'); }
                $label = $group['category'];
                if ($chunkStart > 0) { $label .= ' (suite)'; }
                $this->drawCategoryCell($label, $this->averageFor($group['category']), $chunkHeight);

                $y = $this->GetY();
                for ($i = $chunkStart; $i < $offset; $i++) {
                    $this->SetY($y);
                    $this->drawDataItem($group['items'][$i], $heights[$i]);
                    $y += $heights[$i];
                }
                $this->SetY($y);
            }

            // Un filet plus marque ferme le domaine : la bordure de la cellule
            // de gauche s'arrete a la colonne du libelle, et sans ce trait deux
            // domaines successifs se lisaient comme un seul bloc.
            $this->drawGroupSeparator();
        }

        $this->closeTableFrame();
    }

    // Cadre exterieur du bloc « bandeau + lignes » pour cette page. No-op ici :
    // le modele actuel garde son cadre d'origine, dessine dans
    // renderTableHeader() et limite au bandeau. La charte le surcharge pour
    // fermer tout le tableau, coins arrondis compris - demande de le responsable technique le
    // 07/09/2026, meme jour que les cartouches ETABLISSEMENT/ELEVE/SUIVI.
    protected function closeTableFrame()
    {
    }

    /* La separation entre deux domaines, tiree sur toute la largeur du
       tableau. Meme famille que le filet entre deux essais d'une meme ligne,
       mais un ton plus present : elle separe des blocs, pas des lignes. */
    private function drawGroupSeparator()
    {
        $y = $this->GetY();
        $this->SetDrawColor(...self::SEPARATION);
        $this->SetLineWidth(0.5);
        $this->Line($this->columns[0], $y, $this->columns[4], $y);
        $this->SetLineWidth(0.25);
        $this->SetDrawColor(...self::BORDURE);
    }

    protected function renderTableHeader()
    {
        $this->SetY($this->docBodyTop);

        // Le bandeau : un voile turquoise avec une barre pleine a gauche, au
        // lieu d'un aplat sature de 277 x 9 mm avec du texte blanc dessus. Le
        // titre y est bien plus lisible, et la page respire.
        $y = $this->docBodyTop;
        $hautBloc = $y;
        $this->currentFrameTop = $hautBloc;
        $this->SetFillColor(...self::TURQUOISE_PALE);
        $this->Rect(8, $y, 281, 10, 'F');
        $this->SetFillColor(...self::TURQUOISE);
        $this->Rect(8, $y, 1.8, 10, 'F');

        $title = 'Connaissances fondamentales - '.$this->currentSubject;
        $moyenne = ($this->currentAverage === null || $this->currentAverage === '')
            ? ''
            : 'Moyenne '.$this->formatAverage($this->currentAverage).' / 10';

        // La moyenne va dans une pastille a droite : c'est le seul aplat
        // sature qui reste, et il est petit.
        $largeurPastille = 0;
        if ($moyenne !== '') {
            $this->SetFont('Lexend', 'B', 10);
            $largeurPastille = $this->GetStringWidth(versWinAnsi($moyenne)) + 8;
            $this->SetFillColor(...self::TURQUOISE);
            $this->RoundedBox(289 - 3 - $largeurPastille, $y + 1.8, $largeurPastille, 6.4, 3.2);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY(289 - 3 - $largeurPastille, $y + 1.8);
            $this->Cell($largeurPastille, 6.4, versWinAnsi($moyenne), 0, 0, 'C');
        }

        $titleSize = 12;
        $placeTitre = 281 - 8 - $largeurPastille - 6;
        do {
            $this->SetFont('Lexend', 'B', $titleSize);
            $titleSize -= 0.4;
        } while ($this->GetStringWidth(versWinAnsi($title)) > $placeTitre && $titleSize >= 9.5);
        $this->SetTextColor(...self::TURQUOISE_FONCE);
        $this->SetXY(13, $y);
        $this->Cell($placeTitre, 10, versWinAnsi($title), 0, 1, 'L');
        $this->SetY($y + 10);

        if ($this->currentObservation !== '') {
            $this->SetFillColor(...self::CREME);
            $this->SetTextColor(...self::TEXTE);
            $this->SetFont('Lexend', '', 9);
            $lines = $this->numberOfLines(275, $this->currentObservation);
            $h = max(8, $lines * 4.6 + 3.4);
            $this->Rect(8, $this->GetY(), 281, $h, 'F');
            $this->SetXY(11, $this->GetY() + 1.7);
            $this->MultiCell(275, 4.6, versWinAnsi($this->currentObservation), 0, 'L');
            $this->SetY($this->docBodyTop + 10 + $h);
        }

        // Les en-tetes de colonnes quittent le turquoise pale pour le sable de
        // la charte : le turquoise ne sert plus qu'a annoncer une matiere.
        $this->SetFillColor(...self::SABLE_VOILE);
        $this->SetTextColor(...self::TEXTE);
        $this->SetFont('Lexend', 'B', 9.5);
        $y = $this->GetY();
        $this->Rect($this->columns[0], $y, $this->columns[4] - $this->columns[0], 8.5, 'F');
        $this->SetDrawColor(...self::SABLE);
        $this->SetLineWidth(0.3);
        $this->Line($this->columns[0], $y + 8.5, $this->columns[4], $y + 8.5);
        $this->SetLineWidth(0.25);
        $headers = array("Domaines d'enseignement", 'Éléments du programme travaillés', 'Notes /10', 'Date');
        $alignements = array('L', 'L', 'C', 'C');
        for ($i = 0; $i < 4; $i++) {
            $this->SetXY($this->columns[$i] + ($alignements[$i] === 'L' ? 3 : 0), $y);
            $largeur = $this->columns[$i + 1] - $this->columns[$i] - ($alignements[$i] === 'L' ? 3 : 0);
            $this->Cell($largeur, 8.5, versWinAnsi($headers[$i]), 0, 0, $alignements[$i]);
        }
        $this->SetDrawColor(...self::BORDURE);
        // Les filets de colonne s'arretent juste sous le bandeau du titre :
        // celui-ci reste une seule ligne, alignee a gauche, et rien ne vient
        // le couper.
        for ($c = 1; $c <= 4; $c++) {
            $this->Line($this->columns[$c], $y, $this->columns[$c], $y + 8.5);
        }

        // Le cadre exterieur du bloc « bandeau + en-tete de colonnes ». Les
        // lignes de donnees fournissent deja leur bord gauche (la cellule de
        // domaine est bordee) et leur bord droit ; il manquait le dessus et les
        // cotes de cette partie haute, et les deux bulletins n'etaient pas
        // encadres pareil.
        $this->SetDrawColor(...self::BORDURE);
        $this->Rect($this->columns[0], $hautBloc,
                    $this->columns[4] - $this->columns[0], ($y + 8.5) - $hautBloc, 'D');
        // Et le trait sous le bandeau du titre, qui le detache de l'en-tete de
        // colonnes. C'est le dernier cote qui manquait au cadre.
        $this->Line($this->columns[0], $hautBloc + 10, $this->columns[4], $hautBloc + 10);

        $this->SetY($y + 8.5);
        $this->tableStartY = $this->GetY();
    }

    private function newSubjectPage()
    {
        $this->AddPage();
        $this->renderTableHeader();
    }

    private function drawCategoryCell($category, $average, $height)
    {
        $y = $this->GetY();
        $this->SetFillColor(...self::TURQUOISE_PALE);
        $this->SetDrawColor(...self::BORDURE);
        $this->Rect($this->columns[0], $y, $this->columns[1] - $this->columns[0], $height, 'DF');
        $this->SetFont('Lexend', 'B', 9.5);
        $width = $this->columns[1] - $this->columns[0] - ($this->charteEnhanced ? 6 : 4);
        $hasAverage = ($average !== null && $average !== '');
        $categoryLines = $this->numberOfLines($width, $category);
        $lineHeight = 4.7;
        $totalLines = $categoryLines + ($hasAverage ? 1 : 0);
        $textHeight = $totalLines * $lineHeight;
        $top = $y + max(1, ($height - $textHeight) / 2);

        $this->SetTextColor(...self::TURQUOISE_FONCE);
        $this->SetXY($this->columns[0] + ($this->charteEnhanced ? 3 : 2), $top);
        $this->MultiCell($width, $lineHeight, versWinAnsi($category), 0, 'C');

        if ($hasAverage) {
            // Meme couleur que les notes de l'ecran de saisie et les pastilles
            // de resultat, demande de le responsable technique le 07/09/2026 : la moyenne d'un
            // domaine dit sa maitrise, pas seulement un chiffre neutre.
            // Reserve au modele charte : le modele actuel garde son turquoise
            // fixe, personne ne l'a demande la.
            $moyenneColor = $this->charteEnhanced ? $this->noteColor((float)$average) : self::TURQUOISE_FONCE;
            $this->SetTextColor(...$moyenneColor);
            $this->SetXY($this->columns[0] + ($this->charteEnhanced ? 3 : 2), $top + $categoryLines * $lineHeight);
            $this->Cell($width, $lineHeight, versWinAnsi('moy. '.$this->formatAverage($average)), 0, 0, 'C');
        }

        $this->SetY($y);
        $this->SetTextColor(...self::TEXTE);
    }

    private function drawDataItem(array $item, $height)
    {
        $y = $this->GetY();
        // Toutes les lignes en blanc : l'alternance blanc/creme rayait la
        // colonne des libelles pour rien. Ce sont les filets qui structurent
        // le tableau, pas des aplats.
        if ($this->charteEnhanced && ($this->charteRowParity++ % 2) === 1) {
            $this->SetFillColor(247, 249, 247);
        } else {
            $this->SetFillColor(...self::BLANC);
        }
        $this->SetDrawColor(...self::BORDURE);

        // Un seul aplat sur toute la largeur, puis un trait sous la ligne :
        // les filets verticaux entre colonnes ajoutaient de la grille sans
        // rien apporter.
        $this->Rect($this->columns[1], $y, $this->columns[4] - $this->columns[1], $height, 'F');
        $this->Line($this->columns[1], $y + $height, $this->columns[4], $y + $height);

        // Filets verticaux : la zone des notes est encadree, a gauche et au
        // bord droit de la page. Sans eux, sur 173 mm de libelle, l'oeil ne
        // sait plus ou commence la colonne des notes.
        $this->Line($this->columns[2], $y, $this->columns[2], $y + $height);
        $this->Line($this->columns[3], $y, $this->columns[3], $y + $height);
        $this->Line($this->columns[4], $y, $this->columns[4], $y + $height);

        $comment = $item['comment'];
        $this->SetFont('Lexend', '', 9.5);
        $this->SetTextColor(...self::TEXTE);
        $lineHeight = 4.9;
        $lines = $this->numberOfLines($this->columns[2] - $this->columns[1] - 6, $comment);
        // A gauche, pas au centre : sur 173 mm de large, un texte centre fait
        // perdre le debut de chaque ligne.
        $this->SetXY($this->columns[1] + 3, $y + max(1, ($height - $lines * $lineHeight) / 2));
        $this->MultiCell($this->columns[2] - $this->columns[1] - 6, $lineHeight, versWinAnsi($comment), 0, 'L');

        $attemptHeight = 9;
        $attemptsHeight = count($item['rows']) * $attemptHeight;
        $attemptY = $y + max(0, ($height - $attemptsHeight) / 2);
        $attemptCount = count($item['rows']);
        foreach ($item['rows'] as $attemptIndex => $row) {
            $note = (float)$row[5];
            $noteText = number_format($note, 1, ',', '');
            $meilleure = $this->isSelectedBest($row);

            $couleur = $this->noteColor($note);
            $this->SetTextColor(...$couleur);
            $this->SetFont('Lexend', 'B', 11.5);
            $largeurNote = $this->columns[3] - $this->columns[2];
            if ($this->charteEnhanced) {
                $this->SetFillColor(...$this->notePillFill($note));
                $pillW = 16;
                $pillH = 6.5;
                $pillX = $this->columns[2] + ($largeurNote - $pillW) / 2;
                $pillY = $attemptY + ($attemptHeight - $pillH) / 2;
                $this->RoundedBox($pillX, $pillY, $pillW, $pillH, 3.25);
                $this->SetXY($pillX, $pillY);
                $this->Cell($pillW, $pillH, $noteText, 0, 0, 'C');
            } else {
                $this->SetXY($this->columns[2], $attemptY);
                $this->Cell($largeurNote, $attemptHeight, $noteText, 0, 0, 'C');
            }
            if ($meilleure) {
                $milieu = $this->columns[2] + $largeurNote / 2;
                $demi = $this->GetStringWidth($noteText) / 2;
                $this->drawBestMark($milieu + $demi + 1.4, $attemptY + $attemptHeight / 2 + 1.1, $couleur);
            }

            $this->SetTextColor(...self::GRIS);
            $this->SetFont('Lexend', '', 9);
            $this->SetXY($this->columns[3], $attemptY);
            $this->Cell($this->columns[4] - $this->columns[3], $attemptHeight, date('d/m/Y', strtotime($row[6])), 0, 0, 'C');
            $attemptY += $attemptHeight;
            if ($attemptIndex < $attemptCount - 1) {
                $this->SetDrawColor(228, 224, 205);
                $this->SetLineWidth(0.25);
                $this->Line($this->columns[2], $attemptY, $this->columns[4], $attemptY);
                $this->SetDrawColor(...self::BORDURE);
                $this->SetLineWidth(0.25);
            }
        }
        $this->SetY($y + $height);
    }

    private function groupRows(array $rows)
    {
        $groups = array();
        foreach ($rows as $row) {
            $category = $row[2];
            $last = count($groups) - 1;
            if ($last < 0 || $groups[$last]['category'] !== $category) {
                $groups[] = array('category' => $category, 'items' => array(), 'itemIndexes' => array());
                $last++;
            }
            $knowledgeId = (string)$row[0];
            if (!isset($groups[$last]['itemIndexes'][$knowledgeId])) {
                $groups[$last]['itemIndexes'][$knowledgeId] = count($groups[$last]['items']);
                $groups[$last]['items'][] = array('comment' => $row[3], 'rows' => array());
            }
            $itemIndex = $groups[$last]['itemIndexes'][$knowledgeId];
            $groups[$last]['items'][$itemIndex]['rows'][] = $row;
        }
        foreach ($groups as &$group) {
            unset($group['itemIndexes']);
        }
        unset($group);
        return $groups;
    }

    private function countAttempts(array $rows)
    {
        $counts = array();
        foreach ($rows as $row) {
            $key = (string)$row[0];
            if (!isset($counts[$key])) { $counts[$key] = 0; }
            $counts[$key]++;
        }
        return $counts;
    }

    private function isSelectedBest(array $row)
    {
        $key = (string)$row[0];
        return isset($this->attemptCounts[$key])
            && $this->attemptCounts[$key] > 1
            && isset($this->idTest[$row[0]])
            && (string)$this->idTest[$row[0]] === (string)$row[7];
    }

    private function averageFor($category)
    {
        return array_key_exists($category, $this->moyennes) ? $this->moyennes[$category] : null;
    }

    /* Lexend ne contient aucune fleche : le marqueur de meilleure note est
       donc dessine en vectoriel, ce qui le rend independant de la police et
       lui laisse prendre la couleur de la note. Petit triangle vers le haut,
       pointe en ($x + 1,1 ; $y - 2,2). */
    private function drawBestMark($x, $y, array $couleur)
    {
        $this->SetFillColor(...$couleur);
        $l = 2.2; $h = 2.2;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l %.2F %.2F l f',
            $x * $this->k, ($this->h - $y) * $this->k,
            ($x + $l) * $this->k, ($this->h - $y) * $this->k,
            ($x + $l / 2) * $this->k, ($this->h - ($y - $h)) * $this->k));
    }

    /* FPDF ne sait pas dessiner un rectangle a coins arrondis. Quatre arcs de
       Bezier suffisent, et la pastille de moyenne en a besoin. */
    // $style suit la convention de Rect() : 'F' (fond, par defaut - les
    // appels existants pour la pastille de moyenne ne passent rien et
    // gardent donc exactement leur rendu d'avant), 'D' (contour seul) ou
    // 'DF'/'FD' (fond et contour) - ajoute pour les cartouches arrondis de la
    // charte, meme demande que sur le bulletin des competences le 07/09/2026.

    protected function noteColor($note)
    {
        if ($note < 5) { return self::NA; }
        if ($note < 8) { return self::PA; }
        return self::A;
    }

    private function notePillFill($note)
    {
        if ($note < 5) { return array(248, 229, 225); }
        if ($note < 8) { return array(250, 240, 210); }
        return array(222, 242, 232);
    }

    protected function schoolCardLines()
    {
        $name = isset($_SESSION['nom_etablissement']) ? $_SESSION['nom_etablissement'] : '';
        $address = isset($_SESSION['adresse_etablissement']) ? $_SESSION['adresse_etablissement'] : '';
        $city = trim((isset($_SESSION['departement_etablissement']) ? $_SESSION['departement_etablissement'] : '').' '.(isset($_SESSION['ville_etablissement']) ? $_SESSION['ville_etablissement'] : ''));
        $phone = !empty($_SESSION['tel_etablissement']) ? 'Tél. '.$_SESSION['tel_etablissement'] : '';
        return array($name, $address, trim($city.($phone !== '' ? ' · '.$phone : '')));
    }

    protected function studentCardLines()
    {
        return array(
            $this->prenom.' '.strtoupper($this->nom),
            versWinAnsi('Né(e) le '.$this->date_naiss_fr),
            'Classe : '.(isset($_SESSION['classe_client']) ? $_SESSION['classe_client'] : ''),
        );
    }

    protected function followUpCardLines()
    {
        $teacher = trim((isset($_SESSION['nom_client']) ? $_SESSION['nom_client'] : '').' '.(isset($_SESSION['prenom_client']) ? $_SESSION['prenom_client'] : ''));
        return array(
            'Enseignant(e) : '.$teacher,
            versWinAnsi('Année scolaire : '.$this->anneeScolaireLocal()),
            versWinAnsi('Période : '.$this->debut_perio_fr.' - '.$this->fin_perio_fr),
        );
    }

    protected function formatAverage($value)
    {
        return number_format((float)$value, 1, ',', '');
    }

    private function schoolLine()
    {
        $parts = array();
        foreach (array('nom_etablissement', 'adresse_etablissement') as $key) {
            if (!empty($_SESSION[$key])) { $parts[] = $_SESSION[$key]; }
        }
        $city = trim((isset($_SESSION['departement_etablissement']) ? $_SESSION['departement_etablissement'] : '').' '.(isset($_SESSION['ville_etablissement']) ? $_SESSION['ville_etablissement'] : ''));
        if ($city !== '') { $parts[] = $city; }
        if (!empty($_SESSION['tel_etablissement'])) { $parts[] = 'Tél : '.$_SESSION['tel_etablissement']; }
        return implode(' · ', $parts);
    }

    private function teacherLine()
    {
        $teacher = trim((isset($_SESSION['nom_client']) ? $_SESSION['nom_client'] : '').' '.(isset($_SESSION['prenom_client']) ? $_SESSION['prenom_client'] : ''));
        $class = isset($_SESSION['classe_client']) ? $_SESSION['classe_client'] : '';
        return 'Enseignant(e) '.$teacher.' · Classe : '.$class;
    }

    protected function anneeScolaireLocal()
    {
        $timestamp = strtotime($this->debut_perio);
        $year = (int)date('Y', $timestamp);
        $month = (int)date('m', $timestamp);
        return ($month >= 8) ? $year.'-'.($year + 1) : ($year - 1).'-'.$year;
    }
}
