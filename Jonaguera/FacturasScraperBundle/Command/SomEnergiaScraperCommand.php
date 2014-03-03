<?php

namespace Jonaguera\FacturasScraperBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Jonaguera\FacturasScraperBundle\Services\Downloader;
use Goutte\Client;

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


        // Crear el cliente
        $client = new Client();

        // Login page
        $crawler = $client->request('GET', 'https://oficinavirtual.somenergia.coop/es/login/');
        // Select Login form
        $form = $crawler->selectButton('Continuar')->form();
        // Submit form
        $crawler = $client->submit($form, array(
            'username' => $this->username,
            'password' => $this->password,
        ));

        // Seleccionar primer botón "Facturas" y seguir enlace
        $link = $crawler->filterXPath('//*[@id="contratos-table"]/tbody/tr/td[8]/a')->link();
        $crawler = $client->click($link);


        // Lista de facturas
        $crawler=$crawler->filterXPath('//*[@id="features3"]/div/div[2]/div/div/p');
        var_dump($crawler->html());
        die;
    }
}

