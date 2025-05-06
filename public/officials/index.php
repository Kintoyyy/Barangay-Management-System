<?php
require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        try {
            // Validate required fields
            if (empty($_POST['resident_id'])) {
                throw new Exception("Resident selection is required.");
            }
            if (empty($_POST['position'])) {
                throw new Exception("Position is required.");
            }
            if (empty($_POST['term_start'])) {
                throw new Exception("Term start date is required.");
            }

            $residentId = (int)$_POST['resident_id'];
            $position = trim($_POST['position']);
            $termStart = trim($_POST['term_start']);
            $termEnd = !empty($_POST['term_end']) ? trim($_POST['term_end']) : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            // Check if this resident already holds an active position (if this is a new record or changing resident)
            if (empty($_POST['official_id']) || (isset($_POST['original_resident_id']) && $_POST['original_resident_id'] != $residentId)) {
                $activeCheck = $db->prepare("SELECT COUNT(*) FROM barangay_officials 
                                           WHERE resident_id = ? AND is_active = 1 AND official_id != ?");
                $activeCheck->execute([$residentId, !empty($_POST['official_id']) ? (int)$_POST['official_id'] : 0]);
                
                if ($activeCheck->fetchColumn() > 0) {
                    throw new Exception("This resident already holds an active barangay position.");
                }
            }

            if (!empty($_POST['official_id'])) {
                // Update existing official
                $stmt = $db->prepare("UPDATE barangay_officials SET 
                            resident_id = ?, 
                            position = ?, 
                            term_start = ?, 
                            term_end = ?,
                            is_active = ?
                            WHERE official_id = ?");
                
                $success = $stmt->execute([
                    $residentId,
                    $position,
                    $termStart,
                    $termEnd,
                    $isActive,
                    $_POST['official_id']
                ]);
                
                $message = 'Barangay official updated successfully';
                $messageType = 'success';
            } else {
                // Create new official
                $stmt = $db->prepare("INSERT INTO barangay_officials 
                            (resident_id, position, term_start, term_end, is_active) 
                            VALUES (?, ?, ?, ?, ?)");
                
                $success = $stmt->execute([
                    $residentId,
                    $position,
                    $termStart,
                    $termEnd,
                    $isActive
                ]);
                
                $message = 'Barangay official added successfully';
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
        
        $stmt = $db->prepare("DELETE FROM barangay_officials WHERE official_id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            $message = 'Barangay official deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete barangay official';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editOfficial = [
    'official_id' => '',
    'resident_id' => '',
    'position' => '',
    'term_start' => '',
    'term_end' => '',
    'is_active' => 1
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $stmt = $db->prepare("SELECT * FROM barangay_officials WHERE official_id = ?");
        $stmt->execute([$_GET['id']]);
        $fetchedOfficial = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetchedOfficial) {
            $editOfficial = $fetchedOfficial;
        } else {
            $message = 'Barangay official not found';
            $messageType = 'warning';
            $showForm = false;
        }
    } catch (PDOException $e) {
        $message = 'Error loading barangay official: ' . $e->getMessage();
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

    $countStmt = $db->query("SELECT COUNT(*) FROM barangay_officials");
    $totalOfficials = $countStmt->fetchColumn();
    $totalPages = ceil($totalOfficials / $limit);

    $stmt = $db->prepare("
        SELECT bo.*, 
               (r.first_name || ' ' || r.last_name) as resident_name,
               r.contact_number
        FROM barangay_officials bo
        JOIN residents r ON bo.resident_id = r.resident_id
        ORDER BY 
            CASE 
                WHEN bo.position = 'Captain' THEN 1
                WHEN bo.position = 'Kagawad' THEN 2
                WHEN bo.position = 'Secretary' THEN 3
                WHEN bo.position = 'Treasurer' THEN 4
                WHEN bo.position = 'SK Chair' THEN 5
                WHEN bo.position = 'Health Worker' THEN 6
                WHEN bo.position = 'Tanod' THEN 7
                ELSE 8
            END,
            bo.term_start DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $officials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading barangay officials: ' . $e->getMessage();
    $messageType = 'danger';
    $officials = [];
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

// Get position options from ENUM values
try {
    $stmt = $db->query("SHOW COLUMNS FROM barangay_officials LIKE 'position'");
    $type = $stmt->fetch(PDO::FETCH_ASSOC)['Type'];
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
    $positionOptions = explode("','", $matches[1]);
} catch (PDOException $e) {
    $positionOptions = ['Captain', 'Kagawad', 'Secretary', 'Treasurer', 'Tanod', 'SK Chair', 'Health Worker'];
}
?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay Officials Management</title>
    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .badge-captain { background-color: #dc3545; }
        .badge-kagawad { background-color: #0d6efd; }
        .badge-secretary { background-color: #198754; }
        .badge-treasurer { background-color: #6f42c1; }
        .badge-tanod { background-color: #fd7e14; }
        .badge-sk { background-color: #20c997; }
        .badge-health { background-color: #d63384; }
    </style>
</head>

<body>
    <?php include_once '../navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Barangay Officials Management</h1>
                <p class="text-muted">Manage barangay officials and their positions</p>
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
                <div id="official-details" class="p-3 border rounded bg-light">
                    <?php if ($showForm): ?>
                        <div class="card">
                            <div class="card-header">
                                <?= empty($editOfficial['official_id']) ? 'Add New Barangay Official' : 'Edit Barangay Official' ?>
                            </div>
                            <div class="card-body">
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="official_id" value="<?= htmlspecialchars($editOfficial['official_id']) ?>">
                                    <?php if (!empty($editOfficial['resident_id'])): ?>
                                        <input type="hidden" name="original_resident_id" value="<?= htmlspecialchars($editOfficial['resident_id']) ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="resident_id" class="form-label">Resident *</label>
                                        <select id="resident_id" name="resident_id" required class="form-select">
                                            <option value="">-- Select Resident --</option>
                                            <?php foreach ($residents as $resident): ?>
                                                <option value="<?= (int)$resident['resident_id'] ?>" 
                                                    <?= $editOfficial['resident_id'] == $resident['resident_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($resident['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="position" class="form-label">Position *</label>
                                        <select id="position" name="position" required class="form-select">
                                            <option value="">-- Select Position --</option>
                                            <?php foreach ($positionOptions as $option): ?>
                                                <option value="<?= htmlspecialchars($option) ?>" 
                                                    <?= $editOfficial['position'] === $option ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($option) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="term_start" class="form-label">Term Start *</label>
                                            <input type="date" id="term_start" name="term_start" 
                                                   value="<?= htmlspecialchars($editOfficial['term_start']) ?>"
                                                   required class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="term_end" class="form-label">Term End</label>
                                            <input type="date" id="term_end" name="term_end" 
                                                   value="<?= htmlspecialchars($editOfficial['term_end'] ?? '') ?>"
                                                   class="form-control">
                                            <div class="form-text">Leave empty if current term</div>
                                        </div>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" 
                                               name="is_active" <?= $editOfficial['is_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_active">Active Position</label>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-person-badge-fill" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">Select an official to edit or add a new one</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Barangay Officials List</h2>
                    <a href="index.php?action=add" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Official
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div id="officials-table" class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Resident</th>
                                        <th>Position</th>
                                        <th>Term</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($officials) > 0): ?>
                                        <?php foreach ($officials as $official): 
                                            $termEnd = !empty($official['term_end']) ? date('M j, Y', strtotime($official['term_end'])) : 'Present';
                                            $termText = date('M j, Y', strtotime($official['term_start'])) . ' to ' . $termEnd;
                                            
                                            // Determine badge class based on position
                                            $badgeClass = 'badge-';
                                            switch ($official['position']) {
                                                case 'Captain': $badgeClass .= 'captain'; break;
                                                case 'Kagawad': $badgeClass .= 'kagawad'; break;
                                                case 'Secretary': $badgeClass .= 'secretary'; break;
                                                case 'Treasurer': $badgeClass .= 'treasurer'; break;
                                                case 'Tanod': $badgeClass .= 'tanod'; break;
                                                case 'SK Chair': $badgeClass .= 'sk'; break;
                                                case 'Health Worker': $badgeClass .= 'health'; break;
                                                default: $badgeClass .= 'secondary';
                                            }
                                        ?>
                                            <tr>
                                                <td><?= (int)$official['official_id'] ?></td>
                                                <td><?= htmlspecialchars($official['resident_name']) ?></td>
                                                <td>
                                                    <span class="badge <?= $badgeClass ?>">
                                                        <?= htmlspecialchars($official['position']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $termText ?></td>
                                                <td>
                                                    <?php if ($official['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="index.php?action=edit&id=<?= (int)$official['official_id'] ?>"
                                                           class="btn btn-info btn-sm">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </a>
                                                        <a href="index.php?action=delete&id=<?= (int)$official['official_id'] ?>"
                                                           class="btn btn-danger btn-sm"
                                                           onclick="return confirm('Are you sure you want to delete this official record?');">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No barangay officials found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Officials pagination">
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

            // Set term end min date based on term start
            const termStart = document.getElementById('term_start');
            const termEnd = document.getElementById('term_end');
            
            if (termStart && termEnd) {
                termStart.addEventListener('change', function() {
                    termEnd.min = this.value;
                });
            }
        });
    </script>
</body>

</html>