<?php

// La V2 n'a plus de connexion propre à School Monsters. Le portail commun
// porte l'identité et le SSO choisit ensuite l'espace élève ou enseignant.
header('Location: /schoolmonsters/sso.php', true, 302);
exit;
