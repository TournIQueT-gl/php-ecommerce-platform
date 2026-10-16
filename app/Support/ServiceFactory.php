<?php
declare(strict_types=1);

namespace App\Support;

use App\Events\EventDispatcher;
use App\Repositories\AccountActivityRepository;
use App\Repositories\AnnouncementRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\CommerceRepository;
use App\Repositories\DomainEventRepository;
use App\Repositories\EmailRepository;
use App\Repositories\FeatureFlagRepository;
use App\Repositories\HomepageRepository;
use App\Repositories\IpAccessRepository;
use App\Repositories\LoginSecurityRepository;
use App\Repositories\MigrationRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PasswordRecoveryRepository;
use App\Repositories\SchedulerStateRepository;
use App\Repositories\StorefrontRepository;
use App\Repositories\StoreSettingsRepository;
use App\Repositories\SupportReadRepository;
use App\Repositories\SupportRepository;
use App\Repositories\TwoFactorRepository;
use App\Repositories\UserRepository;
use App\Repositories\VisitorAnalyticsRepository;
use App\Repositories\WebSessionRepository;
use App\Services\AccountService;
use App\Services\AuthenticationService;
use App\Services\CatalogService;
use App\Services\CheckoutService;
use App\Services\EmailService;
use App\Services\EventProcessor;
use App\Services\FoundationScheduler;
use App\Services\HomepageService;
use App\Services\IpIntelligenceService;
use App\Services\InventoryService;
use App\Services\MediaService;
use App\Services\NavigationStateService;
use App\Services\OrderStateService;
use App\Services\PasswordRecoveryService;
use App\Services\ReturnService;
use App\Services\SmtpClient;
use App\Services\StoreSettingsService;
use App\Services\SupportService;
use App\Services\TwoFactorService;
use PDO;

final class ServiceFactory
{
    public static function storefront(PDO $db): StorefrontRepository { return new StorefrontRepository($db); }
    public static function catalogRepository(PDO $db): CatalogRepository { return new CatalogRepository($db); }
    public static function catalog(PDO $db): CatalogService { return new CatalogService(self::catalogRepository($db)); }
    public static function commerceRepository(PDO $db): CommerceRepository { return new CommerceRepository($db); }
    public static function checkout(PDO $db): CheckoutService { return new CheckoutService($db,self::commerceRepository($db),self::events($db),self::notifications($db)); }
    public static function homepageRepository(PDO $db): HomepageRepository { return new HomepageRepository($db); }
    public static function homepage(): HomepageService { static $service=null; return $service ??= new HomepageService(); }
    public static function storeSettingsRepository(PDO $db): StoreSettingsRepository { return new StoreSettingsRepository($db); }
    public static function storeSettings(): StoreSettingsService { static $service=null; return $service ??= new StoreSettingsService(); }
    public static function media(): MediaService { static $service=null; return $service ??= new MediaService(); }
    public static function inventory(PDO $db): InventoryService { return new InventoryService($db); }
    public static function returns(PDO $db): ReturnService { return new ReturnService($db,self::inventory($db),self::events($db)); }

    public static function users(PDO $db): UserRepository { return new UserRepository($db); }
    public static function webSessions(PDO $db): WebSessionRepository { return new WebSessionRepository($db); }
    public static function loginSecurity(PDO $db): LoginSecurityRepository { return new LoginSecurityRepository($db); }
    public static function recoveryRepository(PDO $db): PasswordRecoveryRepository { return new PasswordRecoveryRepository($db); }
    public static function twoFactorRepository(PDO $db): TwoFactorRepository { return new TwoFactorRepository($db); }
    public static function twoFactor(PDO $db): TwoFactorService { return new TwoFactorService(self::twoFactorRepository($db)); }
    public static function auth(PDO $db): AuthenticationService { return new AuthenticationService(self::users($db)); }
    public static function audit(PDO $db): AuditLogRepository { return new AuditLogRepository($db); }
    public static function accountActivity(PDO $db): AccountActivityRepository { return new AccountActivityRepository($db); }

    public static function featureFlags(PDO $db): FeatureFlagRepository { return new FeatureFlagRepository($db); }
    public static function notifications(PDO $db): NotificationRepository { return new NotificationRepository($db); }
    public static function domainEvents(PDO $db): DomainEventRepository { return new DomainEventRepository($db); }
    public static function events(PDO $db): EventDispatcher { return new EventDispatcher(self::domainEvents($db)); }
    public static function supportRepository(PDO $db): SupportRepository { return new SupportRepository($db); }
    public static function supportReads(PDO $db): SupportReadRepository { return new SupportReadRepository($db); }
    public static function support(PDO $db): SupportService { return new SupportService(self::supportRepository($db),self::accountActivity($db),self::events($db)); }
    public static function announcements(PDO $db): AnnouncementRepository { return new AnnouncementRepository($db); }
    public static function ipAccess(PDO $db): IpAccessRepository { return new IpAccessRepository($db); }

    public static function emailRepository(PDO $db): EmailRepository { return new EmailRepository($db); }
    public static function email(PDO $db): EmailService { return new EmailService($db,self::emailRepository($db),self::users($db),new SmtpClient(),self::featureFlags($db)); }
    public static function passwordRecovery(PDO $db): PasswordRecoveryService
    {
        return new PasswordRecoveryService($db,self::users($db),self::recoveryRepository($db),self::webSessions($db),self::accountActivity($db),self::loginSecurity($db),self::events($db),self::notifications($db),self::email($db));
    }
    public static function account(PDO $db): AccountService
    {
        return new AccountService($db,self::users($db),self::webSessions($db),self::events($db),self::notifications($db));
    }

    public static function visitorAnalytics(PDO $db): VisitorAnalyticsRepository { return new VisitorAnalyticsRepository($db); }
    public static function ipIntelligence(): IpIntelligenceService { static $service=null; return $service ??= new IpIntelligenceService(); }
    public static function migrations(PDO $db): MigrationRepository { return new MigrationRepository($db); }
    public static function schedulerState(PDO $db): SchedulerStateRepository { return new SchedulerStateRepository($db); }
    public static function eventProcessor(PDO $db): EventProcessor { return new EventProcessor(self::domainEvents($db),self::notifications($db),self::email($db)); }
    public static function scheduler(PDO $db): FoundationScheduler
    {
        return new FoundationScheduler(self::schedulerState($db),self::eventProcessor($db),self::notifications($db),self::domainEvents($db),self::webSessions($db),self::loginSecurity($db),self::recoveryRepository($db),self::email($db),self::supportRepository($db),self::announcements($db),self::visitorAnalytics($db),self::ipIntelligence());
    }
    public static function navigationState(PDO $db): NavigationStateService { return new NavigationStateService(self::featureFlags($db),self::notifications($db),self::supportReads($db)); }
    public static function orderState(): OrderStateService { static $service=null; return $service ??= new OrderStateService(); }
}
