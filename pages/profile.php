<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Validate profile ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid profile ID.";
    exit;
}

$profile_id = (int)$_GET['id'];

// Fetch user email
$userStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
$userStmt->execute([':id' => $profile_id]);
$user = $userStmt->fetch();

if (!$user) {
    echo "User not found.";
    exit;
}

// Fetch profile info
$infoStmt = $pdo->prepare("
    SELECT nickname, bio, profession, avatar 
    FROM user_info 
    WHERE user_id = :id LIMIT 1
");
$infoStmt->execute([':id' => $profile_id]);
$info = $infoStmt->fetch();

// Avatar logic
$avatar = $info['avatar'] ?? "/assets/default-avatar.jpg";

// --------------------------------------------
// POST CREATION (only owner)
// --------------------------------------------
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
        $content = trim($_POST['content']);
        if ($content !== "" && mb_strlen($content) <= 140) {
            $insert = $pdo->prepare("
                INSERT INTO posts (user_id, content) 
                VALUES (:user_id, :content)
            ");
            $insert->execute([
                ':user_id' => $profile_id,
                ':content' => $content
            ]);
            header("Location: profile.php?id=" . $profile_id);
            exit;
        }
    }
}

// --------------------------------------------
// PAGINATION SYSTEM
// --------------------------------------------
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0
    ? (int)$_GET['page']
    : 1;

$limit = 10;
$offset = ($page - 1) * $limit;

// Count posts
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = :id");
$countStmt->execute([':id' => $profile_id]);
$total_posts = $countStmt->fetchColumn();
$total_pages = ceil($total_posts / $limit);

// Fetch posts
$postStmt = $pdo->prepare("
    SELECT content, created_at 
    FROM posts 
    WHERE user_id = :id 
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$postStmt->bindValue(':id', $profile_id, PDO::PARAM_INT);
$postStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$postStmt->execute();
$posts = $postStmt->fetchAll();

// --------------------------------------------
// Function to display human-readable time
// --------------------------------------------
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 0) $diff = 0;

    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes !== 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours !== 1 ? "s" : "") . " ago";
    } elseif ($diff < 172800) {
        return "Yesterday at " . date("H:i", $time);
    } elseif ($diff < 604800) {
        return date("l at H:i", $time); // weekday + time
    } else {
        return date("d.m.Y at H:i", $time); // date + time
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
    <link rel="stylesheet" href="../assets/style/css.css?v1">
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
    </div>
</div>

<div class="profile-container">

    <!-- Top banner -->
    <div class="profile-header">
        <div class="left">
            <img src="<?= htmlspecialchars($avatar) ?>" class="avatar" alt="Avatar">
            <div>
                <strong><?= htmlspecialchars($info['nickname'] ?? 'Not specified') ?></strong>
                <div class="status">Online</div>
            </div>
        </div>
        <div class="right">
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id): ?>
                <a href="edit.php">Edit Profile</a>
            <?php endif; ?>
            <a href="logout.php" class="logout">Log Out</a>
        </div>
    </div>

    <!-- Main content -->
    <div class="profile-main">

        <!-- Posts area -->
        <div class="posts">

            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id): ?>
            <form method="post" class="post-form">
                <textarea 
                    name="content" 
                    maxlength="140" 
                    placeholder="Write something (max 140 chars)..."
                ></textarea><br>
                <button type="submit">Publish</button>
            </form>
            <?php endif; ?>

            <!-- List of posts -->
            <?php if ($posts): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post">
                        <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                        <small><?= timeAgo($post['created_at']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-posts">No posts yet.</div>
            <?php endif; ?>

            <!-- Pagination (only if total posts > limit) -->
            <?php if ($total_posts > $limit): ?>
            <div class="pagination">
                <?php
                if ($page > 1) {
                    echo "<a href='profile.php?id={$profile_id}&page=" . ($page - 1) . "'>Previous</a> ";
                }
                if ($page < $total_pages) {
                    echo "<a href='profile.php?id={$profile_id}&page=" . ($page + 1) . "'>Next</a>";
                }
                ?>
            </div>
            <?php endif; ?>

            <div class="footer">
                <p>&copy; 2025 powered by <a href="">TeleNotes</a></p>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <h3>About User</h3>

            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Nickname:</div>
                <div class="info-value"><?= htmlspecialchars($info['nickname'] ?? 'Not specified') ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">About:</div>
                <div class="info-value"><?= nl2br(htmlspecialchars($info['bio'] ?? 'Not specified')) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Profession:</div>
                <div class="info-value"><?= htmlspecialchars($info['profession'] ?? 'Not specified') ?></div>
            </div>

        </div>

    </div>
</div>

</body>
</html>
