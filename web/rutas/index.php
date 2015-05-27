<?php

$app->get('/', function () use ($app) {

	try {

		$query = $app->db->prepare("SELECT * FROM subastas LIMIT 10");
		$ok = $query->execute();

		$data = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'hubo un error en la base de datos');
	}

	$app->render('index.html', array('subastas' => $data));

})->name('index');