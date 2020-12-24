<?php
require_once(dirname(__FILE__) . "/../libconnect.php");
/**
 * Returns a json response containing the volumes of the given pool name.
 * @param $pool
 * @api
 */
function getVolumes($pool)
{
    global $lv;
    $myJson = array();
    try {
        $current_pool = $lv->storagepool_get_volume_information($pool);
        if (isset($current_pool)) {
            $pool_keys = array_keys($current_pool);
            for ($i = 0; $i < sizeof($current_pool); $i++) {
                $volume_name = $pool_keys[$i];
                $volume_type = $lv->translate_volume_type($current_pool[$pool_keys[$i]]['type']);
                $volume_capacity = $lv->format_size($current_pool[$pool_keys[$i]]['capacity'], 2);
                $volume_allocation = $lv->format_size($current_pool[$pool_keys[$i]]['allocation'], 2);
                $volume_path = $current_pool[$pool_keys[$i]]['path'];

                $result = array();
                $result['volume_name'] = $volume_name;
                $result['volume_type'] = $volume_type;
                $result['volume_capacity'] = $volume_capacity;
                $result['volume_allocation'] = $volume_allocation;
                $result['volume_path'] = $volume_path;
                array_push($myJson, $result);
            }
            if (empty($myJson)) {
                verbose(2, 'This pool is empty !');
            }
        } else {
            verbose(0, 'This pool doesn\'t exist !');
        }
    } catch (Exception $e) {
        verbose(0, $lv->get_last_error());
    }
    header('Content-Type: application/json');
    echo json_encode($myJson, JSON_PRETTY_PRINT);
}

/**
 * Deletes a volume inside the image folder by name.
 * Returns a json response.
 * @api
 * @param $name
 */
function deleteVolume($name)
{
    global $lv;
    $filePath = '/var/lib/libvirt/images/';
    if ($lv->storagevolume_delete($filePath . $name)) {
        $lv->storagepool_refresh('images');
        verbose(1, 'The volume has been deleted !');
    } else {
        verbose(0, 'Error while deleting the volume');
    }
}

/**
 * Uploads a volume by chunk each chunk is added to the file, the client defines the chunk size.
 * It creates a .part and each time this function is called, a chunk is added to the file.
 * Uploads the file in the images folder of libvirt.
 * Returns a json response.
 * @api
 */
function uploadVolume()
{
    global $lv;
    // (B) INVALID UPLOAD
    if (empty($_FILES) || $_FILES['file']['error']) {
        verbose(0, "Failed to move uploaded file.");
    }
    // (C) UPLOAD DESITINATION
    // ! CHANGE FOLDER IF REQUIRED !
    $filePath = '/var/lib/libvirt/images/';
    if (!file_exists($filePath)) {
        if (!mkdir($filePath, 0777, true)) {
            verbose(0, "Failed to create $filePath");
        }
    }

    // Remove old part files	
    array_map('unlink', glob("$filePath*.part"));

    $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"];
    $filePath = $filePath . DIRECTORY_SEPARATOR . $fileName;

    // (D) DEAL WITH CHUNKS
    $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
    $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
    $out = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
    if ($out) {
        $in = @fopen($_FILES['file']['tmp_name'], "rb");
        if ($in) {
            while ($buff = fread($in, 4096)) {
                fwrite($out, $buff);
            }
        } else {
            verbose(0, "Failed to open input stream");
        }
        @fclose($in);
        @fclose($out);
        @unlink($_FILES['file']['tmp_name']);
    } else {
        verbose(0, "Failed to open output stream");
    }

    // (E) CHECK IF FILE HAS BEEN UPLOADED
    if (!$chunks || $chunk == $chunks - 1) {
        rename("{$filePath}.part", $filePath);
        $lv->storagepool_refresh('images');
        verbose(1, "Upload OK");
    }
}

/**
 * Clones a volume, with volume_name as the volume being cloned.
 * Parameter template_name is optional, used in case a template wants to clone its disk.
 * Returns a json response if template_name is empty, else it returns true on success or nothing (because the script die on error).
 * @api
 * @param $template_name
 * @param $volume_name
 * @param $new_volume_name
 * @return bool
 */
function cloneVolume($template_name, $volume_name, $new_volume_name)
{
    global $lv;
    $destFilePath = '/var/lib/libvirt/images/';
    $originFilePath = $destFilePath;
    $logFilePath = dirname(__FILE__) . "/../../";

    if (!empty($template_name)) {
        $originFilePath = '/var/lib/libvirt/img_templates/' . $template_name . DIRECTORY_SEPARATOR;
    }

    if ($volume_name === $new_volume_name) {
        verbose(0, 'Volume name and new volume name can\'t be the same');
    }
    if ($lv->storagepool_get_volume_information('images', $volume_name) || !empty($template_name)) {
        if (file_exists($destFilePath . $new_volume_name)) {
            verbose(0, 'The specified new volume name is already taken !');
        }
        $remote = fopen($originFilePath . $volume_name, 'r') or verbose(0, 'Can\'t open specified volume');
        $local = fopen($destFilePath . $new_volume_name, 'w') or verbose(0, 'Can\'t create specified volume');
        if (!empty($template_name)) {
            $progresstracker = fopen($logFilePath . $volume_name . 'copyprogress.txt', 'w') or verbose(0, 'Can\'t create progress tracking');
        } else {
            $progresstracker = fopen($logFilePath . $volume_name . $new_volume_name . 'copyprogress.txt', 'w') or verbose(0, 'Can\'t create progress tracking');
        }
        $read_bytes = 0;
        $previousprogress = 0;
        while (!feof($remote)) {
            $buffer = fread($remote, 16384);
            fwrite($local, $buffer);

            $read_bytes += 16384;

            //Use $filesize as calculated earlier to get the progress percentage
            $progress = round(min(100, 100 * $read_bytes / filesize($originFilePath . $volume_name))); // progress in percentage
            if ($progress != $previousprogress) {
                $text = $progress . '%' . PHP_EOL;
                fwrite($progresstracker, $text); // Writing advancement of cloning inside a file so it can be readed by getVolumeProgress.
                $previousprogress = $progress;
            }
        }
        fclose($remote);
        fclose($local);
        fclose($progresstracker);
        sleep(5);
        if (!empty($template_name)) {
            unlink($logFilePath . $volume_name . 'copyprogress.txt');
        }else{
            unlink($logFilePath . $volume_name . $new_volume_name . 'copyprogress.txt');
        }
        $lv->storagepool_refresh('images');
        if (empty($template_name)) {
            verbose(1, 'The volume ' . $volume_name . ' has been copied as ' . $new_volume_name);
        }
    } else {
        verbose(0, 'The specified volume doesn\'t exist!');
    }
    return true;
}

/**
 * Reads the advancement tracking file to get the volume copy progress.
 * Returns the percentage of the copy.
 * @api
 * @param $filename
 */
function getVolumeProgress($filename)
{
    $logFilePath = dirname(__FILE__) . "/../../"; //
    $line = '';
    $f = fopen($logFilePath . $filename, 'r') or verbose(0, 'Error while opening file');
    $cursor = -1;
    fseek($f, $cursor, SEEK_END); // Reading only the last line (where the last percentage is)
    $char = fgetc($f);
    //Trim trailing newline characters in the file
    while ($char === "\n" || $char === "\r") {
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }
    //Read until the next line of the file begins or the first newline char
    while ($char !== false && $char !== "\n" && $char !== "\r") {
        //Prepend the new character
        $line = $char . $line;
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }
    verbose(1, $line);
}
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    addCors();
    switch ($request_method) {
        case 'GET':
            // Retrive VM state
            if (!empty($_GET['pool'])) {
                $pool = $_GET["pool"];
                getVolumes($pool);
            }
            if (!empty($_GET['filename'])) {
                $filename = $_GET["filename"];
                getVolumeProgress($filename);
            }
            break;
        case 'POST':
            if (isset($_POST['action'])) {
                if ($_POST['action'] === 'clone') {
                    cloneVolume($_POST['template_name'], $_POST['volume_name'], $_POST['new_volume_name']);
                }
            } else {
                uploadVolume();
            }
            break;
        case 'DELETE':
            if (!empty($_GET['name'])) {
                $name = $_GET["name"];
                deleteVolume($name);
            }
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>