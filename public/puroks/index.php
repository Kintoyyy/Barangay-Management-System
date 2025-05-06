<?php
require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        try {
            if (empty($_POST['purok_number'])) {
                throw new Exception("Purok number is required.");
            }

            $purokNumber = trim($_POST['purok_number']);
            $purokName = !empty($_POST['purok_name']) ? trim($_POST['purok_name']) : null;
            $leaderId = !empty($_POST['leader_id']) ? (int)$_POST['leader_id'] : null;

            $uniqueCheck = $db->prepare("SELECT COUNT(*) FROM purok WHERE purok_number = ? AND purok_id != ?");
            $uniqueCheck->execute([$purokNumber, !empty($_POST['purok_id']) ? (int)$_POST['purok_id'] : 0]);
            
            if ($uniqueCheck->fetchColumn() > 0) {
                throw new Exception("Purok number already exists. Please use a different number.");
            }

            if (!empty($_POST['purok_id'])) {
                $stmt = $db->prepare("UPDATE purok SET 
                            purok_number = ?, 
                            purok_name = ?, 
                            leader_id = ? 
                            WHERE purok_id = ?");
                
                $success = $stmt->execute([
                    $purokNumber,
                    $purokName,
                    $leaderId,
                    $_POST['purok_id']
                ]);
                
                $message = 'Purok updated successfully';
                $messageType = 'success';
            } 
            else {
                $stmt = $db->prepare("INSERT INTO purok 
                            (purok_number, purok_name, leader_id) 
                            VALUES (?, ?, ?)");
                
                $success = $stmt->execute([
                    $purokNumber,
                    $purokName,
                    $leaderId
                ]);
                
                $message = 'Purok added successfully';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id'];
        
        $residentCheck = $db->prepare("SELECT COUNT(*) FROM residents WHERE purok_id = ?");
        $residentCheck->execute([$id]);
        
        if ($residentCheck->fetchColumn() > 0) {
            $message = 'Cannot delete: This purok has residents assigned to it. Please reassign residents first.';
            $messageType = 'warning';
        } else {
            $stmt = $db->prepare("DELETE FROM purok WHERE purok_id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                $message = 'Purok deleted successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete purok';
                $messageType = 'danger';
            }
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editPurok = [
    'purok_id' => '',
    'purok_number' => '',
    'purok_name' => '',
    'leader_id' => '',
    'date_created' => ''
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $stmt = $db->prepare("SELECT * FROM purok WHERE purok_id = ?");
        $stmt->execute([$_GET['id']]);
        $fetchedPurok = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetchedPurok) {
            $editPurok = $fetchedPurok;
        } else {
            $message = 'Purok not found';
            $messageType = 'warning';
            $showForm = false;
        }
    } catch (PDOException $e) {
        $message = 'Error loading purok: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'add') {
    $showForm = true;
}

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $countStmt = $db->query("SELECT COUNT(*) FROM purok");
    $totalPuroks = $countStmt->fetchColumn();
    $totalPages = ceil($totalPuroks / $limit);

    $stmt = $db->prepare("
        SELECT p.*, (r.first_name || ' ' || r.last_name) as leader_name,
        (SELECT COUNT(*) FROM residents WHERE purok_id = p.purok_id) as resident_count
        FROM purok p
        LEFT JOIN residents r ON p.leader_id = r.resident_id
        ORDER BY p.purok_number
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $puroks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading puroks: ' . $e->getMessage();
    $messageType = 'danger';
    $puroks = [];
    $totalPages = 0;
}

try {
    $residentStmt = $db->query("
        SELECT resident_id, (first_name || ' ' || last_name) as full_name 
        FROM residents 
        WHERE is_active = 1
        ORDER BY last_name, first_name
    ");
    $residents = $residentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $residents = [];
}
?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purok Management</title>
    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>

<body>
    <?php include_once '../navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Purok Management</h1>
                <p class="text-muted">Manage puroks and their leaders</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div id="purok-details" class="p-3 border rounded bg-light">
                    <?php if ($showForm): ?>
                        <div class="card">
                            <div class="card-header">
                                <?= empty($editPurok['purok_id']) ? 'Add New Purok' : 'Edit Purok' ?>
                            </div>
                            <div class="card-body">
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="purok_id" value="<?= htmlspecialchars($editPurok['purok_id']) ?>">

                                    <div class="mb-3">
                                        <label for="purok_number" class="form-label">Purok Number *</label>
                                        <input id="purok_number" name="purok_number" 
                                               value="<?= htmlspecialchars($editPurok['purok_number']) ?>"
                                               placeholder="e.g. 1A, 2B, 3" required class="form-control">
                                        <div class="form-text">Unique identifier for this purok</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="purok_name" class="form-label">Purok Name</label>
                                        <input id="purok_name" name="purok_name" 
                                               value="<?= htmlspecialchars($editPurok['purok_name'] ?? '') ?>"
                                               placeholder="e.g. Purok Malinis" class="form-control">
                                        <div class="form-text">Optional descriptive name</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="leader_id" class="form-label">Purok Leader</label>
                                        <select id="leader_id" name="leader_id" class="form-select">
                                            <option value="">-- Select Leader (Optional) --</option>
                                            <?php foreach ($residents as $resident): ?>
                                                <option value="<?= (int)$resident['resident_id'] ?>" 
                                                    <?= $editPurok['leader_id'] == $resident['resident_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($resident['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if (!empty($editPurok['date_created'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Date Created</label>
                                        <p class="form-control-plaintext">
                                            <?= date('F j, Y g:i A', strtotime($editPurok['date_created'])) ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-geo-alt-fill" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">Select a purok to or add a new one</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Puroks List</h2>
                    <a href="index.php?action=add" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Purok
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div id="puroks-table" class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Number</th>
                                        <th>Name</th>
                                        <th>Leader</th>
                                        <th>Residents Count</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($puroks) > 0): ?>
                                        <?php foreach ($puroks as $purok): ?>
                                            <tr>
                                                <td><?= (int)$purok['purok_id'] ?></td>
                                                <td><?= htmlspecialchars($purok['purok_number']) ?></td>
                                                <td><?= !empty($purok['purok_name']) ? htmlspecialchars($purok['purok_name']) : '-' ?></td>
                                                <td><?= !empty($purok['leader_name']) ? htmlspecialchars($purok['leader_name']) : '-' ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= (int)$purok['resident_count'] ?></span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($purok['date_created'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="index.php?action=edit&id=<?= (int)$purok['purok_id'] ?>"
                                                           class="btn btn-info btn-sm">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="index.php?action=delete&id=<?= (int)$purok['purok_id'] ?>"
                                                           class="btn btn-danger btn-sm"
                                                           onclick="return confirm('Are you sure you want to delete this purok?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <a href="../residents/index.php?purok=<?= (int)$purok['purok_id'] ?>"
                                                           class="btn btn-secondary btn-sm">
                                                            <i class="bi bi-people"></i> View Residents
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No puroks found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Purok pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php
                                        $prevDisabled = ($page <= 1) ? 'disabled' : '';
                                        $prevLink = ($page > 1) ? "index.php?page=" . ($page - 1) : "#";
                                        echo '<li class="page-item ' . $prevDisabled . '">';
                                        echo '<a class="page-link" href="' . $prevLink . '">&laquo; Previous</a>';
                                        echo '</li>';

                                        $startPage = max(1, min($page - 2, $totalPages - 4));
                                        $endPage = min($totalPages, max(5, $page + 2));

                                        for ($i = $startPage; $i <= $endPage; $i++) {
                                            $activeClass = ($i == $page) ? 'active' : '';
                                            echo '<li class="page-item ' . $activeClass . '">';
                                            echo '<a class="page-link" href="index.php?page=' . $i . '">' . $i . '</a>';
                                            echo '</li>';
                                        }

                                        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
                                        $nextLink = ($page < $totalPages) ? "index.php?page=" . ($page + 1) : "#";
                                        echo '<li class="page-item ' . $nextDisabled . '">';
                                        echo '<a class="page-link" href="' . $nextLink . '">Next &raquo;</a>';
                                        echo '</li>';
                                        ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once INCLUDES_PATH . '/scripts.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>

</html>