jQuery(document).ready(function(){
  jQuery('input#spk-clean-action').click(function(e) {
    return confirm('Que el semáforo de ejecución esté activo puede significar que aún se está ejecutando ' +
                   'el proceso de sincronización o que el anterior proceso falló, ¿está seguro de querer limpiar el semáforo?');
  });
});