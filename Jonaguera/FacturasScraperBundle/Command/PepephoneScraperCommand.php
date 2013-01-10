<?php

namespace Jonaguera\FacturasScraperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Util\Filesystem;
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
        $url = 'https://www.pepephone.com/ppm_web/ppm_web/1/mipepephone2/xmipepephone.info.html?xres=C&xsid='.$ses;
        
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
        foreach ($this->lineas as $linea){
        $url = 'https://www.pepephone.com/ppm_web/ppm_web/1/mipepephone2/mislineas/vermas/xmipepephone.detalle_servicio.html?p_msisdn='.$linea.'&p_cabecera=N&p_numregfac=3&xsid='.$ses;

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

echo $output;
die;

        $output_lines = explode("\n", $output);
        $b = preg_grep("/^.*javascript:ir_detalle_factura\(\'(.*)\'.*$/", $output_lines);
        $fac = array();
        foreach ($b as $linea_factura) {
            preg_match('/ir_detalle_factura\(\'(.*)\',\'(.*)\',\'(.*)\',\'(.*)\'\)\;\"\>/', $linea_factura, $matches);
            if (substr($matches[1], 1, 1) == "N") {
                //G
                $fac['G'][] = $matches;
            } else if (substr($matches[1], 1, 1) == "H") {
                //E
                $fac['E'][] = $matches;
            }
        }
        }
        /* PASO 3
         * Peticion última factura G
         */
        $num_factura = $fac['G'][0][1];
        $anio = $fac['G'][0][2];

        $downloader = new Downloader(
                        $location . 'consulta.do?action=detalleFactura&anio=' . $anio . '&num_factura=' . $num_factura . '&original=-',
                        $ckfile,
                        $this->ruta,
                        $this->sender,
                        $this->recipient,
                        $container
        );
        $downloader->save();

        /* PASO 4
         * Peticion última factura E
         */
        $num_factura = $fac['E'][0][1];
        $anio = $fac['E'][0][2];

        $downloader = new Downloader(
                        $location . 'consulta.do?action=detalleFactura&anio=' . $anio . '&num_factura=' . $num_factura . '&original=-',
                        $ckfile,
                        $this->ruta,
                        $this->sender,
                        $this->recipient,
                        $container
        );
        $downloader->save();

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

