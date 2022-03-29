class ajaxTable {

    constructor(
        fetchPath,
        allIds,
        tableId,
        dbInstance,
        mainCheckboxId,
        modelAlias,
        updateCountClass
    ) {
        this.fetchPath          = fetchPath;
        this.allIds             = allIds;
        this.modelAlias         = modelAlias;
        this.updateCountClass   = updateCountClass;
        this.table              = dbInstance;
        this.mainCheckboxId     = mainCheckboxId;
        this.tableId            = tableId;
        this.selectedIds        = [];

        var $this = this;

        this.table.on('click', 'input[type=checkbox]', function() {
            console.log(this);
        });

        $('#' + mainCheckboxId).click(function() {

            var checked = $(this).is(':checked');
            if(checked === undefined)
                return;

            if (checked) {
                // Retrieve all ids
                $this.selectedIds = [...this.allIds];
            } else {
                $this.selectedIds = [...[]];
            }

            $this.refreshCheckbox();
        });
    }

    refreshCheckbox() {
        var rows = $this.table.rows().nodes();
        for (var i = 0; i < rows.length; i++) {
            var checked = (this.selectedIds.includes(parseInt(items[i].getAttribute("data-id"), 10)))
            $('input[type="checkbox"]', rows[i]).prop('checked', checked);
        }
    }

    updateCounters() {
        $('.' + this.updateCountClass).html( this.selectedIds().length);
    }

    getSelectedIds() {
        if (this.selectedIds === []) {

        }
        return this.selectedIds;
    }
}