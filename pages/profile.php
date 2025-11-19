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
$avatar = $info['avatar'] ?? null;
if (!$avatar) {
    $avatar = "/assets/default-avatar.jpg";
}

// --------------------------------------------
// POST CREATION (only owner)
// --------------------------------------------
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = trim($_POST['content'] ?? '');
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
</head>
<body>

<h2>User Profile</h2>

<!-- Link to main feed -->
<p><a href="../index.php">Home Page</a></p>

<img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" width="120" height="120"><br><br>

<p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>

<p><strong>Nickname:</strong> <?= htmlspecialchars($info['nickname'] ?? 'Not specified') ?></p>
<p><strong>About:</strong><br>
<?= nl2br(htmlspecialchars($info['bio'] ?? 'Not specified')) ?></p>
<p><strong>Profession:</strong> <?= htmlspecialchars($info['profession'] ?? 'Not specified') ?></p>

<!-- Edit link only for owner -->
<?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id): ?>
    <p><a href="edit.php">Edit Profile</a></p>
<?php endif; ?>

<a href="logout.php">Log Out</a>

<hr>

<h3>Posts</h3>

<?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id): ?>
<form method="post">
    <textarea 
        name="content" 
        maxlength="140" 
        rows="3" 
        cols="40"
        placeholder="Write something (max 140 chars)..."
    ></textarea><br><br>
    <button type="submit">Publish</button>
</form>
<br>
<?php endif; ?>

<!-- LIST POSTS -->
<?php if ($posts): ?>
    <?php foreach ($posts as $post): ?>
        <div style="margin-bottom: 15px;">
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
    echo "<a href='profile.php?id={$profile_id}&page=" . ($page - 1) . "'>Previous</a> ";
}
if ($page < $total_pages) {
    echo "<a href='profile.php?id={$profile_id}&page=" . ($page + 1) . "'>Next</a>";
}
?>
</div>

</body>
</html>
