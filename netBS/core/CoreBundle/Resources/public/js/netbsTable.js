function netBSTable(tableId, dbInstance, mainCheckboxId, modelAlias, updateCountClass) {

    this.modelAlias         = modelAlias;
    this.updateCountClass   = updateCountClass;
    this.table              = dbInstance;

    var nbs                 = this;

    this.table.on('click', 'input[type=checkbox]', function() {
        nbs.updateCounters();
    });

    $('#' + mainCheckboxId).click(function() {

        var checked = $(this).is(':checked');
        if(checked === undefined)
            return;

        var rows = nbs.table.rows().nodes();
        $('input[type="checkbox"]', rows).prop('checked', checked);
        nbs.updateCounters();
    });

    this.updateCounters     = function() {

        $('.' + this.updateCountClass).html( this.retrieveSelected().length );
    };

    this.retrieveAll        = function() {

        var selected    = [];
        var i           = 0;

        this.table.rows().nodes().each(function(row) {
            selected[i++] = row;
        });

        return selected;
    };

    this.retrieveSelected   = function() {

        var rows        = this.table.rows();
        var selected    = [];
        var i           = 0;

        rows.nodes().each(function(row) {
            if($(row).find('input[type="checkbox"]').is(':checked'))
                selected[i++] = row;
        });

        return selected;
    };

    this.getSelectedIds   = function() {

        var all         = this.retrieveAll();
        var selected    = this.retrieveSelected();
        var items       = selected.length === 0 ? all : selected;
        var ids         = [];

        for(var i = 0; i < items.length; i++)
            ids[i] = items[i].getAttribute("data-id");

        return ids;
    };
}