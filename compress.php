<?php
set_time_limit(600);
ignore_user_abort(true);

// paradox appids that need descriptior proccessing and installer file:
$paradox_appids = [394360, 236850, 203770, 1158310, 42960, 529340, 281990, 859580];

if (!(isset($argv[1]) && isset($argv[2]))) {
    die('Missing required arguments: app_id, file_id');
}

$app_id = $argv[1];
$file_id = $argv[2];

$mod_path = "workshop/{$app_id}/{$file_id}";

if (!is_dir($mod_path)) {
    die('Mod path not found');
}

if (!is_file("compressed/{$file_id}_incomplete.zip")) {
    die('Incomplete file not found');
}

// process descriptor.mod file and include installer for paradox game mods:
$installer_path = "";
if (in_array((int) $app_id, $paradox_appids))
{
    $descriptor_found = false;
    $descriptor_filename = "descriptor.mod";
    if (is_file($mod_path . $descriptor_filename))
    {
        $descriptor_found = true;
    }
    else
    {
        $dir_handle = opendir($mod_path);
        while (false !== ($file = readdir($dir_handle))) {
          if (pathinfo($file, PATHINFO_EXTENSION) == "mod") {
            $descriptor_filename = $file;
            $descriptor_found = true;
            break;
          }
        }
        closedir($dir_handle);
    }

    if (!$descriptor_found)
    {
        $archive_found = false;
        $dir_handle = opendir($mod_path);
        while (false !== ($file = readdir($dir_handle))) {
            if (pathinfo($file, PATHINFO_EXTENSION) == "zip") {
                $archive_filename = $file;
                $archive_found = true;
                break;
            }
        }
        closedir($dir_handle);
        if ($archive_found)
        {
            exec("7z e {$mod_path}/{$archive_filename} *.mod -o{$mod_path}");
        }
    }

    // add extra information to the descriptor.mod file:
    $descriptor_path = $mod_path . "/{$descriptor_filename}";
    $descriptor_text = file_get_contents($descriptor_path);
    if (strpos($descriptor_text, "appid=") === false)
    {
        file_put_contents($descriptor_path, "\nappid=\"{$app_id}\"", FILE_APPEND);
    }
    if (strpos($descriptor_text, "name=") === false)
    {
        file_put_contents($descriptor_path, "\nname=\"{$file_id}\"", FILE_APPEND);
    }
    $installer_path = "ModInstaller.exe";
}

// remove temp final file
unlink("compressed/{$file_id}_incomplete.zip");
while (file_exists("compressed/{$file_id}_incomplete.zip"))
{
    usleep(1000);
}

// prepare 7z command - include installer file directly without linking if needed:
$zip_command = "timeout 600s 7z a -tzip compressed/{$file_id}_incomplete.zip {$mod_path}/ -xr!.git -mx1 -bsp1 -bso1 -l";
if ($installer_path)
{
    $zip_command = "timeout 600s 7z a -tzip compressed/{$file_id}_incomplete.zip {$mod_path}/ {$installer_path} -xr!.git -mx1 -bsp1 -bso1 -l";
}

// start compressing then rename the archive:
exec("{{$zip_command} ; mv compressed/{$file_id}_incomplete.zip compressed/{$file_id}.zip; } > status/{$file_id}");
?>