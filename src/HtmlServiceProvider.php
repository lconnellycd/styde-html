<?php

namespace Styde\Html;

use Styde\Html\CollectiveHtmlServiceProvider as ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Arr;
use Styde\Html\Access\AccessHandler;
use Styde\Html\Menu\Menu;
use Styde\Html\Menu\MenuGenerator;
use Styde\Html\Access\BasicAccessHandler;

class HtmlServiceProvider extends ServiceProvider
{
    /**
     * Array of options taken from the configuration file (config/html.php) and
     * the default package configuration.
     *
     * @var array
     */
    protected $options;

    /**
     * @var \Styde\Html\Theme
     */
    protected $theme;
     /**
     * @var AccessHandler
     */
    protected $accessHandler = null;

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../themes', 'styde.html');

        $this->publishes(
            [__DIR__.'/../themes' => base_path('resources/views/themes')], 'styde-html-themes'
        );

        $this->publishes(
            [__DIR__.'/../config.php' => config_path('html.php')], 'styde-html-config'
        );
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        parent::register();
        $this->mergeConfigFrom(__DIR__.'/../config.php', 'html');
        $this->registerHtmlBuilder();
        $this->registerAccessHandler();
        $this->registerFormBuilder();
        $this->registerFieldBuilder();
        $this->registerMenuGenerator();
    }

    /**
     * Load the configuration options from the config/html.php file.
     *
     * All the configuration options are optional, if they are not found, the
     * default configuration of this package will be used.
     */
    protected function loadConfigurationOptions()
    {
        if ( ! empty($this->options)) return;

        $this->options = $this->app->make('config')->get('html');

        $this->options['theme_values'] = Arr::get($this->options['themes'], $this->options['theme']);

        unset ($this->options['themes']);
    }

    /**
     * Instantiate and return the Theme object
     *
     * Only one theme is necessary, and the theme object will be used internally
     * by the other classes of this component. So we don't need to add it to the
     * IoC container.
     *
     * @return \Styde\Html\Theme
     */
    protected function getTheme()
    {
        if ($this->theme == null) {
            $this->theme = new Theme($this->app['view'], $this->options['theme'], $this->options['custom']);
        }

        return $this->theme;
    }

    /**
     * Register the AccessHandler implementation into the IoC Container.
     * This package provides a BasicAccessHandler.
     *
     * @return \Styde\Html\Access\AccessHandler
     */
    protected function registerAccessHandler()
    {
        $this->app->singleton('access', function ($app) {
            $guard = $app['config']->get('html.guard', null);
            $handler = new BasicAccessHandler($app['auth']->guard($guard));

            $gate = $app->make(Gate::class);
            if ($gate) {
                $handler->setGate($gate);
            }

            return $handler;
        });

        $this->app->alias('access', AccessHandler::class);
    }

    /**
     * Register the Form Builder instance.
     */
    protected function registerFormBuilder()
    {
        $this->app->singleton('form', function ($app) {
            $this->loadConfigurationOptions();

            $form = new FormBuilder(
                $app['html'],
                $app['url'],
                $app['session.store']->token(),
                $this->getTheme()
            );

            $form->novalidate(
                $app['config']->get('html.novalidate', false)
            );

            return $form->setSessionStore($app['session.store']);
        });
    }

    /**
     * Register the HTML Builder instance.singlenotsinntrntrn
     */
    protected function registerHtmlBuilder()
    {
        $this->app->singleton('html', function ($app) {
            return new HtmlBuilder($app['url'], $app['view']);
        });
    }

    /**
     * Register the Field Builder instance
     */
    protected function registerFieldBuilder()
    {
        $this->app->bind('field', function ($app) {

            $this->loadConfigurationOptions();

            $fieldBuilder = new FieldBuilder(
                $app['form'],
                $this->theme,
                $app['translator']
            );

           if ($this->options['control_access']) {
               $fieldBuilder->setAccessHandler($app[AccessHandler::class]);
           }

            $fieldBuilder->setAbbreviations($this->options['abbreviations']);

            if (isset ($this->options['theme_values']['field_classes'])) {
                $fieldBuilder->setCssClasses(
                    $this->options['theme_values']['field_classes']
                );
            }

            if (isset ($this->options['theme_values']['field_templates'])) {
                $fieldBuilder->setTemplates(
                    $this->options['theme_values']['field_templates']
                );
            }

            $fieldBuilder->setSessionStore($app['session.store']);

            return $fieldBuilder;
        });
    }

     /**
     * Register the Menu Generator instance
     */
    protected function registerMenuGenerator()
    {
        $this->app->bind('menu', function ($app) {

            $this->loadConfigurationOptions();

            $menu = new MenuGenerator(
                $app['url'],
                $app['config'],
                $this->getTheme()
            );

            if ($this->options['control_access']) {
                $menu->setAccessHandler($app[AccessHandler::class]);
            }

            if ($this->options['translate_texts']) {
                $menu->setLang($app['translator']);
            }

            return $menu;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            HtmlBuilder::class,
            FormBuilder::class,
            AccessHandler::class,
            FieldBuilder::class,
            Menu::class,
            'html',
            'form',
            'field',
            'alert',
            'menu'
        ];
    }
}
