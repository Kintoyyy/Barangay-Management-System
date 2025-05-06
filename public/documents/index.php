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
            if (empty($_POST['document_type'])) {
                throw new Exception("Document type is required.");
            }

            $residentId = (int)$_POST['resident_id'];
            $documentType = trim($_POST['document_type']);
            $status = trim($_POST['status']);
            $remarks = !empty($_POST['remarks']) ? trim($_POST['remarks']) : null;
            $fee = !empty($_POST['fee']) ? (float)$_POST['fee'] : 0.00;
            $issuedBy = !empty($_POST['issued_by']) ? (int)$_POST['issued_by'] : null;

            if (!empty($_POST['document_id'])) {
                // Update existing document
                $stmt = $db->prepare("UPDATE documents SET 
                            resident_id = ?, 
                            document_type = ?, 
                            status = ?, 
                            remarks = ?,
                            fee = ?,
                            issued_by = ?
                            WHERE document_id = ?");
                
                $success = $stmt->execute([
                    $residentId,
                    $documentType,
                    $status,
                    $remarks,
                    $fee,
                    $issuedBy,
                    $_POST['document_id']
                ]);
                
                $message = 'Document updated successfully';
                $messageType = 'success';
            } else {
                // Create new document
                $stmt = $db->prepare("INSERT INTO documents 
                            (resident_id, document_type, status, remarks, fee, issued_by) 
                            VALUES (?, ?, ?, ?, ?, ?)");
                
                $success = $stmt->execute([
                    $residentId,
                    $documentType,
                    $status,
                    $remarks,
                    $fee,
                    $issuedBy
                ]);
                
                $message = 'Document added successfully';
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
        
        $stmt = $db->prepare("DELETE FROM documents WHERE document_id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            $message = 'Document deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete document';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editDocument = [
    'document_id' => '',
    'resident_id' => '',
    'document_type' => '',
    'status' => 'Pending',
    'remarks' => '',
    'fee' => '',
    'issued_by' => ''
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $stmt = $db->prepare("SELECT * FROM documents WHERE document_id = ?");
        $stmt->execute([$_GET['id']]);
        $fetchedDocument = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetchedDocument) {
            $editDocument = $fetchedDocument;
        } else {
            $message = 'Document not found';
            $messageType = 'warning';
            $showForm = false;
        }
    } catch (PDOException $e) {
        $message = 'Error loading document: ' . $e->getMessage();
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

    $countStmt = $db->query("SELECT COUNT(*) FROM documents");
    $totalDocuments = $countStmt->fetchColumn();
    $totalPages = ceil($totalDocuments / $limit);

    $stmt = $db->prepare("
        SELECT d.*, 
               (r.first_name || ' ' || r.last_name) as resident_name,
               (o.first_name || ' ' || o.last_name) as issued_by_name
        FROM documents d
        JOIN residents r ON d.resident_id = r.resident_id
        LEFT JOIN barangay_officials bo ON d.issued_by = bo.official_id
        LEFT JOIN residents o ON bo.resident_id = o.resident_id
        ORDER BY d.request_date DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading documents: ' . $e->getMessage();
    $messageType = 'danger';
    $documents = [];
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

// Get document type options from ENUM values
try {
    $stmt = $db->query("SHOW COLUMNS FROM documents LIKE 'document_type'");
    $type = $stmt->fetch(PDO::FETCH_ASSOC)['Type'];
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
    $documentTypeOptions = explode("','", $matches[1]);
} catch (PDOException $e) {
    $documentTypeOptions = ['Barangay Clearance', 'Indigency Certificate', 'Residency Certificate', 'Business Permit'];
}

// Get status options from ENUM values
try {
    $stmt = $db->query("SHOW COLUMNS FROM documents LIKE 'status'");
    $type = $stmt->fetch(PDO::FETCH_ASSOC)['Type'];
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
    $statusOptions = explode("','", $matches[1]);
} catch (PDOException $e) {
    $statusOptions = ['Pending', 'Approved', 'Rejected', 'Completed'];
}
?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Management</title>
    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
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
        .status-pending { color: #ffc107; }
        .status-approved { color: #198754; }
        .status-rejected { color: #dc3545; }
        .status-completed { color: #0d6efd; }
        .document-form .form-group {
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <?php include_once '../navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Document Management</h1>
                <p class="text-muted">Manage barangay documents and requests</p>
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
                    <h2>Documents List</h2>
                    <a href="index.php?action=add" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Document
                    </a>
                </div>

                <?php if ($showForm): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><?= empty($editDocument['document_id']) ? 'Add New Document' : 'Edit Document' ?></h3>
                        </div>
                        <div class="card-body document-form">
                            <form method="post" action="index.php">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="document_id" value="<?= htmlspecialchars($editDocument['document_id']) ?>">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="resident_id" class="form-label">Resident *</label>
                                            <select id="resident_id" name="resident_id" required class="form-select form-select-lg">
                                                <option value="">-- Select Resident --</option>
                                                <?php foreach ($residents as $resident): ?>
                                                    <option value="<?= (int)$resident['resident_id'] ?>" 
                                                        <?= $editDocument['resident_id'] == $resident['resident_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($resident['full_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="document_type" class="form-label">Document Type *</label>
                                            <select id="document_type" name="document_type" required class="form-select form-select-lg">
                                                <option value="">-- Select Document Type --</option>
                                                <?php foreach ($documentTypeOptions as $option): ?>
                                                    <option value="<?= htmlspecialchars($option) ?>" 
                                                        <?= $editDocument['document_type'] === $option ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($option) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status" class="form-label">Status *</label>
                                            <select id="status" name="status" required class="form-select form-select-lg">
                                                <?php foreach ($statusOptions as $option): ?>
                                                    <option value="<?= htmlspecialchars($option) ?>" 
                                                        <?= $editDocument['status'] === $option ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($option) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="fee" class="form-label">Fee</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" id="fee" name="fee" step="0.01" min="0"
                                                       value="<?= htmlspecialchars($editDocument['fee']) ?>"
                                                       class="form-control form-control-lg">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="issued_by" class="form-label">Issued By (Official)</label>
                                    <select id="issued_by" name="issued_by" class="form-select form-select-lg">
                                        <option value="">-- Select Official --</option>
                                        <?php foreach ($officials as $official): ?>
                                            <option value="<?= (int)$official['official_id'] ?>" 
                                                <?= $editDocument['issued_by'] == $official['official_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($official['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea id="remarks" name="remarks" rows="3" 
                                              class="form-control form-control-lg"><?= htmlspecialchars($editDocument['remarks']) ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="index.php" class="btn btn-secondary btn-lg">Cancel</a>
                                    <button type="submit" class="btn btn-primary btn-lg">Save Document</button>
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
                                        <th>Resident</th>
                                        <th>Document Type</th>
                                        <th>Request Date</th>
                                        <th>Status</th>
                                        <th>Fee</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($documents) > 0): ?>
                                        <?php foreach ($documents as $doc): 
                                            $requestDate = date('M j, Y h:i A', strtotime($doc['request_date']));
                                            $statusClass = 'status-' . strtolower($doc['status']);
                                        ?>
                                            <tr>
                                                <td><?= (int)$doc['document_id'] ?></td>
                                                <td><?= htmlspecialchars($doc['resident_name']) ?></td>
                                                <td><?= htmlspecialchars($doc['document_type']) ?></td>
                                                <td><?= $requestDate ?></td>
                                                <td>
                                                    <span class="<?= $statusClass ?> fw-bold">
                                                        <?= htmlspecialchars($doc['status']) ?>
                                                    </span>
                                                </td>
                                                <td>₱<?= number_format($doc['fee'], 2) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="index.php?action=edit&id=<?= (int)$doc['document_id'] ?>"
                                                           class="btn btn-info btn-sm">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="index.php?action=delete&id=<?= (int)$doc['document_id'] ?>"
                                                           class="btn btn-danger btn-sm"
                                                           onclick="return confirm('Are you sure you want to delete this document record?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No documents found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Documents pagination">
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

            // Update form fields based on document type selection
            const documentType = document.getElementById('document_type');
            const feeField = document.getElementById('fee');
            
            if (documentType && feeField) {
                documentType.addEventListener('change', function() {
                    // Set default fees based on document type
                    const type = this.value;
                    switch(type) {
                        case 'Barangay Clearance':
                            feeField.value = '50.00';
                            break;
                        case 'Indigency Certificate':
                            feeField.value = '0.00';
                            break;
                        case 'Residency Certificate':
                            feeField.value = '30.00';
                            break;
                        case 'Business Permit':
                            feeField.value = '100.00';
                            break;
                        default:
                            feeField.value = '0.00';
                    }
                });
            }
        });
    </script>
</body>

</html>