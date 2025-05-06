<?php
require __DIR__ . '/../../config/bootstrap.php';


$db = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        try {
            if (empty($_POST['resident_id'])) {
                throw new Exception("Resident selection is required.");
            }
            if (empty($_POST['complaint_type'])) {
                throw new Exception("Complaint type is required.");
            }
             if (empty($_POST['description'])) {
                throw new Exception("Complaint description is required.");
            }
             if (empty($_POST['status'])) {
                throw new Exception("Status is required.");
            }

            $residentId = (int)$_POST['resident_id'];
            $complaintType = trim($_POST['complaint_type']);
            $description = trim($_POST['description']);
            $status = trim($_POST['status']);
            $resolvedBy = !empty($_POST['resolved_by']) ? (int)$_POST['resolved_by'] : null;
            $resolutionNotes = !empty($_POST['resolution_notes']) ? trim($_POST['resolution_notes']) : null;

            if (!in_array($complaintType, $complaintTypeOptions)) {
                 throw new Exception("Invalid complaint type selected.");
            }
             if (!in_array($status, $statusOptions)) {
                 throw new Exception("Invalid status selected.");
            }

            if (!empty($_POST['complaint_id'])) {
                $stmt = $db->prepare("UPDATE complaints SET
                                resident_id = ?,
                                complaint_type = ?,
                                description = ?,
                                status = ?,
                                resolved_by = ?,
                                resolution_notes = ?
                                WHERE complaint_id = ?");

                $success = $stmt->execute([
                    $residentId,
                    $complaintType,
                    $description,
                    $status,
                    $resolvedBy,
                    $resolutionNotes,
                    $_POST['complaint_id']
                ]);

                $message = 'Complaint updated successfully';
                $messageType = 'success';
            } else {
                $stmt = $db->prepare("INSERT INTO complaints
                                (resident_id, complaint_type, description, status, resolved_by, resolution_notes)
                                VALUES (?, ?, ?, ?, ?, ?)");

                $success = $stmt->execute([
                    $residentId,
                    $complaintType,
                    $description,
                    $status,
                    $resolvedBy,
                    $resolutionNotes
                ]);

                $message = 'Complaint added successfully';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
        } catch (PDOException $e) {
             $message = 'Database error: ' . $e->getMessage();
             $messageType = 'danger';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id'];

        $stmt = $db->prepare("DELETE FROM complaints WHERE complaint_id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            $message = 'Complaint deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete complaint';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error deleting complaint: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editComplaint = [
    'complaint_id' => '',
    'resident_id' => '',
    'complaint_type' => '',
    'description' => '',
    'status' => 'Open',
    'resolved_by' => '',
    'resolution_notes' => ''
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM complaints WHERE complaint_id = ?");
        $stmt->execute([$id]);
        $fetchedComplaint = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetchedComplaint) {
            $editComplaint = $fetchedComplaint;
        } else {
            $message = 'Complaint not found';
            $messageType = 'warning';
            $showForm = false;
        }
    } catch (PDOException $e) {
        $message = 'Error loading complaint: ' . $e->getMessage();
        $messageType = 'danger';
        $showForm = false;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'add') {
    $showForm = true;
}

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10; // Items per page
    $offset = ($page - 1) * $limit;

    $countStmt = $db->query("SELECT COUNT(*) FROM complaints");
    $totalComplaints = $countStmt->fetchColumn();
    $totalPages = ceil($totalComplaints / $limit);

    $stmt = $db->prepare("
        SELECT c.*,
               (r.first_name || ' ' || r.last_name) as resident_name,
               (o.first_name || ' ' || o.last_name) as resolved_by_name -- Name of the official who resolved it
        FROM complaints c
        JOIN residents r ON c.resident_id = r.resident_id
        LEFT JOIN barangay_officials bo ON c.resolved_by = bo.official_id
        LEFT JOIN residents o ON bo.resident_id = o.resident_id -- Join officials back to residents for the name
        ORDER BY c.date_reported DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = 'Error loading complaints: ' . $e->getMessage();
    $messageType = 'danger';
    $complaints = [];
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

try {
    $officialStmt = $db->query("
        SELECT bo.official_id, (r.first_name || ' ' || r.last_name) as full_name
        FROM barangay_officials bo
        JOIN residents r ON bo.resident_id = r.resident_id
        WHERE bo.is_active = 1
        ORDER BY r.last_name, r.first_name
    ");
    $officials = $officialStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $officials = [];
}


$complaintTypeOptions = [];
try {
    $stmt = $db->query("SHOW COLUMNS FROM complaints LIKE 'complaint_type'");
    $type = $stmt->fetch(PDO::FETCH_ASSOC)['Type'];
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
    $complaintTypeOptions = explode("','", $matches[1]);
} catch (PDOException $e) {
    $complaintTypeOptions = ['Infrastructure', 'Noise', 'Dispute', 'Sanitation', 'Other'];
}

$statusOptions = [];
try {
    $stmt = $db->query("SHOW COLUMNS FROM complaints LIKE 'status'");
    $type = $stmt->fetch(PDO::FETCH_ASSOC)['Type'];
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
    $statusOptions = explode("','", $matches[1]);
} catch (PDOException $e) {
    $statusOptions = ['Open', 'In Progress', 'Resolved', 'Closed'];
}

?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complaint Management</title>
    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .status-open { color: #0dcaf0; } /* Cyan/Info */
        .status-in-progress { color: #ffc107; } /* Yellow/Warning */
        .status-resolved { color: #198754; } /* Green/Success */
        .status-closed { color: #6c757d; } /* Gray/Secondary */

         .complaint-form .form-group {
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <?php include_once TEMPLATE_PATH . '/navbar.html'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Complaint Management</h1>
                <p class="text-muted">Manage resident complaints</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Complaints List</h2>
                    <a href="?action=add" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Complaint
                    </a>
                </div>

                <?php if ($showForm): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><?= empty($editComplaint['complaint_id']) ? 'Add New Complaint' : 'Edit Complaint' ?></h3>
                        </div>
                        <div class="card-body complaint-form">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="complaint_id" value="<?= htmlspecialchars($editComplaint['complaint_id'] ?? '') ?>">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="resident_id" class="form-label">Complainant Resident *</label>
                                            <select id="resident_id" name="resident_id" required class="form-select form-select-lg">
                                                <option value="">-- Select Resident --</option>
                                                <?php foreach ($residents as $resident): ?>
                                                    <option value="<?= (int)$resident['resident_id'] ?>"
                                                            <?= ($editComplaint['resident_id'] ?? '') == $resident['resident_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($resident['full_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                         <div class="form-group">
                                            <label for="complaint_type" class="form-label">Complaint Type *</label>
                                            <select id="complaint_type" name="complaint_type" required class="form-select form-select-lg">
                                                <option value="">-- Select Type --</option>
                                                <?php foreach ($complaintTypeOptions as $option): ?>
                                                    <option value="<?= htmlspecialchars($option) ?>"
                                                            <?= ($editComplaint['complaint_type'] ?? '') === $option ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($option) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea id="description" name="description" rows="4" required
                                              class="form-control form-control-lg"><?= htmlspecialchars($editComplaint['description'] ?? '') ?></textarea>
                                </div>

                                <div class="row">
                                     <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status" class="form-label">Status *</label>
                                            <select id="status" name="status" required class="form-select form-select-lg">
                                                <?php foreach ($statusOptions as $option): ?>
                                                    <option value="<?= htmlspecialchars($option) ?>"
                                                            <?= ($editComplaint['status'] ?? 'Open') === $option ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($option) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                     <div class="col-md-6">
                                         <div class="form-group">
                                            <label for="resolved_by" class="form-label">Resolved By (Official)</label>
                                            <select id="resolved_by" name="resolved_by" class="form-select form-select-lg">
                                                <option value="">-- Select Official --</option>
                                                <?php foreach ($officials as $official): ?>
                                                    <option value="<?= (int)$official['official_id'] ?>"
                                                            <?= ($editComplaint['resolved_by'] ?? '') == $official['official_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($official['full_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>


                                <div class="form-group">
                                    <label for="resolution_notes" class="form-label">Resolution Notes</label>
                                    <textarea id="resolution_notes" name="resolution_notes" rows="3"
                                              class="form-control form-control-lg"><?= htmlspecialchars($editComplaint['resolution_notes'] ?? '') ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="index.php" class="btn btn-secondary btn-lg">Cancel</a>
                                    <button type="submit" class="btn btn-primary btn-lg">Save Complaint</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Complainant</th>
                                        <th>Type</th>
                                        <th>Date Reported</th>
                                        <th>Status</th>
                                        <th>Resolved By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($complaints) > 0): ?>
                                        <?php foreach ($complaints as $complaint):
                                            $dateReported = date('M j, Y h:i A', strtotime($complaint['date_reported']));
                                            // Generate status class (e.g., 'status-open', 'status-resolved')
                                            $statusClass = 'status-' . strtolower(str_replace(' ', '-', $complaint['status']));
                                        ?>
                                            <tr>
                                                <td><?= (int)$complaint['complaint_id'] ?></td>
                                                <td><?= htmlspecialchars($complaint['resident_name']) ?></td>
                                                <td><?= htmlspecialchars($complaint['complaint_type']) ?></td>
                                                <td><?= $dateReported ?></td>
                                                <td>
                                                    <span class="<?= $statusClass ?> fw-bold">
                                                        <?= htmlspecialchars($complaint['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($complaint['resolved_by_name'] ?? 'N/A') ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="?action=edit&id=<?= (int)$complaint['complaint_id'] ?>"
                                                           class="btn btn-info btn-sm">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </a>
                                                        <a href="?action=delete&id=<?= (int)$complaint['complaint_id'] ?>"
                                                           class="btn btn-danger btn-sm"
                                                           onclick="return confirm('Are you sure you want to delete this complaint record?');">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No complaints found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Complaints pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php
                                        $prevDisabled = ($page <= 1) ? 'disabled' : '';
                                        $prevLink = ($page > 1) ? "?page=" . ($page - 1) : "#";
                                        echo '<li class="page-item ' . $prevDisabled . '">';
                                        echo '<a class="page-link" href="' . $prevLink . '">&laquo; Previous</a>';
                                        echo '</li>';

                                        $startPage = max(1, min($page - 2, $totalPages - 4));
                                        $endPage = min($totalPages, max(5, $page + 2));

                                        for ($i = $startPage; $i <= $endPage; $i++) {
                                            $activeClass = ($i == $page) ? 'active' : '';
                                            echo '<li class="page-item ' . $activeClass . '">';
                                            echo '<a class="page-link" href="?page=' . $i . '">' . $i . '</a>';
                                            echo '</li>';
                                        }

                                        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
                                        $nextLink = ($page < $totalPages) ? "?page=" . ($page + 1) : "#";
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
                if (!alert.classList.contains('alert-warning')) {
                     setTimeout(function () {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 5000); // Dismiss after 5 seconds
                }
            });
        });
    </script>
</body>

</html>