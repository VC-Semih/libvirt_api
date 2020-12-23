<?php
require_once(dirname(__FILE__) . "/../libconnect.php");

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

function addVM($vmname, $ram, $disk, $cpucores)
{
    global $lv;
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
    $generated_mac = exec('MACAddress="$(dd if=/dev/urandom bs=1024 count=1 2>/dev/null|md5sum|sed \'s/^\(..\)\(..\)\(..\)\(..\)\(..\)\(..\).*$/52:\2:\3:\4:\5:\6/\')";echo $MACAddress');
    $vmxml = '<domain type="kvm">	
                  <name>' . $vmname . '</name>	
                  <memory unit="GiB">' . $ram . '</memory>
                  <currentMemory unit="GiB">' . $ram . '</currentMemory>	
                  <vcpu placement="static">' . $cpucores . '</vcpu>	
                  <os>	
                    <type arch="x86_64" machine="pc-i440fx-focal">hvm</type>	
                    <boot dev="hd"/>	
                  </os>	
                  <features>	
                    <acpi/>	
                    <apic/>	
                    <vmport state="off"/>	
                  </features>	
                  <cpu mode="host-model" check="partial"/>	
                  <clock offset="utc">	
                    <timer name="rtc" tickpolicy="catchup"/>	
                    <timer name="pit" tickpolicy="delay"/>	
                    <timer name="hpet" present="no"/>	
                  </clock>	
                  <on_poweroff>destroy</on_poweroff>	
                  <on_reboot>restart</on_reboot>	
                  <on_crash>destroy</on_crash>	
                  <pm>	
                    <suspend-to-mem enabled="no"/>	
                    <suspend-to-disk enabled="no"/>	
                  </pm>	
                  <devices>	
                    <emulator>/usr/bin/qemu-system-x86_64</emulator>	
                    <disk type="file" device="disk">	
                      <driver name="qemu" type="qcow2"/>	
                      <source file="' . $disk . '"/>	
                      <target dev="hda" bus="ide"/>	
                      <address type="drive" controller="0" bus="0" target="0" unit="0"/>	
                    </disk>	
                    <disk type="file" device="cdrom">	
                      <driver name="qemu" type="raw"/>	
                      <target dev="hdb" bus="ide"/>	
                      <readonly/>	
                      <address type="drive" controller="0" bus="0" target="0" unit="1"/>	
                    </disk>	
                    <controller type="usb" index="0" model="ich9-ehci1">	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x05" function="0x7"/>	
                    </controller>	
                    <controller type="usb" index="0" model="ich9-uhci1">	
                      <master startport="0"/>	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x05" function="0x0" multifunction="on"/>	
                    </controller>	
                    <controller type="usb" index="0" model="ich9-uhci2">	
                      <master startport="2"/>	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x05" function="0x1"/>	
                    </controller>	
                    <controller type="usb" index="0" model="ich9-uhci3">	
                      <master startport="4"/>	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x05" function="0x2"/>	
                    </controller>	
                    <controller type="pci" index="0" model="pci-root"/>	
                    <controller type="ide" index="0">	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x01" function="0x1"/>	
                    </controller>	
                    <controller type="virtio-serial" index="0">	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x06" function="0x0"/>	
                    </controller>	
                    <interface type="network">	
                      <mac address="' . $generated_mac . '"/>
                      <source network="default"/>	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x03" function="0x0"/>	
                    </interface>	
                    <serial type="pty">	
                      <target type="isa-serial" port="0">	
                        <model name="isa-serial"/>	
                      </target>	
                    </serial>	
                    <console type="pty">	
                      <target type="serial" port="0"/>	
                    </console>	
                    <channel type="spicevmc">	
                      <target type="virtio" name="com.redhat.spice.0"/>	
                      <address type="virtio-serial" controller="0" bus="0" port="1"/>	
                    </channel>	
                    <input type="tablet" bus="usb">	
                      <address type="usb" bus="0" port="1"/>	
                    </input>	
                    <input type="mouse" bus="ps2"/>	
                    <input type="keyboard" bus="ps2"/>	
                    <graphics type="spice" autoport="yes">	
                      <listen type="address"/>	
                      <image compression="off"/>	
                    </graphics>	
                    <sound model="ich6">	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x04" function="0x0"/>	
                    </sound>	
                    <video>	
                      <model type="qxl" ram="65536" vram="65536" vgamem="16384" heads="1" primary="yes"/>	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x02" function="0x0"/>	
                    </video>	
                    <redirdev bus="usb" type="spicevmc">	
                      <address type="usb" bus="0" port="2"/>	
                    </redirdev>	
                    <redirdev bus="usb" type="spicevmc">	
                      <address type="usb" bus="0" port="3"/>	
                    </redirdev>	
                    <memballoon model="virtio">	
                      <address type="pci" domain="0x0000" bus="0x00" slot="0x07" function="0x0"/>	
                    </memballoon>	
                  </devices>
                </domain>';
    if (!$lv->domain_define($vmxml)) {
        verbose(0, 'Error while creating new virtual machine');
    } else {
        verbose(1, 'Virtual machine ' . $vmname . ' has been created successfully !');
    }
}

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
                $lv->storagepool_refresh('default');
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
    switch ($request_method) {
        case 'GET':
            // Retrive VMs
            if (!empty($_GET["uuid"])) {
                $uuid = $_GET["uuid"];
                getVM($uuid);
            } else {
                getAllVMs();
            }
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;

        case 'POST':
            if ($_POST['vmname']) {
                addVM($_POST['vmname'], $_POST['ram'], $_POST['disk'], $_POST['cpucores']);
            }
            break;

        case 'PUT':
            // Modifier un produit
            $id = intval($_GET["id"]);
            updateProduct($id);
            break;

        case 'DELETE':
            // Supprimer un produit
            $uuid = $_GET["uuid"];
            deleteVM($uuid);
            break;

    }
}
?>