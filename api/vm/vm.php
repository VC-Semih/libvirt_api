<?php
require_once(dirname(__FILE__) . "/../libconnect.php");
/**
 * Returns json response with all domains and their information
 * Informations: domain_name, domain_uuid, domain_id, domain_state, domain_type, domain_memory, domain_cpu, domain_arch, domain_emulator, VNC Port
 * @api
 */
function getAllVMs()
{
    global $lv;
    $doms = $lv->get_domains();
    $myJson = array();
    if ($doms) {
        foreach ($doms as $domName) {
            try {
                $dom = $lv->get_domain_object($domName);
                $uuid = libvirt_domain_get_uuid_string($dom);
                $info = $lv->domain_get_info($dom);
                $mem = number_format($info['memory'] / 1024, 2, '.', ' ') . ' MB';
                $cpu = $info['nrVirtCpu'];
                $state = $lv->domain_state_translate($info['state']);
                $id = $lv->domain_get_id($dom);
                $arch = $lv->domain_get_arch($dom);
                $vnc = $lv->domain_get_vnc_port($dom);
                $domain_type = $lv->get_domain_type($domName);
                $domain_emulator = $lv->get_domain_emulator($domName);

                if (!$id)
                    $id = '-';
                if ($vnc <= 0)
                    $vnc = "N/A";
                unset($dom);
                $result = array();
                $result['domain_name'] = $domName;
                $result['domain_uuid'] = $uuid;
                $result['domain_id'] = $id;
                $result['domain_state'] = $state;
                $result['domain_type'] = $domain_type;
                $result['domain_memory'] = $mem;
                $result['domain_cpu'] = $cpu;
                $result['domain_arch'] = $arch;
                $result['domain_emulator'] = $domain_emulator;
                $result['VNC Port'] = $vnc;
                array_push($myJson, $result);
            } catch (Exception $e) {
                verbose(0, $lv->get_last_error());
            }
        }
        header('Content-Type: application/json');
        echo json_encode($myJson, JSON_PRETTY_PRINT);
    } else {
        verbose(2, 'No domains are available');
    }
}

/**
 * Returns json response with given domain uuid and it's information
 * Information: domain_name, domain_uuid, domain_id, domain_state, domain_type, domain_memory, domain_cpu, domain_arch, domain_emulator, VNC Port
 * @param $uuid
 * @api
 */
function getVM($uuid)
{
    global $lv;
    try {
        $domName = $lv->domain_get_name_by_uuid($uuid);
        if ($domName) {
            $dom = $lv->get_domain_object($domName);
            $info = $lv->domain_get_info($dom);
            $mem = number_format($info['memory'] / 1024, 2, '.', ' ') . ' MB';
            $cpu = $info['nrVirtCpu'];
            $state = $lv->domain_state_translate($info['state']);
            $id = $lv->domain_get_id($dom);
            $arch = $lv->domain_get_arch($dom);
            $vnc = $lv->domain_get_vnc_port($dom);
            $domain_type = $lv->get_domain_type($domName);
            $domain_emulator = $lv->get_domain_emulator($domName);

            if (!$id)
                $id = '-';
            if ($vnc <= 0)
                $vnc = "N/A";
            unset($dom);

            $result['domain_name'] = $domName;
            $result['domain_uuid'] = $uuid;
            $result['domain_id'] = $id;
            $result['domain_state'] = $state;
            $result['domain_type'] = $domain_type;
            $result['domain_memory'] = $mem;
            $result['domain_cpu'] = $cpu;
            $result['domain_arch'] = $arch;
            $result['domain_emulator'] = $domain_emulator;
            $result['VNC Port'] = $vnc;
        } else {
            verbose(0, 'This UUID doesn\'t exist!');
        }
    } catch (Exception $e) {
        verbose(0, $lv->get_last_error());
    }
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
}

/**
 * Adds a domain with given parameters, returns a json response.
 * @param $vmname
 * @param $ram
 * @param $disk
 * @param $cpucores
 * @api
 */
function addVM($vmname, $ram, $disk, $cpucores)
{
    global $lv;
    if (!empty($vmname)) {
        verbose(0, 'The virtual machine name can\'t be empty !');
    }
    if ($lv->get_domain_by_name($vmname)) {
        verbose(0, 'This virtual machine name is already taken !');
    }
    if ($ram < 1 || $ram > 3) {
        verbose(0, 'Incorrect ram value ! 0 < ram < 4');
    }
    if (!$lv->storagevolume_path_exists($disk)) {
        verbose(0, 'The specified disk doesn`t exist !');
    }
    if ($cpucores < 1 || $cpucores > 4) {
        verbose(0, 'Incorrect cpucores value ! 0 < cpucores < 5');
    }
    $generated_mac = $lv->generate_random_mac_addr();
    $vmxml = '<domain type="kvm">	
                  <name>' . $vmname . '</name>	
                  <memory unit="GiB">' . $ram . '</memory>
                  <currentMemory unit="GiB">' . $ram . '</currentMemory>	
                  <vcpu placement="static">' . $cpucores . '</vcpu>	
                  <os>
                    <type arch="x86_64" machine="pc-i440fx-rhel7.0.0">hvm</type>
                    <boot dev="hd"/>
                  </os>
                  <on_poweroff>destroy</on_poweroff>	
                  <on_reboot>restart</on_reboot>	
                  <on_crash>destroy</on_crash>	
                  <devices>	
                    <emulator>/usr/libexec/qemu-kvm</emulator>	
                    <disk type="file" device="disk">	
                      <driver name="qemu" type="qcow2"/>	
                      <source file="' . $disk . '"/>	
                      <target dev="hda" bus="ide"/>	
                      <address type="drive" controller="0" bus="0" target="0" unit="0"/>	
                    </disk>
                    <interface type="network">	
                      <mac address="' . $generated_mac . '"/>
                      <source network="default"/>	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x03" function="0x0"/>	
                    </interface>	
                    <serial type="pty">
                        <target port="0"/>
                    </serial>
                    <console type="pty">
                        <target type="serial" port="0"/>
                    </console>
                  </devices>
                </domain>';
    if (!$lv->domain_define($vmxml)) {
        verbose(0, 'Error while creating new virtual machine');
    } else {
        verbose(1, 'Virtual machine ' . $vmname . ' has been created successfully !');
    }
}

/**
 * Deletes a domain by uuid, returns a json response.
 * @api
 * @param $uuid
 */
function deleteVM($uuid)
{
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'group_member.php'); // Need group member to delete it if vm was a member of any group
    global $lv;
    $domName = $lv->domain_get_name_by_uuid($uuid);
    if ($domName) {
        if ($lv->domain_is_active($domName)) {
            $lv->domain_destroy($domName);
        } else {
            $disk = $lv->get_disk_stats($domName)[0]['file']; //Get 1st disk file
            $lv->domain_undefine($domName);
            if (file_exists($disk)) {
                unlink($disk);
                $lv->storagepool_refresh('images');
                $success_message = "The domain " . $domName . " has been destroyed";
                if ($group_name = findUUIDGroup($uuid)) {
                    if (deleteGroupMember($group_name, $uuid)) {
                        $success_message .= " and removed from group " . $group_name;
                    } else {
                        verbose(0, 'The domain ' . $domName . 'has been destroyed but could not be deleted from group ' . $group_name);
                    }
                }
                verbose(1, $success_message);
            }
        }
    } else {
        verbose(0, 'This UUID doesn\'t exist!');
    }
}
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    addCors();
    switch ($request_method) {
        case 'GET':
            if (!empty($_GET["uuid"])) {
                $uuid = $_GET["uuid"];
                getVM($uuid);
            } else {
                getAllVMs();
            }
            break;
        case 'POST':
            if ($_POST['vmname']) {
                addVM($_POST['vmname'], $_POST['ram'], $_POST['disk'], $_POST['cpucores']);
            }
            break;
        case 'DELETE':
            $uuid = $_GET["uuid"];
            deleteVM($uuid);
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>