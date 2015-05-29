<?php

$app->group('/admin', function () use ($app) {

	$app->group('/usuarios', function () use ($app) {

		// ------------------------------------------------------------------------
		// listar
		// ------------------------------------------------------------------------

		$app->get('/', function () use ($app) {
			
			try {

				$query = $app->db->prepare("SELECT id, username, nombre FROM usuarios");
				$query->execute();
				$data = $query->fetchAll(PDO::FETCH_ASSOC);

			}

			catch (PDOException $e) {
				$app->flash('error', 'hubo un error en la base de datos');
			}

			$app->render('admin/usuarios.html', array(
				'usuarios' => $data)
			);

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

		$app->get('/:id/borrar', function ($id) use ($app) {
			$id = (int)$id;

			try {

				$query = $app->db->prepare("DELETE FROM usuarios WHERE id = :id LIMIT 1");
				
				$return = $query->execute(
					array(
						':id' => $id 
					)
				);

			}

			catch (PDOException $e) {
				$app->flash('error', 'hubo un error en la base de datos');
			}
			
			if ( $return == 0 ) {
				$app->flash('mensaje', 'se ha borrado el usuario!');
			} else {
				$app->flash('error', 'no se ha encontrado ese usuario');
			}

			$app->redirect('/admin/usuarios');

		})->name('admin-borrar-usuario');

	});

});

