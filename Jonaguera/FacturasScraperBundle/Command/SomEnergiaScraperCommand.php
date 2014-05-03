<?php

namespace Jonaguera\FacturasScraperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Jonaguera\FacturasScraperBundle\Services\Downloader;

class SomEnergiaScraperCommand extends ContainerAwareCommand {

    private $headers;
    private $ruta;
    private $username;
    private $password;

    protected function configure() {
        $this
            ->setName('scrapers:somscraper:getfactura')
            ->setDescription('Obtiene la última factura de SomEnergia')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {


        // Carga de variables desde parameters.ini
        $container = $this->getContainer();
        $this->headers = array();
        $this->ruta = $this->getContainer()->getParameter('BasePath').$this->getContainer()->getParameter('SomPath');
        $this->username = $this->getContainer()->getParameter('SomUsername');
        $this->password = $this->getContainer()->getParameter('SomPassword');
        $this->sender = $this->getContainer()->getParameter('SomSender');
        $this->recipient = $this->getContainer()->getParameter('SomRecipient');
        $ckfile = tempnam("/var/services/tmp", "CURLCOOKIE");


        /* PASO 0
         * Obtener csrf
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, 'https://oficinavirtual.somenergia.coop/es/login/');
        // Guardar cookie
        curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        $output = curl_exec($ch);
        curl_close($ch);

        // Obtener csrf
        $dom = new \DOMDocument();
        @$dom->loadHTML($output);
        $xpath = new \DOMXPath($dom);
        $csrf = $xpath->query('//input[@name="csrfmiddlewaretoken"]')->item(0)->getAttribute('value');
        $parameters = array('csrfmiddlewaretoken' => $csrf, 'username' => $this->username, 'password' => $this->password, 'submit' => '');
        $fields_string = http_build_query($parameters);


        /* PASO 1
         * Hacer login
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, 'https://oficinavirtual.somenergia.coop/es/login/');
        // Usar cookie
        curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_REFERER, 'https://oficinavirtual.somenergia.coop/es/login/');
        $output = curl_exec($ch);
        curl_close($ch);

        // Obtener link a pagina de facturas

        // Obtener listado de contratos
        $dom = new \DOMDocument();
        @$dom->loadHTML($output);
        $xpath = new \DOMXPath($dom);
        $link = $xpath->query('//td[@class="periodo"]/a')->item(0)->getAttribute('href');


        /* PASO 2
         * Visitar pagina de facturas
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, 'https://oficinavirtual.somenergia.coop'.$link);
        // Usar cookie
        curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_VERBOSE, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_REFERER, 'https://oficinavirtual.somenergia.coop/es/login/');
        $output = curl_exec($ch);
        curl_close($ch);

        // Obtener listado de facturas
        $dom = new \DOMDocument();
        @$dom->loadHTML($output);
        $xpath = new \DOMXPath($dom);
        $link = $xpath->query('//td[@class="pdf_button"]/a')->item(0)->getAttribute('href');


        /* PASO 3
        * Descargar documento
        */
        $downloader = new Downloader(
            'https://oficinavirtual.somenergia.coop'.$link, // url
            $ckfile,
            $this->ruta,
            $this->sender,
            $this->recipient,
            $container,
            $this->getContainer()->getParameter('SomDateFolders'),
            $this->getContainer()->getParameter('SomParsePdf')

        );
        $downloader->save();
    }
}

