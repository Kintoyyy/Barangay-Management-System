<?php

require __DIR__ . '/../../config/bootstrap.php';

?>

<!doctype html>
<html lang="en"
    data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1">

    <title>Residence</title>

    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
</head>

<body>
    <?php include_once TEMPLATE_PATH . '/navbar.html'; ?>

    <div id="resident-details"
        class="mt-4 p-3 border rounded">
        <p class="text-muted">Select a resident to view details</p>
    </div>

    <div id="residents-table">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Birth</th>
                    <th>Gender</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody hx-get="/api/residents"
                hx-trigger="load">
                <tr>
                    <td colspan="5"
                        class="text-center">Loading data...</td>
                </tr>
            </tbody>
        </table>
    </div>

</body>

<?php include_once INCLUDES_PATH . '/scripts.php'; ?>

</html>