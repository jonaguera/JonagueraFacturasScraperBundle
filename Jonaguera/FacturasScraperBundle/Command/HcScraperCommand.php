<?php

namespace Jonaguera\FacturasScraperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Jonaguera\FacturasScraperBundle\Services\Downloader;

class HcScraperCommand extends ContainerAwareCommand {

    private $headers;
    private $ruta;
    private $username;
    private $password;

    protected function configure() {
        $this
            ->setName('scrapers:hcscraper:getfactura')
            ->setDescription('Obtiene la última factura de Hc Energia')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // Carga de variables desde parameters.ini
        $container = $this->getContainer();
        $this->headers = array();
        $this->ruta = $this->getContainer()->getParameter('BasePath').$this->getContainer()->getParameter('HcPath');
        $this->username = $this->getContainer()->getParameter('HcUsername');
        $this->password = $this->getContainer()->getParameter('HcPassword');
        $this->sender = $this->getContainer()->getParameter('HcSender');
        $this->recipient = $this->getContainer()->getParameter('HcRecipient');
        $parameters = array(
            'action' => 'login',
            'nif' => $this->username,
            'password' => $this->password,
            'idioma_sesion' => 'ES',
            'modo' => '',
            'redireccion' => '',
            'fin_aplicacion' => '',
        );

        $fields_string = http_build_query($parameters);
        $ckfile = tempnam("/tmp", "CURLCOOKIE");







        /* PASO 0
         * Enviar página login (la obtengo parseando el resultado de la página para ver el redirect en la respuesta html
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, 'https://www.edpenergia.es/areacliente');
        // Guardar cookie
        curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        $output = curl_exec($ch);
        curl_close($ch);

        $dom = new \DOMDocument();
        @$dom->loadHTML($output);

        // grab all the on the page
        $xpath = new \DOMXPath($dom);
        $hrefs = $xpath->query('/html/body/p/a');
        for ($i = 0; $i < $hrefs->length; $i++) {
            $href = $hrefs->item($i);
            $location = $href->getAttribute('href');
            break;
        }


        /* PASO 0
         * Obtener página de login
         */
        /** NO FUNCIONA EN SYNOLOGY
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'https://www.hcenergia.com/webclientes/');
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
        $output = curl_exec($ch);
        curl_close($ch);
        echo($output."\n");
        $location = $this->getLocationFromHeaders();
         */


        /* PASO 1
         * Enviar página login
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, $location);
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

        /* PASO 2
         * Pedir página de facturas y parseo de últimas facturas (numero)
         */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $location . '?action=peticionIntFact&num_factura=&anio=&limpiar_variables=-&actionPage=listaFacturas&pagina=1&numFilas=10&numPaginas=2&origen=&idioma=');
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

        $output_lines = explode("\n", $output);
        $b = preg_grep("/^.*javascript:ir_detalle_factura\(\'(.*)\'.*$/", $output_lines);
        $fac = array();
        foreach ($b as $linea_factura) {
            preg_match('/ir_detalle_factura\(\'(.*)\',\'(.*)\',\'(.*)\',\'(.*)\',\'(.*)\'\)\;\"\>/', $linea_factura, $matches);
            if (substr($matches[1], 1, 1) == "N") {
                //G
                $fac['G'][] = $matches;
            } else if (substr($matches[1], 1, 1) == "H") {
                //E
                $fac['E'][] = $matches;
            }
        }

        /* PASO 3
         * Peticion última factura G
         */
        $num_factura = $fac['G'][0][1];
        $anio = $fac['G'][0][2];
        $downloader = new Downloader(
            $location . '?action=detalleFactura&anio=' . $anio . '&num_factura=' . $num_factura . '&original=-',
            $ckfile,
            $this->ruta,
            $this->sender,
            $this->recipient,
            $container,
            $this->getContainer()->getParameter('HcDateFolders'),
            $this->getContainer()->getParameter('HcParsePdf')
        );
        $downloader->save();

        /* PASO 4
         * Peticion última factura E
         */
        $num_factura = $fac['E'][0][1];
        $anio = $fac['E'][0][2];

        $downloader = new Downloader(
            $location . '?action=detalleFactura&anio=' . $anio . '&num_factura=' . $num_factura . '&original=-',
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
        print_r($this->headers);
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

