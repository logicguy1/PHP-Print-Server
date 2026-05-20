<?php
// layout_head($title) — outputs <html> through <body> opening + nav
// layout_foot()       — closes page + status bar

function layout_head(string $title, string $active = ''): void {
    $user = current_user();
    $nav_items = [
        'dashboard' => ['/dashboard.php', 'Dashboard'],
        'new_job'   => ['/new_job.php',   'New Print Job'],
        'history'   => ['/history.php',   'Print History'],
    ];
    if ($user['role'] === 'admin') {
        $nav_items['admin'] = ['/admin.php', 'Admin'];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="app-shell">

<div class="topbar">
    <div class="topbar-brand">
        <?= h(APP_NAME) ?>
    </div>
    <nav class="topbar-nav">
        <?php foreach ($nav_items as $key => [$href, $label]): ?>
            <a href="<?= h($href) ?>"
               class="nav-link<?= $active === $key ? ' nav-link--active' : '' ?>">
                <?= h($label) ?>
            </a>
        <?php endforeach; ?>
        <a href="/logout.php" class="nav-link nav-link--logout" title="Log Out">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"
                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M12 3v9"/>
                <path d="M6.4 5.5a8 8 0 1 0 11.2 0"/>
            </svg>
        </a>
    </nav>
    <button class="nav-toggle" id="navToggle" aria-label="Menu">&#9776;</button>
</div>

<nav class="mobile-nav" id="mobileNav">
    <?php foreach ($nav_items as $key => [$href, $label]): ?>
        <a href="<?= h($href) ?>"
           class="mobile-nav-link<?= $active === $key ? ' mobile-nav-link--active' : '' ?>">
            <?= h($label) ?>
        </a>
    <?php endforeach; ?>
    <a href="/logout.php" class="mobile-nav-link">Log Out</a>
</nav>

<main class="main-content">
    <?php
}

function layout_foot(string $before_scripts = ''): void {
    $user = current_user();
    ?>
</main>

<div class="statusbar">
    <span class="statusbar-item">User: <strong><?= h($user['username']) ?></strong></span>
    <?php if (defined('DEFAULT_PRINTER') && DEFAULT_PRINTER !== ''): ?>
        <span class="statusbar-item statusbar-sep">|</span>
        <span class="statusbar-item">Printer: <strong><?= h(DEFAULT_PRINTER) ?></strong></span>
    <?php endif; ?>
    <span class="statusbar-item statusbar-right"><?= h(APP_NAME) ?> v<?= h(APP_VERSION) ?></span>
</div>

</div><!-- .app-shell -->

<?php if ($before_scripts !== '') echo $before_scripts . "\n"; ?>
<script src="/js/app.js"></script>
</body>
</html>
    <?php
}
