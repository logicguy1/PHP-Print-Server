<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();
$user = current_user();

$db   = db();
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * JOBS_PER_PAGE;

$stmt = $db->prepare('SELECT COUNT(*) FROM print_jobs WHERE user_id = ?');
$stmt->execute([$user['id']]);
$total_jobs = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_jobs / JOBS_PER_PAGE));
$page = min($page, $total_pages);
$offset = ($page - 1) * JOBS_PER_PAGE;

$stmt = $db->prepare(
    'SELECT id, original_name, copies, paper_size, duplex, quality, status, cups_job_id, error_message, created_at
     FROM print_jobs WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$user['id'], JOBS_PER_PAGE, $offset]);
$jobs = $stmt->fetchAll();

layout_head('Print History', 'history');
?>

<div class="page-header">
    <span class="page-title">Print History</span>
    <a href="/new_job.php" class="btn btn-primary">+ New Print Job</a>
</div>

<div class="window">
    <div class="window-titlebar">
        Print Jobs (<?= $total_jobs ?> total)
    </div>
    <div class="window-body">
        <?php if (empty($jobs)): ?>
            <p class="text-muted text-center" style="padding:20px 0">
                No print jobs yet. <a href="/new_job.php">Submit your first job</a>.
            </p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>File</th>
                        <th class="col-hide-mobile">Copies</th>
                        <th class="col-hide-mobile">Paper</th>
                        <th class="col-hide-mobile">Duplex</th>
                        <th class="col-hide-mobile">Quality</th>
                        <th>Status</th>
                        <th class="col-hide-mobile">CUPS Job</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?= h((string)$job['id']) ?></td>
                            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                title="<?= h($job['original_name']) ?>">
                                <?= h($job['original_name']) ?>
                            </td>
                            <td class="col-hide-mobile"><?= h((string)$job['copies']) ?></td>
                            <td class="col-hide-mobile"><?= h($job['paper_size']) ?></td>
                            <td class="col-hide-mobile"><?= h($job['duplex'] === 'none' ? 'Off' : ucwords(str_replace('-', ' ', $job['duplex']))) ?></td>
                            <td class="col-hide-mobile"><?= h(ucfirst($job['quality'])) ?></td>
                            <td>
                                <span class="badge badge-<?= h($job['status']) ?>">
                                    <?= h(ucfirst($job['status'])) ?>
                                </span>
                                <?php if ($job['status'] === 'failed' && $job['error_message']): ?>
                                    <span title="<?= h($job['error_message']) ?>"
                                          style="cursor:help;font-size:12px">&#9432;</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-hide-mobile text-muted text-mono">
                                <?= $job['cups_job_id'] ? h((string)$job['cups_job_id']) : '—' ?>
                            </td>
                            <td class="nowrap" style="font-size:11px">
                                <?= h(date('Y-m-d H:i', strtotime($job['created_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <a href="?page=<?= $page - 1 ?>"
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">&laquo; Prev</a>
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="?page=<?= $p ?>"
                           class="page-btn <?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="?page=<?= $page + 1 ?>"
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Next &raquo;</a>
                    <span class="text-muted">Page <?= $page ?> of <?= $total_pages ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php layout_foot(); ?>
