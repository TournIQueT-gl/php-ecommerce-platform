<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\PublicException;
use PDO;

/** Alpha 5: one primary department per category/product, no duplication of commerce data. */
final class DepartmentService
{
    public function __construct(private PDO $db) {}

    public function ready(): bool
    {
        try {
            $q=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('commerce_departments','commerce_department_categories','commerce_product_departments')");
            return (int)$q->fetchColumn()===3;
        } catch (\Throwable) { return false; }
    }
    public function all(): array
    {
        return $this->db->query("SELECT d.*, (SELECT COUNT(*) FROM commerce_department_categories c WHERE c.department_id=d.id) AS category_count, (SELECT COUNT(*) FROM commerce_product_departments p WHERE p.department_id=d.id) AS product_count FROM commerce_departments d ORDER BY d.sort_order,d.id")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function categoryMap(): array
    {
        $rows=$this->db->query('SELECT category_id,department_id FROM commerce_department_categories')->fetchAll(PDO::FETCH_ASSOC);
        $out=[];foreach($rows as $r)$out[(int)$r['category_id']]=(int)$r['department_id'];return $out;
    }
    public function productDepartment(int $productId): int
    {
        $q=$this->db->prepare('SELECT department_id FROM commerce_product_departments WHERE product_id=?');$q->execute([$productId]);return (int)($q->fetchColumn()?:0);
    }
    public function save(array $input): int
    {
        $id=max(0,(int)($input['department_id']??0));
        $name=trim((string)($input['department_name']??''));
        $description=trim((string)($input['description']??''));
        if($name===''||mb_strlen($name)>120)throw new PublicException('Enter a department name (maximum 120 characters).');
        if(mb_strlen($description)>500)throw new PublicException('Department description cannot exceed 500 characters.');
        $status=(string)($input['status']??'active');
        if(!in_array($status,['active','hidden'],true))throw new PublicException('Invalid department status.');
        $sort=max(0,min(9999,(int)($input['sort_order']??0)));
        if($id){
            $q=$this->db->prepare('UPDATE commerce_departments SET name=?,description=?,status=?,sort_order=? WHERE id=?');
            $q->execute([$name,$description?:null,$status,$sort,$id]);
            if($q->rowCount()===0){$q=$this->db->prepare('SELECT id FROM commerce_departments WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new PublicException('Department not found.');}
            return $id;
        }
        $slug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-', $name),'-'));
        if($slug==='')throw new PublicException('Use an English department name to create its identifier.');
        $slug=substr($slug,0,75);
        $q=$this->db->prepare('SELECT id FROM commerce_departments WHERE code=?');$q->execute([$slug]);
        if($q->fetchColumn())throw new PublicException('A department with this name already exists.');
        $q=$this->db->prepare('INSERT INTO commerce_departments(code,name,description,status,sort_order) VALUES(?,?,?,?,?)');
        $q->execute([$slug,$name,$description?:null,$status,$sort]);return (int)$this->db->lastInsertId();
    }
    public function requireActive(int $id): void
    {
        if($id<1)throw new PublicException('Choose a department first.');
        $q=$this->db->prepare("SELECT id FROM commerce_departments WHERE id=? AND status='active'");$q->execute([$id]);
        if(!$q->fetchColumn())throw new PublicException('Choose an enabled department.');
    }
    /** Run atomically even when used from legacy taxonomy/admin flows. */
    public function assignCategory(int $categoryId,int $departmentId): void
    {
        $ownsTransaction=!$this->db->inTransaction();
        if($ownsTransaction)$this->db->beginTransaction();
        try {
            $this->requireActive($departmentId);
            $q=$this->db->prepare('SELECT parent_id FROM store_categories WHERE id=? FOR UPDATE');$q->execute([$categoryId]);$cat=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
            if(!$cat)throw new PublicException('Category not found.');
            if(!empty($cat['parent_id'])){
                $parent=$this->categoryDepartment((int)$cat['parent_id']);
                if($parent!==0 && $parent!==$departmentId)throw new PublicException('A subcategory must use its parent department.');
            }
            $conflict=$this->db->prepare('SELECT p.product_id FROM store_product_categories pc JOIN commerce_product_departments p ON p.product_id=pc.product_id WHERE pc.category_id=? AND p.department_id<>? LIMIT 1');
            $conflict->execute([$categoryId,$departmentId]);
            if($conflict->fetchColumn())throw new PublicException('A product in this category is assigned to another department. Reconcile its categories before moving this category.');
            $check=$this->db->prepare('SELECT c.id FROM store_categories c JOIN commerce_department_categories d ON d.category_id=c.id WHERE c.parent_id=? AND d.department_id<>? LIMIT 1');
            $check->execute([$categoryId,$departmentId]);
            if($check->fetchColumn())throw new PublicException('Move child categories first; they belong to another department.');
            $q=$this->db->prepare('INSERT INTO commerce_department_categories(category_id,department_id) VALUES(?,?) ON DUPLICATE KEY UPDATE department_id=VALUES(department_id)');
            $q->execute([$categoryId,$departmentId]);
            if($ownsTransaction)$this->db->commit();
        }catch(\Throwable $e){if($ownsTransaction && $this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function categoryDepartment(int $categoryId): int
    {
        $q=$this->db->prepare('SELECT department_id FROM commerce_department_categories WHERE category_id=?');$q->execute([$categoryId]);return (int)($q->fetchColumn()?:0);
    }
    /** Updates an edited product without leaving categories from another department.
     * On a department change, the explicitly selected category becomes the primary
     * and older categories are removed; no product, variant or order row is deleted.
     */
    public function syncProduct(int $productId,int $categoryId,int $claimedDepartment=0): int
    {
        $department=$this->categoryDepartment($categoryId);
        if($department<1)throw new PublicException('Assign this category to a department before saving products.');
        $this->requireActive($department);
        if($claimedDepartment>0 && $claimedDepartment!==$department)throw new PublicException('Category and department do not match.');
        $membership=$this->db->prepare('SELECT 1 FROM store_product_categories WHERE product_id=? AND category_id=?');
        $membership->execute([$productId,$categoryId]);
        if(!$membership->fetchColumn())throw new PublicException('The selected category must be linked to the product before changing its department.');
        $oldDepartment=$this->productDepartment($productId);
        if($oldDepartment>0 && $oldDepartment!==$department){
            // Explicit migration to another department replaces categories, never history.
            $del=$this->db->prepare('DELETE FROM store_product_categories WHERE product_id=? AND category_id<>?');
            $del->execute([$productId,$categoryId]);
        }else{
            // Existing primary department: prune only previously mapped cross-department categories.
            $del=$this->db->prepare('DELETE pc FROM store_product_categories pc JOIN commerce_department_categories dc ON dc.category_id=pc.category_id WHERE pc.product_id=? AND dc.department_id<>?');
            $del->execute([$productId,$department]);
        }
        $q=$this->db->prepare('INSERT INTO commerce_product_departments(product_id,department_id) VALUES(?,?) ON DUPLICATE KEY UPDATE department_id=VALUES(department_id)');
        $q->execute([$productId,$department]);return $department;
    }
    /** Guard legacy perfume editor against creating cross-department product memberships.
     * Called from inside CatalogController's product transaction after category sync.
     */
    public function reconcileLegacyEditor(int $productId,array $categoryIds): void
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$categoryIds),static fn(int $id):bool=>$id>0)));
        $departments=[];
        if($ids){
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $stmt=$this->db->prepare("SELECT DISTINCT department_id FROM commerce_department_categories WHERE category_id IN ({$marks})");
            $stmt->execute($ids);
            $departments=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if(count($departments)>1)throw new PublicException('Selected categories belong to different departments. Use categories from one department only.');
        $existing=$this->productDepartment($productId);
        $matched=(int)($departments[0]??0);
        if($existing>0 && $matched!==$existing)
            throw new PublicException('This product is classified in another department. Keep a category from its current department or move it using the Universal Editor.');
        if($existing===0 && $matched>0){
            $this->requireActive($matched);
            $q=$this->db->prepare('INSERT INTO commerce_product_departments(product_id,department_id) VALUES(?,?)');
            $q->execute([$productId,$matched]);
        }
    }

    /** Only products whose mapped categories agree may be safely auto-classified. */
    private const SAFE_CANDIDATES = " FROM store_product_categories pc JOIN commerce_department_categories dc ON dc.category_id=pc.category_id WHERE dc.department_id=? AND NOT EXISTS(SELECT 1 FROM commerce_product_departments pd WHERE pd.product_id=pc.product_id) AND NOT EXISTS(SELECT 1 FROM store_product_categories extra JOIN commerce_department_categories other_dep ON other_dep.category_id=extra.category_id WHERE extra.product_id=pc.product_id AND other_dep.department_id<>?)";
    public function eligibleProductCount(int $departmentId): int
    {
        $q=$this->db->prepare('SELECT COUNT(DISTINCT pc.product_id)'.self::SAFE_CANDIDATES);
        $q->execute([$departmentId,$departmentId]);return (int)$q->fetchColumn();
    }
    public function classifyUnassignedProducts(int $departmentId): int
    {
        $this->requireActive($departmentId);
        $q=$this->db->prepare('INSERT IGNORE INTO commerce_product_departments(product_id,department_id) SELECT DISTINCT pc.product_id,?'.self::SAFE_CANDIDATES);
        $q->execute([$departmentId,$departmentId,$departmentId]);return $q->rowCount();
    }

    public function assignLegacyProduct(int $productId,int $departmentId): void
    {
        $this->requireActive($departmentId);
        $q=$this->db->prepare('SELECT id FROM store_products WHERE id=?');$q->execute([$productId]);if(!$q->fetchColumn())throw new PublicException('Product not found.');
        $valid=$this->db->prepare('SELECT 1 FROM store_product_categories pc JOIN commerce_department_categories dc ON dc.category_id=pc.category_id WHERE pc.product_id=? AND dc.department_id=? LIMIT 1');
        $valid->execute([$productId,$departmentId]);
        if(!$valid->fetchColumn())throw new PublicException('Assign at least one product category to this department first.');
        $conflict=$this->db->prepare('SELECT dc.department_id FROM store_product_categories pc JOIN commerce_department_categories dc ON dc.category_id=pc.category_id WHERE pc.product_id=? AND dc.department_id<>? LIMIT 1');
        $conflict->execute([$productId,$departmentId]);
        if($conflict->fetchColumn())throw new PublicException('Product has a category assigned to another department. Resolve its category assignments first.');
        $q=$this->db->prepare('INSERT INTO commerce_product_departments(product_id,department_id) VALUES(?,?) ON DUPLICATE KEY UPDATE department_id=VALUES(department_id)');$q->execute([$productId,$departmentId]);
    }
}
