<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\ServiceFactory;

final class DashboardController
{
    public function handle(): array
    {
        $user=\current_user();if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $uid=(int)$user['id'];$db=\db();$message='';$error='';
        $claimed=(int)($_SESSION['_flash_guest_orders_claimed']??0);unset($_SESSION['_flash_guest_orders_claimed']);
        if($claimed>0)$message=$claimed===1?'Your guest order was added to this account.':$claimed.' guest orders were added to this account.';
        $metrics=['wishlist'=>0,'addresses'=>0];$recentOrders=[];$defaultAddress=null;$supportUnread=0;$announcements=[];
        try{
            $s=$db->prepare('SELECT COUNT(*) FROM store_wishlist_items wi JOIN store_wishlists w ON w.id=wi.wishlist_id WHERE w.user_id=?');$s->execute([$uid]);$metrics['wishlist']=(int)$s->fetchColumn();
            $s=$db->prepare('SELECT COUNT(*) FROM customer_addresses WHERE user_id=?');$s->execute([$uid]);$metrics['addresses']=(int)$s->fetchColumn();
            $s=$db->prepare('SELECT order_number,status,payment_status,fulfillment_status,grand_total,currency,placed_at FROM store_orders WHERE user_id=? ORDER BY placed_at DESC,id DESC LIMIT 1');$s->execute([$uid]);$recentOrders=$s->fetchAll();
        }catch(\Throwable){}
        try {
            $addresses=(new \App\Services\CustomerAddressService($db))->all($uid);
            $defaultAddress=$addresses[0]??null;
        } catch(\Throwable){}
        try{$supportUnread=ServiceFactory::supportReads($db)->unreadCountForUser($uid);}catch(\Throwable){}
        try{$isAdmin=!empty($user['is_admin']);$announcements=array_slice(ServiceFactory::announcements($db)->activeForUser($isAdmin,!$isAdmin),0,2);}catch(\Throwable){}
        return compact('user','message','error','metrics','recentOrders','defaultAddress','supportUnread','announcements');
    }
}
