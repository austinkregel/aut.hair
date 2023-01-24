<?php
declare(strict_types=1);

namespace Tests\Unit\Code;

use App\Services\Code;
use App\Services\Programming\LaravelProgrammingStyle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tests\App\CustomNewEvent;
use Tests\App\Listeners\LogAuthenticatedUserListener;
use Tests\App\Providers\EventServiceProvider;
use Tests\App\Traits\CausesActivity;
use Tests\App\Traits\HasApiTokensExample;
use Tests\App\Traits\LogsActivity;
use Tests\App\User;
use Tests\App\Contracts\Ownable;
use Tests\TestCase;

class CodeTest extends TestCase
{

    public function testWeCanReturnTheFileContents()
    {
        $userModel = LaravelProgrammingStyle::for(User::class)
            ->toFile();

        $this->assertSame(file_get_contents(base_path('tests/App/User.php')), $userModel);
    }

    public function testWeCanUseTraitsAndImplementAContract()
    {
        $newUserModel = LaravelProgrammingStyle::for(User::class)
            ->use(HasApiTokensExample::class)
            ->use(CausesActivity::class)
            ->use(LogsActivity::class)
            ->implements(Ownable::class)
            ->toFile();

        $this->assertSame("<?php

namespace Tests\App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tests\App\Contracts\Ownable;
use Tests\App\Traits\CausesActivity;
use Tests\App\Traits\HasApiTokensExample;
use Tests\App\Traits\LogsActivity;

class User extends Authenticatable implements Ownable
{
    use HasFactory;
    use Notifiable;
    use HasApiTokensExample;
    use CausesActivity;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected \$fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected \$hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected \$casts = [
        'email_verified_at' => 'datetime',
    ];
}
", $newUserModel);
    }

    public function testWeCanAddListenersToTheEventServiceProvider()
    {
        $updatedEventServiceProvider = LaravelProgrammingStyle::for(EventServiceProvider::class)
            ->addListenerToEvent(CustomNewEvent::class, LogAuthenticatedUserListener::class)
            ->toFile();

        $this->assertSame("<?php

namespace Tests\App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Tests\App\CustomNewEvent;
use Tests\App\Listeners\LogAuthenticatedUserListener;

class EventServiceProvider extends ServiceProvider
{
    protected \$listen = [
        CustomNewEvent::class => [
            LogAuthenticatedUserListener::class.'@handle', // code: this is an autogenerated line
        ],
        SocialiteWasCalled::class => [
            //
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
", $updatedEventServiceProvider);
    }
}
