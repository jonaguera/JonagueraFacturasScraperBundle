<?php

namespace Jonaguera\FacturasScraperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DomCrawler\Crawler;
use Jonaguera\FacturasScraperBundle\Services\Downloader;

class PepephoneScraperCommand extends ContainerAwareCommand {

    private $headers;
    private $ruta;
    private $username;
    private $password;
    private $lineas;
    private $num_facturas;

    protected function configure() {
        $this
                ->setName('scrapers:ppscraper:getfactura')
                ->setDescription('Obtiene la última factura de Pepephone')
        ;
    }

// @TODO: Valorar el scraping desde el XML, que puede ser más estable que el de la página web. Es el que utiliza la aplicación de pepephone
// RUTAS XML
// Hacer login
// https://www.pepephone.com/ppm_web/ppm_web/1/xmd.login.xml?p_email=&p_pwd=&p_verapp=&p_veross=
// Pagina ultimas facturas en XML
// http://www.pepephone.com/ppm_web/ppm_web/1/xmd.lista_facturas.xml?p_msisdn=[NUMERP]&p_ano=[AÑO]&key=[SESSIONID]
    protected function execute(InputInterface $input, OutputInterface $output) {


        // Carga de variables desde parameters.ini
        $container = $this->getContainer();
        $this->headers = array();
        $this->ruta = $this->getContainer()->getParameter('BasePath').$this->getContainer()->getParameter('PpPath');
        $this->username = $this->getContainer()->getParameter('PpUsername');
        $this->password = $this->getContainer()->getParameter('PpPassword');
        $this->sender = $this->getContainer()->getParameter('PpSender');
        $this->recipient = $this->getContainer()->getParameter('PpRecipient');
        $this->lineas = $this->getContainer()->getParameter('PpLinea');
        $this->num_facturas = $this->getContainer()->getParameter('PpNumFacturas');

        $parameters = array(
            'p_email' => $this->username,
            'p_pwd' => $this->password,
        );

        $fields_string = http_build_query($parameters);
        $ckfile = tempnam("/var/services/tmp", "CURLCOOKIE");

        /* PASO 0
         * Obtener id sesion y montar url login
         * Hasta que sepa bajar la factura con la sesion xml en lugar de la sesion web
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, 'https://www.pepephone.com/ppm_web/ppm_web/1/xmipepephone.login.xml');
        // Guardar cookie
        curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        $output = curl_exec($ch);
        curl_close($ch);
        $xml = simplexml_load_string($output);
        $sesion_web = $xml->attributes()->ses;

//        /* PASO 1
//         * Obtener id sesion y montar url login
//         */
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
//        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
//        curl_setopt($ch, CURLOPT_URL, 'https://www.pepephone.com/ppm_web/ppm_web/1/xmd.login.xml?p_email='.$this->username.'&p_pwd='.$this->password.'&p_verapp=&p_veross=');
//        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
//        curl_setopt($ch, CURLOPT_VERBOSE, '0');
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
//        $output = curl_exec($ch);
//        curl_close($ch);
//        $xml = simplexml_load_string($output);
//        $ses = $xml->attributes()->ses;



        /* PASO 2
         * Pedir página de facturas y parseo de últimas facturas (numero)
         */

    // PAGINA ULTIMAS FACTURAS
    $mespasado = strtotime('-1 month', strtotime(date('Y-m-d')));
    $anno = date('Y', $mespasado);
    // consulta_facturas2
    // $url = 'https://www.pepephone.com/ppm_web/ppm_web/1/mipepephone2/mislineas/vermas/otrasfacturas/cuerpo/xweb_factura_new.consulta_facturas2.html?p_msisdn=' . $linea . '&p_regini=1&p_ano=' . $anno . '&p_numreg=12&xsid=' . $ses;
    // lista_facturas.xml
    // $url = 'https://www.pepephone.com/ppm_web/ppm_web/1/xmd.lista_facturas.xml?p_msisdn='.$linea.'&p_ano='.date('Y').'&key='. $ses;
    // Todas las lineas unificadas
    $url = 'https://www.pepephone.com/ppm_web/ppm_web/1/mipepephone2/facturas/xweb_factura_new.consulta_facturas_unificada.html?p_origen=MOVIL&p_ano=' . $anno . '&p_regini=1&p_numreg='.$this->num_facturas.'&xsid=' . $sesion_web;

    // PAGINA FACTURA
    // 'https://www.pepephone.com/ppm_web2/ppm_web2/mipepephone/mislineas/consumo/factura?numfac='201190810822'.$numfactura.'&xsid='.$ses;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, '0');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
    curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
    $output = curl_exec($ch);
    curl_close($ch);

        // Respuesta html table
        $dom = new \DOMDocument();
        @$dom->loadHTML($output);

        // grab all the on the page
        $xpath = new \DOMXPath($dom);
        // Obtener 6 ultimas facturas
        $enlaces = array();
        for ($i=1; $i<=$this->num_facturas; $i++){
            $anchor = $xpath->query('/html/body/table/tbody/tr['.$i.']/td[2]/a');
            if ($anchor->length) {
                $enlaces[] = $anchor->item(0)->getAttribute('href');
            }
        }
        if (sizeof($enlaces) > 0) {
            /* PASO 3
             * Peticion última factura Pepephone
             */
            foreach ($enlaces as $enlace){
                $url = 'https://www.pepephone.com' . $enlace;
                $downloader = new Downloader(
                                $url,
                                $ckfile,
                                $this->ruta,
                                $this->sender,
                                $this->recipient,
                                $container,
                                $this->getContainer()->getParameter('PpDateFolders'),
                                $this->getContainer()->getParameter('PpParsePdf')

                );

                // Pepephone ya no da nombre en la descarga, hay que forzarlo

                $p_url = parse_url($url);
                $p_query=$this->parse_query($p_url['query']);
                $downloader->save($p_query['numfac'].'.pdf');
            }
        } else {
            echo "No hay facturas que comprobar";
        }
    }

    private function parse_query($var) {
        /**
         *  Use this function to parse out the query array element from 
         *  the output of parse_url(). 
         */
        $var = html_entity_decode($var);
        $var = explode('&', $var);
        $arr = array();

        foreach ($var as $val) {
            $x = explode('=', $val);
            $arr[$x[0]] = $x[1];
        }
        unset($val, $x, $var);
        return $arr;
    }

    private function readHeader($ch, $header) {
        $this->headers[] = $header;
        return strlen($header);
    }

    private function getLocationFromHeaders() {
        foreach ($this->headers as $header) {
            if (substr($header, 0, 10) == 'Location: ') {
                return substr($header, 10, strlen($header) - 12);
            }
        }
        return false;
    }

    private function getFilenameFromHeaders() {
        foreach ($this->headers as $header) {
            if (strcasecmp(substr($header, 0, 42), 'Content-Disposition: attachment; filename=') == 0) {
                return substr($header, 42, strlen($header) - 44);
            }
        }
        return false;
    }

}

