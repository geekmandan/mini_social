<?php
session_start();
require_once __DIR__ . '/config/config.php';

$auth_user_id = $_SESSION['user_id'] ?? null;

// Function: Time ago formatter
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

// Handle new post submission
if ($auth_user_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if ($content !== "" && mb_strlen($content) <= 140) {
        $insert = $pdo->prepare("
            INSERT INTO posts (user_id, content, created_at) 
            VALUES (:user_id, :content, NOW())
        ");
        $insert->execute([
            ':user_id' => $auth_user_id,
            ':content' => $content
        ]);
        header("Location: index.php");
        exit;
    }
}

// Load 5 latest users with posts
$userStmt = $pdo->query("
    SELECT DISTINCT users.id, user_info.nickname
    FROM posts
    JOIN users ON posts.user_id = users.id
    LEFT JOIN user_info ON users.id = user_info.user_id
    ORDER BY posts.created_at DESC
    LIMIT 5
");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Load last post of each user
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
    <link rel="stylesheet" href="assets/style/css.css?v4">
</head>
<body>

<?php if ($auth_user_id): ?>
<div class="header">
    <div class="navbar">
        <div class="menu">
            <a href="../index.php">Home</a>
            <a href="">Discovery</a>
            <a href="">Live</a>
            <a href="">New</a>
        </div>
        <div class="btns-head">
            <a href="messages.php" class="message">Messages</a>
            <a href="settings.php" class="settings">Settings</a>
            <a href="pages/logout.php" class="logout">Log Out</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="profile-container">
    <div class="profile-main">

        <!-- Posts column -->
        <div class="posts">
            <div class="title-journal"><h2>Journal of Users</h2></div>

            <!-- Form to add a post (only for authorized users) -->
            <?php if ($auth_user_id): ?>
            <form method="post" class="post-form" style="margin-bottom: 10px;">
                <textarea 
                    name="content" 
                    maxlength="140" 
                    placeholder="Write something (max 140 chars)..." 
                    required
                    style="width:100%; padding: 10px; margin-bottom: 5px;"
                ></textarea>
                <button type="submit" style="padding: 6px 12px;">Publish</button>
            </form>
            <?php endif; ?>

            <?php if ($posts): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post">
                        <p style="margin-bottom: 5px;">
                            <strong>
                                <a href="pages/profile.php?id=<?= $post['id'] ?>">
                                    <?= htmlspecialchars($post['nickname'] ?: 'User #' . $post['id']) ?>
                                </a>
                            </strong>
                        </p>

                        <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

                        <small style="display: block; margin-top: 6px; color: #555;">
                            <?= timeAgo($post['created_at']) ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-posts">No posts yet.</div>
            <?php endif; ?>

            <?php if (!$auth_user_id): ?>
            <!-- Guest-usefulness block -->
            <div class="guest-benefits" style="margin-top: 10px; margin-bottom: 10px; font-size: 13px;">
                <h3 style="margin-bottom: 10px;">Why TeleNotes Helps You</h3>
                <ul style="margin-left: 20px; line-height: 1.6;">
                    <li>Write and save personal thoughts and ideas.</li>
                    <li>Explore what others share in real time.</li>
                    <li>Stay motivated by watching daily updates from users.</li>
                    <li>Use a simple, old-school interface without distractions.</li>
                    <li>Join the community and build your own journal.</li>
                </ul>
                <p style="margin-top: 15px;">
                    <a href="pages/register.php">Create your account</a> to start posting.
                </p>
            </div>
            <?php endif; ?>

            <div class="footer">
                <p>&copy; 2025 powered by <a href="">TeleNotes</a></p>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="block-sidebar">
                <h3>User Info</h3>
                <?php if ($auth_user_id): ?>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT user_info.nickname 
                        FROM users 
                        LEFT JOIN user_info ON users.id = user_info.user_id 
                        WHERE users.id = :id
                    ");
                    $stmt->execute([':id' => $auth_user_id]);
                    $authUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    $avatar = 'assets/default-avatar.jpg';
                    $nickname = $authUser['nickname'] ?: 'User #' . $auth_user_id;
                    ?>
                    <div class="link-view" style="margin-top: 10px;">
                        <a href="pages/profile.php?id=<?= $auth_user_id ?>" style="display: block; margin-bottom: 6px;">
                            View Profile
                        </a>
                        <a href="pages/logout.php" style="display: block; margin-top: 6px;">
                            Log Out
                        </a>
                    </div>

                <?php else: ?>
                    <p style="margin-top: 10px;">No user logged in</p>
                    <div style="margin-top: 10px;">
                        <a href="pages/login.php" style="display: block; margin-bottom: 8px;">
                            Sign In
                        </a>
                        <a href="pages/register.php" style="display: block; margin-top: 4px;">
                            Sign Up
                        </a>
                    </div>
                <?php endif; ?>
            </div>
                
        </div>

    </div>
</div>

</body>
</html>
