<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\PublicException;

final class MediaService
{
    private string $root;
    private string $uploadRoot;

    public function __construct(?string $root=null)
    {
        $this->root=$root!==null?rtrim($root,'/\\'):dirname(__DIR__,2);
        $this->uploadRoot=$this->root.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'media';
    }

    public function optimizerAvailable(): bool
    {
        return function_exists('imagecreatetruecolor')&&function_exists('imagecopyresampled')&&function_exists('imagewebp');
    }

    public function storeOne(?array $file,string $folder='general',int $minWidth=0,int $minHeight=0,bool $allowGif=true): ?string
    {
        if(!$file || !isset($file['error'])) return null;
        $error=(int)$file['error'];
        if($error===UPLOAD_ERR_NO_FILE) return null;
        if($error!==UPLOAD_ERR_OK) throw new PublicException($this->uploadErrorMessage($error));

        $tmp=(string)($file['tmp_name']??'');
        $size=(int)($file['size']??0);
        if($tmp==='' || !is_uploaded_file($tmp)) throw new PublicException('The uploaded image could not be validated.');
        // Browser-side preparation normally reduces storefront images before the
        // HTTP request. Servers with GD can safely accept a larger fallback source
        // and optimize it; servers without GD refuse multi-megabyte originals so
        // a missing PHP extension cannot silently destroy storefront performance.
        if($size<=0 || $size>8*1024*1024) throw new PublicException('The uploaded image is too large. Use an image smaller than 8 MB or let the Admin browser optimizer prepare it before upload.');

        $imageInfo=@getimagesize($tmp);
        $width=(int)($imageInfo[0]??0);$height=(int)($imageInfo[1]??0);
        if($width<=0||$height<=0)throw new PublicException('The uploaded file is not a readable image.');
        if($width>5000||$height>5000)throw new PublicException('This image is too large. Use an image no larger than 5000 × 5000 pixels.');
        if(($minWidth>0&&$width<$minWidth)||($minHeight>0&&$height<$minHeight))throw new PublicException('This image is too small. Use at least '.$minWidth.' × '.$minHeight.' pixels for this placement.');

        $mime=$this->mime($tmp);
        if(!$this->optimizerAvailable()&&$mime!=='image/gif'&&$size>2*1024*1024)throw new PublicException('Server image optimization is unavailable. Re-select this image in Admin so the browser can compress it below 2 MB before upload.');
        if($mime==='image/gif'&&$size>4*1024*1024)throw new PublicException('GIF images must be smaller than 4 MB.');
        $ext=match($mime){
            'image/jpeg'=>'jpg',
            'image/png'=>'png',
            'image/webp'=>'webp',
            'image/gif'=>'gif',
            default=>throw new PublicException('Use JPG, PNG, WEBP or GIF images.'),
        };

        $safeFolder=preg_replace('/[^a-z0-9_-]+/i','-',trim($folder))?:'general';
        if($mime==='image/gif'&&(!$allowGif||$safeFolder==='products'))throw new PublicException('Storefront images must be JPG, PNG or WEBP. Animated GIFs are not supported in product or content placements.');
        $year=gmdate('Y');$month=gmdate('m');
        $dir=$this->uploadRoot.DIRECTORY_SEPARATOR.$safeFolder.DIRECTORY_SEPARATOR.$year.DIRECTORY_SEPARATOR.$month;
        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)) throw new PublicException('Media storage is not writable.');

        $base=bin2hex(random_bytes(12));
        $canOptimize=$this->optimizerAvailable()&&$mime!=='image/gif';
        if($canOptimize){
            $master=$dir.DIRECTORY_SEPARATOR.$base.'.webp';
            if($this->optimizeToWebp($tmp,$mime,$master,$this->masterMaxDimension($safeFolder),in_array($safeFolder,['homepage','campaigns'],true)?90:82)){
                @chmod($master,0644);
                // Generate delivery-sized variants once, at upload time. Product cards
                // should never need the full original photo merely to render a 300px tile.
                $this->optimizeToWebp($tmp,$mime,$dir.DIRECTORY_SEPARATOR.$base.'-lg.webp',1920,in_array($safeFolder,['homepage','campaigns'],true)?90:82);
                $this->optimizeToWebp($tmp,$mime,$dir.DIRECTORY_SEPARATOR.$base.'-md.webp',1200,in_array($safeFolder,['homepage','campaigns'],true)?86:80);
                $this->optimizeToWebp($tmp,$mime,$dir.DIRECTORY_SEPARATOR.$base.'-sm.webp',640,78);
                @chmod($dir.DIRECTORY_SEPARATOR.$base.'-lg.webp',0644);
                @chmod($dir.DIRECTORY_SEPARATOR.$base.'-md.webp',0644);
                @chmod($dir.DIRECTORY_SEPARATOR.$base.'-sm.webp',0644);
                $this->invalidateIndex();
                return 'uploads/media/'.$safeFolder.'/'.$year.'/'.$month.'/'.$base.'.webp';
            }
        }

        // GIFs keep their animation and servers without GD still get a safe fallback.
        $filename=$base.'.'.$ext;$dest=$dir.DIRECTORY_SEPARATOR.$filename;
        if(!@move_uploaded_file($tmp,$dest)) throw new PublicException('The uploaded image could not be saved.');
        @chmod($dest,0644);$this->invalidateIndex();
        return 'uploads/media/'.$safeFolder.'/'.$year.'/'.$month.'/'.$filename;
    }

    public function storeMany(?array $files,string $folder='gallery',int $minWidth=0,int $minHeight=0,int $maxFiles=20): array
    {
        if(!$files || !isset($files['name']) || !is_array($files['name'])) return [];
        $maxFiles=max(1,min(50,$maxFiles));
        $out=[];$count=min(count($files['name']),$maxFiles);
        try{
            for($i=0;$i<$count;$i++){
                $file=['name'=>$files['name'][$i]??'','type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];
                $stored=$this->storeOne($file,$folder,$minWidth,$minHeight);if($stored!==null)$out[]=$stored;
            }
            return $out;
        }catch(\Throwable $e){
            foreach($out as $stored)$this->delete($stored);
            throw $e;
        }
    }

    public function validateExisting(string $relativePath,int $minWidth=0,int $minHeight=0): string
    {
        $relativePath=ltrim(str_replace('\\','/',$relativePath),'/');
        if(!str_starts_with($relativePath,'uploads/media/'))throw new PublicException('Choose an image from the Media Library.');
        $full=realpath($this->root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath));$media=realpath($this->uploadRoot);
        if($full===false||$media===false||!is_file($full)||!str_starts_with(str_replace('\\','/',$full),rtrim(str_replace('\\','/',$media),'/').'/'))throw new PublicException('The selected Media Library image no longer exists.');
        $info=@getimagesize($full);$width=(int)($info[0]??0);$height=(int)($info[1]??0);
        if($width<=0||$height<=0)throw new PublicException('The selected Media Library file is not a readable image.');
        if(($minWidth>0&&$width<$minWidth)||($minHeight>0&&$height<$minHeight))throw new PublicException('The selected image is too small. Use at least '.$minWidth.' × '.$minHeight.' pixels.');
        return $relativePath;
    }

    public function delete(string $relativePath): bool
    {
        $relativePath=ltrim(str_replace('\\','/',$relativePath),'/');
        if(!str_starts_with($relativePath,'uploads/media/')) return false;
        $full=realpath($this->root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath));$media=realpath($this->uploadRoot);
        if($full===false || $media===false || !str_starts_with(str_replace('\\','/',$full),rtrim(str_replace('\\','/',$media),'/').'/')) return false;
        $deleted=is_file($full)?@unlink($full):false;
        if($deleted){
            $base=preg_replace('/\.[^.]+$/','',$full)??$full;
            foreach(['-sm.webp','-md.webp','-lg.webp'] as $suffix){$variant=$base.$suffix;if(is_file($variant))@unlink($variant);}
            $this->invalidateIndex();
        }
        return $deleted;
    }

    public function list(int $limit=300): array
    {
        if(!is_dir($this->uploadRoot)) return [];
        $limit=max(1,min(10000,$limit));$cacheFile=$this->root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'media-index.json';$rows=null;
        if(is_file($cacheFile)&&filemtime($cacheFile)!==false&&filemtime($cacheFile)>=time()-60){$decoded=json_decode((string)@file_get_contents($cacheFile),true);if(is_array($decoded))$rows=$decoded;}
        if(!is_array($rows)){
            $rows=[];$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->uploadRoot,\FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){
                if(!$file->isFile())continue;$ext=strtolower($file->getExtension());if(!in_array($ext,['jpg','jpeg','png','webp','gif'],true))continue;
                if(preg_match('/-(?:sm|md|lg)\.webp$/i',$file->getFilename()))continue;
                $full=str_replace('\\','/',$file->getPathname());$root=str_replace('\\','/',$this->root).'/';if(!str_starts_with($full,$root))continue;
                $dim=@getimagesize($file->getPathname());$relative=substr($full,strlen($root));$parts=explode('/',$relative);$folder=(string)($parts[2]??'general');$rows[]=['path'=>$relative,'filename'=>$file->getFilename(),'folder'=>$folder,'size'=>$file->getSize(),'modified_at'=>$file->getMTime(),'width'=>(int)($dim[0]??0),'height'=>(int)($dim[1]??0)];
            }
            usort($rows,static fn($a,$b)=>($b['modified_at']<=>$a['modified_at']));
            $dir=dirname($cacheFile);if(is_dir($dir)&&is_writable($dir)){$tmp=$cacheFile.'.tmp';if(@file_put_contents($tmp,json_encode($rows,JSON_UNESCAPED_SLASHES))!==false)@rename($tmp,$cacheFile);else @unlink($tmp);}
        }
        return array_slice($rows,0,$limit);
    }

    public function optimizeExisting(int $limit=1000): array
    {
        if(!$this->optimizerAvailable())throw new PublicException('PHP GD with WEBP support is required to optimize existing media.');
        $limit=max(1,min(5000,$limit));$rows=$this->list($limit);
        $stats=['scanned'=>0,'generated'=>0,'already_ready'=>0,'skipped'=>0,'failed'=>0];
        foreach($rows as $row){
            $relative=(string)($row['path']??'');if($relative==='')continue;$stats['scanned']++;
            $full=$this->absoluteMediaPath($relative);if($full===null){$stats['failed']++;continue;}
            $mime=$this->mime($full);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){$stats['skipped']++;continue;}
            $base=preg_replace('/\.[^.]+$/','',$full)??$full;
            $editorial=str_contains($relative,'/homepage/')||str_contains($relative,'/campaigns/');
            $targets=[['suffix'=>'-sm.webp','max'=>640,'quality'=>78],['suffix'=>'-md.webp','max'=>1200,'quality'=>$editorial?86:80],['suffix'=>'-lg.webp','max'=>1920,'quality'=>$editorial?90:82]];
            $missing=[];foreach($targets as $target){$destination=$base.$target['suffix'];if(is_file($destination)&&filesize($destination)>0)continue;$missing[]=$target;}
            if(!$missing){$stats['already_ready']++;continue;}
            $ok=true;foreach($missing as $target){$destination=$base.$target['suffix'];if(!$this->optimizeToWebp($full,$mime,$destination,(int)$target['max'],(int)$target['quality'])){$ok=false;@unlink($destination);continue;}@chmod($destination,0644);$stats['generated']++;}
            if(!$ok)$stats['failed']++;
        }
        $this->invalidateIndex();return $stats;
    }

    private function absoluteMediaPath(string $relativePath): ?string
    {
        $relativePath=ltrim(str_replace('\\','/',$relativePath),'/');
        if(!str_starts_with($relativePath,'uploads/media/'))return null;
        $candidate=$this->root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath);$full=realpath($candidate);$media=realpath($this->uploadRoot);
        if($full===false||$media===false||!is_file($full))return null;
        $normalFull=str_replace('\\','/',$full);$normalMedia=rtrim(str_replace('\\','/',$media),'/').'/';
        return str_starts_with($normalFull,$normalMedia)?$full:null;
    }

    private function masterMaxDimension(string $folder): int
    {
        return in_array($folder,['homepage','campaigns'],true)?2400:1800;
    }

    private function optimizeToWebp(string $source,string $mime,string $destination,int $maxDimension,int $quality): bool
    {
        $image=match($mime){
            'image/jpeg'=>function_exists('imagecreatefromjpeg')?@imagecreatefromjpeg($source):false,
            'image/png'=>function_exists('imagecreatefrompng')?@imagecreatefrompng($source):false,
            'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($source):false,
            default=>false,
        };
        if(!$image)return false;
        $width=imagesx($image);$height=imagesy($image);if($width<=0||$height<=0){imagedestroy($image);return false;}
        $scale=min(1,$maxDimension/max($width,$height));$targetWidth=max(1,(int)round($width*$scale));$targetHeight=max(1,(int)round($height*$scale));
        $canvas=imagecreatetruecolor($targetWidth,$targetHeight);if(!$canvas){imagedestroy($image);return false;}
        imagealphablending($canvas,false);imagesavealpha($canvas,true);$transparent=imagecolorallocatealpha($canvas,0,0,0,127);imagefill($canvas,0,0,$transparent);
        $ok=imagecopyresampled($canvas,$image,0,0,0,0,$targetWidth,$targetHeight,$width,$height)&&@imagewebp($canvas,$destination,max(1,min(100,$quality)));
        imagedestroy($canvas);imagedestroy($image);return $ok&&is_file($destination)&&filesize($destination)>0;
    }

    private function invalidateIndex(): void
    {
        $file=$this->root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'media-index.json';if(is_file($file))@unlink($file);
    }

    private function mime(string $path): string
    {
        if(function_exists('finfo_open')){$f=@finfo_open(FILEINFO_MIME_TYPE);if($f){$m=(string)@finfo_file($f,$path);@finfo_close($f);if($m!=='')return strtolower($m);}}
        $info=@getimagesize($path);return strtolower((string)($info['mime']??''));
    }

    private function uploadErrorMessage(int $error): string
    {
        return match($error){UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'The image is larger than the server upload limit.',UPLOAD_ERR_PARTIAL=>'The image upload was interrupted. Try again.',UPLOAD_ERR_NO_TMP_DIR=>'The server upload directory is unavailable.',UPLOAD_ERR_CANT_WRITE=>'The server could not write the uploaded image.',UPLOAD_ERR_EXTENSION=>'The image upload was blocked by a server extension.',default=>'The image upload failed.',};
    }
}
