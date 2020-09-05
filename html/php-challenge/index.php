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

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id OR m.id=p.retweet_member_id ORDER BY p.created DESC LIMIT ?, 5');
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

//RETWEET
if(isset($_REQUEST['retweet'])){
	$getSelectedPosts = $db->prepare('SELECT id, retweet_post_id FROM posts WHERE id=?');
	$getSelectedPosts->execute(array($_REQUEST['retweet']));
	$getSelectedPost = $getSelectedPosts->fetch();
	//Get PostID to Retweet
	if($getSelectedPost['retweet_post_id'] === (string)0){
		$retweetPostId = $getSelectedPost['id'];
	}else{
		$retweetPostId = $getSelectedPost['retweet_post_id'];
	}

	//CHeck Retweeted or not
	$checkRetweeted = $db->prepare('SELECT COUNT(*) AS count FROM posts WHERE retweet_member_id=? AND retweet_post_id=?');
	$checkRetweeted->execute(array($member['id'], $retweetPostId));
	$checkRetweet = $checkRetweeted->fetch();

	if($checkRetweet['count'] === (string)0){
		$retweet = $db->prepare('INSERT INTO posts SET retweet_post_id=?, retweet_member_id=?, created=NOW()');
	}else{
		//already retweet
		$retweet = $db->prepare('DELETE FROM posts WHERE retweet_post_id=? AND retweet_member_id=?');
	}
		$retweet->execute(array($retweetPostId, $member['id']));
	header('Location:index.php');exit();
}

//Like
if(isset($_REQUEST['like'])){
	//Like対象投稿の取得
	$getLikes = $db->prepare('SELECT id, retweet_post_id FROM posts WHERE id=?');
	$getLikes->execute(array($_REQUEST['like']));
	$getLike = $getLikes->fetch();
		//Like対象投稿がRTか否か判別し元ポストのidを変数に用意
		if($getLike['retweet_post_id'] === (string)0){
			$likePost = $getLike['id'];
		}else{
			$likePost = $getLike['retweet_post_id'];
		}

	//LIKE済か否か判別
	$checkLikes = $db->prepare('SELECT COUNT(*) AS count FROM likes WHERE post_id=? AND liked_member_id=?');
	$checkLikes->execute(array($likePost, $member['id']));
	$checkLike = $checkLikes->fetch();
	
	if($checkLike['count'] === (string)0){
		//未Like
		$like = $db->prepare('INSERT INTO likes SET post_id=?, liked_member_id=?');
	}else{
		//Like済
		$like = $db->prepare('DELETE FROM likes WHERE post_id=? AND liked_member_id=?');
	}
	$like->execute(array($likePost, $member['id']));

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
	//Get Post INFO
	if($post['retweet_post_id'] === (string)0){
		$timeline = array(
			"picture" => $post['picture'],
			"name" => $post['name'],
			"message" => $post['message'],
			"member_id" => $post['member_id'],
			"reply_post_id" => $post['reply_post_id'],
			"retweet_post_id" => $post['retweet_post_id'],
			"retweet_member_id" => $post['retweet_member_id'],
			"created" => $post['created'],
			"modified" => $post['modified']
		);
	}else{
		//GET ex-Posts data
		$exPosts = $db->prepare('SELECT m.name, m.picture, p.id, p.message, p.member_id, p.reply_post_id, p.retweet_post_id, p.retweet_member_id, p.created, p.modified FROM members m, posts p  WHERE p.id=? AND m.id=p.member_id');
		$exPosts->execute(array($post['retweet_post_id']));
		$exPost = $exPosts->fetch();

		$timeline = array(
			"picture" => $exPost['picture'],
			"name" => $exPost['name'],
			"message" => $exPost['message'],
			"member_id" => $exPost['member_id'],
			"reply_post_id" => $exPost['reply_post_id'],
			"retweet_post_id" => $exPost['retweet_post_id'],
			"retweet_member_id" => $exPost['retweet_member_id'],
			"created" => $exPost['created'],
			"modified" => $exPost['modified']
		);
	}
?>

    <div class="msg">
    <img src="member_picture/<?php echo h($timeline['picture']); ?>" width="48" height="48" alt="<?php echo h($timeline['name']); ?>" />
    <p><?php echo makeLink(h($timeline['message'])); ?><span class="name">（<?php echo h($timeline['name']); ?>）</span>[<a href="index.php?res=<?php echo h($timeline['id']); ?>">Re</a>]</p>
    <p class="day"><a href="view.php?id=<?php echo h($timeline['id']); ?>"><?php echo h($timeline['created']); ?></a>
		<?php
if ($post['reply_post_id'] > 0):
?>
<a href="view.php?id=<?php echo
h($post['reply_post_id']); ?>">
返信元のメッセージ</a>
<?php
endif;

if($post['retweet_member_id'] === (string)0){
	$tweetMemberId = $post['member_id'];
	$tweetPostId = $post['id'];
}else{
	$tweetMemberId = $post['retweet_member_id'];
	$tweetPostId = $post['retweet_post_id'];
}

if ($_SESSION['id'] === $tweetMemberId):
?>
[<a href="delete.php?id=<?php echo h($post['id']); ?>"
style="color: #F33;">削除</a>]
<?php
endif;
?>

<?php
//表示ツイートのRT数の取得
$retweetCounts = $db->prepare('SELECT COUNT(*) AS count FROM posts WHERE retweet_post_id=?');
$retweetCounts->execute(array($tweetPostId));
$retweetCount = $retweetCounts->fetch();

//ログインユーザーが表示ツイートをRTしているか否か取得
$allCheckRetweets = $db->prepare('SELECT COUNT(*) AS count FROM posts WHERE retweet_member_id=? AND retweet_post_id=?');
$allCheckRetweets->execute(array($member['id'], $tweetPostId));
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

//RTしたユーザーを表示
if($post['retweet_member_id'] !== (string)0){
	$retweetMemberNames= $db->prepare('SELECT name from members WHERE id=?');
	$retweetMemberNames->execute(array($post['retweet_member_id']));
	$retweetMemberName= $retweetMemberNames->fetch();

	echo "<br>".$retweetMemberName['name']."がRTしました";
}

//表示ツイートのLike数の取得
$likeCounts = $db->prepare('SELECT COUNT(*) AS count FROM likes WHERE post_id=?');
$likeCounts->execute(array($tweetPostId));
$likeCount = $likeCounts->fetch();

//ログインユーザーが表示ツイートをLikeしているか否か取得
$allCheckLikes = $db->prepare('SELECT COUNT(*) AS count FROM likes WHERE post_id=? AND liked_member_id=?');
$allCheckLikes->execute(array($tweetPostId,$member['id']));
$allChecklike = $allCheckLikes->fetch();

if($allChecklike['count'] === (string) 0){
?>
	<a href="index.php?like=<?=$post['id']?>"><span>&#9825;</span></a>
<?php
}else{
?>
	<a href="index.php?like=<?=$post['id']?>" style="color:#F33;"><span>&#9829;</span></a>
<?php
}
?>
<?php
if($likeCount['count'] > (string) 0 ){
	echo $likeCount['count'];
}
?>
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
