<?php
require_once(dirname(__FILE__) . '/../libvirt.php');

$lv = new Libvirt();
$lv->connect('qemu:///system');

// (A) FUNCTION TO FORMULATE SERVER RESPONSE
function verbose($status = 1, $message = "")
{
    // THROW A 400 ERROR ON FAILURE
    if ($status == 0) {
        http_response_code(400);
    }
    header('Content-Type: application/json');
    die(json_encode(["status" => $status, "status_message" => $message]));
}

function createFolder($path, $dirname)
{

    $filename = $path . $dirname . DIRECTORY_SEPARATOR;

    if (!file_exists($filename)) {
        return mkdir($path . $dirname, 0777);
    } else {
        return false;
    }
}

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