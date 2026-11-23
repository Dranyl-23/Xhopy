<?php

namespace App\Providers;

use App\Domain\Cart\Listeners\MergeGuestCartOnLogin;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Policies\CatalogPolicy;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Customer\Models\WishlistItem;
use App\Domain\Customer\Policies\CustomerAddressPolicy;
use App\Domain\Customer\Policies\WishlistItemPolicy;
use App\Domain\Identity\Listeners\RecordSecurityAuditEvents;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Orders\Policies\OrderPolicy;
use App\Domain\Orders\Policies\SellerOrderPolicy;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Gateways\PayMongoGateway;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Policies\SellerProfilePolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PayMongoGateway::class);

        if ($this->app->runningInConsole() && class_exists(ServeCommand::class)) {
            ServeCommand::$passthroughVariables = array_values(array_unique([
                ...ServeCommand::$passthroughVariables,
                'TMP',
                'TEMP',
                'TMPDIR',
                'USERPROFILE',
                'LOCALAPPDATA',
                'WINDIR',
            ]));

            foreach (['TMP', 'TEMP', 'TMPDIR'] as $tempVar) {
                $current = $_ENV[$tempVar] ?? null;
                if (! is_string($current) || $current === '') {
                    $resolved = getenv($tempVar);
                    if (is_string($resolved) && $resolved !== '') {
                        $_ENV[$tempVar] = $resolved;
                    }
                }
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureDevCommands();
        Event::subscribe(RecordSecurityAuditEvents::class);
        Event::listen(Login::class, MergeGuestCartOnLogin::class);
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(Product::class, CatalogPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);
        Gate::policy(WishlistItem::class, WishlistItemPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(SellerOrder::class, SellerOrderPolicy::class);
        Gate::policy(SellerProfile::class, SellerProfilePolicy::class);

        Gate::define('manage-catalog', static function (User $user): bool {
            return $user->canManageCatalog();
        });

        Gate::define('manage-orders', static function (User $user): bool {
            return $user->canManageOrders();
        });

        Gate::define('manage-inventory', static function (User $user): bool {
            return $user->canManageInventory();
        });

        Gate::define('review-sellers', static function (User $user): bool {
            return $user->canReviewSellers();
        });

        Gate::define('manage-operations', static function (User $user): bool {
            return $user->canManageOperations();
        });

        Gate::define('manage-users', static function (User $user): bool {
            return $user->isSuperAdmin();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        URL::forceHttps(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register the Vite dev server pointing to the separated /frontend workspace.
     */
    protected function configureDevCommands(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            DevCommands::register('npm --prefix ../frontend run dev', 'vite');
        }
    }
}
