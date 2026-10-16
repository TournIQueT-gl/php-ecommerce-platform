<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\PublicException;
use PDO;

final class InventoryService
{
    public function __construct(private PDO $db) {}

    public function adjust(int $variantId,int $delta,string $movementType,string $note='',?int $createdBy=null,?string $referenceType=null,?string $referenceId=null): int
    {
        if($variantId<=0||$delta===0)throw new PublicException('Choose a valid variant and a non-zero stock adjustment.');
        $owns=!$this->db->inTransaction();
        if($owns)$this->db->beginTransaction();
        try{
            $s=$this->db->prepare('SELECT stock_qty FROM store_product_variants WHERE id=? FOR UPDATE');
            $s->execute([$variantId]);$current=$s->fetchColumn();
            if($current===false)throw new PublicException('Variant not found.');
            $next=(int)$current+$delta;
            if($next<0)throw new PublicException('Stock cannot become negative.');
            $u=$this->db->prepare('UPDATE store_product_variants SET stock_qty=? WHERE id=?');$u->execute([$next,$variantId]);
            $m=$this->db->prepare('INSERT INTO store_inventory_movements(variant_id,quantity_delta,movement_type,reference_type,reference_id,note,created_by) VALUES(?,?,?,?,?,?,?)');
            $m->execute([$variantId,$delta,substr($movementType,0,32),$referenceType!==null?substr($referenceType,0,40):null,$referenceId!==null?substr($referenceId,0,80):null,$note!==''?mb_substr($note,0,500):null,$createdBy&&$createdBy>0?$createdBy:null]);
            if($owns)$this->db->commit();
            \storefront_cache_clear();
            return $next;
        }catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function setStock(int $variantId,int $desired,string $movementType,string $note='',?int $createdBy=null,?string $referenceType=null,?string $referenceId=null): int
    {
        $desired=max(0,$desired);
        $owns=!$this->db->inTransaction();
        if($owns)$this->db->beginTransaction();
        try{
            $s=$this->db->prepare('SELECT stock_qty FROM store_product_variants WHERE id=? FOR UPDATE');$s->execute([$variantId]);$current=$s->fetchColumn();
            if($current===false)throw new PublicException('Variant not found.');
            $delta=$desired-(int)$current;
            if($delta!==0){
                $u=$this->db->prepare('UPDATE store_product_variants SET stock_qty=? WHERE id=?');$u->execute([$desired,$variantId]);
                $m=$this->db->prepare('INSERT INTO store_inventory_movements(variant_id,quantity_delta,movement_type,reference_type,reference_id,note,created_by) VALUES(?,?,?,?,?,?,?)');
                $m->execute([$variantId,$delta,substr($movementType,0,32),$referenceType!==null?substr($referenceType,0,40):null,$referenceId!==null?substr($referenceId,0,80):null,$note!==''?mb_substr($note,0,500):null,$createdBy&&$createdBy>0?$createdBy:null]);
            }
            if($owns)$this->db->commit();
            if($delta!==0)\storefront_cache_clear();
            return $desired;
        }catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
