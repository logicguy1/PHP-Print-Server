// Mobile nav toggle
(function () {
    var toggle = document.getElementById('navToggle');
    var nav    = document.getElementById('mobileNav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            nav.classList.toggle('open');
        });
    }
})();

// Tab switching (admin panel)
(function () {
    var btns = document.querySelectorAll('.tab-btn');
    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-tab');
            btns.forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-panel').forEach(function (p) {
                p.classList.remove('active');
            });
            btn.classList.add('active');
            var panel = document.getElementById(targetId);
            if (panel) panel.classList.add('active');
        });
    });
})();

// File upload zone
(function () {
    var zone     = document.getElementById('uploadZone');
    var input    = document.getElementById('pdfInput');
    var label    = zone && zone.querySelector('.upload-zone-label');
    var filename = document.getElementById('uploadFilename');

    if (!zone || !input) return;

    zone.addEventListener('click', function () { input.click(); });

    input.addEventListener('change', function () {
        showFile(input.files[0]);
    });

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', function () {
        zone.classList.remove('drag-over');
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('drag-over');
        var file = e.dataTransfer.files[0];
        if (file) {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showFile(file);
        }
    });

    function showFile(file) {
        if (!file) return;
        if (label) label.style.display = 'none';
        if (filename) {
            filename.textContent = file.name + ' (' + formatBytes(file.size) + ')';
            filename.style.display = 'block';
        }
        showPreview(file);
    }

    function showPreview(file) {
        var frame       = document.getElementById('previewFrame');
        var placeholder = document.getElementById('previewPlaceholder');
        if (!frame) return;

        var url = URL.createObjectURL(file);
        frame.src = url;
        frame.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';

        // Revoke the previous object URL when a new file is chosen
        frame.addEventListener('load', function revokeOnce() {
            frame.removeEventListener('load', revokeOnce);
        });
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
})();

// Admin: inline password form toggle
function togglePassForm(userId) {
    var row = document.getElementById('passForm-' + userId);
    if (!row) return;
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}

// Print form: disable submit button on submit to prevent double-send
(function () {
    var form = document.getElementById('printForm');
    var btn  = document.getElementById('submitBtn');
    if (!form || !btn) return;
    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.textContent = 'Sending to printer...';
    });
})();
