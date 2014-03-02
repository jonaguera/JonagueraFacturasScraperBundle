<?php

/**
 * Clase con funciones para la descarga y guardado de archivos
 * 
 * (c) Jon Agüera Fuente <jon_aguera@viajeseroski.es>
 * 
 * @package PackageName
 * @author AuthorName
 * @abstract Abstract
 */

namespace Jonaguera\FacturasScraperBundle\Services;

use Symfony\Component\Finder\Finder;

class Downloader {

    private $url;
    private $ckfile;
    private $ruta;
    private $sender;
    private $recipient;
    private $container;
    private $datefolders;

    function __construct($url, $ckfile, $ruta, $sender, $recipient, $container,$datefolders=true) {
        // $datefolders hace que se creen directorios en funcion de año y mes
        $this->url = $url;
        $this->ckfile = $ckfile;
        $this->ruta = $ruta;
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->container = $container;
        $this->datefolders = $datefolders;
    }

    function save($filename = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        // Enviar cookie
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->ckfile);
        // Para descargar
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        // Solo para debug
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        // Evitar error SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        // Función callback para lectura de cabeceras
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
        $output = curl_exec($ch);
        curl_close($ch);


        // Mirar si el filename ya existe en el filesystem
        //Obtener el filename
        if (!$filename){
            $filename = $this->getFilenameFromHeaders();
        }

        if ($filename) {
            $i=0;
            $finder = new Finder();
            $finder->name($filename);
            echo "Se va a comprobar la existencia de ".$filename." en ".$this->ruta."\n";
            foreach ($finder->in($this->ruta) as $file) {
                $i++;
                echo "Encontrado el archivo ".$file->getFilename() . "\n";
            }


            if ($i>0) {
                // Chequear si el documento existe
                // Si existe
                echo "El fichero " . $filename . " existe. No se hace nada.\n";
            } else {
                // Si no existe
                echo "El fichero " . $filename . " no existe. Se baja.\n";
                
                // Crear directorio destino
                if($this->datefolders){
                    $this->ruta=$this->ruta."/".date('Y')."/".date('m');
                }

                if (!file_exists($this->ruta)){
                    mkdir($this->ruta, 0777, true);
                }

                $fp = fopen($this->ruta. "/" . $filename.".tmp", 'w');
                fwrite($fp, $output);
                fclose($fp);
                chmod($this->ruta . "/" . $filename.".tmp", 0777);
                rename ( $this->ruta . "/" . $filename.".tmp",$this->ruta . "/" . $filename );

                if ($this->recipient){
                    echo "Se envia mail a " . print_r($this->recipient,1) . ".\n";
                    $message = \Swift_Message::newInstance()
                            ->setSubject('Factura '.$filename.' disponible')
                            ->setFrom($this->sender)
                            ->setTo($this->recipient)
                            ->attach(\Swift_Attachment::fromPath($this->ruta . "/" . $filename))
                            ->setBody(
                            'Se ha descargado la factura '. $filename.' en la ruta '.$this->ruta .'/'
                            )
                    ;
                    $this->container->get('mailer')->send($message);
                }
            }
        } else {
            // No se devuelve un archivo
            echo "La operación no ha devuelto ningún archivo. Revise la configuración.\n";
        }
    }

    private function readHeader($ch, $header) {
        $this->headers[] = $header;
        return strlen($header);
    }

    /* TODO
     * Hacerlo compatible con el otro scraper
     * tip: utilizar la posicion de "filename="
     */

    private function getFilenameFromHeaders() {
        foreach ($this->headers as $header) {
            if (stripos($header, "filename=")) {
                $filename = substr($header, stripos($header, "filename=") + 9);
                // Quito dos caracteres salto línea al final
                $filename = substr($filename, 0, strlen($filename) - 2);
                // Solo caracteres alfanumericos y guiones, puntos
                $filename = preg_replace('/[^\da-z\-\.]/i', '', $filename);
                // Pongo extension si no viene
                if (!strpos($filename, '.')){
                    foreach ($this->headers as $header) {
                        if (strpos($header, 'application/pdf')){
                            $filename = $filename.".pdf";
                        }
                    }
                }
                return $filename;
            }
        }
        return false;
    }

}