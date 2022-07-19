<?php
/*
    Este fichero contiene el código relacionado con la clase JSONData, que sirve
    para abstraer del uso de ficheros JSON.
*/

class JSONData
{
  public function __construct($file = "")
  {
    if($file != "")
    {
      $this->load($file);
    }
  }

  public function setFile($file)
  {
    $this->file = $file;
  }

  public function load($file)
  {
    $this->setFile($file);
    $this->raw = file_get_contents("./json/".$this->file);
    $this->data = json_decode($this->raw);
  }
  public function save()
  {
    $this->raw = json_encode($this->data, JSON_PRETTY_PRINT);
    file_put_contents("./json/".$this->file, $this->raw);
  }
}
