# FRONTEND PiD - PlaceIdentificator
 
Technische zaken die van belang zijn tijdens de ontwikkeling

- App krijgt twee soorten users:
    -- gewone gebruikers met ROLE_USER
    -- admin gebruikers met ROLE_ADMIN (die kunnnen als extraatje ook users beheren en eventuele andere zaken)
    
    -- checken of iemand de juiste rol heeft: if ($app['security']->isGranted('ROLE_ADMIN')) { ... }


##  Documentatie over gebruikte onderdelen   
    
- Database spul werkt nu met *Doctrine DBAL* - http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/
- *Silex* docs: http://silex.sensiolabs.org/documentation
- *Twig template* docs: http://twig.sensiolabs.org