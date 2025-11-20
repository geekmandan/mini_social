<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$profile_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$auth_user_id = $_SESSION['user_id'] ?? null;

if (!$profile_id) {
    echo "Invalid profile ID.";
    exit;
}

// Fetch user info
$userStmt = $pdo->prepare("SELECT email FROM users WHERE id=:id LIMIT 1");
$userStmt->execute([':id' => $profile_id]);
$user = $userStmt->fetch();
if (!$user) {
    echo "User not found.";
    exit;
}

$infoStmt = $pdo->prepare("SELECT nickname, bio, profession, avatar FROM user_info WHERE user_id=:id LIMIT 1");
$infoStmt->execute([':id' => $profile_id]);
$info = $infoStmt->fetch();
$avatar = $info['avatar'] ?? "/assets/default-avatar.jpg";

// Determine current tab
$tab = $_GET['tab'] ?? 'posts';
$allowed_tabs = ['posts', 'friends_posts'];
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'posts';
}

// -------------------
// Add Friend
if ($auth_user_id && $auth_user_id != $profile_id && isset($_GET['add_friend'])) {
    $check = $pdo->prepare("SELECT * FROM friends WHERE user_id=:user AND friend_id=:friend");
    $check->execute([':user' => $auth_user_id, ':friend' => $profile_id]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (:user, :friend, 'pending')")
            ->execute([':user' => $auth_user_id, ':friend' => $profile_id]);
    }
    header("Location: profile.php?id={$profile_id}&tab={$tab}");
    exit;
}

// Accept Friend
if ($auth_user_id && isset($_GET['accept_friend'])) {
    $friend_id = (int)$_GET['accept_friend'];
    $stmt = $pdo->prepare("UPDATE friends SET status='accepted' WHERE user_id=:user AND friend_id=:auth_user AND status='pending'");
    $stmt->execute([':user' => $friend_id, ':auth_user' => $auth_user_id]);
    header("Location: profile.php?id={$profile_id}&tab={$tab}");
    exit;
}

// Reject Friend
if ($auth_user_id && isset($_GET['reject_friend'])) {
    $friend_id = (int)$_GET['reject_friend'];
    $stmt = $pdo->prepare("DELETE FROM friends WHERE user_id=:user AND friend_id=:auth_user AND status='pending'");
    $stmt->execute([':user' => $friend_id, ':auth_user' => $auth_user_id]);
    header("Location: profile.php?id={$profile_id}&tab={$tab}");
    exit;
}

// Pin/Unpin post (only owner)
if (isset($_GET['pin']) && $auth_user_id == $profile_id) {
    $post_id = (int)$_GET['pin'];
    $stmt = $pdo->prepare("SELECT pinned FROM posts WHERE id=:id AND user_id=:user_id");
    $stmt->execute([':id' => $post_id, ':user_id' => $profile_id]);
    $post = $stmt->fetch();
    if ($post) {
        if ($post['pinned'] == 0) {
            // Unpin all previous
            $pdo->prepare("UPDATE posts SET pinned=0 WHERE user_id=:user_id")->execute([':user_id' => $profile_id]);
            // Pin this one
            $pdo->prepare("UPDATE posts SET pinned=1 WHERE id=:id")->execute([':id' => $post_id]);
        } else {
            $pdo->prepare("UPDATE posts SET pinned=0 WHERE id=:id")->execute([':id' => $post_id]);
        }
    }
    header("Location: profile.php?id={$profile_id}&tab={$tab}");
    exit;
}

// Create post (only owner)
if ($auth_user_id == $profile_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if ($content !== "" && mb_strlen($content) <= 140) {
        $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (:user_id, :content)")
            ->execute([':user_id' => $profile_id, ':content' => $content]);
        header("Location: profile.php?id={$profile_id}&tab={$tab}");
        exit;
    }
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// -------------------
// Load posts depending on tab
$posts = [];
$total_posts = 0;
$total_pages = 0;

if ($tab === 'posts') {
    // Только посты владельца профиля
    $postStmt = $pdo->prepare("
        SELECT p.*, u.id AS author_id, ui.nickname 
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN user_info ui ON u.id = ui.user_id
        WHERE p.user_id = :id 
        ORDER BY p.pinned DESC, p.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $postStmt->bindValue(':id', $profile_id, PDO::PARAM_INT);
    $postStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $postStmt->execute();
    $posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = :id");
    $countStmt->execute([':id' => $profile_id]);
    $total_posts = $countStmt->fetchColumn();
} 
elseif ($tab === 'friends_posts') {
    // Основной подзапрос: ID друзей профиля
    $friendsSubquery = "
        SELECT CASE 
            WHEN f.user_id = :profile_id THEN f.friend_id 
            WHEN f.friend_id = :profile_id THEN f.user_id 
        END AS friend_id
        FROM friends f
        WHERE (f.user_id = :profile_id OR f.friend_id = :profile_id)
          AND f.status = 'accepted'
    ";

    if ($auth_user_id == $profile_id) {
        // === СМОТРИТ СВОЙ ПРОФИЛЬ ===
        // Показываем ТОЛЬКО посты друзей, свои НЕ показываем
        $postStmt = $pdo->prepare("
            SELECT p.*, u.id AS author_id, ui.nickname
            FROM posts p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN user_info ui ON u.id = ui.user_id
            WHERE p.user_id IN ($friendsSubquery)
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM posts p
            WHERE p.user_id IN ($friendsSubquery)
        ");
    } else {
        // === СМОТРИТ ЧУЖОЙ ПРОФИЛЬ ===
        // Показываем посты владельца + посты его друзей
        $postStmt = $pdo->prepare("
            SELECT p.*, u.id AS author_id, ui.nickname
            FROM posts p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN user_info ui ON u.id = ui.user_id
            WHERE p.user_id = :profile_id
               OR p.user_id IN ($friendsSubquery)
            ORDER BY p.pinned DESC, p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM posts p
            WHERE p.user_id = :profile_id
               OR p.user_id IN ($friendsSubquery)
        ");
    }

    // Привязываем параметры
    $postStmt->bindValue(':profile_id', $profile_id, PDO::PARAM_INT);
    $postStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($auth_user_id != $profile_id) {
        // Для чужого профиля pinned имеет смысл только у владельца
        // (но сортировка по pinned всё равно работает)
    }
    $postStmt->execute();
    $posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt->bindValue(':profile_id', $profile_id, PDO::PARAM_INT);
    $countStmt->execute();
    $total_posts = $countStmt->fetchColumn();
}

$total_pages = ceil($total_posts / $limit);

// Friends list
$friendsStmt = $pdo->prepare("
    SELECT u.id, ui.nickname 
    FROM friends f
    JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
    LEFT JOIN user_info ui ON u.id = ui.user_id
    WHERE (f.user_id = :profile OR f.friend_id = :profile) 
      AND f.status='accepted' AND u.id != :profile
");
$friendsStmt->execute([':profile' => $profile_id]);
$friends = $friendsStmt->fetchAll(PDO::FETCH_ASSOC);

// Pending followers
$followersStmt = $pdo->prepare("
    SELECT u.id, ui.nickname 
    FROM friends f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN user_info ui ON u.id = ui.user_id
    WHERE f.friend_id = :profile AND f.status = 'pending'
");
$followersStmt->execute([':profile' => $profile_id]);
$followers = $followersStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    elseif ($diff < 3600) return floor($diff/60)." minute".(floor($diff/60)!=1?"s":"")." ago";
    elseif ($diff < 86400) return floor($diff/3600)." hour".(floor($diff/3600)!=1?"s":"")." ago";
    elseif ($diff < 172800) return "Yesterday at ".date("H:i", $time);
    elseif ($diff < 604800) return date("l at H:i", $time);
    else return date("d.m.Y at H:i", $time);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($info['nickname'] ?? 'User') ?> Profile</title>
    <link rel="stylesheet" href="../assets/style/css.css?v12">
    <style>
        .tabs { margin-right: -5px; text-align: right; }
        .tabs a {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 5px;
            background: #fff;
            border: 1px solid #ddd;
            border-bottom: none;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        .tabs a.active {
            background: #fff;
            position: relative;
            top: 1px;
            color: #0073b1;
        }
        .post{position:relative;margin-bottom:15px;}
        .pin-button{position:absolute;top:5px;right:5px;display:none;font-size:12px;background:#0073b1;color:#fff;padding:2px 5px;border-radius:3px;text-decoration:none;}
        .pinned-label{font-size:11px;color:#ff4500;font-weight:bold;margin-bottom:4px;display:block;}
        .post-author { font-size: 13px; color: #555; margin-bottom: 5px; }
        .post-author a { color: #0073b1; text-decoration: none; }
        .post:hover .pin-button { display: block; }
    </style>
</head>
<body>

<div class="header">
    <div class="navbar">
        <div class="menu">
            <a href="../index.php">Home</a>
        <?php if($auth_user_id): ?>
            <a href="">Discovery</a>
            <a href="">Live</a>
            <a href="">New</a>
        <?php endif; ?>
        </div>
        <div class="btns-head">
            <?php if($auth_user_id): ?>
                <a href="messages.php">Messages</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php">Log Out</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="profile-container">

    <div class="profile-header">
        <div class="left">
            <img src="<?= htmlspecialchars($avatar) ?>" class="avatar" alt="Avatar">
            <div><strong><?= htmlspecialchars($info['nickname'] ?? 'Not specified') ?></strong></div>
        </div>
        <div class="right">
            <?php if($auth_user_id == $profile_id): ?>
                <a href="edit.php">Edit Profile</a>
            <?php elseif($auth_user_id && $auth_user_id != $profile_id): ?>
                <a href="messages.php?to=<?= $profile_id ?>">Send Message</a>
                <a href="?id=<?= $profile_id ?>&add_friend=1&tab=<?= $tab ?>">Add Friend</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-main">
        <div class="posts">

            <!-- Tabs -->
            <div class="tabs">
                <a href="?id=<?= $profile_id ?>&tab=posts" class="<?= $tab==='posts' ? 'active' : '' ?>">Posts</a>
                <a href="?id=<?= $profile_id ?>&tab=friends_posts" class="<?= $tab==='friends_posts' ? 'active' : '' ?>">Friends post</a>
            </div>

            <?php if($auth_user_id == $profile_id && $tab === 'posts'): ?>
                <form method="post" class="post-form">
                    <textarea name="content" maxlength="140" placeholder="Write something (max 140 chars)..."></textarea><br>
                    <button type="submit">Publish</button>
                </form>
            <?php endif; ?>

            <?php if($posts): foreach($posts as $post): ?>
                <div class="post">
                    <?php if($post['pinned'] == 1): ?><div class="pinned-label">Pinned</div><?php endif; ?>

                    <?php if ($tab === 'friends_posts'): ?>
                        <div class="post-author">
                            <a href="profile.php?id=<?= $post['author_id'] ?>">
                                <?= htmlspecialchars($post['nickname'] ?? 'User #'.$post['author_id']) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                    <div class="date-post">
                        <p><?= timeAgo($post['created_at']) ?></p>
                    </div>

                    <?php if($auth_user_id == $post['user_id']): ?>
                        <a href="?id=<?= $profile_id ?>&pin=<?= $post['id'] ?>&tab=<?= $tab ?>" class="pin-button">
                            <?= $post['pinned']==1 ? 'Unpin' : 'Pin it' ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; else: ?>
                <div class="no-posts">No posts to show.</div>
            <?php endif; ?>

            <?php if($total_posts > $limit): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?id=<?= $profile_id ?>&tab=<?= $tab ?>&page=<?= $page-1 ?>">Previous</a>
                    <?php endif; ?>
                    <?php if($page < $total_pages): ?>
                        <a href="?id=<?= $profile_id ?>&tab=<?= $tab ?>&page=<?= $page+1 ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>         

            <div class="footer">
                <p>&copy; 2025 powered by <a href="">TeleNotes</a></p>
            </div>

        </div>

        <div class="sidebar">
            <!-- About User -->
            <div class="block-sidebar-info">
                <h3>About User</h3>
                <div class="info-row"><div class="info-label">Email:</div><div class="info-value"><?= htmlspecialchars($user['email']) ?></div></div>
                <div class="info-row"><div class="info-label">Nickname:</div><div class="info-value"><?= htmlspecialchars($info['nickname'] ?? 'Not specified') ?></div></div>
                <div class="info-row"><div class="info-label">About:</div><div class="info-value"><?= nl2br(htmlspecialchars($info['bio'] ?? 'Not specified')) ?></div></div>
                <div class="info-row"><div class="info-label">Profession:</div><div class="info-value"><?= htmlspecialchars($info['profession'] ?? 'Not specified') ?></div></div>
            </div>

            <?php if(!empty($friends)): ?>
                <div class="block-sidebar">
                    <div class="block-friends">
                        <h3>Friends (<?= count($friends) ?>)</h3>
                        <ul>
                            <?php foreach($friends as $f): ?>
                                <li><a href="profile.php?id=<?= $f['id'] ?>"><?= htmlspecialchars($f['nickname'] ?? 'User #'.$f['id']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>                    
            <?php endif; ?>

            <?php if(!empty($followers) && $auth_user_id == $profile_id): ?>
                <div class="block-sidebar">
                    <h3>Friend Requests</h3>
                    <ul>
                        <?php foreach($followers as $fol): ?>
                            <li>
                                <a href="profile.php?id=<?= $fol['id'] ?>"><?= htmlspecialchars($fol['nickname'] ?? 'User #'.$fol['id']) ?></a>
                                <a href="?id=<?= $profile_id ?>&accept_friend=<?= $fol['id'] ?>&tab=<?= $tab ?>" style="color:green;font-size:11px;">Accept</a>
                                <a href="?id=<?= $profile_id ?>&reject_friend=<?= $fol['id'] ?>&tab=<?= $tab ?>" style="color:red;font-size:11px;">Reject</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>