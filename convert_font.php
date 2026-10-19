<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('C:/inetpub/wwwroot/it_request/libs/TCPDF-main/tcpdf.php');

$font_path = 'C:/inetpub/wwwroot/it_request/libs/TCPDF-main/fonts/THSarabunNew/THSarabunNew-Bold.ttf';

try {
    if (file_exists($font_path)) {
        echo "Font file found at: " . $font_path . "<br>";
        $fontname = TCPDF_FONTS::addTTFfont($font_path, 'TrueTypeUnicode', '', 32);
        if ($fontname === false) {
            echo "Error: Failed to convert font. Check font file compatibility or corruption.";
        } else {
            echo "Font added successfully: " . $fontname;
        }
    } else {
        echo "Error: Font file not found at " . $font_path;
    }
} catch (Exception $e) {
    echo "Exception occurred: " . $e->getMessage();
}
?>