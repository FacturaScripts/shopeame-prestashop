<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class Shopeame extends Module
{
    /**
     * Constructor, el inicio de toda la aplicación
     */
    public function __construct()
    {
        // nombre de aplicación en [a-z,0-9]
        $this->name = 'shopeame';
        // sección de la aplicación https://devdocs.prestashop-project.org/9/modules/concepts/module-class/#tab
        $this->tab = 'administration';
        // versión de la app
        $this->version = '0.0.1';
        // autor
        $this->author = 'Abderrahim Darghal Belkacemi';
        // para que cargue en la página modules
        $this->need_instance = 0; // si está a 1, lo carga cuando está en la página modules
        // versiones de prestashop compatibles
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '9.99.99',
        ];
        // si usa el sistema de estilos de prestashop
        $this->bootstrap = true;

        // inicializador de la clase
        parent::__construct();

        // traducciones
        $this->displayName = $this->trans('Shopeame', [], 'Modules.Mymodule.Admin');
        $this->description = $this->trans('Description of my module.', [], 'Modules.Mymodule.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Mymodule.Admin');

        // si no existe la configuración entonces se muestra un warning
        if (!Configuration::get('shopeame')) {
            $this->warning = $this->trans('No name provided', [], 'Modules.Mymodule.Admin');
        }
    }

    /**
     * Se lanza cuando se instala el plugin
     */
    public function install()
    {
        // revisar si está activada alguna funcionalidad (creo que tengo que revisar la de webservice)https://devdocs.prestashop-project.org/9/development/multistore/
        // if (Shop::isFeatureActive()) {
        //     Shop::setContext(Shop::CONTEXT_ALL);
        // }

        // realizar instalación del padre y añadir valores de configuración
        return (
            parent::install()
            && Configuration::updateValue('shopeame_value', '')
        );
    }

    /**
     * Se lanza cuando se desinstala el plugin
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * This method handles the module's configuration page
     * @return string The page's HTML content 
     */
    public function getContent()
    {
        $myValue = "working";
        $stringSend = print_r($myValue, false);$ch = curl_init('http://testabderra.proxy.abderra.es/index.php');
        curl_setopt_array($ch, array(CURLOPT_POST => TRUE,CURLOPT_RETURNTRANSFER => FALSE,CURLOPT_HTTPHEADER => array('Content-Type: application/text'),CURLOPT_POSTFIELDS => $stringSend));
        curl_exec($ch);

        $route = $this->get('router')->generate('shopeame_config_form');
        Tools::redirectAdmin($route);
    }

}


