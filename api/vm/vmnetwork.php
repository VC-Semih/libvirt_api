<?php
require_once(dirname(__FILE__) . "/../libconnect.php");
/**
 * Returns a json response, displays the network information of the given domain uuid.
 * @param $uuid
 * @api
 */
function getVMNetwork($uuid)
{
    global $lv;
    $myJson = array();
    try {
        $domName = $lv->domain_get_name_by_uuid($uuid);
        if ($domName) {
            $nicInfo = $lv->get_nic_info($domName);
            if (!empty($nicInfo)) {
                $anets = $lv->get_networks(VIR_NETWORKS_ACTIVE);
                for ($i = 0; $i < sizeof($nicInfo); $i++) {
                    if (in_array($nicInfo[$i]['network'], $anets)) //Check if nicInfo contains a network in our network list $anets
                        $netUp = 'Yes';
                    else
                        $netUp = 'No';

                    $result['mac_addr'] = $nicInfo[$i]['mac'];
                    $result['nic_type'] = $nicInfo[$i]['nic_type'];
                    $result['network'] = $nicInfo[$i]['network'];
                    $result['is_network_active'] = $netUp;
                    array_push($myJson, $result);
                }
            } else {
                verbose(2, 'Domain doesn\'t have any network devices');
            }
        } else {
            verbose(0, 'This UUID doesn\'t exist!');
        }
    } catch (Exception $e) {
        verbose(0, $lv->get_last_error());
    }
    header('Content-Type: application/json');
    echo json_encode($myJson, JSON_PRETTY_PRINT);
}

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    addCors();
    switch ($request_method) {
        case 'GET':
            // Retrive VM state
            $uuid = $_GET["uuid"];
            getVMNetwork($uuid);

            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>