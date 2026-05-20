<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();
$user = current_user();
$db   = db();

$error   = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $new_username = trim($_POST['new_username'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $new_role     = ($_POST['new_role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($new_username === '' || $new_password === '') {
            $error = 'Username and password required.';
        } elseif (strlen($new_username) < 3 || strlen($new_username) > 32) {
            $error = 'Username must be 3–32 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $new_username)) {
            $error = 'Username may only contain letters, numbers, underscores, hyphens.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)"
                );
                $stmt->execute([$new_username, password_hash($new_password, PASSWORD_BCRYPT), $new_role]);
                $success = "User \"" . h($new_username) . "\" created successfully.";
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    $error = "Username \"" . h($new_username) . "\" already exists.";
                } else {
                    $error = 'Database error creating user.';
                }
            }
        }

    } elseif ($action === 'delete_user') {
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id === (int)$user['id']) {
            $error = 'Cannot delete your own account.';
        } elseif ($del_id > 0) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$del_id]);
            $success = 'User deleted.';
        }

    } elseif ($action === 'change_password') {
        $cp_id       = (int)($_POST['cp_id'] ?? 0);
        $cp_password = $_POST['cp_password'] ?? '';
        if ($cp_id <= 0) {
            $error = 'Invalid user.';
        } elseif (strlen($cp_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([password_hash($cp_password, PASSWORD_BCRYPT), $cp_id]);
            $success = 'Password updated.';
        }
    }
}

// Fetch data
$users = $db->query(
    'SELECT id, username, role, created_at,
     (SELECT COUNT(*) FROM print_jobs WHERE user_id = users.id) AS job_count
     FROM users ORDER BY created_at ASC'
)->fetchAll();

$jobs_page = max(1, (int)($_GET['jpage'] ?? 1));
$jobs_offset = ($jobs_page - 1) * JOBS_PER_PAGE;

$total_jobs  = (int)$db->query('SELECT COUNT(*) FROM print_jobs')->fetchColumn();
$total_pages = max(1, (int)ceil($total_jobs / JOBS_PER_PAGE));
$jobs_page   = min($jobs_page, $total_pages);
$jobs_offset = ($jobs_page - 1) * JOBS_PER_PAGE;

$stmt = $db->prepare(
    'SELECT pj.id, u.username, pj.original_name, pj.copies, pj.paper_size,
            pj.duplex, pj.quality, pj.status, pj.cups_job_id, pj.error_message, pj.created_at
     FROM print_jobs pj JOIN users u ON u.id = pj.user_id
     ORDER BY pj.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([JOBS_PER_PAGE, $jobs_offset]);
$all_jobs = $stmt->fetchAll();

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'jobs' ? 'jobs' : 'users';

layout_head('Admin', 'admin');
?>

<div class="page-header">
    <span class="page-title">Admin Panel</span>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="tab-bar">
    <button class="tab-btn <?= $active_tab === 'users' ? 'active' : '' ?>"
            data-tab="users-panel">Users</button>
    <button class="tab-btn <?= $active_tab === 'jobs' ? 'active' : '' ?>"
            data-tab="jobs-panel">All Print Jobs</button>
</div>
<div class="tab-content">

    <!-- Users Panel -->
    <div class="tab-panel <?= $active_tab === 'users' ? 'active' : '' ?>" id="users-panel">

        <!-- Create User -->
        <div class="window mb-16">
            <div class="window-titlebar">
                Create New User
            </div>
            <div class="window-body">
                <form method="post" action="/admin.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_user">
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                        <div class="form-row mb-0">
                            <label for="new_username">Username</label>
                            <input type="text" id="new_username" name="new_username"
                                   value="<?= h($_POST['new_username'] ?? '') ?>"
                                   autocomplete="off">
                        </div>
                        <div class="form-row mb-0">
                            <label for="new_password">Password</label>
                            <input type="password" id="new_password" name="new_password"
                                   autocomplete="new-password">
                        </div>
                        <div class="form-row mb-0">
                            <label for="new_role">Role</label>
                            <select id="new_role" name="new_role" style="max-width:120px">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-row mb-0" style="justify-content:flex-end">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- User List -->
        <div class="window">
            <div class="window-titlebar">
                Users (<?= count($users) ?>)
            </div>
            <div class="window-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th class="col-hide-mobile">Jobs</th>
                            <th class="col-hide-mobile">Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= h((string)$u['id']) ?></td>
                                <td><strong><?= h($u['username']) ?></strong>
                                    <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                        <span class="text-muted">(you)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-printing' : 'badge-done' ?>">
                                        <?= h(ucfirst($u['role'])) ?>
                                    </span>
                                </td>
                                <td class="col-hide-mobile"><?= h((string)$u['job_count']) ?></td>
                                <td class="col-hide-mobile" style="font-size:11px">
                                    <?= h(date('Y-m-d', strtotime($u['created_at']))) ?>
                                </td>
                                <td class="col-actions" style="white-space:nowrap">
                                    <!-- Change password inline toggle -->
                                    <button class="btn btn-sm"
                                            onclick="togglePassForm(<?= (int)$u['id'] ?>)">
                                        Set Password
                                    </button>
                                    <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                        <form method="post" action="/admin.php" style="display:inline"
                                              onsubmit="return confirm('Delete user <?= h(addslashes($u['username'])) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="del_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Inline password change row -->
                            <tr id="passForm-<?= (int)$u['id'] ?>" style="display:none;background:#FFFEE0">
                                <td colspan="6" style="padding:8px 12px">
                                    <form method="post" action="/admin.php"
                                          style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="change_password">
                                        <input type="hidden" name="cp_id" value="<?= (int)$u['id'] ?>">
                                        <span style="font-size:12px">New password for <strong><?= h($u['username']) ?></strong>:</span>
                                        <input type="password" name="cp_password" autocomplete="new-password"
                                               style="font-size:12px;padding:3px 6px;border:2px solid var(--gray);width:180px">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        <button type="button" class="btn btn-sm"
                                                onclick="togglePassForm(<?= (int)$u['id'] ?>)">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- /users-panel -->

    <!-- All Jobs Panel -->
    <div class="tab-panel <?= $active_tab === 'jobs' ? 'active' : '' ?>" id="jobs-panel">
        <div class="window">
            <div class="window-titlebar">
                All Print Jobs (<?= $total_jobs ?> total)
            </div>
            <div class="window-body">
                <?php if (empty($all_jobs)): ?>
                    <p class="text-muted text-center" style="padding:20px 0">No print jobs yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>File</th>
                                <th class="col-hide-mobile">Copies</th>
                                <th class="col-hide-mobile">Paper</th>
                                <th>Status</th>
                                <th class="col-hide-mobile">Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_jobs as $job): ?>
                                <tr>
                                    <td><?= h((string)$job['id']) ?></td>
                                    <td><strong><?= h($job['username']) ?></strong></td>
                                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                        title="<?= h($job['original_name']) ?>">
                                        <?= h($job['original_name']) ?>
                                    </td>
                                    <td class="col-hide-mobile"><?= h((string)$job['copies']) ?></td>
                                    <td class="col-hide-mobile"><?= h($job['paper_size']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= h($job['status']) ?>">
                                            <?= h(ucfirst($job['status'])) ?>
                                        </span>
                                        <?php if ($job['status'] === 'failed' && $job['error_message']): ?>
                                            <span title="<?= h($job['error_message']) ?>"
                                                  style="cursor:help">&#9432;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-hide-mobile" style="font-size:11px">
                                        <?= h(date('Y-m-d H:i', strtotime($job['created_at']))) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <a href="?tab=jobs&jpage=<?= $jobs_page - 1 ?>"
                               class="page-btn <?= $jobs_page <= 1 ? 'disabled' : '' ?>">&laquo; Prev</a>
                            <?php for ($p = max(1, $jobs_page - 2); $p <= min($total_pages, $jobs_page + 2); $p++): ?>
                                <a href="?tab=jobs&jpage=<?= $p ?>"
                                   class="page-btn <?= $p === $jobs_page ? 'current' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <a href="?tab=jobs&jpage=<?= $jobs_page + 1 ?>"
                               class="page-btn <?= $jobs_page >= $total_pages ? 'disabled' : '' ?>">Next &raquo;</a>
                            <span class="text-muted">Page <?= $jobs_page ?> of <?= $total_pages ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /jobs-panel -->

</div><!-- .tab-content -->

<?php layout_foot(); ?>
