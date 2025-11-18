<?php
session_start();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Home Page</title>
</head>
<body>

<h1>Home Page</h1>

<?php if (isset($_SESSION['user_id'])): ?>
    <p>You are logged in 👍</p>
    <p><a href="pages/logout.php">Logout</a></p>
<?php else: ?>
    <p><a href="pages/login.php">Sign In</a></p>
    <p><a href="pages/register.php">Sign Up</a></p>
<?php endif; ?>

</body>
</html>
