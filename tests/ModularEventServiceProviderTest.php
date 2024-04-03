<?php

namespace InterNACHI\Modular\Tests {
	
	use App\Tests\ModularEventSeviceProviderTest\ForceEventDiscoveryProvider;
	use App\Tests\ModularEventSeviceProviderTest\InheritedEventDiscoveryServiceProvider;
	use Illuminate\Foundation\Support\Providers\EventServiceProvider;
	use Illuminate\Support\Facades\Config;
	use Illuminate\Support\Facades\Event;
	use InterNACHI\Modular\Support\Facades\Modules;
	use InterNACHI\Modular\Support\ModularEventServiceProvider;
	use InterNACHI\Modular\Tests\Concerns\WritesToAppFilesystem;
	
	class ModularEventServiceProviderTest extends TestCase
	{
		use WritesToAppFilesystem;
		
		public function test_it_discovers_event_listeners_in_laravel_11(): void
		{
			$this->requiresLaravelVersion('11.0.0');
			
			$module = Modules::module('test-module');
			
			$this->app->register(new ForceEventDiscoveryProvider($this->app));
			$this->app->register(new ModularEventServiceProvider($this->app), true);
			
			$this->assertNotEmpty(Event::getListeners($module->qualify('Events\\TestEvent')));
			
			// Also check that the events are cached correctly
			
			$this->artisan('event:cache');
			
			$cache = require $this->app->getCachedEventsPath();
			
			$this->assertArrayHasKey($module->qualify('Events\\TestEvent'), $cache[ModularEventServiceProvider::class]);
			
			$this->assertContains(
				$module->qualify('Listeners\\TestEventListener@handle'),
				$cache[ModularEventServiceProvider::class][$module->qualify('Events\\TestEvent')]
			);
			
			$this->artisan('event:clear');
		}
		
		public function test_it_discovers_event_listeners_if_it_is_set_in_config(): void
		{
			$module = $this->makeModule();
			
			$this->artisan('make:event', ['name' => 'TestEvent2', '--module' => $module->name]);
			$this->artisan('make:listener', ['name' => 'TestEvent2Listener', '--event' => $module->qualify('Events\\TestEvent2'), '--module' => $module->name]);
			
			// Because these are created after autoloading has finished, we need to manually load them
			require $module->path('src/Events/TestEvent2.php');
			require $module->path('src/Listeners/TestEvent2Listener.php');
			
			echo file_get_contents($module->path('src/Events/TestEvent2.php'))."\n";
			echo file_get_contents($module->path('src/Listeners/TestEvent2Listener.php'))."\n";
			
			Config::set('app-modules.should_discover_events', true);
			
			$this->app->register(new InheritedEventDiscoveryServiceProvider($this->app));
			$this->app->register(new ModularEventServiceProvider($this->app), true);
			
			$this->assertNotEmpty(Event::getListeners($module->qualify('Events\\TestEvent2')));
			
			// Also check that the events are cached correctly
			
			$this->artisan('event:cache');
			
			$cache = require $this->app->getCachedEventsPath();
			
			$this->assertArrayHasKey($module->qualify('Events\\TestEvent2'), $cache[ModularEventServiceProvider::class]);
			
			$this->assertContains(
				$module->qualify('Listeners\\TestEvent2Listener@handle'),
				$cache[ModularEventServiceProvider::class][$module->qualify('Events\\TestEvent2')]
			);
			
			$this->artisan('event:clear');
		}
		
		protected function getPackageProviders($app)
		{
			return array_merge([EventServiceProvider::class], parent::getPackageProviders($app));
		}
	}
}

// We need to use an "App" namespace to tell modular that this provider should be deferred to

namespace App\Tests\ModularEventSeviceProviderTest {
	
	use Illuminate\Foundation\Support\Providers\EventServiceProvider;
	
	class ForceEventDiscoveryProvider extends EventServiceProvider
	{
		public function shouldDiscoverEvents()
		{
			return true;
		}
	}
	
	class InheritedEventDiscoveryServiceProvider extends EventServiceProvider
	{
	}
}
