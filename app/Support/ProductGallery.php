<?php
declare(strict_types=1);
namespace App\Support;

use App\Support\PublicException;

/** Only existing owned paths and newly validated uploads can enter a gallery. */
final class ProductGallery
{
    public static function arrange(?array $existing,array $input,string $uploadedMain,array $uploadedGallery): array
    {
        $main=trim((string)($existing['main_image_path']??''));
        $saved=json_decode((string)($existing['gallery_json']??'[]'),true);
        $saved=is_array($saved)?array_values(array_unique(array_filter($saved,static fn($v)=>is_string($v)&&trim($v)!==''))):[];
        $removals=is_array($input['remove_gallery']??null)?$input['remove_gallery']:[];
        $removals=array_map('strval',$removals);
        $gallery=array_values(array_filter($saved,static fn($v)=>!in_array($v,$removals,true)));
        $order=json_decode((string)($input['gallery_order']??'[]'),true);
        if(is_array($order)){
            $sorted=[];
            foreach($order as $path){if(is_string($path)&&in_array($path,$gallery,true)&&!in_array($path,$sorted,true))$sorted[]=$path;}
            foreach($gallery as $path)if(!in_array($path,$sorted,true))$sorted[]=$path;
            $gallery=$sorted;
        }
        if(!empty($input['remove_main_image']))$main='';
        $promote=(string)($input['promote_gallery_path']??'');
        if($uploadedMain!=='')$main=$uploadedMain;
        elseif($promote!=='' && in_array($promote,$gallery,true)){
            $gallery=array_values(array_filter($gallery,static fn($v)=>$v!==$promote));
            if($main!==''&&$main!==$promote)$gallery[]=$main;
            $main=$promote;
        }
        foreach($uploadedGallery as $path){if(is_string($path)&&$path!==''&&!in_array($path,$gallery,true)&&$path!==$main)$gallery[]=$path;}
        if($main===''&&$gallery){$main=array_shift($gallery);}
        $gallery=array_values(array_filter($gallery,static fn($path)=>$path!==$main));
        if(count($gallery)+($main!==''?1:0)>6)throw new PublicException('A product can have at most six photos. Remove existing images first.');
        return ['main'=>$main,'gallery'=>$gallery];
    }
}
