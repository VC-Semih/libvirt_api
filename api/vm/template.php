<?php
/*
 * CIFTCI Semih Copyright (c) 2020. All rights reserved.
 */

require_once(dirname(__FILE__) . "/../libconnect.php");
/**
 * Returns json response with all templates (template name, description, disk name)
 * @api
 */
function getTemplates()
{
    $template_path = '/var/lib/libvirt/img_templates/';
    $myJson = array();
    foreach (array_diff(scandir($template_path), array('..', '.')) as $element) {
        if (is_dir($template_path . $element)) {
            $result['template_name'] = $element;
            $currentPath = $template_path . $element . DIRECTORY_SEPARATOR;
            if (file_exists($currentPath . 'description_' . $element . '.json')) {
                $result['template_description'] = json_decode(file_get_contents($currentPath . 'description_' . $element . '.json'));
                if (file_exists($currentPath . 'template_' . $element . '.qcow2')) {
                    $result['template_volume_name'] = 'template_' . $element . '.qcow2';

                } else {
                    $result['template_volume_name'] = '[ERROR] Cannot get volume template file';
                }
            } else {
                $result['template_description'] = '[ERROR] Cannot get template description file';
            }
            array_push($myJson, $result);
        }
    }
    if (empty($myJson)) verbose(2, 'No templates are available');
    header('Content-Type: application/json');
    echo json_encode($myJson, JSON_PRETTY_PRINT);
}

/**
 * Creates a template with given posts
 * Posts: template_name, template_volume_name, template_description, template_cpu, template_ram.
 * Returns json response.
 * @api
 */
function createTemplate()
{
    global $lv;

    $template_path = '/var/lib/libvirt/img_templates/';
    $volume_path = '/var/lib/libvirt/images/';

    $template_name = $_POST['template_name'];
    $template_volume_name = $_POST['template_volume_name'];
    $template_description['template_description'] = $_POST['template_description'];
    $template_description['template_cpu'] = $_POST['template_cpu'];
    $template_description['template_ram'] = $_POST['template_ram'];

    if (createFolder($template_path, $template_name)) {
        if (file_exists($volume_path . $template_volume_name)) {
            if (rename($volume_path . $template_volume_name, $template_path . $template_name . DIRECTORY_SEPARATOR . 'template_' . $template_name . '.qcow2')) {
                $lv->storagepool_refresh('images');
                $description_file = fopen($template_path . $template_name . DIRECTORY_SEPARATOR . 'description_' . $template_name . '.json', 'w') or verbose(0, 'Can\'t create description file');
                fwrite($description_file, json_encode($template_description));
                fclose($description_file);
                verbose(1, 'Your template has been created as: ' . $template_name);
            } else {
                deleteDir($template_path . $template_name);
                verbose(0, 'Failed to move volume !');
            }
        } else {
            deleteDir($template_path . $template_name);
            verbose(2, 'Specified volume doesn\'t exist !');
        }
    } else {
        verbose(0, 'Specified template already exists !');
    }
}

/**
 * Removes a template with the given name.
 * Returns json response.
 * @param $name
 * @api
 */
function deleteTemplate($name)
{
    $template_path = '/var/lib/libvirt/img_templates/';
    $dir = $template_path . $name;
    if (is_dir($dir)) {
        deleteDir($dir);
        verbose(1, 'Template ' . $name . ' has been deleted');
    } else {
        verbose(0, 'This template doesn\'t exist !');
    }
}

/**
 * Deploys a template with the given name.
 * Returns json response.
 * @api
 * @param $template_name
 */
function deployTemplate($template_name)
{
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'volume.php'); // Need volume php to transfer the volume
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vm.php'); // Need vm php to create the vm

    $templates_path = '/var/lib/libvirt/img_templates/';
    $volume_path = '/var/lib/libvirt/images/';


    if (is_dir($templates_path . $template_name)) {
        $current_template_path = $templates_path . $template_name . DIRECTORY_SEPARATOR;
        if (file_exists($current_template_path . 'description_' . $template_name . '.json')) {
            $description = json_decode(file_get_contents($current_template_path . 'description_' . $template_name . '.json'), true);
            if (file_exists($current_template_path . 'template_' . $template_name . '.qcow2')) {
                $template_volume_name = 'template_' . $template_name . '.qcow2';
                $new_volume_name = $template_name . '.qcow2';
                $testedname = $new_volume_name;
                $number = 0;
                while (file_exists($volume_path . $testedname)) { //Searching for a unused name
                    $number++;
                    $testedname = $new_volume_name;
                    $testedname = $number . '_' . $testedname;
                }
                $new_volume_name = $testedname;
                cloneVolume($template_name, $template_volume_name, $new_volume_name); // Calling function clone Volume from volume.php
                $domName = explode('.', $new_volume_name)[0];
                addVM($domName, $description['template_ram'], $volume_path . $new_volume_name, $description['template_cpu']); // Calling function addVm from vm.php
            } else {
                verbose(0, 'Cannot find template volume !');
            }
        }else{
            verbose(0, 'Cannot find template description file !');
        }
    } else {
        verbose(0, 'This template doesn\'t exist !');
    }

}

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) { // Check if the link corresponds to the file (and not another api that made require() )
    $request_method = $_SERVER['REQUEST_METHOD'];
    addCors();
    switch ($request_method) {
        case 'GET':
            getTemplates();
            break;
        case 'POST':
            if ($_POST['action'] == 'create') {
                createTemplate();
            } else if ($_POST['action'] == 'deploy') {
                deployTemplate($_POST['template_name']);
            }
            break;
        case 'DELETE':
            if (!empty($_GET['name'])) {
                deleteTemplate($_GET["name"]);
            }
            break;
        default:
            // Invalid Request Method
            header("HTTP/1.0 405 Method Not Allowed");
            break;
    }
}
?>