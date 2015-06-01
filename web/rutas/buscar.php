<?php

$app->post('/buscar', function () use ($app) {

	$q = $app->request->params('q');

	try {

		$query = $app->db->prepare("SELECT * FROM subastas WHERE titulo LIKE CONCAT('%', :q, '%')");
		$query->execute([
			':q' => $q
		]);
		
		$data = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
	}

	$app->render('resultados.html', [
			'subastas' => $data,
			'q' => $q
	]);

})->name('buscar-subastas');