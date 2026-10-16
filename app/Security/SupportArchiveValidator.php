<?php
declare(strict_types=1);

namespace App\Security;

use App\Support\PublicException;

final class SupportArchiveValidator
{
    private const MAX_ENTRIES = 250;
    private const MAX_UNCOMPRESSED_BYTES = 104857600; // 100 MiB total after extraction.
    private const MAX_SINGLE_FILE_BYTES = 52428800; // 50 MiB per entry.
    private const MAX_COMPRESSION_RATIO = 200.0;
    private const MAX_ENTRY_NAME_BYTES = 240;

    public static function validate(string $path,int $archiveBytes): void
    {
        if(!class_exists(\ZipArchive::class)){
            // Fail closed: accepting an archive we cannot inspect defeats the
            // decompression/path hardening. Raw LOG/TXT/JSON attachments remain
            // available when the host does not provide ext-zip.
            throw new PublicException('ZIP diagnostics are unavailable because secure archive inspection is not supported on this server. Upload LOG, TXT, or JSON instead.');
        }
        if($archiveBytes<=0)throw new PublicException('The ZIP attachment is empty.');

        $zip=new \ZipArchive();
        $open=$zip->open($path,\ZipArchive::RDONLY);
        if($open!==true)throw new PublicException('The ZIP attachment could not be opened.');
        try{
            if($zip->numFiles<=0)throw new PublicException('The ZIP attachment is empty.');
            if($zip->numFiles>self::MAX_ENTRIES)throw new PublicException('The ZIP attachment contains too many files.');

            $totalUncompressed=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i,\ZipArchive::FL_UNCHANGED);
                if(!is_array($stat))throw new PublicException('The ZIP attachment contains an unreadable entry.');
                $name=str_replace('\\','/',trim((string)($stat['name']??'')));
                if($name===''||strlen($name)>self::MAX_ENTRY_NAME_BYTES)throw new PublicException('The ZIP attachment contains an invalid file name.');
                if(str_starts_with($name,'/')||preg_match('/^[A-Za-z]:\//',$name)||str_contains($name,"\0"))throw new PublicException('The ZIP attachment contains an unsafe file path.');
                $parts=array_filter(explode('/',$name),static fn(string $v):bool=>$v!=='');
                if(in_array('..',$parts,true))throw new PublicException('The ZIP attachment contains an unsafe file path.');
                if(str_ends_with($name,'/'))continue;

                $size=max(0,(int)($stat['size']??0));
                $compressed=max(0,(int)($stat['comp_size']??0));
                if($size>self::MAX_SINGLE_FILE_BYTES)throw new PublicException('A file inside the ZIP attachment is too large.');
                $totalUncompressed+=$size;
                if($totalUncompressed>self::MAX_UNCOMPRESSED_BYTES)throw new PublicException('The ZIP attachment expands to too much data.');
                if($size>0&&$compressed===0)throw new PublicException('The ZIP attachment has an invalid compression entry.');
                if($compressed>0&&($size/$compressed)>self::MAX_COMPRESSION_RATIO)throw new PublicException('The ZIP attachment has an unsafe compression ratio.');
            }
        } finally {
            $zip->close();
        }
    }
}
