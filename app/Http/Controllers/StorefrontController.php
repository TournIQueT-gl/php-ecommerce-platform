<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\Logger;
use App\Support\ServiceFactory;

final class StorefrontController
{
    public function handle(): array
    {
        $schemaReady=false;
        $settings=[];
        $sections=[];
        $products=[];
        $brands=[];
        $categories=[];
        $databaseReady=false;
        $setupMessage='';

        try {
            $repo=ServiceFactory::storefront(\db());
            $databaseReady=true;
            $schemaReady=$repo->schemaReady();
            if($schemaReady){
                $settings=$repo->settings();
                $catalogEnabled=\store_catalog_enabled($settings);
                $previewRequested=!empty($_GET['homepage_preview']);
                $previewUser=null;try{$previewUser=\current_user();}catch(\Throwable){}
                $homepagePreview=$previewRequested&&is_array($previewUser)&&!empty($previewUser['is_admin']);
                $homepagePreviewSection=$homepagePreview?max(0,(int)($_GET['homepage_preview_section']??0)):0;
                if($catalogEnabled||$homepagePreview){
                    $sections=$repo->homepageSections($homepagePreview,$homepagePreviewSection);
                    // Builder-managed manual sections are intentionally literal: an empty
                    // section stays empty instead of silently filling itself with unrelated
                    // products/brands/categories. Automatic product sections are populated
                    // by StorefrontRepository::homepageProductSource().
                }else{$setupMessage='The catalog is temporarily unavailable.';}
            } else {
                $setupMessage='Database connected. Apply commerce migration 034 to enable live catalog and CMS data.';
            }
        } catch (\Throwable $e) {
            // The public design preview must remain available on a fresh local copy
            // before MySQL has been configured. Admin/catalog operations still require DB.
            $setupMessage='Storefront preview mode: connect the local database to enable catalog, admin CMS, cart and orders.';
            Logger::warning('Storefront database unavailable; rendering preview fallback', [
                'message'=>$e->getMessage(),
            ]);
        }

        $user=null;
        try { $user=\current_user(); } catch (\Throwable) { $user=null; }
        $homepagePreview=$homepagePreview??false;
        return compact('databaseReady','schemaReady','setupMessage','settings','sections','products','brands','categories','user','homepagePreview');
    }
}
