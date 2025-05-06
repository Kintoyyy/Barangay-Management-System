<?php
require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        try {
            if (empty($_POST['resident_id']) || empty($_POST['record_type'])) {
                throw new Exception("Resident and Record Type are required.");
            }

            $residentId = (int)$_POST['resident_id'];
            $recordType = trim($_POST['record_type']);
            $details = !empty($_POST['details']) ? trim($_POST['details']) : null;
            $recordedBy = !empty($_POST['recorded_by']) ? (int)$_POST['recorded_by'] : null;
            $recordId = !empty($_POST['record_id']) ? (int)$_POST['record_id'] : 0;

            $residentCheck = $db->prepare("SELECT COUNT(*) FROM residents WHERE resident_id = ?");
            $residentCheck->execute([$residentId]);
            if ($residentCheck->fetchColumn() === 0) {
                 throw new Exception("Invalid resident selected.");
            }

            if ($recordedBy !== null) {
                $officialCheck = $db->prepare("SELECT COUNT(*) FROM barangay_officials WHERE official_id = ?");
                $officialCheck->execute([$recordedBy]);
                if ($officialCheck->fetchColumn() === 0) {
                     throw new Exception("Invalid official selected as recorder.");
                }
            }

            if ($recordId > 0) {
                $stmt = $db->prepare("UPDATE health_records SET
                                        resident_id = ?,
                                        record_type = ?,
                                        details = ?,
                                        recorded_by = ?
                                        WHERE record_id = ?");

                $success = $stmt->execute([
                    $residentId,
                    $recordType,
                    $details,
                    $recordedBy,
                    $recordId
                ]);

                $message = 'Health record updated successfully';
                $messageType = 'success';

            } else {
                $stmt = $db->prepare("INSERT INTO health_records
                                        (resident_id, record_type, details, recorded_by)
                                        VALUES (?, ?, ?, ?)");

                $success = $stmt->execute([
                    $residentId,
                    $recordType,
                    $details,
                    $recordedBy
                ]);

                $message = 'Health record added successfully';
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

        $stmt = $db->prepare("DELETE FROM health_records WHERE record_id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            $message = 'Health record deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete health record';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editRecord = [
    'record_id' => '',
    'resident_id' => '',
    'record_type' => '',
    'details' => '',
    'date_recorded' => '',
    'recorded_by' => ''
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $stmt = $db->prepare("SELECT * FROM health_records WHERE record_id = ?");
        $stmt->execute([$_GET['id']]);
        $fetchedRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetchedRecord) {
            $editRecord = $fetchedRecord;
        } else {
            $message = 'Health record not found';
            $messageType = 'warning';
            $showForm = false;
        }
    } catch (PDOException $e) {
        $message = 'Error loading health record: ' . $e->getMessage();
        $messageType = 'danger';
        $showForm = false;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'add') {
    $showForm = true;
}

// Fetch data for dropdowns (Residents and Officials)
try {
    $residentStmt = $db->query("
        SELECT resident_id, (first_name || ' ' || last_name) as full_name
        FROM residents
        WHERE is_active = 1
        ORDER BY last_name, first_name
    ");
    $residents = $residentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch active officials for recorded_by dropdown, joining to residents for their names
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
    error_log("Error fetching residents or officials for dropdowns: " . $e->getMessage());
    $residents = [];
    $officials = [];
}

// Fetch data for the list view (Paginated)
$records = [];
$totalPages = 0;
$page = 1;

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $countStmt = $db->query("SELECT COUNT(*) FROM health_records");
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Fetch records with resident and official names using multiple LEFT JOINs
    $stmt = $db->prepare("
        SELECT
            hr.*,
            (r.first_name || ' ' || r.last_name) as resident_name,
            (bo_r.first_name || ' ' || bo_r.last_name) as official_name
        FROM health_records hr
        LEFT JOIN residents r ON hr.resident_id = r.resident_id
        LEFT JOIN barangay_officials bo ON hr.recorded_by = bo.official_id
        LEFT JOIN residents bo_r ON bo.resident_id = bo_r.resident_id
        ORDER BY hr.date_recorded DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = 'Error loading health records: ' . $e->getMessage();
    $messageType = 'danger';
    $records = [];
    $totalPages = 0;
}
?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Health Records Management</title>
    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
    <?php include_once TEMPLATE_PATH . '/navbar.html'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Health Records Management</h1>
                <p class="text-muted">Manage resident health and special condition records</p>
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
                <div id="record-details" class="p-3 border rounded bg-light">
                    <?php if ($showForm): ?>
                        <div class="card">
                            <div class="card-header">
                                <?= empty($editRecord['record_id']) ? 'Add New Health Record' : 'Edit Health Record' ?>
                            </div>
                            <div class="card-body">
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="record_id" value="<?= htmlspecialchars($editRecord['record_id']) ?>">

                                    <div class="mb-3">
                                        <label for="resident_id" class="form-label">Resident *</label>
                                        <select id="resident_id" name="resident_id" class="form-select" required>
                                            <option value="">-- Select Resident --</option>
                                            <?php foreach ($residents as $resident): ?>
                                                <option value="<?= (int)$resident['resident_id'] ?>"
                                                        <?= $editRecord['resident_id'] == $resident['resident_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($resident['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="record_type" class="form-label">Record Type *</label>
                                        <select id="record_type" name="record_type" class="form-select" required>
                                            <option value="">-- Select Type --</option>
                                            <?php
                                            $recordTypes = ['Vaccination', 'PWD', 'Senior Citizen', 'Prenatal', 'Other'];
                                            foreach ($recordTypes as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>"
                                                        <?= $editRecord['record_type'] == $type ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($type) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                     <div class="mb-3">
                                        <label for="details" class="form-label">Details</label>
                                        <textarea id="details" name="details" class="form-control" rows="3"
                                                  placeholder="Enter record details..."><?= htmlspecialchars($editRecord['details'] ?? '') ?></textarea>
                                        <div class="form-text">Any relevant medical details, dates, etc.</div>
                                    </div>

                                     <div class="mb-3">
                                        <label for="recorded_by" class="form-label">Recorded By (Official)</label>
                                        <select id="recorded_by" name="recorded_by" class="form-select">
                                            <option value="">-- Select Official (Optional) --</option>
                                            <?php foreach ($officials as $official): ?>
                                                <option value="<?= (int)$official['official_id'] ?>"
                                                        <?= $editRecord['recorded_by'] == $official['official_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($official['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if (!empty($editRecord['date_recorded'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Date Recorded</label>
                                        <p class="form-control-plaintext">
                                            <?= date('F j, Y g:i A', strtotime($editRecord['date_recorded'])) ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save Record</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-medical" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">Select a record to edit or add a new one</p>
                             <a href="index.php?action=add" class="btn btn-success mt-3">
                                <i class="bi bi-plus-circle"></i> Add New Record
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Health Records List</h2>
                    <?php if (!$showForm): ?>
                        <a href="index.php?action=add" class="btn btn-success d-md-none">
                            <i class="bi bi-plus-circle"></i> Add Record
                        </a>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div id="records-table" class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Resident</th>
                                        <th>Type</th>
                                        <th>Details</th>
                                        <th>Recorded By</th>
                                        <th>Date Recorded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($records) > 0): ?>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td><?= (int)$record['record_id'] ?></td>
                                                <td>
                                                    <?= !empty($record['resident_name']) ? htmlspecialchars($record['resident_name']) : 'Unknown Resident' ?>
                                                </td>
                                                <td><?= htmlspecialchars($record['record_type']) ?></td>
                                                <td>
                                                     <span title="<?= htmlspecialchars($record['details'] ?? 'No details') ?>">
                                                        <?= !empty($record['details']) ? htmlspecialchars(substr($record['details'], 0, 50)) . (strlen($record['details']) > 50 ? '...' : '') : '-' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= !empty($record['official_name']) ? htmlspecialchars($record['official_name']) : '-' ?>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($record['date_recorded'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="index.php?action=edit&id=<?= (int)$record['record_id'] ?>"
                                                            class="btn btn-info btn-sm" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="index.php?action=delete&id=<?= (int)$record['record_id'] ?>"
                                                            class="btn btn-danger btn-sm" title="Delete"
                                                            onclick="return confirm('Are you sure you want to delete this health record?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <?php if (!empty($record['resident_id'])): ?>
                                                             <a href="../residents/view.php?id=<?= (int)$record['resident_id'] ?>"
                                                                class="btn btn-secondary btn-sm" title="View Resident" target="_blank">
                                                                <i class="bi bi-person-vcard"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No health records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Record pagination">
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