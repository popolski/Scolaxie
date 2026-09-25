<?php
$ordreSuivi = array_flip(array('reprendre', 'fragile', 'maitrise', 'alaise', 'jamais'));
$detailsSuivi = $suiviSelection['detail'];
usort($detailsSuivi, static fn(array $a, array $b): int => ($ordreSuivi[$a['niveau']] <=> $ordreSuivi[$b['niveau']]) ?: ($a['taux'] <=> $b['taux']));
?>
<div class="sv-legende">
    <?php foreach (fgNiveaux() as $niveauSuivi) { ?>
    <span><b><?php echo (int)$suiviSelection['niveaux'][$niveauSuivi]; ?></b> <?php echo fgH(fgLibelleNiveau($niveauSuivi)); ?></span>
    <?php } ?>
</div>
<p class="sv-sous">Les trois derniers essais de chaque élève, du besoin le plus marqué à la réussite.</p>
<div class="sv-detail-eleves">
    <?php foreach ($detailsSuivi as $detailSuivi) {
        $prenomSuivi = $prenomsEleves[$detailSuivi['id_eleve']] ?? 'Élève '.(int)$detailSuivi['id_eleve'];
    ?>
    <div class="sv-detail-eleve">
        <strong><a class="sv-lien-eleve" href="passeport.php?eleve=<?php echo (int)$detailSuivi['id_eleve']; ?>"><?php echo fgH($prenomSuivi); ?></a></strong>
        <span class="sv-n-<?php echo fgH($detailSuivi['niveau']); ?>"><?php echo fgH(fgLibelleNiveau($detailSuivi['niveau'])); ?></span>
        <span><?php echo $detailSuivi['taux'] === null ? '—' : (int)round($detailSuivi['taux']).' %'; ?></span>
        <small><?php echo fgH(fgQuand($detailSuivi['dernier'])); ?><?php echo $detailSuivi['ancien'] ? ' · résultat ancien' : ''; ?></small>
    </div>
    <?php } ?>
</div>
