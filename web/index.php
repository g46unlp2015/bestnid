<?php
require './dependencias/autoload.php';

// ------------------------------------------------------------------------
// setup
// ------------------------------------------------------------------------

session_start();

$app = new \Slim\Slim (array(
	'view' => new \Slim\Views\Twig(),
	'templates.path' => './vistas'
));

$app->container->singleton('db', function() {
	return new PDO('mysql:host=127.0.0.1;dbname=bestnid', 'root', '');
});

// ------------------------------------------------------------------------
// vistas
// ------------------------------------------------------------------------

$view = $app->view();

$view->parserOptions = array(
    'debug' => true,
);

$view->parserExtensions = array(
    new \Slim\Views\TwigExtension()
);

// ------------------------------------------------------------------------
// rutas
// ------------------------------------------------------------------------

require './rutas/index.php';
require './rutas/usuarios.php';

$app->run();