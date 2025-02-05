import "./bootstrap";

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

// Limpa campos
window.clearFieldInvalidSpan = function (id) {
    $(`input[id*=${id}_]`).removeClass('is-invalid').val('');
    $(`[id*=span_${id}_]`).html('');
}
