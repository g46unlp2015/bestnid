<?php

$app->get('/buscar', function () use ($app) {
	$req = $app->request;

	$q = $req->params('q');
	$cat_id = $req->params('cat_id');
	$desde = $req->params('desde') ? $req->params('desde') : date('Y-m-d');
	$hasta = $req->params('hasta') ? $req->params('hasta') : date('Y-m-d', strtotime('+30 days'));
	$order = $req->params('order') ? $req->params('order') : 'popularidad';

	switch ($order) {
		case 'popularidad':
			$sql_order = 'ORDER BY clicks DESC';
			break;
		case 'finalizacion':
			$sql_order = 'ORDER BY dias ASC';
			break;
		case 'ultimas':
			$sql_order = 'ORDER BY alta ASC';
			break;
		default: 
			$sql_order = '';
	}

	$sql_categoria = ($cat_id == 0) ? '' : 'AND id_categoria = ' . (int)$cat_id;

	try {

		$query = $app->db->prepare(
			"SELECT *, DATEDIFF(finalizacion,NOW()) AS dias 
			FROM subastas 
			WHERE titulo LIKE CONCAT('%', :q, '%')
			AND finalizacion >= :desde AND finalizacion >= NOW()
			AND finalizacion <= :hasta
			{$sql_categoria} {$sql_order}");

		$query->execute([
			':q' => $q,
			':desde' => $desde,
			':hasta' => $hasta
		]);
		
		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}

	$filtros = [
		'cat_id' => $cat_id,
		'desde' => $desde,
		'hasta' => $hasta,
		'order' => ['popularidad', 'finalizacion', 'ultimas'],
		'order_selected' => $order
	];

	$app->render('resultados.html', [
			'subastas' => $subastas,
			'q' => $q,
			'filtros' => $filtros
	]);

})->name('buscar-subastas');