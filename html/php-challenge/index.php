<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();

	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない
	header('Location: login.php'); exit();
}

// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
		$message->execute(array(
			$member['id'],
			$_POST['message'],
			$_POST['reply_post_id']
		));

		header('Location: index.php'); exit();
	}
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
	$page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
}

// htmlspecialcharsのショートカット
function h($value) {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value) {
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>' , $value);
}

//Retweet
if(isset($_REQUEST['retweet'])){
	//RT対象投稿の取得
	$getRetweets = $db->prepare('SELECT id, message, member_id, retweet_post_id, retweet_member_id FROM posts WHERE id=?');
	$getRetweets->execute(array($_REQUEST['retweet']));
	$getRetweet = $getRetweets->fetch();
		//RT対象投稿がRTか否か判別し元ポストのidを変数に用意
		if($getRetweet['retweet_post_id'] === (string)0){
			$retweetPost = $getRetweet['id'];
			$retweetMember = $getRetweet['member_id'];
		}else{
			$retweetPost = $getRetweet['retweet_post_id'];
			$retweetMember = $getRetweet['retweet_member_id'];
		}
	
	//RT済か否か判別
	$checkRetweets = $db->prepare('SELECT COUNT(*) AS count FROM posts WHERE member_id=? AND retweet_post_id=?');
	$checkRetweets->execute(array($member['id'], $retweetPost));
	$checkRetweet = $checkRetweets->fetch();
	
	if($checkRetweet['count'] === (string)0){
		//未RT
		$retweet = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=0, retweet_post_id=?, retweet_member_id=?, created=NOW()');
		$retweet->execute(array($getRetweet['message'], $member['id'], $retweetPost, $retweetMember));
	}else{
		//RT済
		$retweet = $db->prepare('DELETE FROM posts WHERE member_id=? AND retweet_post_id=?');
		$retweet->execute(array($member['id'], $retweetPost));
	}
	header('Location:index.php');exit();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>

	<link rel="stylesheet" href="style.css" />
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>ひとこと掲示板</h1>
  </div>
  <div id="content">
  	<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
    <form action="" method="post">
      <dl>
        <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
        <dd>
          <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
          <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
        </dd>
      </dl>
      <div>
        <p>
          <input type="submit" value="投稿する" />
        </p>
      </div>
    </form>

<?php
foreach ($posts as $post):
?>
    <div class="msg">
    <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
    <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
    <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
		<?php
if ($post['reply_post_id'] > 0):
?>
<a href="view.php?id=<?php echo
h($post['reply_post_id']); ?>">
返信元のメッセージ</a>
<?php
endif;
?>
<?php
if ($_SESSION['id'] == $post['member_id']):
?>
[<a href="delete.php?id=<?php echo h($post['id']); ?>"
style="color: #F33;">削除</a>]
<?php
endif;
?>

<?php
//表示ツイートがRTか否か判別し元ポストのidを変数に代入
if($post['retweet_post_id'] === (string)0){
	$retweetPosts = $post['id'];
}else{
	$retweetPosts = $post['retweet_post_id'];
}

//表示ツイートのRT数の取得
$retweetCounts = $db->prepare('SELECT COUNT(*) AS count FROM posts WHERE retweet_post_id=?');
$retweetCounts->execute(array($retweetPosts));
$retweetCount = $retweetCounts->fetch();

//ログインユーザーが表示ツイートをRTしているか否か取得
$allCheckRetweets = $db->prepare('SELECT COUNT(*) AS count FROM posts WHERE member_id=? AND retweet_post_id=?');
$allCheckRetweets->execute(array($member['id'], $retweetPosts));
$allCheckRetweet = $allCheckRetweets->fetch();
?>


[<a href="index.php?retweet=<?php echo h($post['id']);?>"
<?php
	//RT済の場合文字色変更
	if($allCheckRetweet['count'] !== (string)0){
		echo 'style="color:green;"';
	}
?>>RT</a>]
<?php
	//RT数の表示
	if($retweetCount['count'] > (string)0){
		echo $retweetCount['count'];
	}
?>

<!-- <a href="">&#9825;</a> -->

    </p>
    </div>
<?php
endforeach;
?>

<ul class="paging">
<?php
if ($page > 1) {
?>
<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
<?php
} else {
?>
<li>前のページへ</li>
<?php
}
?>
<?php
if ($page < $maxPage) {
?>
<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
<?php
} else {
?>
<li>次のページへ</li>
<?php
}
?>
</ul>
  </div>
</div>
</body>
</html>
