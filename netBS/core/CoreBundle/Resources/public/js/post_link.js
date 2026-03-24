
var PostLink = function(url, data) {

    var csrfToken = document.querySelector('meta[name="csrf-token"]');

    var newForm = $('<form>', {

        'action': url,
        'method': 'POST'

    }).append(jQuery('<input>', {
        'name': 'data',
        'value': JSON.stringify(data),
        'type': 'hidden'
    })).append(jQuery('<input>', {
        'name': '_token',
        'value': csrfToken ? csrfToken.getAttribute('content') : '',
        'type': 'hidden'
    }));

    $(document.body).append(newForm);
    newForm.submit();
};