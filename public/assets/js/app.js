(function () {
    'use strict';

    // ---------- File picker state ----------
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

    // ---------- Delete confirmation (single) ----------
    var forms = document.querySelectorAll('form[data-confirm]');
    Array.prototype.forEach.call(forms, function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                ev.preventDefault();
            }
        });
    });

    // ---------- Bulk delete ----------
    var bulkForm = document.getElementById('bulk-form');
    var checkAll = document.getElementById('check-all');
    var bulkCount = document.getElementById('bulk-count');
    var bulkBtn = document.getElementById('bulk-delete-btn');

    function getChecked() {
        return document.querySelectorAll('.row-check:checked');
    }

    function updateBulk() {
        var n = getChecked().length;
        if (bulkCount) bulkCount.textContent = n + ' terpilih';
        if (bulkBtn) bulkBtn.disabled = n === 0;
        if (checkAll) {
            var boxes = document.querySelectorAll('.row-check');
            checkAll.checked = boxes.length > 0 && n === boxes.length;
        }
    }

    if (bulkForm) {
        var boxes = document.querySelectorAll('.row-check');
        Array.prototype.forEach.call(boxes, function (box) {
            box.addEventListener('change', updateBulk);
        });

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                var checked = checkAll.checked;
                Array.prototype.forEach.call(document.querySelectorAll('.row-check'), function (box) {
                    box.checked = checked;
                });
                updateBulk();
            });
        }

        bulkForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var checked = getChecked();
            if (checked.length === 0) return;

            if (!window.confirm('Hapus ' + checked.length + ' item terpilih?')) return;

            // Isi hidden inputs ids[] dari checkbox yang dicentang
            var existing = bulkForm.querySelectorAll('input[name="ids[]"]');
            Array.prototype.forEach.call(existing, function (el) { el.remove(); });

            Array.prototype.forEach.call(checked, function (box) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids[]';
                hidden.value = box.value;
                bulkForm.appendChild(hidden);
            });

            // form.submit() tidak memicu event submit lagi
            bulkForm.submit();
        });
    }

    // ---------- Image preview modal ----------
    var overlay = document.getElementById('preview-overlay');
    var previewImg = document.getElementById('preview-img');
    var previewName = document.getElementById('preview-name');
    var previewClose = document.getElementById('preview-close');

    function openPreview(url, name) {
        if (!overlay || !previewImg) return;
        previewImg.src = url;
        previewImg.alt = name || 'Preview';
        if (previewName) previewName.textContent = name || '';
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closePreview() {
        if (!overlay) return;
        overlay.hidden = true;
        previewImg.src = '';
        document.body.style.overflow = '';
    }

    var previewLinks = document.querySelectorAll('a[data-preview]');
    Array.prototype.forEach.call(previewLinks, function (link) {
        link.addEventListener('click', function (ev) {
            ev.preventDefault();
            openPreview(link.href, link.getAttribute('data-name') || '');
        });
    });

    if (previewClose) {
        previewClose.addEventListener('click', closePreview);
    }
    if (overlay) {
        overlay.addEventListener('click', function (ev) {
            if (ev.target === overlay) closePreview();
        });
    }
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closePreview();
    });
})();
