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

// Avatar logic
$avatar = $userInfo['avatar'] ?? "/assets/default-avatar.jpg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="/assets/style/css.css">
</head>
<body>

<div class="header">
    <div class="navbar">
        <div class="menu">
            <a href="../index.php">Home</a>
            <a href="">Discovery</a>
            <a href="">Live</a>
            <a href="">New</a>
        </div>
        <div class="btns-head">
            <a href="messages.php" class="messages">Messages</a>
            <a href="settings.php" class="settings">Settings</a>
            <a href="logout.php" class="logout">Log Out</a>
        </div>
    </div>
</div>

<div class="profile-container">

    <!-- Top banner -->
    <div class="profile-header">
        <div class="left">
            <img src="<?= htmlspecialchars($avatar) ?>" class="avatar" alt="Avatar">
            <div class="user-info">
                <strong><?= htmlspecialchars($userInfo['nickname'] ?? 'Not specified') ?></strong>
                <div class="status">Online</div>
            </div>
        </div>
        <div class="right">
            <button form="edit-form" type="submit">Save</button>
            <a href="logout.php" class="logout">Log Out</a>
        </div>
    </div>

    <div class="profile-main">

        <!-- Edit form container -->
        <div class="edit-container">
            <h3>Edit Profile</h3>
            <form method="post" id="edit-form" class="edit-form">
                <label class="label">Nickname:</label>
                <input type="text" name="nickname" value="<?= htmlspecialchars($userInfo['nickname'] ?? '') ?>">

                <label class="label">About You:</label>
                <textarea name="bio"><?= htmlspecialchars($userInfo['bio'] ?? '') ?></textarea>

                <label class="label">Profession:</label>
                <input type="text" name="profession" value="<?= htmlspecialchars($userInfo['profession'] ?? '') ?>">
            </form>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="block-sidebar"> 
                <a href="profile.php?id=<?= $user_id ?>" class="back-button">Back</a>
            </div>
        </div>

    </div>

</div>

</body>
</html>
