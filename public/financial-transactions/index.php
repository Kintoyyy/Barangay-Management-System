<?php
require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        try {
            if (!isset($_POST['amount']) || $_POST['amount'] === '' || empty($_POST['transaction_type'])) {
                 throw new Exception("Amount and Transaction Type are required.");
            }

            $residentId = !empty($_POST['resident_id']) ? (int)$_POST['resident_id'] : null;
            $transactionType = trim($_POST['transaction_type']);
            $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
            if ($amount === false) {
                throw new Exception("Invalid amount entered.");
            }
            $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
            $receivedBy = !empty($_POST['received_by']) ? (int)$_POST['received_by'] : null;
            $transactionId = !empty($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;

            if ($residentId !== null) {
                $residentCheck = $db->prepare("SELECT COUNT(*) FROM residents WHERE resident_id = ?");
                $residentCheck->execute([$residentId]);
                if ($residentCheck->fetchColumn() === 0) {
                     throw new Exception("Invalid resident selected.");
                }
            }

            if ($receivedBy !== null) {
                $officialCheck = $db->prepare("SELECT COUNT(*) FROM barangay_officials WHERE official_id = ?");
                $officialCheck->execute([$receivedBy]);
                if ($officialCheck->fetchColumn() === 0) {
                     throw new Exception("Invalid official selected as receiver.");
                }
            }

            if ($transactionId > 0) {
                // Update
                $stmt = $db->prepare("UPDATE financial_transactions SET
                                        resident_id = ?,
                                        transaction_type = ?,
                                        amount = ?,
                                        description = ?,
                                        received_by = ?
                                        WHERE transaction_id = ?");

                $success = $stmt->execute([
                    $residentId,
                    $transactionType,
                    $amount,
                    $description,
                    $receivedBy,
                    $transactionId
                ]);

                $message = 'Financial transaction updated successfully';
                $messageType = 'success';

            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO financial_transactions
                                        (resident_id, transaction_type, amount, description, received_by)
                                        VALUES (?, ?, ?, ?, ?)");

                $success = $stmt->execute([
                    $residentId,
                    $transactionType,
                    $amount,
                    $description,
                    $receivedBy
                ]);

                $message = 'Financial transaction added successfully';
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

        $stmt = $db->prepare("DELETE FROM financial_transactions WHERE transaction_id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            $message = 'Financial transaction deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete financial transaction';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$editTransaction = [
    'transaction_id' => '',
    'resident_id' => '',
    'transaction_type' => '',
    'amount' => '',
    'description' => '',
    'date_recorded' => '',
    'received_by' => ''
];

$showForm = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $showForm = true;
    try {
        $stmt = $db->prepare("SELECT * FROM financial_transactions WHERE transaction_id = ?");
        $stmt->execute([$_GET['id']]);
        $fetchedTransaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetchedTransaction) {
            $editTransaction = $fetchedTransaction;
             // Format amount for display in input[type=number]
            $editTransaction['amount'] = number_format((float)$editTransaction['amount'], 2, '.', '');
        } else {
            $message = 'Financial transaction not found';
            $messageType = 'warning';
            $showForm = false;
        }
    } catch (PDOException $e) {
        $message = 'Error loading financial transaction: ' . $e->getMessage();
        $messageType = 'danger';
        $showForm = false;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'add') {
    $showForm = true;
}

// Fetch data for dropdowns
try {
    $residentStmt = $db->query("
        SELECT resident_id, (first_name || ' ' || last_name) as full_name
        FROM residents
        WHERE is_active = 1
        ORDER BY last_name, first_name
    ");
    $residents = $residentStmt->fetchAll(PDO::FETCH_ASSOC);

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

// Fetch data for list view
$transactions = [];
$totalPages = 0;
$page = 1;

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $countStmt = $db->query("SELECT COUNT(*) FROM financial_transactions");
    $totalTransactions = $countStmt->fetchColumn();
    $totalPages = ceil($totalTransactions / $limit);

    // Fetch transactions with resident and official names
    $stmt = $db->prepare("
        SELECT
            ft.*,
            (r.first_name || ' ' || r.last_name) as resident_name,
            (rec_by_r.first_name || ' ' || rec_by_r.last_name) as received_by_name
        FROM financial_transactions ft
        LEFT JOIN residents r ON ft.resident_id = r.resident_id
        LEFT JOIN barangay_officials bo ON ft.received_by = bo.official_id
        LEFT JOIN residents rec_by_r ON bo.resident_id = rec_by_r.resident_id
        ORDER BY ft.date_recorded DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = 'Error loading financial transactions: ' . $e->getMessage();
    $messageType = 'danger';
    $transactions = [];
    $totalPages = 0;
}
?>

<!doctype html>
<html lang="en" data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financial Transactions Management</title>
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
                <h1>Financial Transactions Management</h1>
                <p class="text-muted">Manage fees, budgets, and expenses</p>
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
                <div id="transaction-details" class="p-3 border rounded bg-light">
                    <?php if ($showForm): ?>
                        <div class="card">
                            <div class="card-header">
                                <?= empty($editTransaction['transaction_id']) ? 'Add New Transaction' : 'Edit Transaction' ?>
                            </div>
                            <div class="card-body">
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($editTransaction['transaction_id']) ?>">

                                    <div class="mb-3">
                                        <label for="resident_id" class="form-label">Resident (Optional)</label>
                                        <select id="resident_id" name="resident_id" class="form-select">
                                            <option value="">-- Select Resident (Optional) --</option>
                                            <?php foreach ($residents as $resident): ?>
                                                <option value="<?= (int)$resident['resident_id'] ?>"
                                                        <?= $editTransaction['resident_id'] == $resident['resident_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($resident['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Link this transaction to a specific resident if applicable.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="transaction_type" class="form-label">Transaction Type *</label>
                                        <select id="transaction_type" name="transaction_type" class="form-select" required>
                                            <option value="">-- Select Type --</option>
                                            <?php
                                            $transactionTypes = ['Fee Payment', 'Budget Allocation', 'Expense'];
                                            foreach ($transactionTypes as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>"
                                                        <?= $editTransaction['transaction_type'] == $type ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($type) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                     <div class="mb-3">
                                        <label for="amount" class="form-label">Amount *</label>
                                        <input type="number" id="amount" name="amount" step="0.01" required class="form-control"
                                               value="<?= htmlspecialchars($editTransaction['amount']) ?>"
                                               placeholder="e.g., 100.00">
                                    </div>

                                     <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea id="description" name="description" class="form-control" rows="3"
                                                  placeholder="Enter details..."><?= htmlspecialchars($editTransaction['description'] ?? '') ?></textarea>
                                        <div class="form-text">Purpose, item bought, etc.</div>
                                    </div>

                                     <div class="mb-3">
                                        <label for="received_by" class="form-label">Recorded By / Received By (Official)</label>
                                        <select id="received_by" name="received_by" class="form-select">
                                            <option value="">-- Select Official (Optional) --</option>
                                            <?php foreach ($officials as $official): ?>
                                                <option value="<?= (int)$official['official_id'] ?>"
                                                        <?= $editTransaction['received_by'] == $official['official_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($official['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                         <div class="form-text">Official who handled this transaction.</div>
                                    </div>

                                    <?php if (!empty($editTransaction['date_recorded'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Date Recorded</label>
                                        <p class="form-control-plaintext">
                                            <?= date('F j, Y g:i A', strtotime($editTransaction['date_recorded'])) ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save Transaction</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-cash-coin" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">Select a transaction to edit or add a new one</p>
                             <a href="index.php?action=add" class="btn btn-success mt-3">
                                <i class="bi bi-plus-circle"></i> Add New Transaction
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Financial Transactions List</h2>
                    <?php if (!$showForm): ?>
                        <a href="index.php?action=add" class="btn btn-success d-md-none">
                            <i class="bi bi-plus-circle"></i> Add Transaction
                        </a>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div id="transactions-table" class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Resident</th>
                                        <th>Description</th>
                                        <th>Recorded By</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($transactions) > 0): ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?= (int)$transaction['transaction_id'] ?></td>
                                                <td><?= htmlspecialchars($transaction['transaction_type']) ?></td>
                                                <td>₱<?= number_format((float)$transaction['amount'], 2) ?></td>
                                                <td>
                                                    <?= !empty($transaction['resident_name']) ? htmlspecialchars($transaction['resident_name']) : '-' ?>
                                                </td>
                                                 <td>
                                                     <span title="<?= htmlspecialchars($transaction['description'] ?? 'No description') ?>">
                                                        <?= !empty($transaction['description']) ? htmlspecialchars(substr($transaction['description'], 0, 50)) . (strlen($transaction['description']) > 50 ? '...' : '') : '-' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= !empty($transaction['received_by_name']) ? htmlspecialchars($transaction['received_by_name']) : '-' ?>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($transaction['date_recorded'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="index.php?action=edit&id=<?= (int)$transaction['transaction_id'] ?>"
                                                            class="btn btn-info btn-sm" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="index.php?action=delete&id=<?= (int)$transaction['transaction_id'] ?>"
                                                            class="btn btn-danger btn-sm" title="Delete"
                                                            onclick="return confirm('Are you sure you want to delete this financial transaction?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                         <?php if (!empty($transaction['resident_id'])): ?>
                                                             <a href="../residents/view.php?id=<?= (int)$transaction['resident_id'] ?>"
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
                                            <td colspan="8" class="text-center">No financial transactions found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Transaction pagination">
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