(function ($) {

    "use strict";

    $.fn.xdatepicker = $.fn.datepicker.noConflict();

    var HochetDP = function (options) {

        this.init('location', options, HochetDP.defaults);
    };

    $.fn.editableutils.inherit(HochetDP, $.fn.editabletypes.abstractinput);

    $.extend(HochetDP.prototype, {

        render: function () {

            var $this = this;

            $($this.$input[1]).xdatepicker({
                format: 'dd.mm.yyyy',
                inline: true,
                icons: {
                    leftArrow: 'fa-arrow-left',
                    rightArrow: 'fa-arrow-right'
                },
                fontAwesone: true
            }).on('changeDate', function(e) {

                var date    = e.date;
                var day     = date.getDate() < 10 ? ("0" + date.getDate()) : date.getDate();
                var month   = (date.getMonth()+1) < 10 ? ("0" + (date.getMonth()+1)) : date.getMonth()+1;
                var val     = day + "." + month + "." + date.getFullYear();
                $($this.$input[0]).val(val);
            });

            $($this.$input[0]).on('input', function(e) {

                $($this.$input[1]).xdatepicker('update', $(this).val());
            });

            this.$input.focus();
        },

        value2html: function (value, element) {
            $(element).html(value);
        },

        html2value: function (html) {
            return html;
        },

        value2str: function (value) {
            return value;
        },

        str2value: function (str) {
            return str;
        },

        value2input: function (value) {
            $(this.$input[0]).val(value);
            $(this.$input[1]).xdatepicker('update', value);
        },

        input2value: function() {
            return $(this.$input[0]).val();
        },

        clear:  function() {
            this.$input.find('.active').removeClass('active');
        }
    });

    HochetDP.defaults = $.extend({}, $.fn.editabletypes.abstractinput.defaults, {
        //tpl: '<input class="form-control input-sm" data-date-format="dd.mm.yyyy" placeholder="dd.mm.yyyy" type="text" />',
        tpl: '<input class="form-control input-sm" style="padding:0.15rem;width:100%;" placeholder="dd.mm.yyyy" type="text" />' +
        '<div class="xdtm datetimepicker" style="width:300px;" data-date-format="dd.mm.yyyy"></div>',
        inputclass: ''
    });

    $.fn.editabletypes.hochetdatepicker = HochetDP;

}(window.jQuery));
