(function() {

    $('[data-modal]').click(function() {
        let path    = $(this).attr('data-modal-url');
        let modal   = new BSModal(path, {});
        modal.launch();
    });
})();

var BSModal = function(path, params) {

    this.path           = path;
    this.id             = 'dn_modal_' + Math.floor(Math.random() * 99999);

    this.showModal      = function(content) {
        var html = this.generate(content);
        $(document.body).append(html);
        var el = document.getElementById(this.id);
        var bsModal = new bootstrap.Modal(el);
        bsModal.show();

        el.addEventListener('hidden.bs.modal', function() {
            bsModal.dispose();
            el.remove();
        });
    };

    this.launch         = function() {

        var mdl = this;

        $.post(path, params).done(function(data, status, response) {

            if (parseInt(response.status) === 202)
                toastr[data.type](data.message);

            else {
                mdl.showModal(data);
                mdl.attachButtonEvents();
            }

        }).fail(function(err) {
            mdl.showModal(err.responseText);
        });
    };

    this.generate       = function (content) {
        return '<div id="' + this.id + '" class="modal fade netbs-modal" tabindex="-1" aria-hidden="true">' + content + '</div>';
    };

    this.attachButtonEvents = function() {

        var mdl         = this;
        var $modal      = $('#' + this.id);
        var $confirm    = $modal.find('[data-modal-validate]').first();
        var $form       = $modal.find('form').first();

        $confirm.on('click', function() {

            $.post(mdl.path, $form.serialize())
                .done(function(data, status, response) {
                    mdl.handleSubmit(data, status, response);
                })
                .fail(function(data) {
                    $modal.html(data.responseText);
                    mdl.attachButtonEvents();
                })
            ;
        });
    };

    this.handleSubmit = function(data, status, response) {

        var code    = parseInt(response.status);

        if(data === "redirected")
            window.location.href = response.getResponseHeader("Location");
        if(code === 201)
            location.reload();
        if(code === 202)
            toastr[data.type](data.message);
        this.remove();
    };

    this.remove = function() {
        var el = document.getElementById(this.id);
        var instance = bootstrap.Modal.getInstance(el);
        if (instance) instance.hide();
    }
};
