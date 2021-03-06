<?php

namespace Jonaguera\FacturasScraperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Jonaguera\FacturasScraperBundle\Services\Downloader;

class OnoScraperCommand extends ContainerAwareCommand {

    private $headers;
    private $ruta;
    private $username;
    private $password;

    protected function configure() {
        $this
                ->setName('scrapers:onoscraper:getfactura')
                ->setDescription('Obtiene la última factura de ono')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {


        // Carga de variables desde parameters.ini
        $container = $this->getContainer();
        $this->headers = array();
        $this->ruta = $this->getContainer()->getParameter('BasePath').$this->getContainer()->getParameter('OnoPath');
        $this->username = $this->getContainer()->getParameter('OnoUsername');
        $this->password = $this->getContainer()->getParameter('OnoPassword');
        $this->sender = $this->getContainer()->getParameter('OnoSender');
        $this->recipient = $this->getContainer()->getParameter('OnoRecipient');
        $parameters = array('idClientehidden' => '', 'user_username' => $this->username, 'user_password' => $this->password, 'answer' => '');
        $fields_string = http_build_query($parameters);
        $ckfile = tempnam("/var/services/tmp", "CURLCOOKIE");

        /* PASO 1
         * Visitar página que sirve la cookie (login)
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_URL, 'https://www.ono.es/clientes/registro/login/entrar/');
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
         * Visitar la página enviando cookie (facturas emitidas)
         */
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.ono.es/clientes/facturacion/facturas-emitidas/');
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

        /* PASO 3
         * Descargar documento
         */
        $downloader = new Downloader(
                        'https://www.ono.es/delegate/factYpagos-portlets/FYPDelegateServlet/?action=factDetail&numServicio=0&numFactura=0&tipo=Factura&formato=2', // url
                        $ckfile,
                        $this->ruta,
                        $this->sender,
                        $this->recipient,
                        $container,
                        $this->getContainer()->getParameter('OnoDateFolders'),
                        $this->getContainer()->getParameter('OnoParsePdf')

        );
        $downloader->save();
    }
}
