<?php
/*
    Este fichero contiene el código relacionado con el sistema de gestión de ficheros
    de la aplicación. Permite subir, descargar y eliminar ficheros.
*/

class FileManager
{
  private static $root = "/uploads/"; //Ruta raíz del sistema de ficheros
  private static $path = ""; //Ruta a la que apunta el sistema de ficheros

  //FileManager es singleton
  private static $instance = null;
  public static function getInstance()
  {
    if (self::$instance == null)
    {
      self::$instance = new FileManager();
    }

    return self::$instance;
  }

  //Función para asignar un nuevo path
  public static function SetPath($folder)
  {
    self::$path = ($folder != "")? self::$root.$folder.'/' : self::$root;
  }

  //Función para obtener un path
  public static function GetPath()
  {
    return self::$path;
  }

  public static function CheckFolder()
  {
    return file_exists(self::$path);
  }

  public static function ListFolder($folder = "")
  {
    $pila = array();
    $ruta = self::$path.$folder;

    if(is_dir($ruta))
    {
      if($dh = opendir($ruta))
      {
        while(($file = readdir($dh)) !== false)
        {
          if(!is_dir($ruta.$file) && $file!="." && $file!="..")
          {
            array_push($pila,$file);
          }
        }
      }
      closedir($dh);
    }

    return $pila;
  }

  public function Upload($file, $name = "")
  {
    if(!self::CheckFolder() || $file==NULL)
    {
      return;
    }

    $tamano=$file['size'];
    $tipo=$file['type'];

    $nombre = $name;
    if($name == "")
    {
      $archivo=$file['name'];

      $dar_acena= str_replace("á","a",$archivo);
      $dar_acene= str_replace("é","e",$dar_acena);
      $dar_aceni= str_replace("í","i",$dar_acene);
      $dar_aceno= str_replace("ó","o",$dar_aceni);
      $dar_acenu= str_replace("ú","u",$dar_aceno);
      $dar_ene= str_replace("ñ","n",$dar_acenu);
      $dar_esp= str_replace(" ","_",$dar_ene);
      $nombre=$dar_esp;
    }

    $destino = self::$path.$nombre;
    $status = '';

    if(copy($file['tmp_name'],$destino))
    {
      $status=$nombre;
    }

    return $status;
  }

  public function Delete($file)
  {
    if(file_exists(self::$path.$file))
    {
      unlink(self::$path.$file);
    }
  }

  public function __toString()
  {
    $tmp_path = self::$path;
    self::$path = './uploads/';
    $ret = json_encode(self::ListFolder());
    self::$path = $tmp_path;

    if($ret == "[]")
    {
      $ret = "./uploads/";
    }

    return $ret;
  }
}
