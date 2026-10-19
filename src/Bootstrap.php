<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Logger;
use App\Core\Router;
use App\Core\Session;

$config = require __DIR__ . '/../config/app.php';
Session::start($config['session_name']);
return ['config' => $config, 'router' => new Router(), 'database' => new Database($config['database']), 'logger' => new Logger(__DIR__ . '/../storage/logs/app.log')];
