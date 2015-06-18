<?php

$app->group('/admin', $auth('admin'), function () use ($app) {

	$app->group('/usuarios', function () use ($app) {

		// ------------------------------------------------------------------------
		// listar
		// ------------------------------------------------------------------------

		$app->get('/', function () use ($app) {
			
			try {

				$query = $app->db->prepare(
					"SELECT id, email, nombre FROM usuarios"
				);
				
				$query->execute();
				$data = $query->fetchAll(PDO::FETCH_ASSOC);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
			}

			$app->render('admin/usuarios.html', [
				'usuarios' => $data
			]);

		})->name('admin-usuarios');

		// ------------------------------------------------------------------------
		// agregar
		// ------------------------------------------------------------------------

		$app->post('/agregar', function() use ($app) {
			
			// igual que registracion, pero con rol

		})->name('admin-agregar-usuario');

		// ------------------------------------------------------------------------
		// borrar
		// ------------------------------------------------------------------------

		$app->get('/borrar/:id', function ($id) use ($app) {

			try {

				$query = $app->db->prepare(
					"DELETE FROM usuarios WHERE id = :id LIMIT 1"
				);
				
				$res = $query->execute([
					':id' => (int)$id 
				]);

			} catch (PDOException $e) {
				$app->flash('error', 'Hubo un error en la base de datos');
			}
			
			if ( $res == 0 ) {
				$app->flash('error', 'no se ha encontrado ese usuario');
			} else {
				$app->flash('mensaje', 'se ha borrado el usuario!');
			}

			$app->redirect('/admin/usuarios');

		})->conditions(['id' => '\d+'])->name('admin-borrar-usuario');

	});

});

