<?php

it('boots the package service provider', function () {
    expect($this->app->getProviders(\Bijon\LaravelAuth\Providers\AuthServiceProvider::class))->not->toBeEmpty();
});
