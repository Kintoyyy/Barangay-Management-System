<?php
require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        try {
            if (empty($_POST['address']) || empty($_POST['purok_id'])) {
                throw new Exception("Address and Purok are required fields.");
            }

            $address = trim($_POST['address']);
            $purokId = (int)$_POST['purok_id'];
            $householdHeadId = !empty($_POST['household_head_id']) ? (int)$_POST['household_head_id'] : null;

            if (!empty($_POST['household_id'])) {
                $stmt = $db->prepare("UPDATE households SET 
                            address = ?, 
                            purok_id = ?, 
                            household_head_id = ? 
                            WHERE household_id = ?");
                
                $success = $stmt->execute([
                    $address,
                    $purokId,
                    $householdHeadId,
                    $_POST['household_id']
                ]);
                
                $message = 'Household updated successfully';
                $messageType = 'success';
            } 
            else {
                $stmt = $db->prepare("INSERT INTO households 
                            (address, purok_id, household_head_id) 
                            VALUES (?, ?, ?)");
                
                $success = $stmt->execute([
                    $address,
                    $purokId,
                    $householdHeadId
                ]);
                
                $message = 'Household added successfully';
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
        
        $residentCheck = $db->prepare("SELECT COUNT(*) FROM residents WHERE household_id = ?");
        $residentCheck->execute([$id]);
        
        if ($residentCheck->fetchColumn() > 0) {
            $message = 'Cannot delete: This household has residents. Please reassign residents first.';
            $messageType = 'warning';
        } else {
            $stmt = $db->prepare("DELETE FROM households WHERE household_id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                $message = 'Household deleted successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete household';
                $messageType = 'danger';
            }
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editHousehold = [
    'household_id' => '',
    'address' => '',
    'purok_id' => '',
    'household_head_id' => '',
    'date_created' => ''
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $stmt = $db->prepare("SELECT * FROM households WHERE household_id = ?");
        $stmt->execute([$_GET['id']]);
        $fetchedHousehold = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetchedHousehold) {
            $editHousehold = $fetchedHousehold;
        } else {
            $message = 'Household not found';
            $messageType = 'warning';
            $showForm = false;
        }
    } catch (PDOException $e) {
        $message = 'Error loading household: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'add') {
    $showForm = true;
    
    if (isset($_GET['purok_id'])) {
        $editHousehold['purok_id'] = (int)$_GET['purok_id'];
    }
}

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $purokFilter = isset($_GET['purok']) ? (int)$_GET['purok'] : null;
    
    $whereClause = '';
    $params = [];
    
    if ($purokFilter) {
        $whereClause = ' WHERE h.purok_id = ?';
        $params[] = $purokFilter;
    }

    $countSql = "SELECT COUNT(*) FROM households h" . $whereClause;
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalHouseholds = $countStmt->fetchColumn();
    $totalPages = ceil($totalHouseholds / $limit);

    $sql = "
        SELECT h.*, p.purok_number, p.purok_name,
        (r.first_name || ' ' || r.last_name) as head_name,
        (SELECT COUNT(*) FROM residents WHERE household_id = h.household_id) as resident_count
        FROM households h
        LEFT JOIN purok p ON h.purok_id = p.purok_id
        LEFT JOIN residents r ON h.household_head_id = r.resident_id
        $whereClause
        ORDER BY h.household_id DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    if ($purokFilter) {
        $stmt->bindValue(1, $purokFilter, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $households = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading households: ' . $e->getMessage();
    $messageType = 'danger';
    $households = [];
    $totalPages = 0;
}

try {
    $purokStmt = $db->query("SELECT purok_id, purok_number, purok_name FROM purok ORDER BY purok_number");
    $puroks = $purokStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $puroks = [];
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

$selectedPurokName = '';
if ($purokFilter) {
    try {
        $purokNameStmt = $db->prepare("SELECT purok_number, purok_name FROM purok WHERE purok_id = ?");
        $purokNameStmt->execute([$purokFilter]);
        $purokData = $purokNameStmt->fetch(PDO::FETCH_ASSOC);
        if ($purokData) {
            $selectedPurokName = $purokData['purok_number'];
            if (!empty($purokData['purok_name'])) {
                $selectedPurokName .= ' - ' . $purokData['purok_name'];
            }
        }
    } catch (PDOException $e) {

    }
}
?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Household Management</title>
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1>Household Management</h1>
                        <?php if ($selectedPurokName): ?>
                            <p class="text-muted">Showing households in Purok <?= htmlspecialchars($selectedPurokName) ?></p>
                        <?php else: ?>
                            <p class="text-muted">Manage household records and residents</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($purokFilter): ?>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Clear Filter
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
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
                <div id="household-details" class="p-3 border rounded bg-light">
                    <?php if ($showForm): ?>
                        <div class="card">
                            <div class="card-header">
                                <?= empty($editHousehold['household_id']) ? 'Add New Household' : 'Edit Household' ?>
                            </div>
                            <div class="card-body">
                                <form method="post" action="index.php<?= $purokFilter ? "?purok={$purokFilter}" : '' ?>">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="household_id" value="<?= htmlspecialchars($editHousehold['household_id']) ?>">

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address *</label>
                                        <textarea id="address" name="address" required
                                            class="form-control" rows="3"><?= htmlspecialchars($editHousehold['address']) ?></textarea>
                                        <div class="form-text">Complete address of the household</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="purok_id" class="form-label">Purok *</label>
                                        <select id="purok_id" name="purok_id" class="form-select" required>
                                            <option value="">-- Select Purok --</option>
                                            <?php foreach ($puroks as $purok): ?>
                                                <option value="<?= (int)$purok['purok_id'] ?>" 
                                                    <?= $editHousehold['purok_id'] == $purok['purok_id'] || ($purokFilter && $purokFilter == $purok['purok_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($purok['purok_number']) ?>
                                                    <?= !empty($purok['purok_name']) ? ' - ' . htmlspecialchars($purok['purok_name']) : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="household_head_id" class="form-label">Household Head</label>
                                        <select id="household_head_id" name="household_head_id" class="form-select">
                                            <option value="">-- Select Household Head (Optional) --</option>
                                            <?php foreach ($residents as $resident): ?>
                                                <option value="<?= (int)$resident['resident_id'] ?>" 
                                                    <?= $editHousehold['household_head_id'] == $resident['resident_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($resident['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">The primary person responsible for the household</div>
                                    </div>

                                    <?php if (!empty($editHousehold['date_created'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Date Created</label>
                                        <p class="form-control-plaintext">
                                            <?= date('F j, Y g:i A', strtotime($editHousehold['date_created'])) ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="index.php<?= $purokFilter ? "?purok={$purokFilter}" : '' ?>" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-house-fill" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">Select a household to edit or add a new one</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Households List</h2>
                    <a href="index.php?action=add<?= $purokFilter ? "&purok_id={$purokFilter}" : '' ?>" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Household
                    </a>
                </div>

                <div class="mb-3">
                    <label for="purokFilter" class="form-label">Filter by Purok:</label>
                    <div class="d-flex">
                        <select id="purokFilter" class="form-select me-2">
                            <option value="">All Puroks</option>
                            <?php foreach ($puroks as $purok): ?>
                                <option value="<?= (int)$purok['purok_id'] ?>" 
                                    <?= $purokFilter == $purok['purok_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($purok['purok_number']) ?>
                                    <?= !empty($purok['purok_name']) ? ' - ' . htmlspecialchars($purok['purok_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="applyFilter" class="btn btn-primary">Apply</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div id="households-table" class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Address</th>
                                        <th>Purok</th>
                                        <th>Household Head</th>
                                        <th>Residents</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($households) > 0): ?>
                                        <?php foreach ($households as $household): ?>
                                            <tr>
                                                <td><?= (int)$household['household_id'] ?></td>
                                                <td><?= htmlspecialchars($household['address']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($household['purok_number']) ?>
                                                    <?= !empty($household['purok_name']) ? ' - ' . htmlspecialchars($household['purok_name']) : '' ?>
                                                </td>
                                                <td><?= !empty($household['head_name']) ? htmlspecialchars($household['head_name']) : '-' ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= (int)$household['resident_count'] ?></span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($household['date_created'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="index.php?action=edit&id=<?= (int)$household['household_id'] ?><?= $purokFilter ? "&purok={$purokFilter}" : '' ?>"
                                                           class="btn btn-info btn-sm">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </a>
                                                        <a href="index.php?action=delete&id=<?= (int)$household['household_id'] ?><?= $purokFilter ? "&purok={$purokFilter}" : '' ?>"
                                                           class="btn btn-danger btn-sm"
                                                           onclick="return confirm('Are you sure you want to delete this household?');">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </a>
                                                        <a href="../residents/index.php?household=<?= (int)$household['household_id'] ?>"
                                                           class="btn btn-secondary btn-sm">
                                                            <i class="bi bi-people"></i> View Residents
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No households found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Household pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php
                                        $purokParam = $purokFilter ? "&purok={$purokFilter}" : '';
                                        $prevDisabled = ($page <= 1) ? 'disabled' : '';
                                        $prevLink = ($page > 1) ? "index.php?page=" . ($page - 1) . $purokParam : "#";
                                        echo '<li class="page-item ' . $prevDisabled . '">';
                                        echo '<a class="page-link" href="' . $prevLink . '">&laquo; Previous</a>';
                                        echo '</li>';

                                        $startPage = max(1, min($page - 2, $totalPages - 4));
                                        $endPage = min($totalPages, max(5, $page + 2));

                                        for ($i = $startPage; $i <= $endPage; $i++) {
                                            $activeClass = ($i == $page) ? 'active' : '';
                                            echo '<li class="page-item ' . $activeClass . '">';
                                            echo '<a class="page-link" href="index.php?page=' . $i . $purokParam . '">' . $i . '</a>';
                                            echo '</li>';
                                        }

                                        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
                                        $nextLink = ($page < $totalPages) ? "index.php?page=" . ($page + 1) . $purokParam : "#";
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
            
            document.getElementById('applyFilter').addEventListener('click', function() {
                const purokId = document.getElementById('purokFilter').value;
                if (purokId) {
                    window.location.href = 'index.php?purok=' + purokId;
                } else {
                    window.location.href = 'index.php';
                }
            });
        });
    </script>
</body>

</html>