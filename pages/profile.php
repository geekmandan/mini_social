<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$profile_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$auth_user_id = $_SESSION['user_id'] ?? null;

if (!$profile_id) { echo "Invalid profile ID."; exit; }

// Fetch user info
$userStmt = $pdo->prepare("SELECT email FROM users WHERE id=:id LIMIT 1");
$userStmt->execute([':id'=>$profile_id]);
$user = $userStmt->fetch();
if (!$user) { echo "User not found."; exit; }

$infoStmt = $pdo->prepare("SELECT nickname, bio, profession, avatar FROM user_info WHERE user_id=:id LIMIT 1");
$infoStmt->execute([':id'=>$profile_id]);
$info = $infoStmt->fetch();
$avatar = $info['avatar'] ?? "/assets/default-avatar.jpg";

// -------------------
// Add Friend
if ($auth_user_id && $auth_user_id != $profile_id && isset($_GET['add_friend'])) {
    $check = $pdo->prepare("SELECT * FROM friends WHERE user_id=:user AND friend_id=:friend");
    $check->execute([':user'=>$auth_user_id, ':friend'=>$profile_id]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (:user, :friend, 'pending')")
            ->execute([':user'=>$auth_user_id, ':friend'=>$profile_id]);
    }
    header("Location: profile.php?id={$profile_id}");
    exit;
}

// -------------------
// Accept Friend
if ($auth_user_id && isset($_GET['accept_friend'])) {
    $friend_id = (int)$_GET['accept_friend'];
    $stmt = $pdo->prepare("UPDATE friends SET status='accepted' WHERE user_id=:user AND friend_id=:auth_user AND status='pending'");
    $stmt->execute([':user'=>$friend_id, ':auth_user'=>$auth_user_id]);
    header("Location: profile.php?id={$profile_id}");
    exit;
}

// -------------------
// Reject Friend
if ($auth_user_id && isset($_GET['reject_friend'])) {
    $friend_id = (int)$_GET['reject_friend'];
    $stmt = $pdo->prepare("DELETE FROM friends WHERE user_id=:user AND friend_id=:auth_user AND status='pending'");
    $stmt->execute([':user'=>$friend_id, ':auth_user'=>$auth_user_id]);
    header("Location: profile.php?id={$profile_id}");
    exit;
}

// -------------------
// Pin/Unpin post (only owner)
if (isset($_GET['pin']) && $auth_user_id==$profile_id) {
    $post_id = (int)$_GET['pin'];
    $stmt = $pdo->prepare("SELECT pinned FROM posts WHERE id=:id AND user_id=:user_id");
    $stmt->execute([':id'=>$post_id, ':user_id'=>$profile_id]);
    $post = $stmt->fetch();
    if ($post) {
        if ($post['pinned']==0) {
            $pdo->prepare("UPDATE posts SET pinned=0 WHERE user_id=:user_id")->execute([':user_id'=>$profile_id]);
            $pdo->prepare("UPDATE posts SET pinned=1 WHERE id=:id")->execute([':id'=>$post_id]);
        } else {
            $pdo->prepare("UPDATE posts SET pinned=0 WHERE id=:id")->execute([':id'=>$post_id]);
        }
    }
    header("Location: profile.php?id={$profile_id}");
    exit;
}

// -------------------
// Create post (only owner)
if ($auth_user_id==$profile_id && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if ($content!=="" && mb_strlen($content)<=140) {
        $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (:user_id, :content)")
            ->execute([':user_id'=>$profile_id, ':content'=>$content]);
        header("Location: profile.php?id={$profile_id}");
        exit;
    }
}

// -------------------
// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$limit = 10; $offset = ($page-1)*$limit;

// Posts
$postStmt = $pdo->prepare("SELECT * FROM posts WHERE user_id=:id ORDER BY pinned DESC, created_at DESC LIMIT :limit OFFSET :offset");
$postStmt->bindValue(':id',$profile_id,PDO::PARAM_INT);
$postStmt->bindValue(':limit',$limit,PDO::PARAM_INT);
$postStmt->bindValue(':offset',$offset,PDO::PARAM_INT);
$postStmt->execute();
$posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id=:id");
$countStmt->execute([':id'=>$profile_id]);
$total_posts = $countStmt->fetchColumn();
$total_pages = ceil($total_posts/$limit);

// -------------------
// Friends (accepted)
$friendsStmt = $pdo->prepare("
    SELECT u.id, ui.nickname 
    FROM friends f
    JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
    LEFT JOIN user_info ui ON u.id = ui.user_id
    WHERE (f.user_id = :profile OR f.friend_id = :profile) AND f.status='accepted' AND u.id != :profile
");
$friendsStmt->execute([':profile'=>$profile_id]);
$friends = $friendsStmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------
// Followers (pending)
$followersStmt = $pdo->prepare("
    SELECT u.id, ui.nickname 
    FROM friends f
    JOIN users u ON f.user_id=u.id
    LEFT JOIN user_info ui ON u.id=ui.user_id
    WHERE f.friend_id=:profile AND f.status='pending'
");
$followersStmt->execute([':profile'=>$profile_id]);
$followers = $followersStmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------
function timeAgo($datetime){
    $time=strtotime($datetime);
    $diff=time()-$time;
    if($diff<60) return "Just now";
    elseif($diff<3600) return floor($diff/60)." minute".(floor($diff/60)!=1?"s":"")." ago";
    elseif($diff<86400) return floor($diff/3600)." hour".(floor($diff/3600)!=1?"s":"")." ago";
    elseif($diff<172800) return "Yesterday at ".date("H:i",$time);
    elseif($diff<604800) return date("l at H:i",$time);
    else return date("d.m.Y at H:i",$time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Profile</title>
<link rel="stylesheet" href="../assets/style/css.css?v7">
<style>
.post{position:relative;}
.pin-button{position:absolute;top:5px;right:5px;display:none;font-size:12px;background:#0073b1;color:#fff;padding:2px 5px;border-radius:3px;text-decoration:none;}
.post:hover .pin-button{display:inline-block;}
.pinned-label{font-size:11px;color:#ff4500;font-weight:bold;margin-bottom:4px;}
.profile-header .right a{margin-left:6px;text-decoration:none;font-size:13px;color:#0073b1;border:1px solid #ccc;padding:3px 6px;border-radius:3px;}
.profile-header .right a:hover{background:#f5f8fa;}
.block-sidebar{background:#fff;border:1px solid #ccc;padding:10px;margin-bottom:15px;font-size:13px;}
.block-sidebar h3{margin-bottom:8px;}
.block-sidebar ul{padding-left:18px;margin:0;}
.block-sidebar ul li{margin-bottom:4px;list-style:disc;}
.block-sidebar ul li a{color:#0073b1;text-decoration:none;}
.block-sidebar ul li a:hover{text-decoration:underline;}
</style>
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
<?php if($auth_user_id==$profile_id): ?>
<a href="edit.php">Edit Profile</a>
<?php elseif($auth_user_id && $auth_user_id!=$profile_id): ?>
<a href="messages.php?to=<?= $profile_id ?>">Send Message</a>
<a href="?id=<?= $profile_id ?>&add_friend=1">Add Friend</a>
<?php endif; ?>
</div>
</div>

<div class="profile-main">
<div class="posts">
<?php if($auth_user_id==$profile_id): ?>
<form method="post" class="post-form">
<textarea name="content" maxlength="140" placeholder="Write something (max 140 chars)..."></textarea><br>
<button type="submit">Publish</button>
</form>
<?php endif; ?>

<?php if($posts): foreach($posts as $post): ?>
<div class="post">
<?php if($post['pinned']==1): ?><div class="pinned-label">Pinned</div><?php endif; ?>
<p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
<small><?= timeAgo($post['created_at']) ?></small>
<?php if($auth_user_id==$profile_id): ?>
<a href="?id=<?= $profile_id ?>&pin=<?= $post['id'] ?>" class="pin-button"><?= $post['pinned']==1?'Unpin':'Pin it' ?></a>
<?php endif; ?>
</div>
<?php endforeach; else: ?>
<div class="no-posts">No posts yet.</div>
<?php endif; ?>

<?php if($total_posts>$limit): ?>
<div class="pagination">
<?php if($page>1) echo "<a href='profile.php?id={$profile_id}&page=".($page-1)."'>Previous</a> "; ?>
<?php if($page<$total_pages) echo "<a href='profile.php?id={$profile_id}&page=".($page+1)."'>Next</a>"; ?>
</div>
<?php endif; ?>

<div class="footer">
<p>&copy; 2025 powered by <a href="">TeleNotes</a></p>
</div>
</div>

<div class="sidebar">
<div class="block-sidebar-info">
<h3>About User</h3>
<div class="info-row"><div class="info-label">Email:</div><div class="info-value"><?= htmlspecialchars($user['email']) ?></div></div>
<div class="info-row"><div class="info-label">Nickname:</div><div class="info-value"><?= htmlspecialchars($info['nickname'] ?? 'Not specified') ?></div></div>
<div class="info-row"><div class="info-label">About:</div><div class="info-value"><?= nl2br(htmlspecialchars($info['bio'] ?? 'Not specified')) ?></div></div>
<div class="info-row"><div class="info-label">Profession:</div><div class="info-value"><?= htmlspecialchars($info['profession'] ?? 'Not specified') ?></div></div>
</div>

<?php if(!empty($friends)): ?>
<div class="block-sidebar">
<h3>Friends</h3>
<ul>
<?php foreach($friends as $f): ?>
<li><a href="profile.php?id=<?= $f['id'] ?>"><?= htmlspecialchars($f['nickname'] ?? 'User #'.$f['id']) ?></a></li>
<?php endforeach; ?>
</ul>
</div>
<?php elseif(!empty($followers)): ?>
<div class="block-sidebar">
<h3>Followers</h3>
<ul>
<?php foreach($followers as $fol): ?>
<li>
<a href="profile.php?id=<?= $fol['id'] ?>"><?= htmlspecialchars($fol['nickname'] ?? 'User #'.$fol['id']) ?></a>
<?php if($auth_user_id==$profile_id): ?>
&nbsp;<a href="?id=<?= $profile_id ?>&accept_friend=<?= $fol['id'] ?>">Accept</a>
&nbsp;<a href="?id=<?= $profile_id ?>&reject_friend=<?= $fol['id'] ?>">Reject</a>
<?php endif; ?>
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
