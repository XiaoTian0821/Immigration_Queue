<?php
/**
 * Shared layout TOP.
 * Usage: set $page_title, $page_active, optionally $role_nav ('admin'|'officer'),
 * then include __DIR__ . '/parts/layout-top.php'.
 */
$me = Auth::currentUser();
$nav = [
    'admin' => [
        ['url' => APP_URL . 'admin/dashboard.php',  'icon' => 'fa-gauge-high',  'label' => 'Dashboard'],
        ['url' => APP_URL . 'admin/officers.php',    'icon' => 'fa-user-shield', 'label' => 'Officers'],
        ['url' => APP_URL . 'admin/services.php',    'icon' => 'fa-id-card',     'label' => 'Services'],
        ['url' => APP_URL . 'admin/counters.php',    'icon' => 'fa-desktop',     'label' => 'Counters'],
        ['url' => APP_URL . 'admin/queues.php',      'icon' => 'fa-list-ol',     'label' => 'Queues'],
        ['url' => APP_URL . 'admin/applicants.php',  'icon' => 'fa-users',       'label' => 'Applicants'],
        ['url' => APP_URL . 'admin/reports.php',     'icon' => 'fa-chart-line',  'label' => 'Reports'],
        ['url' => APP_URL . 'admin/activity.php',    'icon' => 'fa-clipboard-list','label' => 'Activity Logs'],
        ['url' => APP_URL . 'admin/settings.php',    'icon' => 'fa-gear',        'label' => 'Settings'],
    ],
    'officer' => [
        ['url' => APP_URL . 'officer/dashboard.php',     'icon' => 'fa-gauge-high', 'label' => 'Dashboard'],
        ['url' => APP_URL . 'officer/new-applicant.php', 'icon' => 'fa-user-plus',   'label' => 'Register Applicant'],
        ['url' => APP_URL . 'officer/queue-ops.php',     'icon' => 'fa-phone-volume','label' => 'Queue Operations'],
        ['url' => APP_URL . 'officer/my-queues.php',     'icon' => 'fa-list-check',  'label' => 'My Queue History'],
    ],
];
$navKey   = $nav_key   ?? $me['role'];
$items    = $nav[$navKey] ?? [];
$flash    = Security::getFlash();
$base     = '../'; // shared layouts are only included from /admin/ or /officer/ pages
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Security::e($page_title ?? APP_NAME) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body class="d-flex">
<nav id="sidebar" class="sidebar d-flex flex-column p-3 text-white">
  <div class="d-flex align-items-center gap-2 mb-4">
    <i class="fa-solid fa-landmark fa-2x" style="color:#4dd2ff"></i>
    <div>
      <div class="fw-bold small text-uppercase"><?= Security::e(APP_NAME) ?></div>
      <div style="font-size:.7rem;color:#9fb8d0">Immigration Office</div>
    </div>
  </div>
  <ul class="nav nav-pills flex-column gap-1">
    <?php foreach ($items as $n): $active = (basename($_SERVER['SCRIPT_NAME']) === basename($n['url'])); ?>
    <li class="nav-item">
      <a class="nav-link py-2 <?= $active ? 'active' : 'text-white-50' ?>" href="<?= $n['url'] ?>">
        <i class="fa-solid <?= $n['icon'] ?> me-2"></i><?= $n['label'] ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <hr style="border-color:rgba(255,255,255,.2)">
  <a class="nav-link text-white-50 py-2" href="<?= APP_URL ?>public/display.php" target="_blank"><i class="fa-solid fa-tv me-2"></i>Queue Display</a>
  <a class="nav-link text-white-50 py-2" href="<?= APP_URL ?>public/ticket.php"><i class="fa-solid fa-ticket me-2"></i>Applicant Ticket</a>
  <?php if ($me['role'] === 'admin'): ?>
  <a class="nav-link text-white-50 py-2" data-reset-day href="<?= APP_URL ?>api/reset-day.php"><i class="fa-solid fa-rotate-left me-2"></i>Reset Day Queue</a>
  <?php endif; ?>
  <a class="nav-link text-danger py-2 mt-auto" href="<?= APP_URL ?>logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
</nav>

<main class="flex-grow-1">
  <header class="topbar d-flex align-items-center justify-content-between px-3 px-lg-4 py-2 shadow-sm">
    <button class="btn btn-light d-lg-none" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
    <h1 class="h4 m-0 fw-bold"><?= Security::e($page_title ?? '') ?></h1>
    <div class="d-flex align-items-center gap-3">
      <span class="badge bg-light text-dark d-none d-sm-inline-block"><?= Security::e(date('D, d M Y H:i')) ?></span>
      <div class="dropdown">
        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fa-solid fa-circle-user text-primary me-1"></i><?= Security::e($me['full_name']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text small text-muted"><?= ucfirst($me['role']) ?> account</span></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= APP_URL ?>logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </header>

  <?php if ($flash): ?>
  <div class="px-3 px-lg-4 pt-3">
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible show" role="alert">
      <?= Security::e($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php endif; ?>

  <div class="content px-3 px-lg-4 py-3">
