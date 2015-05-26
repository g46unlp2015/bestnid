<?php

$app->get('/subastas', function () use ($app) {

	$query = $app->db->prepare("SELECT * FROM subastas");
	$ok = $query->execute();

	if ( $ok ) {
		$data = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$app->flash('error', 'hubo un error con la base de datos');
	}
	
	$app->render('subastas.html', array('subastas' => $data));

})->name('subastas');