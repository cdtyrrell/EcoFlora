<?php
include_once('../config/symbini.php');
include_once($SERVER_ROOT.'/classes/ChecklistManager.php');
@include_once($SERVER_ROOT.'/content/lang/checklists/checklistmap.'.$LANG_TAG.'.php');
header('Content-Type: text/html; charset='.$CHARSET);

$clid = filter_var($_REQUEST['clid'], FILTER_SANITIZE_NUMBER_INT);
$thesFilter = array_key_exists('thesfilter',$_REQUEST)?filter_var($_REQUEST['thesfilter'], FILTER_SANITIZE_NUMBER_INT):1;
$taxonFilter = array_key_exists('taxonfilter',$_REQUEST)?filter_var($_REQUEST['taxonfilter'], FILTER_SANITIZE_STRING):'';

if(!$thesFilter) $thesFilter = 1;

$clManager = new ChecklistManager();
$clManager->setClid($clid);
if($thesFilter) $clManager->setThesFilter($thesFilter);
if($taxonFilter) $clManager->setTaxonFilter($taxonFilter);

$coordArr = $clManager->getVoucherCoordinates();
$clMeta = $clManager->getClMetaData();
?>
<html>
<head>
	<title><?php echo $DEFAULT_TITLE.' - '.(isset($LANG['COORD_MAP'])?$LANG['COORD_MAP']:'Checklist Coordinate Map'); ?></title>
	<?php
	//include_once($SERVER_ROOT.'/includes/head.php');
	include_once($SERVER_ROOT.'/includes/googleanalytics.php');

	// If checklist is associated with an external service (i.e., iNaturalist), deploy client-side javascript
	if($clMeta['dynamicProperties']){
		$dynamPropsArr = json_decode($clMeta['dynamicProperties'], true);
		if(isset($dynamPropsArr['externalservice']) && $dynamPropsArr['externalservice'] == 'inaturalist') {
			echo '<script src="../js/symb/checklists.externalserviceapi.js"></script>';
			echo '<script>';
			echo 'const urltail = ".grid.json?mappable=true&project_id='. ($dynamPropsArr['externalserviceid']?$dynamPropsArr['externalserviceid']:'').'&rank='. ($dynamPropsArr['externalservicerank']?$dynamPropsArr['externalservicerank']:'species').'&iconic_taxa='. ($dynamPropsArr['externalserviceiconictaxon']?$dynamPropsArr['externalserviceiconictaxon']:'').'&quality_grade='. ($dynamPropsArr['externalservicegrade']?$dynamPropsArr['externalservicegrade']:'research').'&order=asc&order_by=updated_at";';
			echo 'const inatprojid = "'. ($dynamPropsArr['externalserviceid']?$dynamPropsArr['externalserviceid']:'') .'";';
			echo 'const inaticonic = "'. ($dynamPropsArr['externalserviceiconictaxon']?$dynamPropsArr['externalserviceiconictaxon']:'') .'";';
			echo '</script>';
		}

	} 
	?>
	<script src="//maps.googleapis.com/maps/api/js?v=3.exp&libraries=drawing<?php echo (isset($GOOGLE_MAP_KEY) && $GOOGLE_MAP_KEY?'&key='.$GOOGLE_MAP_KEY:''); ?>&callback=Function.prototype"></script>
	<script type="text/javascript">
		var map;
		var puWin;

		function initialize(){
			var dmOptions = {
				zoom: 3,
				center: new google.maps.LatLng(41,-95),
				mapTypeId: google.maps.MapTypeId.TERRAIN,
				scaleControl: true
			};

			var llBounds = new google.maps.LatLngBounds();
			<?php
			if($coordArr){
				?>
				map = new google.maps.Map(document.getElementById("map_canvas"), dmOptions);
				var vIcon = new google.maps.MarkerImage("../images/google/smpin_red.png");
				var pIcon = new google.maps.MarkerImage("../images/google/smpin_blue.png");
				var inatIcon = new google.maps.MarkerImage("../images/google/smpin_green.png");
				<?php
				$mCnt = 0;
				foreach($coordArr as $tid => $cArr){
					foreach($cArr as $pArr){
						?>
						var pt = new google.maps.LatLng(<?php echo $pArr['ll']; ?>);
						llBounds.extend(pt);
						<?php
						if(array_key_exists('occid',$pArr)){
							?>
							var m<?php echo $mCnt; ?> = new google.maps.Marker({position: pt, map:map, title:"<?php echo $pArr['notes']; ?>", icon:vIcon});
							google.maps.event.addListener(m<?php echo $mCnt; ?>, "click", function(){ openIndPU(<?php echo $pArr['occid']; ?>); });
							<?php
						}
						else{
							?>
							var m<?php echo $mCnt; ?> = new google.maps.Marker({position: pt, map:map, title:"<?php echo $pArr['sciname']; ?>", icon:pIcon});
							<?php
						}
						$mCnt++;
					}
				}
			}

			// Optimize request based on a zoom level that will return 4 tiles within current bounds
            //     Alternative: get project bounding box by: pulling place_id from project json, then pull lat/long from place json (two iNat calls). "bounding_box_geojson": {"coordinates":...}
            // Determine x difference and y difference and get the max
			?> 

			function ll2slippytile(lon, lat, zoom) {
				// https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Lon..2Flat._to_tile_numbers_2
				let n = Math.pow(2, zoom);
				xtile = Math.floor( n * ((lon + 180) / 360) );
				ytile = Math.floor( n * (1 - (Math.log(Math.tan((lat * Math.PI/180)) + (1 / Math.cos((lat * Math.PI/180)))) / Math.PI)) / 2 );
				return {x:xtile, y:ytile};
			}

			async function requestTileCoords(tileurls) {

				const resps = await Promise.all(tileurls.map(async (url) => {
					const resp = await fetch(url);
					// Throttle to < 100 requests per minute as per iNaturalist API guidelines 
					await new Promise(governor => setTimeout(governor, 600));
					return resp;
				}));
				const resppromises = resps.map(result => result.json());
				const inatprojectpoints = await Promise.all(resppromises);
				let returnarr = []; // flatten all promise responses into single object
				for(const i in inatprojectpoints) { returnarr = Object.assign(returnarr, inatprojectpoints[i].data) }
				return returnarr;
			}

			let ne = llBounds.getNorthEast();
			let sw = llBounds.getSouthWest();
			let xdiff = Math.abs( ne.lng() - sw.lng() ); 
			let ydiff = Math.abs( ne.lat() - sw.lat() );
			let diff = Math.max(xdiff, ydiff);
            // Calculate zoom factor
            let zoom = Math.round(Math.log2( 180/diff ));
			
			// Determine approximate tile centers by taking the 25% and 75% positions in x and y
			let x25 = (0.25 * xdiff) + sw.lng();
			let x75 = (0.75 * xdiff) + ne.lng();
			let y25 = (0.25 * ydiff) + sw.lat();
			let y75 = (0.75 * ydiff) + ne.lat();
			const nwtile = ll2slippytile(x25,y25,zoom);
			const setile = ll2slippytile(x75,y75,zoom);
			const netile = ll2slippytile(x75,y25,zoom);
			const swtile = ll2slippytile(x25,y75,zoom);

			// Start with NW tile
			let alltileurls = [`https://api.inaturalist.org/v1/points/${zoom}/${nwtile['x']}/${nwtile['y']}${urltail}`];
			console.log(alltileurls);
			
			// Check rectangularity condition of llbounds converted to tiles (2x2, 2x1, 1x2, 1x1)
			if(setile != nwtile) {
				// 1x1 check
				alltileurls.push(`https://api.inaturalist.org/v1/points/${zoom}/${setile['x']}/${setile['y']}${urltail}`);
				if(netile != setile || nwtile != swtile) {
					// 1x2 check, if succeeds, then 2x2
					alltileurls.push(`https://api.inaturalist.org/v1/points/${zoom}/${netile['x']}/${netile['y']}${urltail}`);
					alltileurls.push(`https://api.inaturalist.org/v1/points/${zoom}/${swtile['x']}/${swtile['y']}${urltail}`);
				} else if(setile != swtile || netile != nwtile) {
					// 2x1 check, if succeeds, then 2x2
					alltileurls.push(`https://api.inaturalist.org/v1/points/${zoom}/${netile['x']}/${netile['y']}${urltail}`);
					alltileurls.push(`https://api.inaturalist.org/v1/points/${zoom}/${swtile['x']}/${swtile['y']}${urltail}`);
				} 
				// if either of above fail, then it's a 1x2 or 2x1. Using the nw + se tiles 
				// (requests already in place because the 1x1 check passed) will cover both cases.
			}

 			requestTileCoords(alltileurls)
				.then(result => {
					var inatmarker;
					for( const inatid in result ) {
						let lat = result[inatid].latitude;
						let lon = result[inatid].longitude;
						let pt = new google.maps.LatLng(lat,lon);
						llBounds.extend(pt);
						inatmarker = new google.maps.Marker({
        					position: pt,
         					map: map, 
							title: "iNaturalist-" + inatid, 
							icon: inatIcon
    					});
					    google.maps.event.addListener(inatmarker, 'click', (function(inatmarker, inatid) {
        					return function() {
             					window.open(`https://www.inaturalist.org/observations/${inatid}`, '_blank')
							}
    					})(inatmarker, inatid));
					}
				})
				.catch(error => {error.message;});

			<?php

			//Check for and add checklist polygon
			if($clMeta['footprintwkt']){
				?>
				var polyPointArr = [];
				<?php
				$footPrintWkt = $clMeta['footprintwkt'];
				if(substr($footPrintWkt, 0, 7) == 'POLYGON'){
					$footPrintWkt = substr($footPrintWkt, 10, -2);
					$pointArr = explode(',', $footPrintWkt);
					foreach($pointArr as $pointStr){
						$llArr = explode(' ', trim($pointStr));
						if($llArr[0] > 90 || $llArr[0] < -90) break;
						?>
						var polyPt = new google.maps.LatLng(<?php echo $llArr[0].','.$llArr[1]; ?>);
						polyPointArr.push(polyPt);
						llBounds.extend(polyPt);
						<?php
					}
					?>
					var footPoly = new google.maps.Polygon({
						paths: polyPointArr,
						strokeWeight: 2,
						fillOpacity: 0.4,
						map: map
					});
					<?php
				}
			}
			?>
			map.fitBounds(llBounds);
			map.panToBounds(llBounds);
		}

		function openIndPU(occId){
			if(puWin != null) puWin.close();
			var puWin = window.open('../collections/individual/index.php?occid='+occId,'indspec' + occId,'scrollbars=1,toolbar=0,resizable=1,width=900,height=600,left=20,top=20');
			if(puWin.opener == null) puWin.opener = self;
			setTimeout(function () { puWin.focus(); }, 0.5);
			return false;
		}

	</script>
	<style>
		html, body, #map_canvas {
			width: 100%;
			height: 100%;
			margin: 0;
			padding: 0;
		}
	</style>
</head>
<body style="background-color:#ffffff;" onload="initialize();">
<?php
	if(!$coordArr){
		?>
		<div style='font-size:120%;font-weight:bold;'>
			<?php echo (isset($LANG['NO_COORDS'])?$LANG['NO_COORDS']:'Your query apparently does not contain any records with coordinates that can be mapped'); ?>.
		</div>
		<div style="margin:15px;">
			<?php echo (isset($LANG['MAYBE_RARE'])?$LANG['MAYBE_RARE']:'It may be that the vouchers have rare/threatened status that require the locality coordinates be hidden'); ?>.
		</div>
		<?php
	}
	?>
	<div id='map_canvas'></div>
</body>
</html>