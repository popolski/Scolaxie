<?php

// Ancien point d'écriture V1, hors SSO : modifiait l'ordre du référentiel en
// base sans passer par la V2. Sans lien nulle part depuis la V2. Neutralisé
// le 22/09/2026 (audit résidus V1).
http_response_code(403);
exit;
