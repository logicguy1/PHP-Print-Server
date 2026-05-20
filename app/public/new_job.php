<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cups.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();
$user = current_user();

$error   = '';
$success = '';

// Allowed option values
$valid_papers  = ['A4', 'Letter'];
$valid_duplex  = ['none', 'long-edge', 'short-edge'];
$valid_quality = ['draft', 'normal', 'high'];
$valid_nup     = [1, 2, 4];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // --- Validate file ---
    $upload = $_FILES['pdf'] ?? null;
    if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please select a PDF file to upload.';
    } elseif ($upload['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory available.',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write file to disk.',
        ];
        $error = $upload_errors[$upload['error']] ?? 'Upload error ' . $upload['error'];
    } elseif ($upload['size'] > MAX_UPLOAD_BYTES) {
        $error = 'File exceeds maximum size of ' . MAX_UPLOAD_MB . ' MB.';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($upload['tmp_name']);
        $ext   = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));

        $image_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff'];
        $image_exts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tiff', 'tif'];
        $is_pdf      = ($mime === 'application/pdf' && $ext === 'pdf');
        $is_image    = in_array($mime, $image_mimes, true) && in_array($ext, $image_exts, true);

        if (!$is_pdf && !$is_image) {
            $error = 'Only PDF and image files (PNG, JPEG, GIF, TIFF, WebP) are accepted.';
        }
    }

    if (!$error) {
        // --- Validate options ---
        $copies          = max(1, min(99, (int)($_POST['copies'] ?? 1)));
        $paper_size      = in_array($_POST['paper_size'] ?? '', $valid_papers, true)
                           ? $_POST['paper_size'] : 'A4';
        $duplex          = in_array($_POST['duplex'] ?? '', $valid_duplex, true)
                           ? $_POST['duplex'] : 'none';
        $quality         = in_array($_POST['quality'] ?? '', $valid_quality, true)
                           ? $_POST['quality'] : 'normal';
        $pages_per_sheet = in_array((int)($_POST['pages_per_sheet'] ?? 1), $valid_nup, true)
                           ? (int)$_POST['pages_per_sheet'] : 1;

        // --- Store file ---
        $stored_name = bin2hex(random_bytes(16)) . '.pdf';
        $dest        = UPLOAD_DIR . $stored_name;
        if ($is_pdf) {
            if (!move_uploaded_file($upload['tmp_name'], $dest)) {
                $error = 'Failed to save uploaded file.';
            }
        } else {
            $tmp_pdf = image_to_pdf($upload['tmp_name']);
            if ($tmp_pdf === false) {
                $error = 'Failed to convert image to PDF.';
            } else {
                if (!rename($tmp_pdf, $dest)) {
                    copy($tmp_pdf, $dest);
                    unlink($tmp_pdf);
                }
            }
        }
    }

    if (!$error) {
        $original_name = basename($upload['name']);

        // --- Insert job record ---
        $db   = db();
        $stmt = $db->prepare(
            'INSERT INTO print_jobs
             (user_id, filename, original_name, copies, paper_size, duplex, quality, pages_per_sheet, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'], $stored_name, $original_name,
            $copies, $paper_size, $duplex, $quality, $pages_per_sheet, 'pending',
        ]);
        $job_db_id = (int)$db->lastInsertId();

        // --- Submit to CUPS ---
        $printer = DEFAULT_PRINTER;
        $result  = cups_submit_job($dest, [
            'copies'          => $copies,
            'paper_size'      => $paper_size,
            'duplex'          => $duplex,
            'quality'         => $quality,
            'pages_per_sheet' => $pages_per_sheet,
        ], $printer);

        if ($result['success']) {
            $db->prepare(
                "UPDATE print_jobs SET status='printing', cups_job_id=? WHERE id=?"
            )->execute([$result['job_id'], $job_db_id]);
        } else {
            $db->prepare(
                "UPDATE print_jobs SET status='failed', error_message=? WHERE id=?"
            )->execute([$result['error'], $job_db_id]);
        }

        header('Location: /history.php?submitted=1');
        exit;
    }
}

$printers = cups_get_printers();

layout_head('New Print Job', 'new_job');
?>

<div class="page-header">
    <span class="page-title">New Print Job</span>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="job-layout">
    <!-- Left: Form -->
    <div class="window job-form-panel">
        <div class="window-titlebar">Submit Print Job</div>
        <div class="window-body">
            <form method="post" action="/new_job.php" enctype="multipart/form-data" id="printForm">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_BYTES ?>">

                <!-- File Upload -->
                <div class="form-row mb-16">
                    <label>Document (PDF)</label>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="pdf" id="pdfInput"
                               accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.tiff,.tif,application/pdf,image/*">
                        <div class="upload-zone-label">
                            <strong>Click to browse</strong> or drag &amp; drop a PDF or image here
                        </div>
                        <div class="upload-filename" id="uploadFilename" style="display:none"></div>
                    </div>
                    <span class="form-hint">PDF or image (PNG, JPEG, GIF, WebP, TIFF) · max <?= MAX_UPLOAD_MB ?> MB</span>
                </div>

                <!-- Print Options -->
                <fieldset style="border:2px solid var(--gray-light);padding:12px;margin-bottom:14px">
                    <legend style="font-weight:bold;font-size:12px;padding:0 6px">Print Options</legend>
                    <div class="options-grid">
                        <div class="form-row">
                            <label for="copies">Copies</label>
                            <input type="number" id="copies" name="copies"
                                   value="<?= h((string)($_POST['copies'] ?? 1)) ?>"
                                   min="1" max="99">
                        </div>
                        <div class="form-row">
                            <label for="paper_size">Paper Size</label>
                            <select id="paper_size" name="paper_size">
                                <option value="A4"<?= (($_POST['paper_size'] ?? 'A4') === 'A4') ? ' selected' : '' ?>>A4</option>
                                <option value="Letter"<?= (($_POST['paper_size'] ?? '') === 'Letter') ? ' selected' : '' ?>>Letter</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="pages_per_sheet">Pages per Sheet</label>
                            <select id="pages_per_sheet" name="pages_per_sheet">
                                <option value="1"<?= (($_POST['pages_per_sheet'] ?? '1') === '1') ? ' selected' : '' ?>>1 (Normal)</option>
                                <option value="2"<?= (($_POST['pages_per_sheet'] ?? '') === '2') ? ' selected' : '' ?>>2 (e.g. A5 on A4)</option>
                                <option value="4"<?= (($_POST['pages_per_sheet'] ?? '') === '4') ? ' selected' : '' ?>>4</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="duplex">Duplex (Two-Sided)</label>
                            <select id="duplex" name="duplex">
                                <option value="none"<?= (($_POST['duplex'] ?? 'none') === 'none') ? ' selected' : '' ?>>Off (Single-sided)</option>
                                <option value="long-edge"<?= (($_POST['duplex'] ?? '') === 'long-edge') ? ' selected' : '' ?>>Long-edge (Flip on long side)</option>
                                <option value="short-edge"<?= (($_POST['duplex'] ?? '') === 'short-edge') ? ' selected' : '' ?>>Short-edge (Flip on short side)</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="quality">Print Quality</label>
                            <select id="quality" name="quality">
                                <option value="draft"<?= (($_POST['quality'] ?? '') === 'draft') ? ' selected' : '' ?>>Draft</option>
                                <option value="normal"<?= (($_POST['quality'] ?? 'normal') === 'normal') ? ' selected' : '' ?>>Normal</option>
                                <option value="high"<?= (($_POST['quality'] ?? '') === 'high') ? ' selected' : '' ?>>High</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <?php if (empty($printers) && DEFAULT_PRINTER === ''): ?>
                    <div class="alert alert-info">
                        No printer configured. Set <code>PRINTER_NAME</code> environment variable or
                        check CUPS connection. Job will still be queued.
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">Print</button>
                    <a href="/dashboard.php" class="btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Right: Preview -->
    <div class="window job-preview-panel" id="previewPanel">
        <div class="window-titlebar">Print Preview</div>
        <div class="window-body preview-body">
            <div class="preview-placeholder" id="previewPlaceholder">
                <p>Select a PDF file to preview it here.</p>
            </div>
            <div id="previewContainer" style="display:none"></div>
        </div>
    </div>
</div>

<?php layout_foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>'); ?>
