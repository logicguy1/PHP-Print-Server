<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();
$user = current_user();

$db = db();

// Stats
$total  = (int)$db->prepare('SELECT COUNT(*) FROM print_jobs WHERE user_id = ?')
              ->execute([$user['id']]) ? $db->query(
                  "SELECT COUNT(*) FROM print_jobs WHERE user_id = {$user['id']}")->fetchColumn() : 0;

$stmt = $db->prepare('SELECT COUNT(*) FROM print_jobs WHERE user_id = ?');
$stmt->execute([$user['id']]);
$total = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM print_jobs WHERE user_id = ? AND status = 'done'");
$stmt->execute([$user['id']]);
$done = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM print_jobs WHERE user_id = ? AND status IN ('pending','printing')");
$stmt->execute([$user['id']]);
$active = (int)$stmt->fetchColumn();

// Recent 5 jobs
$stmt = $db->prepare(
    'SELECT id, original_name, copies, paper_size, duplex, quality, status, created_at
     FROM print_jobs WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 5'
);
$stmt->execute([$user['id']]);
$recent = $stmt->fetchAll();

layout_head('Dashboard', 'dashboard');
?>

<div class="page-header">
    <span class="page-title">Dashboard</span>
    <a href="/new_job.php" class="btn btn-primary">+ New Print Job</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-box-value"><?= $total ?></div>
        <div class="stat-box-label">Total Jobs</div>
    </div>
    <div class="stat-box">
        <div class="stat-box-value"><?= $done ?></div>
        <div class="stat-box-label">Completed</div>
    </div>
    <div class="stat-box">
        <div class="stat-box-value"><?= $active ?></div>
        <div class="stat-box-label">In Queue</div>
    </div>
</div>

<!-- Recent Jobs -->
<div class="window">
    <div class="window-titlebar">
        Recent Print Jobs
    </div>
    <div class="window-body">
        <?php if (empty($recent)): ?>
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
                        <th>Status</th>
                        <th class="col-hide-mobile">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $job): ?>
                        <tr>
                            <td><?= h((string)$job['id']) ?></td>
                            <td class="nowrap" style="max-width:200px;overflow:hidden;text-overflow:ellipsis">
                                <?= h($job['original_name']) ?>
                            </td>
                            <td class="col-hide-mobile"><?= h((string)$job['copies']) ?></td>
                            <td class="col-hide-mobile"><?= h($job['paper_size']) ?></td>
                            <td>
                                <span class="badge badge-<?= h($job['status']) ?>">
                                    <?= h(ucfirst($job['status'])) ?>
                                </span>
                            </td>
                            <td class="col-hide-mobile text-muted">
                                <?= h(date('Y-m-d H:i', strtotime($job['created_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-8 text-right">
                <a href="/history.php" class="btn btn-sm">View All History &raquo;</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php layout_foot(); ?>
