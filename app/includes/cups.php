<?php

/**
 * Convert an image file to PDF using img2pdf. Returns path to temp PDF or false on failure.
 */
function image_to_pdf(string $src_path): string|false {
    $dest = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8)) . '.pdf';
    exec('img2pdf ' . escapeshellarg($src_path) . ' -o ' . escapeshellarg($dest) . ' 2>&1', $out, $code);
    if ($code !== 0 || !file_exists($dest)) {
        return false;
    }
    return $dest;
}

/**
 * Returns array of available printer names from lpstat -p.
 * Empty array if CUPS unreachable.
 */
function cups_get_printers(): array {
    $output = [];
    exec('lpstat -p 2>&1', $lines, $code);
    foreach ($lines as $line) {
        if (preg_match('/^printer\s+(\S+)\s/', $line, $m)) {
            $output[] = $m[1];
        }
    }
    return $output;
}

/**
 * Submit a print job. Returns ['success'=>true,'job_id'=>int] or ['success'=>false,'error'=>string].
 *
 * $options = [
 *   'copies'          => int,
 *   'paper_size'      => 'A4'|'Letter',
 *   'duplex'          => 'none'|'long-edge'|'short-edge',
 *   'quality'         => 'draft'|'normal'|'high',
 *   'pages_per_sheet' => 1|2|4,
 * ]
 */
function cups_submit_job(string $filepath, array $options, string $printer = ''): array {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return ['success' => false, 'error' => 'File not found or not readable.'];
    }

    $args = [];

    if ($printer !== '') {
        $args[] = '-d ' . escapeshellarg($printer);
    }

    $copies = max(1, min(99, (int)($options['copies'] ?? 1)));
    $args[] = '-n ' . $copies;

    $paper = $options['paper_size'] === 'Letter' ? 'Letter' : 'A4';
    $args[] = '-o media=' . escapeshellarg($paper);

    $duplex_map = [
        'none'        => 'one-sided',
        'long-edge'   => 'two-sided-long-edge',
        'short-edge'  => 'two-sided-short-edge',
    ];
    $duplex = $duplex_map[$options['duplex'] ?? 'none'] ?? 'one-sided';
    $args[] = '-o sides=' . escapeshellarg($duplex);

    $quality_map = ['draft' => '3', 'normal' => '4', 'high' => '5'];
    $quality = $quality_map[$options['quality'] ?? 'normal'] ?? '4';
    $args[] = '-o print-quality=' . $quality;

    $nup = (int)($options['pages_per_sheet'] ?? 1);
    if (in_array($nup, [2, 4, 6, 9, 16], true)) {
        $args[] = '-o number-up=' . $nup;
    }

    // Force grayscale
    $args[] = '-o ColorModel=Gray';

    // Scale content to fill printable area without CUPS adding extra margins
    $args[] = '-o fit-to-page';

    $cmd = 'lp ' . implode(' ', $args) . ' ' . escapeshellarg($filepath) . ' 2>&1';
    $output = [];
    exec($cmd, $output, $exit_code);
    $output_str = implode("\n", $output);

    if ($exit_code !== 0) {
        return ['success' => false, 'error' => $output_str];
    }

    // Parse job ID from "request id is <printer>-<id> (1 file(s))"
    $job_id = null;
    if (preg_match('/request id is \S+-(\d+)/i', $output_str, $m)) {
        $job_id = (int)$m[1];
    }

    return ['success' => true, 'job_id' => $job_id];
}

/**
 * Update DB status for all pending/printing jobs by querying CUPS.
 */
function cups_sync_pending_jobs(PDO $db): void {
    $stmt = $db->query(
        "SELECT id, cups_job_id FROM print_jobs
         WHERE status IN ('pending','printing') AND cups_job_id IS NOT NULL"
    );
    $rows = $stmt->fetchAll();
    if (empty($rows)) return;

    $update = $db->prepare("UPDATE print_jobs SET status = ? WHERE id = ?");
    foreach ($rows as $row) {
        $new = cups_job_status((int)$row['cups_job_id'], DEFAULT_PRINTER);
        if ($new !== 'pending' && $new !== 'printing') {
            $update->execute([$new, $row['id']]);
        }
    }
}

/**
 * Poll job status. Returns 'pending'|'printing'|'done'|'failed'|'unknown'.
 */
function cups_job_status(int $cups_job_id, string $printer = ''): string {
    if ($cups_job_id <= 0) return 'unknown';

    $job_ref = $printer !== '' ? $printer . '-' . $cups_job_id : (string)$cups_job_id;
    exec('lpstat -o ' . escapeshellarg($job_ref) . ' 2>/dev/null', $lines, $code);

    if (!empty($lines)) {
        $line = implode(' ', $lines);
        if (stripos($line, 'processing') !== false) return 'printing';
        if (stripos($line, 'pending')    !== false) return 'pending';
        return 'printing';
    }

    // Not in active queue — check completed
    exec('lpstat -W completed -o ' . escapeshellarg($job_ref) . ' 2>/dev/null', $done_lines);
    if (!empty($done_lines)) return 'done';

    // Job disappeared — assume done
    return 'done';
}
