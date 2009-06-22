<?PHP


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
  // connect to LDAP server
  $logwriter->debugwrite('Successfully Validated Password');
  $ldap = ldap_connect("ldaps://$ldapHost:636") or $ldap = false;
  if ($ldap) {
     //Connected successfully to ldap server
    $logwriter->debugwrite('Successfully Connected to LDAP Server');
    $res = ldap_bind($ldap,$binddn,$bindpwd) or $res = false;
    if ($res)
    {
      //Succcessfully bound with search DN login
      $logwriter->debugwrite("Successfully Bound with Search DN: $binddn Passwd: $bindpwd");
      
      $logwriter->debugwrite("Searching for user: $uid");
    
      $filter = "samaccountname=".$uid;
      $sr = ldap_search($ldap,$searchdc,$filter);
      if ($sr)
      {
        // Found username
        $logwriter->debugwrite("Successfully Found User");
        
        $info = ldap_get_entries($ldap,$sr);
        if ($info["count"] > 0)
        {
          //Aquired user CN
          $user_cn = $info[0]["cn"][0];
          $logwriter->debugwrite("Successfully Found User CN: $user_cn");
          
          $ldap = ldap_connect("ldaps://$ldapHost",636) or $ldap = false;
          if ($ldap) {
            $logwriter->debugwrite("Successfully Connected to LDAP Server Second Time");
            
            $userdn = "cn=".$user_cn.", ".$oudc;
            //look up OU
            $logwriter->debugwrite("UserDN: $userdn");
            
            $res = @ldap_set_option($ldap , LDAP_OPT_PROTOCOL_VERSION, 3);
            $res = ldap_bind($ldap,$userdn,$curPwd);
            if ($res)
            {
              $logwriter->debugwrite("Successfully Bound With User: $userdn Password: $curPwd");
                
              $newpassword = "\"" . $newPwdOne. "\"";
            
              $newpass = mb_convert_encoding($newpassword, "UTF-16LE"); 
              $entry["unicodePwd"] = $newpass;
            
              $res = ldap_mod_replace($ldap, $userdn, $entry) or $res = false;
              
              if ($res) {
                //Successfully Changed user Password
                $logwriter->debugwrite("Successfully Changed User Password: $newPwdOne");
                
                Header ("Location: $successUrl");
                die();
              } 
              else {
                //Failed to change user Password  
                 $logwriter->writelog("Failed to Change Password: $newPwdOne Unicode: $newpass LDAP Error:" . ldap_error($ldap));
                Header ("Location: $failureUrl?failCode=8");
                die();
              }
              ldap_unbind($ldap);
            }
            else {
              //Failed to bind with user's credentials
              $logwriter->writelog("Failed to Bind with search DN\n" . "BindDN: $userdn\nBindPwd: $curPwd\n" .  "Error: " . ldap_error($ldap));
              Header ("Location: $failureUrl?failCode=7");
              die();
            }
          }
          else {
            //Falied to connect second time   
          $logwriter->writelog("Failed to connect second time\n" . "Server: $ldapHost\n" .  "Error: " . ldap_error($ldap));
            Header ("Location: $failureUrl?failCode=6");
            die();
          }
        }
        else{
          //Failed to aquire user CN
          $logwriter->writelog("Failed to get User CN\n" . "User: $uid");
          Header ("Location: $failureUrl?failCode=5"); 
          die();
        }      
      }
      else{
        //Failed to find username
        $logwriter->writelog("Failed to find user\n" . "User: $uid");
        Header ("Location: $failureUrl?failCode=4"); 
        die();
      }
      ldap_unbind($ldap);
    }
    else {
      //Failed to Bind with search DN login
      $logwriter->writelog("Bind with search DN\n" . "BindDN: $binddn\nBindPwd: $bindpwd\n" .  "Error: " . ldap_error($ldap));
      Header ("Location: $failureUrl?failCode=3");
      die();
    }
  }
  else {
    //Failed to Connect to LDAP Server
    $logwriter->writelog("Failed to Connect to LDAP Server\n" . "Server: $ldapHost\n" .  "Error: " . ldap_error($ldap));
    Header ("Location: $failureUrl?failCode=2");
    die();
  }
}
else {
  //Failed Password Validation
  $logwriter->writelog("Failed Password Validation\n" . "Password: $newPwdOne  Password Validate: $newPwdTwo");
  Header ("Location: $failureUrl?failCode=1");
  die();
}
?>
