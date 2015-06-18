<?php

$app->get('/buscar', function () use ($app) {
	$req = $app->request;
	$subastas = array();	

	$q = $req->params('q');
	$id_categoria = $req->params('id_categoria');
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

	$sql_categoria = ($id_categoria == 0) ? '' : 'AND id_categoria = ' . (int)$id_categoria;

	try {

		$query = $app->db->prepare(
			"SELECT subastas.*, DATEDIFF(subastas.finalizacion,NOW()) AS dias, fotos.ruta AS foto 
			FROM subastas 
			LEFT JOIN fotos ON subastas.id = fotos.id_subasta 
			WHERE titulo LIKE CONCAT('%', :q, '%')
			AND finalizacion >= :desde AND finalizacion >= NOW()
			AND finalizacion <= :hasta
			GROUP BY subastas.id
			{$sql_categoria} {$sql_order}"
		);

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
		'id_categoria' => $id_categoria,
		'desde' => $desde,
		'hasta' => $hasta,
		'order' => ['popularidad', 'finalizacion', 'ultimas'],
		'order_selected' => $order
	];

	$app->render('subastas/resultados.html', [
			'subastas' => $subastas,
			'q' => $q,
			'filtros' => $filtros
	]);

})->name('buscar-subastas');