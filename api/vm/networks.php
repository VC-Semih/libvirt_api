<?php
require_once(dirname(__FILE__) . "/../libconnect.php");
function getAllNetworks()
{
    global $lv;
    $myJson = null;
    try {
        $tmp = $lv->get_networks(VIR_NETWORKS_ALL);
        if ($tmp) {
            $myJson = getNetworkInformations($tmp);
        } else {
            verbose(2, "Your machine doesn\'t have any network devices");
        }
    } catch (Exception $e) {
        verbose(0, $lv->get_last_error());
    }
    header('Content-Type: application/json');
    echo json_encode($myJson, JSON_PRETTY_PRINT);
}

function getNetworkInformations($networks_array)
{
    global $lv;
    $arr = array();
    for ($i = 0; $i < sizeof($networks_array); $i++) {
        $result = array();
        $network_ressource = $lv->get_network_information($networks_array[$i]);
        $result['network_name'] = urlencode($network_ressource['name']);
        $result['gateway_ip'] = '';
        $result['ip_range'] = '';
        $result['activity'] = $network_ressource['active'] ? 'Active' : 'Inactive';
        $result['dhcp'] = 'Disabled';
        $result['forward'] = 'None';
        if (array_key_exists('forwarding', $network_ressource) && $network_ressource['forwarding'] != 'None') {
            if (array_key_exists('forward_dev', $network_ressource))
                $result['forward'] = $network_ressource['forwarding'] . ' to ' . $network_ressource['forward_dev'];
            else
                $result['forward'] = $network_ressource['forwarding'];
        }

        if (array_key_exists('dhcp_start', $network_ressource) && array_key_exists('dhcp_end', $network_ressource))
            $result['dhcp'] = $network_ressource['dhcp_start'] . ' - ' . $network_ressource['dhcp_end'];

        if (array_key_exists('ip', $network_ressource))
            $result['gateway_ip'] = $network_ressource['ip'];

        if (array_key_exists('ip_range', $network_ressource))
            $result['ip_range'] = $network_ressource['ip_range'];
        array_push($arr, $result);
    }
    return $arr;
}

function getNetwork($network_name){
    global $lv;
    $myJson = null;
    try{
        $tmp = $lv->get_network_by_name($network_name);
        if ($tmp) {
            $myJson = getNetworkInformations(array($tmp))[0];
        }else{
            verbose(2, "Unable to find network device named ".$network_name);
        }
    }catch (Exception $e){
        verbose(0, $lv->get_last_error());
    }
    header('Content-Type: application/json');
    echo json_encode($myJson, JSON_PRETTY_PRINT);
}

function changeNetworkState($network_name)
{
    global $lv;
    $_PUT = array();
    parse_str(file_get_contents('php://input'), $_PUT);
    $action = $_PUT["action"];
    if ($action) {
        if ($action === 'network-start') {
            $lv->set_network_active($network_name, true) ? verbose(1, "Network has been started successfully") : verbose(0, "Error while starting network: " . $lv->get_last_error());
        } else if ($action === 'network-stop') {
            $lv->set_network_active($network_name, false) ? verbose(1, "Network has been stopped successfully") : verbose(0, "Error while stopping network: " . $lv->get_last_error());
        } else {
            verbose(0, "The specified action is unknown !");
        }
    } else {
        verbose(0, "Please specify an action !");
    }
}

function getNetworkConfiguration($network_name)
{
    global $lv;
    if ($data = $lv->get_network_xml($network_name)) {
        header("Content-type: text/xml");
        die($data);
    } else {
        verbose(0, 'Network not found !');
    }
}

function changeNetworkConfiguration($network_name)
{
    global $lv;
    $_PUT = array();
    parse_str(file_get_contents('php://input'), $_PUT);
    $xml = $_PUT['xml'];
    if ($lv->get_network_xml($network_name)) {
        $lv->network_change_xml($network_name, $xml) ? verbose(1, "Network definition has been changed (You might need to start/stop the network)") :
            verbose(0, 'Error changing network definition: ' . $lv->get_last_error());
    } else {
        verbose(0, 'Network not found !');
    }
}

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    switch ($request_method) {
        case 'GET':
            if(isset($_GET['network_name']) && !empty($_GET['network_name'])) {
                if (isset($_GET['action']) && $_GET['action'] === 'conf') {
                    getNetworkConfiguration($_GET['network_name']);
                }else{
                    getNetwork($_GET['network_name']);
                }
            }else {
                getAllNetworks();
            }
            break;
        case 'PUT':
            if (isset($_GET['network_name'])) {
                if (isset($_GET['action']) && $_GET['action'] === 'conf') {
                    changeNetworkConfiguration($_GET['network_name']);
                } else {
                    changeNetworkState($_GET['network_name']);
                }
            }
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>