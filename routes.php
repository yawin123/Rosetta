<?php

    Router::get("/", [HomeController::class, 'index'], "index");
    
    Router::get("/id/{var1}", [HomeController::class, 'vars']);
    Router::get("/{var1}/{var2}", [HomeController::class, 'vars2']);
    
    Router::post("/{var1}", [HomeController::class, 'vars']);
