<?php
declare(strict_types=1);
/** Public localization API for PHP templates. Never translate SKU identifiers or order snapshots. */
function store_lang(): string {return \App\Storefront\StoreLocale::current();}
function store_lang_dir(): string {return \App\Storefront\StoreLocale::direction();}
function store_t(string $english): string {return \App\Storefront\StoreLocale::tr($english);}
function store_public_message_t(string $message): string {return \App\Storefront\StoreLocale::publicMessage($message);}
function store_order_status_t(string $status): string {return \App\Storefront\StoreLocale::statusLabel($status);}
function store_language_url(string $locale): string {return \App\Storefront\StoreLocale::switchUrl($locale);}
function store_available_languages(): array {return \App\Storefront\StoreLocale::config(store_runtime_settings())['enabled'];}
function store_localize_entity(array $row,string $type): array {return \App\Storefront\StoreLocale::rows([$row],$type)[0];}
function store_localize_rows(array $rows,string $type): array {return \App\Storefront\StoreLocale::rows($rows,$type);}
