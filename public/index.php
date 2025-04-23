<?php

require __DIR__ . '/../config/bootstrap.php';

?>

<!doctype html>
<html lang="en"
    data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1">

    <title>Home</title>

    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
</head>

<body>
    <?php include_once TEMPLATE_PATH . '/navbar.html'; ?>
</body>

<?php include_once INCLUDES_PATH . '/scripts.php'; ?>

</html>