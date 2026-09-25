<?php

// Bulletin des competences.
//
// Cette classe suit DELIBEREMENT le bilan des connaissances
// (pdf_bilan_eval_proto.php) : memes jetons, memes colonnes, meme cellule de
// domaine a gauche, meme filet fin entre deux saisies d'une meme ligne. Les
// deux bulletins partent chez les memes familles ; ils doivent se ressembler.
//
// Ce qui differe tient a la nature des donnees, pas au dessin :
//  - une competence n'a pas de note sur 10 mais un niveau (NA, PA, A, D) ;
//  - il n'y a donc pas de moyenne, ni de marqueur de meilleur essai ;
//  - la colonne de droite compte les saisies au lieu de porter une date.

$dependencyDir = getenv('SCOLAXIE_TFPDF_DIR') ?: __DIR__;
require_once __DIR__.'/pdf_document_scolaxie.php';
require_once $dependencyDir.'/font/unifont/ttfonts.php';

if (!function_exists('versWinAnsi')) {
    function versWinAnsi($text) { return $text; }
}

class PdfBilanCompProto extends PdfDocumentScolaxie
{
    // Memes jetons que galaxie-tokens.css, comme le bilan des connaissances.
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
    // Assombrie le 07/09/2026, sur signalement plutot que sur tirage : meme
    // correctif que pdf_bilan_eval_proto.php, memes constantes dupliquees a
    // l'identique dans les deux classes racines. Voir son commentaire pour
    // la raison complete.
    const PA = array(133, 89, 0);
    const A = array(0, 118, 74);
    const D = array(0, 92, 66);

    protected $info;
    public $observations = '';

    protected $currentSubject = '';
    protected $tableStartY = 0;
    protected $currentFrameTop = 0;
    protected $bottomLimit = 193;
    protected $charteEnhanced = false;
    private $charteRowParity = 0;

    // Exactement les colonnes du bilan des connaissances : 8 | 62 | 235 | 259 |
    // 289. Le domaine tient dans 54 mm, le libelle dans 173, le niveau dans 24
    // et le nombre de saisies dans 30. Deux bulletins cote a cote se
    // superposent donc au millimetre.
    protected $columns = array(8, 62, 235, 259, 289);

    public function addInfo(Requete1 $infoEleve) { $this->info = $infoEleve; }

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
        $this->Cell(112, 5, versWinAnsi('Année scolaire '.$this->info->anneeScolaire()), 0, 0, 'L');
        $this->SetXY(169, 15.5);
        $this->Cell(120, 5, versWinAnsi('Période du '.$this->info->periode('debut')
            .' au '.$this->info->periode('fin')), 0, 0, 'R');

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
        $text = 'NA : Non Atteint   |   PA : Partiellement Atteint   |   A : Atteint   |   D : Dépassé'
              .'   |   Saisies = nombre d’évaluations pour cette compétence   |   Page '
              .$this->PageNo().'/{nb}';
        $this->SetX(8);
        $this->Cell(281, 6, versWinAnsi($text), 0, 0, 'C');
    }

    public function bulletin()
    {
        $subjects = $this->info->listeMatiere();

        if (!$subjects) {
            $this->SetXY(8, $this->docBodyTop + 6);
            $this->SetFont('Lexend', '', $this->charteEnhanced ? 9.5 : 11);
            $this->SetTextColor(...self::GRIS);
            $this->Cell(281, 10, versWinAnsi('Aucune compétence enregistrée sur cette période.'), 0, 0, 'C');
            return;
        }

        // Les matieres se suivent : une nouvelle ne va sur une page neuve que
        // s'il ne reste pas la place d'y poser son bandeau, ses en-tetes et une
        // premiere ligne. Un saut systematique laissait des pages a moitie
        // vides, et il y a bien plus de matieres ici que dans le bilan des
        // connaissances.
        $premiere = true;
        foreach ($subjects as $subjectRow) {
            $this->renderSubject($subjectRow[0], $this->departMatiere($premiere));
            $premiere = false;
        }

        if (trim($this->observations) !== '') { $this->drawObservations(); }
    }

    /**
     * Ou commencer une matiere : en haut d'une page neuve pour la premiere, a
     * la suite pour les autres. On ne change de page que s'il ne reste pas de
     * quoi poser le bandeau (10 mm), les en-tetes (8,5 mm) et une ligne (14 mm).
     */
    private function departMatiere($premiere)
    {
        if ($premiere) { return $this->docBodyTop; }
        $depart = $this->GetY() + 5;
        if ($depart + 32.5 > $this->bottomLimit) {
            $this->AddPage();
            return $this->docBodyTop;
        }
        return $depart;
    }

    private function renderSubject($subject, $depart = 52)
    {
        $this->currentSubject = $subject;
        $this->renderTableHeader($depart);

        foreach ($this->groupRows($subject) as $group) {
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

            // Un groupe qui tiendrait entier sur une page fraiche ne se coupe
            // pas : mieux vaut le pousser que le fendre en deux.
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
                $this->drawCategoryCell($label, $chunkHeight);

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

    // Cadre exterieur du bloc « bandeau + lignes » de cette matiere. No-op ici :
    // le modele actuel garde son cadre d'origine, dessine dans
    // renderTableHeader() et limite au bandeau. La charte le surcharge pour
    // fermer tout le tableau, coins arrondis compris - meme demande que sur le
    // bulletin des connaissances le 07/09/2026.
    protected function closeTableFrame()
    {
    }

    /* La separation entre deux domaines, tiree sur toute la largeur du
       tableau. Meme famille que le filet entre deux saisies d'une meme ligne,
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

    protected function renderTableHeader($depart = 52)
    {
        $y = max($depart, $this->docBodyTop);
        $hautBloc = $y;
        $this->currentFrameTop = $hautBloc;
        $this->SetY($y);

        // Voile turquoise et barre pleine a gauche, au lieu d'un aplat sature
        // avec du texte blanc dessus.
        $this->SetFillColor(...self::TURQUOISE_PALE);
        $this->Rect(8, $y, 281, 10, 'F');
        $this->SetFillColor(...self::TURQUOISE);
        $this->Rect(8, $y, 1.8, 10, 'F');

        $title = 'Compétences travaillées - '.$this->currentSubject;
        $titleSize = 12;
        $placeTitre = 281 - 8 - 6;
        do {
            $this->SetFont('Lexend', 'B', $titleSize);
            $titleSize -= 0.4;
        } while ($this->GetStringWidth(versWinAnsi($title)) > $placeTitre && $titleSize >= 9.5);
        $this->SetTextColor(...self::TURQUOISE_FONCE);
        $this->SetXY(13, $y);
        $this->Cell($placeTitre, 10, versWinAnsi($title), 0, 1, 'L');
        $this->SetY($y + 10);

        // Les en-tetes de colonnes sont en sable : le turquoise ne sert qu'a
        // annoncer une matiere.
        $this->SetFillColor(...self::SABLE_VOILE);
        $this->SetTextColor(...self::TEXTE);
        $this->SetFont('Lexend', 'B', 9.5);
        $y = $this->GetY();
        $this->Rect($this->columns[0], $y, $this->columns[4] - $this->columns[0], 8.5, 'F');
        $this->SetDrawColor(...self::SABLE);
        $this->SetLineWidth(0.3);
        $this->Line($this->columns[0], $y + 8.5, $this->columns[4], $y + 8.5);
        $this->SetLineWidth(0.25);
        $headers = array("Domaines d'enseignement", 'Compétences travaillées', 'Niveau', 'Saisies');
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

    private function drawCategoryCell($category, $height)
    {
        $y = $this->GetY();
        $this->SetFillColor(...self::TURQUOISE_PALE);
        $this->SetDrawColor(...self::BORDURE);
        $this->Rect($this->columns[0], $y, $this->columns[1] - $this->columns[0], $height, 'DF');
        $this->SetTextColor(...self::TURQUOISE_FONCE);
        $this->SetFont('Lexend', 'B', 9.5);
        // Pas de compte sous le nom du domaine : toutes les competences du
        // domaine sont reunies dans sa cellule, on les voit. Le bilan des
        // connaissances y met une moyenne, qui est une information ; un
        // compte, non.
        $text = $category;
        $lines = $this->numberOfLines($this->columns[1] - $this->columns[0] - ($this->charteEnhanced ? 6 : 4), $text);
        $lineHeight = 4.7;
        $textHeight = $lines * $lineHeight;
        $this->SetXY($this->columns[0] + ($this->charteEnhanced ? 3 : 2), $y + max(1, ($height - $textHeight) / 2));
        $this->MultiCell($this->columns[1] - $this->columns[0] - ($this->charteEnhanced ? 6 : 4), $lineHeight, versWinAnsi($text), 0, 'C');
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

        // Un seul aplat sur toute la largeur utile, puis un trait sous la
        // ligne : les filets verticaux entre colonnes n'apportaient rien.
        $this->Rect($this->columns[1], $y, $this->columns[4] - $this->columns[1], $height, 'F');
        $this->Line($this->columns[1], $y + $height, $this->columns[4], $y + $height);

        // Filets verticaux : la zone des niveaux est encadree, a gauche et au
        // bord droit de la page. Sans eux, sur 173 mm de libelle, l'oeil ne
        // sait plus ou commence la colonne des niveaux.
        $this->Line($this->columns[2], $y, $this->columns[2], $y + $height);
        $this->Line($this->columns[3], $y, $this->columns[3], $y + $height);
        $this->Line($this->columns[4], $y, $this->columns[4], $y + $height);

        $comment = $item['comment'];
        $this->SetFont('Lexend', '', 9.5);
        $this->SetTextColor(...self::TEXTE);
        $lineHeight = 4.9;
        $lines = $this->numberOfLines($this->columns[2] - $this->columns[1] - 6, $comment);
        $this->SetXY($this->columns[1] + 3, $y + max(1, ($height - $lines * $lineHeight) / 2));
        $this->MultiCell($this->columns[2] - $this->columns[1] - 6, $lineHeight, versWinAnsi($comment), 0, 'L');

        $entryHeight = 9;
        $entriesHeight = count($item['rows']) * $entryHeight;
        $entryY = $y + max(0, ($height - $entriesHeight) / 2);
        $entryCount = count($item['rows']);
        foreach ($item['rows'] as $entryIndex => $row) {
            list($sigle, $couleur) = $this->levelStyle($row[0]);

            $this->SetTextColor(...$couleur);
            $this->SetFont('Lexend', 'B', 11.5);
            $niveauX = $this->columns[2];
            $niveauW = $this->columns[3] - $this->columns[2];
            if ($this->charteEnhanced) {
                $this->SetFillColor(...$this->levelPillFill($sigle));
                $pillW = 12;
                $pillH = 6.5;
                $pillX = $niveauX + ($niveauW - $pillW) / 2;
                $pillY = $entryY + ($entryHeight - $pillH) / 2;
                $this->RoundedBox($pillX, $pillY, $pillW, $pillH, 3.25);
                $this->SetXY($pillX, $pillY);
                $this->Cell($pillW, $pillH, versWinAnsi($sigle), 0, 0, 'C');
            } else {
                $this->SetXY($niveauX, $entryY);
                $this->Cell($niveauW, $entryHeight, versWinAnsi($sigle), 0, 0, 'C');
            }

            $this->SetTextColor(...self::GRIS);
            $this->SetFont('Lexend', '', 9);
            $this->SetXY($this->columns[3], $entryY);
            $nombreSaisies = (int)$row[1];
            $this->Cell($this->columns[4] - $this->columns[3], $entryHeight,
                versWinAnsi($nombreSaisies.' '.($nombreSaisies === 1 ? 'saisie' : 'saisies')), 0, 0, 'C');

            $entryY += $entryHeight;
            // Le filet fin entre deux saisies d'une meme competence : c'est ce
            // qui manquait, et c'est celui du bilan des connaissances, au trait
            // et a la couleur pres. Il ne court que sous les deux dernieres
            // colonnes, pour ne pas couper le libelle.
            if ($entryIndex < $entryCount - 1) {
                $this->SetDrawColor(228, 224, 205);
                $this->SetLineWidth(0.25);
                $this->Line($this->columns[2], $entryY, $this->columns[4], $entryY);
                $this->SetDrawColor(...self::BORDURE);
                $this->SetLineWidth(0.25);
            }
        }
        $this->SetY($y + $height);
    }

    /**
     * Les competences d'une matiere, regroupees par categorie puis par
     * libelle : plusieurs saisies d'une meme competence forment une seule
     * ligne, avec ses niveaux empiles.
     */
    private function groupRows($subject)
    {
        $groups = array();
        foreach ($this->info->listeCategorie($subject) as $categoryRow) {
            $category = $categoryRow[0];
            $items = array();
            $indexes = array();
            foreach ($this->info->listeCommentaire($category) as $row) {
                $text = $row[0];
                if (!isset($indexes[$text])) {
                    $indexes[$text] = count($items);
                    $items[] = array('comment' => $text, 'rows' => array());
                }
                // On ne garde que le niveau et le nombre de saisies : le
                // libelle est deja porte par la ligne.
                $items[$indexes[$text]]['rows'][] = array($row[1], $row[2]);
            }
            if ($items) {
                $groups[] = array('category' => $category, 'items' => $items);
            }
        }
        return $groups;
    }

    protected function drawObservations()
    {
        $this->SetY($this->GetY() + 3);
        $this->documentObservation($this->observations);
    }

    private function levelStyle($value)
    {
        $value = strtolower((string)$value);
        if ($value === 'nonatteint') { return array('NA', self::NA); }
        if ($value === 'patteint')   { return array('PA', self::PA); }
        if ($value === 'atteint')    { return array('A',  self::A); }
        if ($value === 'depasse')    { return array('D',  self::D); }
        return array(strtoupper($value), self::TEXTE);
    }

    private function levelPillFill($sigle)
    {
        if ($sigle === 'NA') { return array(248, 229, 225); }
        if ($sigle === 'PA') { return array(250, 240, 210); }
        if ($sigle === 'A')  { return array(222, 242, 232); }
        if ($sigle === 'D')  { return array(216, 238, 235); }
        return array(239, 241, 239);
    }

    // $style suit la convention de Rect() : 'F' (fond, par defaut - les
    // appels existants pour les pastilles de niveau ne passent rien et
    // gardent donc exactement leur rendu d'avant), 'D' (contour seul) ou
    // 'DF'/'FD' (fond et contour) - ajoute pour les cartouches arrondis de
    // la charte, demande par le responsable technique le 07/09/2026.

    protected function schoolCardLines()
    {
        $city = trim($_SESSION['departement_etablissement'].' '.$_SESSION['ville_etablissement']);
        return array(
            $_SESSION['nom_etablissement'],
            $_SESSION['adresse_etablissement'],
            $city.' · Tél. '.$_SESSION['tel_etablissement'],
        );
    }

    protected function studentCardLines()
    {
        return array(
            $this->info->eleve('prenom').' '.strtoupper($this->info->eleve('nom')),
            'Né(e) le '.$this->info->eleve('naissance'),
            'Classe : '.$_SESSION['classe_client'],
        );
    }

    protected function followUpCardLines()
    {
        return array(
            'Enseignant(e) : '.$_SESSION['nom_client'].' '.$_SESSION['prenom_client'],
            'Année scolaire : '.$this->info->anneeScolaire(),
            'Période : '.$this->info->periode('debut').' - '.$this->info->periode('fin'),
        );
    }
}
