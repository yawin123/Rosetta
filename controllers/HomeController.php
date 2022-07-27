<?php

    class HomeController
    {
        public static function index()
        {
            return view("index");
        }
        
        public static function vars($var1)
        {
            $vars['var1'] = $var1;
            return view("vars", compact("vars"));
        }
        
        public static function vars2($var1, $var2)
        {
            $vars['var1'] = $var1;
            $vars['var2'] = $var2;
            return view("vars", compact("vars"));
        }
    }
