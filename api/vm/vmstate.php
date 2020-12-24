<?php
/*
 * CIFTCI Semih Copyright (c) 2020. All rights reserved.
 */

require_once(dirname(__FILE__) . "/../libconnect.php");
/**
 * Returns a json response of the state of the given domain uuid (running, shutoff etc...).
 * @param $uuid
 * @api
 */
function getVMState($uuid)
{
    global $lv;
    try {
        $domName = $lv->domain_get_name_by_uuid($uuid);
        if ($domName) {
            $dom = $lv->get_domain_object($domName);
            $info = $lv->domain_get_info($dom);
            $state = $lv->domain_state_translate($info['state']);

            $result['domain_state'] = $state;
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
 * Changes the state of the given domain uuid, the state is passed by x-www-form-urlencoded as `action`.
 * Action value can be domain-start, domain-stop, domain-suspend, domain-resume
 * Returns a json response.
 * @api
 * @param $uuid
 */
function changeVMState($uuid)
{
    global $lv;
    $_PUT = array();
    parse_str(file_get_contents('php://input'), $_PUT);
    $action = $_PUT["action"];
    if ($action) {
        $domName = $lv->domain_get_name_by_uuid($uuid);
        if ($domName) {
            if ($action == 'domain-start') {
                $lv->domain_start($domName) ? verbose(1, 'Domain has been started.') : verbose(0, 'Failed to start the domain: ' . $lv->get_last_error());
            } else if ($action == 'domain-stop') {
                $lv->domain_shutdown($domName) ? verbose(1, 'Domain has been stopped.') : verbose(0, 'Failed to stop the domain: ' . $lv->get_last_error());
            } else if ($action == 'domain-suspend') {
                $lv->domain_suspend($domName) ? verbose(1, 'Domain has been suspended.') : verbose(0, 'Failed to suspend the domain: ' . $lv->get_last_error());
            } else if ($action == 'domain-resume') {
                $lv->domain_resume($domName) ? verbose(1, 'Domain has been resumed.') : verbose(0, 'Failed to resume the domain: ' . $lv->get_last_error());
            } else {
                verbose(0, 'This action doesn\'t exist!');
            }
        } else {
            verbose(0, 'This UUID doesn\'t exist!');
        }
    } else {
        verbose(0, 'Please specify an action!');
    }
}
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    addCors();
    switch ($request_method) {
        case 'GET':
            // Retrive VM state
            $uuid = $_GET["uuid"];
            getVMState($uuid);

            break;
        case 'PUT':
            // Modify VM State
            $uuid = $_GET["uuid"];
            changeVMState($uuid);
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>