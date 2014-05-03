JonagueraFacturasScraperBundle
==============================

Bundle Symfony2 para descargar facturas mediante Scraping. Por el momento funciona para descargar facturas de HC Energia, Ono, Pepephone y Som Energia.

Parametros app/config/parameters.yml
====================================
    OnoUsername
    OnoPassword
    OnoSender
    OnoRecipient
    OnoPath
    OnoDateFolders
    OnoParsePdf
    
Los mismos parametros para el resto de scrapers, empezando por el nombre del conector (ej: HcUsername, PpUsername, SomUsername)


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
    "smalot/pdfparser": "dev-master"

