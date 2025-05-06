<?php
require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

// if (is_logged_in()) {
//     header('Location: ../dashboard/index.php');
//     exit;
// }

// if (!has_role('admin')) {
//     $_SESSION['message'] = ['text' => 'You do not have permission to access this page.', 'type' => 'danger'];
//     header('Location: ../dashboard/index.php');
//     exit;
// }


$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']['text'];
    $messageType = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}


$formData = [
    'username' => '',
    'official_id' => '',
    'role' => 'official'
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username'] = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $formData['official_id'] = !empty($_POST['official_id']) ? (int)$_POST['official_id'] : null;
    $formData['role'] = trim($_POST['role'] ?? 'official'); // Default if not provided

    try {
        if (empty($formData['username']) || empty($password) || empty($confirmPassword)) {
            throw new Exception("Username, password, and confirm password are required.");
        }

        if ($password !== $confirmPassword) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$formData['username']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Username already exists. Please choose a different one.");
        }

        if ($formData['official_id'] !== null) {
            $officialCheck = $db->prepare("SELECT COUNT(*) FROM barangay_officials WHERE official_id = ?");
            $officialCheck->execute([$formData['official_id']]);
            if ($officialCheck->fetchColumn() === 0) {
                 throw new Exception("Invalid official selected.");
            }
             $officialUserCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE official_id = ?");
             $officialUserCheck->execute([$formData['official_id']]);
             if ($officialUserCheck->fetchColumn() > 0) {
                 throw new Exception("This official is already linked to another user account.");
             }
        }

        $allowedRoles = ['admin', 'official', 'health_worker'];
        if (!in_array($formData['role'], $allowedRoles)) {
             throw new Exception("Invalid role selected.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("INSERT INTO users (username, password, official_id, role) VALUES (?, ?, ?, ?)");
        $success = $stmt->execute([
            $formData['username'],
            $hashedPassword,
            $formData['official_id'],
            $formData['role']
        ]);

        if ($success) {
            $_SESSION['message'] = ['text' => 'User registered successfully!', 'type' => 'success'];
            $formData = [
                'username' => '',
                'official_id' => '',
                'role' => 'official'
            ];
             // header('Location: register.php'); // Redirect back to the registration page
             // exit;
        } else {
            throw new Exception("Failed to register user.");
        }

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}
$officials = [];
try {
    $officialStmt = $db->query("
        SELECT
            bo.official_id,
            (r.first_name || ' ' || r.last_name) as full_name
        FROM barangay_officials bo
        JOIN residents r ON bo.resident_id = r.resident_id
        WHERE bo.is_active = TRUE
        ORDER BY r.last_name, r.first_name
    ");
    $officials = $officialStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching officials for dropdown: " . $e->getMessage());
}

$allowedRoles = ['admin', 'official', 'health_worker'];

?>
<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register User - Barangay System</title>
    <?php
    $includesPath = __DIR__ . '/../includes';
    if (file_exists($includesPath . '/styles.php')) {
        include_once $includesPath . '/styles.php';
    } else {
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    }
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
     <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .register-container h1 {
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <?php
    // if (is_logged_in() && has_role('admin')) {
        $templatePath = __DIR__ . '/../templates';
        if (file_exists($templatePath . '/navbar.php')) {
            include_once $templatePath . '/navbar.php';
        }
    // }
    ?>

    <div class="register-container">
        <h1>Register New User</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <div class="mb-3">
                <label for="username" class="form-label">Username *</label>
                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($formData['username']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password *</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password *</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>

             <div class="mb-3">
                <label for="official_id" class="form-label">Link to Official (Optional)</label>
                <select id="official_id" name="official_id" class="form-select">
                    <option value="">-- Select Official (Optional) --</option>
                    <?php foreach ($officials as $official): ?>
                        <option value="<?= (int)$official['official_id'] ?>"
                                <?= $formData['official_id'] == $official['official_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($official['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                 <div class="form-text">Link this user account to an existing barangay official.</div>
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Role *</label>
                <select id="role" name="role" class="form-select" required>
                     <?php foreach ($allowedRoles as $role): ?>
                        <option value="<?= htmlspecialchars($role) ?>"
                                <?= $formData['role'] == $role ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($role)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                 <div class="form-text">Assign a role to the new user.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">Register</button>
        </form>
    </div>

    <?php
    $includesPath = __DIR__ . '/../includes';
     if (file_exists($includesPath . '/scripts.php')) {
        include_once $includesPath . '/scripts.php';
    } else {
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
    }
    ?>
</body>

</html>
