<?php
session_start();
require_once __DIR__ . '/config/config.php';

// PAGINATION
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0
    ? (int)$_GET['page']
    : 1;
$offset = ($page - 1) * $limit;

// COUNT total posts
$countStmt = $pdo->query("SELECT COUNT(*) FROM posts");
$total_posts = $countStmt->fetchColumn();
$total_pages = ceil($total_posts / $limit);

// FETCH posts with user info
$postStmt = $pdo->prepare("
    SELECT posts.content, posts.created_at, users.id AS user_id, user_info.nickname
    FROM posts
    JOIN users ON posts.user_id = users.id
    LEFT JOIN user_info ON users.id = user_info.user_id
    ORDER BY posts.created_at DESC
    LIMIT :limit OFFSET :offset
");

$postStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$postStmt->execute();
$posts = $postStmt->fetchAll();

$auth_user_id = $_SESSION['user_id'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home Page</title>
</head>
<body>

<h1>Home Page</h1>

<?php if ($auth_user_id): ?>
    <p>You are logged in 👍</p>
    <p><a href="pages/logout.php">Log Out</a></p>
    <p><a href="pages/profile.php?id=<?= $auth_user_id ?>">My Profile</a></p>
<?php else: ?>
    <p><a href="pages/login.php">Sign In</a></p>
    <p><a href="pages/register.php">Sign Up</a></p>
<?php endif; ?>

<hr>

<h2>Notes Users</h2>

<?php if ($posts): ?>
    <?php foreach ($posts as $post): ?>
        <div style="margin-bottom: 15px; border: 1px solid #ccc; padding: 10px;">
            <p>
                <strong>
                    <a href="pages/profile.php?id=<?= $post['user_id'] ?>">
                        <?= htmlspecialchars($post['nickname'] ?: 'User #' . $post['user_id']) ?>
                    </a>
                </strong>
            </p>
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            <small><?= $post['created_at'] ?></small>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>No posts yet.</p>
<?php endif; ?>

<!-- PAGINATION -->
<div style="margin-top:20px;">
<?php
if ($page > 1) {
    echo "<a href='index.php?page=" . ($page - 1) . "'>Previous</a> ";
}
if ($page < $total_pages) {
    echo "<a href='index.php?page=" . ($page + 1) . "'>Next</a>";
}
?>
</div>

</body>
</html>
