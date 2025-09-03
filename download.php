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

    // Send HTML to browser and start background compression
    flush();

    if (!is_file("compressed/{$file_id}_incomplete.zip"))
    {
        // touch the final file to prevent concurrent compression:
        touch("compressed/{$file_id}_incomplete.zip");

        // get timestamp for later restoration
        $timestamp = exec("stat -c %y {$mod_path}");

        // start background compression using separate script - runs completely in background
        exec("nohup php compress.php {$app_id} {$file_id} \"{$timestamp}\" > /dev/null 2>&1 &");
    }
    
    // Request completes immediately, compression continues in background
    die();
}
else
{
    http_response_code(404);
    die('Mod Not Found');
}
?>
