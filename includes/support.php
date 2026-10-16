<?php
declare(strict_types=1);
require_once __DIR__.'/private_storage.php';

function support_storage_dir(): string {
    return private_storage_primary_dir('support');
}


function support_attachment_path(string $storedName): ?string {
    return private_storage_find('support',$storedName);
}
