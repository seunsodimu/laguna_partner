<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Laguna Partners Portal' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_PATH ?>/">
                <i class="bi bi-gear-fill"></i> Laguna Partners
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php
                    $user = \LagunaPartners\Auth::user();
                    if ($user):
                        switch ($user['role']):
                            case 'vendor':
                    ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/vendor/dashboard.php">
                                        <i class="bi bi-file-earmark-text"></i> Purchase Orders
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/vendor/invoices.php">
                                        <i class="bi bi-receipt"></i> Invoices
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/vendor/messages.php">
                                        <i class="bi bi-chat-square-text"></i> Messaging
                                    </a>
                                </li>
                    <?php
                                break;
                            case 'dealer':
                    ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/dealer/dashboard.php">
                                        <i class="bi bi-box-seam"></i> Items
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/dealer/notifications.php">
                                        <i class="bi bi-bell"></i> Notifications
                                    </a>
                                </li>
                    <?php
                                break;
                                case 'accounting':
                    ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/dashboard.php">
                                        <i class="bi bi-file-earmark-text"></i> Purchase Orders
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/invoices.php">
                                        <i class="bi bi-receipt"></i> Invoices
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/messages.php">
                                        <i class="bi bi-chat-square-text"></i> Messaging
                                    </a>
                                </li>
                    <?php
                                break;
                            case 'buyer':
                    ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/dashboard.php">
                                        <i class="bi bi-file-earmark-text"></i> Purchase Orders
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/invoices.php">
                                        <i class="bi bi-receipt"></i> Invoices
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/messages.php">
                                        <i class="bi bi-chat-square-text"></i> Messaging
                                    </a>
                                </li>
                    <?php
                                break;
                            case 'admin':
                    ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/admin/dashboard.php">
                                        <i class="bi bi-speedometer2"></i> Dashboard
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/admin/sync.php">
                                        <i class="bi bi-arrow-repeat"></i> Sync
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/admin/users.php">
                                        <i class="bi bi-people"></i> Users
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/admin/purchase-orders.php">
                                        <i class="bi bi-file-earmark-text"></i> Purchase Orders
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/admin/logs.php">
                                        <i class="bi bi-journal-text"></i> Logs
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= BASE_PATH ?>/admin/email-templates.php">
                                        <i class="bi bi-envelope"></i> Email Templates
                                    </a>
                                </li>
                    <?php
                                break;
                        endswitch;
                    endif;
                    ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if ($user): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['email']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text"><small>Logged in as: <strong><?= ucfirst($user['type']) ?></strong></small></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>