<?php
/*
    Este fichero contiene la configuración global de la aplicación
*/

    $GLOBALS['app_name'] = "Rosetta";

  /* DATOS DE CONEXIÓN DE LA BASE DE DATOS */
    $GLOBALS['BD_ENABLED'] = false;

    $GLOBALS['BD_ENGINE'] = ""; //Engines: mysql | sqlite
    $GLOBALS['BD_NAME'] = '';

    $GLOBALS['BD_SERVER'] = "";
    $GLOBALS['BD_USER'] = '';
    $GLOBALS['BD_PASS'] = '';


  /* DATOS DE CONFIGURACIÓN DE LAS UTILIDADES DE AUTORIZACIÓN */
    $GLOBALS['LOGIN_BD_ENABLED'] = false;
    $GLOBALS['LOGIN_BD_NAME'] = '';

  /* DATOS DE CONEXIÓN DE LA BASE DE DATOS DE LOGIN SI ES DIFERENTE A LA GENERAL */
    $GLOBALS['LOGIN_BD_ENGINE'] = ""; // mysql | sqlite
    $GLOBALS['LOGIN_BD_SERVER'] = "";
    $GLOBALS['LOGIN_BD_USER'] = '';
    $GLOBALS['LOGIN_BD_PASS'] = '';


  /* ESTABLECE QUÉ ERRORES DE PHP SON NOTIFICADOS */
    $GLOBALS['ERROR_REPORTING_LEVEL'] = E_ALL;

  /* IDIOMA POR DEFECTO */
    $GLOBALS['lang'] = 'es';

  /* MISC OPTIONS */
    $GLOBALS['ROOT_PATH'] = '/';
