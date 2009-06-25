<?php

$uid = $_GET['uid'];
$curPwd = $_GET['curpwd'];
$newPwdOne = $_GET['newpwdone'];
$newPwdTwo = $_GET['newpwdtwo'];
$successUrl = $_GET['successurl'];
$failureUrl = $_GET['failurl'];

include ("changeADPwdConfig.php"); 
include ("changeADPwdValidate.php");

if (!$debug) {
  error_reporting(1);
}

require_once ("csLogging.class.php");

$logwriter = new csLogging($errorlogfile,$debuglogfile,$debug);


if ($failureUrl == "") { $failureUrl = $defaultfailurl; }
if ($successUrl == "") { $successUrl = $defaultsuccessurl; }

if (validate_new_pwd($newPwdOne, $newPwdTwo)){
