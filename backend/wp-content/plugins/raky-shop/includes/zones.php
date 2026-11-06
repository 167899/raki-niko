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

/** Перша зона за порядком у налаштуваннях, куди потрапляє точка. Зони впорядковані від дешевшої. */
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

const RAKY_GEOCODE_URL     = 'https://nominatim.openstreetmap.org/search';
const RAKY_GEOCODE_HIT_TTL = 30 * DAY_IN_SECONDS;
const RAKY_GEOCODE_MISS_TTL = 10 * MINUTE_IN_SECONDS;

/**
 * Вулиця й будинок з адреси клієнта: «вул. Соборна 5, кв. 2» → ["Соборна 5", "5"].
 *
 * @return array{0: string, 1: string}|null
 */
function raky_parse_house(string $address): ?array {
	$clean = preg_replace('/[,\s]*(кв\.?|квартира|під\'?їзд|під\.|офіс|оф\.?|поверх)\s*\S*.*$/iu', '', $address);
	$clean = trim((string) preg_replace('/^(вул\.?|вулиця|просп\.?|проспект|пров\.?|провулок)\s+/iu', '', trim((string) $clean)));
	$clean = trim($clean, " ,.");
	if (! preg_match('/[\s,]+(\d+[а-яєіїa-z]?(?:\/\d+[а-яєіїa-z]?)?)$/iu', $clean, $match)) {
		return null;
	}
	return [(string) preg_replace('/[\s,]+/u', ' ', $clean), mb_strtolower($match[1])];
}

/** Координати будинку або null. Вулиця чи район не підходять: одна вулиця проходить через кілька зон. */
function raky_geocode(string $address): ?array {
	$parsed = raky_parse_house($address);
	if (! $parsed) {
		return null;
	}
	[$query, $house] = $parsed;
	$key    = 'raky_geo_' . md5(mb_strtolower($query));
	$cached = get_transient($key);
	if (is_array($cached)) {
		return $cached['lat'] === null ? null : $cached;
	}

	$bounds = ['lat_min' => 90.0, 'lat_max' => -90.0, 'lng_min' => 180.0, 'lng_max' => -180.0];
	foreach (raky_get_settings()['zones'] as $zone) {
		foreach ($zone['polygon'] as [$lat, $lng]) {
			$bounds = [
				'lat_min' => min($bounds['lat_min'], $lat),
				'lat_max' => max($bounds['lat_max'], $lat),
				'lng_min' => min($bounds['lng_min'], $lng),
				'lng_max' => max($bounds['lng_max'], $lng),
			];
		}
	}
	$url = add_query_arg([
		'q'               => rawurlencode($query),
		'format'          => 'jsonv2',
		'addressdetails'  => 1,
		'limit'           => 5,
		'countrycodes'    => 'ua',
		'accept-language' => 'uk',
		'bounded'         => 1,
		'viewbox'         => implode(',', [$bounds['lng_min'], $bounds['lat_max'], $bounds['lng_max'], $bounds['lat_min']]),
	], RAKY_GEOCODE_URL);
	$response = wp_remote_get($url, ['timeout' => 6, 'user-agent' => 'RakiNiko/1.0']);
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
		return null; // Збій сервісу не кешуємо.
	}

	foreach ((array) json_decode(wp_remote_retrieve_body($response), true) as $place) {
		if (mb_strtolower((string) ($place['address']['house_number'] ?? '')) === $house) {
			$found = ['lat' => (float) $place['lat'], 'lng' => (float) $place['lon']];
			set_transient($key, $found, RAKY_GEOCODE_HIT_TTL);
			return $found;
		}
	}
	set_transient($key, ['lat' => null], RAKY_GEOCODE_MISS_TTL);
	return null;
}

/** Зона за текстом адреси. null: вартість доставки вписує менеджер. */
function raky_zone_for_address(string $address): ?array {
	$point = raky_geocode($address);
	return $point ? raky_find_zone($point['lat'], $point['lng']) : null;
}

function raky_rest_delivery_quote(WP_REST_Request $request) {
	$address = sanitize_text_field((string) $request->get_param('address'));
	if ($address === '' || raky_rate_limited('quote', RAKY_QUOTE_RATE_LIMIT)) {
		return ['found' => false];
	}
	$zone = raky_zone_for_address($address);
	return $zone ? ['found' => true, 'fee' => (float) $zone['fee'], 'zone_name' => $zone['name']] : ['found' => false];
}
