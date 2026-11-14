<?php

/**
 * Arahkan akses http://localhost/Simalas/ ke folder public (front controller Laravel).
 */
header('Location: /Simalas/public/', true, 302);
exit;
