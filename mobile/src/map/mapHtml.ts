import { Config } from '../api';

type Point = { lat: number; lng: number };

/**
 * Сторінка карти для WebView (телефон) та iframe (веб). Працює без ключів:
 * Leaflet + тайли OpenStreetMap. Для публікації тайли треба перевести на
 * комерційного постачальника, бо публічний сервер OSM не для продакшну.
 */
export function buildMapHtml(config: Config, picker: boolean, initial?: Point | null): string {
  const data = JSON.stringify({ zones: config.zones, outlets: config.outlets, picker, initial: initial ?? null }).replace(
    /</g,
    '\\u003c',
  );
  return `<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>html,body,#map{height:100%;margin:0}.leaflet-container{font:13px system-ui,sans-serif}
.pin{background:#D92B20;color:#fff;border:2px solid #fff;border-radius:50%;width:26px!important;height:26px!important;line-height:22px;text-align:center;font-weight:700;box-shadow:0 1px 4px #0006}</style>
</head><body><div id="map"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
var d=${data};
var map=L.map('map',{attributionControl:true}).setView([46.985,32.0],11);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
var bounds=[];
d.zones.forEach(function(z){
  var p=L.polygon(z.polygon,{color:z.color,weight:2,fillOpacity:.15,interactive:!d.picker}).addTo(map);
  if(!d.picker)p.bindPopup(z.name+': доставка '+z.fee+' ₴, від '+z.min_order+' ₴');
  bounds=bounds.concat(z.polygon);
});
d.outlets.forEach(function(o){
  L.marker([o.lat,o.lng],{interactive:!d.picker,icon:L.divIcon({className:'pin',html:'Р',iconSize:[26,26]})}).addTo(map).bindPopup(o.name+'<br>'+o.hours);
});
if(bounds.length)map.fitBounds(bounds,{padding:[12,12]});
var marker=null;
function send(m){var s=JSON.stringify(m);if(window.ReactNativeWebView)window.ReactNativeWebView.postMessage(s);else parent.postMessage(s,'*');}
function place(ll){if(marker)marker.setLatLng(ll);else marker=L.marker(ll).addTo(map);}
if(d.initial)place([d.initial.lat,d.initial.lng]);
if(d.picker)map.on('click',function(e){place(e.latlng);send({type:'raky-pick',lat:e.latlng.lat,lng:e.latlng.lng});});
</script></body></html>`;
}
