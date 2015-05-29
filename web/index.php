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
// informacion de sesion en las vistas
// ------------------------------------------------------------------------

$app->hook('slim.before.dispatch', function() use ($app) {

	if ( isset($_SESSION['usuario']) ) {
		$app->view()->setData('session', $_SESSION['usuario']);
	}

});

// ------------------------------------------------------------------------
// rutas
// ------------------------------------------------------------------------

foreach (glob('./rutas/*.php') as $ruta) {
    require $ruta;
}	

$app->run();