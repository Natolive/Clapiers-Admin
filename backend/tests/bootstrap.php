<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// dev & prod read env vars straight from the container (see disable_dotenv in
// composer.json); there is no base backend/.env. Tests load their values from
// backend/.env.test.
if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env.test', 'test');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
