<?php
$file = 'add_project.php';
$content = file_get_contents($file);
$css = file_get_contents('select2.min.css');

$search = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">';
$replace = "<style>\n" . $css . "\n</style>";

if (strpos($content, $search) !== false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Replaced successfully!";
} else {
    echo "Could not find the link tag.";
}
