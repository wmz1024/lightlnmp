<?php

require __DIR__ . '/../app/bootstrap.php';

(new Router())->dispatch($_SERVER['REQUEST_METHOD'], $_GET['r'] ?? 'dashboard');
