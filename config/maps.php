<?php
declare(strict_types=1);
return [
    'tile_url' => getenv('MAP_TILE_URL') ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    'geocoder_url' => getenv('MAP_GEOCODER_URL') ?: 'https://photon.komoot.io/api/',
];
