<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseController extends Controller
{

    protected $database;

    public function __construct()
    {
        $this->database = Firebase::database();
    }

    public function index()
    {
        // $reference = $database->getReference('path/to/child/location');
    }
}
