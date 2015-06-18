<?php

// ------------------------------------------------------------------------
// detalle
// ------------------------------------------------------------------------

$app->get('/subastas/:id', function ($id) use ($app) {

	$subasta = array();
	$fotos = array();

	try {
		// datos subasta
		$query = $app->db->prepare(
			"SELECT *, DATEDIFF(finalizacion,NOW()) AS dias
			FROM subastas 
			WHERE id = :id"
		);

		$query->execute([':id' => (int)$id]);

		$subasta = $query->fetch(PDO::FETCH_ASSOC);

		// fotos
		$query = $app->db->prepare(
			"SELECT ruta FROM fotos WHERE id_subasta = :id"
		);

		$query->execute([':id' => (int)$id]);

		$fotos = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}

	try {
		// incremento un click
		$query = $app->db->prepare("UPDATE subastas SET clicks = clicks+1 WHERE id = :id");
		$query->execute([ ':id' => (int)$id ]);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base datos al contar un click');
		$app->redirect('/');
	}

	$app->render('subastas/detalle.html', [
			'subasta' => $subasta,
			'fotos' => $fotos
		]
	);

})->conditions(['id' => '\d+'])->name('subasta');

// ------------------------------------------------------------------------
// publicar
// ------------------------------------------------------------------------

$app->get('/subastas/publicar', $auth(), function () use ($app) {
	$app->render('subastas/publicar.html');
})->name('publicar-subasta');

$app->post('/subastas/publicar', $auth(), function () use ($app) {
	$req = $app->request;

	// extrae los parametros en variables del mismo nombre que su "key"
	extract($req->params());

	$fotos = array();
	$errores = array();

	if ( ! empty($_FILES['fotos']['name']) ) {

		$uploads = $_FILES['fotos'];
		$permitidas = ['jpg', 'png', 'jpeg'];

		foreach ($uploads['name'] as $key => $foto_nombre) {

			$foto_tmp = $uploads['tmp_name'][$key];
			$foto_size = $uploads['size'][$key];
			$foto_error = $uploads['error'][$key];
			$foto_ext = strtolower( end( explode('.', $foto_nombre) ) );

			if (in_array($foto_ext, $permitidas)) {

				if ($foto_error === 0) {

					if ($foto_size <= 2097152) {

						$foto_nuevo_nombre = uniqid('', true) . '.' . $foto_ext;
						$foto_destino = 'uploads/' . $foto_nuevo_nombre;

						if ( ! move_uploaded_file($foto_tmp, $foto_destino) ) {
							$errores['fotos'][$key] = "[{$foto_nombre}] fallo al moverse a /uploads.";
						} else {
							array_push($fotos, $foto_nuevo_nombre);
						}

					} else {
						$errores['fotos'][$key] = "[{$foto_nombre}] es mayor a 2MB.";
					}

				} else {
					$errores['fotos'][$key] = "[{$foto_nombre}] con error {$foto_error}.";
				}

			} else {
				$errores['fotos'][$key] = "[{$foto_nombre}] con extension ({$foto_ext}) no permitida.";
			}
		}
	}

	if ( ! empty($errores) ) {
		$app->flash('errores', $errores);
		$app->flash('anterior', $req->params());
		$app->redirect($app->urlFor('publicar-subasta'));
	}

	try {
		// agrego una subasta
		$query = $app->db->prepare(
			"INSERT INTO subastas (titulo, descripcion, id_categoria, finalizacion, id_usuario) 
			VALUES (:titulo, :descripcion, :id_categoria, :finalizacion, :id_usuario)"
		);

		$finalizacion = date('Y-m-d', strtotime('+' . (int)$duracion . ' days'));

		$query->execute([
			':titulo' => $titulo,
			':descripcion' => $descripcion,
			':id_categoria' => $id_categoria,
			':finalizacion' => $finalizacion,
			':id_usuario' => $_SESSION['usuario']['id']
		]);	

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect($app->urlFor('publicar-subasta'));
	}

	// obtengo el id de la ultima subasta agregada
	$id_subasta = $app->db->lastInsertId();

	try {
		// agrego las rutas a la tabla de fotos
		foreach ($fotos as $ruta) {

			$query = $app->db->prepare(
				"INSERT INTO fotos (id_subasta, ruta) 
				VALUES (:id_subasta, :ruta)"
			);

			$query->execute([
				':id_subasta' => $id_subasta,
				':ruta' => $ruta
			]);	
		}

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos, subiendo las fotos');
		$app->redirect($app->urlFor('publicar-subasta'));
	}

	$app->flash('mensaje', 'Tu subasta se ha subido correctamente.');
	$url = $app->urlFor('subasta', ['id' => $id_subasta]);
	$app->redirect($url);

})->name('publicar-subasta-post');

// ------------------------------------------------------------------------
// listado
// ------------------------------------------------------------------------

// por categoria
$app->get('/categoria/:id', function ($id) use ($app) {

	$subastas = array();

	if ( ! array_key_exists($id, $app->categorias) ) {
		$app->flash('error', 'No existe tal categoria.');
		$app->redirect('/');
	}

	try {

		$query = $app->db->prepare(
			"SELECT subastas.*, DATEDIFF(subastas.finalizacion,NOW()) AS dias, fotos.ruta AS foto
			FROM subastas 
			LEFT JOIN fotos ON subastas.id = fotos.id_subasta
			WHERE finalizacion >= NOW() AND id_categoria = :id
			GROUP BY subastas.id
			ORDER BY clicks DESC, dias ASC"
		);
		
		$ok = $query->execute([':id' => (int)$id]);

		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}

	$app->render('subastas/listado.html', [
		'tipo' => $app->categorias[$id],
		'subastas' => $subastas
	]);

})->conditions(['id' => '\d+'])->name('categoria');

// por finalizacion
$app->get('/finalizacion', function () use ($app) {

	$subastas = array();

	try {

		$query = $app->db->prepare(
			"SELECT subastas.*, DATEDIFF(subastas.finalizacion,NOW()) AS dias, fotos.ruta AS foto
			FROM subastas 
			LEFT JOIN fotos ON subastas.id = fotos.id_subasta
			WHERE finalizacion >= NOW()
			GROUP BY subastas.id
			ORDER BY dias ASC"
		);

		$ok = $query->execute();

		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}

	$app->render('subastas/listado.html', [
		'tipo' => 'Próximas a finalizar',
		'subastas' => $subastas
	]);

})->name('ordenar-finalizacion');

// por popularidad
$app->get('/popularidad', function () use ($app) {

	$subastas = array();

	try {

		$query = $app->db->prepare(
			"SELECT subastas.*, DATEDIFF(subastas.finalizacion,NOW()) AS dias, fotos.ruta AS foto
			FROM subastas 
			LEFT JOIN fotos ON subastas.id = fotos.id_subasta
			WHERE finalizacion >= NOW()
			GROUP BY subastas.id
			ORDER BY clicks DESC"
		);

		$ok = $query->execute();

		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}

	$app->render('subastas/listado.html', [
		'tipo' => 'Subastas mas populares',
		'subastas' => $subastas
	]);

})->name('ordenar-popularidad');

// por ultimas subastas agregadas
$app->get('/ultimas', function () use ($app) {

	$subastas = array();

	try {

		$query = $app->db->prepare(
			"SELECT subastas.*, DATEDIFF(subastas.finalizacion,NOW()) AS dias, fotos.ruta AS foto 
			FROM subastas 
			LEFT JOIN fotos ON subastas.id = fotos.id_subasta
			WHERE finalizacion >= NOW()
			GROUP BY subastas.id
			ORDER BY alta DESC"
		);

		$ok = $query->execute();

		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}

	$app->render('subastas/listado.html', [
		'tipo' => 'Ultimas subastas',
		'subastas' => $subastas
	]);

})->name('ordenar-ultimas');