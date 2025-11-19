<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// If the user is already logged in — redirect them to the homepage
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Checking if the email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email 🙂";
    }
    // Checking password strength
    elseif (strlen($password) < 8) {
        $message = "Password is too short! Minimum 8 characters 💪";
    }
    else {
        // Checking if this email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $message = "This username is already taken 😐 Try another one.";
        } else {
            // Hashing the password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Adding the user
            $insert = $pdo->prepare(
                "INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)"
            );
            $insert->execute([
                ':email' => $email,
                ':password_hash' => $passwordHash
            ]);

            // After successful registration → redirect to login page
            header("Location: login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
</head>
<body>

<h2>Sign Up</h2>

<?php if (!empty($message)): ?>
    <p><strong><?= htmlspecialchars($message) ?></strong></p>
<?php endif; ?>

<form method="post">
    <label>Email:<br>
        <input type="email" name="email" required>
    </label><br><br>

    <label>password:<br>
        <input type="password" name="password" required>
    </label><br><br>

    <button type="submit">Sign Up</button>
</form>

<p><a href="login.php">Already have an account? Sign in</a></p>

</body>
</html>
