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
		$app->get('/borrar/:id', function ($id) use ($app) {

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
		$app->get('/editar/:id', function ($id) use ($app) {

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

			try {

				$query = $app->db->prepare(
					"UPDATE categorias SET nombre = :nombre WHERE id = :id"
				);
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

	$app->group('/usuarios', function () use ($app) {

		// listar
		$app->get('/', function () use ($app) {
			
			try {

				$query = $app->db->prepare(
					"SELECT id, rol, email, nombre FROM usuarios"
				);
				
				$query->execute();
				$data = $query->fetchAll(PDO::FETCH_ASSOC);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
				$app->redirect($app->urlFor('admin-usuarios'));
			}

			$app->render('admin/usuarios.html', [
				'usuarios' => $data
			]);

		})->name('admin-usuarios');

		// agregar
		$app->post('/agregar', function() use ($app) {
			
			// igual que registracion, pero con rol

		})->name('admin-agregar-usuario');

		// borrar
		$app->get('/borrar/:id', function ($id) use ($app) {

			try {

				$query = $app->db->prepare(
					"DELETE FROM usuarios WHERE id = :id LIMIT 1"
				);
				
				$query->execute([
					':id' => $id 
				]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
				$app->redirect($app->urlFor('admin-usuarios'));
			}
			
			if ( $query->rowCount() == 0 ) {
				$app->flash('error', 'no se ha encontrado ese usuario');
			} else {
				$app->flash('mensaje', 'se ha borrado el usuario!');
			}

			$app->redirect($app->urlFor('admin-usuarios'));

		})->conditions(['id' => '\d+'])->name('admin-borrar-usuario');

	});

});

