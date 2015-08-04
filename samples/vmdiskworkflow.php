<?php
/**
 * VMware vCloud SDK for PHP
 *
 * PHP version 5
 * *******************************************************
 * Copyright VMware, Inc. 2010-2014. All Rights Reserved.
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
 * For a given VM. Sample attaches a new disk and moves the disk to another storage profile.
 */
// Get parameters from command line
$shorts  = "";
$shorts .= "s:";
$shorts .= "u:";
$shorts .= "p:";
$shorts .= "v:";

$shorts .= "a:";
$shorts .= "b:";
$shorts .= "c:";
$shorts .= "d:";
$shorts .= "e:";
$shorts .= "f:";

$longs  = array(
    "server:",      //-s|--server      [required]
    "user:",        //-u|--user        [required]
    "pswd:",        //-p|--pswd        [required]
    "sdkver:",      //-v|--sdkver      [required]
    "org:",         //-a|--org         [required]
    "vdc:",         //-b|--vdc         [required]
    "vdcStorProf:", //-c|--vdcStorProf [required]
    "vapp:",        //-d|--vapp        [required]
    "vm:",          //-e|--vm          [required]
    "certpath:",    //-f|--certpath    [optional] local certificate path
);

$opts = getopt($shorts, $longs);
//var_dump($opts);

// Initialize parameters
$httpConfig = array('ssl_verify_peer'=>false, 'ssl_verify_host'=>false);

$orgName = null;
$vdcName = null;
$vdcStorageProfile = null;
$vAppName = null;
$vmName = null;
$busType = '6';
$busSubType = 'lsilogic';
$diskCapacity = '2040';
$addedDiskName = null;
$vdcStorageProfileRef = null;
$certPath = null;


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
        $vdcName = $opts['b'];
        break;
    case "vdc":
        $vdcName = $opts['vdc'];
        break;

    case "c":
        $vdcStorageProfile = $opts['c'];
        break;
    case "vdcStorProf":
        $vdcStorageProfile = $opts['vdcStorProf'];
        break;

    case "d":
        $vAppName = $opts['d'];
        break;
    case "vapp":
        $vAppName = $opts['vapp'];
        break;

    case "e":
        $vmName = $opts['e'];
        break;
    case "vm":
        $vmName = $opts['vm'];
        break;

    case "f":
        $certPath = $opts['f'];
        break;
    case "certpath":
        $certPath = $opts['certpath'];
        break;
}

// parameters validation
if ((!isset($server) || !isset($user) || !isset($pswd) || !isset($sdkversion)) ||
    !isset($orgName) || !isset($vdcName) || !isset($vdcStorageProfile) || !isset($vAppName) || !isset($vmName))
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

    // To find vApp VM and get sdk VM object
    $sdkVM = findVM();

    // To add VirtualDisk to VM and get updated VirtualDisk
    $virtualDisk = addVirtualDisk();
    // Modify virtual disk of this VM.
    $task = $sdkVM->modifyVirtualDisks($virtualDisk);
    echo "\nTask:\n" . $task->export();
    $task = $service->waitForTask($task);
    echo "\nTask after waitForTask:\n" . $task->export();
    echo "\nNew Disks Added - " . $addedDiskName . "\n";

    echo "\nModifying the Storage Profile for the disk\n";
    // Override the Default Vm Storage Profile with the specified Storage Profile.
    $virtualDisks = overrideVmStorageProfile(true);
    // To modify the storage profile of Virtual Disk
    $virtualDisks = updateStorageProfile($vdcStorageProfileRef);
    $task = $sdkVM->modifyVirtualDisks($virtualDisks);
    echo "\nTask:\n" . $task->export();
    $task = $service->waitForTask($task);
    echo "\nTask after waitForTask:\n" . $task->export();
    echo "\nModified Storage Profile For Disk: $addedDiskName";
}
else
{
    echo "\n\nLogin Failed due to certification mismatch.";
    return;
}

/**
 * Find VM
 *
 * @return VMware_VCloud_API_RasdItemsListType $virtualDisksTemp
 * @since SDK Version 5.6.3
 */
function findVM()
{
    global $service, $orgName, $vdcName, $vAppName, $vmName, $vdcStorageProfileRef, $vdcStorageProfile;
    echo "Searching VM: " . $vmName . "\n";
    // create an SDK Org object
    $orgRefs = $service->getOrgRefs($orgName);
    if (0 == count($orgRefs))
    {
        exit("No organization with name $orgName is found\n");
    }
    $orgRef = $orgRefs[0];
    $sdkOrg = $service->createSDKObj($orgRef);

    // create an SDK vDC object
    $vdcRefs = $sdkOrg->getVdcRefs($vdcName);
    if (0 == count($vdcRefs))
    {
        exit("No vDC with name $vdcName is found\n");
    }
    $vdcRef = $vdcRefs[0];
    $sdkVdc = $service->createSDKObj($vdcRef);

    // get vdc storage profile ref
    $vdcStorageProfileRefs = $sdkVdc->getVdcStorageProfileRefs($vdcStorageProfile);
    if (0 == count($vdcStorageProfileRefs))
    {
        exit("No vdc StorageProfile with name $vdcStorageProfile is found\n");
    }
    $vdcStorageProfileRef = $vdcStorageProfileRefs[0];

    // get a reference to a vApp in the vDC
    $vAppRefs = $sdkVdc->getVAppRefs($vAppName);
    if (!$vAppRefs)
    {
        exit("No vApp with name $vAppName is found\n");
    }
    $vAppRef = $vAppRefs[0];
    // create an SDK vApp object
    $sdkVApp = $service->createSDKObj($vAppRef);

    // get a reference to a vApp in the vDC
    $vmRefs = $sdkVApp->getContainedVmRefs($vmName);
    if (!$vmRefs)
    {
        exit("No vApp with name $vmName is found\n");
    }
    echo "\nFound VM\n";
    $vmRef = $vmRefs[0];
    // create an SDK vApp object
    $sdkVM = $service->createSDKObj($vmRef);
    return $sdkVM;
}

/**
 * To add VirtualDisk to VM
 *
 * @return VMware_VCloud_API_RasdItemsListType $virtualDisksTemp
 * @since SDK Version 5.6.3
 */
function addVirtualDisk()
{
    global $sdkVM, $busType, $busSubType, $diskCapacity, $addedDiskName;
    // Get virtual Disk of VMware_VCloud_API_RasdItemsListType
    $virtualDisks = $sdkVM->getVirtualDisks();
    $virtualDisksTemp = $sdkVM->getVirtualDisks();
    // Get all virtual Disk's items
    $items = $virtualDisks->getItem();
    // Initiate disk number with zero
    $diskNumber = 0;
    $itemTemp = null;
    foreach ($items as $item)
    {
        $hostResource = $item->getHostResource();
        if (!empty($hostResource))
        {
            // Get virtal hard disk's name
            $name = $item->getElementName()->get_valueof();
            $nameArray = explode(" ", $name);
            if ($nameArray[2] > $diskNumber)
            {
                $diskNumber = $nameArray[2];
                $itemTemp = $item;
            }
        }
    }
    //Name given to the disk added
    $addedDiskName = "Hard disk " . ($diskNumber + 1);
    $itemTemp->getElementName()->set_valueof($addedDiskName);
    echo "\nAdding the disk created to the VM: " . $sdkVM->getVm()->get_name() . "\n";
    $hostRes = $itemTemp->getHostResource();
    // Get all required resource to update
    $anyAttributes = $hostRes[0]->get_anyAttributes();
    $anyAttributes['capacity'] = $diskCapacity;
    $anyAttributes['busSubType'] = $busSubType;
    $anyAttributes['busType'] = $busType;
    $addressParent = $itemTemp->getAddressOnParent()->get_valueof();
    $addressParent = $addressParent + 1;
    $itemTemp->getAddressOnParent()->set_valueof($addressParent);
    // Get instance id of virtual hard disk to update
    $instanceId = $itemTemp->getInstanceID()->get_valueof();
    $instanceId = $instanceId + 1;
    $itemTemp->getInstanceID()->set_valueof($instanceId);
    // Update all required resource i.e. disk capacity, busSubType and busType
    $hostRes[0]->set_anyAttributes($anyAttributes);
    // Add new created virtual disk
    $virtualDisksTemp->addItem($itemTemp);
    return $virtualDisksTemp;
}

/**
 * Override the Default Vm Storage Profile with the specified Storage Profile.
 *
 * @param boolean $value
 * @return VMware_VCloud_API_RasdItemsListType $virtualDisks
 * @since SDK Version 5.6.3
 */
function overrideVmStorageProfile($value)
{
    global $sdkVM, $addedDiskName;
    $virtualDisks = $sdkVM->getVirtualDisks();
    $items = $virtualDisks->getItem();
    foreach ($items as $item)
    {
        $hostResource = $item->getHostResource();
        $element = $item->getElementName()->get_valueof();
        if (!empty($hostResource) && ($element == $addedDiskName))
        {
            $anyAttributes = $hostResource[0]->get_anyAttributes();
            if (array_key_exists('storageProfileOverrideVmDefault', $anyAttributes))
            {
                if ($value)
                {
                    $anyAttributes['storageProfileOverrideVmDefault'] = 'true';
                }
                else
                {
                    $anyAttributes['storageProfileOverrideVmDefault'] = 'false';
                }
                $hostResource[0]->set_anyAttributes($anyAttributes);
                return $virtualDisks;
            }
        }
    }
    exit("\nNo host resource is found\n");
}

/**
 * Update the storage Profile of the Disk.
 *
 * @param VMware_VCloud_API_ReferenceType $vdcStorageProfRef
 * @return VMware_VCloud_API_RasdItemsListType $virtualDisks
 * @since SDK Version 5.6.3
 */
function updateStorageProfile($vdcStorageProfRef)
{
    global $virtualDisks, $addedDiskName;
    $items = $virtualDisks->getItem();
    foreach ($items as $item)
    {
        $hostResource = $item->getHostResource();
        $element = $item->getElementName()->get_valueof();
        if (!empty($hostResource) && ($element == $addedDiskName))
        {
            $anyAttributes = $hostResource[0]->get_anyAttributes();
            if (array_key_exists('storageProfileHref', $anyAttributes))
            {
                $anyAttributes['storageProfileHref'] = $vdcStorageProfRef->get_href();
                $hostResource[0]->set_anyAttributes($anyAttributes);
                return $virtualDisks;
            }
        }
    }
    exit("\nNo host resource is found\n");
}

/**
 * Print the help message of the sample.
 */
function usage()
{
    echo "Usage:\n\n";
    echo "  [Description]\n";
    echo "     This sample attaches a new disk and moves the disk to another storage profile for a given VM.\n";
    echo "\n";
    echo "  [Usage]\n";
    echo "     # php vmdiskworkflow.php -s <server> -u <username> -p <password> -v <sdkversion> [Options]\n";
    echo "\n";
    echo "     -s|--server <IP|hostname>             [req] IP or hostname of the vCloud Director.\n";
    echo "     -u|--user <username>                  [req] User name in the form user@organization\n";
    echo "                                                 for the vCloud Director.\n";
    echo "     -p|--pswd <password>                  [req] Password for user.\n";
    echo "     -v|--sdkver <sdkversion>              [req] SDK Version e.g. 1.5, 5.1, 5.5, 5.6 and 5.7.\n";
    echo "\n";
    echo "  [Options]\n";
    echo "     -a|--org <orgName>                    [req] Name of an existing organization in the vCloud Director.\n";
    echo "     -b|--vdc <vdcName>                    [req] Name of an existing vDC in an organization.\n";
    echo "     -c|--vdcStorProf <vdcStorageProfile>  [req] Name of an existing vdcStorageProfile.\n";
    echo "     -d|--vapp <vAppName>                  [req] Name of an existing vApp (in power off state) to be deployed.\n";
    echo "     -e|--vm <vmName>                      [req] Name of an existing vm.\n";
    echo "     -f|--certpath <certificatepath>       [opt] Local certificate's full path.\n";
    echo "\n";
    echo "  [Examples]\n";
    echo "     # php vmdiskworkflow.php -s 127.0.0.1 -u admin@Org -p password -v 5.7 -a org -b vdc -c vdcStorProf -d vapp -e vm\n";
    echo "     # php vmdiskworkflow.php -s 127.0.0.1 -u admin@Org -p password -v 5.7 -a org -b vdc -c vdcStorProf -d vapp -e vm -f certificatepath\n";
    echo "     # php vmdiskworkflow.php -a org -b vdc -c vdcStorProf -d vapp -e vm // using config.php to set login credentials\n\n";
}
?>
