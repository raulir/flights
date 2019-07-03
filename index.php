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

$airlines = [];
$airports = [];

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
//	print($url);
	$results = json_decode(file_get_contents($url), true);

	foreach($results['appendix']['airlines'] as $airline){
		$GLOBALS['airlines'][$airline['fs']] = $airline['name'];
	}
	
	foreach($results['appendix']['airports'] as $airport){
		$GLOBALS['airports'][$airport['fs']] = $airport['city'];
	}
	
	$return = $results['flightStatuses'];
	
	return $return;
	
}

function normalise_results($results){
// print_r($results);	
	$return = [];
	
	foreach($results as $flight){
		
		$codeshares = [];
		if (!empty($flight['codeshares'])){
			foreach($flight['codeshares'] as $codeshare){
				$codeshares[] = ['code' => $codeshare['fsCode'] . ' ' . $codeshare['flightNumber'], 'airline' => $GLOBALS['airlines'][$codeshare['fsCode']]];
			}
		}
		
		if (empty($codeshares)){
			$codeshares[] = ['code' => $flight['carrierFsCode'] . ' ' . $flight['flightNumber'], 'airline' => $GLOBALS['airlines'][$flight['carrierFsCode']]];
		}
	
		$return[$flight['flightId']] = [
				'status' => $flight['status'],
				'time' => $flight['operationalTimes']['scheduledGateArrival']['dateLocal'],
				'delay' => !empty($flight['operationalTimes']['estimatedGateArrival']['dateLocal']) ?
						round((strtotime($flight['operationalTimes']['estimatedGateArrival']['dateLocal']) -
								strtotime($flight['operationalTimes']['scheduledGateArrival']['dateLocal']))/60)
						: 0,
				'from' => $GLOBALS['airports'][$flight['departureAirportFsCode']],
				'flight_codes' => $codeshares,
		];
		
		if ($return[$flight['flightId']]['delay'] < 0){
			$return[$flight['flightId']]['delay'] = 0;
		}
		
		if ($flight['status'] == 'L'){
			if (!empty($flight['operationalTimes']['estimatedGateArrival']['dateLocal']) /* && 
					($flight['operationalTimes']['scheduledGateArrival']['dateLocal'] != $flight['operationalTimes']['estimatedGateArrival']['dateLocal']) */){
				$return[$flight['flightId']]['information'] = 'Arrived '. date('H:i', strtotime($flight['operationalTimes']['estimatedGateArrival']['dateLocal']));
			} else {
				$return[$flight['flightId']]['information'] = 'Arrived';
			}
		} else if (!empty($flight['operationalTimes']['estimatedGateArrival']['dateLocal'])){
			$return[$flight['flightId']]['information'] = 'Estimated '.date('H:i', strtotime($flight['operationalTimes']['estimatedGateArrival']['dateLocal']));
		} else {
			$return[$flight['flightId']]['information'] = '';
		}
			
	}
	
	// order by arrival time
	usort($return, function ($a, $b) { 
		return ($a['time'] <=> $b['time']); 
	});
	
	return $return;
	
}

$arrivals = get_day_arrivals();

$arrivals = normalise_results($arrivals);

?>

<style>
	
	.arrivals_table {
		display: table;
	}
	
	.arrivals_row {
		display: table-row;
	}
	
	.arrivals_cell {
		display: table-cell;
		padding: 4px 6px;
		border-top: 1px solid blue;
	}
	
</style>


flightstats

<div class="arrivals_table">

<div class="arrivals_row">
	<div class="arrivals_cell">Time</div>
	<div class="arrivals_cell">From</div>
	<div class="arrivals_cell">Flight no</div>
	<div class="arrivals_cell">Airline</div>
	<div class="arrivals_cell">Information</div>
</div>

<?php foreach($arrivals as $arrival): ?>

	<div class="arrivals_row">
		<div class="arrivals_cell"><?= date('H:i', strtotime($arrival['time'])) ?></div>
		<div class="arrivals_cell"><?= $arrival['from'] ?></div>
		<div class="arrivals_cell">
			<?php foreach($arrival['flight_codes'] as $key => $flight_code): ?>
				<div class="fc fc_<?= $key ?>"><?= $flight_code['code'] ?></div>
			<?php endforeach ?>
		</div>
		<div class="arrivals_cell">
			<?php foreach($arrival['flight_codes'] as $key => $flight_code): ?>
				<div class="fc fc_<?= $key ?>"><?= $flight_code['airline'] ?></div>
			<?php endforeach ?>
		</div>
		<div class="arrivals_cell"><?= $arrival['information'] ?></div>
	</div>

<?php endforeach ?>

</div>

<pre>

<?php // print_r($arrivals); ?>

</pre>

