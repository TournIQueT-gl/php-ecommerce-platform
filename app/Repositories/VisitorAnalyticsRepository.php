<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VisitorAnalyticsRepository
{
    private ?bool $schemaReadyCache=null;
    private ?bool $intelligenceReadyCache=null;
    private ?bool $intelligenceLifecycleReadyCache=null;
    private ?bool $intelligenceLocationReadyCache=null;

    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        if($this->schemaReadyCache!==null)return $this->schemaReadyCache;
        try{
            $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('visitor_sessions','visitor_pageviews','visitor_events')");
            return $this->schemaReadyCache=((int)$stmt->fetchColumn()===3);
        }catch(\Throwable){return $this->schemaReadyCache=false;}
    }

    public function intelligenceReady(): bool
    {
        if($this->intelligenceReadyCache!==null)return $this->intelligenceReadyCache;
        try{
            $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visitor_ip_intelligence'");
            return $this->intelligenceReadyCache=((int)$stmt->fetchColumn()===1);
        }catch(\Throwable){return $this->intelligenceReadyCache=false;}
    }

    public function intelligenceLifecycleReady(): bool
    {
        if($this->intelligenceLifecycleReadyCache!==null)return $this->intelligenceLifecycleReadyCache;
        if(!$this->intelligenceReady())return $this->intelligenceLifecycleReadyCache=false;
        try{
            $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visitor_ip_intelligence' AND COLUMN_NAME IN ('resolution_status','last_error','attempt_count','next_retry_at','queued_at')");
            return $this->intelligenceLifecycleReadyCache=((int)$stmt->fetchColumn()===5);
        }catch(\Throwable){return $this->intelligenceLifecycleReadyCache=false;}
    }

    public function intelligenceLocationReady(): bool
    {
        if($this->intelligenceLocationReadyCache!==null)return $this->intelligenceLocationReadyCache;
        if(!$this->intelligenceReady())return $this->intelligenceLocationReadyCache=false;
        try{
            $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visitor_ip_intelligence' AND COLUMN_NAME IN ('region_name','city_name')");
            return $this->intelligenceLocationReadyCache=((int)$stmt->fetchColumn()===2);
        }catch(\Throwable){return $this->intelligenceLocationReadyCache=false;}
    }

    public function trackPageview(array $data): void
    {
        if(!$this->schemaReady())return;
        // Never perform GeoIP cache reads or remote lookups on the public pageview path.
        // Background/admin resolvers discover distinct unresolved IPs directly from visitor_sessions.
        $sql="INSERT INTO visitor_sessions(visitor_key,session_key,user_id,ip_address,user_agent,browser,os_name,device_type,country_code,first_referrer_host,last_referrer_host,landing_path,last_path,utm_source,utm_medium,utm_campaign,pageview_count,is_bot,first_seen_at,last_seen_at)
              VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())
              ON DUPLICATE KEY UPDATE
                id=LAST_INSERT_ID(id),
                user_id=COALESCE(VALUES(user_id),user_id),
                ip_address=VALUES(ip_address),
                user_agent=VALUES(user_agent),
                browser=VALUES(browser),
                os_name=VALUES(os_name),
                device_type=VALUES(device_type),
                country_code=COALESCE(VALUES(country_code),country_code),
                last_referrer_host=COALESCE(VALUES(last_referrer_host),last_referrer_host),
                last_path=VALUES(last_path),
                pageview_count=pageview_count+1,
                last_seen_at=UTC_TIMESTAMP()";
        $stmt=$this->db->prepare($sql);
        $stmt->execute([
            $data['visitor_key'],$data['session_key'],$data['user_id'],$data['ip_address'],$data['user_agent'],
            $data['browser'],$data['os_name'],$data['device_type'],$data['country_code'],$data['referrer_host'],$data['referrer_host'],
            $data['path'],$data['path'],$data['utm_source'],$data['utm_medium'],$data['utm_campaign'],!empty($data['is_bot'])?1:0,
        ]);
        $sessionId=(int)$this->db->lastInsertId();
        if($sessionId<=0){
            $lookup=$this->db->prepare('SELECT id FROM visitor_sessions WHERE session_key=? LIMIT 1');
            $lookup->execute([$data['session_key']]);$sessionId=(int)$lookup->fetchColumn();
        }
        if($sessionId<=0)return;
        $pv=$this->db->prepare('INSERT INTO visitor_pageviews(session_id,visitor_key,user_id,path,referrer_host,occurred_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP())');
        $pv->execute([$sessionId,$data['visitor_key'],$data['user_id'],$data['path'],$data['referrer_host']]);
    }

    public function trackEvent(string $eventKey,?int $userId,?string $visitorKey,?string $sessionKey,string $path,array $metadata=[]): void
    {
        if(!$this->schemaReady())return;
        $sessionId=null;
        if($sessionKey){
            $stmt=$this->db->prepare('SELECT id FROM visitor_sessions WHERE session_key=? LIMIT 1');$stmt->execute([$sessionKey]);
            $value=$stmt->fetchColumn();if($value!==false)$sessionId=(int)$value;
        }
        $json=$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;if($json===false)$json=null;
        $stmt=$this->db->prepare('INSERT INTO visitor_events(session_id,visitor_key,user_id,event_key,path,metadata_json,occurred_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())');
        $stmt->execute([$sessionId,$visitorKey,$userId,$eventKey,$path!==''?$path:null,$json]);
    }

    public function summary(int $days=30): array
    {
        $days=$this->days($days);$interval='INTERVAL '.$days.' DAY';
        $sql="SELECT COUNT(*) unique_visitors,
                     COALESCE(SUM(x.sessions),0) sessions,
                     COALESCE(SUM(x.pageviews),0) pageviews,
                     COALESCE(SUM(CASE WHEN x.has_user=0 THEN 1 ELSE 0 END),0) guests,
                     COALESCE(SUM(CASE WHEN x.sessions>1 THEN 1 ELSE 0 END),0) returning_visitors,
                     COALESCE(SUM(CASE WHEN x.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) THEN 1 ELSE 0 END),0) online
              FROM (
                SELECT visitor_key,COUNT(*) sessions,SUM(pageview_count) pageviews,MAX(last_seen_at) last_seen_at,MAX(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) has_user
                FROM visitor_sessions
                WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})
                GROUP BY visitor_key
              ) x";
        $row=$this->db->query($sql)->fetch()?:[];
        $loggedIn=(int)$this->db->query("SELECT COUNT(DISTINCT user_id) FROM visitor_sessions WHERE is_bot=0 AND user_id IS NOT NULL AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})")->fetchColumn();
        $bots=(int)$this->db->query("SELECT COUNT(*) FROM visitor_sessions WHERE is_bot=1 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})")->fetchColumn();
        return [
            'days'=>$days,'unique'=>(int)($row['unique_visitors']??0),'sessions'=>(int)($row['sessions']??0),'pageviews'=>(int)($row['pageviews']??0),
            'loggedIn'=>$loggedIn,'guests'=>(int)($row['guests']??0),'bots'=>$bots,'online'=>(int)($row['online']??0),'returning'=>(int)($row['returning_visitors']??0),
        ];
    }

    public function recentVisitorsPage(int $page=1,int $perPage=50,string $search='',int $days=30,bool $allowIdentitySearch=true,string $account='all',string $resolution='all'): array
    {
        $days=$this->days($days);$page=max(1,$page);$perPage=max(10,min(100,$perPage));$offset=($page-1)*$perPage;
        $search=trim($search);$account=in_array($account,['all','guest','signed'],true)?$account:'all';$resolution=in_array($resolution,['all','resolved','unresolved','pending','failed','private'],true)?$resolution:'all';
        $intel=$this->intelligenceReady();$life=$this->intelligenceLifecycleReady();$location=$this->intelligenceLocationReady();
        $intelSelect=$intel?',intel.country_code AS resolved_country_code,intel.country_name,intel.asn,intel.organization,intel.timezone_name,intel.source AS intelligence_source,intel.resolved_at AS intelligence_resolved_at':'';
        if($intel&&$location)$intelSelect.=',intel.region_name,intel.city_name';
        if($life)$intelSelect.=',intel.resolution_status,intel.last_error,intel.attempt_count,intel.next_retry_at';
        $intelJoin=$intel?' LEFT JOIN visitor_ip_intelligence intel ON intel.ip_address=latest.ip_address':'';
        $latestUser="(SELECT su.user_id FROM visitor_sessions su WHERE su.visitor_key=agg.visitor_key AND su.is_bot=0 AND su.user_id IS NOT NULL ORDER BY su.last_seen_at DESC,su.id DESC LIMIT 1)";
        $base="FROM (
                SELECT visitor_key,MIN(first_seen_at) first_seen_at,MAX(last_seen_at) last_seen_at,COUNT(*) sessions,SUM(pageview_count) pageviews
                FROM visitor_sessions
                WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)
                GROUP BY visitor_key
              ) agg
              INNER JOIN visitor_sessions latest ON latest.id=(
                SELECT sl.id FROM visitor_sessions sl
                WHERE sl.visitor_key=agg.visitor_key AND sl.is_bot=0 AND sl.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)
                ORDER BY sl.last_seen_at DESC,sl.id DESC LIMIT 1
              )
              LEFT JOIN users u ON u.id={$latestUser}{$intelJoin}";
        $where=[];$args=[];
        if($search!==''){
            $like='%'.$search.'%';
            if($allowIdentitySearch){
                $parts=['u.username LIKE ?','latest.ip_address LIKE ?','agg.visitor_key LIKE ?','latest.last_path LIKE ?','latest.first_referrer_host LIKE ?','latest.utm_source LIKE ?','latest.utm_medium LIKE ?','latest.utm_campaign LIKE ?','latest.browser LIKE ?','latest.os_name LIKE ?','latest.device_type LIKE ?'];
                $args=array_fill(0,count($parts),$like);
                if($intel){array_push($parts,'intel.country_code LIKE ?','intel.country_name LIKE ?','intel.organization LIKE ?','intel.asn LIKE ?');array_push($args,$like,$like,$like,$like);if($location){array_push($parts,'intel.region_name LIKE ?','intel.city_name LIKE ?');array_push($args,$like,$like);}}
                $where[]='('.implode(' OR ',$parts).')';
            }else{
                $where[]='(latest.last_path LIKE ? OR latest.first_referrer_host LIKE ?)';$args=[$like,$like];
            }
        }
        if($account==='guest')$where[]='u.id IS NULL';elseif($account==='signed')$where[]='u.id IS NOT NULL';
        if($resolution==='resolved')$where[]=$intel?"COALESCE(NULLIF(intel.country_code,''),NULLIF(latest.country_code,'')) IS NOT NULL":"NULLIF(latest.country_code,'') IS NOT NULL";
        elseif($resolution==='unresolved')$where[]=$intel?"COALESCE(NULLIF(intel.country_code,''),NULLIF(latest.country_code,'')) IS NULL":"NULLIF(latest.country_code,'') IS NULL";
        elseif($resolution==='pending'&&$life)$where[]="intel.resolution_status='pending'";
        elseif($resolution==='failed'&&$life)$where[]="intel.resolution_status='failed'";
        elseif($resolution==='private'&&$life)$where[]="intel.resolution_status='private'";
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$this->db->prepare("SELECT COUNT(*) {$base}{$whereSql}");$count->execute($args);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));if($page>$pages){$page=$pages;$offset=($page-1)*$perPage;}
        $sql="SELECT agg.*,latest.ip_address,latest.browser,latest.os_name,latest.device_type,latest.country_code,latest.first_referrer_host,latest.last_referrer_host,latest.landing_path,latest.last_path,latest.utm_source,latest.utm_medium,latest.utm_campaign,u.id AS user_id,u.username{$intelSelect}
              {$base}{$whereSql}
              ORDER BY agg.last_seen_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt=$this->db->prepare($sql);$stmt->execute($args);$rows=$stmt->fetchAll();
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    public function recentVisitors(int $limit=100,string $search='',int $days=30,bool $allowIdentitySearch=true): array
    {
        return $this->recentVisitorsPage(1,max(10,min(100,$limit)),$search,$days,$allowIdentitySearch)['rows'];
    }

    public function recentSessions(int $limit=100,string $search='',string $type='human',int $days=30,bool $allowIdentitySearch=true): array
    {
        $limit=max(1,min(250,$limit));$days=$this->days($days);$where=['s.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$days.' DAY)'];$args=[];
        if($type==='human')$where[]='s.is_bot=0';elseif($type==='bot')$where[]='s.is_bot=1';
        $search=trim($search);
        if($search!==''){
            $like='%'.$search.'%';
            if($allowIdentitySearch){$where[]='(u.username LIKE ? OR s.ip_address LIKE ? OR s.visitor_key LIKE ? OR s.last_path LIKE ? OR s.first_referrer_host LIKE ?)';array_push($args,$like,$like,$like,$like,$like);}
            else{$where[]='(s.last_path LIKE ? OR s.first_referrer_host LIKE ?)';array_push($args,$like,$like);}
        }
        $whereSql=' WHERE '.implode(' AND ',$where);
        $sql="SELECT s.*,u.username FROM visitor_sessions s LEFT JOIN users u ON u.id=s.user_id{$whereSql} ORDER BY s.last_seen_at DESC LIMIT {$limit}";
        $stmt=$this->db->prepare($sql);$stmt->execute($args);return $stmt->fetchAll();
    }

    public function topPages(int $days=30,int $limit=20): array
    {
        $days=$this->days($days);$limit=max(1,min(100,$limit));
        return $this->db->query("SELECT p.path,COUNT(*) pageviews,COUNT(DISTINCT p.visitor_key) visitors FROM visitor_pageviews p INNER JOIN visitor_sessions s ON s.id=p.session_id WHERE s.is_bot=0 AND p.occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY p.path ORDER BY pageviews DESC,visitors DESC LIMIT {$limit}")->fetchAll();
    }

    public function topLandingPages(int $days=30,int $limit=12): array
    {
        $days=$this->days($days);$limit=max(1,min(50,$limit));
        return $this->db->query("SELECT landing_path path,COUNT(*) sessions,COUNT(DISTINCT visitor_key) visitors FROM visitor_sessions WHERE is_bot=0 AND first_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY landing_path ORDER BY sessions DESC LIMIT {$limit}")->fetchAll();
    }

    public function topExitPages(int $days=30,int $limit=12): array
    {
        $days=$this->days($days);$limit=max(1,min(50,$limit));
        return $this->db->query("SELECT last_path path,COUNT(*) sessions,COUNT(DISTINCT visitor_key) visitors FROM visitor_sessions WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY last_path ORDER BY sessions DESC LIMIT {$limit}")->fetchAll();
    }

    public function topReferrers(int $days=30,int $limit=20): array
    {
        $days=$this->days($days);$limit=max(1,min(100,$limit));
        return $this->db->query("SELECT COALESCE(NULLIF(first_referrer_host,''),'Direct / unknown') referrer,COUNT(*) sessions,COUNT(DISTINCT visitor_key) visitors FROM visitor_sessions WHERE is_bot=0 AND first_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY referrer ORDER BY sessions DESC LIMIT {$limit}")->fetchAll();
    }

    public function deviceBreakdown(int $days=30): array
    {
        $days=$this->days($days);return $this->db->query("SELECT COALESCE(NULLIF(device_type,''),'Unknown') label,COUNT(*) sessions FROM visitor_sessions WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY label ORDER BY sessions DESC")->fetchAll();
    }

    public function browserBreakdown(int $days=30): array
    {
        $days=$this->days($days);return $this->db->query("SELECT COALESCE(NULLIF(browser,''),'Unknown') label,COUNT(*) sessions FROM visitor_sessions WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY label ORDER BY sessions DESC LIMIT 12")->fetchAll();
    }

    public function networkBreakdown(int $days=30,int $limit=12): array
    {
        if(!$this->intelligenceReady())return [];
        $days=$this->days($days);$limit=max(1,min(50,$limit));
        return $this->db->query("SELECT COALESCE(NULLIF(i.organization,''),'Unknown network') organization,MAX(i.asn) asn,COUNT(*) sessions,COUNT(DISTINCT s.visitor_key) visitors FROM visitor_sessions s LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address WHERE s.is_bot=0 AND s.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY organization ORDER BY sessions DESC LIMIT {$limit}")->fetchAll();
    }

    public function countryBreakdown(int $days=30,int $limit=20): array
    {
        $days=$this->days($days);$limit=max(1,min(100,$limit));$intel=$this->intelligenceReady();
        $intelJoin=$intel?' LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address':'';
        $code=$intel?"COALESCE(NULLIF(i.country_code,''),NULLIF(s.country_code,''),'??')":"COALESCE(NULLIF(s.country_code,''),'??')";
        $name=$intel?'MAX(i.country_name)':'NULL';
        $sql="SELECT {$code} code,{$name} country_name,COUNT(*) visitors FROM visitor_sessions s{$intelJoin}
              WHERE s.is_bot=0 AND s.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM visitor_sessions newer
                    WHERE newer.visitor_key=s.visitor_key AND newer.is_bot=0 AND newer.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)
                      AND (newer.last_seen_at>s.last_seen_at OR (newer.last_seen_at=s.last_seen_at AND newer.id>s.id))
                )
              GROUP BY code ORDER BY visitors DESC LIMIT {$limit}";
        return $this->db->query($sql)->fetchAll();
    }

    public function locationBreakdown(int $days=30,int $limit=15): array
    {
        if(!$this->intelligenceLocationReady())return [];
        $days=$this->days($days);$limit=max(1,min(50,$limit));
        $sql="SELECT COALESCE(NULLIF(i.country_code,''),'??') code,MAX(i.country_name) country_name,
                     COALESCE(NULLIF(i.region_name,''),'Unknown region') region_name,
                     COALESCE(NULLIF(i.city_name,''),'Unknown city') city_name,COUNT(*) visitors
              FROM visitor_sessions s
              LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address
              WHERE s.is_bot=0 AND s.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)
                AND (NULLIF(i.city_name,'') IS NOT NULL OR NULLIF(i.region_name,'') IS NOT NULL)
                AND NOT EXISTS (
                    SELECT 1 FROM visitor_sessions newer
                    WHERE newer.visitor_key=s.visitor_key AND newer.is_bot=0 AND newer.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)
                      AND (newer.last_seen_at>s.last_seen_at OR (newer.last_seen_at=s.last_seen_at AND newer.id>s.id))
                )
              GROUP BY COALESCE(NULLIF(i.country_code,''),'??'),COALESCE(NULLIF(i.region_name,''),'Unknown region'),COALESCE(NULLIF(i.city_name,''),'Unknown city')
              ORDER BY visitors DESC,city_name,region_name LIMIT {$limit}";
        return $this->db->query($sql)->fetchAll();
    }

    public function liveVisitors(int $limit=30,bool $allowIdentity=true): array
    {
        $limit=max(1,min(100,$limit));$intel=$this->intelligenceReady();
        $location=$this->intelligenceLocationReady();$intelSelect=$intel?',i.country_code AS resolved_country_code,i.country_name':'';if($intel&&$location)$intelSelect.=',i.region_name,i.city_name';$intelJoin=$intel?' LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address':'';
        $sql="SELECT s.*,u.username{$intelSelect}
              FROM visitor_sessions s
              LEFT JOIN users u ON u.id=COALESCE(s.user_id,(SELECT su.user_id FROM visitor_sessions su WHERE su.visitor_key=s.visitor_key AND su.is_bot=0 AND su.user_id IS NOT NULL ORDER BY su.last_seen_at DESC,su.id DESC LIMIT 1)){$intelJoin}
              WHERE s.is_bot=0 AND s.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)
                AND NOT EXISTS (
                    SELECT 1 FROM visitor_sessions newer
                    WHERE newer.visitor_key=s.visitor_key AND newer.is_bot=0 AND newer.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)
                      AND (newer.last_seen_at>s.last_seen_at OR (newer.last_seen_at=s.last_seen_at AND newer.id>s.id))
                )
              ORDER BY s.last_seen_at DESC LIMIT {$limit}";
        $rows=$this->db->query($sql)->fetchAll();
        if(!$allowIdentity){foreach($rows as &$r){$r['ip_address']='RESTRICTED';$r['username']=!empty($r['user_id'])?'SIGNED IN':'GUEST';}unset($r);}return $rows;
    }

    public function dailyTraffic(int $days=30): array
    {
        $days=$this->days($days);return $this->db->query("SELECT DATE(p.occurred_at) d,COUNT(*) pageviews,COUNT(DISTINCT p.visitor_key) visitors FROM visitor_pageviews p INNER JOIN visitor_sessions s ON s.id=p.session_id WHERE s.is_bot=0 AND p.occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY DATE(p.occurred_at) ORDER BY d")->fetchAll();
    }

    public function trafficOverview(int $days=30): array
    {
        $days=$this->days($days);$interval='INTERVAL '.$days.' DAY';
        $row=$this->db->query("SELECT COUNT(*) sessions,COUNT(DISTINCT visitor_key) visitors,COALESCE(SUM(pageview_count),0) pageviews,ROUND(AVG(pageview_count),2) pages_per_session,ROUND(AVG(TIMESTAMPDIFF(SECOND,first_seen_at,last_seen_at))) avg_session_seconds,SUM(CASE WHEN pageview_count<=1 THEN 1 ELSE 0 END) single_page_sessions FROM visitor_sessions WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})")->fetch()?:[];
        $cohort=$this->db->query("SELECT COUNT(*) active_visitors,SUM(CASE WHEN first_seen_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval}) THEN 1 ELSE 0 END) new_visitors FROM (SELECT visitor_key,MIN(first_seen_at) first_seen_at,MAX(last_seen_at) last_seen_at FROM visitor_sessions WHERE is_bot=0 GROUP BY visitor_key HAVING last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})) x")->fetch()?:[];
        $active=(int)($cohort['active_visitors']??0);$new=(int)($cohort['new_visitors']??0);
        return [
            'sessions'=>(int)($row['sessions']??0),'visitors'=>(int)($row['visitors']??0),'pageviews'=>(int)($row['pageviews']??0),'pages_per_session'=>(float)($row['pages_per_session']??0),
            'avg_session_seconds'=>(int)($row['avg_session_seconds']??0),'single_page_sessions'=>(int)($row['single_page_sessions']??0),'new_visitors'=>$new,'returning_visitors'=>max(0,$active-$new),
        ];
    }

    public function conversionSummary(int $days=30): array
    {
        $days=$this->days($days);
        $interval='INTERVAL '.$days.' DAY';
        $visitors=(int)$this->db->query("SELECT COUNT(DISTINCT visitor_key) FROM visitor_sessions WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})")->fetchColumn();
        $productViews=$this->eventCount('product_viewed',$days,true);
        $cartAdds=$this->eventCount('product_added_to_cart',$days,true);
        $checkoutStarts=$this->eventCount('checkout_started',$days,true);
        $wishlistAdds=$this->eventCount('wishlist_added',$days,true);
        $registrations=$this->eventCount('registration_completed',$days,true);
        $orders=0;$paidOrders=0;
        try{
            $orders=(int)$this->db->query("SELECT COUNT(*) FROM store_orders WHERE status<>'cancelled' AND placed_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})")->fetchColumn();
            $paidOrders=(int)$this->db->query("SELECT COUNT(*) FROM store_orders WHERE status<>'cancelled' AND payment_status IN ('paid','partially_refunded','refunded') AND placed_at>=DATE_SUB(UTC_TIMESTAMP(),{$interval})")->fetchColumn();
        }catch(\Throwable){}
        return compact('days','visitors','productViews','cartAdds','checkoutStarts','wishlistAdds','registrations','orders','paidOrders');
    }

    public function conversionFunnel(int $days=30): array
    {
        $days=$this->days($days);
        return [
            'days'=>$days,
            'visitors'=>(int)$this->db->query("SELECT COUNT(DISTINCT visitor_key) FROM visitor_sessions WHERE is_bot=0 AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)")->fetchColumn(),
            'product_views'=>$this->eventCount('product_viewed',$days,true),
            'cart_adds'=>$this->eventCount('product_added_to_cart',$days,true),
            'checkout_starts'=>$this->eventCount('checkout_started',$days,true),
            'registrations'=>$this->eventCount('registration_completed',$days,true),
            'orders'=>($this->safeScalar("SELECT COUNT(*) FROM store_orders WHERE status<>'cancelled' AND placed_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)")),
            'paid_orders'=>($this->safeScalar("SELECT COUNT(*) FROM store_orders WHERE status<>'cancelled' AND payment_status IN ('paid','partially_refunded','refunded') AND placed_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)")),
        ];
    }

    public function campaignSummary(int $days=30,int $limit=20): array
    {
        $days=$this->days($days);$limit=max(1,min(100,$limit));return $this->db->query("SELECT COALESCE(NULLIF(utm_source,''),'Direct / untagged') source,COALESCE(NULLIF(utm_campaign,''),'—') campaign,COUNT(*) sessions,COUNT(DISTINCT visitor_key) visitors FROM visitor_sessions WHERE is_bot=0 AND first_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) GROUP BY source,campaign ORDER BY sessions DESC LIMIT {$limit}")->fetchAll();
    }

    public function reclassifyInvalidGuestSessions(array $validPaths): int
    {
        $validPaths=array_values(array_unique(array_filter(array_map(static fn($v)=>is_string($v)?trim($v):'', $validPaths),static fn($v)=>$v!=='')));if(!$validPaths)return 0;
        $marks=implode(',',array_fill(0,count($validPaths),'?'));$sql="UPDATE visitor_sessions SET is_bot=1 WHERE is_bot=0 AND user_id IS NULL AND landing_path NOT IN ({$marks}) AND last_path NOT IN ({$marks})";
        $stmt=$this->db->prepare($sql);$stmt->execute(array_merge($validPaths,$validPaths));return $stmt->rowCount();
    }

    public function automatedRequestCount(int $days=30): int
    {
        $days=$this->days($days);$stmt=$this->db->prepare("SELECT COUNT(*) FROM visitor_events WHERE event_key='automated_request' AND occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)");$stmt->execute();return (int)$stmt->fetchColumn();
    }

    public function recentAutomatedRequests(int $days=30,int $limit=100): array
    {
        $days=$this->days($days);$limit=max(1,min(250,$limit));$rows=$this->db->query("SELECT path,metadata_json,occurred_at FROM visitor_events WHERE event_key='automated_request' AND occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) ORDER BY occurred_at DESC LIMIT {$limit}")->fetchAll();
        foreach($rows as &$row){$meta=json_decode((string)($row['metadata_json']??''),true);if(!is_array($meta))$meta=[];$row['reason']=(string)($meta['reason']??'filtered');$row['method']=(string)($meta['method']??'—');$row['ip']=(string)($meta['ip']??'—');$row['user_agent']=(string)($meta['user_agent']??'');$row['browser']=(string)($meta['browser']??'Other');$row['os']=(string)($meta['os']??'Other');$row['device']=(string)($meta['device']??'Other');}unset($row);return $rows;
    }

    public function visitorSummary(string $visitorKey): ?array
    {
        $intel=$this->intelligenceReady();$life=$this->intelligenceLifecycleReady();$location=$this->intelligenceLocationReady();
        $intelSelect=$intel?',i.country_code AS resolved_country_code,i.country_name,i.asn,i.organization,i.timezone_name,i.source AS intelligence_source,i.resolved_at AS intelligence_resolved_at':'';
        if($intel&&$location)$intelSelect.=',i.region_name,i.city_name';
        if($life)$intelSelect.=',i.resolution_status,i.last_error AS intelligence_last_error,i.attempt_count AS intelligence_attempt_count,i.next_retry_at AS intelligence_next_retry_at';
        $intelJoin=$intel?' LEFT JOIN visitor_ip_intelligence i ON i.ip_address=latest.ip_address':'';
        $sql="SELECT agg.*,latest.ip_address AS last_ip,latest.browser,latest.os_name,latest.device_type,latest.user_agent,latest.country_code,latest.last_path,latest.landing_path,latest.first_referrer_host AS referrer,latest.last_referrer_host,latest.utm_source,latest.utm_medium,latest.utm_campaign,u.id AS user_id,u.username{$intelSelect}
              FROM (
                SELECT visitor_key,MIN(first_seen_at) first_seen_at,MAX(last_seen_at) last_seen_at,SUM(pageview_count) pageviews,COUNT(*) sessions,COUNT(DISTINCT NULLIF(ip_address,'')) unique_ips,COUNT(DISTINCT DATE(first_seen_at)) active_days,COUNT(DISTINCT NULLIF(browser,'')) unique_browsers,COUNT(DISTINCT NULLIF(device_type,'')) unique_devices
                FROM visitor_sessions WHERE visitor_key=? GROUP BY visitor_key
              ) agg
              INNER JOIN visitor_sessions latest ON latest.id=(SELECT sl.id FROM visitor_sessions sl WHERE sl.visitor_key=agg.visitor_key ORDER BY sl.last_seen_at DESC,sl.id DESC LIMIT 1)
              LEFT JOIN users u ON u.id=(SELECT su.user_id FROM visitor_sessions su WHERE su.visitor_key=agg.visitor_key AND su.user_id IS NOT NULL ORDER BY su.last_seen_at DESC,su.id DESC LIMIT 1){$intelJoin}
              LIMIT 1";
        $stmt=$this->db->prepare($sql);$stmt->execute([$visitorKey]);$row=$stmt->fetch();if(!is_array($row))return null;$row['pages_per_session']=(int)$row['sessions']>0?round((int)$row['pageviews']/(int)$row['sessions'],2):0;return $row;
    }

    public function visitorAcquisition(string $visitorKey): ?array
    {
        $stmt=$this->db->prepare('SELECT landing_path,first_referrer_host,utm_source,utm_medium,utm_campaign,first_seen_at FROM visitor_sessions WHERE visitor_key=? ORDER BY first_seen_at ASC,id ASC LIMIT 1');$stmt->execute([$visitorKey]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function visitorIpHistory(string $visitorKey,int $limit=20): array
    {
        $limit=max(1,min(100,$limit));$intel=$this->intelligenceReady();$life=$this->intelligenceLifecycleReady();$location=$this->intelligenceLocationReady();
        $intelSelect=$intel?',MAX(i.country_code) AS resolved_country_code,MAX(i.country_name) country_name,MAX(i.asn) asn,MAX(i.organization) organization,MAX(i.timezone_name) timezone_name,MAX(i.source) AS intelligence_source,MAX(i.resolved_at) AS intelligence_resolved_at':'';
        if($intel&&$location)$intelSelect.=',MAX(i.region_name) region_name,MAX(i.city_name) city_name';
        if($life)$intelSelect.=',MAX(i.resolution_status) resolution_status,MAX(i.last_error) intelligence_last_error,MAX(i.attempt_count) intelligence_attempt_count,MAX(i.next_retry_at) intelligence_next_retry_at';
        $intelJoin=$intel?' LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address':'';
        $sql="SELECT s.ip_address,MIN(s.first_seen_at) first_seen_at,MAX(s.last_seen_at) last_seen_at,COUNT(*) sessions,SUM(s.pageview_count) pageviews,MAX(s.country_code) country_code{$intelSelect} FROM visitor_sessions s{$intelJoin} WHERE s.visitor_key=? GROUP BY s.ip_address ORDER BY last_seen_at DESC LIMIT {$limit}";
        $stmt=$this->db->prepare($sql);$stmt->execute([$visitorKey]);return $stmt->fetchAll();
    }

    public function ipIntelligence(string $ip): ?array
    {
        if(!$this->intelligenceReady())return null;$stmt=$this->db->prepare('SELECT * FROM visitor_ip_intelligence WHERE ip_address=? LIMIT 1');$stmt->execute([$ip]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function queueIpIntelligence(string $ip): void
    {
        if(!$this->intelligenceLifecycleReady()||!filter_var($ip,FILTER_VALIDATE_IP))return;
        $stmt=$this->db->prepare("INSERT IGNORE INTO visitor_ip_intelligence(ip_address,country_code,country_name,asn,organization,timezone_name,source,resolution_status,last_error,attempt_count,next_retry_at,queued_at,resolved_at) VALUES(?,NULL,NULL,NULL,NULL,NULL,'pending','pending',NULL,0,NULL,UTC_TIMESTAMP(),NULL)");$stmt->execute([$ip]);
    }

    public function ipIntelligenceCandidates(int $limit=5): array
    {
        if(!$this->intelligenceLifecycleReady())return [];$limit=max(1,min(25,$limit));
        $sql="SELECT ip_address FROM (
                SELECT i.ip_address,0 priority,COALESCE(i.next_retry_at,'1970-01-01 00:00:00') due_at FROM visitor_ip_intelligence i WHERE i.resolution_status='pending' OR (i.resolution_status='failed' AND i.attempt_count<6 AND (i.next_retry_at IS NULL OR i.next_retry_at<=UTC_TIMESTAMP()))
                UNION
                SELECT s.ip_address,1 priority,'1970-01-01 00:00:00' due_at FROM visitor_sessions s LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address WHERE s.ip_address IS NOT NULL AND s.ip_address<>'' AND i.ip_address IS NULL GROUP BY s.ip_address
              ) c GROUP BY ip_address ORDER BY MIN(priority),MIN(due_at),ip_address LIMIT {$limit}";
        return array_values(array_filter(array_map('strval',$this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN)),static fn(string $ip)=>$ip!==''));
    }

    public function markIpIntelligenceFailure(string $ip,string $error,bool $permanent=false): void
    {
        if(!$this->intelligenceLifecycleReady()||!filter_var($ip,FILTER_VALIDATE_IP))return;$error=substr(trim($error),0,255);
        if($permanent){$stmt=$this->db->prepare("INSERT INTO visitor_ip_intelligence(ip_address,source,resolution_status,last_error,attempt_count,next_retry_at,queued_at,resolved_at) VALUES(?,'local','private',?,1,NULL,UTC_TIMESTAMP(),NULL) ON DUPLICATE KEY UPDATE source='local',resolution_status='private',last_error=VALUES(last_error),attempt_count=attempt_count+1,next_retry_at=NULL,resolved_at=NULL");$stmt->execute([$ip,$error]);return;}
        $stmt=$this->db->prepare("INSERT INTO visitor_ip_intelligence(ip_address,source,resolution_status,last_error,attempt_count,next_retry_at,queued_at,resolved_at) VALUES(?,'pending','failed',?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE),UTC_TIMESTAMP(),NULL) ON DUPLICATE KEY UPDATE source='pending',resolution_status='failed',last_error=VALUES(last_error),attempt_count=attempt_count+1,next_retry_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 MINUTE),resolved_at=NULL");$stmt->execute([$ip,$error]);
    }

    public function saveIpIntelligence(array $data): void
    {
        if(!$this->intelligenceReady())throw new \RuntimeException('Visitor intelligence database update is not installed.');$ip=(string)($data['ip']??'');if(!filter_var($ip,FILTER_VALIDATE_IP))throw new \InvalidArgumentException('Invalid IP intelligence address.');
        $location=$this->intelligenceLocationReady();
        if($this->intelligenceLifecycleReady()){
            if($location){
                $stmt=$this->db->prepare("INSERT INTO visitor_ip_intelligence(ip_address,country_code,country_name,region_name,city_name,asn,organization,timezone_name,source,resolution_status,last_error,attempt_count,next_retry_at,queued_at,resolved_at) VALUES(?,?,?,?,?,?,?,?,?,'resolved',NULL,0,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE country_code=VALUES(country_code),country_name=VALUES(country_name),region_name=VALUES(region_name),city_name=VALUES(city_name),asn=VALUES(asn),organization=VALUES(organization),timezone_name=VALUES(timezone_name),source=VALUES(source),resolution_status='resolved',last_error=NULL,attempt_count=0,next_retry_at=NULL,resolved_at=UTC_TIMESTAMP()");
                $args=[$ip,$data['country_code']??null,$data['country_name']??null,$data['region']??null,$data['city']??null,$data['asn']??null,$data['organization']??null,$data['timezone_name']??null,substr((string)($data['source']??'manual'),0,48)];
            }else{
                $stmt=$this->db->prepare("INSERT INTO visitor_ip_intelligence(ip_address,country_code,country_name,asn,organization,timezone_name,source,resolution_status,last_error,attempt_count,next_retry_at,queued_at,resolved_at) VALUES(?,?,?,?,?,?,?,'resolved',NULL,0,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE country_code=VALUES(country_code),country_name=VALUES(country_name),asn=VALUES(asn),organization=VALUES(organization),timezone_name=VALUES(timezone_name),source=VALUES(source),resolution_status='resolved',last_error=NULL,attempt_count=0,next_retry_at=NULL,resolved_at=UTC_TIMESTAMP()");
                $args=[$ip,$data['country_code']??null,$data['country_name']??null,$data['asn']??null,$data['organization']??null,$data['timezone_name']??null,substr((string)($data['source']??'manual'),0,48)];
            }
        }else{
            if($location){
                $stmt=$this->db->prepare('INSERT INTO visitor_ip_intelligence(ip_address,country_code,country_name,region_name,city_name,asn,organization,timezone_name,source,resolved_at) VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE country_code=VALUES(country_code),country_name=VALUES(country_name),region_name=VALUES(region_name),city_name=VALUES(city_name),asn=VALUES(asn),organization=VALUES(organization),timezone_name=VALUES(timezone_name),source=VALUES(source),resolved_at=UTC_TIMESTAMP()');
                $args=[$ip,$data['country_code']??null,$data['country_name']??null,$data['region']??null,$data['city']??null,$data['asn']??null,$data['organization']??null,$data['timezone_name']??null,substr((string)($data['source']??'manual'),0,48)];
            }else{
                $stmt=$this->db->prepare('INSERT INTO visitor_ip_intelligence(ip_address,country_code,country_name,asn,organization,timezone_name,source,resolved_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE country_code=VALUES(country_code),country_name=VALUES(country_name),asn=VALUES(asn),organization=VALUES(organization),timezone_name=VALUES(timezone_name),source=VALUES(source),resolved_at=UTC_TIMESTAMP()');
                $args=[$ip,$data['country_code']??null,$data['country_name']??null,$data['asn']??null,$data['organization']??null,$data['timezone_name']??null,substr((string)($data['source']??'manual'),0,48)];
            }
        }
        $stmt->execute($args);
        if(!empty($data['country_code'])){$update=$this->db->prepare("UPDATE visitor_sessions SET country_code=? WHERE ip_address=? AND (country_code IS NULL OR country_code='')");$update->execute([(string)$data['country_code'],$ip]);}
    }

    public function intelligenceStats(): array
    {
        if(!$this->intelligenceReady())return ['resolved'=>0,'pending'=>0,'failed'=>0,'private'=>0,'total'=>0,'unqueued'=>0];
        if(!$this->intelligenceLifecycleReady()){
            $row=$this->db->query("SELECT COUNT(DISTINCT s.ip_address) total,COUNT(DISTINCT CASE WHEN COALESCE(NULLIF(i.country_code,''),NULLIF(s.country_code,'')) IS NOT NULL THEN s.ip_address END) resolved,COUNT(DISTINCT CASE WHEN i.ip_address IS NULL THEN s.ip_address END) unqueued FROM visitor_sessions s LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address WHERE s.is_bot=0 AND s.ip_address IS NOT NULL AND s.ip_address<>''")->fetch()?:[];
            $total=(int)($row['total']??0);$resolved=(int)($row['resolved']??0);$unqueued=(int)($row['unqueued']??0);
            return ['resolved'=>$resolved,'pending'=>max(0,$total-$resolved-$unqueued),'failed'=>0,'private'=>0,'total'=>$total,'unqueued'=>$unqueued];
        }
        $row=$this->db->query("SELECT COUNT(DISTINCT s.ip_address) total,COUNT(DISTINCT CASE WHEN COALESCE(NULLIF(i.country_code,''),NULLIF(s.country_code,'')) IS NOT NULL THEN s.ip_address END) resolved,COUNT(DISTINCT CASE WHEN i.resolution_status='pending' THEN s.ip_address END) pending,COUNT(DISTINCT CASE WHEN i.resolution_status='failed' THEN s.ip_address END) failed,COUNT(DISTINCT CASE WHEN i.resolution_status='private' THEN s.ip_address END) private,COUNT(DISTINCT CASE WHEN i.ip_address IS NULL THEN s.ip_address END) unqueued FROM visitor_sessions s LEFT JOIN visitor_ip_intelligence i ON i.ip_address=s.ip_address WHERE s.is_bot=0 AND s.ip_address IS NOT NULL AND s.ip_address<>''")->fetch()?:[];
        return ['resolved'=>(int)($row['resolved']??0),'pending'=>(int)($row['pending']??0),'failed'=>(int)($row['failed']??0),'private'=>(int)($row['private']??0),'total'=>(int)($row['total']??0),'unqueued'=>(int)($row['unqueued']??0)];
    }

    public function visitorEngagementSummary(string $visitorKey): array
    {
        $summary=['unique_pages'=>0,'top_pages'=>[],'events'=>[]];
        try{
            $stmt=$this->db->prepare('SELECT COUNT(DISTINCT path) FROM visitor_pageviews WHERE visitor_key=?');$stmt->execute([$visitorKey]);$summary['unique_pages']=(int)$stmt->fetchColumn();
            $stmt=$this->db->prepare('SELECT path,COUNT(*) views FROM visitor_pageviews WHERE visitor_key=? GROUP BY path ORDER BY views DESC,path ASC LIMIT 8');$stmt->execute([$visitorKey]);$summary['top_pages']=$stmt->fetchAll();
            $stmt=$this->db->prepare("SELECT event_key,COUNT(*) events FROM visitor_events WHERE visitor_key=? AND event_key IN ('product_viewed','product_added_to_cart','search_used','checkout_started','wishlist_added','registration_completed','login_completed') GROUP BY event_key ORDER BY events DESC,event_key");$stmt->execute([$visitorKey]);
            foreach($stmt->fetchAll() as $row)$summary['events'][(string)$row['event_key']]=(int)$row['events'];
        }catch(\Throwable){}
        return $summary;
    }

    public function visitorAccountSummary(int $userId): array
    {
        if($userId<=0)return [];
        try{
            $sql="SELECT u.created_at,
                  (SELECT COUNT(*) FROM login_security_events l WHERE l.user_id=u.id AND l.success=1) successful_logins,
                  (SELECT COUNT(*) FROM store_orders o WHERE o.user_id=u.id) orders,
                  (SELECT COALESCE(SUM(GREATEST(o.grand_total-COALESCE((SELECT SUM(r.refund_amount) FROM store_returns r WHERE r.order_id=o.id AND r.status='completed'),0),0)),0) FROM store_orders o WHERE o.user_id=u.id AND o.status<>'cancelled' AND o.payment_status IN ('paid','partially_refunded','refunded')) lifetime_value,
                  (SELECT COUNT(*) FROM store_wishlist_items wi INNER JOIN store_wishlists w ON w.id=wi.wishlist_id WHERE w.user_id=u.id) wishlist_items,
                  (SELECT COUNT(*) FROM support_tickets st WHERE st.user_id=u.id) support_tickets
                  FROM users u WHERE u.id=? LIMIT 1";
            $stmt=$this->db->prepare($sql);$stmt->execute([$userId]);$row=$stmt->fetch();return is_array($row)?$row:[];
        }catch(\Throwable){return [];}
    }

    public function visitorSessionsPage(string $visitorKey,int $page=1,int $perPage=15): array
    {
        $page=max(1,$page);$perPage=max(5,min(100,$perPage));$count=$this->db->prepare('SELECT COUNT(*) FROM visitor_sessions WHERE visitor_key=?');$count->execute([$visitorKey]);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));if($page>$pages)$page=$pages;$offset=($page-1)*$perPage;
        $stmt=$this->db->prepare("SELECT s.*,u.username FROM visitor_sessions s LEFT JOIN users u ON u.id=s.user_id WHERE s.visitor_key=? ORDER BY s.last_seen_at DESC,s.id DESC LIMIT {$perPage} OFFSET {$offset}");$stmt->execute([$visitorKey]);
        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    public function visitorSessions(string $visitorKey,int $limit=30): array
    {
        $limit=max(1,min(100,$limit));return $this->visitorSessionsPage($visitorKey,1,$limit)['rows'];
    }

    public function visitorPageviews(string $visitorKey,int $limit=150): array
    {
        $limit=max(1,min(300,$limit));$stmt=$this->db->prepare("SELECT path,referrer_host,occurred_at FROM visitor_pageviews WHERE visitor_key=? ORDER BY occurred_at DESC,id DESC LIMIT {$limit}");$stmt->execute([$visitorKey]);return $stmt->fetchAll();
    }

    public function visitorEvents(string $visitorKey,int $limit=100): array
    {
        $limit=max(1,min(200,$limit));$stmt=$this->db->prepare("SELECT event_key,path,metadata_json,occurred_at FROM visitor_events WHERE visitor_key=? ORDER BY occurred_at DESC,id DESC LIMIT {$limit}");$stmt->execute([$visitorKey]);return $stmt->fetchAll();
    }

    public function visitorJourneyPage(string $visitorKey,int $page=1,int $perPage=50,string $kind='all'): array
    {
        $page=max(1,$page);$perPage=max(10,min(100,$perPage));$kind=in_array($kind,['all','pages','events','conversions'],true)?$kind:'all';
        $eventKeys=['product_viewed','product_added_to_cart','search_used','checkout_started','wishlist_added','registration_completed','login_completed'];
        $filter='';$args=[$visitorKey,$visitorKey];
        if($kind==='pages')$filter=" WHERE j.kind='pageview'";
        elseif($kind==='events')$filter=" WHERE j.kind='event'";
        elseif($kind==='conversions'){$marks=implode(',',array_fill(0,count($eventKeys),'?'));$filter=" WHERE j.kind='event' AND j.event_key IN ({$marks})";$args=array_merge($args,$eventKeys);}
        $union="SELECT 'pageview' kind,NULL event_key,p.path,NULL metadata_json,p.occurred_at,p.id sort_id FROM visitor_pageviews p WHERE p.visitor_key=? UNION ALL SELECT 'event' kind,e.event_key,e.path,e.metadata_json,e.occurred_at,e.id sort_id FROM visitor_events e WHERE e.visitor_key=?";
        $count=$this->db->prepare("SELECT COUNT(*) FROM ({$union}) j{$filter}");$count->execute($args);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));if($page>$pages)$page=$pages;$offset=($page-1)*$perPage;
        $stmt=$this->db->prepare("SELECT * FROM ({$union}) j{$filter} ORDER BY j.occurred_at DESC,j.sort_id DESC LIMIT {$perPage} OFFSET {$offset}");$stmt->execute($args);$rows=$stmt->fetchAll();
        foreach($rows as &$row){$row['label']=$row['kind']==='pageview'?'Visited '.(string)$row['path']:strtoupper(str_replace(['_','.'],' ',(string)$row['event_key']));$meta=json_decode((string)($row['metadata_json']??''),true);$row['metadata']=is_array($meta)?$meta:[];}unset($row);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage,'kind'=>$kind];
    }

    public function blockedRequestCount(int $days=30): int
    {
        $days=$this->days($days);return (int)$this->db->query("SELECT COUNT(*) FROM visitor_events WHERE event_key='ip_blocked_request' AND occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)")->fetchColumn();
    }

    public function recentBlockedRequests(int $days=30,int $limit=100): array
    {
        $days=$this->days($days);$limit=max(1,min(250,$limit));$rows=$this->db->query("SELECT path,metadata_json,occurred_at FROM visitor_events WHERE event_key='ip_blocked_request' AND occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) ORDER BY occurred_at DESC LIMIT {$limit}")->fetchAll();
        foreach($rows as &$row){$meta=json_decode((string)($row['metadata_json']??''),true);if(!is_array($meta))$meta=[];$row=array_merge($row,['block_id'=>(int)($meta['block_id']??0),'cidr'=>(string)($meta['cidr']??''),'reason'=>(string)($meta['reason']??''),'ip'=>(string)($meta['ip']??''),'method'=>(string)($meta['method']??''),'user_agent'=>(string)($meta['user_agent']??'')]);}unset($row);return $rows;
    }

    public function prune(int $days=90): int
    {
        $days=max(30,min(365,$days));$stmt=$this->db->prepare('DELETE FROM visitor_sessions WHERE last_seen_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$days.' DAY)');$stmt->execute();$sessions=$stmt->rowCount();
        $this->db->exec('DELETE FROM visitor_events WHERE session_id IS NULL AND occurred_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$days.' DAY)');
        if($this->intelligenceReady()){
            if($this->intelligenceLifecycleReady())$this->db->exec("DELETE i FROM visitor_ip_intelligence i LEFT JOIN visitor_sessions s ON s.ip_address=i.ip_address WHERE s.id IS NULL AND COALESCE(i.resolved_at,i.queued_at) < DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)");
            else $this->db->exec('DELETE i FROM visitor_ip_intelligence i LEFT JOIN visitor_sessions s ON s.ip_address=i.ip_address WHERE s.id IS NULL AND i.resolved_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$days.' DAY)');
        }
        return $sessions;
    }

    private function eventCount(string $eventKey,int $days,bool $distinctUsers=false): int
    {
        $days=$this->days($days);$expr=$distinctUsers?'COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR),visitor_key))':'COUNT(*)';$stmt=$this->db->prepare("SELECT {$expr} FROM visitor_events WHERE event_key=? AND occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)");$stmt->execute([$eventKey]);return (int)$stmt->fetchColumn();
    }

    private function days(int $days): int
    {
        return in_array($days,[7,30,90],true)?$days:30;
    }
    private function safeScalar(string $sql): int
    {
        try{return (int)$this->db->query($sql)->fetchColumn();}catch(\Throwable){return 0;}
    }

}
