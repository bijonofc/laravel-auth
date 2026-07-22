<?php

it('boots the package service provider', function () {
    expect($this->app->getProviders(\Appsbd\Auth\Providers\AuthServiceProvider::class))->not->toBeEmpty();
});
