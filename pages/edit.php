<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get existing user info (if any)
$stmt = $pdo->prepare("SELECT * FROM user_info WHERE user_id = :user_id LIMIT 1");
$stmt->execute([':user_id' => $user_id]);
$userInfo = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Trim and set "Not specified" for empty fields
    $nickname   = trim($_POST['nickname'] ?? '');
    $bio        = trim($_POST['bio'] ?? '');
    $profession = trim($_POST['profession'] ?? '');

    $nickname   = $nickname !== '' ? $nickname : 'Not specified';
    $bio        = $bio !== '' ? $bio : 'Not specified';
    $profession = $profession !== '' ? $profession : 'Not specified';

    // If record exists → update
    if ($userInfo) {
        $update = $pdo->prepare("
            UPDATE user_info 
            SET nickname = :nickname, bio = :bio, profession = :profession
            WHERE user_id = :user_id
        ");
        $update->execute([
            ':nickname' => $nickname,
            ':bio' => $bio,
            ':profession' => $profession,
            ':user_id' => $user_id
        ]);
    }
    // Else → create new record
    else {
        $insert = $pdo->prepare("
            INSERT INTO user_info (user_id, nickname, bio, profession)
            VALUES (:user_id, :nickname, :bio, :profession)
        ");
        $insert->execute([
            ':user_id' => $user_id,
            ':nickname' => $nickname,
            ':bio' => $bio,
            ':profession' => $profession
        ]);
    }

    // Redirect to profile page
    header("Location: profile.php?id=" . $user_id);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
</head>
<body>

<h2>Edit Profile</h2>

<form method="post">

    <label>Nickname:<br>
        <input type="text" name="nickname" value="<?= htmlspecialchars($userInfo['nickname'] ?? '') ?>">
    </label><br><br>

    <label>About You:<br>
        <textarea name="bio" rows="4" cols="40"><?= htmlspecialchars($userInfo['bio'] ?? '') ?></textarea>
    </label><br><br>

    <label>Profession:<br>
        <input type="text" name="profession" value="<?= htmlspecialchars($userInfo['profession'] ?? '') ?>">
    </label><br><br>

    <button type="submit">Save</button>
</form>

<p><a href="logout.php">Log Out</a></p>

</body>
</html>
