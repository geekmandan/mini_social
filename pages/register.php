<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    echo '<div class="logged-in-box">';
    echo "<h2>You are already logged in 👍</h2>";
    echo '<a href="../index.php">Go to homepage</a>';
    echo '</div>';
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');

    if ($email === "" || $password === "" || $nickname === "") {
        $message = "Please fill all fields 😕";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $message = "Email is already registered 😐";
        } else {
            // Insert new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)");
            $insert->execute([
                ':email' => $email,
                ':password_hash' => $password_hash
            ]);
            $user_id = $pdo->lastInsertId();

            // Insert default info
            $infoInsert = $pdo->prepare("INSERT INTO user_info (user_id, nickname) VALUES (:user_id, :nickname)");
            $infoInsert->execute([
                ':user_id' => $user_id,
                ':nickname' => $nickname
            ]);

            // Log in the user
            $_SESSION['user_id'] = $user_id;
            header("Location: ../index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
    <link rel="stylesheet" href="../assets/style/css.css?v2">
</head>
<body>

<div class="header">
    <div class="navbar">
        <div class="menu">
            <a href="../index.php">Home</a>
        </div>
    </div>
</div>

<div class="register-wrapper">
    <h2>Create an Account</h2>

    <?php if (!empty($message)): ?>
        <p style="color: red; font-size: 12px; margin-bottom: 10px;">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <form method="post" class="post-form">
        <label>Email:
            <input type="email" name="email" required>
        </label>

        <label>Nickname:
            <input type="text" name="nickname" required>
        </label>

        <label>Password:
            <input type="password" name="password" required>
        </label>

        <button type="submit">Sign Up</button>
    </form>

    <div class="small-link">
        Already have an account? <a href="login.php">Sign in</a>
    </div>
</div>

</body>
</html>
