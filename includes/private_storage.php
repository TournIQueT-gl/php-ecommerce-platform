<?php
declare(strict_types=1);

/**
 * Runtime storage that may contain customer/private material.
 *
 * Preferred layout is outside the replaceable/public website tree:
 *   PERFUME_RUNTIME_DIR=/home/account/.auraa-runtime
 * or area-specific overrides:
 *   PERFUME_BACKUP_DIR, PERFUME_ORDER_FILES_DIR, PERFUME_SUPPORT_DIR
 *
 * When no writable external location is available, the legacy protected
 * storage/<area> directory remains a compatible fallback. Reads search both
 * locations so upgrading does not orphan files created by earlier application versions.
 */
function private_storage_project_root(): string {
    return dirname(__DIR__);
}

function private_storage_legacy_dir(string $area): string {
    $area=preg_replace('/[^a-z0-9_-]/i','',strtolower($area))??'';
    return private_storage_project_root().'/storage/'.$area;
}

function private_storage_normalize_absolute(string $path): string {
    $path=trim($path);
    if($path==='')return '';
    $path=str_replace('\\','/',$path);
    // Unix absolute path or Windows drive/UNC path.
    if($path[0]!=='/' && !preg_match('/^[A-Za-z]:\//',$path) && !str_starts_with($path,'//'))return '';
    return rtrim($path,'/');
}

function private_storage_path_is_inside_project(string $path): bool {
    $root=str_replace('\\','/',rtrim(private_storage_project_root(),'/\\')).'/';
    $candidate=str_replace('\\','/',rtrim($path,'/\\')).'/';
    if(DIRECTORY_SEPARATOR==='\\'){
        $root=strtolower($root);$candidate=strtolower($candidate);
    }
    return str_starts_with($candidate,$root);
}

function private_storage_external_base(): string {
    $configured=private_storage_normalize_absolute((string)(getenv('PERFUME_RUNTIME_DIR')?:''));
    if($configured!=='')return $configured;

    // Shared hosts commonly deploy under /home/account/public_html[/app].
    // Deriving from the web-root segment keeps CLI and FPM on the same path
    // even when HOME is not exported consistently between SAPIs.
    $project=str_replace('\\','/',private_storage_project_root());
    foreach(['/public_html/','/httpdocs/','/htdocs/','/www/'] as $segment){
        $pos=strpos($project,$segment);
        if($pos!==false){
            $candidate=substr($project,0,$pos).'/.auraa-runtime';
            if($candidate!==''&&!private_storage_path_is_inside_project($candidate))return $candidate;
        }
    }
    $baseName=strtolower(basename($project));
    if(in_array($baseName,['public_html','httpdocs','htdocs','www'],true)){
        $candidate=dirname($project).'/.auraa-runtime';
        if(!private_storage_path_is_inside_project($candidate))return $candidate;
    }

    $home=private_storage_normalize_absolute((string)(getenv('HOME')?:($_SERVER['HOME']??'')));
    if($home==='')return '';
    $candidate=$home.'/.auraa-runtime';
    return private_storage_path_is_inside_project($candidate)?'':$candidate;
}

function private_storage_area_override(string $area): string {
    $key=match(strtolower($area)){
        'backups'=>'PERFUME_BACKUP_DIR',
        'orders'=>'PERFUME_ORDER_FILES_DIR',
        'support'=>'PERFUME_SUPPORT_DIR',
        default=>'',
    };
    if($key==='')return '';
    return private_storage_normalize_absolute((string)(getenv($key)?:''));
}

function private_storage_ensure_dir(string $dir): bool {
    if($dir==='')return false;
    if(!is_dir($dir) && !@mkdir($dir,0770,true) && !is_dir($dir))return false;
    @chmod($dir,0770);
    return is_dir($dir) && is_writable($dir);
}

function private_storage_primary_dir(string $area): string {
    $area=preg_replace('/[^a-z0-9_-]/i','',strtolower($area))??'';
    if($area==='')throw new InvalidArgumentException('Invalid private storage area.');

    $override=private_storage_area_override($area);
    if($override!=='' && private_storage_ensure_dir($override))return $override;

    $base=private_storage_external_base();
    if($base!==''){
        $candidate=$base.'/'.$area;
        if(!private_storage_path_is_inside_project($candidate) && private_storage_ensure_dir($candidate))return $candidate;
    }

    $legacy=private_storage_legacy_dir($area);
    private_storage_ensure_dir($legacy);
    return $legacy;
}

function private_storage_dirs(string $area): array {
    $primary=private_storage_primary_dir($area);
    $legacy=private_storage_legacy_dir($area);
    $dirs=[$primary];
    if(str_replace('\\','/',$legacy)!==str_replace('\\','/',$primary))$dirs[]=$legacy;
    return array_values(array_unique($dirs));
}

function private_storage_existing_paths(string $area,string $storedName): array {
    $stored=basename($storedName);
    if($stored===''||$stored!==$storedName)return [];
    $paths=[];
    foreach(private_storage_dirs($area) as $dir){
        $path=$dir.'/'.$stored;
        if(is_file($path))$paths[]=$path;
    }
    return $paths;
}

function private_storage_find(string $area,string $storedName): ?string {
    $paths=private_storage_existing_paths($area,$storedName);
    return $paths[0]??null;
}

function private_storage_delete(string $area,string $storedName): bool {
    $paths=private_storage_existing_paths($area,$storedName);
    if(!$paths)return true;
    $ok=true;
    foreach($paths as $path){if(is_file($path)&&!@unlink($path))$ok=false;}
    return $ok;
}

function private_storage_is_external(string $path): bool {
    return $path!=='' && !private_storage_path_is_inside_project($path);
}

function private_storage_status(string $area): array {
    $primary=private_storage_primary_dir($area);
    return [
        'area'=>$area,
        'primary'=>$primary,
        'external'=>private_storage_is_external($primary),
        'writable'=>is_dir($primary)&&is_writable($primary),
        'legacy'=>private_storage_legacy_dir($area),
        'search_dirs'=>private_storage_dirs($area),
    ];
}
