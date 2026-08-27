(function () {
    'use strict';

    // File picker state
    var input = document.querySelector('input[name="files[]"]');
    var hint = document.getElementById('upload-hint');
    var uploadBtn = document.getElementById('upload-btn');

    if (input && hint && uploadBtn) {
        input.addEventListener('change', function () {
            var files = input.files;
            if (files.length === 0) {
                hint.textContent = '';
                uploadBtn.disabled = true;
                return;
            }
            var label = files.length === 1 ? files[0].name : files.length + ' file dipilih';
            hint.textContent = label;
            uploadBtn.disabled = false;
        });
    }

    // Delete confirmation
    var forms = document.querySelectorAll('form[data-confirm]');
    Array.prototype.forEach.call(forms, function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                ev.preventDefault();
            }
        });
    });
})();
