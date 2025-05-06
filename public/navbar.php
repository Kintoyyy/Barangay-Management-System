<?php
// This is a PHP snippet intended to be included where your navbar is needed.
// It assumes session_start() has been called earlier (e.g., in bootstrap.php)
// and that $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'] are set on login.

// Determine the current page path for highlighting the active link
$current_page = $_SERVER[ 'REQUEST_URI' ];
// Remove query string for simpler matching
$current_page = strtok( $current_page, '?' );

// Check if user is logged in
$is_logged_in       = isset( $_SESSION[ 'user_id' ] );
$logged_in_username = $is_logged_in ? htmlspecialchars( $_SESSION[ 'username' ] ) : '';
$logged_in_role     = $is_logged_in ? htmlspecialchars( ucfirst( $_SESSION[ 'role' ] ) ) : ''; // Capitalize role
?>

<nav class="navbar navbar-expand-lg"
  style="
    background-color: var(--bs-content-bg);
    border-bottom: var(--bs-border-width) solid var(--bs-content-border-color);
  ">
  <div class="container-fluid">
    <a class="navbar-brand"
      href="/">
      <i class="bi bi-building me-2"></i> Barangay Management System
    </a>
    <button class="navbar-toggler"
      type="button"
      data-bs-toggle="offcanvas"
      data-bs-target="#navbar-offcanvas"
      aria-controls="navbar-offcanvas"
      aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end"
      tabindex="-1"
      id="navbar-offcanvas"
      aria-labelledby="navbar-offcanvas-label">
      <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title"
          id="navbar-offcanvas-label">Navigation</h5>
        <button type="button"
          class="btn-close"
          data-bs-dismiss="offcanvas"
          aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/dashboard/' || $current_page === '/dashboard/index.php' || $current_page === '/' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/residents/' || $current_page === '/residents/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/residents/">Residents</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/households/' || $current_page === '/households/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/households/">Households</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/puroks/' || $current_page === '/puroks/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/puroks/">Purok</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/documents/' || $current_page === '/documents/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/documents/">Documents</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/complaints/' || $current_page === '/complaints/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/complaints/">Complaints</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/health-records/' || $current_page === '/health-records/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/health-records/">Health Records</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/financial-transactions/' || $current_page === '/financial-transactions/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/financial-transactions/">Financial Transactions</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= ( $current_page === '/officials/' || $current_page === '/officials/index.php' ) ? 'active' : '' ?>"
              aria-current="page"
              href="/officials/">Officials</a>
          </li>
          <?php if ( $is_logged_in && isset( $_SESSION[ 'role' ] ) && $_SESSION[ 'role' ] === 'admin' ): // Example: only show register to admin ?>
            <li class="nav-item">
              <a class="nav-link <?= ( $current_page === '/auth/register.php' ) ? 'active' : '' ?>"
                aria-current="page"
                href="/auth/register.php">Register User</a>
            </li>
          <?php endif; ?>
        </ul>
        <ul class="navbar-nav">
          <?php if ( $is_logged_in ): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle"
                href="#"
                id="navbarDropdownUser"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i> <?= $logged_in_username ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end"
                aria-labelledby="navbarDropdownUser">
                <li><span class="dropdown-item-text">Role: <?= $logged_in_role ?></span></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item"
                    href="/auth/logout.php">Logout</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link <?= ( $current_page === '/auth/login.php' ) ? 'active' : '' ?>"
                href="/auth/login.php">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</nav>