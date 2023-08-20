<?php
include_once('../config/symbini.php');
include_once($SERVER_ROOT.'/classes/ChecklistVoucherReport.php');
include_once($SERVER_ROOT.'/classes/ChecklistManager.php');
include_once($SERVER_ROOT.'/content/lang/checklists/voucheradmin.'.$LANG_TAG.'.php');

$clid = array_key_exists('clid', $_REQUEST) ? filter_var($_REQUEST['clid'], FILTER_SANITIZE_NUMBER_INT) : 0;
$pid = array_key_exists('pid', $_REQUEST) ? filter_var($_REQUEST['pid'], FILTER_SANITIZE_NUMBER_INT) : '';
$startPos = (array_key_exists('start', $_REQUEST) ? filter_var($_REQUEST['start'], FILTER_SANITIZE_NUMBER_INT) : 0);
$displayMode = (array_key_exists('displaymode', $_REQUEST) ? filter_var($_REQUEST['displaymode'], FILTER_SANITIZE_NUMBER_INT) : 0);

$clManager = new ChecklistManager();
$clVoucherManager = new ChecklistVoucherReport();
if($clid) {
	$clManager->setClid($clid);
	$clVoucherManager->setClid($clid);
	//$clVoucherManager->setCollectionVariables();
}

$isEditor = 0;
if($IS_ADMIN || (array_key_exists('ClAdmin', $USER_RIGHTS) && in_array($clid, $USER_RIGHTS['ClAdmin']))){
	$isEditor = 1;
}
if($isEditor){
	$taxaArray = $clManager->getTaxaList();
	?>
	<div id="externalServiceVoucherDiv">
    <div style="margin:10px;">
    <div style="clear:both;">
		<div style="margin:10px;">
			<?php echo $LANG['LISTEDBELOWEXTERNAL'];?>
		</div>
		<div style="margin:20px;">
		<style>
			#taxalist-div {
				margin-bottom: 10px;
			}
			label {
				display: inline-block;
				width: 150px;
				text-align: right;
			}
		</style>
			<?php
			echo '<div id="taxalist-div">';
			echo '<button type="submit">'.$LANG['SAVEEXTVOUCH'].'</button>';
			$prevGroup = '';
			foreach($taxaArray as $tid => $sppArr){
				$group = $sppArr['taxongroup'];
				if($group != $prevGroup){
					$famUrl = '../taxa/index.php?taxauthid=1&taxon='.strip_tags($group).'&clid='.$clid;
					//Edit family name display style here
					?>
					<div class="family-div" id="<?php echo strip_tags($group);?>">
						<a href="<?php echo $famUrl; ?>" target="_blank" style="color:black;"><?php echo $group;?></a>
					</div>
					<?php
					$prevGroup = $group;
				}
				$taxonWithDashes = str_replace(' ', '-', $sppArr['sciname']);
				echo '<div class="taxon-container">';
				echo '<a href="#" target="_blank" id="a-' . $taxonWithDashes . '" style="pointer-events:none;">'; 
				echo '<label>'.$sppArr['sciname'].' '.$sppArr["author"].'</label></a>&nbsp;';
				?>
				<input type="text" name="<?php echo $tid; ?>" id="i-<?php echo $taxonWithDashes; ?>" style="background-color:#E3E7EB">
				<span class="view-specimen-span printoff">
					<a href="#" target="_blank" style="text-decoration: none;">
						<?php echo (isset($LANG['LOOKUPEXT'])?$LANG['LOOKUPEXT']:'Lookup external vouchers'); ?>
					</a>
				</span>
				<?php
				echo "</div>\n";
				$scinameasid = str_replace(" ", "-", $sppArr['sciname']);
				if($arrforexternalserviceapi == '') {
					$arrforexternalserviceapi .= "'" . $scinameasid . "'";
				} else {
					$arrforexternalserviceapi .= ",'" . $scinameasid . "'";
				}
			}
			echo '<button type="submit">'.$LANG['SAVEEXTVOUCH'].'</button>';
			echo '</div>';
			//if(isset($dynamPropsArr) && $dynamPropsArr['externalservice'] == 'inaturalist') {
				echo '<script src="../js/symb/checklists.externalserviceapi.js"></script>';
				?>
				<script>
					<?php 
					echo 'const checklisttaxa = [' . $arrforexternalserviceapi . '];';
					//echo 'const externalProjID = "' . ($dynamPropsArr['externalserviceid']?$dynamPropsArr['externalserviceid']:'') . '";';
					echo 'const externalProjID = "biodiversity-of-puerto-rico";'; //DEBUG ONLY!!!!!!!!!!!!!
					echo 'const iconictaxon = "' . ($dynamPropsArr['externalserviceiconictaxon']?$dynamPropsArr['externalserviceiconictaxon']:'') . '";';
					?>

					// iNaturalist Integration
					// Note: the two part request (...Page1 vs ...AdditionalPages) is performed
					// to allow for a variable number of total results. There will always be a 
					// first page, but there may be 0 or more additional pages. The answer is
					// extracted from the response to the first ("Page1") fetch request.
					fetchiNatPage1(externalProjID, iconictaxon)
						.then(pageone => {
							const totalresults = pageone.total_results;
							const perpage = pageone.per_page;
							const loopnum = Math.ceil(totalresults / perpage);
							const taxalist1 = extractiNatTaxaIdAndName(pageone.results);
							fetchiNatAdditionalPages(loopnum, externalProjID, iconictaxon)
							.then(pagestwoplus => {
								const taxalist2 = pagestwoplus.map(page => extractiNatTaxaIdAndName(page.results))
								taxalist = taxalist1.concat(taxalist2.flat());
								checklisttaxa.forEach( taxon => { 
									let anchortag = document.getElementById('a-'+taxon);
									let imgtag = document.getElementById('i-'+taxon);
									let taxonwithspaces = taxon.replaceAll('-', ' ');
									const idx = taxalist.findIndex( elem => elem.name === taxonwithspaces);
									if(idx >= 0) {
										console.log('yep');
										anchortag.setAttribute("style", "pointer-events:auto;");
										imgtag.setAttribute("style", "background-color: #FFFFFF;");
										anchortag.setAttribute("href", `https://www.inaturalist.org/observations?project_id=${externalProjID}&taxon_id=${taxalist[idx].id}`);
									}
								})
							})
							.catch(error => {
								error.message;
							})
						})
				</script>
			<?php //} ?>
		</div>
    </div>
	</div>
    </div>
	<?php
}
?>