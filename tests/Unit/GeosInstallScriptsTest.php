<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->geosDirectory = sys_get_temp_dir().'/rodnik-geos-test-'.bin2hex(random_bytes(8));
    mkdir($this->geosDirectory.'/bin', 0700, true);

    foreach (['sudo', 'apt', 'apt-get', 'brew', 'systemctl', 'service', 'pecl', 'make', 'phpenmod', 'ldconfig', 'curl', 'git', 'herd', 'install'] as $command) {
        $path = $this->geosDirectory.'/bin/'.$command;
        file_put_contents($path, <<<'BASH'
#!/bin/bash
printf '%s\n' "${0##*/} $*" >> "$GEOS_TEST_DIRECTORY/blocked-commands"
exit 97
BASH);
        chmod($path, 0700);
    }
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->geosDirectory);
});

/**
 * @param  list<string>  $arguments
 */
function runGeosInstaller(string $script, string $directory, array $arguments = [], ?string $body = null): Process
{
    $path = dirname(__DIR__, 2).'/'.$script;
    $command = $body === null
        ? ['/bin/bash', $path, ...$arguments]
        : ['/bin/bash', '-c', 'source "$1"'."\n".$body, 'geos-test', $path, ...$arguments];

    $process = new Process($command, dirname(__DIR__, 2), [
        'PATH' => $directory.'/bin:/usr/bin:/bin',
        'GEOS_TEST_DIRECTORY' => $directory,
    ], timeout: 10);
    $process->run();

    return $process;
}

function writeGeosFpmFixture(string $directory, string $output, string $errorOutput = '', int $exitCode = 0): void
{
    file_put_contents($directory.'/fpm-output', $output);
    file_put_contents($directory.'/fpm-error-output', $errorOutput);
    file_put_contents($directory.'/fpm-exit-code', (string) $exitCode);
    file_put_contents($directory.'/bin/php-fpm', <<<'BASH'
#!/bin/bash
set -e
printf '%s\n' "$@" > "$GEOS_TEST_DIRECTORY/fpm-arguments"
cat "$GEOS_TEST_DIRECTORY/fpm-output"
cat "$GEOS_TEST_DIRECTORY/fpm-error-output" >&2
touch "$GEOS_TEST_DIRECTORY/fpm-completed"
exit "$(cat "$GEOS_TEST_DIRECTORY/fpm-exit-code")"
BASH);
    chmod($directory.'/bin/php-fpm', 0700);
}

dataset('geos installers', [
    'macOS Herd' => ['install-geos-local.sh'],
    'Ubuntu server' => ['install-geos-server.sh'],
]);

test('GEOS installer help works without installing anything', function (string $script) {
    $process = runGeosInstaller($script, $this->geosDirectory, ['--help']);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getOutput())->toContain('--execute')
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
})->with('geos installers');

test('GEOS installer rejects unknown arguments before invoking external commands', function (string $script) {
    $process = runGeosInstaller($script, $this->geosDirectory, ['--invalid-option']);

    expect($process->getExitCode())->toBe(2)
        ->and($process->getOutput().$process->getErrorOutput())->toContain('Usage:')
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
})->with('geos installers');

test('local GEOS installer writes its INI idempotently and preserves unrelated extensions', function () {
    $ini = $this->geosDirectory.'/99-rodnik-geos.ini';
    $unrelated = $this->geosDirectory.'/php.ini';
    $original = "memory_limit=768M\nextension=redis.so\n";
    file_put_contents($unrelated, $original);
    $body = <<<'BASH'
write_managed_ini "$GEOS_TEST_DIRECTORY/99-rodnik-geos.ini" "$GEOS_TEST_DIRECTORY/extension directory/geos.so"
BASH;

    $first = runGeosInstaller('install-geos-local.sh', $this->geosDirectory, body: $body);
    expect($first->getExitCode())->toBe(0, $first->getErrorOutput());
    $inode = fileinode($ini);

    $second = runGeosInstaller('install-geos-local.sh', $this->geosDirectory, body: $body);
    clearstatcache(true, $ini);

    expect($second->getExitCode())->toBe(0, $second->getErrorOutput())
        ->and(file_get_contents($ini))->toBe(
            "; Managed by Rodnik install-geos-local.sh.\nextension=\"".$this->geosDirectory."/extension directory/geos.so\"\n",
        )
        ->and(fileinode($ini))->toBe($inode)
        ->and(file_get_contents($unrelated))->toBe($original)
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});

test('local GEOS installer refuses to overwrite an INI file it does not own', function () {
    $ini = $this->geosDirectory.'/99-rodnik-geos.ini';
    $original = "; maintained manually\nextension=custom-geos.so\n";
    file_put_contents($ini, $original);

    $process = runGeosInstaller('install-geos-local.sh', $this->geosDirectory, body: <<<'BASH'
write_managed_ini "$GEOS_TEST_DIRECTORY/99-rodnik-geos.ini" "$GEOS_TEST_DIRECTORY/geos.so"
BASH);

    expect($process->getExitCode())->not->toBe(0)
        ->and(file_get_contents($ini))->toBe($original)
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});

test('local GEOS installer refuses an extension already configured in a different INI', function () {
    $ini = $this->geosDirectory.'/php.ini';
    $original = "memory_limit=768M\nextension=\"/custom/geos.so\"\n";
    file_put_contents($ini, $original);

    $process = runGeosInstaller('install-geos-local.sh', $this->geosDirectory, body: <<<'BASH'
ini_dir="$GEOS_TEST_DIRECTORY"
managed_ini="$GEOS_TEST_DIRECTORY/99-rodnik-geos.ini"
php_bin="$GEOS_TEST_DIRECTORY/bin/php"
check_existing_configuration
BASH);

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getErrorOutput())->toContain('already configured')
        ->and(file_get_contents($ini))->toBe($original)
        ->and(file_exists($this->geosDirectory.'/99-rodnik-geos.ini'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});

test('GEOS installer only prints a plan unless execution is explicitly requested', function (string $script) {
    foreach ([[], ['--plan']] as $arguments) {
        $process = runGeosInstaller($script, $this->geosDirectory, $arguments);

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
            ->and($process->getOutput())->not->toBeEmpty()
            ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
    }
})->with('geos installers');

test('GEOS installer verifies source archive bytes before accepting them', function (string $script) {
    file_put_contents($this->geosDirectory.'/archive.tar.gz', 'the pinned source archive');
    file_put_contents($this->geosDirectory.'/expected-checksum', hash('sha256', 'the pinned source archive'));

    $process = runGeosInstaller($script, $this->geosDirectory, body: <<<'BASH'
sha256sum() { shasum -a 256 "$@"; }
archive_sha256=$(cat "$GEOS_TEST_DIRECTORY/expected-checksum")
verify_checksum "$GEOS_TEST_DIRECTORY/archive.tar.gz"
BASH);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();

    file_put_contents($this->geosDirectory.'/archive.tar.gz', 'a corrupted or substituted archive');

    $process = runGeosInstaller($script, $this->geosDirectory, body: <<<'BASH'
sha256sum() { shasum -a 256 "$@"; }
archive_sha256=$(cat "$GEOS_TEST_DIRECTORY/expected-checksum")
verify_checksum "$GEOS_TEST_DIRECTORY/archive.tar.gz"
touch "$GEOS_TEST_DIRECTORY/accepted-archive"
BASH);

    expect($process->getExitCode())->not->toBe(0)
        ->and(file_exists($this->geosDirectory.'/accepted-archive'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
})->with('geos installers');

test('GEOS installer aborts when an isolated candidate fails its smoke test', function (string $script) {
    file_put_contents($this->geosDirectory.'/candidate.so', 'a candidate extension');
    file_put_contents($this->geosDirectory.'/bin/php', <<<'BASH'
#!/bin/bash
printf '%s\n' "$@" > "$GEOS_TEST_DIRECTORY/php-arguments"
exit 59
BASH);
    chmod($this->geosDirectory.'/bin/php', 0700);

    $process = runGeosInstaller($script, $this->geosDirectory, body: <<<'BASH'
php_bin="$GEOS_TEST_DIRECTORY/bin/php"
smoke_test "$GEOS_TEST_DIRECTORY/candidate.so"
touch "$GEOS_TEST_DIRECTORY/accepted-candidate"
BASH);

    expect($process->getExitCode())->not->toBe(0)
        ->and(file_get_contents($this->geosDirectory.'/php-arguments'))
        ->toContain("-n\n", 'extension='.$this->geosDirectory.'/candidate.so')
        ->and(file_exists($this->geosDirectory.'/accepted-candidate'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
})->with('geos installers');

test('GEOS installer can smoke test the enabled extension using normal PHP configuration', function (string $script) {
    file_put_contents($this->geosDirectory.'/bin/php', <<<'BASH'
#!/bin/bash
printf '%s\n' "$@" > "$GEOS_TEST_DIRECTORY/php-arguments"
printf 'GEOS smoke tests passed: 1.0 / 3.12.1\n'
BASH);
    chmod($this->geosDirectory.'/bin/php', 0700);

    $process = runGeosInstaller($script, $this->geosDirectory, body: <<<'BASH'
php_bin="$GEOS_TEST_DIRECTORY/bin/php"
smoke_test
BASH);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(file_get_contents($this->geosDirectory.'/php-arguments'))->not->toContain("-n\n", 'extension=')
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
})->with('geos installers');

test('GEOS installer remains non-mutating when read from standard input', function (string $script) {
    foreach ([[], ['--plan'], ['--help']] as $arguments) {
        $process = new Process(
            ['/bin/bash', '-s', '--', ...$arguments],
            dirname(__DIR__, 2),
            [
                'PATH' => $this->geosDirectory.'/bin:/usr/bin:/bin',
                'GEOS_TEST_DIRECTORY' => $this->geosDirectory,
            ],
            file_get_contents(dirname(__DIR__, 2).'/'.$script),
            10,
        );
        $process->run();

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
            ->and($process->getOutput())->toContain('--execute')
            ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
    }
})->with('geos installers');

test('GEOS installer requires matching supported CLI and FPM runtime builds', function (string $script) {
    $info = "PHP Version => 8.5.8\nPHP API => 20250925\nPHP Extension Build => API20250925,NTS\nDebug Build => no\nThread Safety => disabled\n";
    file_put_contents($this->geosDirectory.'/php-info', $info);
    file_put_contents($this->geosDirectory.'/fpm-info', $info);
    file_put_contents($this->geosDirectory.'/bin/php', <<<'BASH'
#!/bin/bash
case "$*" in
    *PHP_MAJOR_VERSION*) printf '%s' '8.5 NTS' ;;
    *extension_dir*) printf '%s' "$GEOS_TEST_DIRECTORY/modules" ;;
    *) cat "$GEOS_TEST_DIRECTORY/php-info" ;;
esac
BASH);
    file_put_contents($this->geosDirectory.'/bin/php-fpm', <<<'BASH'
#!/bin/bash
cat "$GEOS_TEST_DIRECTORY/fpm-info"
BASH);
    chmod($this->geosDirectory.'/bin/php', 0700);
    chmod($this->geosDirectory.'/bin/php-fpm', 0700);
    $body = <<<'BASH'
php_bin="$GEOS_TEST_DIRECTORY/bin/php"
fpm_bin="$GEOS_TEST_DIRECTORY/bin/php-fpm"
extension_dir="$GEOS_TEST_DIRECTORY/modules"
validate_runtime
BASH;

    $valid = runGeosInstaller($script, $this->geosDirectory, body: $body);
    expect($valid->getExitCode())->toBe(0, $valid->getErrorOutput());

    foreach (['8.5.8' => '8.4.14', '20250925' => '20240924', 'NTS' => 'ZTS'] as $supported => $incompatible) {
        file_put_contents($this->geosDirectory.'/fpm-info', str_replace((string) $supported, $incompatible, $info));
        $invalid = runGeosInstaller($script, $this->geosDirectory, body: $body);

        expect($invalid->getExitCode())->not->toBe(0)
            ->and($invalid->getErrorOutput())->toContain('PHP')
            ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
    }
})->with('geos installers');

test('server GEOS installer creates only its CLI and FPM INI links and is idempotent', function () {
    foreach (['mods-available', 'cli/conf.d', 'fpm/conf.d', 'modules'] as $directory) {
        mkdir($this->geosDirectory.'/'.$directory, 0700, true);
    }

    $unrelated = $this->geosDirectory.'/cli/conf.d/20-redis.ini';
    $original = "extension=redis.so\n";
    file_put_contents($unrelated, $original);
    file_put_contents($this->geosDirectory.'/modules/geos.so', 'compiled extension');
    $body = <<<'BASH'
php_root="$GEOS_TEST_DIRECTORY"
ini_file="$php_root/mods-available/rodnik-geos.ini"
module_path="$php_root/modules/geos.so"
activate_extension
BASH;

    $first = runGeosInstaller('install-geos-server.sh', $this->geosDirectory, body: $body);
    expect($first->getExitCode())->toBe(0, $first->getErrorOutput());
    $ini = $this->geosDirectory.'/mods-available/rodnik-geos.ini';
    $inode = fileinode($ini);
    $contents = file_get_contents($ini);

    $second = runGeosInstaller('install-geos-server.sh', $this->geosDirectory, body: $body);
    clearstatcache(true, $ini);

    expect($second->getExitCode())->toBe(0, $second->getErrorOutput())
        ->and(file_get_contents($ini))->toBe($contents)
        ->and($contents)->toContain('extension="'.$this->geosDirectory.'/modules/geos.so"')
        ->and(fileinode($ini))->toBe($inode)
        ->and(readlink($this->geosDirectory.'/cli/conf.d/20-rodnik-geos.ini'))->toBe($ini)
        ->and(readlink($this->geosDirectory.'/fpm/conf.d/20-rodnik-geos.ini'))->toBe($ini)
        ->and(file_get_contents($unrelated))->toBe($original)
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});

test('server GEOS installer refuses an existing unmanaged extension configuration', function () {
    foreach (['mods-available', 'cli/conf.d', 'fpm/conf.d'] as $directory) {
        mkdir($this->geosDirectory.'/'.$directory, 0700, true);
    }

    $ini = $this->geosDirectory.'/mods-available/geos.ini';
    $original = "; maintained by the administrator\nextension=geos.so\n";
    file_put_contents($ini, $original);

    $process = runGeosInstaller('install-geos-server.sh', $this->geosDirectory, body: <<<'BASH'
php_root="$GEOS_TEST_DIRECTORY"
ini_file="$php_root/mods-available/rodnik-geos.ini"
check_existing_configuration
BASH);

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getErrorOutput())->toContain('Existing GEOS configuration:')
        ->and(file_get_contents($ini))->toBe($original)
        ->and(file_exists($this->geosDirectory.'/mods-available/rodnik-geos.ini'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});

test('server GEOS installer never activates a candidate that failed after building', function () {
    $process = runGeosInstaller('install-geos-server.sh', $this->geosDirectory, body: <<<'BASH'
php_root="$GEOS_TEST_DIRECTORY"
ini_file="$php_root/activated.ini"
extension_dir="$php_root/modules"
phpize_bin=/usr/bin/true
preflight() { :; }
validate_runtime() { :; }
validate_build_tools() { :; }
apt-get() { :; }
curl() { touch "$work_dir/source.tar.gz"; }
verify_checksum() { :; }
mktemp() { command mktemp -d "$GEOS_TEST_DIRECTORY/build.XXXXXXXX"; }
tar() {
    mkdir -p "$work_dir/source/modules"
    touch "$work_dir/source/modules/geos.so"
    printf '#!/bin/bash\nexit 0\n' > "$work_dir/source/configure"
    chmod 0700 "$work_dir/source/configure"
}
make() { :; }
smoke_test() { return 59; }
install_extension
BASH);

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getErrorOutput())->toContain('candidate GEOS module failed')
        ->and(file_exists($this->geosDirectory.'/activated.ini'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});

test('server GEOS installer reuses a passing managed module without rebuilding or installing packages', function () {
    foreach (['mods-available', 'cli/conf.d', 'fpm/conf.d', 'modules'] as $directory) {
        mkdir($this->geosDirectory.'/'.$directory, 0700, true);
    }

    $module = $this->geosDirectory.'/modules/geos.so';
    file_put_contents($module, 'previously compiled module');

    $process = runGeosInstaller('install-geos-server.sh', $this->geosDirectory, body: <<<'BASH'
php_root="$GEOS_TEST_DIRECTORY"
ini_file="$php_root/mods-available/rodnik-geos.ini"
preflight() { existing_module="$GEOS_TEST_DIRECTORY/modules/geos.so"; }
smoke_test() { return 0; }
verify_activation() { :; }
build_extension() {
    touch "$GEOS_TEST_DIRECTORY/unexpected-build"
    return 99
}
install_extension
BASH);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getOutput())->toContain('Reusing the existing managed GEOS module')
        ->and(file_get_contents($module))->toBe('previously compiled module')
        ->and(file_exists($this->geosDirectory.'/mods-available/rodnik-geos.ini'))->toBeTrue()
        ->and(file_exists($this->geosDirectory.'/unexpected-build'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});

test('server GEOS installer restores previous configuration when activation verification fails', function (bool $previouslyInstalled) {
    foreach (['mods-available', 'cli/conf.d', 'fpm/conf.d', 'modules'] as $directory) {
        mkdir($this->geosDirectory.'/'.$directory, 0700, true);
    }

    $ini = $this->geosDirectory.'/mods-available/rodnik-geos.ini';
    $cliLink = $this->geosDirectory.'/cli/conf.d/20-rodnik-geos.ini';
    $fpmLink = $this->geosDirectory.'/fpm/conf.d/20-rodnik-geos.ini';
    $previousIni = "; previous managed configuration\nextension=\"/previous/geos.so\"\n";
    file_put_contents($this->geosDirectory.'/modules/geos.so', 'new compiled module');

    if ($previouslyInstalled) {
        file_put_contents($ini, $previousIni);
        symlink($ini, $cliLink);
    }

    $process = runGeosInstaller('install-geos-server.sh', $this->geosDirectory, body: <<<'BASH'
php_root="$GEOS_TEST_DIRECTORY"
ini_file="$php_root/mods-available/rodnik-geos.ini"
preflight() { :; }
build_extension() { module_path="$GEOS_TEST_DIRECTORY/modules/geos.so"; }
verify_activation() { return 41; }
install_extension
BASH);

    expect($process->getExitCode())->toBe(41, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('previous INI and links have been restored')
        ->and(is_link($fpmLink))->toBeFalse()
        ->and(file_exists($fpmLink))->toBeFalse()
        ->and(file_get_contents($this->geosDirectory.'/modules/geos.so'))->toBe('new compiled module')
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();

    if ($previouslyInstalled) {
        expect(file_get_contents($ini))->toBe($previousIni)
            ->and(is_link($cliLink))->toBeTrue()
            ->and(readlink($cliLink))->toBe($ini);
    } else {
        expect(file_exists($ini))->toBeFalse()
            ->and(is_link($cliLink))->toBeFalse()
            ->and(file_exists($cliLink))->toBeFalse();
    }
})->with([
    'new installation' => [false],
    'existing installation with one missing SAPI link' => [true],
]);

test('local GEOS installer restores its previous INI when the enabled extension fails', function (bool $previouslyInstalled) {
    $ini = $this->geosDirectory.'/99-rodnik-geos.ini';
    $previousIni = "; Managed by Rodnik install-geos-local.sh.\nextension=\"/previous/geos.so\"\n";
    file_put_contents($this->geosDirectory.'/geos.so', 'new compiled module');
    mkdir($this->geosDirectory.'/backup', 0700);
    file_put_contents($this->geosDirectory.'/bin/php', "#!/bin/bash\nexit 41\n");
    chmod($this->geosDirectory.'/bin/php', 0700);

    if ($previouslyInstalled) {
        file_put_contents($ini, $previousIni);
    }

    $process = runGeosInstaller('install-geos-local.sh', $this->geosDirectory, body: <<<'BASH'
php_bin="$GEOS_TEST_DIRECTORY/bin/php"
managed_ini="$GEOS_TEST_DIRECTORY/99-rodnik-geos.ini"
work_dir="$GEOS_TEST_DIRECTORY/backup"
if [[ -f "$managed_ini" ]]; then
    cp -p "$managed_ini" "$work_dir/previous.ini"
    had_managed_ini=true
fi
trap cleanup EXIT
configuration_changed=true
activate_extension "$GEOS_TEST_DIRECTORY/geos.so"
BASH);

    expect($process->getExitCode())->toBe(41, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('Restored the previous GEOS INI')
        ->and(file_get_contents($this->geosDirectory.'/geos.so'))->toBe('new compiled module')
        ->and(is_dir($this->geosDirectory.'/backup'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();

    if ($previouslyInstalled) {
        expect(file_get_contents($ini))->toBe($previousIni);
    } else {
        expect(file_exists($ini))->toBeFalse();
    }
})->with([
    'new installation' => [false],
    'existing installation' => [true],
]);

test('GEOS installer drains FPM output after finding the module so pipefail cannot reject a loaded extension', function (string $script) {
    writeGeosFpmFixture($this->geosDirectory, "[PHP Modules]\ngeos\n".str_repeat("other_module\n", 100_000));

    $process = runGeosInstaller($script, $this->geosDirectory, body: <<<'BASH'
fpm_bin="$GEOS_TEST_DIRECTORY/bin/php-fpm"
verify_fpm_extension
BASH);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(file_get_contents($this->geosDirectory.'/fpm-arguments'))->toBe("-m\n")
        ->and(file_exists($this->geosDirectory.'/fpm-completed'))->toBeTrue()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
})->with('geos installers');

test('GEOS installer checks the FPM process result and diagnostics before accepting its module list', function (string $output, string $errorOutput, int $exitCode, bool $accepted, string $script) {
    writeGeosFpmFixture($this->geosDirectory, $output, $errorOutput, $exitCode);

    $process = runGeosInstaller($script, $this->geosDirectory, body: <<<'BASH'
fpm_bin="$GEOS_TEST_DIRECTORY/bin/php-fpm"
verify_fpm_extension
BASH);

    if ($accepted) {
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    } else {
        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getErrorOutput())->not->toBeEmpty();
    }

    expect(file_get_contents($this->geosDirectory.'/fpm-arguments'))->toBe("-m\n")
        ->and(file_exists($this->geosDirectory.'/fpm-completed'))->toBeTrue()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
})->with([
    'loaded module' => ["[PHP Modules]\ngeos\nxml\n", '', 0, true],
    'uppercase loaded module' => ["[PHP Modules]\nGEOS\nxml\n", '', 0, true],
    'missing module' => ["[PHP Modules]\nxml\n", '', 0, false],
    'unrelated module name' => ["[PHP Modules]\ngeos_extra\n", '', 0, false],
    'startup warning despite loaded module' => ["[PHP Modules]\ngeos\n", "PHP Warning: Module geos is already loaded\n", 0, false],
    'extension loading failure' => ["[PHP Modules]\ngeos\n", "Unable to load dynamic library geos.so\n", 0, false],
    'failed FPM process despite loaded module' => ["[PHP Modules]\ngeos\n", "FPM diagnostic process failed\n", 255, false],
])->with('geos installers');

test('server GEOS installer detects an unmanaged FPM extension even when the module list keeps streaming', function () {
    foreach (['mods-available', 'cli/conf.d', 'fpm/conf.d'] as $directory) {
        mkdir($this->geosDirectory.'/'.$directory, 0700, true);
    }

    writeGeosFpmFixture($this->geosDirectory, "[PHP Modules]\ngeos\n".str_repeat("other_module\n", 100_000));
    file_put_contents($this->geosDirectory.'/bin/php', "#!/bin/bash\nprintf no\n");
    chmod($this->geosDirectory.'/bin/php', 0700);

    $process = runGeosInstaller('install-geos-server.sh', $this->geosDirectory, body: <<<'BASH'
php_root="$GEOS_TEST_DIRECTORY"
ini_file="$php_root/mods-available/rodnik-geos.ini"
php_bin="$GEOS_TEST_DIRECTORY/bin/php"
fpm_bin="$GEOS_TEST_DIRECTORY/bin/php-fpm"
check_existing_configuration
BASH);

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getErrorOutput())->toContain('unmanaged GEOS extension is already loaded in FPM')
        ->and(file_exists($this->geosDirectory.'/fpm-completed'))->toBeTrue()
        ->and(file_exists($this->geosDirectory.'/mods-available/rodnik-geos.ini'))->toBeFalse()
        ->and(file_exists($this->geosDirectory.'/blocked-commands'))->toBeFalse();
});
