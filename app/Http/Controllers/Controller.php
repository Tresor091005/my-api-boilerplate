<?php

namespace App\Http\Controllers;

abstract class Controller
{
    // Adding a syntax error here
    public function testError() {
        echo "This is a test;
    }
}
