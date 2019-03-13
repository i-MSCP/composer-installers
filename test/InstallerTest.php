<?php /** @noinspection PhpDocSignatureInspection PhpUnhandledExceptionInspection */

namespace iMSCPTest\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Util\Filesystem;
use iMSCP\Composer\Installer;

class InstallerTest extends TestCase
{
    /** @var \Composer\Composer */
    private $composer;

    /** @var Config */
    private $config;

    /** @var string */
    private $vendorDir;

    /** @var string */
    private $binDir;

    /** @var \Composer\Downloader\DownloadManager */
    private $dm;

    /** @var \Composer\Repository\InstalledRepositoryInterface */
    private $repository;

    /** @var \Composer\IO\IOInterface */
    private $io;

    /** @var Filesystem */
    private $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem;
        $this->composer = new Composer();
        $this->config = new Config();
        $this->composer->setConfig($this->config);
        $this->vendorDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'frontend-test-vendor';
        $this->ensureDirectoryExistsAndClear($this->vendorDir);
        $this->binDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'frontend-test-bin';
        $this->ensureDirectoryExistsAndClear($this->binDir);
        $this->config->merge([
            'config' => [
                'vendor-dir' => $this->vendorDir,
                'bin-dir'    => $this->binDir,
            ],
        ]);
        $this->dm = $this->getMockBuilder('Composer\Downloader\DownloadManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->composer->setDownloadManager($this->dm);
        $this->repository = $this->createMock('Composer\Repository\InstalledRepositoryInterface');
        $this->io = $this->createMock('Composer\IO\IOInterface');
        $consumerPackage = new RootPackage('imscp/frontend', '1.0.0', '1.0.0');
        $this->composer->setPackage($consumerPackage);
    }

    protected function tearDown(): void
    {
        $this->fs->removeDirectory($this->vendorDir);
        $this->fs->removeDirectory($this->binDir);
    }

    /**
     * dataForTestSupport
     */
    public function dataForTestSupport()
    {
        return [
            ['imscp-tool', true],
            ['imscp-plugin', true],
            ['roundcube-plugin', true],
            ['imscp-foo', false],
            ['foo-bar', false],
        ];
    }

    /**
     * testSupports
     *
     * @dataProvider dataForTestSupport
     * @return void
     */
    public function testSupports($type, $expected)
    {
        $installer = new Installer($this->io, $this->composer);
        $this->assertSame($expected, $installer->supports($type), sprintf('Failed to show support for %s', $type));
    }

    /**
     * dataFormTestInstallPath
     */
    public function dataForTestInstallPath()
    {
        return [
            ['imscp-plugin', 'plugins/phpswitcher/', 'imscp/phpswitcher'],
            ['imscp-plugin', 'plugins/letsencrypt/', 'imscp/letsencrypt'],
            ['imscp-theme', 'theme/foo/', 'imscp/foo'],
            ['imscp-theme', 'theme/bar/', 'imscp/bar'],
            ['imscp-tool', 'public/tools/phpmyadmin/', 'imscp/phpmyadmin'],
            ['imscp-tool', 'public/tools/roundcube/', 'imscp/roundcube'],
            ['roundcube-plugin', 'plugins/bar/', 'foo/bar'],
            ['roundcube-plugin', 'plugins/foo/', 'foo/foo'],
        ];
    }

    /**
     * testInstallPath
     *
     * @dataProvider dataForTestInstallPath
     */
    public function testInstallPath($type, $path, $name, $version = '1.0.0')
    {
        $installer = new Installer($this->io, $this->composer);
        $package = new Package($name, $version, $version);
        $package->setType($type);
        $result = $installer->getInstallPath($package);
        $this->assertEquals($path, $result);
    }

    /**
     * testCustomInstallPath
     */
    public function testCustomInstallPath()
    {
        $installer = new Installer($this->io, $this->composer);
        $package = new Package('imscp/roundcube', '1.0.0', '1.0.0');
        $package->setType('imscp-tool');
        $this->composer->getPackage()->setExtra([
            'installer-paths' => [
                'my/custom/path/{$name}/' => [
                    'imscp/foo',
                    'imscp/roundcube',
                    'imscp/bar',
                ],
            ],
        ]);
        $result = $installer->getInstallPath($package);
        $this->assertEquals('my/custom/path/roundcube/', $result);
    }

    /**
     * testCustomInstallerName
     */
    public function testCustomInstallerName()
    {
        $installer = new Installer($this->io, $this->composer);
        $package = new Package('imscp/roundcube-tool', '1.0.0', '1.0.0');
        $package->setType('imscp-tool');
        $package->setExtra([
            'installer-name' => 'webmail',
        ]);

        $result = $installer->getInstallPath($package);
        $this->assertEquals('public/tools/webmail/', $result);
    }

    /**
     * testCustomTypePath
     */
    public function testCustomTypePath()
    {
        $installer = new Installer($this->io, $this->composer);
        $package = new Package('imscp/roundcube', '1.0.0', '1.0.0');
        $package->setType('imscp-tool');
        $this->composer
            ->getPackage()
            ->setExtra([
                'installer-paths' => [
                    'my/custom/path/{$name}/' => [
                        'type:imscp-tool'
                    ],
                ],
            ]);

        $result = $installer->getInstallPath($package);
        $this->assertEquals('my/custom/path/roundcube/', $result);
    }

    /**
     * testVendorPath
     */
    public function testVendorPath()
    {
        $installer = new Installer($this->io, $this->composer);
        $package = new Package('imscp/roundcube', '1.0.0', '1.0.0');
        $package->setType('imscp-tool');
        $this->composer
            ->getPackage()
            ->setExtra([
                'installer-paths' => [
                    'tools/custom/{$name}/' => [
                        'vendor:imscp'
                    ],
                ],
            ]);
        $result = $installer->getInstallPath($package);
        $this->assertEquals('tools/custom/roundcube/', $result);
    }

    /**
     * testNoVendorName
     */
    public function testNoVendorName()
    {
        $installer = new Installer($this->io, $this->composer);
        $package = new Package('roundcube', '1.0.0', '1.0.0');
        $package->setType('imscp-tool');
        $result = $installer->getInstallPath($package);
        $this->assertEquals('public/tools/roundcube/', $result);
    }

    /**
     * testUninstallAndDeletePackageFromLocalRepo
     */
    public function testUninstallAndDeletePackageFromLocalRepo()
    {
        $package = new Package('foo', '1.0.0', '1.0.0');

        //$installer = $this->getMock('iMSCP\Composer\Installers\Installer', array('getInstallPath'), array($this->io, $this->composer));

        /** @var  \iMSCP\Composer\Installer|\PHPUnit\Framework\MockObject\MockObject $installer */
        $installer = $this->getMockBuilder('iMSCP\Composer\Installer')
            ->setMethods(['getInstallPath'])
            ->setConstructorArgs([$this->io, $this->composer])
            ->getMock();
        $installer->expects($this->atLeastOnce())
            ->method('getInstallPath')
            ->with($package)
            ->will($this->returnValue(sys_get_temp_dir() . '/foo'));

        /** @var \Composer\Repository\InstalledRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject $repo */
        $repo = $this->createMock('Composer\Repository\InstalledRepositoryInterface');
        $repo->expects($this->once())
            ->method('hasPackage')
            ->with($package)
            ->will($this->returnValue(true));
        $repo->expects($this->once())->method('removePackage')
            ->with($package);
        $installer->uninstall($repo, $package);
    }

    /**
     * dataForTestDisabledInstallers
     *
     * @return array
     */
    public function dataForTestDisabledInstallers()
    {
        return [
            [false, 'imscp-tool', true],
            [true, 'imscp-tool', false],
            ['true', 'imscp-tool', true],
            ['all', 'imscp-tool', false],
            ['*', 'imscp-tool', false],
            ['roundcube', 'imscp-tool', true],
            ['imscp', 'roundcube-plugin', true],
            ['roundcube', 'roundcube-plugin', false],
            ['imscp', 'imscp-tool', false],
            [['imscp', 'roundcube'], 'roundcube-plugin', false],
            [['imscp', 'roundcube'], 'imscp-tool', false],
            [['roundcube', true], 'imscp-tool', false],
            [['roundcube', 'all'], 'imscp-tool', false],
            [['roundcube', '*'], 'imscp-tool', false],
            [['roundcube', 'true'], 'imscp-tool', true],
            [['imscp', 'true'], 'roundcube-plugin', true],
        ];
    }

    /**
     * testDisabledInstallers
     *
     * @dataProvider dataForTestDisabledInstallers
     */
    public function testDisabledInstallers($disabled, $type, $expected)
    {
        $this->composer
            ->getPackage()
            ->setExtra([
                'installer-disable' => $disabled,
            ]);
        $this->testSupports($type, $expected);
    }
}
