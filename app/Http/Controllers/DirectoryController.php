<?php


namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Orhanerday\OpenAi\OpenAi;

class DirectoryController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function index()
    {
        function countTokens($code)
        {
            // Remove comments and whitespace
            $code = removeComments($code);
            $code = preg_replace('/\s+/', ' ', $code);

            // Count tokens
            $tokens = str_word_count($code);

            return $tokens;
        }
        function removeComments($code)
        {
            $code = preg_replace('/\/\*.*?\*\//s', '', $code); // Remove /* ... */ comments
            $code = preg_replace('/\/\/.*?$\R?/m', '', $code); // Remove // ... comments

            return $code;
        }


        function splitCodeIntoParts($code, $maxTokens)
        {
            $parts = [];
            $currentPart = '';
            $tokenCount = 0;

            $lines = explode("\n", $code);
            foreach ($lines as $line) {
                $lineTokenCount = countTokens($line);

                if ($tokenCount + $lineTokenCount <= $maxTokens) {
                    $currentPart .= $line . "\n";
                    $tokenCount += $lineTokenCount;
                } else {
                    $parts[] = $currentPart;
                    $currentPart = $line . "\n";
                    $tokenCount = $lineTokenCount;
                }
            }

            // Add the last part
            $parts[] = $currentPart;

            return $parts;
        }

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 'http://localhost:8081/JSON/alert/view/alertsByRisk/?apikey=qe18gslcka4doa484bq7sb97ri&url=&recurse=');
        $data = json_decode($response->getBody());
        // dd($data->alertsByRisk);
        $data = $data->alertsByRisk[2];
        // dd($data);
        $urls = $this->extractUrls($data);
        // dd($urls);
        foreach ($urls as $url) {
            // var_dump($url);
            $params = parse_url($url, PHP_URL_QUERY);
            parse_str($params, $query);
            $urlParts = parse_url($url);
            // dd($urlParts);
            // var_dump($urlParts);
            if (isset($urlParts['path'])) {
                $urlpath = $urlParts['path'];
                $index = "index.php";
                if (strpos($urlParts['path'], ".php") === false) {

                    $filePath = "/var/www/html$urlpath$index";
                    // dd($filePath);
                    // $subDirectories = glob($filePath . '/*', GLOB_ONLYDIR); 
                    // dd($subDirectories);
                    // if (($subDirectories)){

                        $fileContents = file_get_contents($filePath);

                        // Define the regular expression pattern
                        $pattern = "/require_once\s+DVWA_WEB_PAGE_TO_ROOT\s*\.\s*\"(.*?)\";/";

                        // Perform preg_match on the file contents
                        if (preg_match($pattern, $fileContents, $matches)) {
                            $filePath = $matches[1];
                            $directoryPath = dirname("/var/www/html/$filePath");
                            // dd($directoryPath);
                            // echo "Directory Path: " . $directoryPath;
                            $inputFiles = $this->getFilesRecursively($directoryPath);
                        } else {
                            echo "Directory Path not found.";
                        // }
                    }
                } else {
                    // Retrieve the directories within the main directory
                    // $subDirectories = glob($directoryPath . '/*', GLOB_ONLYDIR);
                    //  if (($subDirectories)) {
                    $directoryPath = "/var/www/html$urlpath";
                    // echo "Directory Path: " . $directoryPath;
                    $inputFiles = $directoryPath;
                    // }else{
                    // echo "subdirectory";
                    // }
                }
                // var_dump($inputFiles);



                $apiKey = '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';
                // $directoryPath = '/var/www/html/vulnerabilities/xss_r/source';

                // Get all file paths in the directory and its subdirectories


                $client = new Client([
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $apiKey,
                    ],
                ]);
                // dd($inputFiles);


                if (isset($inputFiles)) {
                    if (is_array($inputFiles) || is_object($inputFiles)) {
                        foreach ($inputFiles as $filePath) {
                            // Read the contents of the file
                            if (is_string($filePath)) {
                                $trimmedFilePath = rtrim($filePath, '.');
                            $data = file_get_contents($filePath);

                            // dd(countTokens($data));

                            // Split the code if token size exceeds 4000
                            if (countTokens($data) > 4000) {
                                $codeParts = splitCodeIntoParts($data, 4000);

                                foreach ($codeParts as $codePart) {
                                    // Create the prompt for the OpenAI API
                                    $prompt = [
                                        [
                                            "role" => "user",
                                            "content" => "Find the vulnerability in the code and dont give me any explanation only give me the updated code:",
                                        ],
                                        [
                                            'role' => 'user',
                                            'content' => $codePart,
                                        ],
                                    ];

                                    $openAi = new OpenAi($apiKey);

                                    $chat = $openAi->chat([
                                        'model' => 'gpt-3.5-turbo',
                                        'messages' => $prompt,
                                        'temperature' => 0.7,
                                        'max_tokens' => 900,
                                        // 'frequency_penalty' => 0,
                                        // 'presence_penalty' => 0,
                                    ]);

                                    $result = json_decode($chat);

                                    if (isset($result->choices) && is_array($result->choices) && count($result->choices) > 0) {
                                        $fixedCode = $result->choices[0]->message->content;
                                        // Extract the code from the completion response
                                        $code = $this->extractCodeFromCompletion($fixedCode);
                                        // Write the completion response to the file, replacing its contents
                                        file_put_contents($filePath, $code);
                                    } else {
                                        dd($result);
                                    }
                                }
                            } else {
                                echo "hiii";
                                if (countTokens($data) > 4000) {
                                    $codeParts = splitCodeIntoParts($data, 4000);

                                    foreach ($codeParts as $codePart) {
                                        // Create the prompt for the OpenAI API
                                        $prompt = [
                                            [
                                                "role" => "user",
                                                "content" => "Find the vulnerability in the code and dont give me any explanation only give me the updated code:",
                                            ],
                                            [
                                                'role' => 'user',
                                                'content' => $data,
                                            ],
                                        ];

                                        $openAi = new OpenAi($apiKey);

                                        $chat = $openAi->chat([
                                            'model' => 'gpt-3.5-turbo',
                                            'messages' => $prompt,
                                            'temperature' => 0.7,
                                            'max_tokens' => 900,
                                            // 'frequency_penalty' => 0,
                                            // 'presence_penalty' => 0,
                                        ]);

                                        $result = json_decode($chat);

                                        if (isset($result->choices) && is_array($result->choices) && count($result->choices) > 0) {
                                            $fixedCode = $result->choices[0]->message->content;
                                            // Extract the code from the completion response
                                            $code = $this->extractCodeFromCompletion($fixedCode);
                                            // Write the completion response to the file, replacing its contents
                                            file_put_contents($filePath, $code);
                                        } else {
                                            dd($result);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    } else {
                        $decodedUrl = urldecode($inputFiles);

                        // Get the file extension
                        $fileExtension = pathinfo($decodedUrl, PATHINFO_EXTENSION);

                        // Remove the file extension from the URL
                        $decodedUrlWithoutExtension = substr($decodedUrl, 0, -strlen($fileExtension) - 1);

                        // Split the URL into segments
                        $segments = explode('/', $decodedUrlWithoutExtension);

                        // Remove the last segment
                        array_pop($segments);

                        // Reconstruct the URL
                        $decodedUrl = implode('/', $segments);

                        $inputFiles = preg_replace('/[^a-zA-Z0-9\/]/', '', $decodedUrl);
                        // var_dump($dec);
                        // Check if the URL points to a file
                        if (is_file($decodedUrl)) {
                            $data = file_get_contents($decodedUrl);

                            // echo $inputFiles;

                            // Split the code if token size exceeds 4000
                            if (countTokens($data) > 4000) {
                                $codeParts = splitCodeIntoParts($data, 4000);

                                foreach ($codeParts as $codePart) {
                                    dd($codePart);
                                    // Create the prompt for the OpenAI API
                                    $prompt = [
                                        [
                                            "role" => "user",
                                            "content" => "Find the vulnerability in the code and dont give me any explanation only give me the updated code:",
                                        ],
                                        [
                                            'role' => 'user',
                                            'content' => $codePart,
                                        ],
                                    ];

                                    $openAi = new OpenAi($apiKey);

                                    $chat = $openAi->chat([
                                        'model' => 'gpt-3.5-turbo',
                                        'messages' => $prompt,
                                        'temperature' => 0.7,
                                        'max_tokens' => 900,
                                        // 'frequency_penalty' => 0,
                                        // 'presence_penalty' => 0,
                                    ]);

                                    $result = json_decode($chat);
                                    // dd($result->choices[0]->message->content);
                                    $fixedCode = $result->choices[0]->message->content;
                                    // Extract the code from the completion response
                                    $code = $this->extractCodeFromCompletion($fixedCode);
                                    // Write the completion response to the file, replacing its contents
                                    file_put_contents($inputFiles, $code);
                                }
                            } else {
                                if (countTokens($data) > 4000) {
                                    $codeParts = splitCodeIntoParts($data, 4000);

                                    foreach ($codeParts as $codePart) {
                                        // Create the prompt for the OpenAI API
                                        $prompt = [
                                            [
                                                "role" => "user",
                                                "content" => "Find the vulnerability in the code and dont give me any explanation only give me the updated code:",
                                            ],
                                            [
                                                'role' => 'user',
                                                'content' => $data,
                                            ],
                                        ];

                                        $openAi = new OpenAi($apiKey);

                                        $chat = $openAi->chat([
                                            'model' => 'gpt-3.5-turbo',
                                            'messages' => $prompt,
                                            'temperature' => 0.7,
                                            'max_tokens' => 900,
                                            // 'frequency_penalty' => 0,
                                            // 'presence_penalty' => 0,
                                        ]);

                                        $result = json_decode($chat);
                                        // dd($result->choices[0]->message->content);
                                        $fixedCode = $result->choices[0]->message->content;
                                        // Extract the code from the completion response
                                        $code = $this->extractCodeFromCompletion($fixedCode);
                                        // Write the completion response to the file, replacing its contents
                                        file_put_contents($inputFiles, $code);
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // Handle the case when the URL points to a directory
                    // For example, display an error message or take appropriate action
                    $data = null;
                    // Add your error handling logic here
                }

            }
            echo 'All files processed.';

        }

    }


    /**
     * Recursively get all files in a directory and its subdirectories.
     *
     * @param  string  $directoryPath
     * @return array
     */
    private function getFilesRecursively($directoryPath)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directoryPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied" error
        );

        $files = [];
        foreach ($iterator as $path => $file) {
            if ($file->isFile()) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Extracts code from the completion response.
     * Assumes that the code is enclosed in "<?php" and "?>" delimiters.
     * Modify this function based on the specific format of your completion response.
     *
     * @param  string  $completion
     * @return string
     */
    private function extractCodeFromCompletion($completion)
    {
        $matches = [];
        preg_match('/^<\?php(.|\n)*\?>$/s', $completion, $matches);
        if (isset($matches[0])) {
            return $matches[0];
        }
        return '';
    }
    private function extractUrls($data)
    {
        $urls = [];
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $urls = array_merge($urls, $this->extractUrls($value));
            } elseif ($key === 'url') {
                $urls[] = $value;
            }
        }
        return $urls;
    }


}