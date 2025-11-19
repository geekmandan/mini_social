<?php
session_start();
require_once __DIR__ . '/config/config.php';

$auth_user_id = $_SESSION['user_id'] ?? null;

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 0) $diff = 0;

    if ($diff < 60) return "Just now";
    elseif ($diff < 3600) return floor($diff/60) . " minute" . (floor($diff/60) != 1 ? "s" : "") . " ago";
    elseif ($diff < 86400) return floor($diff/3600) . " hour" . (floor($diff/3600) != 1 ? "s" : "") . " ago";
    elseif ($diff < 172800) return "Yesterday at " . date("H:i", $time);
    else return date("d.m.Y \a\\t H:i", $time);
}

$userStmt = $pdo->query("
    SELECT DISTINCT users.id, user_info.nickname
    FROM posts
    JOIN users ON posts.user_id = users.id
    LEFT JOIN user_info ON users.id = user_info.user_id
    ORDER BY posts.created_at DESC
    LIMIT 5
");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$posts = [];
$postStmt = $pdo->prepare("
    SELECT content, created_at 
    FROM posts 
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 1
");

foreach ($users as $user) {
    $postStmt->execute([':user_id' => $user['id']]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);
    if ($post) {
        $posts[] = array_merge($user, $post);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home Page</title>
    <link rel="stylesheet" href="assets/style/css.css?v1">
</head>
<body>

<div class="profile-container">

    <div class="profile-main">

        <!-- Posts column -->
        <div class="posts" style="margin-top: 10px;">
            <div class="title-journal"><h2>Journal of Users</h2></div>

            <?php if ($posts): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post">
                        <!-- Имя пользователя -->
                        <p style="margin-bottom: 5px;">
                            <strong>
                                <a href="pages/profile.php?id=<?= $post['id'] ?>">
                                    <?= htmlspecialchars($post['nickname'] ?: 'User #' . $post['id']) ?>
                                </a>
                            </strong>
                        </p>

                        <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

                        <small style="display: block; margin-top: 6px; color: #555;"><?= timeAgo($post['created_at']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-posts">No posts yet.</div>
            <?php endif; ?>

            <div class="footer">
                <p>&copy; 2025 powered by <a href="">TeleNotes</a></p>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <h3>User Info</h3>
            <?php if ($auth_user_id): ?>
                <?php

                $stmt = $pdo->prepare("SELECT user_info.nickname FROM users LEFT JOIN user_info ON users.id = user_info.user_id WHERE users.id = :id");
                $stmt->execute([':id' => $auth_user_id]);
                $authUser = $stmt->fetch(PDO::FETCH_ASSOC);

                $avatar = 'assets/default-avatar.jpg'; // дефолтный аватар
                $nickname = $authUser['nickname'] ?: 'User #' . $auth_user_id;
                ?>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                    <img src="<?= $avatar ?>" alt="Avatar" style="width: 60px; height: 60px; border:1px solid #ccc;">
                    <div>
                        <strong><?= htmlspecialchars($nickname) ?></strong><br>
                        <div class="link-view" style="margin-top: 5px;">
                            <a href="pages/profile.php?id=<?= $auth_user_id ?>">View Profile</a><br>
                            <a href="pages/logout.php" style="margin-top: 3px; display: inline-block;">Log Out</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p>No user logged in</p>
                <a href="pages/login.php">Sign In</a> | <a href="pages/register.php">Sign Up</a>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>
