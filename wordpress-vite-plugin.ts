import fs from 'fs'
import {AddressInfo} from 'net'
import path from 'path'
import colors from 'picocolors'
import {
    ConfigEnv,
    loadEnv,
    Plugin,
    ResolvedConfig,
    SSROptions,
    UserConfig
} from 'vite'

interface PluginConfig {
    /**
     * The path or paths of the entry points to compile.
     */
    input: string|string[]

    /**
     * Wordpress's public directory.
     *
     * @default 'public'
     */
    publicDirectory?: string

    /**
     * The public subdirectory where compiled assets should be written.
     *
     * @default 'build'
     */
    buildDirectory?: string

    /**
     * The path to the "hot" file.
     *
     * @default `${publicDirectory}/hot`
     */
    hotFile?: string

    /**
     * The path of the SSR entry point.
     */
    ssr?: string|string[]

    /**
     * The directory where the SSR bundle should be written.
     *
     * @default 'bootstrap/ssr'
     */
    ssrOutputDirectory?: string

    /**
     * Transform the code while serving.
     */
    transformOnServe?: (code: string, url: DevServerUrl) => string,
}

interface WordpressPlugin extends Plugin {
    config: (config: UserConfig, env: ConfigEnv) => UserConfig
}

type DevServerUrl = `${'http'|'https'}://${string}:${number}`

let exitHandlersBound = false

/**
 * Wordpress plugin for Vite.
 *
 * @param config - A config object or relative path(s) of the scripts to be compiled.
 */
export default function wordpress(config: string|string[]|PluginConfig): [WordpressPlugin, ...Plugin[]]  {
    const pluginConfig = resolvePluginConfig(config)

    return [
        resolveWordpressPlugin(pluginConfig),
    ];
}

/**
 * Resolve the Wordpress Plugin configuration.
 */
function resolveWordpressPlugin(pluginConfig: Required<PluginConfig>): WordpressPlugin {
    let viteDevServerUrl: DevServerUrl
    let resolvedConfig: ResolvedConfig

    const defaultAliases: Record<string, string> = {
        '@': '/resources/js',
    };

    return {
        name: 'wordpress',
        enforce: 'post',
        config: (userConfig, { command, mode }) => {
            const ssr = !! userConfig.build?.ssr
            const env = loadEnv(mode, userConfig.envDir || process.cwd(), '')
            const assetUrl = env.ASSET_URL ?? ''
            const serverConfig = command === 'serve'
                ? resolveEnvironmentServerConfig(env)
                : undefined

            return {
                base: userConfig.base ?? (command === 'build' ? resolveBase(pluginConfig, assetUrl) : ''),
                publicDir: userConfig.publicDir ?? false,
                build: {
                    manifest: userConfig.build?.manifest ?? !ssr,
                    outDir: userConfig.build?.outDir ?? resolveOutDir(pluginConfig, ssr),
                    rollupOptions: {
                        input: userConfig.build?.rollupOptions?.input ?? resolveInput(pluginConfig, ssr)
                    },
                    assetsInlineLimit: userConfig.build?.assetsInlineLimit ?? 0,
                },
                server: {
                    origin: userConfig.server?.origin ?? '__wordpress_vite_placeholder__',
                    ...(serverConfig ? {
                        host: userConfig.server?.host ?? serverConfig.host,
                        hmr: userConfig.server?.hmr === false ? false : {
                            ...serverConfig.hmr,
                            ...(userConfig.server?.hmr === true ? {} : userConfig.server?.hmr),
                        },
                        https: userConfig.server?.https === false ? false : {
                            ...serverConfig.https,
                            ...(userConfig.server?.https === true ? {} : userConfig.server?.https),
                        },
                    } : undefined),
                },
                resolve: {
                    alias: Array.isArray(userConfig.resolve?.alias)
                        ? [
                            ...userConfig.resolve?.alias ?? [],
                            ...Object.keys(defaultAliases).map(alias => ({
                                find: alias,
                                replacement: defaultAliases[alias]
                            }))
                        ]
                        : {
                            ...defaultAliases,
                            ...userConfig.resolve?.alias,
                        }
                },
                ssr: {
                    noExternal: noExternalInertiaHelpers(userConfig),
                },
            }
        },
        configResolved(config) {
            resolvedConfig = config
        },
        transform(code) {
            if (resolvedConfig.command === 'serve') {
                code = code.replace(/__wordpress_vite_placeholder__/g, viteDevServerUrl)

                return pluginConfig.transformOnServe(code, viteDevServerUrl)
            }
        },
        configureServer(server) {
            const envDir = resolvedConfig.envDir || process.cwd()
            const appUrl = loadEnv(resolvedConfig.mode, envDir, 'WP_HOME').WP_HOME ?? 'undefined'

            server.httpServer?.once('listening', () => {
                const address = server.httpServer?.address()

                const isAddressInfo = (x: string|AddressInfo|null|undefined): x is AddressInfo => typeof x === 'object'
                if (isAddressInfo(address)) {
                    viteDevServerUrl = resolveDevServerUrl(address, server.config)
                    fs.writeFileSync(pluginConfig.hotFile, viteDevServerUrl)

                    setTimeout(() => {
                        server.config.logger.info(`\n  ${colors.red(`${colors.bold('WORDPRESS')} ${wordpressVersion()}`)}  ${colors.dim('plugin')} ${colors.bold(`v${pluginVersion()}`)}`)
                        server.config.logger.info('')
                        server.config.logger.info(`  ${colors.green('➜')}  ${colors.bold('WP_HOME')}: ${colors.cyan(appUrl.replace(/:(\d+)/, (_, port) => `:${colors.bold(port)}`))}`)
                    }, 100)
                }
            })

            if (! exitHandlersBound) {
                const clean = () => {
                    if (fs.existsSync(pluginConfig.hotFile)) {
                        fs.rmSync(pluginConfig.hotFile)
                    }
                }

                process.on('exit', clean)
                process.on('SIGINT', process.exit)
                process.on('SIGTERM', process.exit)
                process.on('SIGHUP', process.exit)

                exitHandlersBound = true
            }
        }
    }
}

/**
 * The version of Wordpress being run.
 */
function wordpressVersion(): string {
    try {
        const composer = JSON.parse(fs.readFileSync('composer.lock').toString())

        return composer.packages?.find((composerPackage: {name: string}) => composerPackage.name === 'roots/wordpress')?.version ?? ''
    } catch {
        return ''
    }
}

/**
 * The version of the Wordpress Vite plugin being run.
 */
function pluginVersion(): string {
    try {
        return JSON.parse(fs.readFileSync(path.join(__dirname, './package.json')).toString())?.version
    } catch {
        return ''
    }
}

/**
 * Convert the users configuration into a standard structure with defaults.
 */
function resolvePluginConfig(config: string|string[]|PluginConfig): Required<PluginConfig> {
    if (typeof config === 'undefined') {
        throw new Error('wordpress-vite-plugin: missing configuration.')
    }

    if (typeof config === 'string' || Array.isArray(config)) {
        config = { input: config, ssr: config }
    }

    if (typeof config.input === 'undefined') {
        throw new Error('wordpress-vite-plugin: missing configuration for "input".')
    }

    if (typeof config.publicDirectory === 'string') {
        config.publicDirectory = config.publicDirectory.trim().replace(/^\/+/, '')

        if (config.publicDirectory === '') {
            throw new Error('wordpress-vite-plugin: publicDirectory must be a subdirectory. E.g. \'public\'.')
        }
    }

    if (typeof config.buildDirectory === 'string') {
        config.buildDirectory = config.buildDirectory.trim().replace(/^\/+/, '').replace(/\/+$/, '')

        if (config.buildDirectory === '') {
            throw new Error('wordpress-vite-plugin: buildDirectory must be a subdirectory. E.g. \'build\'.')
        }
    }

    if (typeof config.ssrOutputDirectory === 'string') {
        config.ssrOutputDirectory = config.ssrOutputDirectory.trim().replace(/^\/+/, '').replace(/\/+$/, '')
    }

    return {
        input: config.input,
        publicDirectory: config.publicDirectory ?? 'public',
        buildDirectory: config.buildDirectory ?? 'build',
        ssr: config.ssr ?? config.input,
        ssrOutputDirectory: config.ssrOutputDirectory ?? 'bootstrap/ssr',
        hotFile: config.hotFile ?? path.join((config.publicDirectory ?? 'public'), 'hot'),
        transformOnServe: config.transformOnServe ?? ((code) => code),
    }
}

/**
 * Resolve the Vite base option from the configuration.
 */
function resolveBase(config: Required<PluginConfig>, assetUrl: string): string {
    return assetUrl + (! assetUrl.endsWith('/') ? '/' : '') + config.buildDirectory + '/'
}

/**
 * Resolve the Vite input path from the configuration.
 */
function resolveInput(config: Required<PluginConfig>, ssr: boolean): string|string[]|undefined {
    if (ssr) {
        return config.ssr
    }

    return config.input
}

/**
 * Resolve the Vite outDir path from the configuration.
 */
function resolveOutDir(config: Required<PluginConfig>, ssr: boolean): string|undefined {
    if (ssr) {
        return config.ssrOutputDirectory
    }

    return path.join(config.publicDirectory, config.buildDirectory)
}

/**
 * Resolve the dev server URL from the server address and configuration.
 */
function resolveDevServerUrl(address: AddressInfo, config: ResolvedConfig): DevServerUrl {
    const configHmrProtocol = typeof config.server.hmr === 'object' ? config.server.hmr.protocol : null
    const clientProtocol = configHmrProtocol ? (configHmrProtocol === 'wss' ? 'https' : 'http') : null
    const serverProtocol = config.server.https ? 'https' : 'http'
    const protocol = clientProtocol ?? serverProtocol

    const configHmrHost = typeof config.server.hmr === 'object' ? config.server.hmr.host : null
    const configHost = typeof config.server.host === 'string' ? config.server.host : null
    const serverAddress = isIpv6(address) ? `[${address.address}]` : address.address
    const host = configHmrHost ?? configHost ?? serverAddress

    const configHmrClientPort = typeof config.server.hmr === 'object' ? config.server.hmr.clientPort : null
    const port = configHmrClientPort ?? address.port

    return `${protocol}://${host}:${port}`
}

function isIpv6(address: AddressInfo): boolean {
    return address.family === 'IPv6'
        // In node >=18.0 <18.4 this was an integer value. This was changed in a minor version.
        // eslint-disable-next-line @typescript-eslint/ban-ts-comment
        // @ts-ignore-next-line
        || address.family === 6;
}

/**
 * Add the Inertia helpers to the list of SSR dependencies that aren't externalized.
 *
 * @see https://vitejs.dev/guide/ssr.html#ssr-externals
 */
function noExternalInertiaHelpers(config: UserConfig): true|Array<string|RegExp> {
    /* eslint-disable-next-line @typescript-eslint/ban-ts-comment */
    /* @ts-ignore */
    const userNoExternal = (config.ssr as SSROptions|undefined)?.noExternal
    const pluginNoExternal = ['wordpress-vite-plugin']

    if (userNoExternal === true) {
        return true
    }

    if (typeof userNoExternal === 'undefined') {
        return pluginNoExternal
    }

    return [
        ...(Array.isArray(userNoExternal) ? userNoExternal : [userNoExternal]),
        ...pluginNoExternal,
    ]
}

/**
 * Resolve the server config from the environment.
 */
function resolveEnvironmentServerConfig(env: Record<string, string>): {
    hmr?: { host: string }
    host?: string,
    https?: { cert: Buffer, key: Buffer }
}|undefined {
    if (! env.VITE_DEV_SERVER_KEY && ! env.VITE_DEV_SERVER_CERT) {
        return
    }

    if (! fs.existsSync(env.VITE_DEV_SERVER_KEY) || ! fs.existsSync(env.VITE_DEV_SERVER_CERT)) {
        throw Error(`Unable to find the certificate files specified in your environment. Ensure you have correctly configured VITE_DEV_SERVER_KEY: [${env.VITE_DEV_SERVER_KEY}] and VITE_DEV_SERVER_CERT: [${env.VITE_DEV_SERVER_CERT}].`)
    }

    const host = resolveHostFromEnv(env)

    if (! host) {
        throw Error(`Unable to determine the host from the environment's WP_HOME: [${env.WP_HOME}].`)
    }

    return {
        hmr: { host },
        host,
        https: {
            key: fs.readFileSync(env.VITE_DEV_SERVER_KEY),
            cert: fs.readFileSync(env.VITE_DEV_SERVER_CERT),
        },
    }
}

/**
 * Resolve the host name from the environment.
 */
function resolveHostFromEnv(env: Record<string, string>): string|undefined
{
    try {
        return new URL(env.WP_HOME).host
    } catch {
        return
    }
}
