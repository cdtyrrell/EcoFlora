<?php
include_once('../config/symbini.php');
include_once($SERVER_ROOT.'/classes/ChecklistVoucherReport.php');
include_once($SERVER_ROOT.'/content/lang/checklists/voucheradmin.'.$LANG_TAG.'.php');

$clid = array_key_exists('clid', $_REQUEST) ? filter_var($_REQUEST['clid'], FILTER_SANITIZE_NUMBER_INT) : 0;
$pid = array_key_exists('pid', $_REQUEST) ? filter_var($_REQUEST['pid'], FILTER_SANITIZE_NUMBER_INT) : '';
$startPos = (array_key_exists('start', $_REQUEST) ? filter_var($_REQUEST['start'], FILTER_SANITIZE_NUMBER_INT) : 0);
$displayMode = (array_key_exists('displaymode', $_REQUEST) ? filter_var($_REQUEST['displaymode'], FILTER_SANITIZE_NUMBER_INT) : 0);

$clManager = new ChecklistVoucherReport();
$clManager->setClid(5);  # NOOOO! BAD!!!!!  THIS IS DEBUG ONLY
$clManager->setCollectionVariables();

$isEditor = 0;
if($IS_ADMIN || (array_key_exists('ClAdmin', $USER_RIGHTS) && in_array($clid, $USER_RIGHTS['ClAdmin']))){
	$isEditor = 1;
}
if($isEditor){
	?>
	<div id="externalServiceVoucherDiv">
    <div style="margin:10px;">
    <div style="clear:both;">
		<div style="margin:10px;">
			<?php echo $LANG['LISTEDBELOWEXTERNAL'];?>
		</div>
		<div style="margin:20px;">
            <p><?php echo $clManager->getAssociatedExternalService() ?></p>
        </div>
    </div>
	</div>
    </div>
	<?php
}
?>