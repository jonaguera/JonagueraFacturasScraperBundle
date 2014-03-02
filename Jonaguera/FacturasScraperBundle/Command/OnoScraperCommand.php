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
        $ckfile = tempnam("/tmp", "CURLCOOKIE");

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
                        $container
        );
        $downloader->save();
 
        /*
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.ono.es/delegate/factYpagos-portlets/FYPDelegateServlet/?action=factDetail&numServicio=0&numFactura=0&tipo=Factura&formato=2');
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1");
        // Enviar cookie
        curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
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
                        ->setSubject('Factura de Ono bajada')
                        ->setFrom($this->sender)
                        ->setTo($this->recipient)
                        ->setBody(
                        'Se ha descargado la factura ' . $filename
                        )
                ;
                $container->get('mailer')->send($message);
            }
        } else {
            // No se devuelve un archivo
            echo "La operación no ha devuelto ningún archivo. Revise la configuración.\n";
        }
        die;
        */
    }

    private function readHeader($ch, $header) {
        $this->headers[] = $header;
        return strlen($header);
    }

    private function getFilenameFromHeaders() {
        foreach ($this->headers as $header) {
            if (substr($header, 0, 41) == 'Content-Disposition: attachment;filename=') {
                return substr($header, 41, strlen($header) - 43);
            }
        }
        return false;
    }

}
