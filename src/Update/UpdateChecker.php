<?php
/**
 * Shopeame - PrestaShop connector for shopea.me
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    FacturaCity SL
 * @copyright 2025-2026 Carlos García Gómez
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Update;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Shopeame\Form\ConfigFormManager;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Consulta la última versión del módulo publicada en las releases de GitHub.
 *
 * El módulo no está en PrestaShop Addons: se descarga del repositorio público de GitHub. La acción «Crear Release»
 * adjunta a cada release un info.json con la versión, el zip de esa etiqueta y las versiones de PrestaShop y PHP
 * compatibles. Shopeame::hookActionListModules() se lo pasa al gestor de módulos, que muestra entonces el botón
 * «Actualizar» y descarga e instala el zip como con cualquier módulo, y la pantalla de Shopeame muestra el aviso.
 *
 * El resultado se guarda en la configuración durante CACHE_TTL segundos, porque el gestor de módulos ejecuta el hook
 * cada vez que se abre y no puede esperar a GitHub en cada carga.
 */
final class UpdateChecker
{
    /** Repositorio público del módulo en GitHub. */
    public const REPOSITORY_URL = 'https://github.com/FacturaScripts/shopeame-prestashop';

    /** Nombre del zip de cada release: PrestaShop saca el nombre del módulo del nombre del archivo. */
    public const ZIP_NAME = 'shopeame.zip';

    /** Segundos durante los que se reutiliza la última consulta (12 horas, como WordPress). */
    public const CACHE_TTL = 43200;

    /**
     * URL del info.json de la última release.
     *
     * Se puede sobrescribir con la constante SHOPEAME_UPDATE_INFO_URL en config/defines_custom.inc.php para probar
     * las actualizaciones con un info.json local.
     *
     * @return string
     */
    public static function infoUrl(): string
    {
        return defined('SHOPEAME_UPDATE_INFO_URL')
            ? (string) SHOPEAME_UPDATE_INFO_URL
            : self::REPOSITORY_URL . '/releases/latest/download/info.json';
    }

    /**
     * Devuelve la última versión publicada si es mayor que la instalada y compatible con esta tienda.
     *
     * @param string $installedVersion versión instalada del módulo
     * @param bool $force true para consultar GitHub aunque la última consulta sea reciente
     *
     * @return array{version: string, download_url: string, details_url: string, changelog: string[]}|null
     */
    public static function getAvailableUpdate(string $installedVersion, bool $force = false): ?array
    {
        $info = self::getLatest($force);
        if (null === $info || !version_compare($info['version'], $installedVersion, '>')) {
            return null;
        }

        return self::isCompatible($info) ? $info : null;
    }

    /**
     * Devuelve el último info.json conocido, consultándolo de nuevo si la caché ha caducado.
     *
     * Si la consulta falla se conserva el anterior y no se vuelve a intentar hasta que caduque, para que un GitHub caído
     * no retrase cada carga del gestor de módulos.
     *
     * @param bool $force true para ignorar la caché
     *
     * @return array{version: string, download_url: string, details_url: string, changelog: string[], ps_min: string, ps_max: string, php_min: string}|null
     */
    public static function getLatest(bool $force = false): ?array
    {
        $checkedAt = (int) \Configuration::getGlobalValue(ConfigFormManager::UPDATE_CHECKED_KEY);
        if ($force || $checkedAt + self::CACHE_TTL < time()) {
            self::refresh();
        }

        $cached = json_decode((string) \Configuration::getGlobalValue(ConfigFormManager::UPDATE_INFO_KEY), true);

        return is_array($cached) ? self::normalize($cached) : null;
    }

    /**
     * Consulta GitHub en ese momento y guarda el resultado si es válido.
     *
     * Siempre apunta la hora de la consulta, también si falla, para no repetirla hasta que caduque la caché.
     *
     * @return bool false si GitHub no respondió o el info.json no es válido (se conserva el anterior)
     */
    public static function refresh(): bool
    {
        \Configuration::updateGlobalValue(ConfigFormManager::UPDATE_CHECKED_KEY, time());
        $fetched = self::fetch();
        if (null === $fetched) {
            return false;
        }

        // JSON_HEX_TAG: Configuration quita las etiquetas HTML de los valores al guardarlos
        \Configuration::updateGlobalValue(
            ConfigFormManager::UPDATE_INFO_KEY,
            (string) json_encode($fetched, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES)
        );

        return true;
    }

    /**
     * Descarga y valida el info.json de la última release.
     *
     * @return array|null null si GitHub no responde o el archivo no es válido
     */
    private static function fetch(): ?array
    {
        try {
            // HttpClient sigue las redirecciones de GitHub al almacén de las descargas; los fallos de red y TLS
            // afloran al leer la respuesta, así que todo va dentro del try
            $response = HttpClient::create()->request('GET', self::infoUrl(), [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 5,
                'max_duration' => 8,
            ]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $data = json_decode($response->getContent(false), true);
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog('Shopeame: the update information could not be downloaded: ' . $e->getMessage(), 2);

            return null;
        }

        return is_array($data) ? self::normalize($data) : null;
    }

    /**
     * Valida el contenido del info.json y lo reduce a los campos que usa el módulo.
     *
     * Fuera de las pruebas (sin SHOPEAME_UPDATE_INFO_URL), el zip tiene que ser una descarga de las releases de nuestro
     * repositorio y llamarse shopeame.zip: así nunca se instala un paquete de otro sitio.
     *
     * @param array $data contenido decodificado del info.json
     *
     * @return array|null
     */
    private static function normalize(array $data): ?array
    {
        $version = $data['version'] ?? null;
        $downloadUrl = $data['download_url'] ?? null;
        if (!is_string($version) || !is_string($downloadUrl) || 1 !== preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return null;
        }

        $validUrl = defined('SHOPEAME_UPDATE_INFO_URL')
            ? false !== filter_var($downloadUrl, FILTER_VALIDATE_URL)
            : str_starts_with($downloadUrl, self::REPOSITORY_URL . '/releases/download/');
        if (!$validUrl || !str_ends_with($downloadUrl, '/' . self::ZIP_NAME)) {
            return null;
        }

        $changelog = isset($data['changelog']) && is_array($data['changelog'])
            ? array_values(array_filter($data['changelog'], 'is_string'))
            : [];

        return [
            'version' => $version,
            'download_url' => $downloadUrl,
            'details_url' => self::string($data, 'details_url', self::REPOSITORY_URL . '/releases'),
            'changelog' => $changelog,
            'ps_min' => self::string($data, 'ps_min'),
            'ps_max' => self::string($data, 'ps_max'),
            'php_min' => self::string($data, 'php_min'),
        ];
    }

    /**
     * Comprueba que la versión publicada admite la versión de PrestaShop y de PHP de esta tienda.
     *
     * @param array $info info.json normalizado
     *
     * @return bool
     */
    private static function isCompatible(array $info): bool
    {
        if ($info['ps_min'] !== '' && version_compare(_PS_VERSION_, $info['ps_min'], '<')) {
            return false;
        }
        if ($info['ps_max'] !== '' && version_compare(_PS_VERSION_, $info['ps_max'], '>')) {
            return false;
        }

        return $info['php_min'] === '' || version_compare(PHP_VERSION, $info['php_min'], '>=');
    }

    /**
     * Devuelve un campo de texto del info.json o el valor por defecto si no existe.
     *
     * @param array $data contenido del info.json
     * @param string $key campo que se quiere leer
     * @param string $default valor si el campo falta o no es texto
     *
     * @return string
     */
    private static function string(array $data, string $key, string $default = ''): string
    {
        return isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '' ? $data[$key] : $default;
    }
}
