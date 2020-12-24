<?php
/*
 * CIFTCI Semih Copyright (c) 2020. All rights reserved.
 */

require_once(dirname(__FILE__) . '/../libvirt.php');

$lv = new Libvirt();
$lv->connect('qemu:///system');

/**
 * Function to send status messages as json.
 * Supported status by my client are 0,1,2. 0 is error, 1 is success and 2 is warning.
 * Dies as it sends message.
 * @param int $status
 * @param string $message
 * @api
 */
function verbose($status = 1, $message = "")
{
    // THROW A 400 ERROR ON FAILURE
    if ($status == 0) {
        http_response_code(400);
    }
    header('Content-Type: application/json');
    die(json_encode(["status" => $status, "status_message" => $message]));
}

/**
 * This function is used to allow everyone to do cross site requests.
 */
function addCors(){
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: token, Content-Type');
        header('Access-Control-Max-Age: 1728000');
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        die();
    }
    header('Access-Control-Allow-Origin: *');
}

/**
 * Creates a folder for the given path and directory name.
 * @param $path
 * @param $dirname
 * @return bool
 */
function createFolder($path, $dirname)
{

    $filename = $path . $dirname . DIRECTORY_SEPARATOR;

    if (!file_exists($filename)) {
        return mkdir($path . $dirname, 0777);
    } else {
        return false;
    }
}

/**
 * Recursively deletes a folder and it's contents for the given directory, use it with caution.
 * @param $dir
 */
function deleteDir($dir)
{
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it,
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dir);
}


?>