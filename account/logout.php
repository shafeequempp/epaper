<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
subscriber_logout();
redirect(url('index.php'));
