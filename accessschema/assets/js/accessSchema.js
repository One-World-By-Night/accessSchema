// File: assets/js/accessSchema.js
// @version 1.2.0

document.addEventListener('DOMContentLoaded', function () {
    // Handle role removal via (×) button
    document.querySelectorAll('.remove-role-button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const rolePath = this.getAttribute('data-role');
            const chip = this.closest('.access-role-tag');

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'accessSchema_remove_roles[]';
            input.value = rolePath;

            chip.style.display = 'none';
            document.querySelector('form').appendChild(input);
        });
    });

    // Initialize Select2 for multi-select dropdown
    if (window.jQuery && jQuery().select2) {
        jQuery('#accessSchema_add_roles').select2({
            placeholder: 'Select roles to add',
            width: 'resolve'
        });
    }

    // Filter roles in the role manager table
    const filterInput = document.getElementById('accessSchema-role-filter');
    const tableRows = document.querySelectorAll('#accessSchema-roles-table tbody tr');

    if (filterInput) {
        filterInput.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            tableRows.forEach(row => {
                const pathCell = row.querySelector('.role-path');
                if (pathCell) {
                    const path = pathCell.textContent.toLowerCase();
                    row.style.display = path.includes(query) ? '' : 'none';
                }
            });
        });
    }
});