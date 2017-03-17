<?php

$properties = [
    'place of birth' => 'P19',
    'place of death' => 'P20',
    'residence' => 'P551',
    'work location' => 'P937'
];
$data = getData();
foreach ($properties as $prop) {
    $data = array_merge($data, getData($prop));
}
makeCsv($data);

function makeCsv($data)
{
    ob_start();
    $df = fopen("php://output", 'w');
    fputcsv($df, array_keys(reset($data)));
    foreach ($data as $row) {
        fputcsv($df, $row);
    }
    fclose($df);
    echo ob_get_clean();
}

function getData($property = false)
{
    $itemWithCoord = "?item";
    $propStmt = "";
    if ($property) {
        $itemWithCoord = "?itemWithCoord";
        $propStmt = "?item wdt:$property $itemWithCoord .";
    }
    $query = "
	select ?title ?item ?sitelink ?latitude ?longitude where {
		$propStmt
	{ $itemWithCoord wdt:P131 wd:Q606212 } UNION { $itemWithCoord wdt:P131 wd:Q604376 } .
	?sitelink schema:about ?item .
	FILTER EXISTS {
	?sitelink schema:inLanguage 'en' .
	?sitelink schema:isPartOf <https://en.wikipedia.org/>
	} .
	$itemWithCoord p:P625 ?coordinate .
	?coordinate psv:P625 ?coordinate_node .
	?coordinate_node wikibase:geoLatitude ?latitude .
	?coordinate_node wikibase:geoLongitude ?longitude .
	?item rdfs:label ?title .
	FILTER (LANG(?title) = 'en') .  
	}
	";
    $data = [];
    $xml = getXml($query);
    foreach ($xml->results->result as $res) {
        $data[] = getBindings($res);
    }
    return $data;
}

function getXml($query)
{
    $url = "https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=" . urlencode($query);
    try {
        $result = file_get_contents($url);
    } catch (\Exception $e) {
        throw new \Exception("Unable to run query: <pre>" . htmlspecialchars($query) . "</pre>", 500);
    }
    if (empty($result)) {
        header('Content-type:text/plain');
        echo $query;
        exit(1);
    }
    $xml = new \SimpleXmlElement($result);
    return $xml;
}

function getBindings($xml)
{
    $out = [];
    foreach ($xml->binding as $binding) {
        if (isset($binding->literal)) {
            $out[(string)$binding['name']] = (string)$binding->literal;
        }
        if (isset($binding->uri)) {
            $out[(string)$binding['name']] = (string)$binding->uri;
        }
    }
    return $out;
}
