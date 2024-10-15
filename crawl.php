<?php
// Define the URL
$url = "https://learn.lianglianglee.com/";

// Send the GET request
$response = file_get_contents($url);

if ($response === FALSE) {
    echo "Failed to access the URL.";
} else {
    // Use regex to find the href values across multiple lines (with the 's' modifier)
    preg_match_all('/<li><a href="([^"]*)">([^<]*)<\/a><\/li>/', $response, $matches);
    
    // Prepare the output for the readme file
    $output = "";
    if (!empty($matches[1])) {
        foreach ($matches[1] as $href) {
            $output .= $href.PHP_EOL;
        }
    } else {
        $output = "No match found.\n";
    }

        // Write the results to readme.txt file
    file_put_contents("README.md", $output);
    
    echo "Results saved to readme.txt";
}
?>
