<?php
$content = file_get_contents('login.php');
// Replace all smart quotes with straight quotes
// UTF-8 encoding: E2 80 9C is " and E2 80 9D is "
$content = str_replace("\xE2\x80\x9C", '"', $content);
$content = str_replace("\xE2\x80\x9D", '"', $content);
file_put_contents('login.php', $content);
echo "File fixed!\n";
?>
