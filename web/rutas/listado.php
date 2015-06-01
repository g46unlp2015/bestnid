<?php

// por categoria
$app->get('/categoria/:id', function ($id) use ($app) {
	$id = (int)$id;

	if ( ! array_key_exists($id, $app->categorias) ) {
		$app->flash('error', 'No existe tal categoria.');
		$app->redirect('/');
	}

	try {

		$query = $app->db->prepare("SELECT *, DATEDIFF(finalizacion,NOW()) AS dias 
									FROM subastas WHERE finalizacion >= NOW() AND id_categoria = :id
									ORDER BY clicks DESC, dias ASC");
		$ok = $query->execute([
			':id' => $id
		]);

		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
	}

	$app->render('listado.html', [
		'tipo' => $app->categorias[$id],
		'subastas' => $subastas
	]);

})->name('categoria');


$app->group('/ordenar', function () use ($app) {

	// por finalizacion
	$app->get('/finalizacion', function () use ($app) {

		try {

			$query = $app->db->prepare("SELECT *, DATEDIFF(finalizacion,NOW()) AS dias
										FROM subastas WHERE finalizacion >= NOW()
										ORDER BY dias ASC");
			$ok = $query->execute();

			$data = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
		}

		$app->render('listado.html', [
			'tipo' => 'Próximas a finalizar',
			'subastas' => $data
		]);

	})->name('ordenar-finalizacion');

	// por popularidad
	$app->get('/clicks', function () use ($app) {

		try {

			$query = $app->db->prepare("SELECT *, DATEDIFF(finalizacion,NOW()) AS dias
										FROM subastas WHERE finalizacion >= NOW()
										ORDER BY clicks DESC");
			$ok = $query->execute();

			$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
		}

		$app->render('listado.html', [
			'tipo' => 'Subastas mas populares',
			'subastas' => $subastas
		]);

	})->name('ordenar-clicks');

	// por ultimas subastas agregadas
	$app->get('/ultimas', function () use ($app) {

		try {

			$query = $app->db->prepare("SELECT *, DATEDIFF(finalizacion,NOW()) AS dias
										FROM subastas WHERE finalizacion >= NOW()
										ORDER BY alta DESC");
			$ok = $query->execute();

			$data = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
		}

		$app->render('listado.html', [
			'tipo' => 'Ultimas subastas',
			'subastas' => $data
		]);

	})->name('ordenar-ultimas');

});