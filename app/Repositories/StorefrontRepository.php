<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use App\Support\DepartmentVisibility;
use App\Support\VariantOffer;

final class StorefrontRepository
{
    public function __construct(private PDO $db) {}


    /** Only active, explicitly configured departments appear in the storefront. */
    public function activeDepartments(): array
    {
        $sql="SELECT d.id,d.code,d.name,d.description FROM commerce_departments d WHERE d.status='active' ORDER BY d.sort_order,d.id";
        return $this->cachedArray('alpha6-active-departments',15,fn()=> $this->db->query($sql)->fetchAll());
    }

    /** One read for the header, instead of one category query per department on every page. */
    public function navigationDepartments(): array
    {
        return $this->cachedArray('alpha7-5-navigation-departments',30,function():array{
            $sql="SELECT d.id AS department_id,d.code,d.name,d.description,c.id AS category_id,c.name AS category_name,c.name_ar AS category_name_ar,c.slug AS category_slug
                  FROM commerce_departments d
                  LEFT JOIN commerce_department_categories dc ON dc.department_id=d.id
                  LEFT JOIN store_categories c ON c.id=dc.category_id AND c.status='active'
                  WHERE d.status='active'
                  ORDER BY d.sort_order,d.id,(c.parent_id IS NOT NULL),c.sort_order,c.name LIMIT 650";
            $departments=[];
            foreach($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
                $code=(string)$row['code'];
                if(!isset($departments[$code])){
                    $departments[$code]=['id'=>(int)$row['department_id'],'code'=>$code,'name'=>(string)$row['name'],'description'=>(string)($row['description']??''),'categories'=>[]];
                }
                if($row['category_id']!==null&&count($departments[$code]['categories'])<6){
                    $departments[$code]['categories'][]=['id'=>(int)$row['category_id'],'name'=>(string)$row['category_name'],'name_ar'=>(string)($row['category_name_ar']??''),'slug'=>(string)$row['category_slug']];
                }
            }
            return array_values($departments);
        });
    }

    public function departmentForProduct(int $productId): ?array
    {
        if($productId<1)return null;
        $stmt=$this->db->prepare("SELECT d.code,d.name FROM commerce_product_departments pd JOIN commerce_departments d ON d.id=pd.department_id WHERE pd.product_id=? AND d.status='active' LIMIT 1");
        $stmt->execute([$productId]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    /** Header browse index: only brands currently linked to public, published products. */
    public function navigationBrands(int $limit=24): array
    {
        $limit=max(1,min(48,$limit));
        return $this->cachedArray('navigation-brands-'.$limit,60,function()use($limit):array{
            $sql="SELECT DISTINCT b.id,b.name,b.name_ar,b.slug FROM store_brands b
                JOIN store_products p ON p.brand_id=b.id
                WHERE b.status='active' AND p.status='active'
                  AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP())
                  AND ".DepartmentVisibility::product('p')."
                ORDER BY b.name LIMIT {$limit}";
            return $this->db->query($sql)->fetchAll();
        });
    }

    public function brandsForDepartment(string $departmentCode): array
    {
        $stmt=$this->db->prepare("SELECT DISTINCT b.* FROM store_brands b JOIN store_products p ON p.brand_id=b.id JOIN commerce_product_departments pd ON pd.product_id=p.id JOIN commerce_departments d ON d.id=pd.department_id WHERE d.code=? AND d.status='active' AND b.status='active' AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) ORDER BY b.name LIMIT 150");
        $stmt->execute([$departmentCode]);return $stmt->fetchAll();
    }

    public function departmentCategories(string $departmentCode): array
    {
        $stmt=$this->db->prepare("SELECT c.id,c.name,c.slug,c.parent_id FROM store_categories c JOIN commerce_department_categories dc ON dc.category_id=c.id JOIN commerce_departments d ON d.id=dc.department_id WHERE c.status='active' AND d.status='active' AND d.code=? ORDER BY c.sort_order,c.name LIMIT 200");
        $stmt->execute([$departmentCode]);return $stmt->fetchAll();
    }

    /** Public facet options are selected from category configuration, not fixed perfume fields. */
    public function catalogAttributeFacets(string $departmentCode='',string $categorySlug=''): array
    {
        $sql="SELECT DISTINCT a.code,a.label,a.scope,o.code AS option_code,o.label AS option_label,a.sort_order,o.sort_order AS option_sort FROM catalog_attributes a JOIN catalog_category_attributes ca ON ca.attribute_id=a.id JOIN store_categories c ON c.id=ca.category_id JOIN catalog_attribute_options o ON o.attribute_id=a.id AND o.status='active' LEFT JOIN commerce_department_categories dc ON dc.category_id=c.id LEFT JOIN commerce_departments d ON d.id=dc.department_id WHERE a.status='active' AND a.is_filterable=1 AND ca.is_filterable=1 AND c.status='active' AND (d.id IS NULL OR d.status='active')";
        $params=[];
        if($departmentCode!==''){$sql.=' AND d.code=?';$params[]=$departmentCode;}
        if($categorySlug!==''){$sql.=' AND c.slug=?';$params[]=$categorySlug;}
        $sql.=' ORDER BY a.sort_order,a.code,o.sort_order,o.label LIMIT 160';
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$out=[];
        foreach($stmt->fetchAll() as $row){$code=(string)$row['code'];if(!isset($out[$code]))$out[$code]=['label'=>$row['label'],'options'=>[]];$out[$code]['options'][(string)$row['option_code']]=$row['option_label'];}
        return $out;
    }

    public function productSpecifications(int $productId): array
    {
        if($productId<1)return [];
        $stmt=$this->db->prepare("SELECT a.label,a.unit,COALESCE(o.label,v.value_text,CAST(v.value_decimal AS CHAR),CASE WHEN v.value_boolean=1 THEN 'Yes' WHEN v.value_boolean=0 THEN 'No' END,CAST(v.value_date AS CHAR)) AS display_value FROM catalog_product_attribute_values v JOIN catalog_attributes a ON a.id=v.attribute_id AND a.status='active' LEFT JOIN catalog_attribute_options o ON o.id=v.option_id WHERE v.product_id=? ORDER BY a.sort_order,a.id");
        $stmt->execute([$productId]);return array_values(array_filter($stmt->fetchAll(),static fn($r)=>trim((string)($r['display_value']??''))!==''));
    }

    public function variantAttributeOptions(int $productId): array
    {
        if($productId<1)return [];
        $stmt=$this->db->prepare("SELECT v.id AS variant_id,a.code,a.label AS attribute_label,o.code AS option_code,o.label AS option_label,a.sort_order,o.sort_order AS option_sort FROM store_product_variants v JOIN catalog_variant_option_values vo ON vo.variant_id=v.id JOIN catalog_attributes a ON a.id=vo.attribute_id AND a.status='active' JOIN catalog_attribute_options o ON o.id=vo.option_id WHERE v.product_id=? AND v.status='active' ORDER BY a.sort_order,a.id,o.sort_order,o.label");
        $stmt->execute([$productId]);return $stmt->fetchAll();
    }

    public function schemaReady(): bool
    {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('store_products','store_product_variants','homepage_sections','homepage_section_items')");
            return $ready=((int)$stmt->fetchColumn()===4);
        } catch (\Throwable) {
            return $ready=false;
        }
    }

    public function settings(): array
    {
        if(!$this->schemaReady())return [];
        $shared=$GLOBALS['_store_settings_db_cache']??null;
        if(is_array($shared))return $shared;
        $rows=$this->db->query('SELECT setting_key,setting_value FROM store_settings')->fetchAll();
        $out=[];foreach($rows as $row)$out[(string)$row['setting_key']]=$row['setting_value'];
        $GLOBALS['_store_settings_db_cache']=$out;
        return $out;
    }

    public function homepageSections(bool $includeDraft=false,?int $previewSectionId=null): array
    {
        if(!$this->schemaReady())return [];
        $previewId=max(0,(int)($previewSectionId??0));
        $where=$includeDraft
            ? "(status IN ('active','draft','scheduled')".($previewId>0?" OR (status='hidden' AND id={$previewId})":'').")"
            : "(status='active' OR (status='scheduled' AND starts_at IS NOT NULL AND starts_at<=UTC_TIMESTAMP())) AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at>=UTC_TIMESTAMP())";
        $sections=$this->db->query("SELECT * FROM homepage_sections WHERE {$where} ORDER BY sort_order,id")->fetchAll();
        if(!$sections)return [];

        // Load all section items in one query. The previous implementation ran one
        // nearly identical item query per homepage section (classic N+1), which made
        // every extra CMS section add another round trip to MySQL.
        $sectionIds=array_values(array_filter(array_map(static fn($row)=>(int)($row['id']??0),$sections),static fn($id)=>$id>0));
        $itemsBySection=[];
        if($sectionIds){
            $itemProductVisibility=$includeDraft?'':" AND (i.product_id IS NULL OR (p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p').")) AND (i.brand_id IS NULL OR b.status='active') AND (i.category_id IS NULL OR (c.status='active' AND ".DepartmentVisibility::category('c')."))";
            $marks=implode(',',array_fill(0,count($sectionIds),'?'));
            $sql="SELECT i.*,p.name AS product_name,p.slug AS product_slug,p.main_image_path AS product_image,p.gender AS product_gender,p.concentration AS concentration,ff.name AS family_name,b.name AS brand_name,b.slug AS brand_slug,c.name AS category_name,c.slug AS category_slug,".VariantOffer::sql('p','price')." AS product_price,".VariantOffer::sql('p','regular_price')." AS regular_price,".VariantOffer::sql('p','currency')." AS product_currency,(SELECT SUM(GREATEST(v3.stock_qty,0)) FROM store_product_variants v3 WHERE v3.product_id=p.id AND v3.status='active') AS available_stock,(SELECT COUNT(*) FROM store_product_variants v4 WHERE v4.product_id=p.id AND v4.status='active') AS active_variant_count,(SELECT v6.id FROM store_product_variants v6 WHERE v6.product_id=p.id AND v6.status='active' ORDER BY v6.sort_order,v6.id LIMIT 1) AS first_available_variant_id,(SELECT v7.label FROM store_product_variants v7 WHERE v7.product_id=p.id AND v7.status='active' ORDER BY v7.sort_order,v7.id LIMIT 1) AS first_available_variant_label,p.is_bestseller,p.is_new_arrival,p.is_exclusive FROM homepage_section_items i LEFT JOIN store_products p ON p.id=i.product_id LEFT JOIN store_brands b ON b.id=COALESCE(i.brand_id,p.brand_id) LEFT JOIN store_categories c ON c.id=i.category_id LEFT JOIN fragrance_families ff ON ff.id=p.fragrance_family_id WHERE i.section_id IN ({$marks}) AND i.status='active'{$itemProductVisibility} ORDER BY i.section_id,i.sort_order,i.id";
            $stmt=$this->db->prepare($sql);$stmt->execute($sectionIds);
            foreach($stmt->fetchAll() as $item){$sid=(int)($item['section_id']??0);if($sid>0)$itemsBySection[$sid][]=$item;}
            $stmt->closeCursor();
        }

        foreach($sections as &$section){
            $section['items']=$itemsBySection[(int)($section['id']??0)]??[];
            $settings=json_decode((string)($section['settings_json']??''),true);if(!is_array($settings))$settings=[];
            if((string)($section['section_type']??'')==='product_carousel'){
                $source=(string)($settings['product_source']??'manual');
                if($source!=='manual')$section['source_products']=$this->homepageProductSource($source,(int)($settings['max_items']??5));
            }
        }
        unset($section);
        return $sections;
    }

    private function homepageProductSource(string $source,int $limit): array
    {
        $limit=max(1,min(24,$limit));
        return $this->cachedArray('home-source-'.$source.'-'.$limit,10,function() use($source,$limit): array {
            $where="p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p');
            $order='p.id DESC';
            if($source==='featured'){$where.=' AND p.is_featured=1';$order='p.is_bestseller DESC,p.id DESC';}
            elseif($source==='bestselling'){$where.=' AND p.is_bestseller=1';$order='p.is_featured DESC,p.id DESC';}
            elseif($source==='new'){$where.=' AND p.is_new_arrival=1';$order='p.published_at DESC,p.id DESC';}
            elseif($source==='sale'){$where.=" AND EXISTS(SELECT 1 FROM store_product_variants sx WHERE sx.product_id=p.id AND sx.status='active' AND sx.sale_price IS NOT NULL AND sx.sale_price>0 AND sx.sale_price<sx.price)";$order='p.id DESC';}
            else return [];
            return $this->db->query($this->productSelect()." WHERE {$where} ORDER BY {$order} LIMIT {$limit}")->fetchAll();
        });
    }

    public function featuredProducts(int $limit=8): array
    {
        if(!$this->schemaReady())return [];
        $limit=max(1,min(24,$limit));
        return $this->cachedArray('featured-products-'.$limit,10,fn()=> $this->db->query($this->productSelect()." WHERE p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." ORDER BY p.is_featured DESC,p.is_bestseller DESC,p.is_new_arrival DESC,p.id DESC LIMIT {$limit}")->fetchAll());
    }

    public function featuredBrands(int $limit=12): array
    {
        if(!$this->schemaReady())return [];
        $limit=max(1,min(100,$limit));
        return $this->cachedArray('brands-'.$limit,45,fn()=> $this->db->query("SELECT * FROM store_brands WHERE status='active' ORDER BY is_featured DESC,sort_order,id LIMIT {$limit}")->fetchAll());
    }

    public function categories(int $limit=12): array
    {
        if(!$this->schemaReady())return [];
        $limit=max(1,min(100,$limit));
        return $this->cachedArray('categories-'.$limit,45,fn()=> $this->publicTaxonomyRows($this->db->query("SELECT c.* FROM store_categories c WHERE c.status='active' AND c.parent_id IS NULL AND ".DepartmentVisibility::category('c')." ORDER BY c.sort_order,c.id LIMIT {$limit}")->fetchAll()));
    }

    public function fragranceFamilies(): array
    {
        if(!$this->schemaReady())return [];
        return $this->cachedArray('families',45,fn()=> $this->publicTaxonomyRows($this->db->query("SELECT id,name,name_ar,slug FROM fragrance_families WHERE status='active' ORDER BY sort_order,name")->fetchAll()));
    }

    public function fragranceNotes(int $limit=80): array
    {
        if(!$this->schemaReady())return [];
        $limit=max(1,min(200,$limit));
        return $this->cachedArray('notes-'.$limit,45,fn()=> $this->publicTaxonomyRows($this->db->query("SELECT id,name,name_ar,slug,image_path FROM fragrance_notes WHERE status='active' ORDER BY sort_order,name LIMIT {$limit}")->fetchAll()));
    }


    private function publicTaxonomyRows(array $rows): array
    {
        return array_values(array_filter($rows,static function(array $row): bool {
            $name=trim((string)($row['name']??''));
            return $name!=='' && preg_match('/\p{L}/u',$name)===1;
        }));
    }

    public function concentrations(): array
    {
        if(!$this->schemaReady())return [];
        return $this->cachedArray('concentrations',45,function(): array {
            $rows=$this->db->query("SELECT DISTINCT p.concentration FROM store_products p WHERE p.status='active' AND p.concentration IS NOT NULL AND p.concentration<>'' AND ".DepartmentVisibility::product('p')." ORDER BY p.concentration")->fetchAll();
            return array_values(array_filter(array_map(static fn($r)=>trim((string)($r['concentration']??'')),$rows),static fn($v)=>$v!==''));
        });
    }

    public function publicPrimaryFacetExists(string $facet,string $value): bool
    {
        if(!$this->schemaReady())return false;
        $value=trim($value);if($value==='')return true;
        $sql=match($facet){
            'brand'=>"SELECT 1 FROM store_brands WHERE status='active' AND slug=? LIMIT 1",
            'category'=>"SELECT 1 FROM store_categories c WHERE c.status='active' AND c.slug=? AND ".DepartmentVisibility::category('c')." LIMIT 1",
            default=>'',
        };
        if($sql==='')return false;
        $stmt=$this->db->prepare($sql);$stmt->execute([$value]);return $stmt->fetchColumn()!==false;
    }

    public function catalog(array $filters=[], int $limit=60): array
    {
        $page=$this->catalogPage($filters,1,$limit);
        return $page['items'];
    }

    public function catalogPage(array $filters=[], int $page=1, int $perPage=24): array
    {
        if(!$this->schemaReady())return ['items'=>[],'total'=>0,'page'=>1,'pages'=>0,'per_page'=>$perPage];
        [$joins,$where,$params]=$this->catalogQueryParts($filters);
        $from=" FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id LEFT JOIN fragrance_families ff ON ff.id=p.fragrance_family_id ".$joins;
        $whereSql=' WHERE '.implode(' AND ',$where);

        $count=$this->db->prepare('SELECT COUNT(DISTINCT p.id)'.$from.$whereSql);
        $count->execute($params);
        $total=(int)$count->fetchColumn();
        $count->closeCursor();

        $perPage=max(12,min(60,$perPage));
        $pages=$total>0?(int)ceil($total/$perPage):0;
        $page=max(1,$page);
        if($pages>0)$page=min($page,$pages);
        $offset=($page-1)*$perPage;

        $sort=strtolower((string)($filters['sort']??'bestselling'));
        $queryParams=$params;
        $order=match($sort){
            'new'=>'p.is_new_arrival DESC,p.published_at DESC,p.id DESC',
            'price_asc'=>'price IS NULL ASC,price ASC,p.id DESC',
            'price_desc'=>'price IS NULL ASC,price DESC,p.id DESC',
            'relevance'=>trim((string)($filters['q']??''))!==''?"CASE WHEN LOWER(p.name)=LOWER(?) THEN 0 WHEN p.name LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(b.name)=LOWER(?) THEN 2 WHEN b.name LIKE ? ESCAPE '!' THEN 3 ELSE 4 END,p.is_bestseller DESC,p.is_featured DESC,p.id DESC":'p.is_bestseller DESC,p.is_featured DESC,p.id DESC',
            default=>'p.is_bestseller DESC,p.is_featured DESC,p.id DESC',
        };
        if($sort==='relevance'&&trim((string)($filters['q']??''))!==''){
            $q=trim((string)$filters['q']);$prefix=$this->likeLiteral($q).'%';array_push($queryParams,$q,$prefix,$q,$prefix);
        }
        $sql=$this->productSelect().$joins.$whereSql.' GROUP BY p.id ORDER BY '.$order.' LIMIT '.$perPage.' OFFSET '.$offset;
        $stmt=$this->db->prepare($sql);
        $stmt->execute($queryParams);
        $items=$stmt->fetchAll();
        $stmt->closeCursor();
        return ['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    private function catalogQueryParts(array $filters): array
    {
        $where=["p.status='active'","(p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP())",DepartmentVisibility::product('p')];
        $params=[];
        $joins='';
        $department=trim((string)($filters['department']??''));
        if($department!==''){
            $where[]="EXISTS(SELECT 1 FROM commerce_product_departments pd_filter JOIN commerce_departments d_filter ON d_filter.id=pd_filter.department_id WHERE pd_filter.product_id=p.id AND d_filter.code=? AND d_filter.status='active')";
            $params[]=$department;
        }
        foreach((array)($filters['spec']??[]) as $code=>$optionCode){
            if(!is_string($code)||!preg_match('/^[a-z0-9_-]{1,100}$/i',$code)||!is_string($optionCode)||!preg_match('/^[a-z0-9_-]{1,140}$/i',$optionCode))continue;
            $where[]="(EXISTS(SELECT 1 FROM catalog_product_attribute_values pav_filter JOIN catalog_attributes a_filter ON a_filter.id=pav_filter.attribute_id JOIN catalog_attribute_options o_filter ON o_filter.id=pav_filter.option_id WHERE pav_filter.product_id=p.id AND a_filter.code=? AND o_filter.code=?) OR EXISTS(SELECT 1 FROM store_product_variants vs_filter JOIN catalog_variant_option_values vav_filter ON vav_filter.variant_id=vs_filter.id JOIN catalog_attributes va_filter ON va_filter.id=vav_filter.attribute_id JOIN catalog_attribute_options vo_filter ON vo_filter.id=vav_filter.option_id WHERE vs_filter.product_id=p.id AND vs_filter.status='active' AND va_filter.code=? AND vo_filter.code=?))";
            array_push($params,$code,$optionCode,$code,$optionCode);
        }
        $q=trim((string)($filters['q']??''));
        if($q!==''){
            $where[]="(p.name LIKE ? ESCAPE '!' OR p.name_ar LIKE ? ESCAPE '!' OR b.name LIKE ? ESCAPE '!' OR b.name_ar LIKE ? ESCAPE '!' OR p.short_description LIKE ? ESCAPE '!' OR p.concentration LIKE ? ESCAPE '!' OR ff.name LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM store_product_notes pnq JOIN fragrance_notes fnq ON fnq.id=pnq.note_id WHERE pnq.product_id=p.id AND (fnq.name LIKE ? ESCAPE '!' OR fnq.name_ar LIKE ? ESCAPE '!')) OR EXISTS(SELECT 1 FROM store_product_variants svq WHERE svq.product_id=p.id AND svq.status='active' AND svq.sku LIKE ? ESCAPE '!') OR EXISTS(SELECT 1 FROM store_entity_translations trp WHERE trp.entity_type='product' AND trp.entity_id=p.id AND trp.locale='ar' AND trp.field_key IN ('name','description','short_description') AND trp.text_value LIKE ? ESCAPE '!') OR EXISTS(SELECT 1 FROM store_entity_translations trb WHERE trb.entity_type='brand' AND trb.entity_id=b.id AND trb.locale='ar' AND trb.field_key='name' AND trb.text_value LIKE ? ESCAPE '!'))";
            $needle='%'.$this->likeLiteral($q).'%';array_push($params,...array_fill(0,12,$needle));
        }
        $gender=strtolower(trim((string)($filters['gender']??'')));
        if(in_array($gender,['men','women','unisex'],true)){$where[]='p.gender=?';$params[]=$gender;$where[]="EXISTS(SELECT 1 FROM commerce_product_departments ad JOIN commerce_departments audience_d ON audience_d.id=ad.department_id WHERE ad.product_id=p.id AND audience_d.status='active' AND audience_d.code IN ('perfume','perfumes','fragrance','fragrances','fashion','clothing','apparel'))";}
        $brand=trim((string)($filters['brand']??''));
        if($brand!==''){$where[]='(b.slug=? OR b.name=?)';array_push($params,$brand,$brand);}
        $family=trim((string)($filters['family']??''));
        if($family!==''){$where[]='ff.slug=?';$params[]=$family;}
        $collection=trim((string)($filters['collection']??''));
        $category=trim((string)($filters['category']??''));
        if($category!==''||$collection!==''){
            $joins.=' LEFT JOIN store_product_categories pc_filter ON pc_filter.product_id=p.id LEFT JOIN store_categories c_filter ON c_filter.id=pc_filter.category_id ';
            if($category!==''){$where[]='c_filter.slug=?';$where[]=DepartmentVisibility::category('c_filter');$params[]=$category;}
            else{
                $aliases=match($collection){
                    'niche'=>['niche','niche perfumes'],
                    'arabian'=>['arabian','arabian perfumes','arabian-perfumes'],
                    'gifts'=>['gifts','gift sets','gift set','gift-sets'],
                    default=>[],
                };
                if($aliases){$marks=implode(',',array_fill(0,count($aliases),'?'));$where[]="(LOWER(TRIM(c_filter.name)) IN ({$marks}) OR LOWER(TRIM(c_filter.slug)) IN ({$marks}))";array_push($params,...$aliases,...$aliases);}
            }
        }
        $concentration=trim((string)($filters['concentration']??''));
        if($concentration!==''){$where[]='p.concentration=?';$params[]=$concentration;}
        $note=trim((string)($filters['note']??''));
        if($note!==''){
            $joins.=' LEFT JOIN store_product_notes pn_filter ON pn_filter.product_id=p.id LEFT JOIN fragrance_notes fn_filter ON fn_filter.id=pn_filter.note_id ';
            $where[]='fn_filter.slug=?';$params[]=$note;
        }
        $season=strtolower(trim((string)($filters['season']??'')));
        if($season!==''){$where[]="FIND_IN_SET(?,REPLACE(LOWER(p.season_tags),' ',''))>0";$params[]=$season;}
        $occasion=strtolower(trim((string)($filters['occasion']??'')));
        if($occasion!==''){$where[]="FIND_IN_SET(?,REPLACE(LOWER(p.occasion_tags),' ',''))>0";$params[]=$occasion;}
        if(!empty($filters['sale']))$where[]="EXISTS(SELECT 1 FROM store_product_variants sv WHERE sv.product_id=p.id AND sv.status='active' AND sv.sale_price IS NOT NULL AND sv.sale_price>0 AND sv.sale_price<sv.price)";
        $min=(float)($filters['min_price']??0);$max=(float)($filters['max_price']??0);
        if($min>0){$where[]="(SELECT MIN(CASE WHEN sv.sale_price IS NOT NULL AND sv.sale_price>0 AND sv.sale_price<sv.price THEN sv.sale_price ELSE sv.price END) FROM store_product_variants sv WHERE sv.product_id=p.id AND sv.status='active')>=?";$params[]=$min;}
        if($max>0){$where[]="(SELECT MIN(CASE WHEN sv.sale_price IS NOT NULL AND sv.sale_price>0 AND sv.sale_price<sv.price THEN sv.sale_price ELSE sv.price END) FROM store_product_variants sv WHERE sv.product_id=p.id AND sv.status='active')<=?";$params[]=$max;}
        return [$joins,$where,$params];
    }


    public function catalogFacetCounts(array $filters=[]): array
    {
        if(!$this->schemaReady())return [];
        $loader=function() use($filters): array { return [
            'gender'=>$this->groupedFacetCounts($filters,'gender','p.gender'),
            'brand'=>$this->groupedFacetCounts($filters,'brand','b.slug'),
            'family'=>$this->groupedFacetCounts($filters,'family','ff.slug'),
            'category'=>$this->groupedFacetCounts($filters,'category','c_facet.slug',' JOIN store_product_categories pc_facet ON pc_facet.product_id=p.id JOIN store_categories c_facet ON c_facet.id=pc_facet.category_id '),
            'concentration'=>$this->groupedFacetCounts($filters,'concentration','p.concentration'),
            'note'=>$this->groupedFacetCounts($filters,'note','fn_facet.slug',' JOIN store_product_notes pn_facet ON pn_facet.product_id=p.id JOIN fragrance_notes fn_facet ON fn_facet.id=pn_facet.note_id '),
            'season'=>$this->tagFacetCounts($filters,'season','season_tags'),
            'occasion'=>$this->tagFacetCounts($filters,'occasion','occasion_tags'),
        ];};
        // Search terms are effectively unbounded, so avoid creating persistent cache
        // files for arbitrary queries. Normal browse/filter combinations get a short
        // cache that turns repeated pagination/filter renders into cheap reads.
        if(trim((string)($filters['q']??''))!=='')return $loader();
        $cacheFilters=$filters;unset($cacheFilters['page'],$cacheFilters['sort']);ksort($cacheFilters);
        return $this->cachedArray('facets-'.hash('sha256',json_encode($cacheFilters,JSON_UNESCAPED_SLASHES)?:''),30,$loader);
    }

    private function groupedFacetCounts(array $filters,string $exclude,string $expr,string $extraJoin=''): array
    {
        unset($filters[$exclude],$filters['page']);
        [$joins,$where,$params]=$this->catalogQueryParts($filters);
        if($exclude==='gender')$where[]="EXISTS(SELECT 1 FROM commerce_product_departments ad JOIN commerce_departments audience_d ON audience_d.id=ad.department_id WHERE ad.product_id=p.id AND audience_d.status='active' AND audience_d.code IN ('perfume','perfumes','fragrance','fragrances','fashion','clothing','apparel'))";
        $sql='SELECT '.$expr.' AS facet_value,COUNT(DISTINCT p.id) AS facet_count FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id LEFT JOIN fragrance_families ff ON ff.id=p.fragrance_family_id '.$joins.$extraJoin.' WHERE '.implode(' AND ',$where).' AND '.$expr." IS NOT NULL AND ".$expr."<>'' GROUP BY ".$expr;
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();$stmt->closeCursor();$out=[];
        foreach($rows as $row){$key=(string)($row['facet_value']??'');if($key!=='')$out[$key]=(int)($row['facet_count']??0);}return $out;
    }

    private function tagFacetCounts(array $filters,string $exclude,string $column): array
    {
        unset($filters[$exclude],$filters['page']);
        [$joins,$where,$params]=$this->catalogQueryParts($filters);
        $sql='SELECT DISTINCT p.id,p.'.$column.' AS facet_tags FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id LEFT JOIN fragrance_families ff ON ff.id=p.fragrance_family_id '.$joins.' WHERE '.implode(' AND ',$where);
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();$stmt->closeCursor();$out=[];
        foreach($rows as $row){foreach(preg_split('/\s*,\s*/',strtolower(trim((string)($row['facet_tags']??''))),-1,PREG_SPLIT_NO_EMPTY)?:[] as $tag){$out[$tag]=($out[$tag]??0)+1;}}return $out;
    }


    private function cachedArray(string $key,int $ttl,callable $loader): array
    {
        static $requestCache=[];
        $cacheKey=preg_replace('/[^a-zA-Z0-9._-]+/','-',trim($key))?:'cache';
        if(array_key_exists($cacheKey,$requestCache))return $requestCache[$cacheKey];
        $dir=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'cache';
        $file=$dir.DIRECTORY_SEPARATOR.'storefront-'.$cacheKey.'.json';
        if($ttl>0&&is_file($file)){
            $mtime=@filemtime($file);if($mtime!==false&&(time()-(int)$mtime)<=$ttl){$data=json_decode((string)@file_get_contents($file),true);if(is_array($data))return $requestCache[$cacheKey]=$data;}
        }
        $value=$loader();if(!is_array($value))$value=[];$requestCache[$cacheKey]=$value;
        if($ttl>0&&(is_dir($dir)||@mkdir($dir,0770,true)||is_dir($dir))&&is_writable($dir)){
            $tmp=$file.'.tmp.'.bin2hex(random_bytes(3));$json=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if($json!==false&&@file_put_contents($tmp,$json,LOCK_EX)!==false){@chmod($tmp,0660);@rename($tmp,$file);}else{@unlink($tmp);}
        }
        return $value;
    }

    public function searchSuggestions(string $query,int $limit=8): array
    {
        if(!$this->schemaReady())return [];
        $query=trim($query);$limit=max(1,min(12,$limit));
        if($query===''||mb_strlen($query)<3)return [];
        $escaped=$this->likeLiteral($query);$like='%'.$escaped.'%';$prefix=$escaped.'%';$items=[];

        $productLimit=min(6,$limit);
        $sql=$this->productSelect()." WHERE p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." AND (p.name LIKE ? ESCAPE '!' OR p.name_ar LIKE ? ESCAPE '!' OR b.name LIKE ? ESCAPE '!' OR b.name_ar LIKE ? ESCAPE '!' OR p.short_description LIKE ? ESCAPE '!' OR p.concentration LIKE ? ESCAPE '!' OR ff.name LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM store_product_notes pnq JOIN fragrance_notes fnq ON fnq.id=pnq.note_id WHERE pnq.product_id=p.id AND (fnq.name LIKE ? ESCAPE '!' OR fnq.name_ar LIKE ? ESCAPE '!')) OR EXISTS(SELECT 1 FROM store_product_variants svq WHERE svq.product_id=p.id AND svq.status='active' AND svq.sku LIKE ? ESCAPE '!') OR EXISTS(SELECT 1 FROM store_entity_translations trp WHERE trp.entity_type='product' AND trp.entity_id=p.id AND trp.locale='ar' AND trp.field_key IN ('name','description','short_description') AND trp.text_value LIKE ? ESCAPE '!') OR EXISTS(SELECT 1 FROM store_entity_translations trb WHERE trb.entity_type='brand' AND trb.entity_id=b.id AND trb.locale='ar' AND trb.field_key='name' AND trb.text_value LIKE ? ESCAPE '!')) ORDER BY CASE WHEN LOWER(p.name)=LOWER(?) THEN 0 WHEN p.name LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(b.name)=LOWER(?) THEN 2 WHEN b.name LIKE ? ESCAPE '!' THEN 3 ELSE 4 END,p.is_bestseller DESC,p.is_featured DESC,p.id DESC LIMIT {$productLimit}";
        $stmt=$this->db->prepare($sql);
        $stmt->execute(array_merge(array_fill(0,12,$like),[$query,$prefix,$query,$prefix]));
        foreach($stmt->fetchAll() as $row){$row['_search_type']='product';$items[]=$row;}
        $stmt->closeCursor();

        if(count($items)<$limit){
            $brandLimit=min(3,$limit-count($items));
            $stmt=$this->db->prepare("SELECT id,name,slug,logo_path FROM store_brands WHERE status='active' AND (name LIKE ? ESCAPE '!' OR name_ar LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM store_entity_translations trb WHERE trb.entity_type='brand' AND trb.entity_id=store_brands.id AND trb.locale='ar' AND trb.field_key='name' AND trb.text_value LIKE ? ESCAPE '!')) ORDER BY CASE WHEN LOWER(name)=LOWER(?) THEN 0 WHEN name LIKE ? ESCAPE '!' THEN 1 ELSE 2 END,is_featured DESC,sort_order,id LIMIT {$brandLimit}");
            $stmt->execute([$like,$like,$like,$query,$prefix]);
            foreach($stmt->fetchAll() as $row){$row['_search_type']='brand';$items[]=$row;}
            $stmt->closeCursor();
        }
        if(count($items)<$limit){
            $noteLimit=min(3,$limit-count($items));
            $stmt=$this->db->prepare("SELECT id,name,name_ar,slug,image_path FROM fragrance_notes WHERE status='active' AND (name LIKE ? ESCAPE '!' OR name_ar LIKE ? ESCAPE '!') ORDER BY CASE WHEN LOWER(name)=LOWER(?) THEN 0 WHEN name LIKE ? ESCAPE '!' THEN 1 ELSE 2 END,sort_order,id LIMIT {$noteLimit}");
            $stmt->execute([$like,$like,$query,$prefix]);
            foreach($stmt->fetchAll() as $row){$row['_search_type']='note';$items[]=$row;}
            $stmt->closeCursor();
        }
        return array_slice($items,0,$limit);
    }

    public function productBySlug(string $slug): ?array
    {
        if(!$this->schemaReady())return null;
        $stmt=$this->db->prepare($this->productSelect()." WHERE p.slug=? AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." LIMIT 1");
        $stmt->execute([$slug]);
        $row=$stmt->fetch();
        return $row?:null;
    }

    public function productVariants(int $productId): array
    {
        if(!$this->schemaReady()||$productId<=0)return [];
        $stmt=$this->db->prepare("SELECT id,label,size_ml,sku,barcode,price,sale_price,currency,stock_qty,low_stock_threshold,sort_order,status,stock_qty AS available_qty FROM store_product_variants WHERE product_id=? AND status='active' ORDER BY sort_order,id");
        $stmt->execute([$productId]);return $stmt->fetchAll();
    }

    public function productVariant(int $productId,int $variantId): ?array
    {
        if(!$this->schemaReady()||$productId<=0||$variantId<=0)return null;
        $stmt=$this->db->prepare("SELECT id,product_id,label,size_ml,sku,price,sale_price,currency,stock_qty,status,stock_qty AS available_qty FROM store_product_variants WHERE product_id=? AND id=? AND status='active' LIMIT 1");
        $stmt->execute([$productId,$variantId]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function productNotes(int $productId): array
    {
        if(!$this->schemaReady()||$productId<=0)return [];
        $stmt=$this->db->prepare("SELECT fn.id,fn.name,fn.name_ar,fn.slug,fn.image_path,pn.note_layer,pn.sort_order FROM store_product_notes pn JOIN fragrance_notes fn ON fn.id=pn.note_id WHERE pn.product_id=? AND fn.status='active' ORDER BY FIELD(pn.note_layer,'top','heart','base','other'),pn.sort_order,fn.name");
        $stmt->execute([$productId]);return $stmt->fetchAll();
    }

    public function relatedProducts(array $product,int $limit=4): array
    {
        if(!$this->schemaReady()||empty($product['id']))return [];
        $limit=max(1,min(8,$limit));$productId=(int)$product['id'];$brand=(int)($product['brand_id']??0);$family=(int)($product['fragrance_family_id']??0);
        $stmt=$this->db->prepare($this->productSelect()." WHERE p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." AND p.id<>? AND (p.brand_id=? OR (p.fragrance_family_id IS NOT NULL AND p.fragrance_family_id=?)) ORDER BY (p.brand_id=? ) DESC,p.is_bestseller DESC,p.is_featured DESC,p.id DESC LIMIT {$limit}");
        $stmt->execute([$productId,$brand,$family,$brand]);$rows=$stmt->fetchAll();
        if(count($rows)>=$limit)return array_slice($rows,0,$limit);
        $exclude=[$productId];foreach($rows as $row)$exclude[]=(int)$row['id'];$exclude=array_values(array_unique(array_filter($exclude,static fn($v)=>$v>0)));
        $remaining=$limit-count($rows);$notIn=implode(',',array_map('intval',$exclude));$gender=(string)($product['gender']??'');
        $fallback=$this->db->prepare($this->productSelect()." WHERE p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." AND p.id NOT IN ({$notIn}) ORDER BY (p.gender=? ) DESC,p.is_bestseller DESC,p.is_featured DESC,p.is_new_arrival DESC,p.id DESC LIMIT {$remaining}");
        $fallback->execute([$gender]);foreach($fallback->fetchAll() as $row)$rows[]=$row;
        return array_slice($rows,0,$limit);
    }

    public function sitemapProducts(int $limit=5000): array
    {
        if(!$this->schemaReady())return [];
        $limit=max(1,min(50000,$limit));
        return $this->db->query("SELECT p.slug,p.updated_at FROM store_products p WHERE p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." ORDER BY p.updated_at DESC,p.id DESC LIMIT {$limit}")->fetchAll();
    }

    private function likeLiteral(string $value): string
    {
        // Treat customer-entered %, _ and ! as normal characters, not SQL LIKE wildcards.
        return str_replace(['!','%','_'], ['!!','!%','!_'], $value);
    }

    private function productSelect(): string
    {
        return "SELECT p.*,(SELECT d.code FROM commerce_product_departments pd JOIN commerce_departments d ON d.id=pd.department_id WHERE pd.product_id=p.id LIMIT 1) AS department_code,b.name AS brand_name,b.name_ar AS brand_name_ar,b.slug AS brand_slug,ff.name AS family_name,ff.slug AS family_slug,".VariantOffer::sql('p','price')." AS price,".VariantOffer::sql('p','regular_price')." AS regular_price,".VariantOffer::sql('p','currency')." AS currency,(SELECT SUM(GREATEST(v3.stock_qty,0)) FROM store_product_variants v3 WHERE v3.product_id=p.id AND v3.status='active') AS available_stock,(SELECT COUNT(*) FROM store_product_variants v4 WHERE v4.product_id=p.id AND v4.status='active') AS active_variant_count,".VariantOffer::sql('p','variant_id',true)." AS first_available_variant_id,".VariantOffer::sql('p','variant_label',true)." AS first_available_variant_label FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id LEFT JOIN fragrance_families ff ON ff.id=p.fragrance_family_id";
    }
}
