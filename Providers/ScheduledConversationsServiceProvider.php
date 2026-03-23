<?php

/**
 * Scheduled Conversations Service Provider
 *
 * Main entry point for the Scheduled Conversations module. Registers all module
 * services including views, translations, migrations, commands, and Eventy hooks.
 *
 * Key responsibilities:
 * - Registers the Artisan command: scheduledconversations:process
 * - Hooks into FreeScout's scheduler to run the command periodically
 * - Integrates with FreeScout's native settings system (Administrar > Configuración)
 * - Controls menu visibility based on user permissions
 * - Fixes sidebar mailbox switcher links for module detail views
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 */

namespace Modules\ScheduledConversations\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Modules\ScheduledConversations\Entities\ScheduledConversation;

// Module alias constant
define('SCHEDULEDCONV_MODULE', 'scheduledconversations');

class ScheduledConversationsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerTranslations();
        $this->registerCommands();
        $this->hooks();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Routes are loaded via start.php
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('scheduledconversations.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'scheduledconversations'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/scheduledconversations');
        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/scheduledconversations';
        }, \Config::get('view.paths')), [$sourcePath]), 'scheduledconversations');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Register artisan commands.
     *
     * @return void
     */
    public function registerCommands()
    {
        $this->commands([
            \Modules\ScheduledConversations\Console\ProcessScheduledConversations::class,
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        // Add module's CSS file to the application layout
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(SCHEDULEDCONV_MODULE).'/css/module.css';
            return $styles;
        });
        
        // Add module's JS file only on module pages to avoid loading on all FreeScout pages
        if (\Request::is('mailbox/*/scheduled-conversations*') || \Request::is('scheduled-conversations*')) {
            \Eventy::addFilter('javascripts', function($javascripts) {
                $javascripts[] = \Module::getPublicPath(SCHEDULEDCONV_MODULE).'/js/module.js';
                return $javascripts;
            });
        }

        // Register permission for managing scheduled conversations
        \Eventy::addFilter('user_permissions.list', function($list) {
            $list[] = ScheduledConversation::PERM_MANAGE_SCHEDULED_CONVERSATIONS;
            return $list;
        });
        
        // Define permission name displayed in user settings
        \Eventy::addFilter('user_permissions.name', function($name, $permission) {
            if ($permission != ScheduledConversation::PERM_MANAGE_SCHEDULED_CONVERSATIONS) {
                return $name;
            }
            return __('Users are allowed to manage scheduled conversations');
        }, 20, 2);

        // Add item to the mailbox settings menu
        \Eventy::addAction('mailboxes.settings.menu', function($mailbox) {
            // Show menu if user can view (has access to mailbox)
            if (ScheduledConversation::canView(null, $mailbox->id)) {
                echo \View::make('scheduledconversations::partials/settings_menu', [
                    'mailbox' => $mailbox
                ])->render();
            }
        }, 25);

        // Determine whether the user can view mailboxes menu
        \Eventy::addFilter('user.can_view_mailbox_menu', function($value, $user) {
            return $value || ScheduledConversation::canView();
        }, 20, 2);

        // Redirect user to the accessible mailbox settings route
        \Eventy::addFilter('mailbox.accessible_settings_route', function($value, $user, $mailbox) {
            if ($value) {
                return $value;
            }
            if (ScheduledConversation::canView(null, $mailbox->id)) {
                return 'scheduledconversations.index';
            } else {
                return $value;
            }
        }, 20, 3);

        // Fix sidebar menu links when navigating from module detail views (history, edit, create).
        // FreeScout uses the current route name to build mailbox switcher links in the sidebar.
        // Without this filter, clicking a mailbox in the sidebar from history/edit/create would
        // generate URLs like /scheduled-conversations/{mailbox_id}/history instead of the index.
        \Eventy::addFilter('mailboxes.menu_current_route', function($route) {
            $moduleRoutes = [
                'scheduledconversations.history',
                'scheduledconversations.edit',
                'scheduledconversations.create',
                'scheduledconversations.view',
            ];
            if (in_array($route, $moduleRoutes)) {
                return 'scheduledconversations.index';
            }
            return $route;
        });

        // Register module settings section in FreeScout's native settings system
        \Eventy::addFilter('settings.sections', function($sections) {
            if (!is_array($sections)) {
                $sections = [];
            }
            $sections['scheduledconversations'] = [
                'title' => __('Scheduled Conversations'),
                'icon'  => 'calendar',
                'order' => 600,
            ];
            return $sections;
        });

        // Provide settings data to the settings view
        \Eventy::addFilter('settings.section_settings', function($settings, $section) {
            if ($section !== 'scheduledconversations') {
                return $settings;
            }
            $settings['scheduledconversations.all_users_can_view'] = \Option::get('scheduledconversations.all_users_can_view', true);
            $settings['scheduledconversations.process_frequency'] = \Option::get('scheduledconversations.process_frequency', 5);
            return $settings;
        }, 20, 2);

        // Render the settings view
        \Eventy::addFilter('settings.view', function($view, $section) {
            if ($section !== 'scheduledconversations') {
                return $view;
            }
            return 'scheduledconversations::settings';
        }, 20, 2);

        // Save settings
        \Eventy::addFilter('settings.before_save', function($request, $section, $settings) {
            if ($section !== 'scheduledconversations') {
                return $request;
            }
            $settingsData = $request->input('settings', []);

            $allUsersCanView = isset($settingsData['scheduledconversations.all_users_can_view']) ? true : false;
            $processFrequency = (int)($settingsData['scheduledconversations.process_frequency'] ?? 5);

            if (!in_array($processFrequency, [1, 5, 15])) {
                $processFrequency = 5;
            }

            \Option::set('scheduledconversations.all_users_can_view', $allUsersCanView);
            \Option::set('scheduledconversations.process_frequency', $processFrequency);

            return $request;
        }, 20, 3);

        // Schedule background processing for scheduled conversations.
        // Frequency is read from Option (configurable via settings page) with fallback to config.
        // Note: this filter only runs when the module's ServiceProvider is loaded,
        // which only happens when the module is enabled. If the module is disabled,
        // this filter is never registered and the command is never scheduled.
        \Eventy::addFilter('schedule', function($schedule) {
            $frequency = (int)\Option::get('scheduledconversations.process_frequency',
                config('scheduledconversations.process_frequency', 5));

            if ($frequency == 1) {
                $schedule->command('scheduledconversations:process')->everyMinute();
            } elseif ($frequency == 5) {
                $schedule->command('scheduledconversations:process')->everyFiveMinutes();
            } elseif ($frequency == 15) {
                $schedule->command('scheduledconversations:process')->everyFifteenMinutes();
            } else {
                $schedule->command('scheduledconversations:process')->everyFiveMinutes();
            }

            return $schedule;
        });
    }
}
