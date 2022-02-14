
var PostLink = function(url, data) {

    var newForm = $('<form>', {

        'action': url,
        'method': 'POST'

    }).append(jQuery('<input>', {
        'name': 'data',
        'value': JSON.stringify(data),
        'type': 'hidden'
    }));

    $(document.body).append(newForm);
    newForm.submit();
};