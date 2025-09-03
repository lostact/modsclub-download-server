<?php

$file_id = $_GET['file_id'];

if (!isset($file_id)) {
	echo '0';
	die();
}
$status_file_path = "status/{$file_id}";


$text = file_get_contents($status_file_path);
preg_match('/Server is busy/', $text, $matches);
if ($matches) {
    echo 'busy';
    die();
}
preg_match('/Everything is Ok/', $text, $matches);
if ($matches) {
	echo '100';
	die();
}

preg_match('/(\d+)%[^%]+$/', $text, $matches);
if (isset($matches[1])) {
	echo $matches[1];
}
else
{
	echo '0';
}

?>
