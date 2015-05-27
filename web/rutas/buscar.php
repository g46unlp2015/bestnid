<?php

$app->post('/buscar', function () use ($app) {

	$q = $app->request->params('q');

	try {

		$query = $app->db->prepare("SELECT * FROM subastas WHERE titulo LIKE CONCAT('%', :q, '%')");
		$query->execute(
			array(':q' => $q)
		);
		
		$data = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'hubo un error en la base de datos');
	}

	$app->render('resultados.html', 
		array(
			'subastas' => $data,
			'q' => $q
		)
	);

})->name('buscar-subastas');