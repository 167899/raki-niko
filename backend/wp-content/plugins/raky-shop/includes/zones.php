<?php
defined('ABSPATH') || exit;

/** Ray casting: чи лежить точка всередині багатокутника [[lat, lng], ...]. */
function raky_point_in_polygon(float $lat, float $lng, array $polygon): bool {
	$inside = false;
	$count  = count($polygon);
	for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
		[$lat_i, $lng_i] = $polygon[$i];
		[$lat_j, $lng_j] = $polygon[$j];
		$crosses = ($lng_i > $lng) !== ($lng_j > $lng);
		if ($crosses && $lat < ($lat_j - $lat_i) * ($lng - $lng_i) / ($lng_j - $lng_i) + $lat_i) {
			$inside = ! $inside;
		}
	}
	return $inside;
}

/** Перша зона за порядком у налаштуваннях, куди потрапляє точка. */
function raky_find_zone(float $lat, float $lng): ?array {
	foreach (raky_get_settings()['zones'] as $zone) {
		if (raky_point_in_polygon($lat, $lng, $zone['polygon'] ?? [])) {
			return $zone;
		}
	}
	return null;
}

function raky_zone_payload(?array $zone): array {
	if (! $zone) {
		return ['in_zone' => false];
	}
	return [
		'in_zone'   => true,
		'zone_id'   => $zone['id'],
		'zone_name' => $zone['name'],
		'outlet_id' => $zone['outlet_id'],
		'fee'       => (float) $zone['fee'],
		'min_order' => (float) $zone['min_order'],
	];
}

function raky_rest_delivery_check(WP_REST_Request $request) {
	$lat = $request->get_param('lat');
	$lng = $request->get_param('lng');
	if (! is_numeric($lat) || ! is_numeric($lng)) {
		return new WP_Error('raky_bad_coords', 'Вкажіть координати.', ['status' => 400]);
	}
	return raky_zone_payload(raky_find_zone((float) $lat, (float) $lng));
}
