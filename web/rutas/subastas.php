<?php

$app->group('/subastas', function () use ($app, $auth) {

// ------------------------------------------------------------------------
// listado
// ------------------------------------------------------------------------

	// por categoria
	$app->get('/categoria/:id', function ($id) use ($app) {

		$subastas = array();

		if ( ! array_key_exists($id, $app->categorias) ) {
			$app->flash('error', 'No existe tal categoria.');
			$app->redirect($app->urlFor('index'));
		}

		try {

			$query = $app->db->prepare(
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias, fotos.ruta AS foto
				FROM subastas 
				INNER JOIN fotos ON subastas.id = fotos.id_subasta
				WHERE finalizacion >= NOW() AND id_categoria = :id
				GROUP BY id
				ORDER BY clicks DESC, dias ASC"
			);
			
			$ok = $query->execute([':id' => (int)$id]);

			$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
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
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias, fotos.ruta AS foto
				FROM subastas 
				INNER JOIN fotos ON subastas.id = fotos.id_subasta
				WHERE finalizacion >= NOW()
				GROUP BY id
				ORDER BY dias ASC"
			);

			$ok = $query->execute();

			$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
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
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias, fotos.ruta AS foto
				FROM subastas 
				INNER JOIN fotos ON subastas.id = fotos.id_subasta
				WHERE finalizacion >= NOW()
				GROUP BY id
				ORDER BY clicks DESC"
			);

			$ok = $query->execute();

			$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
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
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias, fotos.ruta AS foto 
				FROM subastas 
				INNER JOIN fotos ON subastas.id = fotos.id_subasta
				WHERE finalizacion >= NOW()
				GROUP BY id
				ORDER BY alta DESC"
			);

			$ok = $query->execute();

			$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		$app->render('subastas/listado.html', [
			'tipo' => 'Ultimas subastas',
			'subastas' => $subastas
		]);

	})->name('ordenar-ultimas');

// ------------------------------------------------------------------------
// detalle
// ------------------------------------------------------------------------

	$app->get('/:id', function ($id) use ($app) {

		$subasta = array();
		$fotos = array();

		try {
			// datos subasta
			$query = $app->db->prepare(
				"SELECT *, DATEDIFF(finalizacion,NOW()) AS dias
				FROM subastas 
				WHERE id = :id"
			);
			$query->execute([':id' => $id]);
			$subasta = $query->fetch(PDO::FETCH_ASSOC);

			// fotos
			$query = $app->db->prepare(
				"SELECT ruta FROM fotos WHERE id_subasta = :id"
			);
			$query->execute([':id' => $id]);
			$fotos = $query->fetchAll(PDO::FETCH_ASSOC);

			// oferta
			$oferta = [];
			if (isset($_SESSION['usuario'])) {
				$query = $app->db->prepare(
					"SELECT ofertas.*
					FROM usuarios
					INNER JOIN ofertas ON usuarios.id = ofertas.id_usuario
					WHERE usuarios.id = :id_usuario AND ofertas.id_subasta = :id_subasta
					LIMIT 1"
				);
				$query->execute([
					':id_subasta' => $id,
					':id_usuario' => $_SESSION['usuario']['id']
				]);
				$oferta = $query->fetch(PDO::FETCH_ASSOC);
			}
			
		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		try {
			// incremento un click
			$query = $app->db->prepare("UPDATE subastas SET clicks = clicks+1 WHERE id = :id");
			$query->execute([ ':id' => $id ]);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base datos al contar un click');
			$app->redirect($app->urlFor('index'));
		}

		$app->render('subastas/detalle.html', [
				'subasta' => $subasta,
				'oferta' => $oferta,
				'fotos' => $fotos
			]
		);

	})->conditions(['id' => '\d+'])->name('subasta');

// ------------------------------------------------------------------------
// publicar subasta
// ------------------------------------------------------------------------

	$app->get('/publicar', $auth(), function () use ($app) {

		$app->render('subastas/publicar.html');

	})->name('publicar-subasta');


	$app->post('/publicar', $auth(), function () use ($app) {

		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());

		$rutas = array();

		if ( ! empty($_FILES['fotos']['name'][0]) ) {

			$fotos = $_FILES['fotos'];

			foreach ($fotos['name'] as $key => $foto_nombre) {

				$foto_tmp = $fotos['tmp_name'][$key];
				$foto_ext = strtolower( end( explode('.', $foto_nombre) ) );

				$foto_nuevo_nombre = uniqid('', true) . '.' . $foto_ext;
				$foto_destino = $app->config['uploads.ruta'] . $foto_nuevo_nombre;

				if ( ! move_uploaded_file($foto_tmp, $foto_destino) ) {
					$app->flash('error.fotos', "[{$foto_nombre}] fallo al moverse en el servidor.");
					$app->flash('anterior', $app->request->params());
					$app->redirect($app->urlFor('publicar-subasta'));
				} else {
					array_push($rutas, $foto_nuevo_nombre);
				}
			}
		}

		try {
			// agrego una subasta
			$query = $app->db->prepare(
				"INSERT INTO subastas (titulo, descripcion, id_categoria, finalizacion, id_usuario) 
				VALUES (:titulo, :descripcion, :id_categoria, :finalizacion, :id_usuario)"
			);

			$finalizacion = date('Y-m-d', strtotime('+' . $duracion . ' days'));

			$query->execute([
				':titulo' => $titulo,
				':descripcion' => $descripcion,
				':id_categoria' => $id_categoria,
				':finalizacion' => $finalizacion,
				':id_usuario' => $_SESSION['usuario']['id']
			]);	

		} catch (PDOException $e) {
			$app->flash('error', 'No se pudo publicar tu subasta, por un error en la base de datos.');
			$app->redirect($app->urlFor('publicar-subasta'));
		}

		// obtengo el id de la ultima subasta agregada
		$id_subasta = $app->db->lastInsertId();

		try {
			// agrego las rutas a la tabla de fotos
			foreach ($rutas as $ruta) {

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
			$app->flash('error', 'Hubo un error en la base de datos, al agregar las fotos.');
			$app->redirect($app->urlFor('publicar-subasta'));
		}

		$app->flash('mensaje', 'Tu subasta se ha subido correctamente.');
		$app->redirect($app->urlFor('subasta', ['id' => $id_subasta]));

	})->name('publicar-subasta-post');

// ------------------------------------------------------------------------
// modificar subasta (aca es donde me doy cuenta que tengo que refactorizar)
// ------------------------------------------------------------------------

	$app->get('/modificar/:id', $auth(), function ($id) use ($app) {

		$subasta = array();
		$fotos = array();

		try {
			// datos
			$query = $app->db->prepare(
				"SELECT *, DATEDIFF(finalizacion,NOW()) AS dias
				FROM subastas 
				WHERE id = :id AND id_usuario = :id_usuario"
			);
			$query->execute([
				':id' => $id,
				':id_usuario' => $_SESSION['usuario']['id']
			]);
			$subasta = $query->fetch(PDO::FETCH_ASSOC);

			// fotos
			$query = $app->db->prepare(
				"SELECT id, ruta FROM fotos WHERE id_subasta = :id"
			);
			$query->execute([':id' => $id]);
			$fotos = $query->fetchAll(PDO::FETCH_ASSOC);
			
		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		if ( $query->rowCount() == 0 ) {
			$app->flash('error', 'La subasta no existe o no tienes permiso para modificarla.');
			$app->redirect($app->urlFor('index'));
		} 

		$alta = new DateTime($subasta['alta']);
		$now = new DateTime();
		$subasta['diasMax'] = 30 - (int)$now->diff($alta)->format('%a');

		$app->render('subastas/modificar.html', [
			'subasta' => $subasta,
			'fotos' => $fotos
		]);

	})->conditions([':id' => '\d+'])->name('modificar-subasta');


	$app->post('/modificar', $auth(), function () use ($app) {

		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());
		$rutas = array();

		if ( ! empty($_FILES['fotos']['name'][0]) ) {

			$fotos = $_FILES['fotos'];

			foreach ($fotos['name'] as $key => $foto_nombre) {

				$foto_tmp = $fotos['tmp_name'][$key];
				$foto_ext = strtolower( end( explode('.', $foto_nombre) ) );

				$foto_nuevo_nombre = uniqid('', true) . '.' . $foto_ext;
				$foto_destino = $app->config['uploads.ruta'] . $foto_nuevo_nombre;

				if ( ! move_uploaded_file($foto_tmp, $foto_destino) ) {
					$app->flash('error.fotos', "[{$foto_nombre}] fallo al moverse en el servidor.");
					$app->redirect($app->urlFor('modificar-subasta', ['id' => $id]));
				} else {
					array_push($rutas, $foto_nuevo_nombre);
				}
			}
		}

		try {
			// actualizar subasta
			$query = $app->db->prepare(
				"UPDATE subastas
				SET titulo = :titulo, descripcion = :descripcion, id_categoria = :id_categoria, finalizacion = :finalizacion
				WHERE id = :id"
			);

			$finalizacion = date('Y-m-d', strtotime('+' . $duracion . ' days'));

			$query->execute([
				':id' => $id,
				':titulo' => $titulo,
				':descripcion' => $descripcion,
				':id_categoria' => $id_categoria,
				':finalizacion' => $finalizacion,
			]);	

		} catch (PDOException $e) {
			$app->flash('error', 'No se pudo modificar tu subasta, por un error en la base de datos.');
			$app->redirect($app->urlFor('subasta', ['id' => $id]));
		}

		try {
			// agrego las rutas a la tabla de fotos
			foreach ($rutas as $ruta) {

				$query = $app->db->prepare(
					"INSERT INTO fotos (id_subasta, ruta) 
					VALUES (:id_subasta, :ruta)"
				);

				$query->execute([
					':id_subasta' => $id,
					':ruta' => $ruta
				]);	
			}

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos, al agregar las fotos.');
			$app->redirect($app->urlFor('subasta', ['id' => $id]));
		}

		$app->flash('mensaje', 'Tu subasta se ha modificado correctamente.');
		$app->redirect($app->urlFor('subasta', ['id' => $id]));

	})->name('modificar-subasta-post');


// ------------------------------------------------------------------------
// AJAX: borrar foto de la subasta
// ------------------------------------------------------------------------

	$app->post('/fotos/borrar', $auth(), function () use($app) {

		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());
		$app->response->headers->set('Content-Type', 'application/json');

		try {

			// consulta ruta
			$query = $app->db->prepare("SELECT ruta FROM fotos WHERE id = :id_foto");
			$query->execute([':id_foto' => $id_foto]);
			$foto = $query->fetch(PDO::FETCH_ASSOC);

			// borrado de la base de datos
			$query = $app->db->prepare(
				"DELETE FROM fotos WHERE id = :id_foto
				AND EXISTS(SELECT * FROM subastas 
					WHERE subastas.id_usuario = :id_usuario 
					AND subastas.id = :id_subasta)"
			);

			$query->execute([
				':id_foto' => $id_foto,
				':id_subasta' => $id_subasta,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

		} catch (PDOException $e) {
			die( json_encode( ['status' => 403, 'error' => 'Hubo un error en la base de datos.']) );
		}

		if ( $query->rowCount() == 0 ) {
			echo json_encode( ['status' => 403, 'error' => 'Esa foto no existe o no tienes permiso de borrarla.'] );
		} else {
			// borrado fisico
			unlink($app->config['uploads.ruta'].$foto['ruta']);
			echo json_encode( ['status' => 200] );
		}

	})->conditions([
		':id' => '\d+',
		':foto' => '\d+'
	])->name('borrar-foto');

// ------------------------------------------------------------------------
// borrar subasta
// ------------------------------------------------------------------------

	$app->get('/borrar/:id', $auth(), function ($id) use ($app) {

		try {
			// comprobar ofertas existentes
			$query = $app->db->prepare(
				"SELECT id FROM ofertas WHERE id_subasta = :id"
			);

			$query->execute([':id' => $id]);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		if ( $query->rowCount() > 0 ) {
			$app->flash('error', 'La subasta tiene ofertas y no se puede borrar.');
			$app->redirect($app->urlFor('subasta', ['id' => $id]));
		}

		try {

			$query = $app->db->prepare(
				"DELETE FROM subastas WHERE id = :id AND id_usuario = :id_usuario"
			);
			$query->execute([
				':id' => $id,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		// si la subasta no es del usuario o no existe
		if ( $query->rowCount() == 0 ) {
			$app->flash('error', 'La subasta que quieres borrar no es tuya o no existe.');
			$app->redirect($app->urlFor('index'));
		}

		try {
			// borrar fotos
			$fotos = [];
			$query = $app->db->prepare(
				"SELECT ruta FROM fotos WHERE id_subasta = :id"
			);
			$query->execute([':id' => $id]);
			$fotos = $query->fetchAll(PDO::FETCH_ASSOC);

			foreach ($fotos as $foto) {
				unlink($app->config['uploads.ruta'].$foto['ruta']);
			}

			$query = $app->db->prepare(
				"DELETE FROM fotos WHERE id_subasta = :id"
			);
			$query->execute([':id' => $id]);

			// --- borrar comentarios ----
			
		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		$app->flash('mensaje', 'Se ha borrado la subasta.');
		$app->redirect($app->urlFor('index'));

	})->conditions(['id' => '\d+'])->name('borrar-subasta');

// ------------------------------------------------------------------------
// ofertar subasta
// ------------------------------------------------------------------------

	$app->post('/ofertar', $auth(), function () use ($app) {
		
		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());

		try {

			$query = $app->db->prepare(
				"INSERT INTO ofertas (monto, motivo, id_subasta, id_usuario)
				VALUES (:monto, :motivo, :id_subasta, :id_usuario)"
			);

			$query->execute([
				':monto' => $monto,
				':motivo' => $motivo,
				':id_subasta' => $id_subasta,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

			$app->flash('mensaje', 'Se ha publicado tu oferta!');

		} catch (PDOException $e) {
			$app->flash('error', 'No se pudo publicar tu oferta');
		}

		$app->redirect($app->urlFor('subasta', ['id' => $id_subasta]));

	})->name('ofertar-post');

// ------------------------------------------------------------------------
// modificar oferta
// ------------------------------------------------------------------------

	$app->post('/oferta/modificar', $auth(), function () use ($app) {

		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());

		try {

			$query = $app->db->prepare(
				"UPDATE ofertas SET monto = :monto WHERE id = :id"
			);

			$query->execute([
				':id' => $id_oferta,
				':monto' => $monto
			]);

			$app->flash('mensaje', 'Se ha actualizado tu oferta');

		} catch (PDOException $e) {
			$app->flash('error', 'No se pudo actualizar tu oferta');
		}

		$app->redirect($app->urlFor('subasta', ['id' => $id_subasta]));

	})->name('modificar-oferta-post');

});
