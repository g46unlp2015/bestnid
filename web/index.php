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

$app->container->singleton('db', function () use ($config) {
	return new PDO('mysql:host='.$config['db.host'].';dbname='.$config['db.name'].';charset=utf8', 
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
// informacion global para las vistas
// ------------------------------------------------------------------------

$app->container->set('categorias', function () use ($app) {
	
	try {

		$query = $app->db->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
		$query->execute();
		$categorias = $query->fetchAll(PDO::FETCH_ASSOC);
		

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
	}
 
	return array_column($categorias, 'nombre', 'id');

});

$app->hook('slim.before.dispatch', function() use ($app) {

	// session
	if ( isset($_SESSION['usuario']) ) {
		$app->view()->setData('session', $_SESSION['usuario']);
	}

	$app->view()->setData('categorias', $app->categorias);

});

// ------------------------------------------------------------------------
// rutas
// ------------------------------------------------------------------------

foreach (glob('./rutas/*.php') as $ruta) {
    require $ruta;
}	

$app->run();