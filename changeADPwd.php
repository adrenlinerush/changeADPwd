<?PHP

if (!$debug) {
  error_reporting(1);
}

$uid = $_GET['uid'];
$curPwd = $_GET['curpwd'];
$newPwdOne = $_GET['newpwdone'];
$newPwdTwo = $_GET['newpwdtwo'];
$successUrl = $_GET['successurl'];
$failureUrl = $_GET['failurl'];

include ("changeADPwdConfig.php"); 
include ("changeADPwdValidate.php");

if ($failureUrl == "") { $failureUrl = $defaultfailurl; }
if ($successUrl == "") { $successUrl = $defaultsuccessurl; }

if (validate_new_pwd($newPwdOne, $newPwdTwo)){
  // connect to LDAP server
  if ($debug) {
      $dtStamp = date("d/m/y : H:i:s", time());
      file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Validated Password\n" , FILE_APPEND | LOCK_EX);
  }
  $ldap = ldap_connect("ldaps://$ldapHost",636) or $ldap = false;
  if ($ldap) {
     //Connected successfully to ldap server
      if ($debug) {
        $dtStamp = date("d/m/y : H:i:s", time());
        file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Connected to LDAP Server\n" , FILE_APPEND | LOCK_EX);
      }
    $res = ldap_bind($ldap,$binddn,$bindpwd) or $res = false;
    if ($res)
    {
      //Succcessfully bound with search DN login
      if ($debug) {
        $dtStamp = date("d/m/y : H:i:s", time());
        file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Bound with Search DN: $binddn Passwd: $bindpwd\n" , FILE_APPEND | LOCK_EX);
      }
      if ($debug) {
          $dtStamp = date("d/m/y : H:i:s", time());
          file_put_contents($logfile,"DEBUG: $dtStamp: Searching for user: $uid\n" , FILE_APPEND | LOCK_EX);
      }
      $filter = "samaccountname=".$uid;
      $sr = ldap_search($ldap,$searchdc,$filter);
      if ($sr)
      {
        // Found username
        if ($debug) {
          $dtStamp = date("d/m/y : H:i:s", time());
          file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Found User\n" , FILE_APPEND | LOCK_EX);
        }
        $info = ldap_get_entries($ldap,$sr);
        if ($info["count"] > 0)
        {
          //Aquired user CN
          $user_cn = $info[0]["cn"][0];
          if ($debug) {
            $dtStamp = date("d/m/y : H:i:s", time());
            file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Found User CN: $user_cn\n" , FILE_APPEND | LOCK_EX);
          }
          $ldap = ldap_connect("ldaps://$ldapHost",636) or $ldap = false;
          if ($ldap) {
            if ($debug) {
              $dtStamp = date("d/m/y : H:i:s", time());
              file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Connected to LDAP Server Second Time\n" , FILE_APPEND | LOCK_EX);
            }
            $userdn = "cn=".$user_cn.", ".$oudc;
            //look up OU
            if ($debug) {
              $dtStamp = date("d/m/y : H:i:s", time());
              file_put_contents($logfile,"DEBUG: $dtStamp: UserDN: $userdn\n" , FILE_APPEND | LOCK_EX);
            }
            $res = @ldap_set_option($ldap , LDAP_OPT_PROTOCOL_VERSION, 3);
            $res = ldap_bind($ldap,$userdn,$curPwd);
            if ($res)
            {
              if ($debug) {
                $dtStamp = date("d/m/y : H:i:s", time());
                file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Bound With User: $userdn Password: $curPwd\n" , FILE_APPEND | LOCK_EX);
              }
          
              //$res = ldap_mod_replace ($ldap, $userdn, array('userpassword' => "{MD5}".base64_encode(pack("H*",md5($newPwdOne)))));
    
              $newpassword = "\"" . $newPwdOne. "\"";
              /*$len = strlen($newpassword);
              for ($i = 0; $i < $len; $i++) 
              {
                $newpass .= "{$newpassword{$i}}\000";
              }*/
              $newpass = mb_convert_encoding($newpassword, "UTF-16LE"); 
              $entry["unicodePwd"] = $newpass;
            
              $res = ldap_mod_replace($ldap, $userdn, $entry) or $res = false;
              
              if ($res) {
                //Successfully Changed user Password
                if ($debug) {
                  $dtStamp = date("d/m/y : H:i:s", time());
                  file_put_contents($logfile,"DEBUG: $dtStamp: Successfully Changed User Password: $newPwdOne\n" , FILE_APPEND | LOCK_EX);
                }
                Header ("Location: $successUrl");
                die();
              } 
              else {
                //Failed to change user Password
                file_put_contents($logfile,"$dtStamp: Failed to Change Password: $newPwdOne Unicode: $newpass LDAP Error:" . ldap_error($ldap) . "\n" , FILE_APPEND | LOCK_EX);
                Header ("Location: $failureUrl?failCode=8");
                die();
              }
              ldap_unbind($ldap);
            }
            else {
              //Failed to bind with user's credentials
              file_put_contents($logfile,"$dtStamp: Failed to Bind with search DN\n" . "BindDN: $userdn\nBindPwd: $curPwd\n" .  "Error: " . ldap_error($ldap) . "\n", FILE_APPEND | LOCK_EX);
              Header ("Location: $failureUrl?failCode=7");
              die();
            }
          }
          else {
            //Falied to connect second time    
            file_put_contents($logfile,"$dtStamp: Failed to connect second time\n" . "Server: $ldapHost\n" .  "Error: " . ldap_error($ldap) . "\n", FILE_APPEND | LOCK_EX);
            Header ("Location: $failureUrl?failCode=6");
            die();
          }
        }
        else{
          //Failed to aquire user CN
          file_put_contents($logfile,"$dtStamp: Failed to get User CN\n" . "User: $uid\n", FILE_APPEND | LOCK_EX);
          Header ("Location: $failureUrl?failCode=5"); 
          die();
        }      
      }
      else{
        //Failed to find username
        $dtStamp = date("d/m/y : H:i:s", time());
        file_put_contents($logfile,"$dtStamp: Failed to find user\n" . "User: $uid\n", FILE_APPEND | LOCK_EX);
        Header ("Location: $failureUrl?failCode=4"); 
        die();
      }
      ldap_unbind($ldap);
    }
    else {
      //Failed to Bind with search DN login
      $dtStamp = date("d/m/y : H:i:s", time());
      file_put_contents($logfile,"$dtStamp: Bind with search DN\n" . "BindDN: $binddn\nBindPwd: $bindpwd\n" .  "Error: " . ldap_error($ldap) . "\n", FILE_APPEND | LOCK_EX);
      Header ("Location: $failureUrl?failCode=3");
      die();
    }
  }
  else {
    //Failed to Connect to LDAP Server
    $dtStamp = date("d/m/y : H:i:s", time());
    file_put_contents($logfile,"$dtStamp: Failed to Connect to LDAP Server\n" . "Server: $ldapHost\n" .  "Error: " . ldap_error($ldap) . "\n", FILE_APPEND | LOCK_EX);
    Header ("Location: $failureUrl?failCode=2");
    die();
  }
}
else {
  //Failed Password Validation
  $dtStamp = date("d/m/y : H:i:s", time());
  file_put_contents($logfile,"$dtStamp: Failed Password Validation\n" . "Password: $newPwdOne  Password Validate: $newPwdTwo\n", FILE_APPEND | LOCK_EX);
  Header ("Location: $failureUrl?failCode=1");
  die();
}
?>
