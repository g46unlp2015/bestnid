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
// informacion global
// ------------------------------------------------------------------------

$app->container->set('config', $config);

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

	// sesion
	if ( isset($_SESSION['usuario']) ) {
		$app->view()->setData('usuario', $_SESSION['usuario']);
	}
	
	// categorias
	$app->view()->setData('categorias', $app->categorias);

});

// ------------------------------------------------------------------------
// autenticacion
// ------------------------------------------------------------------------

$auth = function ( $rol = 'miembro' ) {

    return function () use ( $rol ) {

    	$app = \Slim\Slim::getInstance();

    	if ( isset($_SESSION['usuario']) ) {
    		if ($_SESSION['usuario']['rol'] !== $rol ) {	            
	            $app->flash('error', 'No tienes permiso para ver esta pagina.');
	            $app->redirect('/login');
        	}

        } else {
        	$app->flash('error', 'Necesitas registrarte o iniciar sesión.');
	        $app->redirect('/login');
        }

    };
    
};

// ------------------------------------------------------------------------
// rutas
// ------------------------------------------------------------------------

foreach (glob('./rutas/*.php') as $ruta) {
    require $ruta;
}	

$app->run();