<?php

return [
    // Staging i lokalnie: false (noindex + robots Disallow: /). Produkcja: true, chyba że APP_INDEXABLE ją nadpisze.
    'indexable' => (bool) env('APP_INDEXABLE', env('APP_ENV') === 'production'),

    // Domyślny obraz Open Graph, gdy strona nie ma własnego zdjęcia (zdjęcie z hero).
    'default_image' => 'images/aikwarium-akwarium-1600.jpg',

    // Stare adresy WordPressa (bez końcowego ukośnika) -> nowe trasy. Końcowy ukośnik usuwa middleware.
    // /oferty-w-twojej-okolicy (rid, aqs_db) obsługuje middleware: produkt, a gdy się nie da, /doradca.
    'redirects' => [
        '/wp-login.php' => '/logowanie',
        '/dashboard' => '/moje-akwarium',
        '/redfield' => '/moje-akwarium/redfield',
        '/regulamin-platformy-aikwarium' => '/regulamin',
        '/polityka-prywatnosci' => '/prywatnosc',
        '/polityka-plikow-cookies-eu' => '/prywatnosc',
    ],
];
