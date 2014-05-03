<?php

/**
 * Clase con funciones para el parseo de ficheros PDF y la extracción de datos
 *
 * (c) Jon Agüera Fuente <jon_aguera@viajeseroski.es>
 *
 * @package PackageName
 * @author AuthorName
 * @abstract Abstract
 */

namespace Jonaguera\FacturasScraperBundle\Services;

use Smalot\PdfParser\Parser;


class PdfParser {
    private $file;
    function __construct($file){
        $this->file = $file;
    }

    function readInvoiceTotal(){
        $parser = new Parser();
        $pdf    = $parser->parseFile($this->file);
        $text = $pdf->getText();
        $text = explode("\n",$text);

        // Comprobar si es de ONO
        if (count(preg_grep("/Su servicio ONO/",$text))){
            $p_text = preg_grep("/Total\sfactura\ (\d+,\d+)/",$text);
            if (count($p_text)){
                $p_text = array_values($p_text);
                $total_factura = substr($p_text[0],14);
            } else {
                $total_factura = "Dato no encontrado en factura ONO";
            }
            return $total_factura;
        }


        return "Factura no identificada";
    }
}
