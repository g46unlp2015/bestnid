<?php

// ------------------------------------------------------------------------
// registracion
// ------------------------------------------------------------------------

$app->get('/registracion', function () use ($app) {

	$app->render('usuarios/registracion.html');

})->name('registracion');


$app->post('/registracion', function() use ($app) {
	
	// extrae los parametros en variables del mismo nombre que su "key"
	extract($app->request->params());

	$errores = array();

	try {

		$query = $app->db->prepare("SELECT id FROM usuarios WHERE email = :email AND activo = 1");
		$query->execute([':email' => $email]);
		$usuario_existe = $query->fetch();

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect($app->urlFor('registracion'));
	}

	if ( $usuario_existe ) {
		$errores['email'] = 'Ya existe un usuario con ese email.';
	}
	
	if ( $password !== $password2 ) {
		$errores['password'] = 'No coinciden las contraseñas.';
	}

	if ( ! empty($errores) ) {
		$app->flash('errores', $errores);
		$app->flash('anterior', $app->request->params());
		$app->redirect($app->urlFor('registracion'));
	}

	try {

		$query = $app->db->prepare(
			"INSERT INTO usuarios (email, password, nombre, dni, calle, piso, dpto, ciudad, pais) 
			VALUES (:email, :password, :nombre, :dni, :calle, :piso, :dpto, :ciudad, :pais)"
		);

		$query->execute([
			':email' => $email,
			':password' => md5($password),
			':nombre' => $nombre,
			':dni' => $dni,
			':calle' => $calle,
			':piso' => $piso, 
			':dpto' => $dpto, 
			':ciudad' => $ciudad,
			':pais' => $pais
		]);

	} catch (PDOException $e) {
		$app->flash('error', 'No se pudo registrar tu usuario por un error en la base de datos');
		$app->redirect($app->urlFor('registracion'));
	}

	$app->flash('mensaje', 'Te has registrado correctamente. Ahora puedes ingresar al sistema.');
	$app->redirect($app->urlFor('login'));

})->name('registracion-post');

// ------------------------------------------------------------------------
// login
// ------------------------------------------------------------------------

$app->get('/login', function() use ($app) {

	$app->render('usuarios/login.html');

})->name('login');


$app->post('/login', function() use ($app) {

	$req = $app->request;

	$referrer = $req->params('referrer');
	$email = $req->params('email');
	$password = $req->params('password');

	try {

		$query = $app->db->prepare(
			"SELECT id, email, password, nombre, rol, activo FROM usuarios
			WHERE email = :email AND password = :password
			AND activo = 1"
		);

		$query->execute([
			':email' => $email,
			':password' => md5($password)
		]);

		$usuario = $query->fetch(PDO::FETCH_ASSOC);

	}

	catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect($app->urlFor('login'));
	}

	if ( empty($usuario) ) {
		$app->flash('error', 'Email o contraseña incorrecta');
		$app->flash('referrer', $referrer);
		$app->redirect($app->urlFor('login'));
	} else {
		$_SESSION['usuario']['id'] = $usuario['id'];
		$_SESSION['usuario']['nombre'] = $usuario['nombre'];
		$_SESSION['usuario']['rol'] = $usuario['rol'];
		$app->flash('mensaje', 'Bienvenido ' . $usuario['nombre'] . ', has iniciado sesión.');
	}	

	// referrer
	if ( empty($referrer) ) {
		$app->redirect($app->urlFor('index'));
	} else {
		$app->redirect($referrer);
	}

})->name('login-post');

// ------------------------------------------------------------------------
// logout
// ------------------------------------------------------------------------

$app->get('/logout', function() use ($app) {
	
	if( isset($_SESSION['usuario']) ) {
		session_destroy();
	}

	$app->redirect($app->urlFor('index'));

})->name('logout');

// ------------------------------------------------------------------------
// perfil
// ------------------------------------------------------------------------

$app->group('/perfil', $auth(), function () use ($app) {
	
	$app->get('/', function() use ($app) {

		$app->redirect($app->urlFor('perfil-subastas'));

	})->name('perfil');

	// mis subastas
	$app->get('/subastas', function() use ($app) {

		try {

			$query = $app->db->prepare(
				"SELECT subastas.*, DATEDIFF(finalizacion,NOW()) AS dias, fotos.ruta AS foto
				FROM subastas 
				INNER JOIN fotos ON subastas.id = fotos.id_subasta
				WHERE id_usuario = :id
				GROUP BY id
				ORDER BY finalizacion"
			);

			$query->execute([
				':id' => $_SESSION['usuario']['id']
			]);

			$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', $e->getMessage());
			$app->redirect('/');
		}

		$app->render('usuarios/perfil/subastas.html', [
			'subastas' => $subastas
		]);

	})->name('perfil-subastas');


	// mis ofertas
	$app->get('/ofertas', function() use ($app) {
		
		try {

			$query = $app->db->prepare(
				"SELECT ofertas.*, subastas.titulo, DATEDIFF(subastas.finalizacion,NOW()) AS dias, fotos.ruta AS foto
				FROM usuarios
				INNER JOIN ofertas ON ofertas.id_usuario = usuarios.id
				INNER JOIN subastas ON subastas.id = ofertas.id_subasta
				INNER JOIN fotos ON fotos.id_subasta = ofertas.id_subasta
				WHERE usuarios.id = :id
				GROUP BY id"
			);

			$query->execute([
				':id' => $_SESSION['usuario']['id']
			]);

			$ofertas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		$app->render('usuarios/perfil/ofertas.html', [
			'ofertas' => $ofertas
		]);

	})->name('perfil-ofertas');

	// mis preguntas
	$app->get('/preguntas', function() use ($app) {
		
		try {

			$query = $app->db->prepare(
				"SELECT subastas.titulo AS subasta, preguntas.*, respuestas.id AS id_respuesta, respuestas.texto AS texto_respuesta
				FROM preguntas
				INNER JOIN usuarios ON preguntas.id_usuario = usuarios.id
				INNER JOIN subastas ON preguntas.id_subasta = subastas.id
				LEFT JOIN respuestas ON respuestas.id_pregunta = preguntas.id
				WHERE preguntas.id_usuario = :id"
			);

			$query->execute([
				':id' => $_SESSION['usuario']['id']
			]);

			$preguntas = $query->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('index'));
		}

		$app->render('usuarios/perfil/preguntas.html', [
			'preguntas' => $preguntas
		]);

	})->name('perfil-preguntas');

	// editar datos personales
	$app->get('/editar', function() use ($app) {
	
		try {
			$query = $app->db->prepare(
				"SELECT * FROM usuarios WHERE id = :id"
			);
			$query->execute([':id' => $_SESSION['usuario']['id']]);
			$usuario = $query->fetch(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
		}

		$app->render('usuarios/perfil/editar.html', [
			'usuario' => $usuario
		]);

	})->name('perfil-editar');

	$app->post('/editar', function() use ($app) {
	
		// extrae los parametros en variables del mismo nombre que su "key"
		extract($app->request->params());

		$errores = array();

		try {

			$query = $app->db->prepare(
				"SELECT id FROM usuarios 
				WHERE email = :email AND id != :id
				LIMIT 1");

			$query->execute([
				':email' => $email,
				':id' => $_SESSION['usuario']['id']
			]);

			$usuario_existe = $query->fetch();

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('perfil-editar'));
		}

		if ( $usuario_existe ) {
			$errores['email'] = 'Ya existe un usuario con ese email.';
		}

		if ( $password !== $password2 ) {
			$errores['password'] = 'No coinciden las contraseñas.';
		}

		
		if ( ! empty($errores) ) {
			$app->flash('errores', $errores);
			$app->redirect($app->urlFor('perfil-editar'));
		}

		try {

			if (strlen($password) > 0) {

				$query = $app->db->prepare(
					"UPDATE usuarios 
					SET email = :email, password = :password, nombre = :nombre, 
					dni = :dni, calle = :calle, piso = :piso, 
					dpto = :dpto, ciudad = :ciudad, pais = :pais
					WHERE id = :id"
				);

				$query->execute([
					':id' => $_SESSION['usuario']['id'],
					':email' => $email,
					':password' => md5($password),
					':nombre' => $nombre,
					':dni' => $dni,
					':calle' => $calle,
					':piso' => $piso, 
					':dpto' => $dpto, 
					':ciudad' => $ciudad,
					':pais' => $pais
				]);

				session_destroy();
				$app->redirect($app->urlFor('login'));

			} else {

				$query = $app->db->prepare(
					"UPDATE usuarios 
					SET email = :email, nombre = :nombre, 
					dni = :dni, calle = :calle, piso = :piso, 
					dpto = :dpto, ciudad = :ciudad, pais = :pais
					WHERE id = :id"
				);

				$query->execute([
					':id' => $_SESSION['usuario']['id'],
					':email' => $email,
					':nombre' => $nombre,
					':dni' => $dni,
					':calle' => $calle,
					':piso' => $piso, 
					':dpto' => $dpto, 
					':ciudad' => $ciudad,
					':pais' => $pais
				]);

			}
			
		} catch (PDOException $e) {
			$app->flash('error', 'No se pudo actualizar tu usuario por un error en la base de datos');
			$app->redirect($app->urlFor('perfil-editar'));
		}

		$app->flash('mensaje', 'Tus datos fueron actualizados correctamente.');
		$app->redirect($app->urlFor('perfil-editar'));

	})->name('perfil-editar-post');

	// borrar
	$app->get('/borrar', function () use ($app) {

		try {

			$query = $app->db->prepare(
				"UPDATE usuarios 
				SET activo = 0
				WHERE id = :id"
			);
			
			$ok = $query->execute([
				':id' => $_SESSION['usuario']['id']
			]);

		} catch (PDOException $e) {
			$app->flash('error', 'Hubo un error en la base de datos');
			$app->redirect($app->urlFor('admin-usuarios'));
		}
		
		if ( ! $ok ) {
			$app->flash('error', 'No se ha encontrado ese usuario');
			$app->redirect($app->urlFor('index'));
		}

		$app->redirect($app->urlFor('mensaje-despedida'));

	})->conditions(['id' => '\d+'])->name('perfil-borrar');

	// mensaje despedida
	$app->get('/despedida', function () use ($app) {

		session_destroy();
		$app->render('usuarios/despedida.html');
		
	})->name('mensaje-despedida');

});