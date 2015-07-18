<?php

$app->group('/subastas', function () use ($app, $auth) {

// ------------------------------------------------------------------------
// detalle
// ------------------------------------------------------------------------

	$app->get('/:id', function ($id) use ($app) {

		$subasta = array();
		$fotos = array();

		try {
			// datos subasta
			$query = $app->db->prepare(
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias
				FROM subastas
				INNER JOIN usuarios ON usuarios.id = subastas.id_usuario
				WHERE subastas.id = :id AND usuarios.activo = 1"
			);
			$query->execute([':id' => $id]);
			$subasta = $query->fetch(PDO::FETCH_ASSOC);

			if ( $query->rowCount() == 0 ) {
				$app->flash('error', 'No existe esa subasta');
				$app->redirect($app->urlFor('index'));
			}

			// la subasta finalizo?
			if ( $subasta['dias'] <= 0 ) {

				// chequear si existe ganador
				$query = $app->db->prepare("SELECT id FROM ganadores WHERE id_subasta = :id");
				$query->execute(['id' => $id]);
				$ganador = $query->fetch(PDO::FETCH_ASSOC);

				// si no hay ganador
				if ( empty($ganador) ) {
					// si el usuario es el dueño
					// redireccionar a la pagina de finalizacion
					if (isset($_SESSION['usuario'])) {
						if ($subasta['id_usuario'] == $_SESSION['usuario']['id']) {
							$app->redirect($app->urlFor('subasta-finalizacion', ['id' => $id]));
						}
					}
				} else {
					$app->redirect($app->urlFor('subasta-ganador', ['id' => $id]));
				}

			}

			// fotos
			$query = $app->db->prepare("SELECT ruta FROM fotos WHERE id_subasta = :id");
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

			// preguntas
			$preguntas = [];
			$query = $app->db->prepare(
				"SELECT usuarios.nombre, preguntas.*, respuestas.id AS id_respuesta, respuestas.texto AS texto_respuesta
				FROM preguntas
				INNER JOIN usuarios ON preguntas.id_usuario = usuarios.id
				LEFT JOIN respuestas ON respuestas.id_pregunta = preguntas.id
				WHERE preguntas.id_subasta = :id"
			);
			$query->execute([':id' => $id]);
			$preguntas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos' . $e->getMessage());
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
				'fotos' => $fotos,
				'preguntas' => $preguntas
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
				$foto_destino = $app->opciones['uploads.ruta'] . $foto_nuevo_nombre;

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
// modificar subasta
// ------------------------------------------------------------------------

	$app->get('/:id/modificar', $auth(), function ($id) use ($app) {

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
			$query = $app->db->prepare("SELECT id, ruta FROM fotos WHERE id_subasta = :id");
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
				$foto_destino = $app->opciones['uploads.ruta'] . $foto_nuevo_nombre;

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
			unlink($app->opciones['uploads.ruta'].$foto['ruta']);
			echo json_encode( ['status' => 200] );
		}

	})->name('borrar-foto');

// ------------------------------------------------------------------------
// borrar subasta
// ------------------------------------------------------------------------

	$app->get('/:id/borrar', $auth(), function ($id) use ($app) {

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
				unlink($app->opciones['uploads.ruta'].$foto['ruta']);
			}

			$query = $app->db->prepare(
				"DELETE FROM fotos WHERE id_subasta = :id"
			);
			$query->execute([':id' => $id]);

			// borrar respuestas
			$query = $app->db->prepare(
				"DELETE FROM respuestas
				WHERE respuestas.id_pregunta IN (SELECT id FROM preguntas WHERE preguntas.id_subasta = :id)"
			);
			$query->execute([':id' => $id]);

			// borrar preguntas
			$query = $app->db->prepare("DELETE FROM preguntas WHERE preguntas.id_subasta = :id");
			$query->execute([':id' => $id]);

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
				"UPDATE ofertas SET monto = :monto WHERE id = :id AND id_usuario = :id_usuario"
			);

			$ok = $query->execute([
				':id' => $id_oferta,
				':id_usuario' => $_SESSION['usuario']['id'],
				':monto' => $monto
			]);

		} catch (PDOException $e) {
			$app->flash('error', 'No se pudo actualizar tu oferta');
		}

		if ( $ok ) {
			$app->flash('mensaje', 'Se ha modificado tu oferta');
		} else {
			$app->flash('error', 'No existe esa oferta o no tienes permiso');
		}

		$app->redirect($app->urlFor('subasta', ['id' => $id_subasta]));

	})->name('modificar-oferta-post');

// ------------------------------------------------------------------------
// borrar oferta
// ------------------------------------------------------------------------

	$app->get('/:id/oferta/:id_oferta/borrar', $auth(), function ($id, $id_oferta) use ($app) {

		try {

			$query = $app->db->prepare(
				"DELETE FROM ofertas WHERE id = :id_oferta AND id_usuario = :id_usuario"
			);

			$query->execute([
				':id_oferta' => $id_oferta,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

		} catch (PDOException $e) {
			$app->flash('error', 'No se pudo borrar tu oferta, ' .$e->getMessage());
		}

		if ( $query->rowCount() == 0 ) {
			$app->flash('error', 'No existe esa oferta o no tienes permiso de borrarla');
		} else {
			$app->flash('mensaje', 'Se ha borrado tu oferta');
		}

		$app->redirect($app->urlFor('subasta', ['id' => $id]));

	})->conditions([
		':id' => '\d+',
		':id_oferta' => '\d+'
	])->name('borrar-oferta');

// ------------------------------------------------------------------------
// preguntar
// ------------------------------------------------------------------------

	$app->post('/preguntar', $auth(), function () use ($app) {

		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());

		try {

			$query = $app->db->prepare(
				"INSERT INTO preguntas (texto, id_usuario, id_subasta)
				VALUES (:texto, :id_usuario, :id_subasta)"
			);

			$query->execute([
				':texto' => $texto,
				':id_subasta' => $id_subasta,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
		}

		$app->flash('mensaje', 'Se ha publicado tu pregunta');
		$app->redirect($app->urlFor('subasta', ['id' => $id_subasta]));

	})->name('preguntar');

// ------------------------------------------------------------------------
// responder
// ------------------------------------------------------------------------

	$app->post('/responder', $auth(), function () use ($app) {

		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());

		try {

			$query = $app->db->prepare(
				"INSERT INTO respuestas (texto, id_pregunta)
				VALUES (:texto, :id_pregunta)"
			);

			$query->execute([
				':texto' => $texto,
				':id_pregunta' => $id_pregunta
			]);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
		}

		$app->flash('mensaje', 'Se ha publicado tu respuesta');
		$app->redirect($app->urlFor('subasta', ['id' => $id_subasta]));

	})->name('responder');

// ------------------------------------------------------------------------
// borrar pregunta
// ------------------------------------------------------------------------

	$app->get('/:id/pregunta/:id_pregunta/borrar', $auth(), function ($id, $id_pregunta) use ($app) {

		try {

			$query = $app->db->prepare(
				"DELETE FROM preguntas
				WHERE id = :id
				AND id_usuario = :id_usuario"
			);

			$query->execute([
				':id' => $id_pregunta,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		if ( $query->rowCount() == 0 ) {
			$app->flash('error', 'Esa pregunta no existe o no es tuyo');
		} else {
			$app->flash('mensaje', 'Has borrado la pregunta.');
		}

		$app->redirect($app->urlFor('subasta', ['id' => $id]));

	})->name('borrar-pregunta');

// ------------------------------------------------------------------------
// finalizacion subasta
// ------------------------------------------------------------------------

	$app->get('/:id/finalizacion', function ($id) use ($app) {

		try {
			// chequear si existe un ganador
			$query = $app->db->prepare("SELECT id FROM ganadores WHERE id_subasta = :id");
			$query->execute([':id' => $id]);
			if ( $query->rowCount() == 1 ) {
				$app->redirect($app->urlFor('subasta-ganador', ['id' => $id]));
			}

			// datos subasta
			$query = $app->db->prepare(
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias, fotos.ruta AS foto
				FROM subastas
				INNER JOIN fotos ON fotos.id_subasta = subastas.id
				WHERE subastas.id = :id AND subastas.id_usuario = :id_usuario"
			);

			$query->execute([
				':id' => $id,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

			$subasta = $query->fetch(PDO::FETCH_ASSOC);

			if ( $query->rowCount() == 0 ) {
				$app->flash('error', 'No existe esa subasta o no tienes permiso para ver esta pagina');
				$app->redirect($app->urlFor('index'));
			}

			if ( $subasta['dias'] > 0 ) {
				$app->flash('error', 'Esta subasta aun no finalizo.');
				$app->redirect($app->urlFor('subasta', ['id' => $subasta['id']]));
			}

			// ofertas
			$query = $app->db->prepare(
				"SELECT ofertas.*, usuarios.nombre AS usuario
				FROM ofertas
				INNER JOIN usuarios ON usuarios.id = ofertas.id_usuario
				WHERE id_subasta = :id"
			);

			$query->execute([
				':id' => $id
			]);

			$ofertas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		$app->render('subastas/finalizacion.html', [
			'subasta' => $subasta,
			'ofertas' => $ofertas
		]);

	})->name('subasta-finalizacion');

// ------------------------------------------------------------------------
// seleccionar ganador
// ------------------------------------------------------------------------

	$app->get('/:id/finalizacion/ganador/:id_usuario', $auth(), function ($id, $id_usuario) use ($app) {

		try {
			// datos subasta
			$query = $app->db->prepare(
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias, ofertas.monto AS ganador_monto, ofertas.motivo AS ganador_motivo
				FROM subastas
				INNER JOIN ofertas ON ofertas.id_subasta = subastas.id
				WHERE subastas.id = :id AND subastas.id_usuario = :id_usuario"
			);

			$query->execute([
				':id' => $id,
				':id_usuario' => $_SESSION['usuario']['id']
			]);

			$subasta = $query->fetch(PDO::FETCH_ASSOC);

			if ( $query->rowCount() == 0 ) {
				$app->flash('error', 'No existe esa subasta o no tienes permiso para ver esta pagina');
				$app->redirect($app->urlFor('index'));
			}

			if ( $subasta['dias'] > 0 ) {
				$app->flash('error', 'Esta subasta aun no finalizo.');
				$app->redirect($app->urlFor('subasta', ['id' => $subasta['id']]));
			}

			// chequear si existe ganador
			$query = $app->db->prepare("SELECT id FROM ganadores WHERE id_subasta = :id");
			$query->execute(['id' => $id]);

			if ( $query->rowCount() > 0 ) {
				$app->redirect($app->urlFor('subasta', ['id' => $id]));
			}

			// agregar ganador
			$query = $app->db->prepare(
				"INSERT INTO ganadores (id_subasta, id_usuario)
				VALUES (:id_subasta, :id_usuario)"
			);
			$query->execute([
				':id_subasta' => $id,
				':id_usuario' => $id_usuario
			]);

			// datos ganador
			$query = $app->db->prepare("SELECT * FROM usuarios WHERE id = :id_usuario");
			$query->execute([':id_usuario' => $id_usuario]);
			$ganador = $query->fetch(PDO::FETCH_ASSOC);

			// datos subastador
			$query = $app->db->prepare("SELECT * FROM usuarios WHERE id = :id_usuario");
			$query->execute([':id_usuario' => $subasta['id_usuario']]);
			$subastador = $query->fetch(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos' . $e->getMessage());
			$app->redirect($app->urlFor('index'));
		}

		// mails
		$headers = "From: Bestnid <no-responder@bestnid.com.ar>\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=utf-8\r\n";

		$para = $ganador['email'];
		$asunto = 'Ganaste la subasta: '. $subasta['titulo'];

		$body = '<p>Hola, '. $ganador['nombre'] .'! Ganaste la subasta <strong>"'. $subasta['titulo'] .'"</strong>.</p>';
		$body .= '<p>Tu monto fue de <strong>$'. $subasta['ganador_monto'] .'</strong>';
		$body .= '<p>Estos son los datos del subastador para que finalicen la operación: </p>';
		$body .= '<ul>';
		$body .= '<li>Nombre: ' . $subastador['nombre'] . '</li>';
		$body .= '<li>Email: ' . $subastador['email'] . '</li>';
		if ( ! empty($subastador['ciudad']) ) $body .= '<li>Ciudad: ' . $subastador['ciudad'] . '</li>';
		if ( ! empty($subastador['pais']) ) $body .= '<li>País: ' . $subastador['pais'] . '</li>';
		$body .= '</ul>';

		// mail para el ganador
		mail($para, $asunto, $body, $headers);

		$para = $subastador['email'];
		$asunto = 'Ganador de la subasta: ' . $subasta['titulo'];

		$body = '<p>Hola, '. $subastador['nombre'] .'! Seleccionaste ganador de tu subasta <strong>"'. $subasta['titulo'] .'"</strong>.</p>';
		$body .= '<p>Su motivo fue: <blockquote><em>"'. $subasta['ganador_motivo'] .'"</em></blockquote>Con un monto de: <strong>$'. $subasta['ganador_monto'] .'</strong></p>';
		$body .= '<p>Estos son los datos del ganador para que finalicen la operación, </p>';
		$body .= '<ul>';
		$body .= '<li>Nombre: ' . $ganador['nombre'] . '</li>';
		$body .= '<li>Email: ' . $ganador['email'] . '</li>';
		$body .= '<li>Direccion: ' . $ganador['calle'] . '</li>';
		if ( ! empty($ganador['piso']) ) {
			$body .= '<li>Piso: ' . $ganador['piso'] . '</li>';
			$body .= '<li>Dpto: ' . $ganador['dpto'] . '</li>';
		}
		if ( ! empty($ganador['ciudad']) ) $body .= '<li>Ciudad: ' . $ganador['ciudad'] . '</li>';
		if ( ! empty($ganador['pais']) ) $body .= '<li>País: ' . $ganador['pais'] . '</li>';
		$body .= '</ul>';

		// mail para el subastador
		mail($para, $asunto, $body, $headers);

		$app->redirect($app->urlFor('subasta', ['id' => $id]));

	})->name('subasta-seleccionar-ganador');

// ------------------------------------------------------------------------
// ver ganador
// ------------------------------------------------------------------------

	$app->get('/:id/ganador', function ($id) use ($app) {

		try {
			// chequear si existe un ganador
			$query = $app->db->prepare(
				"SELECT usuarios.nombre
				FROM ganadores
				INNER JOIN usuarios ON usuarios.id = ganadores.id_usuario
				WHERE ganadores.id_subasta = :id"
			);
			$query->execute([':id' => $id]);

			if ( $query->rowCount() == 0 ) {
				$app->flash('error', 'No existe ganador');
				$app->redirect($app->urlFor('index'));
			}

			$ganador = $query->fetch(PDO::FETCH_ASSOC);

			// datos subasta
			$query = $app->db->prepare(
				"SELECT subastas.*, fotos.ruta AS foto
				FROM subastas
				INNER JOIN fotos ON fotos.id_subasta = subastas.id
				WHERE subastas.id = :id"
			);
			$query->execute([':id' => $id]);

			$subasta = $query->fetch(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		$app->render('subastas/ganador.html', [
			'subasta' => $subasta,
			'ganador' => $ganador
		]);

	})->name('subasta-ganador');

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
				INNER JOIN usuarios ON usuarios.id = subastas.id_usuario
				WHERE finalizacion >= NOW()
				AND id_categoria = :id
				AND usuarios.activo = 1
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
				INNER JOIN usuarios ON usuarios.id = subastas.id_usuario
				WHERE finalizacion >= NOW()
				AND usuarios.activo = 1
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
				INNER JOIN usuarios ON usuarios.id = subastas.id_usuario
				WHERE finalizacion >= NOW()
				AND usuarios.activo = 1
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
				INNER JOIN usuarios ON usuarios.id = subastas.id_usuario
				WHERE finalizacion >= NOW()
				AND usuarios.activo = 1
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

});
