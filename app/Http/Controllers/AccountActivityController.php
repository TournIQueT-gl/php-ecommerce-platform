<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Support\ServiceFactory;
final class AccountActivityController
{
    public function handle(): array
    {
        $user=\current_user();if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $uid=(int)$user['id'];$db=\db();
        $activity=[];$orders=[];
        try{$activity=ServiceFactory::accountActivity($db)->forUser($uid,80);}catch(\Throwable){}
        try{$stmt=$db->prepare("SELECT order_number,status,payment_status,grand_total,currency,placed_at FROM store_orders WHERE user_id=? ORDER BY placed_at DESC,id DESC LIMIT 30");$stmt->execute([$uid]);$orders=$stmt->fetchAll();}catch(\Throwable){}
        return compact('user','activity','orders');
    }
}
