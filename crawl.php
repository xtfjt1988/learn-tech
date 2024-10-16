<?php
// Define the URL
$url = "https://learn.lianglianglee.com";

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
    $curlUrl = $url . $line;

    $response = file_get_contents($curlUrl);
    mkdir($folderName, 0777, true);
    preg_match_all('/<a class="menu-item" id="([^"]*)" href="([^"]*)">([^<]*)<\/a>/', $response, $matches);

    if (isset($matches[1])) {
        $fileNameList = $matches[1];
        $urlList = $matches[2];

        foreach($fileNameList as $key => $name) {

            $fileName = str_replace(' ', '', $name);
            $fileName = $folderName . '/'. $fileName;

            echo $fileName;
            echo PHP_EOL;

            if(filesize($fileName) > 0) continue; 

            $fileUlr = $url . $urlList[$key];
            $fileContents = file_get_contents($fileUlr);

            preg_match_all('/<div class="book-post">(.*?)<div id="prePage" style="float: left">/s', $fileContents, $divMatchs);

            $a = '<meta charset="UTF-8">'.$divMatchs[1][0];
            $doc = new DOMDocument();
            libxml_use_internal_errors(true); // To handle any invalid HTML
            $doc->loadHtml($a);
            libxml_clear_errors();
            $text = $doc->textContent;

            file_put_contents($fileName, $text);

            sleep(5);
            
            // preg_match_all('/<p>([^<]*)<\/p>/', $fileContents, $fileMatches);
        }
    }
}