<?php


$uid = $_GET['uid'];
$curPwd = $_GET['curpwd'];
$newPwdOne = $_GET['newpwdone'];
$newPwdTwo = $_GET['newpwdtwo'];
$successUrl = $_GET['successurl'];
$failureUrl = $_GET['failurl'];

include_once ("changeADPwd.class.php");

$objChangePasswd = new csChangeADPasswd($failureUrl,$successUrl);
$null = $objChangePasswd->changePWD($uid,$curPwd,$newPwdOne,$newPwdTwo);

?>
