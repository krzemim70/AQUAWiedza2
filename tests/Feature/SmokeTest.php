<?php

it('serves public and panel pages', function (string $url) {
    $this->get($url)->assertOk();
})->with(['/', '/dobor', '/admin/login', '/panel-sklepu/login', '/logowanie']);

it('redirects guests from the aquarist area to login', function () {
    $this->get('/moje-akwarium')->assertRedirect('/logowanie');
});
