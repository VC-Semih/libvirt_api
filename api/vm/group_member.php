<?php
require_once(dirname(__FILE__) . "/../libconnect.php");
$group_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'groups' . DIRECTORY_SEPARATOR;

function getGroupsMembers()
{
    $myJson = array();
    $files = glob('groups/*.{json}', GLOB_BRACE);
    if ($files) {
        foreach ($files as $file) {
            $myJson[basename($file, '.json')] = array(); //Preparing array to store the data

            $fp = fopen($file, "r"); // Opening file in read mode
            $content = fread($fp, filesize($file)); //Getting content
            fclose($fp); //Closing file

            $data = json_decode($content, true); //Decoding json
            array_push($myJson[basename($file, '.json')], $data);
        }
        header('Content-Type: application/json');
        echo json_encode($myJson, JSON_PRETTY_PRINT);
    } else {
        verbose(2, 'There isn\'t any group');
    }
}

function getGroupMembers($group_name)
{
    global $group_dir;

    $myJson = array();
    if (is_dir($group_dir)) {
        $filename = $group_dir . $group_name . '.json';
        if (file_exists($filename)) {
            try {
                $myJson[$group_name] = array();

                $fp = fopen($filename, "r");
                $content = fread($fp, filesize($filename));
                fclose($fp);

                $data = json_decode($content, true);
                array_push($myJson[$group_name], $data);

                header('Content-Type: application/json');
                echo json_encode($myJson, JSON_PRETTY_PRINT);
                return $myJson;
            } catch (Exception $exception) {
                verbose(0, 'Error occurred while reading group information');
            }
        } else {
            verbose(2, 'Specified group doesn\'t exist !');
        }
    } else {
        verbose(0, 'Fatal error, the group container doesn\'t exist');
    }
    return false;
}

function addGroupMember()
{
    global $lv;
    global $group_dir;

    $group_name = $_POST['group_name'];
    $uuid = $_POST['uuid'];

    $domName = $lv->domain_get_name_by_uuid($uuid);

    if (is_dir($group_dir)) {
        $filename = $group_dir . $group_name . '.json';
        if (file_exists($filename)) {
            if ($domName) {
                try {
                    $jsonFileData = file_get_contents($filename); //Reading json file content
                    $tempArray = json_decode($jsonFileData, true); //Decoding json content into array
                    if ($foundname = findUUIDGroup($uuid)) { //Check if the uuid is already in the uuid array
                        verbose(0, 'This UUID is already in the group: ' . $foundname);
                    } else {
                        array_push($tempArray, $uuid); // Pushing new data to json array
                        $jsonData = json_encode($tempArray); // Re-encoding file
                        file_put_contents($filename, $jsonData); // Writing changes into file
                        verbose(1, 'The uuid: ' . $uuid . ' has been added to group: ' . $group_name);
                    }
                } catch (Exception $exception) {
                    verbose(0, 'Error occurred while writing new content');
                }
            } else {
                verbose(0, 'This UUID doesn\'t exist !');
            }
        } else {
            verbose(0, 'Specified group doesn\'t exist !');
        }
    } else {
        verbose(0, 'Fatal error, the group container doesn\'t exist');
    }
}

function deleteGroupMember($group_name, $uuid)
{
    global $group_dir;
    $called = false;
    if(!isset($group_dir)) {
        $group_dir = __DIR__ . DIRECTORY_SEPARATOR . 'groups' . DIRECTORY_SEPARATOR;
        $called = true;
    }
    if (is_dir($group_dir)) {
        $filename = $group_dir . $group_name . '.json';
        if (file_exists($filename)) {
            try {
                $jsonFileData = file_get_contents($filename);
                $tempArray = json_decode($jsonFileData, true);
                if (($key = array_search($uuid, $tempArray)) !== false) {
                    unset($tempArray[$key]);
                    $tempArray = array_values($tempArray);
                    $jsonData = json_encode($tempArray);
                    file_put_contents($filename, $jsonData);
                    if(!$called) {
                        verbose(1, 'The uuid: ' . $uuid . ' has been removed from group: ' . $group_name);
                    }else{
                        return true;
                    }
                } else {
                    verbose(0, 'This uuid isn\'t in this group !');
                }
            } catch (Exception $exception) {
                verbose(0, "Unknow error occurred !");
            }
        } else {
            verbose(0, 'Specified group doesn\'t exist !');
        }
    } else {
        verbose(0, 'Fatal error, the group container doesn\'t exist');
    }
}

function findUUIDGroup($uuid)
{
    $files = glob('groups/*.{json}', GLOB_BRACE);
    if ($files) {
        foreach ($files as $file) {
            $fp = fopen($file, "r"); // Opening file in read mode
            $content = fread($fp, filesize($file)); //Getting content
            fclose($fp); //Closing file

            $data = json_decode($content, true); //Decoding json
            if (in_array($uuid, $data)) {
                return basename($file, '.json');
            }
        }
    }
    return false;
}
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    addCors();
    switch ($request_method) {
        case 'GET':
            // Retrive VM state
            if ($_GET['group_name']) {
                getGroupMembers($_GET['group_name']);
            } else {
                getGroupsMembers();
            }
            break;
        case 'POST':
            addGroupMember();
            break;
        case 'DELETE':
            $group_name = $_GET["group_name"];
            $uuid = $_GET["uuid"];
            deleteGroupMember($group_name, $uuid);
            break;
        case 'PUT':
            var_dump(findUUIDGroup($_GET['uuid']));
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>