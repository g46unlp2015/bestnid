<?php

// ------------------------------------------------------------------------
// index
// ------------------------------------------------------------------------

$app->get('/', function () use ($app) {

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
			ORDER BY clicks DESC, dias ASC"
		);
		
		$query->execute();
		$subastas = $query->fetchAll(PDO::FETCH_ASSOC);

	} catch (PDOException $e) {
		$app->flash('error', 'Hubo un error en la base de datos');
		$app->redirect('/');
	}
	
	$app->render('index.html', [
		'subastas' => $subastas
	]);

})->name('index');

// ------------------------------------------------------------------------
// contacto
// ------------------------------------------------------------------------

$app->get('/contacto', function () use ($app) {
	
	$app->render('contacto.html');

})->name('contacto');

$app->post('/contacto', function () use ($app) {
	
	extract($app->request->params());

	$headers = "From: Bestnid <no-responder@bestnid.com.ar>\r\n";
	$headers .= "Reply-To: ". $email ."\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=utf-8\r\n";

	$para = $app->opciones['contacto.email'];
	$asunto = 'Contacto: ' . $nombre;

	$body = '<p>Nombre: ' . $nombre . '</p>';
	$body .= '<p>Email: ' . $email . '</p>';
	$body .= '<p>Mensaje: </p>';
	$body .= '<blockquote>' . $mensaje .'</blockquote>';

	mail($para, $asunto, $body, $headers);

	$app->flash('mensaje', 'Se ha enviado tu mensaje');
	$app->redirect($app->urlFor('index'));

})->name('contacto-post');