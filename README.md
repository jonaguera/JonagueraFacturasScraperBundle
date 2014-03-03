JonagueraFacturasScraperBundle
==============================

Bundle Symfony2 para descargar facturas mediante Scraping. Por el momento funciona para descargar facturas de HC Energia, Ono y Pepephone.

Parametros app/config/parameters.ini
====================================
    OnoUsername
    OnoPassword
    OnoSender  
    OnoRecipient 
    OnoPath     

    HcUsername
    HcPassword     
    HcSender      
    HcRecipient    
    HcPath           

    PpUsername    
    PpPassword  
    PpSender   
    PpRecipient     
    PpPath         
    PpNumFacturas

    SomUsername
    SomPassword
    SomSender
    SomRecipient
    SomPath

    mailer_user    
    mailer_password

app/AppKernel.php
=================
    new Jonaguera\FacturasScraperBundle\JonagueraFacturasScraperBundle(),

app/config/config.yml
=====================
Seccion swiftmailer
-------------------
    transport: gmail
    username:  %mailer_user%
    password:  %mailer_password%


Dependencias
============
    php5_curl
    "fabpot/goutte": "1.0.*@dev"

