<?php

namespace App\Http\Controllers;
use MVar\LogParser\LogIterator;
use MVar\LogParser\SimpleParser;  

class DataController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function index()
    {
        // dd(__DIR__);
        // $file_path = realpath('/Users/katesaikishore/teler/output.log');
        // $array = explode("\n", file_get_contents($file_path));
        // foreach ( $array as $line)
        // {
        //     $obj = json_decode($line);
        //     dd($obj->category);
        //     echo "<hr>";
        // }
        // $json = json_encode(file_get_contents($file_path), true);
        // dd($json);
        // var_dump((new \Flow\JSONPath\JSONPath($json))->find('$')->getData()[0]);
        return view('data');
    }

    //
}
