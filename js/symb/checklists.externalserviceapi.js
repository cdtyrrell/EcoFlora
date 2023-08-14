/*--------------------------------------------------------------------
|  This Symbiota enhancement was made possible with support from
|   the United States Institute of Museum and Library Services grant
|   MG-70-19-0057-19, to the New York Botanical Garden (NYBG).
|  Programming performed by Christopher D. Tyrrell, all errors and
|   omissions are his.
*-------------------------------------------------------------------*/




function extractiNatTaxaIdAndName(resultsjson) {
    let outputArr = Array();
    resultsjson.forEach(element => {
        outputArr.push({id: element.taxon.id, name: element.taxon.name});
    });
    return outputArr;
}

// Note: as of the time of coding, iNaturalist API v2 is in beta and this section may require some adjusting post-release.
//       API v1 does not allow custom fields (i.e., taxa names) to be returned.
async function fetchiNatPage1(projID, iconictaxon = '', qualitygrade = 'research') {
    let apiurl = '';
    if(iconictaxon == '') {
        apiurl = `https://api.inaturalist.org/v2/observations/species_counts?project_id=${projID}&quality_grade=${qualitygrade}&per_page=500&fields=(taxon:(name:!t))`;
    } else {
        apiurl = `https://api.inaturalist.org/v2/observations/species_counts?project_id=${projID}&quality_grade=${qualitygrade}&iconic_taxa=${iconictaxon}&per_page=500&fields=(taxon:(name:!t))`;
    }
    const resp = await fetch(apiurl);
    try {
        if(resp.ok) {
            const page1 = await resp.json();
            return page1;
        }
    } catch(err) {
        console.error(err);
    }
}

async function fetchiNatAdditionalPages(loopnum, projID, iconictaxon = '', qualitygrade = 'research') {
    try {
        let apiurl = '';
        if(iconictaxon == '') {
            apiurl = `https://api.inaturalist.org/v2/observations/species_counts?project_id=${projID}&quality_grade=${qualitygrade}&per_page=500&fields=(taxon:(name:!t))`;
        } else {
            apiurl = `https://api.inaturalist.org/v2/observations/species_counts?project_id=${projID}&quality_grade=${qualitygrade}&iconic_taxa=${iconictaxon}&per_page=500&fields=(taxon:(name:!t))`;
        }
        let allapiurls = [];
        if(loopnum > 1) {
            for(let i = 2; i <= loopnum; i++) {
                allapiurls.push(apiurl + `&page=${i}`);
            }
        }
        const resps = await Promise.all(allapiurls.map(async (url) => {
            const resp = await fetch(url);
            // Throttle to < 100 requests per minute as per iNaturalist API guidelines 
            await new Promise(governor => setTimeout(governor, 600));
            return resp;
        }));
        const resppromises = resps.map(result => result.json());
        const additionalPages = await Promise.all(resppromises);
        return additionalPages;
    } catch(err) {
        console.error(err);
    }
}

async function iNatPlotPoints(llbounds, projID, iconictaxon = '', qualitygrade = 'research', rank = 'species') {
    let apiurl = '';
    if(iconictaxon == '') {
        // add something here to switch to API v2 if v1 fails?
        apiurl = `https://api.inaturalist.org/v1/points/${zoom}/${xtile}/${ytile}.grid.json?mappable=true&project_id=${projID}&rank=${rank}&quality_grade=${qualitygrade}&order=asc&order_by=updated_at`;
    } else {
        apiurl = `https://api.inaturalist.org/v1/points/${zoom}/${xtile}/${ytile}.grid.json?mappable=true&project_id=${projID}&rank=${rank}&iconic_taxa=${iconictaxon}&quality_grade=${qualitygrade}&order=asc&order_by=updated_at`;
    }
    const resp = await fetch(apiurl);
    try {
        if(resp.ok) {
            const page1 = await resp.json();
            return page1;
        }
    } catch(err) {
        console.error(err);
    }
}


async function iNatGetVoucher(obsID) {
    let apiurl = `https://api.inaturalist.org/v1/observation`; //ADD NECESSARY CODE TO RETURN species, user, number, date
    const resp = await fetch(apiurl);
    try {
        if(resp.ok) {
            const obsjson = await resp.json();
            return obsjson;
        }
    } catch(err) {
        console.error(err);
    }
}

// LINK VOUCHER
// split on comma


// check for an iNaturalist project id

// x1. on create or update: Pull place_id from project json, then pull lat/long from place json (two calls).
// "bounding_box_geojson": {"coordinates":...}
// x2a. Calculate tiles for extent. Use some formula to dynamically gauge zoom level... i.e., limit the number of api calls to X.
// zoom = round(log_2( 180/maxbboxdiff ))
// 2b. Dynamically ping api for tile points based geo info; Can only call one tile per second.
// "id","latitude","longitude"

/*
array[0]['ll']=>$r1->decimallatitude.','.$r1->decimallongitude
						var pt = new google.maps.LatLng(<?php echo $pArr['ll']; ?>);
						llBounds.extend(pt);

                        							var m<?php echo $mCnt; ?> = new google.maps.Marker({position: pt, map:map, title:"<?php echo $pArr['sciname']; ?>", icon:pIcon});

*/

// 20221125: new plan, "dynamic" plotting: get current extent, then ping appropriate zoom level (one tile? four tiles?) for inat.
// google.maps.getNorthEast()
// google.maps.getSouthWest()



			// Calculate tiles by taking the 25% and 75% positions in x and y to get the centers of nw, ne, sw, se tiles
			// Start a promise 
			//   ping the api for nw tile.
			//   Must wait at least 0.7 seconds as per iNaturalist documentation
			//   ping the api for se tile.
			//   if ne tile != nw && ne != se,
			// 	Must wait at least 0.7 seconds as per iNaturalist documentation
			// 	ping the api for ne tile
			//   if sw tile != se && sw != nw
			// 	Must wait at least 0.7 seconds as per iNaturalist documentation
			// 	ping the api for sw tile
			// fulfil promise by sending points from all tiles