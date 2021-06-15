
// Mostrar erros
window.showErrors = function(e, prefix = null) {
    $.each(e.responseJSON.errors, function (key, error) {

        let id      = `#${key}`;
        let id_span = `#span_${key}`;

        if (prefix)
        {
            id      = `#${prefix}_${key}`;
            id_span = `#span_${prefix}_${key}`;
        }

        $(id).addClass('is-invalid');
        $(id_span).html(error[0]);
    });
}
