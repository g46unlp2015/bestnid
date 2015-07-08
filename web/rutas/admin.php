<?php

$app->group('/admin', $auth('admin'), function () use ($app) {

// ------------------------------------------------------------------------
// principal
// ------------------------------------------------------------------------

	$app->get('/', function () use($app) {

		$app->render('admin/index.html');

	})->name('admin');

// ------------------------------------------------------------------------
// categorias
// ------------------------------------------------------------------------
	
	$app->group('/categorias', function () use ($app) {

		// listar
		$app->get('/', function () use ($app) {

			$app->render('admin/categorias/index.html');

		})->name('admin-categorias');

		
		// borrar
		$app->get('/:id/borrar', function ($id) use ($app) {

			try {
				// comprobar ofertas existentes
				$query = $app->db->prepare(
					"SELECT ofertas.id FROM ofertas
					LEFT JOIN subastas ON subastas.id = ofertas.id_subasta
					WHERE subastas.id_categoria = :id"
				);

				$query->execute([':id' => $id]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
				$app->redirect($app->urlFor('admin-categorias'));
			}

			if ( $query->rowCount() > 0 ) {
				$app->flash('error', 'La categoria tiene subastas y no se puede borrar.');
				$app->redirect($app->urlFor('admin-categorias'));
			}

			try {

				$query = $app->db->prepare(
					"DELETE FROM categorias
					WHERE id = :id"
				);
				$query->execute([':id' => $id]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error con la base de datos.');
				$app->redirect($app->urlFor('admin-categorias'));
			}

			$app->flash('mensaje', 'Se borro la categoria.');
			$app->redirect($app->urlFor('admin-categorias'));

		})->conditions(['id' => '\d+'])->name('admin-borrar-categoria');

		// agregar
		$app->post('/agregar', function () use ($app) {
			$nombre = $app->request->params('nombre');
			
			try {

				$query = $app->db->prepare(
					"INSERT INTO categorias (nombre)
					VALUES (:nombre)"
				);
				$query->execute([':nombre' => $nombre]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error con la base de datos.');
				$app->redirect($app->urlFor('admin-categorias'));
			}

			$app->flash('mensaje', 'Se agrego la categoria "' . $nombre . '"');
			$app->redirect($app->urlFor('admin-categorias'));

		})->name('admin-agregar-categoria');

		// editar
		$app->get('/:id/editar', function ($id) use ($app) {

			try {
				
				$query = $app->db->prepare("SELECT * FROM categorias WHERE id = :id");
				$query->execute([':id' => $id]);
				$categoria = $query->fetch(PDO::FETCH_ASSOC);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error con la base de datos.');
				$app->redirect($app->urlFor('admin-categorias'));
			}

			$app->render('admin/categorias/editar.html', [
				'categoria' => $categoria
			]);

		})->name('admin-editar-categoria');

		$app->post('/editar', function () use ($app) {
			
			extract($app->request->params());

			if (in_array($nombre, $app->categorias)) {
				$app->flash('error', 'La categoria ya existe. Elige otro nombre');
				$app->redirect($app->urlFor('admin-editar-categoria', ['id' => $id]));
			}

			try {

				$query = $app->db->prepare("UPDATE categorias SET nombre = :nombre WHERE id = :id");
				$query->execute([
					':nombre' => $nombre,
					':id' => $id
				]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error con la base de datos.');
				$app->redirect($app->urlFor('admin-categorias'));
			}

			$app->flash('mensaje', 'Se actualizo la categoria "' . $nombre . '"');
			$app->redirect($app->urlFor('admin-categorias'));

		})->name('admin-editar-categoria-post');

	});

// ------------------------------------------------------------------------
// usuarios
// ------------------------------------------------------------------------

	$app->group('/reportes/usuarios', function () use ($app) {

		// listar
		$app->get('/', function () use ($app) {
			$req = $app->request;

			$desde = $req->params('desde') ? $req->params('desde') : date('Y-m-d', strtotime('-30 days'));
			$hasta = $req->params('hasta') ? $req->params('hasta') : date('Y-m-d');
			
			try {

				$query = $app->db->prepare(
					"SELECT * FROM usuarios
					WHERE alta >= :desde AND alta <= :hasta
					ORDER BY activo DESC, alta DESC"
				);

				$query->execute([
					':desde' => $desde,
					':hasta' => $hasta
				]);
				
				$query->execute();
				$usuarios = $query->fetchAll(PDO::FETCH_ASSOC);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
				$app->redirect($app->urlFor('admin-usuarios'));
			}

			$filtros = [
				'desde' => $desde,
				'hasta' => $hasta
			];

			$app->render('admin/reportes/usuarios.html', [
				'usuarios' => $usuarios,
				'filtros' => $filtros
			]);

		})->name('admin-usuarios');

		// borrar
		$app->get('/:id/borrar', function ($id) use ($app) {

			try {

				$query = $app->db->prepare(
					"UPDATE usuarios 
					SET activo = 0
					WHERE id = :id"
				);
				
				$query->execute([
					':id' => $id 
				]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
				$app->redirect($app->urlFor('admin-usuarios'));
			}
			
			if ( $query->rowCount() == 0 ) {
				$app->flash('error', 'No se ha encontrado ese usuario');
			} else {
				$app->flash('mensaje', 'Se ha deshabilitado el usuario.');
			}

			$app->redirect($app->urlFor('admin-usuarios'));

		})->conditions(['id' => '\d+'])->name('admin-borrar-usuario');

		// habilitar
		$app->get('/:id/habilitar', function ($id) use ($app) {
			
			try {

				$query = $app->db->prepare(
					"UPDATE usuarios 
					SET activo = 1
					WHERE id = :id"
				);
				
				$query->execute([
					':id' => $id 
				]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
				$app->redirect($app->urlFor('admin-usuarios'));
			}
			
			if ( $query->rowCount() == 0 ) {
				$app->flash('error', 'No se ha encontrado ese usuario');
			} else {
				$app->flash('mensaje', 'Se ha habilitado el usuario.');
			}

			$app->redirect($app->urlFor('admin-usuarios'));

		})->conditions(['id' => '\d+'])->name('admin-habilitar-usuario');

		// cambiar rol
		$app->post('/cambiar-rol', function () use ($app) {

			extract($app->request->params());
			$app->response->headers->set('Content-Type', 'application/json');
			
			try {

				$query = $app->db->prepare(
					"UPDATE usuarios 
					SET rol = :rol
					WHERE id = :uid"
				);
				
				$query->execute([
					':rol' => $rol,
					':uid' => $uid 
				]);

			} catch (PDOException $e) {
				die( json_encode( ['status' => 403, 'error' => 'Hubo un error en la base de datos: ' . $e->getMessage()]) );
			}
			
			if ( $query->rowCount() == 0 ) {
				echo json_encode( ['status' => 403, 'error' => 'No existe ese usuario.'] );
			} else {
				echo json_encode( ['status' => 200] );
			}

		})->conditions(['id' => '\d+'])->name('admin-cambiar-rol');

	});

// ------------------------------------------------------------------------
// reporte ventas entre dos fechas
// ------------------------------------------------------------------------

	$app->get('/reportes/ventas', function () use ($app) {
		$req = $app->request;
		$ventas = array();	

		$desde = $req->params('desde') ? $req->params('desde') : date('Y-m-d', strtotime('-30 days'));
		$hasta = $req->params('hasta') ? $req->params('hasta') : date('Y-m-d');

		try {

			$query = $app->db->prepare(
				"SELECT subastas.*, usuarios.nombre AS ganador_nombre, usuarios.email AS ganador_email
				FROM subastas 
				INNER JOIN ganadores ON ganadores.id_subasta = subastas.id
				INNER JOIN usuarios ON usuarios.id = ganadores.id_usuario
				WHERE subastas.finalizacion >= :desde AND subastas.finalizacion <= :hasta
				ORDER BY subastas.finalizacion"
			);

			$query->execute([
				':desde' => $desde,
				':hasta' => $hasta
			]);
			
			$ventas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos' . $e->getMessage());
			$app->redirect('/');
		}

		$filtros = [
			'desde' => $desde,
			'hasta' => $hasta
		];

		$app->render('admin/reportes/ventas.html', [
				'ventas' => $ventas,
				'filtros' => $filtros
		]);

	})->name('reporte-ventas');

});

