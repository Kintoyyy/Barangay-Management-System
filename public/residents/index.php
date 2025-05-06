<?php
require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

$message     = '';
$messageType = '';

try {
    $purokStmt = $db->query("SELECT purok_id, purok_number, purok_name FROM purok ORDER BY purok_number");
    $puroks = $purokStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading puroks: ' . $e->getMessage();
    $messageType = 'danger';
    $puroks = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['birth_date'])) {
            throw new Exception("First name, last name, and birth date are required.");
        }
        
        if (empty($_POST['purok_id'])) {
            throw new Exception("Purok selection is required.");
        }

        $firstName     = trim($_POST['first_name']);
        $lastName      = trim($_POST['last_name']);
        $middleName    = !empty($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
        $birthDate     = $_POST['birth_date'];
        $gender        = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $civilStatus   = !empty($_POST['civil_status']) ? $_POST['civil_status'] : null;
        $email         = !empty($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : null;
        $contactNumber = !empty($_POST['contact_number']) ? $_POST['contact_number'] : null;
        $purokId       = (int)$_POST['purok_id'];
        $householdId   = !empty($_POST['household_id']) ? (int)$_POST['household_id'] : null;
        $isVoter       = isset($_POST['is_voter']) ? 1 : 0;
        $isActive      = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($_POST['resident_id'])) {
            $stmt = $db->prepare("UPDATE residents SET 
                          first_name=?, 
                          last_name=?,
                          middle_name=?,
                          birth_date=?, 
                          gender=?,
                          civil_status=?,
                          email=?, 
                          contact_number=?,
                          purok_id=?,
                          household_id=?,
                          is_voter=?,
                          is_active=?
                          WHERE resident_id=?");
            $success = $stmt->execute([
                $firstName,
                $lastName,
                $middleName,
                $birthDate,
                $gender,
                $civilStatus,
                $email,
                $contactNumber,
                $purokId,
                $householdId,
                $isVoter,
                $isActive,
                $_POST['resident_id']
            ]);
            $message = 'Resident updated successfully';
            $messageType = 'success';
        } else {
            $stmt = $db->prepare("INSERT INTO residents 
                          (first_name, last_name, middle_name, birth_date, gender, civil_status, 
                           email, contact_number, purok_id, household_id, is_voter, is_active) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([
                $firstName,
                $lastName,
                $middleName,
                $birthDate,
                $gender,
                $civilStatus,
                $email,
                $contactNumber,
                $purokId,
                $householdId,
                $isVoter,
                $isActive
            ]);
            $message = 'Resident added successfully';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id'];
        
        $leaderCheck = $db->prepare("SELECT COUNT(*) FROM purok WHERE leader_id = ?");
        $leaderCheck->execute([$id]);
        
        if ($leaderCheck->fetchColumn() > 0) {
            $message = 'Cannot delete: Resident is assigned as a purok leader. Please remove as leader first.';
            $messageType = 'warning';
        } else {
            $stmt = $db->prepare("DELETE FROM residents WHERE resident_id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                $message = 'Resident deleted successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete resident';
                $messageType = 'danger';
            }
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editResident = [
    'resident_id'    => '',
    'first_name'     => '',
    'last_name'      => '',
    'middle_name'    => '',
    'birth_date'     => '',
    'gender'         => '',
    'civil_status'   => '',
    'email'          => '',
    'contact_number' => '',
    'purok_id'       => '',
    'household_id'   => '',
    'is_voter'       => 0,
    'is_active'      => 1
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $stmt = $db->prepare("SELECT * FROM residents WHERE resident_id = ?");
        $stmt->execute([$_GET['id']]);
        $fetchedResident = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fetchedResident) {
            $editResident = $fetchedResident;
        }
    } catch (PDOException $e) {
        $message = 'Error loading resident: ' . $e->getMessage();
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

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $purokFilter = isset($_GET['purok']) ? (int)$_GET['purok'] : 0;
    $householdFilter = isset($_GET['household']) ? (int)$_GET['household'] : 0;
    $idFilter = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $whereClause = [];
    $params = [];
    
    if (!empty($search)) {
        $whereClause[] = "(r.first_name LIKE ? OR r.last_name LIKE ? OR r.middle_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($purokFilter > 0) {
        $whereClause[] = "r.purok_id = ?";
        $params[] = $purokFilter;
    }

    if ( $householdFilter > 0 ) {
        $whereClause[] = "r.household_id = ?";
        $params[]      = $householdFilter;
    }

    if ( $idFilter > 0 ) {
        $whereClause[] = "r.resident_id = ?";
        $params[]      = $idFilter;
    }
    
    $whereSQL = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";
    
    $countSQL = "SELECT COUNT(*) FROM residents r $whereSQL";
    $countStmt = $db->prepare($countSQL);
    $countStmt->execute($params);
    $totalResidents = $countStmt->fetchColumn();
    $totalPages = ceil($totalResidents / $limit);

    $sql = "SELECT r.*, p.purok_number, p.purok_name 
            FROM residents r 
            LEFT JOIN purok p ON r.purok_id = p.purok_id 
            $whereSQL 
            ORDER BY r.last_name, r.first_name 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex, $param);
        $paramIndex++;
    }
    
    $stmt->execute();
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading residents: ' . $e->getMessage();
    $messageType = 'danger';
    $residents = [];
    $totalPages = 0;
}

try {
    $householdStmt = $db->query("SELECT household_id, household_name FROM households ORDER BY household_name");
    $households = $householdStmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($households);
} catch (PDOException $e) {
    $households = [];
}
?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resident Management</title>
    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .form-check-lg {
            min-height: 1.8rem;
            margin-bottom: 0.125rem;
        }
        .form-check-lg .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.135em;
        }
        .form-check-lg .form-check-label {
            padding-left: 0.25rem;
        }
    </style>
</head>

<body>
    <?php include_once '../navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Resident Management</h1>
                <p class="text-muted">Manage your residential database</p>
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
                <div id="resident-details" class="p-3 border rounded bg-light">
                    <?php if ($showForm): ?>
                        <div class="card">
                            <div class="card-header">
                                <?= empty($editResident['resident_id']) ? 'Add New Resident' : 'Edit Resident' ?>
                            </div>
                            <div class="card-body">
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="resident_id" value="<?= htmlspecialchars($editResident['resident_id']) ?>">

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input id="first_name" name="first_name" 
                                                   value="<?= htmlspecialchars($editResident['first_name']) ?>"
                                                   placeholder="First Name" required class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input id="last_name" name="last_name" 
                                                   value="<?= htmlspecialchars($editResident['last_name']) ?>"
                                                   placeholder="Last Name" required class="form-control">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="middle_name" class="form-label">Middle Name</label>
                                        <input id="middle_name" name="middle_name" 
                                               value="<?= htmlspecialchars($editResident['middle_name'] ?? '') ?>"
                                               placeholder="Middle Name" class="form-control">
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="birth_date" class="form-label">Birth Date *</label>
                                            <input type="date" id="birth_date" name="birth_date" required
                                                   value="<?= htmlspecialchars($editResident['birth_date']) ?>"
                                                   class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select id="gender" name="gender" class="form-select">
                                                <option value="" <?= $editResident['gender'] == '' ? 'selected' : '' ?>>
                                                    Select Gender
                                                </option>
                                                <option value="Male" <?= $editResident['gender'] == 'Male' ? 'selected' : '' ?>>
                                                    Male
                                                </option>
                                                <option value="Female" <?= $editResident['gender'] == 'Female' ? 'selected' : '' ?>>
                                                    Female
                                                </option>
                                                <option value="Other" <?= $editResident['gender'] == 'Other' ? 'selected' : '' ?>>
                                                    Other
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="civil_status" class="form-label">Civil Status</label>
                                        <select id="civil_status" name="civil_status" class="form-select">
                                            <option value="" <?= $editResident['civil_status'] == '' ? 'selected' : '' ?>>
                                                Select Civil Status
                                            </option>
                                            <option value="Single" <?= $editResident['civil_status'] == 'Single' ? 'selected' : '' ?>>
                                                Single
                                            </option>
                                            <option value="Married" <?= $editResident['civil_status'] == 'Married' ? 'selected' : '' ?>>
                                                Married
                                            </option>
                                            <option value="Widowed" <?= $editResident['civil_status'] == 'Widowed' ? 'selected' : '' ?>>
                                                Widowed
                                            </option>
                                            <option value="Divorced" <?= $editResident['civil_status'] == 'Divorced' ? 'selected' : '' ?>>
                                                Divorced
                                            </option>
                                        </select>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" id="email" name="email" 
                                                   value="<?= htmlspecialchars($editResident['email'] ?? '') ?>"
                                                   placeholder="Email" class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="contact_number" class="form-label">Contact Number</label>
                                            <input id="contact_number" name="contact_number" 
                                                   value="<?= htmlspecialchars($editResident['contact_number'] ?? '') ?>"
                                                   placeholder="Contact Number" class="form-control">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="purok_id" class="form-label">Purok *</label>
                                            <select id="purok_id" name="purok_id" required class="form-select">
                                                <option value="">Select Purok</option>
                                                <?php foreach ($puroks as $purok): ?>
                                                    <option value="<?= (int)$purok['purok_id'] ?>" 
                                                        <?= $editResident['purok_id'] == $purok['purok_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($purok['purok_number']) ?>
                                                        <?= !empty($purok['purok_name']) ? ' - ' . htmlspecialchars($purok['purok_name']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="household_id" class="form-label">Household</label>
                                            <select id="household_id" name="household_id" class="form-select">
                                                <option value="">Select Household</option>
                                                <?php foreach ($households as $household): ?>
                                                    <option value="<?= (int)$household['household_id'] ?>" 
                                                        <?= $editResident['household_id'] == $household['household_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($household['household_name'] ?? 'Household #' . $household['household_id']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-check form-check-lg">
                                                <input type="checkbox" class="form-check-input" id="is_voter" name="is_voter" 
                                                       <?= $editResident['is_voter'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="is_voter">Is Voter</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-check-lg">
                                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                                       <?= !isset($editResident['is_active']) || $editResident['is_active'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="is_active">Active Resident</label>
                                            </div>
                                        </div>
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
                            <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">Select a resident to view details or add a new one</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Residents List</h2>
                    <a href="index.php?action=add" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Resident
                    </a>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form method="get" action="index.php" class="row g-3">
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by name..." value="<?= htmlspecialchars($search ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="purok" class="form-select">
                                    <option value="">All Puroks</option>
                                    <?php foreach ($puroks as $purok): ?>
                                        <option value="<?= (int)$purok['purok_id'] ?>" 
                                                <?= $purokFilter == $purok['purok_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($purok['purok_number']) ?>
                                            <?= !empty($purok['purok_name']) ? ' - ' . htmlspecialchars($purok['purok_name']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div id="residents-table" class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Birth Date</th>
                                        <th>Purok</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($residents) > 0): ?>
                                        <?php foreach ($residents as $resident): ?>
                                            <tr>
                                                <td><?= (int)$resident['resident_id'] ?></td>
                                                <td>
                                                    <?= htmlspecialchars($resident['last_name'] . ', ' . $resident['first_name']) ?>
                                                    <?= !empty($resident['middle_name']) ? ' ' . htmlspecialchars(substr($resident['middle_name'], 0, 1) . '.') : '' ?>
                                                    <?php if ($resident['is_voter']): ?>
                                                        <span class="badge bg-primary ms-1">Voter</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= !empty($resident['birth_date']) ? htmlspecialchars($resident['birth_date']) : '-' ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($resident['purok_number']) ?>
                                                </td>
                                                <td>
                                                    <?= !empty($resident['contact_number']) ? htmlspecialchars($resident['contact_number']) : '-' ?>
                                                </td>
                                                <td>
                                                    <?php if ($resident['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="index.php?action=edit&id=<?= (int)$resident['resident_id'] ?>"
                                                           class="btn btn-info btn-sm">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="index.php?action=delete&id=<?= (int)$resident['resident_id'] ?>"
                                                           class="btn btn-danger btn-sm"
                                                           onclick="return confirm('Are you sure you want to delete this resident?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No residents found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Resident pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php
                                        // Create pagination URLs that preserve search params
                                        $paginationParams = [];
                                        if (!empty($search)) $paginationParams[] = "search=" . urlencode($search);
                                        if ($purokFilter > 0) $paginationParams[] = "purok=" . $purokFilter;
                                        $paginationBaseUrl = "index.php?" . implode("&", $paginationParams);
                                        
                                        $prevDisabled = ($page <= 1) ? 'disabled' : '';
                                        $prevLink = ($page > 1) ? $paginationBaseUrl . "&page=" . ($page - 1) : "#";
                                        echo '<li class="page-item ' . $prevDisabled . '">';
                                        echo '<a class="page-link" href="' . $prevLink . '">&laquo; Previous</a>';
                                        echo '</li>';

                                        $startPage = max(1, min($page - 2, $totalPages - 4));
                                        $endPage = min($totalPages, max(5, $page + 2));

                                        for ($i = $startPage; $i <= $endPage; $i++) {
                                            $activeClass = ($i == $page) ? 'active' : '';
                                            echo '<li class="page-item ' . $activeClass . '">';
                                            echo '<a class="page-link" href="' . $paginationBaseUrl . '&page=' . $i . '">' . $i . '</a>';
                                            echo '</li>';
                                        }

                                        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
                                        $nextLink = ($page < $totalPages) ? $paginationBaseUrl . "&page=" . ($page + 1) : "#";
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