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

// File upload zone + print preview
(function () {
    var zone       = document.getElementById('uploadZone');
    var input      = document.getElementById('pdfInput');
    var label      = zone && zone.querySelector('.upload-zone-label');
    var filenameEl = document.getElementById('uploadFilename');
    var paperSel   = document.getElementById('paper_size');
    var nupSel     = document.getElementById('pages_per_sheet');
    var currentFile = null;

    if (!zone || !input) return;

    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    zone.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () { onFile(input.files[0]); });

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
            onFile(file);
        }
    });

    if (paperSel) paperSel.addEventListener('change', function () { if (currentFile) renderPreview(currentFile); });
    if (nupSel)   nupSel.addEventListener('change',   function () { if (currentFile) renderPreview(currentFile); });

    function onFile(file) {
        if (!file) return;
        currentFile = file;
        if (label) label.style.display = 'none';
        if (filenameEl) {
            filenameEl.textContent = file.name + ' (' + formatBytes(file.size) + ')';
            filenameEl.style.display = 'block';
        }
        renderPreview(file);
    }

    function renderPreview(file) {
        var container   = document.getElementById('previewContainer');
        var placeholder = document.getElementById('previewPlaceholder');
        if (!container) return;

        container.style.display = 'flex';
        container.innerHTML = '<p style="margin:auto;color:#555;font-size:12px">Rendering preview…</p>';
        if (placeholder) placeholder.style.display = 'none';

        var paperSize = paperSel ? paperSel.value : 'A4';
        var nup       = nupSel   ? parseInt(nupSel.value, 10) : 1;
        if ([1, 2, 4].indexOf(nup) === -1) nup = 1;

        var dims   = { A4: [595, 842], Letter: [612, 792] };
        var d      = dims[paperSize] || dims.A4;
        var pW = d[0], pH = d[1];

        // 2-up => landscape; 4-up => 2×2 portrait
        var cols   = nup >= 2 ? 2 : 1;
        var rows   = nup === 4 ? 2 : 1;
        var sheetW = nup === 2 ? pH : pW;
        var sheetH = nup === 2 ? pW : pH;

        var availW = container.parentElement ? container.parentElement.clientWidth - 48 : 400;
        var scale  = Math.min(availW / sheetW, 680 / sheetH);
        var cW = Math.round(sheetW * scale);
        var cH = Math.round(sheetH * scale);

        if (typeof pdfjsLib === 'undefined') {
            var frame = document.createElement('iframe');
            frame.src = URL.createObjectURL(file);
            frame.style.cssText = 'width:100%;min-height:500px;border:none;display:block';
            container.innerHTML = '';
            container.appendChild(frame);
            return;
        }

        var canvas    = document.createElement('canvas');
        canvas.width  = cW;
        canvas.height = cH;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, cW, cH);

        var objUrl = URL.createObjectURL(file);
        pdfjsLib.getDocument(objUrl).promise.then(function (pdf) {
            URL.revokeObjectURL(objUrl);

            var cellW   = sheetW / cols;
            var cellH   = sheetH / rows;
            var renders = [];
            for (var i = 0; i < Math.min(nup, pdf.numPages); i++) {
                renders.push(renderPage(pdf, ctx, i, cellW, cellH, cols, rows, scale));
            }

            return Promise.all(renders).then(function () {
                drawGrid(ctx, cW, cH, cols, rows);
                toGrayscale(ctx, cW, cH);
                container.innerHTML = '';
                container.appendChild(canvas);
            });
        }).catch(function (err) {
            container.innerHTML =
                '<p style="margin:auto;color:#800;font-size:12px">Preview failed: ' + err.message + '</p>';
        });
    }

    function renderPage(pdf, ctx, idx, cellW, cellH, cols, rows, scale) {
        return pdf.getPage(idx + 1).then(function (page) {
            var vp  = page.getViewport({ scale: 1 });
            var fit = Math.min(cellW / vp.width, cellH / vp.height) * scale;
            var sv  = page.getViewport({ scale: fit });

            var col = idx % cols;
            var row = Math.floor(idx / cols);
            var x   = col * cellW * scale + (cellW * scale - sv.width)  / 2;
            var y   = row * cellH * scale + (cellH * scale - sv.height) / 2;

            var off    = document.createElement('canvas');
            off.width  = Math.round(sv.width);
            off.height = Math.round(sv.height);
            return page.render({ canvasContext: off.getContext('2d'), viewport: sv }).promise
                .then(function () { ctx.drawImage(off, Math.round(x), Math.round(y)); });
        });
    }

    function drawGrid(ctx, cW, cH, cols, rows) {
        if (cols === 1 && rows === 1) return;
        ctx.strokeStyle = '#ccc';
        ctx.lineWidth   = 1;
        if (cols > 1) { ctx.beginPath(); ctx.moveTo(cW / 2, 0);  ctx.lineTo(cW / 2, cH);  ctx.stroke(); }
        if (rows > 1) { ctx.beginPath(); ctx.moveTo(0,  cH / 2); ctx.lineTo(cW,  cH / 2); ctx.stroke(); }
    }

    function toGrayscale(ctx, w, h) {
        var id = ctx.getImageData(0, 0, w, h), d = id.data;
        for (var i = 0; i < d.length; i += 4) {
            var g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            d[i] = d[i + 1] = d[i + 2] = g;
        }
        ctx.putImageData(id, 0, 0);
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
