$(document).ready(function() {

  $('.borrar-comentario').click(function (e) {
    var elem = $(this);
    var id = elem.data('id');
    var id_subasta = elem.data('subasta-id');

    $.post('/subastas/fotos/borrar', {

      'id': id,
      'id_subasta': id_subasta

    }, function(data) {

      if (data.status == 200) {
        elem.parent().fadeOut();
      } 

    });

  });

});