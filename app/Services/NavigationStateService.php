<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\FeatureFlagRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\SupportReadRepository;

final class NavigationStateService
{
    public function __construct(
        private FeatureFlagRepository $flags,
        private NotificationRepository $notifications,
        private SupportReadRepository $supportReads
    ) {}

    public function forUser(int $userId, bool $admin = false, ?array $notificationTypes = null, ?array $excludedNotificationTypes = null): array
    {
        if($userId<=0)return $this->emptyState();

        // Resolve navigation feature flags in one query instead of hitting the
        // feature-flags table once per badge on every account/admin render.
        $flagMap=[];
        foreach($this->flags->forUser($userId) as $flagRow){
            $key=(string)($flagRow['flag_key']??'');if($key!=='')$flagMap[$key]=(bool)($flagRow['enabled']??false);
        }
        $notificationEnabled=!empty($flagMap['notification_center']);
        $supportEnabled=!empty($flagMap['support_unread']);
        $notificationUnread=$notificationEnabled?$this->notifications->unreadCount($userId,$notificationTypes,$excludedNotificationTypes):0;
        $supportTicketIds=$supportEnabled
            ?($admin?$this->supportReads->unreadTicketIdsForAdmin($userId):$this->supportReads->unreadTicketIdsForUser($userId))
            :[];
        $supportUnread=count($supportTicketIds);

        // Support activity exists in two overlapping stores: ticket read-state and
        // asynchronous notification rows. Add only tickets not already represented
        // by an unread support notification so the bell neither misses pending work
        // nor double-counts it after the event processor catches up.
        $representedSupportIds=$notificationEnabled?$this->notifications->unreadSupportTicketIds($userId,$admin):[];
        $pendingSupportIds=array_diff($supportTicketIds,$representedSupportIds);
        $globalUnread=$notificationUnread+count($pendingSupportIds);
        $href=$notificationUnread>0
            ?\route_url($admin?'admin/notifications.php':'notifications.php')
            :($supportUnread>0?\route_url($admin?'admin/support.php':'support.php'):\route_url($admin?'admin/notifications.php':'notifications.php'));

        return [
            'notifications_enabled'=>$notificationEnabled,
            'notification_unread'=>$notificationUnread,
            'support_unread_enabled'=>$supportEnabled,
            'support_unread'=>$supportUnread,
            'global_unread'=>$globalUnread,
            'has_unread'=>$globalUnread>0,
            'notification_href'=>$href,
        ];
    }

    private function emptyState(): array
    {
        return [
            'notifications_enabled'=>false,'notification_unread'=>0,
            'support_unread_enabled'=>false,'support_unread'=>0,
            'global_unread'=>0,'has_unread'=>false,'notification_href'=>'',
        ];
    }
}
