<?php

core()->events()->on('section.created', static function (array $payload): void {
});

core()->events()->on('section.updated', static function (array $payload): void {
});

core()->events()->on('section.deleted', static function (array $payload): void {
});

core()->events()->on('infoblock.created', static function (array $payload): void {
});

core()->events()->on('infoblock.updated', static function (array $payload): void {
});

core()->events()->on('infoblock.deleted', static function (array $payload): void {
});

core()->events()->on('object.created', static function (array $payload): void {
});

core()->events()->on('object.updated', static function (array $payload): void {
});

core()->events()->on('object.published', static function (array $payload): void {
});

core()->events()->on('object.unpublished', static function (array $payload): void {
});

core()->events()->on('object.deleted', static function (array $payload): void {
});

core()->events()->on('component.created', static function (array $payload): void {
});

core()->events()->on('component.updated', static function (array $payload): void {
});
