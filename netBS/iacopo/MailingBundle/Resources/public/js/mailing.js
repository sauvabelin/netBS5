/**
 * Mailing Bundle JavaScript
 */
(function($) {
    'use strict';

    // Toggle list active status
    window.toggleListActive = function(id) {
        var checkbox = document.getElementById('toggle-' + id);
        var label = document.getElementById('label-' + id);

        fetch(window.mailingRoutes.toggleActive.replace('__ID__', id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                label.textContent = data.active ? 'Active' : 'Inactive';
                label.className = 'badge ' + (data.active ? 'text-bg-success' : 'text-bg-secondary');
            } else {
                checkbox.checked = !checkbox.checked;
                alert('Erreur lors de la mise à jour');
            }
        })
        .catch(function() {
            checkbox.checked = !checkbox.checked;
            alert('Erreur lors de la mise à jour');
        });
    };

    // Lazy load recipient counts on hover
    $(document).on('mouseenter', '.recipient-count[data-list-id]', function() {
        var $el = $(this);
        if ($el.data('loaded')) return;

        var listId = $el.data('list-id');
        $el.data('loaded', true);

        // Update tooltip to show loading
        $el.attr('title', 'Chargement...');
        $el.tooltip('dispose').tooltip('show');

        $.get(window.mailingRoutes.recipients.replace('__ID__', listId))
            .done(function(data) {
                var badge = data.count > 0 ? 'text-bg-info' : 'text-bg-warning';
                $el.find('.badge')
                   .removeClass('text-bg-secondary')
                   .addClass(badge)
                   .text(data.count + ' adresse(s)');

                // Update tooltip with email list
                var emailList = data.emails.length > 0 ? data.emails.join('\n') : 'Aucune adresse';
                $el.attr('title', emailList);
                $el.tooltip('dispose').tooltip();
            })
            .fail(function() {
                $el.find('.badge')
                   .removeClass('text-bg-secondary')
                   .addClass('text-bg-danger')
                   .text('Erreur');
                $el.attr('title', 'Erreur de chargement');
                $el.tooltip('dispose').tooltip();
            });
    });

    // Target type field switching (for edit page)
    function initTargetTypeSelector() {
        var typeSelect = document.querySelector('.target-type-selector');
        if (!typeSelect) return;

        var fields = {
            email: document.getElementById('email-field'),
            user: document.getElementById('user-field'),
            unite: document.getElementById('group-field'),
            role: document.getElementById('fonction-field'),
            list: document.getElementById('list-field')
        };

        function updateFields() {
            var type = typeSelect.value;
            Object.keys(fields).forEach(function(key) {
                if (fields[key]) {
                    fields[key].style.display = type === key ? 'block' : 'none';
                    if (type !== key) {
                        var input = fields[key].querySelector('input, select');
                        if (input) input.value = '';
                    }
                }
            });
        }

        typeSelect.addEventListener('change', updateFields);
        updateFields(); // Initialize
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        // Initialize Bootstrap tooltips
        $('[data-bs-toggle="tooltip"]').tooltip({
            html: true,
            trigger: 'hover',
            container: 'body'
        });

        // Initialize target type selector if on edit page
        initTargetTypeSelector();
    });

})(jQuery);
