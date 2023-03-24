<?php
/**
 * Vite integration for WordPress
 *
 * @package ViteForWp
 */

declare(strict_types=1);

namespace WebID\Vite;

use Exception;

const VITE_CLIENT_SCRIPT_HANDLE = 'vite-client';

class WordpressVitePlugin
{
    private array $options;
    private string $rootDirectory;
    private bool $hot = false;
    private string $url;
    private array $manifest = [];

    public function __construct(array $options)
    {
        $this->parseOptions($options);
        $this->rootDirectory = str_replace($this->options['publicDirectory'], '', WP_CONTENT_DIR);
    }

    /**
     * Get manifest data
     *
     * @throws Exception Exception is thrown when the file doesn't exist, unreadble, or contains invalid data.
     *
     */
    protected function getManifest(): void
    {
        if (is_readable($this->rootDirectory . $this->options['hotFile'])) {
            $this->hot = true;
            return;
        }

        $manifestDir = sprintf('%s/%s/%s', $this->rootDirectory, $this->options['publicDirectory'], $this->options['buildDirectory']);
        $manifestPath = sprintf('%s/manifest.json', $manifestDir);

        if (!is_readable($manifestPath)) {
            throw new Exception(sprintf('[Vite] No manifest found in %s.', $manifestDir));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $manifest_content = file_get_contents($manifestPath);


        if (!$manifest_content) {
            throw new Exception(sprintf('[Vite] Failed to read manifest %s.', $manifestPath));
        }

        $manifest = json_decode($manifest_content, true);

        if (json_last_error()) {
            throw new Exception(sprintf('[Vite] Manifest %s contains invalid data.', $manifestPath));
        }

        $this->manifest = apply_filters('wordpress_vite_plugin__manifest', $manifest);
    }

    /**
     * Filter script tag
     *
     * This creates a protected function to be used as callback for the `script_loader` filter
     * which adds `type="module"` attribute to the script tag.
     *
     * @param string $handle Script handle.
     *
     * @return void
     *
     */
    protected function filterScriptTag(string $handle): void
    {
        add_filter('script_loader_tag', fn(...$args) => $this->setScriptTypeAttribute($handle, ...$args), 10, 3);
    }

    /**
     * Add `type="module"` to a script tag
     *
     * @param string $target_handle Handle of the script being targeted by the filter callback.
     * @param string $tag Original script tag.
     * @param string $handle Handle of the script that's currently being filtered.
     *
     * @return string Script tag with attribute `type="module"` added.
     *
     */
    protected function setScriptTypeAttribute(string $target_handle, string $tag, string $handle): string
    {
        if ($target_handle !== $handle) {
            return $tag;
        }

        $attribute = 'type="module"';
        $script_type_regex = '/type=(["\'])([\w\/]+)(["\'])/';

        if (preg_match($script_type_regex, $tag)) {
            // Pre-HTML5.
            $tag = preg_replace($script_type_regex, $attribute, $tag);
        } else {
            $pattern = $handle === VITE_CLIENT_SCRIPT_HANDLE
                ? '#(<script)(.*)#'
                : '#(<script)(.*></script>)#';
            $tag = preg_replace($pattern, sprintf('$1 %s$2', $attribute), $tag);
        }

        return $tag;
    }

    /**
     * Generate development asset src
     *
     * @param string $entry Asset entry name.
     *
     * @return string
     *
     */
    protected function hotAsset(string $entry): string
    {
        return sprintf('%s/%s', untrailingslashit($this->url), trim($entry));
    }

    /**
     * Register vite client script
     *
     * @return void
     *
     */
    protected function registerViteClientScript(): void
    {
        wp_register_script(VITE_CLIENT_SCRIPT_HANDLE, $this->hotAsset('@vite/client'), [], null);
        $this->filterScriptTag(VITE_CLIENT_SCRIPT_HANDLE);
    }

    /**
     * Register react refresh script preamble
     *
     * @return void
     *
     */
    protected function registerReactRefreshScriptPreamble(): void
    {
        $script = sprintf(
            <<< EOS
                import RefreshRuntime from '%s'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.\$RefreshReg$ = () => {}
                window.\$RefreshSig$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            EOS,
            $this->hotAsset('@react-refresh')
        );

        wp_add_inline_script(VITE_CLIENT_SCRIPT_HANDLE, $script);
    }

    /**
     * Load development asset
     *
     * @return array|null Array containing registered scripts or NULL if the none was registered.
     *
     */
    protected function loadDevelopmentAsset(): ?array
    {
        $this->registerViteClientScript();

        if ($this->options['reactRefresh']) {
            $this->registerReactRefreshScriptPreamble();
        }

        $src = $this->hotAsset($this->options['input']);

        $this->filterScriptTag($this->options['handle']);

        // This is a development script, browsers shouldn't cache it.
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        if (!wp_register_script($this->options['handle'], $src, [], null)) {
            return null;
        }

        $assets = [
            'scripts' => [VITE_CLIENT_SCRIPT_HANDLE, $this->options['handle']],
            'styles' => [],
        ];

        /**
         * Filter registered development assets
         *
         * @param array $assets Registered assets.
         * @param object $manifest Manifest object.
         * @param array $options Enqueue options.
         */
        return apply_filters('wordpress_vite_plugin__development_assets', $assets, $this->manifest, $this->options);
    }

    /**
     * Load build asset
     *
     * @return array|null Array containing registered scripts & styles or NULL if there was an error.
     *
     */
    protected function loadBuildAsset(): ?array
    {
        if (!isset($this->manifest[$this->options['input']])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                wp_die(esc_html(sprintf('[Vite] Input %s not found.', $this->options['input'])));
            }

            return null;
        }

        $assets = [
            'scripts' => [],
            'styles' => [],
        ];

        $item = $this->manifest[$this->options['input']];
        $src = sprintf('%s/%s', $this->url, $item['file']);

        $this->filterScriptTag($this->options['handle']);

        if (wp_register_script($this->options['handle'], $src, [], null)) {
            $assets['scripts'][] = $this->options['handle'];
        }

        if (!empty($item['css'])) {
            foreach ($item['css'] as $index => $cssFilePath) {
                $style_handle = "{$this->options['handle']}-{$index}";
                if (wp_register_style($style_handle, "{$this->url}/{$cssFilePath}", [], null)) {
                    $assets['styles'][] = $style_handle;
                }
            }
        }

        /**
         * Filter registered build assets
         *
         * @param array $assets Registered assets.
         * @param object $manifest Manifest object.
         * @param array $options Enqueue options.
         */
        return apply_filters('wordpress_vite_plugin__build_assets', $assets, $this->manifest, $this->options);
    }

    /**
     * Parse register/enqueue options
     *
     * @param array $options Array of options.
     *
     */
    protected function parseOptions(array $options): void
    {
        $defaults = [
            'input' => null,
            'publicDirectory' => 'public',
            'buildDirectory' => 'build',
            'reactRefresh' => true,
            'handle' => 'wordpress-vite-plugin',
        ];

        $parsed = wp_parse_args($options, $defaults);
        $parsed['hotFile'] = $options['hotFile'] ?? sprintf('%s/hot', $parsed['publicDirectory']);

        $this->options = $parsed;
    }

    /**
     * Prepare asset url
     *
     */
    protected function prepareAssetUrl(): void
    {
        if ($this->hot) {
            $this->url = file_get_contents($this->rootDirectory . $this->options['hotFile']);
        } else {
            $this->url = sprintf('%s/%s', content_url(), $this->options['buildDirectory']);
        }
    }

    /**
     * Register asset
     *
     * @return array|null
     * @see loadDevelopmentAsset
     * @see loadBuildAsset
     *
     */
    public function registerAsset(): ?array
    {
        try {
            $this->getManifest();
            $this->prepareAssetUrl();
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                wp_die(esc_html($e->getMessage()));
            }
            return null;
        }

        return $this->hot
            ? $this->loadDevelopmentAsset()
            : $this->loadBuildAsset();
    }
}

/**
 * Enqueue asset
 *
 * @param array $options Enqueue options.
 *
 * @return bool
 *
 * @see registerAsset
 *
 */
function enqueue_asset(array $options): bool
{
    $plugin = new WordpressVitePlugin($options);
    $assets = $plugin->registerAsset();

    if (is_null($assets)) {
        return false;
    }

    $map = [
        'scripts' => 'wp_enqueue_script',
        'styles' => 'wp_enqueue_style',
    ];

    foreach ($assets as $group => $handles) {
        $func = $map[$group];

        foreach ($handles as $handle) {
            $func($handle);
        }
    }

    return true;
}
