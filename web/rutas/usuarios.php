<?php

// ------------------------------------------------------------------------
// registracion
// ------------------------------------------------------------------------

$app->get('/registracion', function () use ($app) {
	$app->render('usuarios/registracion.html');
})->name('registracion');


$app->post('/registracion', function() use ($app) {
	$req = $app->request;

	$username = $req->params('username');
	$password = $req->params('password');
	$email = $req->params('email');
	$nombre = $req->params('nombre');

	// validar !

	try {

		$query = $app->db->prepare("INSERT INTO usuarios (username, password, nombre, email) 
							  VALUES (:username, :password, :nombre, :email)");
		$query->execute(
			array(
				':username' => $usuario,
				':password' => md5($password),
				':email' => $email,
				':nombre' => $nombre
			)
		);

	}

	catch (PDOException $e) {
		$app->flash('error', 'hubo un error en la base de datos');
	}

	$app->redirect('/');

})->name('registracion-post');

// ------------------------------------------------------------------------
// login
// ------------------------------------------------------------------------

$app->get('/login', function() use ($app) {
	$app->render('usuarios/login.html');
})->name('login');


$app->post('/login', function() use ($app) {
	$req = $app->request;
	
	$username = $req->params('username');
	$password = $req->params('password');

	try {

		$query = $app->db->prepare("SELECT id, username, password FROM usuarios
							  WHERE username = :username AND password = :password
							  LIMIT 1");

		$query->execute(
			array(
				':username' => $username,
				':password' => md5($password)
			)
		);

		$user = $query->fetch(PDO::FETCH_ASSOC);

	}

	catch (PDOException $e) {
		$app->flash('error', 'error en la base de datos');

	}

	if ( empty($user) ) {
		$app->flash('error', 'usuario o contraseña incorrecta');
		$app->redirect('/login');
	} else {
		$_SESSION['usuario']['id'] = $user['id'];
		$_SESSION['usuario']['username'] = $user['username'];
		$app->flash('mensaje', 'has iniciado sesión');
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