<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
defined('_JEXEC') or die;
?>

<div class="alert alert-block alert-warning">
    <strong><?php echo JText::_('J2STORE_WARNING'); ?></strong>
    <p><?php echo JText::_('J2STORE_PRODUCTCSV_WARNING_MESSAGE'); ?></p>
</div>
<div class="alert alert-block alert-info">
    <strong><?php echo JText::_('J2STORE_PRODUCTCSV_INSTRUCTIONS') ?></strong>:
    <ol>
        <li>BACKUP your site using Akeeba Backup before using this app. It is SUPER DUPER IMPORTANT.</li>
        <li>Export Products to see how the CSV is formatted. Check the field names. The field names should match.</li>
        <li>if you are importing new products, then you can ignore the product_id field. This will create a new product.</li>
        <li>Make sure SKU of your products are unique. Duplicate SKUs will result in an error.</li>
        <li>The app depends on the SKU to find the product. If product found, it will update. If it does not, it will create a new product. So make sure the SKU field is right. Double, triple check</li>
        <li>Refer this documentation on exporting your <a target="_blank" href="https://support.office.com/en-gb/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba">Excel file as CSV</a></li>
        <li>Support for importing product options / variants is not yet available</li>
    </ol>
</div>


<form class="form-horizontal form-validate" id="adminForm" 	name="adminForm" method="post" action="index.php" enctype="multipart/form-data">
    <?php echo J2Html::hidden('option', 'com_j2store'); ?>
    <?php echo J2Html::hidden('view', 'apps'); ?>
    <?php echo J2Html::hidden('task', 'view', array('id' => 'task')); ?>
    <?php echo J2Html::hidden('appTask', '', array('id' => 'appTask')); ?>
    <?php echo J2Html::hidden('table', '', array('id' => 'table')); ?>
    <?php echo J2Html::hidden('id', $vars->id, array('id' => 'id')); ?>
    <?php echo JHTML::_('form.token'); ?>
    <div class="row-fluid">
        <div class="span6" id="product-csv-container">
            <div class="control-group">
                <label class="control-label">
                    <?php echo JText::_('PLG_J2STORE_APP_PRODUCTCSV_CHOOSE_IMPORT_TYPE'); ?>
                </label>
                <div class="controls">
                    <fieldset class="radio btn-group btn-group-yesno">
                        <input id="importtype0" type="radio" name="importtype" value="file">
                        <label for="importtype0" class="btn btn-success"><?php echo JText::_('PLG_J2STORE_PRODUCTCSV_IMPORT_TYPE_FILE') ?></label>
                        <input id="importtype1" type="radio" name="importtype" value="path">
                        <label for="importtype1" class="btn"><?php echo JText::_('PLG_J2STORE_PRODUCTCSV_IMPORT_TYPE_PATH') ?></label>
                    </fieldset>
                </div>
            </div>
            <div id="input-file" class="control-group" style="display:none;">
                <label class="control-label">
                    <?php echo JText::_('PLG_J2STORE_APP_PRODUCTCSV_IMPORT_FILE'); ?>
                </label>
                <div class="controls">
                    <input type="file" name="csvdata" id="importCsv" /> <?php echo JTExt::_('PLG_J2STORE_APP_PRODUCTCSV_MAX_SIZE_HELP'); ?>
                </div>
            </div>
            <div id="input-path" class="control-group" style="display:none;">
                <label class="control-label">
                    <?php echo JText::_('PLG_J2STORE_APP_PRODUCTCSV_ENTER_FILEPATH'); ?>
                </label>
                <div class="controls">
                    <?php echo JPATH_SITE; ?><input type="text" name="csvfile_path"/>
                </div>
            </div>
            <div class="control-group form-inline">
                <label class="control-label">
                    <?php echo JText::_('PLG_J2STORE_APP_PRODUCTCSV_CHARSET'); ?>
                </label>
                <?php echo $vars->charset_list; ?>
            </div>
            <div class="control-group form-inline">
                <label class="control-label">
                    <?php echo JText::_('PLG_J2STORE_APP_PRODUCTCSV_OVERWRITE_PRODUCT_IF_SKU_MATCHES'); ?>
                </label>
                <?php echo JHtmlSelect::booleanlist('overwrite', array('class' => 'inline')); ?>
            </div>
            <div class="control-group">
                <div class="controls">
                    <input class="btn btn-success" onclick="jQuery('#appTask').attr('value', 'importCsv');
                            jQuery(this).attr('disable', 'disable');"  type="submit"  value="<?php echo JText::_('J2STORE_IMPORT'); ?>" />
                </div>
            </div>
        </div>
        <div class="span6">
            <div class="alert alert-block alert-warning">
                <?php echo JText::_('J2STORE_PRODUCTCSV_EXPORT_DATA_HELP_TEXT'); ?>
                <br />
                <a class="btn btn-large btn-success" onclick="this.disable" href="index.php?option=com_j2store&view=apps&format=csv&task=view&app_id=<?php echo $vars->id; ?>" ><?php echo JText::_('J2STORE_EXPORT_PRODUCTS'); ?></a>
            </div>
        </div>
    </div>
</form>
<script type="text/javascript">
    (function ($) {
        var labelID;
        $('label').click(function () {
            labelID = $(this).attr('for');
            $('#' + labelID).trigger('click');
        });

        $(document).on('click', '#product-csv-container input[name=\'importtype\']', function () {
            if ($(this).attr('value') == 'path') {
                jQuery('#input-path').show();
                jQuery('#input-file').hide();
            } else {
                jQuery('#input-file').show();
                jQuery('#input-path').hide();
            }
        });

        $('#product-csv-container label[for=\'importtype0\']').trigger('click');
    })(j2store.jQuery);
</script>