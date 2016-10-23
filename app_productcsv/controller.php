<?php

/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');
require_once(JPATH_ADMINISTRATOR . '/components/com_j2store/library/appcontroller.php');

class J2StoreControllerAppProductcsv extends J2StoreAppController {

    var $_element = 'app_productcsv';

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->includeCustomModels();
        JFactory::getLanguage()->load('plg_j2store_' . $this->_element, JPATH_ADMINISTRATOR);
    }

    /**
     * Method to import the uploaded csv file
     */
    public function importCsv() {
        $this->includeCustomModel('AppProductCsv', 'J2StoreModel');
        $model = F0FModel::getTmpInstance('AppProductCsv', 'J2StoreModel');
        $type = $this->input->getString('importtype', 'file');

        try {
            $result = false;
            switch ($type) {
                case 'file' :
                    $result = $model->importFromFile();
                    break;
                case 'path' :
                    $result = $model->importFromPath();
                    break;
            }

            $msg = JText::_('J2STORE_PRODUCTCSV_IMPORT_SUCCESSFUL');
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $msgType = 'warning';
        }

        $url = $this->baseLink();
        $this->setRedirect($url, $msg, $msgType);
    }

}
