<?php

$app->get('/', function () use ($app) {

	try {

		$query = $app->db->prepare("SELECT *, DATEDIFF(finalizacion,NOW()) AS dias 
									FROM subastas WHERE finalizacion >= NOW()
									ORDER BY clicks DESC, dias ASC");
		$query->execute();
		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
	}

	$app->render('index.html', [
		'subastas' => $subastas
	]);

})->name('index');