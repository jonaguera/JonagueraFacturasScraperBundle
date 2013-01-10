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

class Downloader {

    private $url;
    private $ckfile;
    private $ruta;
    private $sender;
    private $recipient;
    private $container;

    function __construct($url, $ckfile, $ruta, $sender, $recipient, $container) {
        $this->url = $url;
        $this->ckfile = $ckfile;
        $this->ruta = $ruta;
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->container = $container;
    }

    function Save() {


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
        $filename = $this->getFilenameFromHeaders();

        if ($filename) {
            if (file_exists($this->ruta . "/" . $filename)) {
                // Si existe
                echo "El fichero " . $this->ruta . "/" . $filename . " existe. No se hace nada.\n";
            } else {
                // Si no existe
                echo "El fichero " . $this->ruta . "/" . $filename . " no existe. Se baja.\n";
                $fp = fopen($this->ruta . "/" . $filename, 'w');
                fwrite($fp, $output);
                fclose($fp);
                $message = \Swift_Message::newInstance()
                        ->setSubject('Factura de HC bajada')
                        ->setFrom($this->sender)
                        ->setTo($this->recipient)
                        ->setBody(
                        'Se ha descargado la factura ' . $filename
                        )
                ;
                $this->container->get('mailer')->send($message);
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
            if (stripos($header,"filename=")){
                $filename=substr($header,stripos($header,"filename=")+9);
                // Quito dos caracteres salto línea al final
                $filename=substr($filename, 0 , strlen($filename)-2);
                return $filename;
            }
        }
        return false;
    }
}