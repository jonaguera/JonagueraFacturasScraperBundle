<?php

namespace Jonaguera\FacturasScraperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Util\Filesystem;
use Symfony\Component\DomCrawler\Crawler;
use Jonaguera\FacturasScraperBundle\Services\Downloader;

class PepephoneScraperCommand extends ContainerAwareCommand {

    private $headers;
    private $ruta;
    private $username;
    private $password;

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
        $this->ruta = $this->getContainer()->getParameter('PpPath');
        $this->username = $this->getContainer()->getParameter('PpUsername');
        $this->password = $this->getContainer()->getParameter('PpPassword');
        $this->sender = $this->getContainer()->getParameter('PpSender');
        $this->recipient = $this->getContainer()->getParameter('PpRecipient');
        $this->lineas = $this->getContainer()->getParameter('PpLinea');

        $parameters = array(
            'p_email' => $this->username,
            'p_pwd' => $this->password,
        );

        $fields_string = http_build_query($parameters);
        $ckfile = tempnam("/tmp", "CURLCOOKIE");

        /* PASO 0
         * Obtener id sesion y montar url login
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
        $ses = $xml->attributes()->ses;
        $url = 'https://www.pepephone.com/ppm_web/ppm_web/1/mipepephone2/xmipepephone.info.html?xres=C&xsid=' . $ses;

        /* PASO 1
         * Enviar página login
         */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, $url);
        // Guardar cookie
        curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        $output = curl_exec($ch);
        curl_close($ch);




        /* PASO 2
         * Pedir página de facturas y parseo de últimas facturas (numero)
         */

        foreach ($this->lineas as $linea) {
            // PAGINA ULTIMAS FACTURAS
            $mespasado = strtotime('-1 month', strtotime(date('Y-m-d')));
            $anno = date('Y', $mespasado);
            $url = 'https://www.pepephone.com/ppm_web/ppm_web/1/mipepephone2/mislineas/vermas/otrasfacturas/cuerpo/xweb_factura_new.consulta_facturas2.html?p_msisdn=' . $linea . '&p_regini=1&p_ano=' . $anno . '&p_numreg=12&xsid=' . $ses;



            // PAGINA FACTURA
            // 'https://www.pepephone.com/ppm_web2/ppm_web2/mipepephone/mislineas/consumo/factura?numfac='201190810822'.$numfactura.'&xsid='.$ses;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
            //Enviar cookie
            curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, '0');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
            $output = curl_exec($ch);
            curl_close($ch);

            $crawler = new Crawler($output);
            $crawler = $crawler->filter('div > div > a.lnkBlack');
            $enlaces = array();
            foreach ($crawler as $domElement) {
                $enlaces[] = $domElement->getAttribute('href');
            }
            if (sizeof($enlaces) > 0) {
                /* PASO 3
                 * Peticion última factura Pepephone
                 */
                $url = 'https://www.pepephone.com' . $enlaces[0];
                $downloader = new Downloader(
                                $url,
                                $ckfile,
                                $this->ruta,
                                $this->sender,
                                $this->recipient,
                                $container
                );

                // Pepephone ya no da nombre en la descarga, hay que forzarlo
                $p_url = parse_url($url);
                $p_query=$this->parse_query($p_url['query']);
                $downloader->save($p_query['p_numfac'].'.pdf');
            } else {
                echo "No hay facturas que comprobar para la línea " . $linea;
            }
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

