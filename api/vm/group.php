<?php
require_once(dirname(__FILE__) . "/../libconnect.php");
$group_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'groups' . DIRECTORY_SEPARATOR;
/**
 * Returns group names or an error code if there isn't any group.
 * @return array|false
 * @api
 */
function getGroups()
{
    $myJson = array();
    $files = glob('groups/*.{json}', GLOB_BRACE);
    if ($files) {
        foreach ($files as $file) {
            array_push($myJson, basename($file, '.json'));
        }
        header('Content-Type: application/json');
        echo json_encode($myJson, JSON_PRETTY_PRINT);
        return $myJson;
    } else {
        verbose(2, 'There isn\'t any group');
    }
    return false;
}

/**
 * Changes the name of a group defined by param, the new name is passed by x-www-form-urlencoded as `new_name`
 * @api
 * @param $old_name
 */
function changeGroupName($old_name)
{
    $_PUT = array();
    parse_str(file_get_contents('php://input'), $_PUT);

    global $group_dir;
    $regex = '/^[a-zA-Z0-9]+$/m'; //regex, only chars and numbers
    $new_name = $_PUT['new_name'];

    if($old_name == $new_name){
        verbose(0,'The old name and new name can\'t be the same !');
    }

    if (is_dir($group_dir)) {
        if (preg_match($regex, $new_name) && preg_match($regex, $old_name)) {
            $old_filename = $group_dir . $old_name . '.json';
            $new_filename = $group_dir . $new_name . '.json';
            if (file_exists($old_filename)) {
                if (!file_exists($new_filename)) {
                    try {
                        rename($old_filename, $new_filename);
                        verbose(1, 'Group: ' . $old_name . ' has been renamed to ' . $new_name);
                    } catch (Exception $exception) {
                        verbose(0, 'Error while renaming the group !');
                    }
                } else {
                    verbose(0, 'New group name is already taken !');
                }
            } else {
                verbose(0, 'Specified group to rename has not been found !');
            }
        } else {
            verbose(0, 'Incorrect group name, the group name can\'t contain special chars');
        }
    } else {
        verbose(0, 'Fatal error, the group container doesn\'t exist');
    }

}

/**
 * Adds a group with a post named `group_name`
 * @api
 */
function addGroup()
{
    $regex = '/^[a-zA-Z0-9]+$/m'; //regex, only chars and numbers
    $group_name = $_POST['group_name'];
    global $group_dir;
    if (is_dir($group_dir)) {
        if (preg_match($regex, $group_name)) {
            $filename = $group_dir . $group_name . '.json';
            if (!file_exists($filename)) {
                try {
                    $json_file = fopen($filename,'w');
                    fwrite($json_file, "[]");
                    fclose($json_file);
                    verbose(1, $group_name . ' group has been created !');
                } catch (Exception $exception) {
                    verbose(0, 'An error occurred while creating file');
                }
            } else {
                verbose(0, 'This group name is already taken !');
            }
        } else {
            verbose(0, 'Incorrect group name, the group name can\'t contain special chars (regex[a-zA-Z0-9])');
        }
    } else {
        verbose(0, 'Fatal error, the group container doesn\'t exist');
    }
}

/**
 * Deletes a group with group name.
 * @param $group_name
 */
function deleteGroup($group_name){
    global $group_dir;
    $filename = $group_dir.$group_name.'.json';
    if(file_exists($filename)){
        try {
            unlink($filename);
            verbose(1, 'Group: '.$group_name.' has been deleted');
        }catch (Exception $exception){
            verbose(0, 'An error occurred while deleting file');
        }
    }else{
        verbose(0, 'Specified group not found !');
    }
}

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    addCors();
    switch ($request_method) {
        case 'GET':
            getGroups();
            break;
        case 'PUT':
            $old_name = $_GET["old_name"];
            changeGroupName($old_name);
            break;
        case 'POST':
            addGroup();
            break;
        case 'DELETE':
            $group_name = $_GET["group_name"];
            deleteGroup($group_name);
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>