$(document).ready(function() {

  var MAX_FOTOS = 5;
  var CANT_FOTOS_EXISTENTES = $('#uploads-existentes').children().length || 0;

  // preview fotos
  $('#uploads').on('change', function (e) {
    var files = e.target.files;
    preview = document.getElementById("uploads-preview");
    preview.innerHTML = '';

    for (var i = 0; i < files.length; i++) {
      var file = files[i];
      var imageType = /image.*/;

      if ( ! file.type.match(imageType) ) {
        continue;
      }

      var img = document.createElement("img");
      img.file = file;
      preview.appendChild(img);

      var reader = new FileReader();
      // https://developer.mozilla.org/es/docs/Using_files_from_web_applications#Example.3A.C2.A0Showing_thumbnails_of_user-selected_images
      reader.onload = (function(aImg) { return function(e) { aImg.src = e.target.result; }; })(img);
      reader.readAsDataURL(file);
    }

  });

  // antes del submit
  $('#form-subasta').submit(function (e) {   

    var files = document.getElementById("uploads").files;
    var errores = [];

    $('#errores-fotos').html('');

    if (files.length > MAX_FOTOS - CANT_FOTOS_EXISTENTES) {
      errores.push('<li class="text-danger">No puedes agregar mas de 5 fotos para una subasta.</li>');
    }

    for (var i = 0; i < files.length; i++) {
      var file = files[i];
      var imageType = /image.*/;

      if ( ! file.type.match(imageType) ) {
        errores.push('<li class="text-danger">'+file.name+' no es una imagen.</li>');
      }

      else if ( file.size > 2097152) {
        errores.push('<li class="text-danger">La imagen '+file.name+' pesa mas de 2MB.</li>');
      }
    }

    if (errores.length > 0) {
      $.each(errores, function(key, error) {
        $('#errores-fotos').append(error);
      });
      e.preventDefault();
    }

    return;

  });

  // ajax borrar foto
  $('.borrar-foto').click(function (e) {
    var elem = $(this);
    var id_foto = elem.data('foto-id');
    var id_subasta = elem.data('subasta-id');

    if ( CANT_FOTOS_EXISTENTES > 1 ) {

      $.post('/subastas/fotos/borrar', {

        'id_foto': id_foto,
        'id_subasta': id_subasta

      }, function(data) {

        if (data.status == 200) {
          elem.parent().fadeOut();
        } else {
          $('#errores-fotos').append('<li class="text-danger">'+data['error']+'</li>');
        }

      });

    } else {
      $('#errores-fotos').append('<li class="text-danger">No puedes borrar la única foto de la subasta</li>');
    }

  });

});