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

		// listar categorias
		$app->get('/', function () use ($app) {

			$app->render('admin/categorias.html');

		})->name('admin-categorias');

		
		// borrar categoria
		$app->post('/borrar/:id', function ($id) use ($app) {

			// -

		})->conditions(['id' => '\d+'])->name('admin-borrar-categoria');

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

