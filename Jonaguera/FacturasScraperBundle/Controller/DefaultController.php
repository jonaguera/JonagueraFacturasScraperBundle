<?php

namespace Jonaguera\OnoScraperBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class DefaultController extends Controller
{
    
    public function indexAction($name)
    {
        return $this->render('JonagueraOnoScraperBundle:Default:index.html.twig', array('name' => $name));
    }
}
