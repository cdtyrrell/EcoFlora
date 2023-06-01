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
            // iNaturalist API requests throttling to < 100 requests per minute
            await new Promise(governer => setTimeout(governer, 600));
            return resp;
        }));
        const resppromises = resps.map(result => result.json());
        const additionalPages = await Promise.all(resppromises);
        return additionalPages;
    } catch(err) {
        console.error(err);
    }
}

// check for an iNaturalist project id
			// Optimize request based on a zoom level that will return 4 tiles within project viewbox
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