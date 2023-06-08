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
      
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 'http://localhost:8081/JSON/alert/view/alertsByRisk/?apikey=qe18gslcka4doa484bq7sb97ri&url=&recurse='); 
        $data = json_decode($response->getBody());
        // dd($data->alertsByRisk);
        $data = $data->alertsByRisk[3];
        $urls = $this->extractUrls($data);
        // dd($urls);
        foreach($urls as $url){
                            var_dump($url);
                            $params = parse_url($url, PHP_URL_QUERY);
                            parse_str($params, $query);
                            $urlParts = parse_url($url);
                            // dd($urlParts);
                            // var_dump($urlParts);
                            $urlpath= $urlParts['path'];
                            $index = "index.php";
                            if (strpos($urlParts['path'], ".php") === false) {
                                
                            $filePath = "/var/www/html$urlpath$index";
                            // var_dump($filePath);
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
                                // echo "Directory Path not found.";
                            }
                            }else{
                            $directoryPath = "/var/www/html$urlpath";
                            // echo "Directory Path: " . $directoryPath;
                            $inputFiles = $directoryPath;
                            }
                            // var_dump($inputFiles);
                          
                          
                            
                            $apiKey = '';
                            // $directoryPath = '/var/www/html/vulnerabilities/xss_r/source';
                    
                            // Get all file paths in the directory and its subdirectories
    
                    
                            $client = new Client([
                                'headers' => [
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer ' . $apiKey,
                                ],
                            ]);
                            // dd($inputFiles);
                            if (is_array($inputFiles) || is_object($inputFiles)) {
                            foreach ($inputFiles as $filePath) {
                                // Read the contents of the file
                                $data = file_get_contents($filePath);
                                echo $filePath;
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
                                
                                $fixedCode = $result->choices[0]->message->content;
                                // dd($fixedCode);
                                // Extract the code from the completion response
                                $code = $this->extractCodeFromCompletion($fixedCode);
                                dd($code);
                                // Write the completion response to the file, replacing its contents
                                file_put_contents($filePath, $code);
                            }
                        }else{
                            $data = file_get_contents($inputFiles);
                                echo $inputFiles;
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
                                // dd($fixedCode);
                                // Extract the code from the completion response
                                $code = $this->extractCodeFromCompletion($fixedCode);
                                // dd($code);
                                // Write the completion response to the file, replacing its contents
                                file_put_contents($inputFiles, $code);
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

    private function extractUrls($data) {
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