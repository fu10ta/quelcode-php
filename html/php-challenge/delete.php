<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {

	$getSelectedPosts = $db->prepare('SELECT member_id, retweet_member_id FROM posts WHERE id=?');
	$getSelectedPosts->execute(array($_REQUEST['id']));
	$getSelectedpost = $getSelectedPosts->fetch();

	//DELETE Tweet or Retweet
	if($getSelectedpost['retweet_member_id'] === (string)0){
		//DELETE Tweet and those Retweet
		$delete = $db->prepare('DELETE FROM posts WHERE id=? OR retweet_post_id=?');
		$delete->execute(array($_REQUEST['id'], $_REQUEST['id']));
	}else{
		//DETELE selected Retweet only
		$delete = $db->prepare('DELETE FROM posts WHERE id=?');
		$delete->execute(array($_REQUEST['id']));
	}
}
header('Location: index.php'); exit();
?>
