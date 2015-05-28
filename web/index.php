<?php
require './dependencias/autoload.php';
require './config.php';

// ------------------------------------------------------------------------
// setup
// ------------------------------------------------------------------------

session_start();

$app = new \Slim\Slim (array(
	'view' => new \Slim\Views\Twig(),
	'templates.path' => './vistas'
));

$app->container->singleton('db', function() use ($config) {
	return new PDO('mysql:host='.$config['db.host'].';dbname='.$config['db.name'], 
		$config['db.user'], $config['db.pass']
	);
});


$app->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
require './rutas/buscar.php';

$app->run();