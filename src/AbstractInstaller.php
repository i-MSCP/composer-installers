<?php

namespace iMSCP\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

abstract class AbstractInstaller
{
    protected $locations = [];
    protected $composer;
    protected $package;
    protected $io;

    /**
     * Initializes base installer.
     *
     * @param PackageInterface $package
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(
        PackageInterface $package = NULL,
        Composer $composer = NULL,
        IOInterface $io = NULL
    ) {
        $this->composer = $composer;
        $this->package = $package;
        $this->io = $io;
    }

    /**
     * Return the install path based on package type.
     *
     * @param  PackageInterface $package
     * @param  string $installerType
     * @return string
     */
    public function getInstallPath(PackageInterface $package, $installerType = ''): string
    {
        $type = $this->package->getType();
        $prettyName = $this->package->getPrettyName();

        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

        $availableVars = $this->inflectPackageVars(compact('name', 'vendor', 'type'));
        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            $availableVars['name'] = $extra['installer-name'];
        }

        if ($this->composer->getPackage()) {
            $extra = $this->composer->getPackage()->getExtra();
            if (!empty($extra['installer-paths'])) {
                $customPath = $this->mapCustomInstallPaths($extra['installer-paths'], $prettyName, $type, $vendor);
                if ($customPath !== false) {
                    return $this->templatePath($customPath, $availableVars);
                }
            }
        }

        $packageType = substr($type, strlen($installerType) + 1);
        $locations = $this->getLocations();

        if (!isset($locations[$packageType])) {
            throw new \InvalidArgumentException(sprintf('Package type "%s" is not supported', $type));
        }

        return $this->templatePath($locations[$packageType], $availableVars);
    }

    /**
     * For an installer to override to modify the vars per installer.
     *
     * @param  array $vars
     * @return array
     */
    public function inflectPackageVars(array $vars): array
    {
        return $vars;
    }

    /**
     * Search through a passed paths array for a custom install path.
     *
     * @param  array $paths
     * @param  string $name
     * @param  string $type
     * @param  string $vendor = NULL
     * @return string
     */
    protected function mapCustomInstallPaths(array $paths, $name, $type, $vendor = NULL): string
    {
        foreach ($paths as $path => $names) {
            if (in_array($name, $names) || in_array('type:' . $type, $names) || in_array('vendor:' . $vendor, $names)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Replace vars in a path
     *
     * @param  string $path
     * @param  array $vars
     * @return string
     */
    protected function templatePath($path, array $vars = []): string
    {
        if (strpos($path, '{') !== false) {
            extract($vars);
            preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $var) {
                    $path = str_replace('{$' . $var . '}', $$var, $path);
                }
            }
        }

        return $path;
    }

    /**
     * Gets the installer's locations
     *
     * @return array
     */
    public function getLocations(): array
    {
        return $this->locations;
    }
}
