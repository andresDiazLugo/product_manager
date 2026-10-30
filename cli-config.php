<?php

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;

require_once __DIR__ . '/vendor/autoload.php';

$entityManager = require __DIR__ . '/config/doctrine.php';

$config = new PhpFile(__DIR__ . '/migrations.php');

return DependencyFactory::fromEntityManager(
    $config,
    new ExistingEntityManager($entityManager)
);