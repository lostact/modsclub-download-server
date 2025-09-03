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
            // extract to temp location since workshop is readonly
            exec("7z e {$mod_path}/{$archive_filename} *.mod -otemp_extract_{$file_id}");
            // find the extracted descriptor file
            $temp_extract_dir = "temp_extract_{$file_id}";
            if (is_dir($temp_extract_dir)) {
                $dir_handle = opendir($temp_extract_dir);
                while (false !== ($file = readdir($dir_handle))) {
                    if (pathinfo($file, PATHINFO_EXTENSION) == "mod") {
                        $descriptor_filename = $file;
                        $descriptor_found = true;
                        // move to main temp location for processing
                        rename("{$temp_extract_dir}/{$file}", "temp_extracted_descriptor_{$file_id}.mod");
                        break;
                    }
                }
                closedir($dir_handle);
                // cleanup temp extract directory
                exec("rm -rf {$temp_extract_dir}");
            }
        }
    }

    // create a temporary modified descriptor file since workshop is readonly:
    $temp_descriptor_path = "temp_descriptor_{$file_id}.mod";
    
    if ($descriptor_found) {
        // check if we extracted it or it exists in the original location
        $source_descriptor = file_exists("temp_extracted_descriptor_{$file_id}.mod")
            ? "temp_extracted_descriptor_{$file_id}.mod"
            : $mod_path . "/{$descriptor_filename}";
        
        $descriptor_text = file_get_contents($source_descriptor);
        
        // add extra information to the descriptor content:
        if (strpos($descriptor_text, "appid=") === false)
        {
            $descriptor_text .= "\nappid=\"{$app_id}\"";
        }
        if (strpos($descriptor_text, "name=") === false)
        {
            $descriptor_text .= "\nname=\"{$file_id}\"";
        }
        
        // write the modified descriptor to temp location
        file_put_contents($temp_descriptor_path, $descriptor_text);
        
        // cleanup extracted descriptor if it exists
        if (file_exists("temp_extracted_descriptor_{$file_id}.mod")) {
            unlink("temp_extracted_descriptor_{$file_id}.mod");
        }
    }
    
    $installer_path = "ModInstaller.exe";
}

// remove temp final file
unlink("compressed/{$file_id}_incomplete.zip");
while (file_exists("compressed/{$file_id}_incomplete.zip"))
{
    usleep(1000);
}

// prepare 7z command with proper folder structure - everything inside file_id folder:
$zip_command = "timeout 600s 7z a -tzip compressed/{$file_id}_incomplete.zip -xr!.git -mx1 -bsp1 -bso1 -l";

// add mod contents to file_id folder (exclude original descriptor if we have a modified one)
if ($descriptor_found) {
    $zip_command .= " -x!{$descriptor_filename}";
}
$zip_command .= " {$mod_path}/*={$file_id}/";

// add installer to file_id folder if needed
if ($installer_path) {
    $zip_command .= " {$installer_path}={$file_id}/ModInstaller.exe";
}

// add modified descriptor with correct name to file_id folder if we have one
if ($descriptor_found && file_exists($temp_descriptor_path)) {
    $zip_command .= " {$temp_descriptor_path}={$file_id}/descriptor.mod";
}

// start compressing then rename the archive and cleanup temp files:
$cleanup_cmd = "";
if ($installer_path && $descriptor_found && file_exists($temp_descriptor_path))
{
    $cleanup_cmd = " rm -f {$temp_descriptor_path} ;";
}
exec("{ " . $zip_command . " ; mv compressed/{$file_id}_incomplete.zip compressed/{$file_id}.zip ;" . $cleanup_cmd . " } > status/{$file_id}");
?>