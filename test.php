<?php
// Define the URL
$url = "https://learn.lianglianglee.com/";

# 1 获取文件主目录
// $response = file_get_contents($url);
// if ($response === FALSE) {
//     echo "Failed to access the URL.";
// } else {
//     // Use regex to find the href values across multiple lines (with the 's' modifier)
//     preg_match_all('/<li><a href="([^"]*)">([^<]*)<\/a><\/li>/', $response, $matches);
    
//     // Prepare the output for the readme file
//     $output = "";
//     if (!empty($matches[1])) {
//         foreach ($matches[1] as $href) {
//             $output .= $href.PHP_EOL;
//         }
//     } else {
//         $output = "No match found.\n";
//     }

//         // Write the results to readme.txt file
//     file_put_contents("README.md", $output);
    
//     echo "Results saved to readme.txt";
// }

# 2 生成不同目录的文件夹

// Path to the README.md file
$readmePath = 'README.md';

// Read the file into an array of lines
$lines = file($readmePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    // Remove spaces from each line
    $folderName = str_replace(' ', '', $line);
    $folderName = "/Users/01397713/Documents/github/learn-tech".$folderName;
    
    $line = str_replace(' ', '%20', $line);
    $curlUrl = $url. $line;
    echo $curlUrl.PHP_EOL;
}