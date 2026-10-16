<?php
declare(strict_types=1);
require_once __DIR__.'/store_layout.php';

/**
 * Public informational/auth-adjacent pages use the same storefront chrome as the shop.
 * Keeping one header/footer implementation prevents the public shell drifting away from
 * Store Design settings and removes a second hardcoded navigation/footer system.
 */
function public_layout_start(array $options=[]): void
{
    $settings=store_runtime_settings();
    store_page_start(
        (string)($options['title']??store_runtime_name()),
        (string)($options['description']??store_runtime_tagline()),
        [
            'settings'=>$settings,
            'store_name'=>store_runtime_name(),
            'active'=>(string)($options['active']??''),
            'canonical'=>(string)($options['canonical']??''),
            'robots'=>(string)($options['robots']??'index,follow'),
            'og_image'=>(string)($options['og_image']??''),
            'json_ld'=>$options['json_ld']??null,
        ]
    );
}

function public_layout_end(array $options=[]): void
{
    store_page_end();
}
