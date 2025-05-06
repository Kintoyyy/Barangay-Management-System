<?php
session_start();

if ( isset( $_SESSION[ 'user_id' ] ) ) {
    header( 'Location: ../' );
    exit;
}

$message     = '';
$messageType = '';
if ( isset( $_SESSION[ 'message' ] ) ) {
    $message     = $_SESSION[ 'message' ][ 'text' ];
    $messageType = $_SESSION[ 'message' ][ 'type' ];
    unset( $_SESSION[ 'message' ] );
}

?>
<!doctype html>
<html lang="en"
    data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1">
    <title>Login - Barangay System</title>
    <?php
    $includesPath = __DIR__ . '/../includes';
    if ( file_exists( $includesPath . '/styles.php' ) ) {
        include_once $includesPath . '/styles.php';
    }
    else {
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    }
    ?>
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .login-container h1 {
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>Login</h1>

        <?php if ( !empty( $message ) ): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"
                role="alert">
                <?= htmlspecialchars( $message ) ?>
                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="authenticate.php"
            method="post">
            <div class="mb-3">
                <label for="username"
                    class="form-label">Username</label>
                <input type="text"
                    class="form-control"
                    id="username"
                    name="username"
                    required>
            </div>
            <div class="mb-3">
                <label for="password"
                    class="form-label">Password</label>
                <input type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    required>
            </div>
            <button type="submit"
                class="btn btn-primary w-100">Login</button>
        </form>
    </div>

    <?php
    $includesPath = __DIR__ . '/../includes';
    if ( file_exists( $includesPath . '/scripts.php' ) ) {
        include_once $includesPath . '/scripts.php';
    }
    else {
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
    }
    ?>
</body>

</html>