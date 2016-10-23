<?php

/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');
require_once(JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/app.php');

class plgJ2StoreApp_Productcsv extends J2StoreAppPlugin {

    /**
     * @var $_element  string  Should always correspond with the plugin's filename,
     *                         forcing it to be unique
     */
    var $_element = 'app_productcsv';

    function __construct(&$subject, $config) {
        parent::__construct($subject, $config);
        JFactory::getLanguage()->load('plg_j2store_' . $this->_element, JPATH_ADMINISTRATOR);
    }

    /**
     * Overriding
     *
     * @param $options
     * @return unknown_type
     */
    function onJ2StoreGetAppView($row) {
        if (!$this->_isMe($row)) {
            return null;
        }

        $html = $this->viewList();
        return $html;
    }

    /**
     * Validates the data submitted based on the suffix provided
     * A controller for this plugin, you could say
     *
     * @param $task
     * @return html
     */
    function viewList() {
        $app = JFactory::getApplication();
        $option = 'com_j2store';
        $ns = $option . '.tool';
        $html = "";
        JToolBarHelper::title(JText::_('J2STORE_APP') . '-' . JText::_('PLG_J2STORE_' . strtoupper($this->_element)), 'j2store-logo');
        JToolBarHelper::back('J2STORE_BACK_TO_DASHBOARD', 'index.php?option=com_j2store');
        $vars = new JObject();
        $this->includeCustomModel('AppProductcsv');
        $this->includeCustomTables();
        //$model = F0FModel::getTmpInstance('ToolDiagnostics', 'J2StoreModel');
        $id = $app->input->getInt('id', '0');
        $vars->id = $id;
        $values = array();
        $charsets = $this->getCharacterSets();
        $values[] = JHTML::_('select.option', JText::_('J2STORE_UNKNOWN'), '');

        foreach ($charsets as $code => $charset) {
            $values[] = JHTML::_('select.option', $code, $charset);
        }

        $vars->charset_list = JHTML::_('select.genericlist', $values, 'charsetconvert', 'size="1"', 'value', 'text', $app->input->getString('charsetconvert', ''));
        $form = array();
        $form['action'] = "index.php?option=com_j2store&view=app&task=view&id={$id}";
        $vars->form = $form;
        $html = $this->_getLayout('default', $vars);
        return $html;
    }

    function onJ2StoreAppExportCsv($row) {
        $app = JFactory::getApplication();
        if (!$this->_isMe($row)) {
            return null;
        }
        $this->includeCustomModel('AppProductcsv');
        $model = F0FModel::getTmpInstance('Appproductcsv', 'J2StoreModel');
        $data = $model->getExportData();
        return $data;
    }

    public function getCharacterSets() {
        $charsets = array(
            'BIG5' => 'BIG5', //Iconv,mbstring
            'ISO-8859-1' => 'ISO-8859-1', //Iconv,mbstring
            'ISO-8859-2' => 'ISO-8859-2', //Iconv,mbstring
            'ISO-8859-3' => 'ISO-8859-3', //Iconv,mbstring
            'ISO-8859-4' => 'ISO-8859-4', //Iconv,mbstring
            'ISO-8859-5' => 'ISO-8859-5', //Iconv,mbstring
            'ISO-8859-6' => 'ISO-8859-6', //Iconv,mbstring
            'ISO-8859-7' => 'ISO-8859-7', //Iconv,mbstring
            'ISO-8859-8' => 'ISO-8859-8', //Iconv,mbstring
            'ISO-8859-9' => 'ISO-8859-9', //Iconv,mbstring
            'ISO-8859-10' => 'ISO-8859-10', //Iconv,mbstring
            'ISO-8859-13' => 'ISO-8859-13', //Iconv,mbstring
            'ISO-8859-14' => 'ISO-8859-14', //Iconv,mbstring
            'ISO-8859-15' => 'ISO-8859-15', //Iconv,mbstring
            'ISO-2022-JP' => 'ISO-2022-JP', //mbstring for sure... not sure about Iconv
            'US-ASCII' => 'US-ASCII', //Iconv,mbstring
            'UTF-7' => 'UTF-7', //Iconv,mbstring
            'UTF-8' => 'UTF-8', //Iconv,mbstring
            'Windows-1250' => 'Windows-1250', //Iconv,mbstring
            'Windows-1251' => 'Windows-1251', //Iconv,mbstring
            'Windows-1252' => 'Windows-1252' //Iconv,mbstring
        );

        if (function_exists('iconv')) {
            $charsets['ARMSCII-8'] = 'ARMSCII-8';
            $charsets['ISO-8859-16'] = 'ISO-8859-16';
        }

        return $charsets;
    }

}
