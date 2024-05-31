<?php
$myPath_xjkdf = realpath(dirname(__FILE__).'/../../../..');
require_once ($myPath_xjkdf.'/NicerAppWebOS/3rd-party/adodb5/adodb.inc.php');

// provide access to the following adodb5 drivers :
    // 1 : ADO connection 'msqli' = databases MySQL, MariaDB, Percona
    // Docs : https://adodb.org/dokuwiki/doku.php?id=v5:database:mysql
    /*--- example connection code :
    $db = newAdoConnection('mysqli')
    $db->ssl_key    = "key.pem";
    $db->ssl_cert   = "cert.pem";
    $db->ssl_ca     = "cacert.pem";
    $db->ssl_capath = null;
    $db->ssl_cipher = null;
    $db->connect($host, $user, $password, $database);
    ---*/

    // 2 : ADO connection 'postgresql9' = CURRENTLY SUPPORTED
    // Docs : https://adodb.org/dokuwiki/doku.php?id=v5:database:postgresql
    // Docs used in this example (because this supports both Windows and Linux PostgreSQL installations) : https://adodb.org/dokuwiki/doku.php?id=v5:database:pdo#pdo_pgsql
    /*--- example connection code                    :
    $db = newAdoConnection('pdo');
    $dsn  = 'pgsql:host=192.168.0.212;dbname=dvdrental';
    $user = 'someuser';
    $pass = 'somepass';
    $db->connect($dsn,$user,$pass);
    ---*/


class class_NicerAppWebOS_database_API_adodb {
    public $cn = 'class_NicerAppWebOS_database_API_fileSystemDB';
    public $version = '1.0.0';

    // i'm already thinking of the proper routing in the upward level's code :
    //  how to get data for a specific 'table' into a specifict connectorType's database architecure call-distribution code.
    // .. .. .

    // now i'm thinking about how to get the initial data stored properly in this file, so that those function calls even have data to store (on the file-system).
    public function __construct ($naWebOS, $username = 'Guest', $cRec = null) {
        global $dbConfigFile_couchdb;

        if (is_null($naWebOS)) $this->throwError(
            '__construct($naWebOS) : invalid $naWebOS',
            E_USER_ERROR
        );
        $this->cms = $naWebOS;

        $this->connectionSettings = $cRec;
    }


    public function setGlobals ($username) {
        global $naWebOS;
        $users = safeLoadJSONfile(
            realpath(dirname(__FILE__).'/../../../..')
            .'/NicerAppWebOS/domainConfigs/'.$naWebOS->domain.'/database.users.json.php'
        );
        //echo '<pre style="color:skyblue;background:rgba(0,50,0,0.7);">'; var_dump ($users); echo '</pre>'; die();
        //$users = json_decode($usersJSON, true);
        $groups = safeLoadJSONfile(
            realpath(dirname(__FILE__).'/../../../..')
            .'/NicerAppWebOS/domainConfigs/'.$naWebOS->domain.'/database.groups.json.php'
        );
        //$groups = json_decode($groupsJSON, true);

        $clientUsersJSONfn = //dirname(__FILE__).'/domainConfigs/'.$naWebOS->domain.'/database.users.CLIENT.json.php';
            realpath(dirname(__FILE__).'/../../../..')
            .'/NicerAppWebOS/domainConfigs/'.$naWebOS->domain.'/database.users.CLIENT.json.php';

        $clientUsersJSON = (!file_exists($clientUsersJSONfn) ? '' : require_return($clientUsersJSONfn));
        $clientUsers = json_decode ($clientUsersJSON, true);
        //echo '<pre style="color:skyblue;background:rgba(0,50,0,0.7);">'; var_dump ($clientUsers); echo '</pre>'; die();

        $clientGroupsJSONfn = //dirname(__FILE__).'/domainConfigs/'.$naWebOS->domain.'/database.groups.CLIENT.json.php';
            realpath(dirname(__FILE__).'/../../../..')
            .'/NicerAppWebOS/domainConfigs/'.$naWebOS->domain.'/database.groups.CLIENT.json.php';


        $clientGroupsJSON = (!file_exists($clientGroupsJSONfn) ? '' : require_return($clientGroupsJSONfn));
        $clientGroups = json_decode ($clientGroupsJSON, true);

        if (!is_null($clientUsers))
            $usersFinal = array_merge_recursive($users, $clientUsers);
        else $usersFinal = $users;
        $this->usersFinal = $usersFinal;
        //echo '<pre style="color:skyblue;background:rgba(0,50,0,0.7);">'; var_dump ($usersFinal); echo '</pre>'; die();

        if (!is_null($clientGroups))
            $groupsFinal = array_merge_recursive($groups, $clientGroups);
        else $groupsFinal = $groups;
        $this->groupsFinal = $groupsFinal;


        if (is_null($users)) {
            echo '<pre style="color:yellow;background:brown;">t3332:is_null($users);'.PHP_EOL;
            echo json_encode(debug_backtrace(), JSON_PRETTY_PRINT);
            echo '</pre>';
        }
        //echo '<pre style="color:green;">'.$username.'</pre>';
        //echo '<pre style="color:orange;">'.json_encode(debug_backtrace(),JSON_PRETTY_PRINT).'</pre>';

        if (!is_null($usersFinal))
        foreach ($usersFinal as $username1 => $userDoc) {
            $dbg = [
                'username1' => $username1,
                'username1-tr' => $this->translate_plainUserName_to_SQLuserName($username1),
                'username' => $username
            ];
            //echo '<pre style="color:blue;">t32118:'; var_dump ($dbg); echo '</pre>';
            if (
                $this->translate_plainUserName_to_SQLuserName($username1)===$username
                || $username1===$username
                //|| $username==='admin'
            ) {
               //echo '<pre>'.$this->translate_plainUserName_to_couchdbUserName($username1).'==='.$username.'</pre>';
                $g = [];
                //echo '<pre>'; var_dump($userDoc); echo '</pre>';
                foreach ($userDoc['groups'] as $idx => $gn) {
                    $g[] = $this->translate_plainGroupName_to_SQLgroupName($gn);
                };
                //echo '<pre>'; var_dump ($g); echo '</pre>';

                $this->security_admin = json_encode([
                    "admins" => [
                        "names" => [],
                        "roles" => $g
                    ],
                    "members" => [
                        "names" => [],
                        "roles" => $g
                    ]
                ]);


                $g = [];
                $g[] = $this->translate_plainGroupName_to_SQLgroupName('Guests');

                $this->security_guest = json_encode([
                    "admins" => [
                        "names" => [],
                        "roles" => $g
                    ],
                    "members" => [
                        "names" => [],
                        "roles" => $g
                    ]
                ]);
            }
        }

        return true;
    }


    public function connect ($dbName) {
        $cRec = $this->connectionSettings;
        $db = false;
        switch ($cRec['type']) {
            case 'msqli':
                $db = newAdoConnection('mysqli');
                $db->ssl_key = $cRec['ssl_key'];
                $db->ssl_cert = $cRec['ssl_cert'];
                $db->ssl_ca = $cRec['ssl_ca'];
                $db->ssl_capath = null;
                $db->ssl_cipher = null;
                $db->connect ($cRec['host'], $cRec['user'], $cRec['password'], $dbName);
                break;
            case 'pdo':
                $db = newAdoConnection('pdo');
                $dsn = 'pgsql:host='.$cRec['host'].';dbname='.$dbName;
                $u = $cRec['user'];
                $p = $cRec['password'];
                $db->connect ($dsn, $u, $p);
                break;
        }

        if ($db === false) {
            $this->throwError(
                'connect() : Could not connect to '.json_encode($cRec, JSON_PRETTY_PRINT),
                E_USER_WARNING
            )
        }
        return $db;
    }

    public function dataSetName_domainName ($domainName) {
        $dn = str_replace('.','_',strToLower($domainName));
        if (preg_match('/^\d/', $dn)) {
            $dn = 'number_'.$dn;
        }
        return $dn;
    }

    public function dataSetName ($dbSuffix) {
        global $naWebOS;
        $domainName = $this->dataSetName_domainName($naWebOS->domain);
        $dataSetName = $domainName.'___'.str_replace('.','_',$dbSuffix);
        $dataSetName = strtolower($dataSetName);
        return $dataSetName;
    }

    public function dbName ($dbSuffix) {
        return $this->dataSetName($dbSuffix);
    }

    public function translate_plainUserName_to_SQLuserName ($un) {
        global $naWebOS;
        $dn = $this->dataSetName_domainName($naWebOS->domain);
        $un = str_replace($dn.'___', '', $un);
        return $dn.'___'.str_replace('.','__',str_replace(' ', '_', $un));
    }
    public function translate_SQLuserName_to_plainUserName ($un) {
        $un = preg_replace('/.*___/','', $un);
        return str_replace('_',' ',str_replace('__', '.', $un));
    }

    public function translate_plainGroupName_to_SQLgroupName ($gn) {
        global $naWebOS;
        $dn = $this->dataSetName_domainName($naWebOS->domain);
        //echo '<pre style="color:red">'; var_dump ($dn); echo '</pre>';
        $gn = str_replace($dn.'___', '', $gn);
        //echo '<pre style="color:purple">'; var_dump ($dn); echo '</pre>';
        return $dn.'___'.str_replace('.','__',str_replace(' ', '_', $gn));
    }
    public function translate_SQLgroupName_to_plainGroupName ($gn) {
        $gn = preg_replace('/.*___/','', $gn);
        return str_replace('_',' ',str_replace('__', '.', $gn));
    }

    public function throwError ($msg, $errorLevel) {
        echo '<pre class="nicerapp_error__database">$msg='.$msg.', $errorLevel='.$errorLevel.'</pre>';
        trigger_error ($msg, $errorLevel);
    }

    public function createUsers($users=null, $groups=null) {
        // $users and $groups are defined in .../NicerAppWebOS/db_init.php (bottom of the file).
        global $naWebOS;
        $g2 = [];
        //echo '<pre>633:'; var_dump ($users); die();
        foreach ($users as $userName => $userDoc) {
            $dn = $this->dataSetName_domainName($naWebOS->domain);
            $uid = 'org.couchdb.user:'.$this->translate_plainUserName_to_couchdbUserName($userName);
            //var_dump ($uid); die();
            $got = true;
            $this->cdb->setDatabase('_users',false);
            try { $call = $this->cdb->get($uid); } catch (Exception $e) { $got = false; }
            $g = [];
            foreach ($userDoc['groups'] as $idx => $gn) {
                $gn1 = $this->translate_plainGroupName_to_couchdbGroupName($gn);
                if (!in_array($gn1, $g2)) $g2[] = $gn1;
                $g[] = $gn1;
            };
            try {
                $rec = array (
                    '_id' => $uid,
                    'name' => $this->translate_plainUserName_to_couchdbUserName($userName),
                    'password' => $userDoc['password'],
                    'realname' => $userDoc['realname'],
                    'email' => $userDoc['email'],
                    'roles' => $g, // a CouchDB 'role' is a SQL 'group'.
                    'type' => "user"
                );
                if ($got) $rec['_rev'] = $call->body->_rev;
                $call = $this->cdb->post ($rec);
                if ($call->body->ok) echo (!$got?'Created ':'Updated ').$this->translate_plainUserName_to_couchdbUserName($userName).' user document in database _users.<br/>'; else echo '<span style="color:red">Could not '.(!$got?'create ':'update ').$this->translate_plainUserName_to_couchdbUserName($userName).' user document in database _users.</span><br/>';
            } catch (Exception $e) {
                echo '<pre style="color:red">'; var_dump ($e); echo '</pre>';
            }
        }



        $dataSetName = $this->dataSetName('groups');
        try { $this->cdb->deleteDatabase ($dataSetName); } catch (Exception $e) { };
        $this->cdb->setDatabase($dataSetName, true);
        foreach ($groups as $gn => $groupRec) {
            $gn1 = $this->translate_plainGroupName_to_couchdbGroupName($gn);
            $got = true;
            try { $call = $this->cdb->get($gn); } catch (Exception $e) { $got = false; }
            if ($got) $groupRec['_rev'] = $call->body->_rev;
            $groupRec['_id'] = $gn;
            $call = $this->cdb->post ($groupRec);
            //echo '<pre style="background:purple;color:white;border-radius:10px;">'; var_dump ($call); echo '</pre>';
            if ($call->body->ok) echo (!$got?'Created ':'Updated ').'\''.$gn.'\' group document in database '.$dataSetName.'.<br/>'; else echo '<span style="color:red">Could not '.(!$got?'create ':'update ').'\''.$gn.'\' group document in database '.$dataSetName.'.</span><br/>';

        }

        return true;
    }


}

?>
