<?php

$app->get('/', function () use ($app) {

	$subastas = array();

	try {

		$query = $app->db->prepare(
			"SELECT subastas.*, DATEDIFF(subastas.finalizacion,NOW()) AS dias, fotos.ruta AS foto 
			FROM subastas 
			LEFT JOIN fotos ON subastas.id = fotos.id_subasta
			WHERE finalizacion >= NOW()
			GROUP BY subastas.id
			ORDER BY clicks DESC, dias ASC"
		);
		
		$query->execute();
		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}
	
	$app->render('index.html', [
		'subastas' => $subastas
	]);

})->name('index');