// Les anciennes fiches gardent leurs sources ; seule la mascotte rejoint le titre commun.
for (const titre of document.querySelectorAll('.gx-app-schoolmonsters .container_12 > :is(.text4,.text4bis)')) {
    const mascotte = titre.nextElementSibling;
    if (!titre.querySelector(':scope > h1') || !mascotte?.matches('.grid_2') ||
        !mascotte.querySelector(':scope > img') || mascotte.querySelector('a')) continue;
    titre.classList.add('sm-lecon-titre');
    titre.classList.remove('gx-ligne-titre');
    const intitule = document.createElement('div');
    intitule.className = 'sm-lecon-intitule';
    while (titre.firstChild) intitule.append(titre.firstChild);
    mascotte.classList.remove('grid_2');
    mascotte.classList.add('sm-lecon-mascotte');
    titre.append(intitule, mascotte);
}
