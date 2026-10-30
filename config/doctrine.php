<?php

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar las variables del archivo .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Configurar los modelos de Doctrine
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/../app/models'],
    isDevMode: true
);

$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => $_ENV['DB_HOST'],
    'port' => (int) $_ENV['DB_PORT'],
    'dbname' => $_ENV['DB_NAME'],
    'user' =>  $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset' => 'utf8mb4',
], $config);

return new EntityManager($connection, $config);