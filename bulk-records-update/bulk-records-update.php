<?php

// Auth ID and Password
define("AUTH_ID", 0);
define("AUTH_PASS", "xxx");

// search criteria
$search = array(
	'type' => 'A',		// type of the records you want to update, mandatory field
	'record' => '1.2.3.4',	// current value of the records, mandatory field
);

// what will be updated, remove the record parameter and leave the $update array empty only to search for records without update
$update = array(
	'record' => '4.3.2.1',	// what record you want to be set on the found DNS records
);

// function to connect to the API
function apiCall ($url, $data) {
	$url = "https://api.cloudns.net/{$url}";
	$data = "auth-id=".AUTH_ID."&auth-password=".AUTH_PASS."&{$data}";
	
	$init = curl_init();
	curl_setopt($init, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($init, CURLOPT_URL, $url);
	curl_setopt($init, CURLOPT_POST, true);
	curl_setopt($init, CURLOPT_POSTFIELDS, $data);
	curl_setopt($init, CURLOPT_USERAGENT, 'cloudns_api_script/0.1 (+https://github.com/ClouDNS/cloudns-api-bulk-updates/tree/master/bulk-records-update)');
	
	$content = curl_exec($init);
	
	curl_close($init);
	
	return json_decode($content, true);
}

// checking if we can log in successfully
$login = apiCall('dns/login.json', "");
if (isset($login['status']) && $login['status'] == 'Failed') {
	die($login['statusDescription']);
}

// getting all the domains
$rows_per_page = 100; // 100 is the maximum
$pages = apiCall('dns/get-pages-count.json', "rows-per-page={$rows_per_page}");

for ($i=1; $i<=$pages; $i++) {
	foreach (apiCall('dns/list-zones.json', "page={$i}&rows-per-page={$rows_per_page}") as $page => $zone) {
		if ($zone['type'] != 'master') {
			continue;
		}
		$records = apiCall('dns/records.json', "domain-name={$zone['name']}");
		if (empty($records)) {
			echo "{$zone['name']} has no DNS records.\n";
		}
		if (isset($records['status']) && $records['status'] == 'Failed') {
			echo $records['statusDescription'];
		}
		foreach ($records as $record) {
			if ($record['type'] == $search['type'] && $record['record'] == $search['record']) {
				echo "Record found in zone {$zone['name']}\n";
				
				if (!empty($update)) {
					$response = apiCall('dns/mod-record.json', "domain-name={$zone['name']}&record-id={$record['id']}&host={$record['host']}&record={$update['record']}&ttl={$record['ttl']}");
					if (isset($response['status'])) {
						echo (!empty($record['host']) ? $record['host'].'.' : '').$zone['name'].': '.$response['statusDescription']."\n";
					} else {
						echo "The record for {$record['host']}.{$zone['name']} was not edited for unknown reason.\n";
					}
				}
			}
		}
	}
}