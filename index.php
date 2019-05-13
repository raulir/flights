<?php

if (file_exists('settings.json')){
	$settings = json_decode(file_get_contents('settings.json'), true);
} else {
	include('settings.php');
	die();
}

// curl -v  -X GET "https://api.flightstats.com/flex/flightstatus/rest/v2/json/airport/status/TLL/arr/2019/05/08/06?
// appId=8a4a24d5&appKey=e171fd118ae8226c71786f6f24746fd5&utc=false&numHours=6&maxFlights=5"

date_default_timezone_set('Europe/Tallinn');

function get_day_arrivals(){
	
	$time = floor(time() / 21600) * 21600;
	
	$results = [];
	
	list($day, $month, $year, $hour) = explode(' ', date('d m Y H', $time - 21600));
	$results = array_merge($results, get_arrivals($year, $month, $day, $hour));
	
	list($day, $month, $year, $hour) = explode(' ', date('d m Y H', $time));
	$results = array_merge($results, get_arrivals($year, $month, $day, $hour));
	
	list($day, $month, $year, $hour) = explode(' ', date('d m Y H', $time + 21600));
	$results = array_merge($results, get_arrivals($year, $month, $day, $hour));
	
	list($day, $month, $year, $hour) = explode(' ', date('d m Y H', $time + 43200));
	$results = array_merge($results, get_arrivals($year, $month, $day, $hour));
	
	return $results;
	
}

function get_arrivals($year, $month, $day, $hour){
	
	$url = 'https://api.flightstats.com/flex/flightstatus/rest/v2/json/airport/status/TLL/arr/'.$year.'/'.$month.'/'.$day.'/'.$hour.'?'.
			'appId='.$GLOBALS['settings']['appid'].'&appKey='.$GLOBALS['settings']['key'].'&numHours=6';
	
	$results = json_decode(file_get_contents($url), true);
	
	$return = $results['flightStatuses'];
	
	return $return;
	
}

function normalise_results($results){
	
	$return = [];
	
	foreach($results as $flight){
		
		$codeshares = [];
		if (!empty($flight['codeshares'])){
			foreach($flight['codeshares'] as $codeshare){
				$codeshares[] = $codeshare['fsCode'] . ' ' . $codeshare['flightNumber'];
			}
		}
	
		$return[$flight['flightId']] = [
				'status' => $flight['status'],
				'time' => $flight['operationalTimes']['scheduledGateArrival']['dateLocal'],
				'delay' => !empty($flight['operationalTimes']['estimatedGateArrival']['dateLocal']) ?
						round((strtotime($flight['operationalTimes']['estimatedGateArrival']['dateLocal']) -
								strtotime($flight['operationalTimes']['scheduledGateArrival']['dateLocal']))/60)
						: 0,
				'from' => $flight['departureAirportFsCode'],
				'flight_codes' => $codeshares,
		];
		
		if ($return[$flight['flightId']]['delay'] < 0){
			$return[$flight['flightId']]['delay'] = 0;
		}
			
	}
	
	return $return;
	
}

$arrivals = get_day_arrivals();

$arrivals = normalise_results($arrivals);

?>
flightstats

<pre>

<?php print_r($arrivals); ?>

</pre>

