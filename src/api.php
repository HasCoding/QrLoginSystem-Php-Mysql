<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\ApiController;

// Tüm işlemi ApiController'a devret
ApiController::handleRequest();
?>