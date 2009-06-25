<?php
class csChangeADPasswd {
  private $logwriter;
  private $strFailURL;
  private $strSuccessURL;
  private $strHost;
  private $strSearchDC;
  private $objSearchBind;
  private $strOUDC;

  function __construct($failureUrl, $successUrl) {
    include_once ("changeADPwdConfig.php"); 

    if (!$debug) {
      error_reporting(1);
    }

    require_once ("csLogging.class.php");

    $this->logwriter = new csLogging($errorlogfile,$debuglogfile,$debug);

    if ($failureUrl == "") { $failureUrl = $defaultfailurl; }
    $this->strFailURL = $failureURL;
    if ($successUrl == "") { $successUrl = $defaultsuccessurl; }
    $this->strSuccessURL = $successUrl;
  
    $this->strHost = $ldapHost;
    $this->strSearchDC = $searchdc;
    $this->strOUDC = $oudc;

    $this->objSearchBind = $this->bindLDAP($binddn,$bindpwd, true);
  }

  public function changePWD($strUID, $strOldPwd, $strNewPwdOne, $strNewPwdTwo) {
    include_once ("changeADPwdValidate.php");
    if (validate_new_pwd($strNewPwdOne, $strNewPwdTwo)){
      $strUser = $this->getDN($strUID);
      $strUserDN = "cn=".$strUser.", ". $this->strOUDC;
      $objUserBind = $this->bindLDAP($strUserDN, $strOldPwd, false);
      $this->changeADPWD($objUserBind, $strUserDN, $strNewPwdOne);
    }
    else {
      $this->failure(1, array($strNewPwdOne, $strNewPwdTwo));
    }
  }

  private function bindLDAP($strDN, $strPWD, $bSearch) {
    $ldap = ldap_connect("ldaps://$this->strHost:636") or $ldap = false;
    if ($ldap) {
       //Connected successfully to ldap server
      $this->logwriter->debugwrite('Successfully Connected to LDAP Server');
      $res = ldap_bind($ldap,$strDN,$strPWD) or $res = false;
      if ($res) {
        //Succcessfully bound with search DN login
        $this->logwriter->debugwrite("Successfully Bound with Search DN: $strDN Passwd: $strPWD");
        return $ldap;
      }
      else {
        if ($bSearch) {
          $this->failure(3, array($strDN, $strPWD, ldap_error($ldap)));
        }
        else {
          $this->failure(7, array($strDN, $strPWD, ldap_error($ldap)));
        }
      }
    }
    else {
      if ($bSearch) {
        $this->failure(2, array($this->strHost));
      }
      else {
        $this->failure(6, array($this->strHost));
      }
    }
  }

  private function getDN($uid) {
    $filter = "samaccountname=".$uid;
    $sr = ldap_search($this->objSearchBind,$this->strSearchDC,$filter);
    if ($sr)
    {
      // Found username
      $this->logwriter->debugwrite("Successfully Found User");
      
      $info = ldap_get_entries($this->objSearchBind,$sr);
      if ($info["count"] > 0)
      {
        //Aquired user CN
        $user_cn = $info[0]["cn"][0];
        $this->logwriter->debugwrite("Successfully Found User CN: $user_cn");
        return $user_cn;
      }
      else {
        $this->failure(5, array($uid));
      }
    }
    else {
       $this->failure(4, array($uid));
    }
  }

  private function changeADPWD($objLdapBinding, $strUserDN, $strNewPwd) {
    $newpassword = "\"" . $strNewPwd . "\"";
            
    $newpass = mb_convert_encoding($newpassword, "UTF-16LE"); 
    $entry["unicodePwd"] = $newpass;
  
    $res = ldap_mod_replace($objLdapBinding, $strUserDN, $entry) or $res = false;
    
    if ($res) {
      $this->success($strNewPwd);
    } 
    else {
      //Failed to change user Password  
      $this->failure(8, array($strNewPwd,$newpass,ldap_error($objLdapBinding)));
    }
  }

  private function failure($iFailCode, $aryLogParams) {
    switch($iFailCode) {
      case 1:
        $this->logwriter->writelog("Failed Password Validation\n" . "Password: $aryLogParams[0]  Password Validate: $aryLogParams[1]");
        break;
      case 2:
        $this->logwriter->writelog("Failed to Connect to LDAP Server\n" . "Server: $aryLogParams[0]\n" .  "Error: " . $aryLogParams[1]);
        break;
      case 3:
        $this->logwriter->writelog("Bind with search DN\n" . "BindDN: $aryLogParams[0]\nBindPwd: $aryLogParams[1]\n" .  "Error: " . $aryLogParams[2]);
        break;
      case 4:
        $this->logwriter->writelog("Failed to find user\n" . "User: $aryLogParams[0]");
        break;
      case 5:
        $this->logwriter->writelog("Failed to get User CN\n" . "User: $aryLogParams[0]");
        break;
      case 6:
        $this->logwriter->writelog("Failed to connect second time\n" . "Server: $aryLogParams[0]\n" .  "Error: " . $aryLogParams[1]);
        break;
      case 7:
        $this->logwriter->writelog("Failed to Bind with search DN\n" . "BindDN: $aryLogParams[0]\nBindPwd: $aryLogParams[1]\n" .  "Error: " . $aryLogParams[2]);
        break;
      case 8:
        $this->logwriter->writelog("Failed to Change Password: $aryLogParams[0] Unicode: $aryLogParams[1] LDAP Error:" . $aryLogParams[2]);
        break;    
    }
    Header ("Location: " . $this->strFailURL . "?failCode=$iFailCode");
    die();
  }

  private function success($strNewPwd) {
    $this->logwriter->debugwrite("Successfully Changed User Password: $strNewPwd");             
    Header ("Location: " . $this->strSuccessURL);
    die();
  }
}




