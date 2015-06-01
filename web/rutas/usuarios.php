<?php

// ------------------------------------------------------------------------
// registracion
// ------------------------------------------------------------------------

$app->get('/registracion', function () use ($app) {
	$app->render('usuarios/registracion.html');
})->name('registracion');


$app->post('/registracion', function() use ($app) {
	$req = $app->request;

	// extrae los parametros en variables del mismo nombre que su "key"
	extract($req->params());

	try {

		$query = $app->db->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
		$query->execute([
			':email' => $email
		]);

		$usuario_existe = $query->fetch();

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
	}

	if ( $usuario_existe ) {
		$errores['email'] = 'Ya existe un usuario con ese email.';
	}
	
	if ( $password !== $password2 ) {
		$errores['password'] = 'No coinciden las contraseñas.';
	}

	if ( ! empty($errores) ) {
		$app->flash('errores', $errores);
		$app->flash('anterior', $req->params());
		$app->redirect('/registracion');
	}

	try {

		$query = $app->db->prepare("INSERT INTO usuarios (email, password, nombre, dni, calle, nro, dpto, ciudad, pais) 
							  VALUES (:email, :password, :nombre, :dni, :calle, :nro, :dpto, :ciudad, :pais)");
		$query->execute([
			':email' => $email,
			':password' => md5($password),
			':nombre' => $nombre,
			':dni' => $dni,
			':calle' => $calle,
			':nro' => $nro, 
			':dpto' => $dpto, 
			':ciudad' => $ciudad,
			':pais' => $pais
		]);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
	} 
	
	$app->flash('mensaje', 'Te has registrado correctamente. Ahora puedes ingresar al sistema.');
	$app->redirect('/login');

})->name('registracion-post');

// ------------------------------------------------------------------------
// login
// ------------------------------------------------------------------------

$app->get('/login', function() use ($app) {
	$app->render('usuarios/login.html');
})->name('login');


$app->post('/login', function() use ($app) {
	$req = $app->request;
	
	$email = $req->params('email');
	$password = $req->params('password');

	try {

		$query = $app->db->prepare("SELECT id, email, password, nombre FROM usuarios
							  WHERE email = :email AND password = :password
							  LIMIT 1");

		$query->execute([
			':email' => $email,
			':password' => md5($password)
		]);

		$user = $query->fetch(PDO::FETCH_ASSOC);

	}

	catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');

	}

	if ( empty($user) ) {
		$app->flash('error', 'email o contraseña incorrecta');
		$app->redirect('/login');
	} else {
		$_SESSION['usuario']['id'] = $user['id'];
		$_SESSION['usuario']['nombre'] = $user['nombre'];
		$app->flash('mensaje', 'Bienvenido, ' . $user['nombre'] . '! has iniciado sesión.');
	}	

	$app->redirect('/');

})->name('login-post');

// ------------------------------------------------------------------------
// logout
// ------------------------------------------------------------------------

$app->get('/logout', function() use ($app) {
	
	if( isset($_SESSION['usuario']) ) {
		session_destroy();
	}

	$app->redirect('/');

})->name('logout');