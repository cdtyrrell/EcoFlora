/*--------------------------------------------------------------------
|  This Symbiota enhancement was made possible with support from
|   the United States Institute of Museum and Library Services grant
|   MG-70-19-0057-19, to the New York Botanical Garden (NYBG).
|  Programming performed by Christopher D. Tyrrell, all errors and
|   omissions are his.
*-------------------------------------------------------------------*/
//
/*---------------------------------------------------------------------
|
|  Purpose:  Retreive the name and iNaturalist taxon ID for
|	   all taxa associated with an iNaturalist project.
|
|  Pre-condition: 
|
|  Post-condition: 
|
|  Parameters:
|	 param1 -- def'n.
|	 param2 -- def'n.
|
|  Returns:  
*-------------------------------------------------------------------*/

const projID = '10230';
const iconictaxon = 'Plantae';
const qualitygrade = 'research';
const apiurl = 'https://api.inaturalist.org/v2/observations/species_counts?project_id=' + projID + '&quality_grade=' + qualitygrade + '&iconic_taxa=' + iconictaxon + '&per_page=500&fields=(taxon:(name:!t))';

let taxalist = '';
let totalresults = 0;
let perpage = 0;

function extractiNatTaxaIdAndName(resultsjson) {
    let outputArr = Array();
    resultsjson.forEach(element => {
        outputArr.push({id: element.taxon.id, name: element.taxon.name});
    });
    return outputArr;
}

fetch(apiurl)
  .then(response => {
    if(response.ok){
	  return response.json();  
    }
	throw new Error('Request failed!');
  }, 
    networkError => {
    console.log(networkError.message);
  })
  .then(jsonResponse => {
    taxalist = extractiNatTaxaIdAndName(jsonResponse.results);
    totalresults = jsonResponse.total_results;
    perpage = jsonResponse.per_page;

    let loopnum = Math.ceil(totalresults / perpage);

    for (let i = 2; i <= loopnum; i++) {
        fetch(apiurl + '&page=' + i)
          .then(response => {
            if(response.ok){
              return response.json();  
            }
            throw new Error('Request failed!');
          }, 
            networkError => {
            console.log(networkError.message);
          })
          .then(jsonResponse2 => {
            taxalist = taxalist.concat(extractiNatTaxaIdAndName(jsonResponse2.results));
          })
      }

      chacklisttaxa.forEach( taxon => {taxalist.indexof(taxon)} );
      // add id to url for hidden icon with document.getElementById(id).innerHTML = new HTML; icon display not hidden css
      // var link = document.getElementById("abc");
      //  link.setAttribute("href", "xyz.php");

  })