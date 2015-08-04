<?php
/**
 * VMware vCloud SDK for PHP
 *
 * PHP version 5
 * *******************************************************
 * Copyright VMware, Inc. 2010-2014 All Rights Reserved.
 * *******************************************************
 *
 * @category    VMware
 * @package     VMware_VCloud_SDK
 * @subpackage  Samples
 * @author      Ecosystem Engineering
 * @disclaimer  this program is provided to you "as is" without
 *              warranties or conditions # of any kind, whether oral or written,
 *              express or implied. the author specifically # disclaims any implied
 *              warranties or conditions of merchantability, satisfactory # quality,
 *              non-infringement and fitness for a particular purpose.
 * @SDK version 5.7.0
 */
require_once dirname(__FILE__) . '/config.php';

/**
 * Sample for creating an organization
 */
// Get parameters from command line
$shorts  = "";
$shorts .= "s:";
$shorts .= "u:";
$shorts .= "p:";
$shorts .= "v:";

$shorts .= "a::";
$shorts .= "b::";
$shorts .= "c:";
$shorts .= "l";

$longs  = array(
    "server:",    //-s|--server    [required]
    "user:",      //-u|--user      [required]
    "pswd:",      //-p|--pswd      [required]
    "sdkver:",    //-v|--sdkver    [required]
    "org::",      //-a|--org       [required for creating]
    "full::",     //-b|--full      [required for creating]
    "certpath:",  //-c|--certpath  [optional] local certificate path
    "list",       //-l|--list
);

$opts = getopt($shorts, $longs);
//var_dump($opts);

// Initialize parameters
$httpConfig = array('ssl_verify_peer'=>false, 'ssl_verify_host'=>false);

$orgName = null;
$fullName = null;
// The following are hard-coded to simplify command line options
$description = "Org description";
$isEnabled = true;
$canPublishCatalogs = true;
$ldapMode = 'NONE';
$certPath = null;
$list = null;

// loop through command arguments
foreach (array_keys($opts) as $opt) switch ($opt)
{
    case "s":
        $server = $opts['s'];
        break;
    case "server":
        $server = $opts['server'];
        break;

    case "u":
        $user = $opts['u'];
        break;
    case "user":
        $user = $opts['user'];
        break;

    case "p":
        $pswd = $opts['p'];
        break;
    case "pswd":
        $pswd = $opts['pswd'];
        break;

    case "v":
        $sdkversion = $opts['v'];
        break;
    case "sdkver":
        $sdkversion = $opts['sdkver'];
        break;

    case "a":
        $orgName = $opts['a'];
        break;
    case "org":
        $orgName = $opts['org'];
        break;
        
    case "b":
        $fullName = $opts['b'];
        break;
    case "full":
        $fullName = $opts['full'];
        break;

    case "c":
        $certPath = $opts['c'];
        break;
    case "certpath":
        $certPath = $opts['certpath'];
        break;

    case "l":
        $list = true;
        break;
    case "list":
        $list = true;
        break;
}

// parameters validation
if ((!isset($server) || !isset($user) || !isset($pswd) || !isset($sdkversion)) ||
    ((true !== $list) && (!isset($orgName) || !isset($fullName))))
{
    echo "Error: missing required parameters\n";
    usage();
    exit(1);
}

$flag = true;
if (isset($certPath))
{
    $cert = file_get_contents($certPath);
    $data = openssl_x509_parse($cert);
    $encodeddata1 = base64_encode(serialize($data));

    // Split a server url by forward back slash
    $url = explode('/', $server);
    $url = end($url);

    // Creates and returns a stream context with below options supplied in options preset
    $context = stream_context_create();
    stream_context_set_option($context, 'ssl', 'capture_peer_cert', true);
    stream_context_set_option($context, 'ssl', 'verify_host', true);

    $encodeddata2 = null;
    if ($socket = stream_socket_client("ssl://$url:443/", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context))
    {
        if ($options = stream_context_get_options($context))
        {
            if (isset($options['ssl']) && isset($options['ssl']['peer_certificate']))
            {
                $x509_resource = $options['ssl']['peer_certificate'];
                $cert_arr = openssl_x509_parse($x509_resource);
                $encodeddata2 = base64_encode(serialize($cert_arr));
            }
        }
    }

    // compare two certificate as string
    if (strcmp($encodeddata1, $encodeddata2)==0)
    {
        echo "\n\nValidation of certificates is successful.\n\n";
        $flag=true;
    }
    else
    {
        echo "\n\nCertification Failed.\n";
        $flag=false;
    }
}

if ($flag==true)
{
    if (!isset($certPath))
    {
        echo "\n\nIgnoring the Certificate Validation --Fake certificate - DO NOT DO THIS IN PRODUCTION.\n\n";
    }
    // login
    $service = VMware_VCloud_SDK_Service::getService();
    $service->login($server, array('username'=>$user, 'password'=>$pswd), $httpConfig, $sdkversion);

    if (true === $list)
    {
        $orgRefs = $service->getOrgRefs();
        if (0 < count($orgRefs))
        {
            foreach ($orgRefs as $ref)
            {
                echo "href=" . $ref->get_href() . " type=" . $ref->get_type() .
                     " name=" . $ref->get_name() . "\n";
            }
        }
        exit(0);
    }

    // create an SDK Admin object
    $sdkAdmin = $service->createSDKAdminObj();

    // create an AdminOrgType data object
    $gSettings = new VMware_VCloud_API_OrgGeneralSettingsType();
    $gSettings->setCanPublishCatalogs($canPublishCatalogs);

    $lSettings = new VMware_VCloud_API_OrgLdapSettingsType();
    $lSettings->setOrgLdapMode($ldapMode);

    $settings = new VMware_VCloud_API_OrgSettingsType();
    $settings->setOrgGeneralSettings($gSettings);
    $settings->setOrgLdapSettings($lSettings);

    $adminOrgObj = new VMware_VCloud_API_AdminOrgType();
    $adminOrgObj->set_name($orgName); // name is required
    $adminOrgObj->setFullName($fullName);  // FillName is required
    $adminOrgObj->setDescription($description); // Description is optioanl
    $adminOrgObj->setSettings($settings); // Settings is required
    $adminOrgObj->setIsEnabled($isEnabled);
    //echo $adminOrgObj->export() . "\n";

    // create an administrative organization in vCloud Director.
    $sdkAdmin->createOrganization($adminOrgObj);
}
else
{
    echo "\n\nLogin Failed due to certification mismatch.";
    return;
}

/**
 * Print the help message of the sample.
 */
function usage()
{
    echo "Usage:\n\n";
    echo "  [Description]\n";
    echo "     This sample demonstrates creating a new organization in vCloud Director.\n";
    echo "\n";
    echo "  [Usage]\n";
    echo "     # php createorg.php -s <server> -u <username> -p <password> -v <sdkversion> [Options]\n";
    echo "\n";
    echo "     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.\n";
    echo "     -u|--user <username>             [req] User name in the form user@organization\n";
    echo "                                             for the vCloud Director.\n";
    echo "     -p|--pswd <password>             [req] Password for user.\n";
    echo "     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1, 5.5, 5.6 and 5.7.\n";
    echo "\n";
    echo "  [Options]\n";
    echo "     -a|--org <orgName>               [opt] Name of the organization to be created in the vCloud Director. Required when creating.\n";
    echo "     -b|--full <fullName>             [opt] Full name of the organization to be created. Required when creating.\n";
    echo "     -c|--certpath <certificatepath>  [opt] Local certificate's full path.\n";
    echo "     -l|--list                        [opt] List all organizations in vCloud Director\n";
    echo "\n";
    echo "  [Examples]\n";
    echo "     # php createorg.php -s 127.0.0.1 -u admin@Org -p password -v 5.7 -a=org -b=full\n";
    echo "     # php createorg.php -s 127.0.0.1 -u admin@Org -p password -v 5.7 -a=org -b=full -c certificatepath\n";
    echo "     # php createorg.php -a=org -b=full // using config.php to set login credentials\n";
    echo "     # php createorg.php -l // list all organizations in vCloud Director\n\n";
}
?>
