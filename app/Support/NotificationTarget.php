<?php
declare(strict_types=1);

namespace App\Support;

final class NotificationTarget
{
    public static function resolve(array $notification): array
    {
        $type=(string)($notification['type']??'');
        $data=$notification['data']??json_decode((string)($notification['data_json']??'{}'),true);
        if(!is_array($data))$data=[];

        if($type==='support.admin_replied' && (int)($data['ticket_id']??0)>0){
            return ['url'=>\route_url('support-ticket.php').'?id='.(int)$data['ticket_id'],'label'=>'Open support ticket'];
        }
        if(in_array($type,['support.user_replied','support.ticket_created'],true) && (int)($data['ticket_id']??0)>0){
            return ['url'=>\route_url('admin/support-ticket.php').'?id='.(int)$data['ticket_id'],'label'=>'Open support ticket'];
        }
        if(str_starts_with($type,'order.')){
            $orderId=(int)($data['order_id']??0);
            if(!empty($data['admin_target'])){
                $query=$orderId>0?'?q='.rawurlencode((string)($data['order_number']??$orderId)):'';
                return ['url'=>\route_url('admin/orders.php').$query,'label'=>'Open order'];
            }
            return ['url'=>\route_url('account-orders.php').($orderId>0?'?id='.$orderId:''),'label'=>'Open orders'];
        }
        if(str_starts_with($type,'announcement.')){
            return ['url'=>\route_url('dashboard.php').'#announcements','label'=>'Open announcements'];
        }
        if(str_starts_with($type,'maintenance.')){
            return ['url'=>\route_url('dashboard.php'),'label'=>'Open account'];
        }
        if($type==='security.password_recovery_requested' && (int)($data['user_id']??0)>0){
            return ['url'=>\route_url('admin/customers.php').'?q='.rawurlencode((string)($data['username']??'')),'label'=>'Open customer directory'];
        }
        if($type==='admin.message'){
            return match((string)($data['destination']??'dashboard')){
                'support'=>['url'=>\route_url('support.php'),'label'=>'Open support'],
                'security'=>['url'=>\route_url('account-security.php'),'label'=>'Open account security'],
                'orders'=>['url'=>\route_url('account-orders.php'),'label'=>'Open orders'],
                'activity'=>['url'=>\route_url('account-activity.php'),'label'=>'Open account activity'],
                default=>['url'=>\route_url('dashboard.php'),'label'=>'Open dashboard'],
            };
        }
        if(str_starts_with($type,'security.')||str_starts_with($type,'account.')){
            return ['url'=>\route_url('account-security.php'),'label'=>'Open account security'];
        }
        return ['url'=>\route_url('dashboard.php'),'label'=>'Open dashboard'];
    }
}
