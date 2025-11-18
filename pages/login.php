<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// If already logged in — hide the form
if (isset($_SESSION['user_id'])) {
    echo "<h2>You are already logged in 👍</h2>";
    echo '<p><a href="../index.php">Go to homepage</a></p>';
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        $message = "User not found 😕";
    } else {
        if (password_verify($password, $user['password_hash'])) {

            // Successful login
            $_SESSION['user_id'] = $user['id'];

            header("Location: ../index.php");
            exit;

        } else {
            $message = "Incorrect password 😐";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In</title>
</head>
<body>

<h2>Sign In</h2>

<?php if (!empty($message)): ?>
    <p><strong><?= htmlspecialchars($message) ?></strong></p>
<?php endif; ?>

<form method="post">
    <label>Email:<br>
        <input type="email" name="email" required>
    </label><br><br>

    <label>Password:<br>
        <input type="password" name="password" required>
    </label><br><br>

    <button type="submit">Sign In</button>
</form>

<p><a href="register.php">No account? Sign up</a></p>

</body>
</html>
