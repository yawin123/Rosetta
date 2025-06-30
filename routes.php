<?php

    Router::get("/", [HomeController::class, 'index'], "index");
    
    Router::get("/id/{var1}", [HomeController::class, 'vars']);
    Router::get("/{var1}/{var2}", [HomeController::class, 'vars2']);
    
    Router::post("/{var1}", [HomeController::class, 'vars']);

    Router::redirection("repo", "https://git.yawin.es/personal/rosetta.git");
    Router::redirection("license", "https://www.gnu.org/licenses/gpl-3.0.en.html");
    Router::redirection("author", "https://miguelalbors.es");
