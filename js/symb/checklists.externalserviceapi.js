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

// TO DO:
// extract these vars from fmchecklist table
//const projID = 'jamaican-plants'; //'10230';
//const iconictaxon = 'Plantae'; 
let taxalist = '';

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
                    imgtag.setAttribute("style", "width:12px;display:inline;");
                    anchortag.setAttribute("href", `https://www.inaturalist.org/observations?project_id=${externalProjID}&taxon_id=${taxalist[idx].id}`);
                }
            })
        })
        .catch(error => {
            error.message;
        })
    })