jQuery(document).ready(function($) {
    // Select all checkboxes
    $('#cb-select-all, #cb-select-all-bottom').on('click', function() {
        $('.check-item').prop('checked', $(this).prop('checked'));
    });

    // If all checkboxes are selected, check the "select all" checkbox
    $('.check-item').on('click', function() {
        if ($('.check-item:checked').length === $('.check-item').length) {
            $('#cb-select-all, #cb-select-all-bottom').prop('checked', true);
        } else {
            $('#cb-select-all, #cb-select-all-bottom').prop('checked', false);
        }
    });

    // Submit form confirmation for bulk actions
    $('#bulk-action-form').on('submit', function(e) {
        let action = $('#bulk-action-selector-top').val();

        if (action === 'delete') {
            let checked = $('.check-item:checked').length;
            if (checked === 0) {
                alert('Please select at least one item to delete.');
                e.preventDefault();
                return false;
            }

            if (!confirm('Are you sure you want to delete ' + checked + ' selected items?')) {
                e.preventDefault();
                return false;
            }
        } else if (action === 'import') {
            let checked = $('.check-item:checked').length;
            if (checked === 0) {
                alert('Please select at least one item to import.');
                e.preventDefault();
                return false;
            }
        } else if (action === '') {
            alert('Please select an action.');
            e.preventDefault();
            return false;
        }
    });
});

function importEntry(id, title) {
    if (confirm(title + ' - Are you sure you want to import this entry?')) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = '';

        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'import_entry';
        input.value = id;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteEntry(id, title) {
    if (confirm(title + ' - Are you sure you want to delete this entry?')) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = '';

        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_entry';
        input.value = id;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
