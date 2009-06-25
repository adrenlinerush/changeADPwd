<?php


$uid = $_GET['uid'];
$curPwd = $_GET['curpwd'];
$newPwdOne = $_GET['newpwdone'];
$newPwdTwo = $_GET['newpwdtwo'];
$successUrl = $_GET['successurl'];
$failureUrl = $_GET['failurl'];

include_once ("changeADPwdConfig.class.php");

$objChangePasswd = new csChangeADPasswd($failureUrl,$successUrl);
$objChangePasswd->changePWD($uid,$curPwd,$newPwdOne,$newPwdTwo);

?>
