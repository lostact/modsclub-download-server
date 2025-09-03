<?php
set_time_limit(600);
ignore_user_abort();

// paradox appids that need descriptior proccessing and installer file:
$paradox_appids = [394360, 236850, 203770, 1158310, 42960, 529340, 281990, 859580];

if (!(isset($_GET['app_id']) && isset($_GET['file_id']) && isset($_GET['access_key']))) {
   http_response_code(400);
   die('Bad Request');
}

$app_id = $_GET['app_id'];
$file_id = $_GET['file_id'];
$access_key = $_GET['access_key'];

// check if user has access to this mod:
$data = ['access_key' => $access_key, 'file_id' => $file_id];
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, "https://modsclub.ir/wp-json/steam_mods/authenticate");
curl_setopt($ch,CURLOPT_POST, true);
curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']	);
$response = curl_exec($ch);
$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$access_granted = false;
if ($response_code)
{
    if ($response_code == 200)
	{
	   $access_granted = true;
	}
}
if (!$access_granted)
{
    http_response_code(403);
    die('Access Denied');
}

$mod_path = "workshop/{$app_id}/{$file_id}";
$file_path = "compressed/{$file_id}.zip";
if (is_file($file_path)) 
{
    header('Content-Type: application/octet-stream');
    header('Content-disposition: attachment; filename="' . $file_id . '.zip"');
    header("X-Accel-Redirect: /{$file_path}");
    die();
}

if (is_dir($mod_path))
{
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-'>
        <title>دانلود فایل</title>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js'></script>
        <link rel='stylesheet' type='text/css' href='assets/styles.css' media='screen' />
        <title></title>
    </head>
        <body style="direction: rtl;font-family: Vazir">
            <h1>در حال فشرده سازی فایل</h1>
            <div id='progress_bar' class='progress-bar' style='--progress: 0%;direction: ltr;'>
                <span id='progress_bar_text'>0%</span>
            </div>
            <p id='wait_text'>فشرده سازی در حال انجام است لطفا منتظر باشید...</p>
            <a id='download_link' href='#' class='download-link'>دانلود</a>
        </body>
    </html>

    <script type='text/javascript'>
        var status_file_url = '/get_status.php?file_id=' + '<?php echo $file_id;?>';
        var check_timer = setInterval(check_function, 2000);
        check_function();
        function check_function() {
            jQuery.get(status_file_url, function(progress_text) 
            {
                if (progress_text == "busy")
                {
                    progress_text = 0;
                    document.getElementById('wait_text').innerHTML = 'سرور مشغول است لطفا صبر کنید...';
                }
                else
                {
                    document.getElementById('wait_text').innerHTML = 'فشرده سازی در حال انجام است لطفا منتظر باشید...';
                }
                document.getElementById('progress_bar').style.setProperty('--progress', progress_text + '%');
                document.getElementById('progress_bar_text').innerHTML = progress_text + '%';
                if (progress_text == '100')
                {
                    // document.getElementById('result-status').innerHTML = pre_text + '<strong>' + 'تمام!' + '</strong>';
                    document.getElementById('download_link').href = document.URL;
                    document.getElementById('download_link').style.display = 'inline-block';
                    document.getElementById('wait_text').style.display = 'none';
                    clearInterval(check_timer);
                    location.reload();
                }
            });
        }
    </script>
    <?php

    ignore_user_abort(true);

    flush();
    fastcgi_finish_request();

    if (!is_file("compressed/{$file_id}_incomplete.zip"))
    {
        // touch the final file to prevent concurrent compression:
        touch("compressed/{$file_id}_incomplete.zip");

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

            // we dont want the target mod folder timestamps to change so get a backup of timestamp to reapply it later:
            $timestamp = exec("stat -c %y {$mod_path}");
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

        // start compressing then rename the archive and reset target mod folder timestamps:
        exec("{{$zip_command} ; touch -d \"{$timestamp}\" {$mod_path} ; mv compressed/{$file_id}_incomplete.zip compressed/{$file_id}.zip; } > status/{$file_id} &");
        die();
    }
}
else
{
    http_response_code(404);
    die('Mod Not Found');
}
?>
