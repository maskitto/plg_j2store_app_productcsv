<?php

/**
 *
 * @package J2Store
 * @copyright Copyright (c)2016 maskitto / alphahost.net.br
 * @license GNU GPL v3 or later
 */
/**
 * ensure this file is being included by a parent file
 */
defined('_JEXEC') or die('Restricted access');
require_once (JPATH_ADMINISTRATOR . '/components/com_j2store/library/appmodel.php');

class J2StoreModelAppProductcsv extends J2StoreAppModel {

    public $_prices = array();
    public $_variants = array();
    var $delimiter = ',';
    var $listSeparators = array(
        ';',
        ',',
        "\t"
    );
    var $data = array();
    var $header = array();
    var $importdata = array();
    var $columns = array();
    var $totalInserted = 0;
    var $totalTry = 0;
    var $totalValid = 0;
    var $perBatch = 50;
    var $codes = array();
    var $characteristics = array();
    var $characteristicsConversionTable = array();
    var $characteristicColumns = array();
    var $countVariant = true;
    var $overwrite = false;
    var $products_already_in_db = array();
    var $new_variants_in_db = array();
    var $columnNamesConversionTable = array();
    var $createCategories = false;
    var $header_errors = true;
    var $force_published = true;
    var $tax_category = 0;
    var $default_file = '';
    var $charsetConvert = '';
    var $db;
    var $errors = array();
    var $rawimportdata;
    var $products;
    public $_element = 'app_productcsv';

    /**
     * Method to save data into the table
     *
     * @param array $data
     * @return result
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        $this->delimiter = $this->input->getString('delimiter', ',');
        $this->initialise();
    }

    function initialise() {
        if (!defined('DS'))
            define('DS', DIRECTORY_SEPARATOR);
        define('J2STORE_ROOT', rtrim(JPATH_ROOT, DS) . DS);

        $this->db = JFactory::getDbo();

        $this->fields = array('weight', 'introtext', 'fulltext', 'taxprofile_id', 'vendor_id', 'brand_desc_id', 'manufacturer_id', 'metadata', 'weight_class_id', 'length_class_id', 'width', 'length', 'height');
        $this->all_fields = array_merge($this->fields, array('product_name', 'enabled', 'sku', 'quantity_from', 'quantity_to', 'date_from', 'date_to', 'product_type', 'quantity'));

        $this->columnsContentTable = array_keys($this->db->getTableColumns('#__content'));
        $this->columnsProductTable = array_keys($this->db->getTableColumns('#__j2store_products'));
        $this->columnsVariantTable = array_keys($this->db->getTableColumns('#__j2store_variants'));
        $this->overwrite = $this->input->getInt('overwrite', 0);
        $this->charsetConvert = $this->input->getString('charsetconvert', '');
    }

    public function importFromFile() {
        $app = JFactory::getApplication();

        $importFile = $this->input->files->get('csvdata');
        if (empty($importFile ['name'])) {
            throw new Exception(JText::_('J2STORE_PRODUCTCSV_ERROR_NO_FILE_SELECTED'));
            return false;
        }

        $allowed = 'csv';

        $attachment = new stdClass ();
        $attachment->filename = strtolower(JFile::makeSafe($importFile ['name']));
        $attachment->size = $importFile ['size'];
        if (!preg_match('#\.(' . str_replace(array(
                            ',',
                            '.'
                                ), array(
                            '|',
                            '\.'
                                ), $allowed) . ')$#Ui', $attachment->filename, $extension) || preg_match('#\.(php.?|.?htm.?|pl|py|jsp|asp|sh|cgi)$#Ui', $attachment->filename)) {
            throw new Exception(JText::sprintf('J2STORE_PRODUCTCSV_ERROR_ACCEPTED_TYPE', substr($attachment->filename, strrpos($attachment->filename, '.') + 1), $allowed), 'notice');
            return false;
        }

        if ($this->readContent($importFile ['tmp_name'])) {
            return $this->handleImport();
        }

        return false;
    }

    public function importFromPath() {
        $filepath = $this->input->getString('csvfile_path', '');

        if (empty($filepath)) {
            throw new Exception(JText::_('J2STORE_PRODUCTCSV_ERROR_NO_FILE_FOUND_IN_PATH'));
        }
        $path = JPath::clean(J2STORE_ROOT . $filepath);
        if (JFile::exists($path) && JFile::getExt($path) == 'csv') {

            return $this->readContent($path);
        } else {
            throw new Exception(JText::_('J2STORE_PRODUCTCSV_ERROR_READING_FILE'));
            return false;
        }

        return false;
    }

    function readContent($file) {
        $app = JFactory::getApplication();
        $content = file_get_contents($file);
        if (!$content) {
            $app->enqueueMessage(JText::sprintf('FAIL_OPEN', $file), 'error');
            return false;
        };

        if (empty($this->charsetConvert)) {
            $this->charsetConvert = $this->detectEncoding($content);
        }
        return $this->handleImport($content);
    }

    public function handleImport(&$contentFile) {
        $app = JFactory::getApplication();
        $contentFile = str_replace(array("\r\n", "\r"), "\n", $contentFile);
        $this->importLines = explode("\n", $contentFile);
        $this->i = 0;
        while (empty($this->header)) {
            $this->header = trim($this->importLines[$this->i]);
            $this->i++;
        }

        // prepare the header
        if ($this->prepareHeader() == false) {
            return false;
        }

        $this->numberColumns = count($this->columns);
        $importProducts = array();
        $errorcount = 0;
        while ($data = $this->_getProduct()) {
            $this->totalTry ++;
            $newProduct = new stdClass ();
            foreach ($data as $num => $value) {
                if (!empty($this->columns [$num])) {
                    $field = $this->columns [$num];
                    if (strpos('|', $field) !== false) {
                        $field = str_replace('|', '__tr__', $field);
                    }
                    $newProduct->$field = preg_replace('#^[\'" ]{1}(.*)[\'" ]{1}$#', '$1', $value);
                    if (!empty($this->charsetConvert)) {
                        $newProduct->$field = $this->change($newProduct->$field, $this->charsetConvert, 'UTF-8');
                    }
                }
            }
            $this->_checkData($newProduct, true);

            if (!empty($newProduct->sku)) {
                $importProducts [] = $newProduct;
                if (count($this->currentProductVariants)) {
                    foreach ($this->currentProductVariants as $variant) {
                        $importProducts [] = $variant;
                    }
                }
                $this->totalValid ++;
            } else {
                $errorcount ++;
                if ($errorcount < 20) {
                    if (isset($this->importLines [$this->i - 1]))
                        $app->enqueueMessage(JText::sprintf('IMPORT_ERRORLINE', $this->importLines [$this->i - 1]) . ' ' . JText::_('PRODUCT_NOT_FOUND'), 'notice');
                } elseif ($errorcount == 20) {
                    $app->enqueueMessage('...', 'notice');
                }
            }

            if ($this->totalValid % $this->perBatch == 0) {
                $this->_insertProducts($importProducts);
                $importProducts = array();
            }
        }

        if (!empty($importProducts)) {
            $this->_insertProducts($importProducts);
        }
        $app->enqueueMessage(JText::sprintf('IMPORT_REPORT', $this->totalTry, $this->totalInserted, $this->totalTry - $this->totalValid, $this->totalValid - $this->totalInserted));
        return true;
    }

    function _checkData(&$product, $main = false) {

        $filter = JFilterInput::getInstance();
        $app = JFactory::getApplication();
        //print_r($product);
        //run basic checks and set default values
        foreach ($product as $key => $value) {
            $product->$key = str_replace("\\r\\n", '', $value);
        }

        //metrics
        if (!empty($product->weight)) {
            $product->weight = floatval($product->weight);
        }

        if (!empty($product->length)) {
            $product->length = floatval($product->length);
        }
        if (!empty($product->width)) {
            $product->width = floatval($product->width);
        }
        if (!empty($product->height)) {
            $product->height = floatval($product->height);
        }

        if (!isset($product->product_type) || empty($product->product_type)) {
            $product->product_type = 'simple';
        } else {
            if (!in_array($product->product_type, array('simple', 'variable', 'configurable', 'downloadable'))) {
                $product->product_type = 'simple';
            }
        }

        if (!isset($product->catid) || empty($product->catid)) {
            $product->catid = '2';
        }

        //auto publish the article
        if (!isset($product->state) || empty($product->state)) {
            $product->state = '1';
        }
        //access
        if (!isset($product->access) || empty($product->access)) {
            $product->access = '1';
        }

        if (!isset($product->created_by) || empty($product->created_by)) {
            $product->created_by = JFactory::getUser()->id;
        }

        if (!isset($product->publish_up) || empty($product->publish_up)) {
            $product->publish_up = $this->db->getNullDate();
        }

        if (!isset($product->publish_down) || empty($product->publish_down)) {
            $product->publish_down = $this->db->getNullDate();
        }


        if (!isset($product->enabled)) {
            $product->enabled = 1;
        }

        if (!isset($product->visibility)) {
            $product->visibility = 1;
        }

        if (!isset($product->created)) {
            $product->created = JFactory::getDate()->toSql(true);
        }

        if (!empty($product->product_id) && isset($product->sku) && empty($product->sku)) {
            $query = $this->db->getQuery(true)->select('sku')->from('#__j2store_variants')->where('product_id=' . (int) $product->product_id);
            $this->db->setQuery($query);
            $product->sku = $this->db->loadResult();
        } else
        if (isset($product->sku) && empty($product->sku) && !empty($product->product_name)) {
            $test = preg_replace('#[^a-z0-9_-]#i', '', $product->product_name);
            if (empty($test)) {
                static $last_pid = null;
                if ($last_pid === null) {
                    $query = 'SELECT MAX(`j2store_product_id`) FROM #__j2store_products';
                    $this->db->setQuery($query);
                    $last_pid = (int) $this->db->loadResult();
                }
                $last_pid++;
                $product->sku = 'product_' . $last_pid;
            } else {
                $product->sku = preg_replace('#[^a-z0-9_-]#i', '_', $product->product_name);
            }
        }
    }

    function &_getProduct() {
        $false = false;
        if (!isset($this->importLines[$this->i])) {
            return $false;
        }
        if (empty($this->importLines[$this->i])) {
            $this->i++;
            return $this->_getProduct();
        }

        $quoted = false;
        $dataPointer = 0;
        $data = array('');

        while ($data !== false && isset($this->importLines[$this->i]) && (count($data) < $this->numberColumns || $quoted)) {
            $k = 0;
            $total = strlen($this->importLines[$this->i]);
            while ($k < $total) {
                switch ($this->importLines[$this->i][$k]) {
                    case '"':

                        if ($quoted && isset($this->importLines[$this->i][$k + 1]) && $this->importLines[$this->i][$k + 1] == '"') {
                            $data[$dataPointer].='"';
                            $k++;
                        } elseif ($quoted) {
                            $quoted = false;
                        } elseif (empty($data[$dataPointer])) {
                            $quoted = true;
                        } else {
                            $data[$dataPointer].='"';
                        }
                        break;
                    case $this->separator:
                        if (!$quoted) {
                            $data[] = '';
                            $dataPointer++;
                            break;
                        }
                    default:
                        $data[$dataPointer].=$this->importLines[$this->i][$k];
                        break;
                }
                $k++;
            }

            $this->_checkLineData($data);

            if (count($data) < $this->numberColumns || $quoted) {
                $data[$dataPointer].="\r\n";
            }

            $this->i++;
        }

        if ($data != false) {
            $this->_checkLineData($data, false);
        }
        return $data;
    }

    function _checkLineData(&$data, $type = true) {
        if ($type) {
            $not_ok = count($data) > $this->numberColumns;
        } else {
            $not_ok = count($data) != $this->numberColumns;
        }
        if ($not_ok) {
            static $errorcount = 0;
            if (empty($errorcount)) {
                $app = JFactory::getApplication();
                $app->enqueueMessage(JText::sprintf('IMPORT_ARGUMENTS', $this->numberColumns), 'error');
            }
            $errorcount++;
            if ($errorcount < 20) {
                $app = JFactory::getApplication();
                $app->enqueueMessage(JText::sprintf('IMPORT_ERRORLINE', $this->importLines[$this->i]), 'notice');
                $data = $this->_getProduct();
            } elseif ($errorcount == 20) {
                $app = JFactory::getApplication();
                $app->enqueueMessage('...', 'notice');
            }
        }
    }

    function prepareHeader() {
        $app = JFactory::getApplication();
        $this->separator = ',';
        $this->header = str_replace("\xEF\xBB\xBF", "", $this->header);

        foreach ($this->listSeparators as $sep) {
            if (strpos($this->header, $sep) !== false) {
                $this->separator = $sep;
                break;
            }
        }
        $this->columns = explode($this->separator, $this->header);
        $this->translateColumns = array();
        $columns = $this->getImportColumns();

        foreach ($this->columns as $i => $oneColumn) {
            if (function_exists('mb_strtolower')) {
                $this->columns [$i] = mb_strtolower(trim($oneColumn, '" '));
            } else {
                $this->columns [$i] = strtolower(trim($oneColumn, '" '));
            }
            $this->columns [$i] = strtolower(trim($oneColumn, '" '));

            foreach ($this->columns as $k => $otherColumn) {
                if ($i != $k && $this->columns [$i] == strtolower($otherColumn)) {
                    $app->enqueueMessage('The column "' . $this->columns [$i] . '" is twice in your CSV. Only the second column data will be taken into account.', 'error');
                }
            }

            if (!isset($columns [$this->columns [$i]])) {
                if (isset($this->columnNamesConversionTable [$this->columns [$i]])) {
                    if (is_array($this->columnNamesConversionTable [$this->columns [$i]])) {
                        $this->columnNamesConversionTable [$this->columnNamesConversionTable [$this->columns [$i]] ['name']] = $this->columnNamesConversionTable [$this->columns [$i]];
                        $this->columns [$i] = $this->columnNamesConversionTable [$this->columns [$i]] ['name'];
                    } else {
                        $this->columns [$i] = $this->columnNamesConversionTable [$this->columns [$i]];
                    }
                } else {
                    if (isset($this->characteristicsConversionTable [$this->columns [$i]])) {
                        $this->characteristicColumns [] = $this->columns [$i];
                    } else {
                        $possibilities = array_diff(array_keys($columns), array('product_id'));

                        if (!empty($this->characteristics)) {
                            foreach ($this->characteristics as $char) {
                                if (empty($char->characteristic_parent_id)) {
                                    if (function_exists('mb_strtolower')) {
                                        $possibilities [] = mb_strtolower(trim($char->characteristic_value, ' "'));
                                    } else {
                                        $possibilities [] = strtolower(trim($char->characteristic_value, ' "'));
                                    }
                                }
                            }
                        }
                        if ($this->header_errors) {
                            $app->enqueueMessage(JText::sprintf('IMPORT_ERROR_FIELD', $this->columns [$i], implode(' | ', $possibilities)), 'error');
                        }
                    }
                }
            }
        }
        return true;
    }

    public function getImportColumns() {
        $columns = $this->db->getTableColumns('#__j2store_products');

        // also get columns from variant table
        $variant_columns = $this->db->getTableColumns('#__j2store_variants');

        $columns = array_merge($columns, $variant_columns);
        $content_columns = $this->db->getTableColumns('#__content');
        $columns = array_merge($columns, $content_columns);

        // add more for article table
        $columns ['product_name'] = 'product_name';
        $columns ['introtext'] = 'introtext';
        $columns ['fulltext'] = 'fulltext';
        $columns ['catid'] = 'catid';
        $columns ['metakey'] = 'metakey';
        $columns ['metadesc'] = 'metadesc';
        $columns ['access'] = 'access';

        // price table
        $columns ['quantity_from'] = 'quantity_from';
        $columns ['quantity_to'] = 'quantity_to';
        $columns ['date_from'] = 'date_from';
        $columns ['date_to'] = 'date_to';
        $columns ['customer_group'] = 'customer_group';
        $columns ['price_value'] = 'price_value';

        // inventory
        $columns ['quantity'] = 'quantity';

        // images
        $columns ['main_image'] = 'main_image';
        $columns ['thumb_image'] = 'thumb_image';
        $columns ['additional_images'] = 'additional_images';

        //manufacture
        $columns ['brand_desc_id'] = 'brand_desc_id';
        return $columns;
    }

    function _insertProducts(&$products) {
        $this->_insertAllProducts($products, 'simple');
        $this->_insertAllProducts($products, 'configurable');
        $this->_insertAllProducts($products, 'downloadable');
        $this->products = & $products;
        return true;
    }

    function _insertAllProducts($products, $type = "simple") {
        if (empty($products)) {
            return true;
        }

        $app = JFactory::getApplication();
        // first get the SKUs and check if already exists
        $codes = array();

        foreach ($products as $product) {
            if ($product->product_type != $type) {
                continue;
            }
            $codes [$product->sku] = $this->db->Quote($product->sku);
        }

        if (!empty($codes)) {
            $query = $this->db->getQuery(true)
                    ->select('v.*, p.product_type')
                    ->from('#__j2store_variants AS v')
                    ->where('sku IN (' . implode(',', $codes) . ')')
                    ->innerJoin('#__j2store_products AS p ON v.product_id = p.j2store_product_id');

            $this->db->setQuery($query);
            $already = $this->db->loadObjectList('product_id');

            if (!empty($already)) {
                foreach ($already as $code) {
                    $found = false;

                    foreach ($products as $k => $product) {
                        if ($product->sku == $code->sku) {
                            $found = $k;
                            break;
                        }
                    }

                    if ($found !== false) {
                        if ($this->overwrite) {
                            if (!empty($products [$found]->product_type) && !empty($code->product_type) && $products [$found]->product_type == $code->product_type) {
                                $products [$found]->product_id = $code->product_id;
                                $products [$found]->j2store_update = true;
                            } else {
                                $app = JFactory::getApplication();
                                $app->enqueueMessage('The product ' . $products [$found]->sku . ' is of the type ' . $products [$found]->product_type . ' but it already exists in the database and is of the type ' . $code->product_type . '. In order to avoid any problem the product insertion process has been skipped. Please correct its type before trying to reimport it.', 'error');
                                unset($products [$found]);
                            }
                        } else {
                            unset($products [$found]);
                        }
                    }
                }
            }

            $exist = 0;

            if (!empty($codes)) {
                foreach ($products as $product) {
                    if ($product->product_type != $type || empty($codes[$product->sku]))
                        continue;

                    //we have to work our magic from here.
                    if (isset($product->j2store_update) && $product->j2store_update) {
                        //this product is already present. So just update it.
                        $keys = array_keys((array) ($product));

                        //product table
                        $productTable = F0FTable::getInstance('Product', 'J2StoreTable')->getClone();
                        if ($productTable->load(array('j2store_product_id' => $product->product_id))) {
                            $newProduct = new stdClass;
                            $fields = $this->getFields('products');

                            foreach ($fields as $field) {
                                if (in_array($field, $keys)) {
                                    $newProduct->$field = $product->$field;
                                }
                            }

                            $newProduct->j2store_product_id = $product->product_id;

                            try {
                                $this->db->updateObject('#__j2store_products', $newProduct, 'j2store_product_id');
                            } catch (Exception $e) {
                                //do nothing
                            }

                            //now time to load the content table
                            if ($productTable->product_source == 'com_content') {
                                $contentTable = JTable::getInstance('Content');

                                if ($contentTable->load($productTable->product_source_id)) {
                                    $fields = $this->getFields('content', false);
                                    $content = new stdClass();

                                    foreach ($fields as $field) {
                                        if (in_array($field, $keys)) {
                                            $content->$field = $product->$field;
                                        }
                                    }

                                    if (isset($product->product_name) && !empty($product->product_name)) {
                                        $content->title = $product->product_name;
                                    }

                                    $content->id = $productTable->product_source_id;

                                    try {
                                        $this->db->updateObject('#__content', $content, 'id');
                                    } catch (Exception $e) {
                                        
                                    }
                                }
                            }

                            //now insert variant
                            $variantTable = F0FTable::getInstance('Variant', 'J2StoreTable')->getClone();
                            if ($variantTable->load(array('product_id' => $product->product_id, 'is_master' => 1))) {
                                $fields = $this->getFields('variants');
                                $variant = new stdClass();

                                foreach ($fields as $field) {
                                    if (in_array($field, $keys)) {
                                        $variant->$field = $product->$field;
                                    }
                                }

                                $variant->j2store_variant_id = $variantTable->j2store_variant_id;

                                try {
                                    $this->db->updateObject('#__j2store_variants', $variant, 'j2store_variant_id');

                                    //insert quantity
                                    $this->_insertQuantity($product, $variantTable->j2store_variant_id);
                                } catch (Exception $e) {
                                    
                                }
                            }

                            //now insert images
                            $this->_insertImages($product, $productTable->j2store_product_id);
                            $exist++;
                        }

                        $this->totalValid++;
                        $this->totalInserted ++;
                    } else {
                        //this is a new product
                        //first we should insert basic data in the article table
                        $this->_insertContent($product);

                        //now insert product
                        $this->_insertNewProduct($product);

                        //now insert variants
                        $product->is_master = 1;
                        $this->_insertNewVariant($product);

                        //now insert quantity
                        $this->_insertQuantity($product, $product->variant_id);

                        //now insert images
                        $this->_insertImages($product, $product->product_id);

                        $this->totalValid++;
                        $this->totalInserted ++;
                    }
                }

                $this->totalInserted = $this->totalInserted - $exist;
            }
        }
    }

    public function _insertContent(&$product) {
        $contentTable = JTable::getInstance('Content');
        $fields = $this->getFields('content', false);
        $content = new stdClass();

        foreach ($fields as $field) {
            if (isset($product->$field) && !empty($product->$field)) {
                $content->$field = $product->$field;
            }
        }

        if (isset($product->product_name) && !empty($product->product_name)) {
            $content->title = $product->product_name;
        }

        $this->db->insertObject('#__content', $content);
        $product->product_source_id = $this->db->insertid();
        $product->product_source = 'com_content';
    }

    public function _insertNewProduct(&$product) {
        $fields = $this->getFields('products');
        $object = new stdClass();

        foreach ($fields as $field) {
            if (isset($product->$field) && !empty($product->$field)) {
                $object->$field = $product->$field;
            }
        }

        $this->db->insertObject('#__j2store_products', $object);
        $product->product_id = $this->db->insertid();
    }

    public function _insertNewVariant(&$product) {
        $fields = $this->getFields('variants');
        $object = new stdClass();

        foreach ($fields as $field) {
            if (isset($product->$field) && !empty($product->$field)) {
                $object->$field = $product->$field;
            }
        }

        $this->db->insertObject('#__j2store_variants', $object);
        $product->variant_id = $this->db->insertid();
    }

    public function _insertQuantity($product, $variant_id) {

        //if quantity field is set, we need to import that as well
        if (isset($product->quantity) && !empty($product->quantity)) {
            if ($variant_id) {
                $quantityTable = F0FTable::getInstance('Productquantity', 'J2StoreTable')->getClone();
                $quantityTable->load(array('variant_id' => $variant_id));
                $quantityTable->quantity = intval($product->quantity);
                $quantityTable->variant_id = $variant_id;
                $quantityTable->store();
            }
        }
    }

    public function _insertImages($product, $product_id) {
        $keys = array_keys((array) ($product));

        if ($product_id) {
            $imageTable = F0FTable::getInstance('Productimage', 'J2StoreTable')->getClone();
            if ($imageTable->load(array('product_id' => $product_id))) {
                $fields = $this->getFields('productimages');
                $image = new stdClass();

                foreach ($fields as $field) {
                    if (isset($product->$field) && !empty($product->$field)) {
                        if ($field == 'additional_images') {
                            $additional_images = explode('|', $product->$field);
                            if (count($additional_images)) {
                                for ($i = 0; $i < count($additional_images); $i++) {
                                    if (filter_var($additional_images[$i], FILTER_VALIDATE_URL, array('flags' => FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED))) {
                                        $additional_images[$i] = $this->getImageFromURL($additional_images[$i]);
                                    }
                                }

                                $image->$field = json_encode((array) $additional_images);
                            }
                        } else {
                            if (filter_var($product->$field, FILTER_VALIDATE_URL, array('flags' => FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED))) {
                                $image->$field = $this->getImageFromURL($product->$field, $field == 'thumb_image');
                            } else {
                                $image->$field = $product->$field;
                            }
                        }
                    }
                }

                $image->j2store_productimage_id = $imageTable->j2store_productimage_id;
                $image->product_id = $product_id;
                $this->db->updateObject('#__j2store_productimages', $image, 'j2store_productimage_id');
            } else {
                $fields = $this->getFields('productimages');
                $image = new stdClass();

                foreach ($fields as $field) {
                    if (in_array($field, $keys)) {
                        if ($field == 'additional_images' && isset($product->$field)) {
                            $additional_images = explode('|', $product->$field);
                            if (count($additional_images)) {
                                $image->$field = json_encode((array) $additional_images);
                            }
                        } else {
                            $image->$field = $product->$field;
                        }

                        $image->$field = $product->$field;
                    }
                }

                $image->product_id = $product_id;
                $this->db->insertObject('#__j2store_productimages', $image);
            }
        }
    }

    public function getFields($table, $j2store_table = true) {
        $prefix = ($j2store_table) ? '#__j2store_' : '#__';
        return array_keys($this->db->getTableColumns($prefix . $table));
    }

    public function getExportData() {

        //export the real products
        $products = F0FModel::getTmpInstance('Products', 'J2StoreModel')->enabled(1)->product_source('com_content')->getList();
        $newproducts = array();
        foreach ($products as $product) {
            $table = F0FTable::getAnInstance('Product', 'J2StoreTable');
            $table->load($product->j2store_product_id);
            $product_array = JArrayHelper::fromObject($table);
            $source = $product->source;
            $product_id = $product_array['j2store_product_id'];
            $this->unsetVariables($product_array);
            $this->unsetVariables($source);
            $newproduct = array_merge($product_array, (array) $source);

            //load the variant
            $variant = F0FModel::getTmpInstance('Variants', 'J2StoreModel')->product_id($product_id)->is_master(1)->getList();

            if (isset($variant[0])) {
                $this->unsetVariables($variant[0]);
                $newproduct = array_merge($newproduct, (array) $variant[0]);
            }

            //process the additional images
            if (isset($newproduct['additional_images'])) {
                $images = json_decode($newproduct['additional_images']);

                if (count($images)) {
                    $newproduct['additional_images'] = implode('|', (array) $images);
                }
            }

            $newproduct = array_merge(array('product_id' => $product_id), $newproduct);
            $newproducts[] = (object) $newproduct;
        }

        return $newproducts;
    }

    function unsetVariables(&$data) {
        $vars = array(
            'source',
            'id',
            'asset_id',
            'attribs',
            'images',
            'urls',
            'product_view_url',
            'product_edit_url',
            'exists',
            'j2store_productquantity_id',
            'j2store_product_id',
            'product_id',
            'pricing_calculator',
            'j2store_productimage_id',
            'j2store_variant_id',
            'manufacturer_first_name',
            'manufacturer_last_name',
            'manufacturer',
            'length_title',
            'length_unit',
            'weight_title',
            'weight_unit',
            'category_title',
            'category_alias',
            'category_access',
            'catslug',
            'slug',
        );

        foreach ($vars as $key) {
            if (is_object($data)) {
                unset($data->$key);
            } elseif (is_array($data)) {
                unset($data[$key]);
            }
        }
    }

    function detectEncoding(&$content) {
        if (!function_exists('mb_check_encoding')) {
            return '';
        }

        $toTest = array('UTF-8');
        $lang = JFactory::getLanguage();
        $tag = $lang->getTag();

        if ($tag == 'el-GR') {
            $toTest [] = 'ISO-8859-7';
        }

        $toTest [] = 'ISO-8859-1';
        $toTest [] = 'ISO-8859-2';
        $toTest [] = 'Windows-1252';

        foreach ($toTest as $oneEncoding) {
            if (mb_check_encoding($content, $oneEncoding))
                return $oneEncoding;
        }

        return '';
    }

    function change($data, $input, $output) {
        $input = strtoupper(trim($input));
        $output = strtoupper(trim($output));

        if ($input == $output) {
            return $data;
        }

        if ($input == 'UTF-8' && $output == 'ISO-8859-1') {
            $data = str_replace(array('�', '�', '�'), array('EUR', '"', '"'), $data);
        }

        if (function_exists('iconv')) {
            set_error_handler('j2store_error_handler_encoding');
            $encodedData = iconv($input, $output . "//IGNORE", $data);
            restore_error_handler();

            if (!empty($encodedData) && !j2store_error_handler_encoding('result')) {
                return $encodedData;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            return @mb_convert_encoding($data, $output, $input);
        }

        if ($input == 'ISO-8859-1' && $output == 'UTF-8') {
            return utf8_encode($data);
        }

        if ($input == 'UTF-8' && $output == 'ISO-8859-1') {
            return utf8_decode($data);
        }

        return $data;
    }

    public function getImageFromURL($imageURL, $thumb = false) {
        $app = JFactory::getApplication();
        $fileName = pathinfo($imageURL, PATHINFO_BASENAME);
        $savePath = JPATH_ROOT . DS . 'images' . DS . 'products' . DS . ($thumb ? 'thumbs' . DS : '') . $fileName;
        $imagePath = 'images' . DS . 'products' . DS . ($thumb ? 'thumbs' . DS : '') . $fileName;
        $ch = curl_init($imageURL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        try {
            $raw = curl_exec($ch);
        } catch (Exception $ex) {
            curl_close($ch);
            $app->enqueueMessage('Erro ao carregar imagem. ' . $ex->getMessage(), 'error');
            return '';
        }

        curl_close($ch);

        if (empty($raw)) {
            return '';
        }

        if (file_exists($savePath)) {
            unlink($savePath);
        }

        try {
            $fp = fopen($savePath, 'x');
        } catch (Exception $ex) {
            $app->enqueueMessage('Erro ao criar arquivo. ' . $ex->getMessage(), 'error');
            return '';
        }

        try {
            fwrite($fp, $raw);
        } catch (Exception $ex) {
            fclose($fp);
            $app->enqueueMessage('Erro ao salvar arquivo. ' . $ex->getMessage(), 'error');
            return '';
        }

        fclose($fp);
        return $imagePath;
    }

}

function j2store_error_handler_encoding($errno, $errstr = '') {
    static $error = false;

    if (is_string($errno) && $errno == 'result') {
        $currentError = $error;
        $error = false;
        return $currentError;
    }

    $error = true;
    return true;
}
